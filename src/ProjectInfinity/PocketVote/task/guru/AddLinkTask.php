<?php

namespace ProjectInfinity\PocketVote\task\guru;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\lib\Firebase\JWT;
use ProjectInfinity\PocketVote\PocketVote;

class AddLinkTask extends GuruTask {

    public $player;
    public $link;
    public $cert;

    public function __construct($player, $title, $link) {
        $this->cert = PocketVote::$cert;
        $this->player = $player;
        $this->link = $link;
        parent::__construct(PocketVote::$dev ? 'http://dev.mcpe.guru/api/link' : 'https://mcpe.guru/api/link', 'POST',
            ['token' => JWT::encode(['title' => $title, 'url' => $link], PocketVote::getPlugin()->secret)]
        );
    }

    public function onRun(): void {
        $ch = curl_init($this->link);
        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);
        $exists = curl_exec($ch) !== false;
        curl_close($ch);

        if($exists) {
            parent::onRun();
        } else {
            $this->setResult(null);
        }
    }

    public function onCompletion(Server $server) {
        $player = $this->player === 'CONSOLE' ? new ConsoleCommandSender() : $server->getPlayer($this->player);

        if($player === null) return;

        if(!$this->hasResult()) {
            $player->sendMessage(TextFormat::RED.'Got no response when adding link to MCPE.Guru');
            return;
        }

        $result = $this->getResult();

        if($result === null) {
            $player->sendMessage(TextFormat::RED.'The provided link could not be queried. Make sure it is correctly typed.');
            return;
        }

        # Invalid response.
        if(!$result) {
            $player->sendMessage(TextFormat::RED.'The response from MCPE.Guru was not valid JSON.');
            return;
        }

        if(isset($result->error)) {
            $player->sendMessage(TextFormat::RED.'An error occurred while performing this command.');
            $server->getLogger()->error('[PocketVote] Curl error: '.$result->error);
            return;
        }

        if(!$result->success) {
            $player->sendMessage(TextFormat::RED.'Failed to add link. API response:');
            $player->sendMessage(TextFormat::RED.$result->message);
            return;
        }

        if($result->success) {
            $player->sendMessage(TextFormat::GREEN.'Link added!');
            if(isset($result->payload->url) && PocketVote::getPlugin()->getVoteManager()->getVoteLink() === null) PocketVote::getPlugin()->getVoteManager()->setVoteLink($result->payload->url);
            return;
        }
    }

}