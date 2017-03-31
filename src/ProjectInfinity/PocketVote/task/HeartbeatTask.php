<?php

namespace ProjectInfinity\PocketVote\task;

use pocketmine\scheduler\AsyncTask;
use ProjectInfinity\PocketVote\lib\Firebase\JWT;
use ProjectInfinity\PocketVote\PocketVote;

class HeartbeatTask extends AsyncTask {

    public $meta = [];
    public $identity;
    public $isDev;
    public $cert;
    public $secret;

    public function __construct($identity) {
        $this->identity = $identity;
        $this->isDev = PocketVote::$dev;
        $this->cert = PocketVote::$cert;

        /**
         * Used for a heartbeat on startup.
         *
         * The information is simply used to gauge what kind of servers run
         * PocketVote and how and what I need to optimise.
         */
        $plugin = PocketVote::getPlugin();
        $config = $plugin->getConfig();
        $this->secret = $config->get('secret', null);
        $this->meta['pluginVersion'] = $plugin->getDescription()->getVersion();
        $this->meta['mcpeVersion'] = $plugin->getServer()->getVersion();
        $this->meta['serverVersion'] = $plugin->getServer()->getPocketMineVersion();
        $this->meta['serverApiVersion'] = $plugin->getServer()->getApiVersion();
        $this->meta['serverPort'] = $plugin->getServer()->getPort();
        $this->meta['serverName'] = $plugin->getServer()->getName();
        $this->meta['pluginConfig'] = [
            'multi-server' => $config->getNested('multi-server.enabled', false),
            'multi-server-role' => $config->getNested('multi-server.role', 'master'),
            'lock' => $config->get('lock', false),
            'vote-expiration' => $config->get('vote-expiration', 7)
        ];
    }

    # TODO: Change echos to logging in onCompletion.
    public function onRun() {

        if($this->secret === null || $this->identity === null) return;

        $curl = curl_init($this->isDev ? 'http://127.0.0.1/v2/heartbeat' : 'https://api.pocketvote/v2/heartbeat');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => $this->isDev ? 9000 : 443,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_USERAGENT => 'PocketVote v'.$this->meta['pluginVersion'],
            CURLOPT_HTTPHEADER => ['Identity: '.$this->identity],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'token' => JWT::encode($this->meta, $this->secret)
            ]
        ]);

        $res = curl_exec($curl);

        if($res === false) {
            echo PHP_EOL.curl_error($curl).PHP_EOL;
            echo curl_errno($curl).PHP_EOL;
        }

        curl_close($curl);
    }
}