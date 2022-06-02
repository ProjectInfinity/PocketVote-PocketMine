<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use ProjectInfinity\PocketVote\data\TaskResult;
use ProjectInfinity\PocketVote\event\VoteEvent;
use ProjectInfinity\PocketVote\lib\Firebase\JWT;
use ProjectInfinity\PocketVote\PocketVote;

class VoteCheckTask extends AsyncTask {

    public $isDev;

    public $identity;
    public $secret;
    public $version;
    public $cert;
    
    public function __construct($identity, $secret, $version) {
        $this->isDev = PocketVote::$dev;
        $this->identity = $identity;
        $this->secret = $secret;
        $this->version = $version;
        $this->cert = PocketVote::$cert;
    }

    public function onRun(): void {
        $curl = curl_init($this->isDev ? 'http://127.0.0.1/v2/check' : 'https://api.pocketvote.io/v2/check');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => $this->isDev ? 9000 : 443,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_USERAGENT => 'PocketVote '.$this->version,
            CURLOPT_HTTPHEADER => ['Identity: '.$this->identity]
        ]);

        $res = curl_exec($curl);

        if($res === false) {
            $this->setResult($this->createResult(true, null, curl_error($curl)));
        } else {

            $result = json_decode($res);

            if(isset($result->code) && $result->code === 'InternalError') {
                $this->setResult($this->createResult(true, $result));
                return;
            }

            if($result->success && isset($result->payload)) {
                JWT::$leeway = 120;
                try {
                    $decoded = JWT::decode($result->payload, $this->secret, array('HS256'));
                } catch(\Exception $e) {
                    $this->setResult($this->createResult(true, $result, $e->getMessage()));
                    return;
                }

                $decoded_array = (array)$decoded;
                unset($decoded_array['iat']);

                $votes = [];

                foreach($decoded_array as $key => $vote) {
                    $votes[] = $vote;
                }

                $r = $this->createResult(false, $result);
                $r->setVotes($votes[0]);
                $this->setResult($r);
            }

            # No outstanding votes.
            if($result->success && !isset($result->payload)) {
                $this->setResult($this->createResult(false, $result));
            }

            if(!$result->success) {
                $this->setResult($this->createResult(true, $result));
            }
        }
        curl_close($curl);
    }

    private function createResult($error, $res, $customError = null) {
        $r = new TaskResult();
        $r->setError($error);
        if($error) {
            if(!isset($customError)) {
                $r->setErrorData(['message' => $res->message]);
            }
            else {
                $r->setErrorData(['message' => $customError]);
            }
        }
        # Had votes.
        if(isset($res->payload) && $res->success) $r->setVotes($res->payload);

        if(isset($res->meta)) $r->setMeta($res->meta);

        return $r;
    }
    
    public function onCompletion(): void {
        $server = Server::getInstance();
        if(!$this->hasResult()) {
            $server->getLogger()->emergency('A request finished without a response from the API. It may have failed to be sent.');
            return;
        }

        $result = $this->getResult();

        if(!($result instanceof TaskResult)) {
            $server->getLogger()->warning('VoteCheckTask result was not an instance of TaskResult');
            return;
        }

        # Set meta.
        PocketVote::getPlugin()->startScheduler($result->getMeta()->frequency ?? 60);

        if($result->hasError()) {
            $server->getLogger()->error('[PocketVote] VoteCheckTask: '.$result->getError()['message']);
            return;
        }

        foreach($result->getVotes() as $key => $vote) {
            (new VoteEvent(PocketVote::getPlugin(), $vote->player, $vote->ip, $vote->site))->call();
        }
    }
}