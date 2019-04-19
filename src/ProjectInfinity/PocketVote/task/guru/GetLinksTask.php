<?php

namespace ProjectInfinity\PocketVote\task\guru;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;

class GetLinksTask extends GuruTask {

    /** @var Player */
    public $player;

    public function __construct($player) {
        parent::__construct(PocketVote::$dev ? 'http://dev.mcpe.guru/api/links' : 'https://mcpe.guru/api/links');
        $this->player = $player;
    }

    public function onRun(): void {
        parent::onRun();
    }

    public function onCompletion(Server $server) {
        if($this->player === 'CONSOLE') {
            $this->player->sendMessage(TextFormat::RED.'Console cannot manage links.');
            return;
        }
        $player = $server->getPlayer($this->player);

        # Player is offline.
        if($player === null) return;

        if(!$this->hasResult()) {
            $player->sendMessage(TextFormat::RED.'Got no response when retrieving links from MCPE.Guru');
            return;
        }

        $result = $this->getResult();

        # Invalid response.
        if(!$result) {
            $player->sendMessage(TextFormat::RED.'The response from MCPE.Guru was not valid JSON.');
            return;
        }

        if(isset($result->error)) {
            $player->sendMessage(TextFormat::RED.'An error occurred while performing this command.');
            $server->getLogger()->error('[PocketVote] Curl error: '.$result->error);
            return;
        }

        if($result->success && (!isset($result->payload) || ($result->success && count($result->payload) === 0))) {
            $player->sendMessage(TextFormat::YELLOW.'There are no added links, use '.TextFormat::AQUA.'/guadd [url]'.TextFormat::YELLOW.' to add a link!');
            return;
        }

        $links = [];
        foreach($result->payload as $link) {
            $links[$link->id] = $link;
        }

        $selectedId = 0;
        $selectedForm = new ModalForm(static function(Player $player, $delete) use(&$selectedId) {
            if(!$delete) return;
            PocketVote::getPlugin()->getServer()->dispatchCommand($player, 'gudel '.$selectedId);
        });

        $form = new SimpleForm(static function(Player $player, $id) use($links, $selectedForm, &$selectedId) {
            if(!isset($links[$id])) return;
            $link = $links[$id];
            $selectedForm->setTitle($link->title);
            $selectedForm->setContent($link->url);
            $selectedForm->setButton1('Delete');
            $selectedForm->setButton2('Cancel');

            $selectedId = $link->id;
            $player->sendForm($selectedForm);
        });

        $form->setTitle('Voting links');

        //$color = true;
        foreach($result->payload as $link) {
            /*$c = $color ? TextFormat::AQUA : TextFormat::YELLOW;
            $player->sendMessage($c.'ID: '.$link->id);
            $player->sendMessage($c.'Title: '.$link->title);
            $player->sendMessage($c.'URL: '.$link->url);

            $color = !$color;*/
            $form->addButton($link->title, -1, '', $link->id);
        }

        $player->sendForm($form);

    }
}