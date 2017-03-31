<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;

class VoteLinkTask extends AsyncTask {

    public $isDev;
    public $cert;
    public $identity;
    public $version;

    public function __construct($identity) {
        $this->isDev = PocketVote::$dev;
        $this->cert = PocketVote::$cert;
        $this->identity = $identity;
        $this->version = PocketVote::getPlugin()->getDescription()->getVersion();
    }

    # TODO: Change echos to logging in onCompletion.
    public function onRun() {
        $curl = curl_init($this->isDev ? 'http://dev.mcpe.guru/api/link' : 'https://mcpe.guru/api/link');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => $this->isDev ? 80 : 443,
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
            echo PHP_EOL.curl_error($curl).PHP_EOL;
            echo curl_errno($curl).PHP_EOL;
        } else {
            $result = json_decode($res);

            # Check if result is valid JSON.
            if(empty($result)) {
                $this->setResult(false);
                curl_close($curl);
                return;
            }

            $this->setResult($result);
        }

        curl_close($curl);
    }

    public function onCompletion(Server $server) {
        $server->getLogger()->debug('[PocketVote] Attempting to retrieve vote link.');

        if(!$this->hasResult()) {
            $server->getLogger()->warning('[PocketVote] Failed to retrieve voting link from MCPE.guru, the API may be down.');
        }
        $result = $this->getResult();

        if(!$result->success) {
            $server->getLogger()->warning('[PocketVote] Server not found when attempting to retrieve vote link, is your identity correct?');
            return;
        }

        if($result->success && !isset($result->payload )) {
            $server->getLogger()->info('[PocketVote] No vote link to retrieve, add a entry using /guru to create a link.');
            return;
        }

        if($result->success && isset($result->payload->url)) {
            $server->getLogger()->info(TextFormat::YELLOW.'[PocketVote] Voting link set to '.$result->payload->url);
            PocketVote::getPlugin()->getVoteManager()->setVoteLink($result->payload->url);
        }
    }
}