<?php

namespace ProjectInfinity\PocketVote\task\guru;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;

class GetLinksTask extends GuruTask {

    public $player;

    public function __construct($player) {
        parent::__construct(PocketVote::$dev ? 'http://dev.mcpe.guru/api/links' : 'https://mcpe.guru/api/links');
        $this->player = $player;
    }

    public function onRun() {
        parent::onRun();
    }

    public function onCompletion(Server $server) {
        $player = $this->player === 'CONSOLE' ? new ConsoleCommandSender() : $server->getPlayer($this->player);

        # Player is offline.
        if($player === null) return;

        if(!$this->hasResult()) {
            $player->sendMessage(TextFormat::RED.'Got no response when retrieving links from MCPE.Guru');
            return;
        }

        $result = $this->getResult();

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

        if($result->success && (!isset($result->payload) || $result->success && count($result->payload) === 0)) {
            $player->sendMessage(TextFormat::YELLOW.'There are no added links, use '.TextFormat::AQUA.'/guadd [url]'.TextFormat::YELLOW.' to add a link!');
            return;
        }

        $color = true;
        foreach($result->payload as $link) {
            $c = $color ? TextFormat::AQUA : TextFormat::YELLOW;
            $player->sendMessage($c.'ID: '.$link->id);
            $player->sendMessage($c.'Title: '.$link->title);
            $player->sendMessage($c.'URL: '.$link->url);

            $color = !$color;
        }

    }
}