<?php

namespace ProjectInfinity\PocketVote\task\guru;

use pocketmine\scheduler\AsyncTask;
use ProjectInfinity\PocketVote\PocketVote;

class GuruTask extends AsyncTask {

    public $isDev;
    public $cert;
    public $identity;
    public $version;
    public $url;
    public $method;
    public $postFields;

    public function __construct($url, $method = 'GET', $postFields = null) {
        $this->url = $url;
        $this->isDev = PocketVote::$dev;
        $this->cert = PocketVote::$cert;
        $this->identity = PocketVote::getPlugin()->identity;
        $this->version = PocketVote::getPlugin()->getDescription()->getVersion();
        $this->method = $method;
        $this->postFields = $postFields;
    }

    public function onRun(): void {
        $curl = curl_init($this->url);

        $options = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PORT => $this->isDev ? 80 : 443,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CAINFO => $this->cert,
            CURLOPT_USERAGENT => 'PocketVote v'.$this->version,
            CURLOPT_HTTPHEADER => ['Identity: '.$this->identity]
        ];

        switch($this->method) {
            case 'POST':
                $options[CURLOPT_POST] = 1;
                if($this->postFields !== null) $options[CURLOPT_POSTFIELDS] = $this->postFields;
                break;

            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($curl, $options);

        $res = curl_exec($curl);

        if($res === false) {
            $this->setResult((object)['error' => curl_error($curl)]);
        } else {
            $result = json_decode($res, false);

            # Check if result is valid JSON.
            if(empty($result)) {
                $this->setResult(false);
                curl_close($curl);
                return;
            }

            $this->setResult($result);
        }

        curl_close($curl);
    }
}