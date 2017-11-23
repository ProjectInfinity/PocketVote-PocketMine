<?php

namespace ProjectInfinity\PocketVote\util;

use ProjectInfinity\PocketVote\PocketVote;
use ProjectInfinity\PocketVote\task\ExpireVotesTask;
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
        if($this->plugin->expiration > 0) $plugin->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new ExpireVotesTask(), 300, 6000);
    }

    public function addVRC($record) {
        $this->loadedVRC[] = $record;
    }

    public function getVRC(): array {
        return $this->loadedVRC;
    }

    public function removeVRCTask($player) {
        if(!isset($this->currentVRCTasks[$player])) return;
        unset($this->currentVRCTasks[$player]);
    }

    public function scheduleVRCTask($player) {
        if((PocketVote::$hasVRC && !$this->plugin->multiserver) || (PocketVote::$hasVRC && $this->plugin->multiserver && $this->plugin->multiserver_role === 'master')) {
            if(isset($this->currentVRCTasks[$player])) return;
            # Only run when VRC is enabled and multiserver is off or VRC is enabled and multiserver and server role is set to master.
            $this->plugin->getServer()->getScheduler()->scheduleAsyncTask(new VRCCheckTask($player));
        }
    }

    public function getVoteLink() {
        return $this->voteLink;
    }

    public function setVoteLink($link) {
        $this->voteLink = $link;
    }
    
    public function hasVotes($player) {
        $player = strtolower($player);
        foreach($this->votes as $key => $vote) {
            if(strtolower($vote['player']) === $player) return true;
        }
        return false;
    }
    
    public function getVotes($player) {
        $votes = [];
        $player = strtolower($player);
        foreach($this->votes as $key => $vote) {
            if(strtolower($vote['player']) === $player) {
                $votes[$key] = $vote;
            }
        }
        return $votes;
    } 
    
    public function addVote($player, $site, $ip) {
        $this->votes[] = ['player' => $player, 'site' => $site, 'ip' => $ip, 'expires' => time() + $this->plugin->expiration];
    }
    
    public function removeVote($key) {
        unset($this->votes[$key]);
    }

    public function commit() {
        $this->plugin->getConfig()->set('votes', array_values($this->votes));
        $this->plugin->saveConfig();
    }

    public function expireVotes() {
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