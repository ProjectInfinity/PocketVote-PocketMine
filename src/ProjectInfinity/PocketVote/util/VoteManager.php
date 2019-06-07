<?php

namespace ProjectInfinity\PocketVote\util;

use pocketmine\Player;
use ProjectInfinity\PocketVote\PocketVote;
use ProjectInfinity\PocketVote\task\ExpireVotesTask;
use ProjectInfinity\PocketVote\task\guru\AddLinkTask;
use ProjectInfinity\PocketVote\task\VRCCheckTask;

class VoteManager {
    
    private $plugin;

    /** @var $votes array  */
    private $votes;
    /** @var $loadedVRC array */
    private $loadedVRC;
    /** @var $currentVRCTasks array */
    private $currentVRCTasks;

    private $voteLink = null;
    
    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
        $this->votes = $plugin->getConfig()->get('votes', []);
        $this->loadedVRC = [];
        $this->currentVRCTasks = [];
        if($this->plugin->expiration > 0) $plugin->getScheduler()->scheduleDelayedRepeatingTask(new ExpireVotesTask(), 300, 6000);
    }

    public function addVRC($record): void {
        $this->loadedVRC[] = $record;
    }

    public function getVRC(): array {
        return $this->loadedVRC;
    }

    public function removeVRCTask($player): void {
        if(!isset($this->currentVRCTasks[$player])) return;
        unset($this->currentVRCTasks[$player]);
    }

    public function scheduleVRCTask($player) : void {
        if(PocketVote::$hasVRC) {
            if(isset($this->currentVRCTasks[$player])) return;
            $this->plugin->getServer()->getAsyncPool()->submitTask(new VRCCheckTask($player));
        }
    }

    public function getVoteLink() {
        return $this->voteLink;
    }

    public function setVoteLink($link): void {
        $this->voteLink = $link;
    }

    public static function addLink(Player $player, ?array $data): void {
        if(!$data) return;
        if(!$data['url']) {
            $player->sendForm(PocketVote::getPlugin()->getFormManager()->createLinkAddForm(true));
            return;
        }
        PocketVote::getPlugin()->getServer()->getAsyncPool()->submitTask(new AddLinkTask($player->getName(), $data['title'] === '' ? $data['url'] : $data['title'], $data['url']));
    }
    
    public function hasVotes($player): bool {
        $player = strtolower($player);
        foreach($this->votes as $key => $vote) {
            if(strtolower($vote['player']) === $player) return true;
        }
        return false;
    }
    
    public function getVotes($player): array {
        $votes = [];
        $player = strtolower($player);
        foreach($this->votes as $key => $vote) {
            if(strtolower($vote['player']) === $player) {
                $votes[$key] = $vote;
            }
        }
        return $votes;
    } 
    
    public function addVote($player, $site, $ip): void {
        $this->votes[] = ['player' => $player, 'site' => $site, 'ip' => $ip, 'expires' => time() + $this->plugin->expiration];
    }
    
    public function removeVote($key): void {
        unset($this->votes[$key]);
    }

    public function commit(): void {
        $this->plugin->getConfig()->set('votes', array_values($this->votes));
        $this->plugin->saveConfig();
    }

    public function expireVotes(): void {
        $expired = 0;
        $ts = time();
        foreach($this->votes as $key => $vote) {
            if(!isset($vote['expires']) || $ts >= $vote['expires']) {
                $this->removeVote($key);
                $expired++;
            }
        }
        $this->commit();
        $this->plugin->getLogger()->debug('Expired '.$expired.' votes.');
    }

}