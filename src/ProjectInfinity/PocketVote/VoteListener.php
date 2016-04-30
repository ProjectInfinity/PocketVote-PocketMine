<?php

namespace ProjectInfinity\PocketVote;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use ProjectInfinity\PocketVote\event\VoteEvent;

class VoteListener implements Listener {
    
    private $plugin;
    
    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @priority LOWEST
     */
    public function onVoteEvent(VoteEvent $event) {
        if($event->isCancelled()) return;

        $sender = new ConsoleCommandSender();
        foreach($this->plugin->cmds as $cmd) {
            $cmd = str_replace('%player', $event->getPlayer(), $cmd);
            $cmd = str_replace('%ip', $event->getIp(), $cmd);
            $cmd = str_replace('%site', $event->getSite(), $cmd);
            $this->plugin->getServer()->dispatchCommand($sender, $cmd);
        }
    }

}