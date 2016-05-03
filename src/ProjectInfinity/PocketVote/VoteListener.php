<?php

namespace ProjectInfinity\PocketVote;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use ProjectInfinity\PocketVote\event\VoteEvent;

class VoteListener implements Listener {
    
    private $plugin;
    private $vm;
    
    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
        $this->vm = $plugin->getVoteManager();
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
        
        if($this->plugin->getServer()->getPlayer($event->getPlayer()) === null) {
            $this->vm->addVote($event->getPlayer(), $event->getSite(), $event->getIp());
            $this->vm->commit();
            return;
        }

        foreach($this->plugin->cmdos as $cmd) {
            $cmd = str_replace('%player', $event->getPlayer(), $cmd);
            $cmd = str_replace('%ip', $event->getIp(), $cmd);
            $cmd = str_replace('%site', $event->getSite(), $cmd);
            $this->plugin->getServer()->dispatchCommand($sender, $cmd);
        }
        
    }

    /**
     * @priority LOWEST
     */
    public function onPlayerJoin(PlayerJoinEvent $event) {
        if(!$this->vm->hasVotes($event->getPlayer()->getName())) return;

        $sender = new ConsoleCommandSender();
        foreach($this->vm->getVotes($event->getPlayer()->getName()) as $key => $vote) {
            # Iterate all commands.
            foreach($this->plugin->cmdos as $cmd) {
                $cmd = str_replace('%player', $vote['player'], $cmd);
                $cmd = str_replace('%ip', $vote['ip'], $cmd);
                $cmd = str_replace('%site', $vote['site'], $cmd);
                $this->plugin->getServer()->dispatchCommand($sender, $cmd);
            }
            $this->vm->removeVote($key);
        }
        $this->vm->commit();
    }

}