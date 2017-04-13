<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\scheduler\Task;
use ProjectInfinity\PocketVote\PocketVote;

class SchedulerTask extends Task {

    private $plugin;
    private $version;

    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
        $this->version = $plugin->getDescription()->getVersion();
    }

    public function onRun($currentTick) {
        $this->plugin->getLogger()->debug('Checking for outstanding votes.');
        if(!$this->plugin->multiserver || ($this->plugin->multiserver && strtolower($this->plugin->multiserver_role) === 'master')) $this->plugin->getServer()->getScheduler()->scheduleAsyncTask(new VoteCheckTask($this->plugin->identity, $this->plugin->secret, $this->version));
        if($this->plugin->multiserver && strtolower($this->plugin->multiserver_role) === 'slave') $this->plugin->getServer()->getScheduler()->scheduleAsyncTask(new SlaveCheckTask());
    }
}