<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\scheduler\Task;
use ProjectInfinity\PocketVote\PocketVote;

class SchedulerTask extends Task {

    private $plugin;
    private $version;
    private $lastTopVote;

    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
        $this->version = $plugin->getDescription()->getVersion();
        $this->lastTopVote = -900; // Ensures that TopVoterTask will fire immediately.
    }

    public function onRun(): void {
        $this->plugin->getLogger()->debug('Checking for pending votes');
        $this->plugin->getServer()->getAsyncPool()->submitTask(new VoteCheckTask($this->plugin->identity, $this->plugin->secret, $this->version));

        // Get top voters on first run and then every 15 minutes.
        if((time() - $this->lastTopVote) >= 900) {
            $this->plugin->getLogger()->debug('Getting top voters');
            $this->plugin->getServer()->getAsyncPool()->submitTask(new TopVoterTask($this->plugin->identity));
            $this->lastTopVote = time();
        }
    }
}