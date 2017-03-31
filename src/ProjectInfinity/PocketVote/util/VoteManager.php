<?php

namespace ProjectInfinity\PocketVote\util;

use ProjectInfinity\PocketVote\PocketVote;

class VoteManager {
    
    private $plugin;
    
    private $votes;

    private $voteLink = null;
    
    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
        $this->votes = $plugin->getConfig()->get('votes', []);
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
        $this->votes[] = ['player' => $player, 'site' => $site, 'ip' => $ip];
    }
    
    public function removeVote($key) {
        unset($this->votes[$key]);
    }

    public function commit() {
        $this->plugin->getConfig()->set('votes', array_values($this->votes));
        $this->plugin->saveConfig();
    }

}