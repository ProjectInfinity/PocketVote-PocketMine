<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;

class TopVoterTask extends AsyncTask {

    public $isDev, $cert, $identity, $version;

    public function __construct($identity) {
        $this->identity = $identity;
        $this->isDev = PocketVote::$dev;
        $this->cert = PocketVote::$cert;
        $this->version = PocketVote::getPlugin()->getDescription()->getVersion();
    }

    public function onRun(): void {
        $curl = curl_init($this->isDev ? 'http://127.0.0.1/v2/top/10' : 'https://api.pocketvote.io/v2/top/10');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => $this->isDev ? 9000 : 443,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_USERAGENT => 'PocketVote '.$this->version,
            CURLOPT_HTTPHEADER => ['Identity: '.$this->identity],
        ]);

        $res = curl_exec($curl);

        if($res === false) {
            $this->setResult((object)['success' => false, 'error' => curl_error($curl)]);
            curl_close($curl);
            return;
        }

        curl_close($curl);
        $this->setResult(json_decode($res, true));
    }

    public function onCompletion(Server $server): void {
        if(!$this->hasResult()) {
            $server->getLogger()->error('[PocketVote] TopVoterTask - Failed to retrieve top voters. Try again later.');
            return;
        }

        $result = $this->getResult();

        if(!$result['success'] && isset($result['error'])) {
            $server->getLogger()->error('[PocketVote] TopVoterTask - An error occurred while contacting the PocketVote servers, please try again later.');
            $server->getLogger()->error('[PocketVote] curl error occurred during TopVoterTask: '.$result['error']);
            return;
        }

        if(!$result['success']) {
            $server->getLogger()->error('[PocketVote] TopVoterTask - An error occurred while contacting the PocketVote servers, please try again later.');
            return;
        }

        if(!isset($result['payload'])) {
            $server->getLogger()->error('[PocketVote] TopVoterTask - Error! No payload.');
            return;
        }

        if(count($result['payload']) === 0) return;

        PocketVote::getPlugin()->setTopVoters($result['payload']);
    }
}