<?php

namespace ProjectInfinity\PocketVote\cmd\guru;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;
use ProjectInfinity\PocketVote\task\guru\GetLinksTask;

class GuListCommand extends Command {

    public function __construct(PocketVote $plugin) {
        parent::__construct('gulist', 'MCPE Guru list links command', '/gulist', ['gul']);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, String $commandLabel, array $args): bool {
        if(!$sender->hasPermission('pocketvote.admin')) {
            $sender->sendMessage(TextFormat::RED.'You do not have permission to do that.');
            return true;
        }
        PocketVote::getPlugin()->getServer()->getAsyncPool()->submitTask(new GetLinksTask($sender->getName()));
        return true;
    }
}