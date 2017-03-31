<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use ProjectInfinity\PocketVote\event\VoteEvent;
use ProjectInfinity\PocketVote\lib\Firebase\JWT;
use ProjectInfinity\PocketVote\PocketVote;

class VoteCheckTask extends AsyncTask {

    public $isDev;

    public $identity;
    public $secret;
    public $version;
    public $cert;

    public $multiserver;
    public $mysql_host, $mysql_port, $mysql_username, $mysql_password, $mysql_database;
    
    public function __construct($identity, $secret, $version) {
        $this->isDev = PocketVote::$dev;
        $this->identity = $identity;
        $this->secret = $secret;
        $this->version = $version;
        $this->cert = PocketVote::$cert;
        $this->mysql_host = PocketVote::getPlugin()->mysql_host;
        $this->mysql_port = PocketVote::getPlugin()->mysql_port;
        $this->mysql_username = PocketVote::getPlugin()->mysql_username;
        $this->mysql_password = PocketVote::getPlugin()->mysql_password;
        $this->mysql_database = PocketVote::getPlugin()->mysql_database;
        $this->multiserver = PocketVote::getPlugin()->multiserver;
    }

    public function onRun() {
        # TODO: Set result output and exit, then read the result in on completion to get proper logging.
        $curl = curl_init($this->isDev ? 'http://127.0.0.1/check' : 'https://api.pocketvote.io/check');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => $this->isDev ? 9000 : 443,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_USERAGENT => 'PocketVote v'.$this->version,
            CURLOPT_HTTPHEADER => ['Identity: '.$this->identity]
        ]);

        $res = curl_exec($curl);

        if($res === false) {
            echo PHP_EOL.curl_error($curl).PHP_EOL;
            echo curl_errno($curl).PHP_EOL;
        } else {

            $result = json_decode($res);

            if($result->code === 'success' && strpos($result->message, 'outstanding') === false) {
                JWT::$leeway = 54000;
                try {
                    $decoded = JWT::decode($result->payload, $this->secret, array('HS256'));
                } catch(\Exception $e) {
                    echo PHP_EOL . $e->getMessage() . PHP_EOL;
                    return;
                }

                $decoded_array = (array)$decoded;
                unset($decoded_array['iat']);

                $votes = [];

                foreach($decoded_array as $key => $vote) {
                    $votes[] = $vote;
                }
                
                $this->setResult($votes);

                if($this->multiserver && count($votes) > 0) {
                    $db = new \mysqli($this->mysql_host, $this->mysql_username, $this->mysql_password, $this->mysql_database, $this->mysql_port);
                    # Ensure we are actually connected.
                    if(!$db->ping()) {
                        curl_close($curl);
                        return;
                    }
                    $time = time();
                    foreach($votes as $vote) {
                        $stmt = $db->prepare('INSERT INTO `pocketvote_votes` (`player`, `ip`, `site`, `timestamp`) VALUES (?, ?, ?, ?)');
                        $stmt->bind_param('sssi', $vote->player, $vote->ip, $vote->site, $time);
                        $stmt->execute();
                        $stmt->close();
                    }

                    # All done.
                    $db->close();
                }
            } else {
                $this->setResult([]);
            }
        }

        curl_close($curl);
    }
    
    public function onCompletion(Server $server) {

        if(!$this->hasResult()) {
            $server->getLogger()->emergency('A request finished without a response from the API. It may have failed to be sent.');
            return;
        }
            
        foreach($this->getResult() as $vote) {
            Server::getInstance()->getPluginManager()->callEvent(
                new VoteEvent(PocketVote::getPlugin(),
                    $vote->player,
                    $vote->ip,
                    $vote->site
                )
            );
        }
    }
}