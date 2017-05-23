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

    public $multiserver;
    public $mysql_host, $mysql_port, $mysql_username, $mysql_password, $mysql_database;
    
    public function __construct($identity, $secret, $version) {
        $this->isDev = PocketVote::$dev;
        $this->identity = $identity;
        $this->secret = $secret;
        $this->version = $version;
        $this->cert = PocketVote::$cert;
        $this->mysql_host = PocketVote::getPlugin()->mysql_host;
        $this->mysql_port = PocketVote::getPlugin()->mysql_port;
        $this->mysql_username = PocketVote::getPlugin()->mysql_username;
        $this->mysql_password = PocketVote::getPlugin()->mysql_password;
        $this->mysql_database = PocketVote::getPlugin()->mysql_database;
        $this->multiserver = PocketVote::getPlugin()->multiserver;
    }

    public function onRun() {
        $curl = curl_init($this->isDev ? 'http://127.0.0.1/v2/check' : 'https://api.pocketvote.io/v2/check');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => $this->isDev ? 9000 : 443,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_USERAGENT => 'PocketVote v'.$this->version,
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

                if($this->multiserver && count($votes) > 0) {
                    $db = new \mysqli($this->mysql_host, $this->mysql_username, $this->mysql_password, $this->mysql_database, $this->mysql_port);
                    # Ensure we are actually connected.
                    if(!$db->ping()) {
                        curl_close($curl);
                        return;
                    }
                    $time = time();
                    foreach($votes as $vote) {
                        $stmt = $db->prepare('INSERT INTO `pocketvote_votes` (`player`, `ip`, `site`, `timestamp`) VALUES (?, ?, ?, ?)');
                        $stmt->bind_param('sssi', $vote->player, $vote->ip, $vote->site, $time);
                        $stmt->execute();
                        $stmt->close();
                    }

                    # All done.
                    $db->close();
                }
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
    
    public function onCompletion(Server $server) {

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
            Server::getInstance()->getPluginManager()->callEvent(
                new VoteEvent(PocketVote::getPlugin(),
                    $vote->player,
                    $vote->ip,
                    $vote->site
                )
            );
        }
    }
}