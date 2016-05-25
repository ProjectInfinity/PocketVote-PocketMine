<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use ProjectInfinity\PocketVote\event\VoteEvent;
use ProjectInfinity\PocketVote\lib\Firebase\JWT;
use ProjectInfinity\PocketVote\PocketVote;

class SlaveCheckTask extends AsyncTask {

    public $mysql_host, $mysql_port, $mysql_username, $mysql_password, $mysql_database;

    public $hash;

    public function __construct() {
        $this->mysql_host = PocketVote::getPlugin()->mysql_host;
        $this->mysql_port = PocketVote::getPlugin()->mysql_port;
        $this->mysql_username = PocketVote::getPlugin()->mysql_username;
        $this->mysql_password = PocketVote::getPlugin()->mysql_password;
        $this->mysql_database = PocketVote::getPlugin()->mysql_database;
        $this->hash = md5(PocketVote::getPlugin()->getServer()->getIp().PocketVote::getPlugin()->getServer()->getPort());
    }

    public function onRun() {

        $db = new \mysqli($this->mysql_host, $this->mysql_username, $this->mysql_password, $this->mysql_database, $this->mysql_port);
        # Ensure we are actually connected.
        if(!$db->ping()) {
            $db->close();
            return;
        }

        $result = $db->query('SELECT * FROM pocketvote_checks WHERE `server_hash` = \''.mysqli_real_escape_string($db, $this->hash).'\'');

        if(!$result) {
            $this->setResult([]);
            $db->close();
            return;
        }

        $ignore = [];

        while($row = $result->fetch_assoc()) {
            $ignore[] = $row['vote_id'];
        }

        $time = time();

        if(count($ignore) > 0) {
            $sql = 'SELECT * FROM pocketvote_votes WHERE `id` NOT IN ('.implode(',', $ignore).') AND `timestamp` > '.($time - 432000);
        } else {
            $sql = 'SELECT * FROM pocketvote_votes WHERE `timestamp` > '.($time - 432000);
        }

        $result = $db->query($sql);

        if(!$result) {
            $this->setResult([]);
            $db->close();
            return;
        }

        $votes = [];

        while($row = $result->fetch_assoc()) {
            $votes[] = $row;
        }

        foreach($votes as $vote) {
            $stmt = $db->prepare('INSERT INTO pocketvote_checks (`server_hash`, `vote_id`, `timestamp`) VALUES (?, ?, ?)');
            $stmt->bind_param('sii', $this->hash, $vote['id'], $time);
            $stmt->execute();
            $stmt->close();
        }

        $this->setResult($votes);

        $db->close();

    }

    public function onCompletion(Server $server) {

        if(!$this->hasResult()) {
            $server->getLogger()->emergency('A database check failed.');
            return;
        }

        foreach($this->getResult() as $vote) {
            Server::getInstance()->getPluginManager()->callEvent(
                new VoteEvent(PocketVote::getPlugin(),
                    $vote['player'],
                    $vote['ip'],
                    $vote['site']
                )
            );
        }
    }
}