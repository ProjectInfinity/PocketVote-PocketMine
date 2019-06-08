<?php

namespace ProjectInfinity\PocketVote\api;

use ProjectInfinity\PocketVote\PocketVote;

class PocketVoteAPI {

    private $plugin;

    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Retrieves the array of the top voters this month.
     * This array is updated every 15 minutes.
     *
     * Example: [['player' => 'ProjectInfinity', 'votes' => 42], ['player' => 'DevInfinity', 'votes' => 37]]
     * @return array
     */
    public function getTopVoters(): array {
        return $this->plugin->getTopVoters();
    }
}