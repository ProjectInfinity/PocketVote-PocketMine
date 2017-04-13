<?php

namespace ProjectInfinity\PocketVote\task\guru;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;

class DeleteLinkTask extends GuruTask {

    public $player, $id;

    public function __construct($player, $id) {
        $this->player = $player;
        $this->id = $id;
        parent::__construct(PocketVote::$dev ? "http://dev.mcpe.guru/api/link/$id" : "https://mcpe.guru/api/link/$id",
            'DELETE');
    }

    public function onRun() {
        parent::onRun();
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
            $player->sendMessage(TextFormat::RED.'The provided link could not be deleted.');
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

        if(!$result->success && !isset($result->payload)) {
            $player->sendMessage(TextFormat::RED.'Couldn\'t find your server.');
            return;
        }

        if(!$result->success && isset($result->payload)) {
            if($result->payload->error->code === 403) $player->sendMessage(TextFormat::RED.'You do not have permission to delete that link.');
            if($result->payload->error->code === 500) $player->sendMessage(TextFormat::RED.'Failed to delete link.');
            return;
        }

        if($result->success) {
            $player->sendMessage(TextFormat::GREEN.'Successfully deleted link.');
            return;
        }
    }

}