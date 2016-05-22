<?php

namespace ProjectInfinity\PocketVote\event;

use ProjectInfinity\PocketVote\PocketVote;

class VoteDispatchEvent extends VoteEvent {

    public static $handlerList = null;

    public function __construct(PocketVote $plugin, $player, $ip, $site) {
        parent::__construct($plugin, $player, $ip, $site);
    }

    /**
     * @return string
     */
    public function getPlayer() {
        return parent::getPlayer();
    }

    /**
     * @return string
     */
    public function getIp() {
        return parent::getIp();
    }

    /**
     * @return string
     */
    public function getSite() {
        return parent::getSite();
    }

}