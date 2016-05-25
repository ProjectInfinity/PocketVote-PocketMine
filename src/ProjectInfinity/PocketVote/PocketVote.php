<?php

namespace ProjectInfinity\PocketVote;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\cmd\PocketVoteCommand;
use ProjectInfinity\PocketVote\task\SchedulerTask;
use ProjectInfinity\PocketVote\util\VoteManager;

class PocketVote extends PluginBase {

    /** @var PocketVote $plugin */
    private static $plugin;

    public $lock;
    public $identity;
    public $secret;

    public $multiserver;
    public $multiserver_role;
    public $mysql_host, $mysql_port, $mysql_username, $mysql_password, $mysql_database;

    public $cmds;
    public $cmdos;
    
    public static $cert;

    /** @var VoteManager $voteManager */
    private $voteManager;

    public function onEnable() {
        self::$plugin = $this;
        $this->saveDefaultConfig();
        $this->updateConfig();
        
        # Save and load certificates.
        self::$cert = $this->getDataFolder().'cacert.pem';
        if(!file_exists(self::$cert)) {

            $this->getLogger()->warning('Could not find cacert.pem, downloading it now.');

            $curl = curl_init('https://curl.haxx.se/ca/cacert.pem');

            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_PORT => 443,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT => 10
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

        $this->multiserver = $this->getConfig()->getNested('multi-server.enabled', false);
        $this->multiserver_role = $this->getConfig()->getNested('multi-server.role', 'master');
        $this->mysql_host = $this->getConfig()->getNested('multi-server.mysql.host', 'localhost');
        $this->mysql_port = $this->getConfig()->getNested('multi-server.mysql.port', 3306);
        $this->mysql_username = $this->getConfig()->getNested('multi-server.mysql.username', 'pocketvote');
        $this->mysql_password = $this->getConfig()->getNested('multi-server.mysql.password', 'pocketvote');
        $this->mysql_database = $this->getConfig()->getNested('multi-server.mysql.database', 'pocketvote');

        if($this->multiserver) {
            $db = new \mysqli($this->mysql_host, $this->mysql_username, $this->mysql_password, $this->mysql_database, $this->mysql_port);
            # Ensure we are actually connected.
            if(!$db->ping()) {
                $this->getLogger()->critical('Failed to get connection to database.');
            }

            # Ensure tables exist.
            $resource = $this->getResource('create_votes_table.sql');
            $db->query(stream_get_contents($resource));
            fclose($resource);
            $resource = $this->getResource('create_checks_table.sql');
            $db->query(stream_get_contents($resource));
            fclose($resource);

            # All done.
            $db->close();
        }

        $this->identity = $this->getConfig()->get('identity', null);
        $this->secret = $this->getConfig()->get('secret', null);
        $this->cmds = $this->getConfig()->getNested('onvote.run-cmd', []);
        $this->cmdos = $this->getConfig()->getNested('onvote.online-cmd', []);
        $this->lock = $this->getConfig()->get('lock', false);
        $this->voteManager = new VoteManager($this);
        $this->getServer()->getCommandMap()->register('pocketvote', new PocketVoteCommand($this));
        $this->getServer()->getPluginManager()->registerEvents(new VoteListener($this), $this);

        $this->getServer()->getScheduler()->scheduleRepeatingTask(new SchedulerTask($this), 1200); # 1200 ticks = 60 seconds.
    }

    public function onDisable() {
        self::$plugin = null;
        self::$cert = null;
        unset($this->identity, $this->secret, $this->voteManager, $this->cmds, $this->cmdos);
    }
    
    public static function getPlugin(): PocketVote {
        return self::$plugin;
    }
    
    public function getVoteManager(): VoteManager {
        return $this->voteManager;
    }

    public function updateConfig() {
        if($this->getConfig()->get('version', 0) === 0) {
            $this->getLogger()->info(TextFormat::YELLOW.'Migrating config to version 1.');
            $this->getConfig()->set('version', 1);
            $this->getConfig()->set('lock', false);
            $this->saveConfig();
        }
        if($this->getConfig()->get('version', 0) === 1) {
            $this->getLogger()->info(TextFormat::YELLOW.'Migrating config to version 2.');
            $votes = [];
            foreach($this->getConfig()->get('votes', []) as $key => $value) {
                $votes[] = $value;
            }
            $this->getConfig()->set('votes', $votes);
            $this->getConfig()->set('version', 2);
            $this->saveConfig();
        }
        if($this->getConfig()->get('version', 0) === 2) {
            $this->getLogger()->info(TextFormat::YELLOW.'Migrating config to version 3.');
            $this->getConfig()->setNested('multi-server.enabled', false);
            $this->getConfig()->setNested('multi-server.role', 'master');
            $this->getConfig()->setNested('multi-server.mysql.host', 'localhost');
            $this->getConfig()->setNested('multi-server.mysql.port', 3306);
            $this->getConfig()->setNested('multi-server.mysql.username', 'pocketvote');
            $this->getConfig()->setNested('multi-server.mysql.password', 'pocketvote');
            $this->getConfig()->setNested('multi-server.mysql.database', 'pocketvote');
            $this->getConfig()->set('version', 3);
            $this->saveConfig();
        }
    }

}