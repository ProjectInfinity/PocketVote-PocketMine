<?php

namespace ProjectInfinity\PocketVote\cmd\guru;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;
use ProjectInfinity\PocketVote\task\guru\AddLinkTask;

class GuAddCommand extends Command {

    private $plugin;

    public function __construct(PocketVote $plugin) {
        parent::__construct('guadd', 'MCPE Guru Add link command', '/guadd [url]', ['gua']);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, String $commandLabel, array $args) {
        if(!$sender->hasPermission('pocketvote.admin')) {
            $sender->sendMessage(TextFormat::RED.'You do not have permission to do that!');
            return true;
        }

        if(count($args) < 1) {
            $sender->sendMessage(TextFormat::RED.'Not enough arguments, example: /guadd Title_Name http://example.com');
            $sender->sendMessage(TextFormat::RED.'A title is optional but recommended.');
            return true;
        }

        if(count($args) > 2) {
            $sender->sendMessage(TextFormat::RED.'Too many arguments, example: /guadd Title_Name http://example.com');
            $sender->sendMessage(TextFormat::RED.'A title is optional but recommended.');
            return true;
        }

        $title = null;
        $link = null;

        switch(count($args)) {
            case 1:
                $title = null;
                $link = $args[0];
                break;

            case 2:
                $s = mb_strtolower(mb_substr( $args[0], 0, 4 ));
                if($s === 'http' || $s === 'www.') {
                    list($link, $title) = $args;
                } else {
                    list($title, $link) = $args;
                }
                $s = null;
                break;
        }

        $this->plugin->getServer()->getScheduler()->scheduleAsyncTask(new AddLinkTask($sender->getName(), $title, $link));
        return true;
    }
}