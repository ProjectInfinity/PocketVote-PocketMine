<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\lib\Firebase\JWT;
use ProjectInfinity\PocketVote\PocketVote;

class DiagnoseTask extends AsyncTask {
    public $identity;
    public $isDev;
    public $cert;
    public $secret;
    public $version;
    public $player;

    public function __construct($version, $player) {
        $this->identity = PocketVote::getPlugin()->getConfig()->get('identity', null);
        $this->secret = PocketVote::getPlugin()->getConfig()->get('secret', null);
        $this->isDev = PocketVote::$dev;
        $this->cert = PocketVote::$cert;
        $this->version = $version;
        $this->player = $player;
    }

    public function onRun() {

        if($this->identity === null) return;

        $curl = curl_init($this->isDev ? 'http://127.0.0.1/v2/diagnose' : 'https://api.pocketvote.io/v2/diagnose');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => $this->isDev ? 9000 : 443,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_USERAGENT => 'PocketVote v'.$this->version,
            CURLOPT_HTTPHEADER => ['Identity: '.$this->identity],
        ]);

        $res = curl_exec($curl);

        if($res === false) {
            $this->setResult((object)['success' => false, 'error' => curl_error($curl)]);
            curl_close($curl);
            return;
        }

        curl_close($curl);
        $this->setResult(json_decode($res));
    }

    public function onCompletion(Server $server) {
        $player = $this->player === 'CONSOLE' ? new ConsoleCommandSender() : $server->getPlayer($this->player);
        if($player === null) return;

        if(!$this->hasResult()) {
            $player->sendMessage(TextFormat::RED.'Failed to diagnose PocketVote. Try again later.');
            return;
        }

        $result = $this->getResult();

        if(!$result->success && isset($result->error)) {
            $player->sendMessage(TextFormat::RED.'An error occurred while contacting the PocketVote servers, please try again later.');
            $server->getLogger()->error('[PocketVote] curl error occurred during DiagnoseTask: '.$result->error);
            return;
        }

        if(isset($result->payload)) {
            $player->sendMessage(($result->payload->foundServer ? TextFormat::GREEN.'✔' : TextFormat::RED.'✖').' Found server');
            $player->sendMessage(($result->payload->hasVotes ? TextFormat::GREEN.'✔' : TextFormat::RED.'✖').' Has votes (trivial)');

            try {
                JWT::$leeway = 60;
                $token = JWT::decode($result->payload->voteSample, $this->secret, array('HS256'));
                if($token->player === 'PocketVoteSample' && $token->ip === '127.0.0.1' && $token->site === 'PocketVote.io') {
                    $player->sendMessage((TextFormat::GREEN.'✔').' Decode sample vote');
                } else {
                    throw new \ErrorException('Token did not meet expectations');
                }
            } catch(\Exception $e) {
                $player->sendMessage((TextFormat::RED.'✖').' Decode sample vote');
                $player->sendMessage(TextFormat::YELLOW.'Reason: '.$e->getMessage());
            }

            $player->sendMessage(TextFormat::YELLOW.'A test vote will be dispatched momentarily. If more than a couple of minutes passes the dispatch has failed.');
        } else {
            $player->sendMessage(TextFormat::RED.'Please wait before trying to use this command again.');
        }
    }
}