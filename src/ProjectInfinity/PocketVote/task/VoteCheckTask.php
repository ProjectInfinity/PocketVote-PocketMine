<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use ProjectInfinity\PocketVote\event\VoteEvent;
use ProjectInfinity\PocketVote\lib\Firebase\JWT;
use ProjectInfinity\PocketVote\PocketVote;

class VoteCheckTask extends AsyncTask {

    public $identity;
    public $secret;
    public $version;
    public $cert;
    
    public function __construct($identity, $secret, $version) {
        $this->identity = $identity;
        $this->secret = $secret;
        $this->version = $version;
        $this->cert = PocketVote::$cert;
    }

    public function onRun() {

        $curl = curl_init('https://api.pocketvote.io/check');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => 443,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_USERAGENT => 'PocketVote v'.$this->version,
            CURLOPT_HTTPHEADER => ['Identity: '.$this->identity]
        ]);

        $res = curl_exec($curl);

        if($res === false) {
            echo PHP_EOL.curl_error($curl).PHP_EOL;
            echo curl_errno($curl).PHP_EOL;
        } else {
            $this->setResult($res);
        }

        curl_close($curl);
        
    }
    
    public function onCompletion(Server $server) {

        if(!$this->hasResult()) {
            $server->getLogger()->emergency('A request finished without a response from the API. It may have failed to be sent.');
            return;
        }

        $result = json_decode($this->getResult());

        if($result->code === 'success' and strpos($result->message, 'outstanding') === false) {
            JWT::$leeway = 54000;
            try {
                $decoded = JWT::decode($result->payload, $this->secret, array('HS256'));
            } catch(\Exception $e) {
                Server::getInstance()->getLogger()->alert($e->getMessage());
                return;
            }

            $decoded_array = (array) $decoded;
            unset($decoded_array['iat']);
            
            foreach($decoded_array as $key => $vote) {
                Server::getInstance()->getPluginManager()->callEvent(
                    new VoteEvent(PocketVote::getPlugin(), 
                    $vote->player,
                    $vote->ip,
                    $vote->site
                ));
            }
        } /*elseif($result->code === 'error') {
            # We don't need to handle this right now.
        }*/
        
    }
}