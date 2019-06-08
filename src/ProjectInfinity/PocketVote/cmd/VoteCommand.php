<?php

namespace ProjectInfinity\PocketVote\cmd;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;

class VoteCommand extends Command implements PluginIdentifiableCommand {

    private $plugin;

    public function __construct(PocketVote $plugin) {
        parent::__construct('vote', 'PocketVote vote command', '/vote', ['v']);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, String $commandLabel, array $args) {
        if(!$sender->hasPermission('pocketvote.vote')) {
            $sender->sendMessage(TextFormat::RED.'You do not have permission use /vote.');
            return true;
        }

        if(isset($args[0]) && strtoupper($args[0]) === 'TOP') {
            $topVoters = $this->plugin->getTopVoters();

            if(count($topVoters) === 0) {
                $sender->sendMessage(TextFormat::GRAY.'No voters found, start voting!');
                return true;
            }

            $sender->sendMessage(TextFormat::AQUA.'### Current top 10 voters ###');
            $rank = 1;
            $color = true;

            foreach($topVoters as $voter) {
                $sender->sendMessage(($color ? TextFormat::WHITE : TextFormat::GRAY).$rank.'. '.$voter['player'].' ('.$voter['votes'].')');
                $rank++;
                $color = !$color;
            }

            return true;
        }

        $link = $this->plugin->getVoteManager()->getVoteLink();
        if($link === null) {
            if($sender->hasPermission('pocketvote.admin')) {
                $sender->sendMessage(TextFormat::YELLOW.'You can add a link by typing /guadd');
                $sender->sendMessage(TextFormat::YELLOW.'See /guru for help!');
            } else {
                $sender->sendMessage(TextFormat::YELLOW.'The server operator has not added any voting sites.');
            }
            return true;
        }
        if($sender->hasPermission('pocketvote.admin')) $sender->sendMessage(TextFormat::YELLOW.'Use /guru to manage this link.');
        $sender->sendMessage(TextFormat::AQUA.'You can vote at '.$link);
        return true;
    }

    public function getPlugin(): Plugin {
        return $this->plugin;
    }
}