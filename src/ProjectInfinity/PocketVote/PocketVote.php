<?php

namespace ProjectInfinity\PocketVote;

use pocketmine\plugin\PluginBase;
use ProjectInfinity\PocketVote\cmd\PocketVoteCommand;
use ProjectInfinity\PocketVote\task\SchedulerTask;

class PocketVote extends PluginBase {

    /** @var PocketVote $plugin */
    private static $plugin;

    public $identity;
    public $secret;

    public $cmds;
    
    public static $cert;

    private $voteManager;

    public function onEnable() {
        self::$plugin = $this;
        $this->saveDefaultConfig();
        
        # Save and load certificates.
        #$this->saveResource('cacert.pem', true);
        self::$cert = $this->getDataFolder().'cacert.pem';
        if(!file_exists(self::$cert)) {
            $curl = curl_init('https://curl.haxx.se/ca/cacert.pem');

            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_PORT => 443,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $res = curl_exec($curl);

            if($res === false) {
                $this->getLogger()->critical(curl_error($curl));
                $this->getLogger()->critical(curl_errno($curl));
            } else {
                $this->getLogger()->info('Downloading cURL root CA.');
                $file = fopen(self::$cert, 'w+');
                fwrite($file, $res);
                fclose($file);
                $this->getLogger()->info('Finished downloading cURL root CA.');
            }

            curl_close($curl);
        }
        
        $this->identity = $this->getConfig()->get('identity', null);
        $this->secret = $this->getConfig()->get('secret', null);
        $this->cmds = $this->getConfig()->getNested('onvote.run-cmd', []);
        #$this->voteManager = new VoteManager();
        $this->getServer()->getCommandMap()->register('pocketvote', new PocketVoteCommand($this));
        $this->getServer()->getPluginManager()->registerEvents(new VoteListener($this), $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new SchedulerTask($this), 1200); # 1200 ticks = 60 seconds.
    }

    public function onDisable() {
        self::$plugin = null;
        self::$cert = null;
        unset($this->identity, $this->secret, $this->voteManager, $this->cmds);
    }
    
    public static function getPlugin(): PocketVote {
        return self::$plugin;
    }

}