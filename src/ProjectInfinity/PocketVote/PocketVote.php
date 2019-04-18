<?php

namespace ProjectInfinity\PocketVote;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\cmd\guru\GuAddCommand;
use ProjectInfinity\PocketVote\cmd\guru\GuDelCommand;
use ProjectInfinity\PocketVote\cmd\guru\GuListCommand;
use ProjectInfinity\PocketVote\cmd\guru\GuruCommand;
use ProjectInfinity\PocketVote\cmd\PocketVoteCommand;
use ProjectInfinity\PocketVote\cmd\VoteCommand;
use ProjectInfinity\PocketVote\task\HeartbeatTask;
use ProjectInfinity\PocketVote\task\SchedulerTask;
use ProjectInfinity\PocketVote\task\VoteLinkTask;
use ProjectInfinity\PocketVote\util\VoteManager;

class PocketVote extends PluginBase {

    /** @var PocketVote $plugin */
    private static $plugin;

    public $lock;
    public $identity;
    public $secret;
    public $expiration;

    public $multiserver;
    public $multiserver_role;
    public $mysql_host, $mysql_port, $mysql_username, $mysql_password, $mysql_database;

    public $cmds;
    public $cmdos;
    
    public static $cert;
    public static $dev;
    public static $hasVRC;

    /** @var VoteManager $voteManager */
    private $voteManager;

    /** @var  TaskHandler $schedulerTask */
    private $schedulerTask;
    private $schedulerTs;
    private $schedulerFreq = 60;

    public function onEnable(): void {
        self::$plugin = $this;
        $this->saveDefaultConfig();
        $this->updateConfig();
        self::$dev = $this->getConfig()->get('dev', false) === true;
        
        # Save and load certificates.
        self::$cert = $this->getDataFolder().'cacert.pem';
        if(!file_exists(self::$cert)) {

            $this->getLogger()->warning('Could not find cacert.pem, downloading it now.');

            $curl = curl_init('https://curl.haxx.se/ca/cacert.pem');

            /** @noinspection CurlSslServerSpoofingInspection */
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
                $file = fopen(self::$cert, 'wb+');
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
        $this->expiration = 86400 * $this->getConfig()->get('vote-expiration', 7);
        $this->voteManager = new VoteManager($this);
        $this->getServer()->getCommandMap()->register('pocketvote', new PocketVoteCommand($this));
        $this->getServer()->getCommandMap()->register('pocketvote', new VoteCommand($this));

        ### MCPE Guru commands ###
        $this->getServer()->getCommandMap()->register('pocketvote', new GuruCommand());
        $this->getServer()->getCommandMap()->register('pocketvote', new GuAddCommand($this));
        $this->getServer()->getCommandMap()->register('pocketvote', new GuDelCommand($this));
        $this->getServer()->getCommandMap()->register('pocketvote', new GuListCommand($this));

        $this->getServer()->getPluginManager()->registerEvents(new VoteListener($this), $this);

        # Check if the VRC folder exists for legacy voting sites.
        if(file_exists($this->getDataFolder().'vrc')) {
            $this->getLogger()->info(TextFormat::GOLD.'VRC folder found, to enable sites that support VRC files. Place them in the plugins/PocketVote/vrc folder and ensure the file name ends with .vrc');
            $vrcFiles = glob($this->getDataFolder().'vrc/*.{vrc}', GLOB_BRACE);
            foreach($vrcFiles as $file) {
                $fh = fopen($file, 'rb');
                $raw = fread($fh, filesize($file));
                fclose($fh);
                $data = json_decode($raw, false);
                if(!$raw || !$data) {
                    $this->getLogger()->warning(TextFormat::RED.'VRC file '.$file.' could not be read.');
                    continue;
                }
                $this->voteManager->addVRC((object)['website' => $data->website, 'check' => $data->check, 'claim' => $data->claim]);
                self::$hasVRC = true;
                $this->getLogger()->info(TextFormat::GREEN.'VRC enabled for '.$data->website);
            }
        }

        $this->schedulerTask = $this->getScheduler()->scheduleRepeatingTask(new SchedulerTask($this), 1200); # 1200 ticks = 60 seconds.
        # Get voting link.
        $this->getServer()->getAsyncPool()->submitTask(new VoteLinkTask($this->identity));
        # Report usage.
        if(!$this->getConfig()->get('opt-out-usage', false)) $this->getServer()->getAsyncPool()->submitTask(new HeartbeatTask($this->identity));
    }

    public function onDisable(): void {
        $this->getScheduler()->cancelAllTasks();
        self::$plugin = null;
        self::$cert = null;
        self::$dev = null;
        unset($this->identity, $this->secret, $this->voteManager, $this->cmds, $this->cmdos, $this->expiration);
    }
    
    public static function getPlugin(): PocketVote {
        return self::$plugin;
    }
    
    public function getVoteManager(): VoteManager {
        return $this->voteManager;
    }

    public function stopScheduler(): void {
        if($this->schedulerTask->isCancelled()) return;
        $this->schedulerTask->cancel();
    }

    public function startScheduler(int $seconds): void {
        $time = time();
        # Ensure that at least 5 minutes has passed since we last changed frequency and check that the frequency is different from before.
        if($time - $this->schedulerTs < 300 || $this->schedulerFreq === $seconds) return;
        if(!$this->schedulerTask->isCancelled()) $this->schedulerTask->cancel();

        $this->schedulerTs = $time;
        $this->schedulerTask = $this->getScheduler()->scheduleRepeatingTask(new SchedulerTask($this), $seconds > 0 ? ($seconds * 20) : 1200);
        $this->schedulerFreq = $seconds;

        $this->getLogger()->debug("Scheduler interval changed to $seconds seconds.");
    }

    public function updateConfig(): void {
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
        if($this->getConfig()->get('version', 0) === 3) {
            $this->getLogger()->info(TextFormat::YELLOW.'Migrating config to version 4.');
            $votes = [];
            foreach($this->getConfig()->get('votes') as $key => $vote) {
                $vote['expires'] = time() + (86400 * 7);
                $votes[] = $vote;
            }
            $this->getConfig()->set('votes', $votes);
            $this->getConfig()->set('vote-expiration', 7);
            $this->getConfig()->set('version', 4);
            $this->saveConfig();
        }
        if($this->getConfig()->get('version', 0) === 4) {
            $this->getLogger()->info(TextFormat::YELLOW.'Migrating config to version 5.');
            $this->getConfig()->setNested('multi-server.use-pocketvote-sync', true);
            $this->getConfig()->set('opt-out-usage', false);
            $this->saveConfig();
        }
    }

}