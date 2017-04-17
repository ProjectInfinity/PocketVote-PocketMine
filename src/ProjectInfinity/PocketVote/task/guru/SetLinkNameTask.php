<?php

namespace ProjectInfinity\PocketVote\task\guru;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\lib\Firebase\JWT;
use ProjectInfinity\PocketVote\PocketVote;

class SetLinkNameTask extends GuruTask {

    public $player, $name;

    public function __construct($player, $name) {
        parent::__construct(PocketVote::$dev ? 'http://dev.mcpe.guru/api/name' : 'https://mcpe.guru/api/name', 'POST',
            [
                'token' => JWT::encode(
                    [
                        'name' => $name
                    ],
                    PocketVote::getPlugin()->secret
                )
            ]);
        $this->player = $player;
        $this->name = $name;
    }

    public function onCompletion(Server $server) {
        $player = $this->player === 'CONSOLE' ? new ConsoleCommandSender() : $server->getPlayer($this->player);

        # Player is offline.
        if($player === null) return;

        if(!$this->hasResult()) {
            $player->sendMessage(TextFormat::RED . 'Got no response when retrieving links from MCPE.Guru');
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

        # Server not found.
        if(!$result->success && !isset($result->payload)) {
            $player->sendMessage(TextFormat::RED.'Server not found.');
            $server->getLogger()->error('[PocketVote] API responded with server not found during SetLinkNameTask.');
            return;
        }

        if(!$result->success && isset($result->payload->error)) {
            switch($result->payload->error->code) {
                case 100:
                    $player->sendMessage(TextFormat::RED.'A token error occurred, check your server log for more info!');
                    $server->getLogger()->error('[PocketVote] Token error during SetLinkNameTask:');
                    $server->getLogger()->error('[PocketVote] '.$result->payload->error->message);
                    return;

                case 400:
                    $player->sendMessage(TextFormat::RED.'The API did not receive a name, please try again.');
                    return;

                case 403:
                    $player->sendMessage(TextFormat::RED.'Provided name is already in use, please choose a different one.');
                    return;

                case 500:
                    $player->sendMessage(TextFormat::RED.'Failed to update name. Please try again later.');
                    return;

                default:
                    $player->sendMessage(TextFormat::DARK_RED.'An unexpected error occurred.');
                    $server->getLogger()->error('[PocketVote] Uncaught error code occurred during SetLinkNameTask!');
                    return;
            }
        }

        if($result->success) {
            PocketVote::getPlugin()->getVoteManager()->setVoteLink('mcpe.guru/'.$this->name);
            $player->sendMessage(TextFormat::GREEN.'Your link is now '.PocketVote::getPlugin()->getVoteManager()->getVoteLink());
        }

    }
}