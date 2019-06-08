<?php

declare(strict_types = 1);

namespace ProjectInfinity\PocketVote\util;

use jojoe77777\FormAPI\{CustomForm, ModalForm, SimpleForm};
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;

class FormManager {

    private $plugin;

    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
    }

    public function createMainMenuForm(Player $player): void {
        $form = new SimpleForm(\Closure::fromCallable([$this, 'handleMainMenuAction']));
        $form->setTitle('PocketVote - Main menu');
        $form->addButton('On vote commands', -1, '', 'cmds');
        $player->sendForm($form);
    }

    private function handleMainMenuAction(Player $player, ?string $selection): void {
        if(!$selection) return;
        switch(strtolower($selection)) {
            case 'cmds':
                $form = new SimpleForm(\Closure::fromCallable([$this, 'showCommandsForm']));
                $form->setTitle('Commands');
                $form->addButton('New command', -1, '', 'add');
                $form->addButton('Instant commands', -1, '', 'instant');
                $form->addButton('When player is online', -1, '', 'online');
                $player->sendForm($form);
                break;

            default:
                $player->sendMessage(TextFormat::RED.'[PocketVote] Detected invalid menu selection');
        }
    }

    public function createLinkAddForm($failed = false): CustomForm {
        $form = new CustomForm(\Closure::fromCallable([VoteManager::class, 'addLink']));
        $form->setTitle('Add link');
        $form->addLabel('Your added links will be visible at '.$this->plugin->getVoteManager()->getVoteLink());
        $form->addInput('Title (optional)', 'Enter title...', null, 'title');
        if($failed) $form->addLabel(TextFormat::RED.'A URL is required.');
        $form->addInput('URL', 'Enter URL...', null, 'url');
        return $form;
    }

    private static function showCommandsForm(Player $player, ?string $selection): void {
        if(!$selection) return;

        if($selection === 'add') {
            // Send create new command form to player
            $player->sendForm(self::createAddCommandForm());
            return;
        }

        $plugin = PocketVote::getPlugin();

        // Sends form that allows user to choose whether to edit or delete command
        $form = new SimpleForm(static function(Player $player, ?string $selection) {
            if(!$selection) return;

            [$type, $index] = explode(':', $selection, 2);

            $cmd = PocketVote::getPlugin()->onVote[(int) $index]['cmd'];

            // Send form to present options to delete or edit selected command
            $form = new ModalForm(static function(Player $player, bool $isDelete) use($type, $index) {
                $plugin = PocketVote::getPlugin();

                if($isDelete) {
                    unset($plugin->onVote[(int) $index]);
                    $plugin->saveCommands();
                    $player->sendMessage(TextFormat::YELLOW.'[PocketVote] Command deleted.');
                    return;
                }

                // Create and send form for editing selected command
                $player->sendForm(self::createEditCommandForm($type, (int) $index));
            });

            $form->setTitle('Select action');
            $form->setContent($cmd);
            $form->setButton1('Delete');
            $form->setButton2('Edit');

            $player->sendForm($form);
        });

        if($selection === 'instant') {
            $form->setTitle('Runs instantly');
            $hasCmds = false;
            foreach($plugin->onVote as $key => $onVote) {
                if($onVote['player-online']) continue;
                $form->addButton($onVote['cmd'], -1, '', 'instant:'.$key);
                $hasCmds = true;
            }

            if(!$hasCmds) {
                $player->sendMessage(TextFormat::YELLOW.'[PocketVote] There are no commands that run when a vote is received');
                return;
            }

        } else {
            $form->setTitle('Runs when player is online');

            $hasCmds = false;
            foreach($plugin->onVote as $key => $onVote) {
                if(!$onVote['player-online']) continue;
                $form->addButton($onVote['cmd'], -1, '', 'online:'.$key);
                $hasCmds = true;
            }

            if(!$hasCmds) {
                $player->sendMessage(TextFormat::YELLOW.'[PocketVote] There are no commands that run when a player is online');
                return;
            }
        }

        $player->sendForm($form);
    }

    private static function createEditCommandForm(string $type, int $index): CustomForm {
        $form = new CustomForm(static function(Player $player, ?array $data) use($index) {
            if(!$data) return;

            $plugin = PocketVote::getPlugin();
            $cmd = &$plugin->onVote[$index];
            $cmd['cmd'] = $data['cmd'];
            if($data['enable_permission'] && $data['permission'] !== '') $cmd['permission'] = $data['permission'];

            $plugin->saveCommands();
            $player->sendMessage(TextFormat::GREEN.'[PocketVote] Command updated.');
        });

        $onVote = PocketVote::getPlugin()->onVote[$index];

        $form->setTitle("Editing $type command");
        $form->addLabel('Available variables: player, site, ip');
        $form->addInput('Command', 'Command without slash', $onVote['cmd'], 'cmd');
        $form->addLabel('To use a variable prefix it with a percent symbol');
        if($type === 'instant') {
            $form->addToggle('Requires permission', false, 'enable_permission');
            $form->addInput('Permission node', 'Enter permission node', null, 'permission');
        }
        return $form;
    }

    private static function createAddCommandForm(): CustomForm {
        $form = new CustomForm(static function(Player $player, ?array $data) {
            if(!$data || !$data['command']) return;

            if($data['uses_permission'] && !$data['online']) {
                $player->sendMessage(TextFormat::RED.'[PocketVote] Cannot combine permission and running commands when players are offline.');
                return;
            }

            $plugin = PocketVote::getPlugin();
            $commands = &$plugin->onVote;
            $new = [];

            $new['cmd'] = $data['command'];
            $new['player-online'] = $data['online'];
            if($data['uses_permission'] && $data['permission'] !== '') $new['permission'] = $data['permission'];

            $commands[] = $new;
            $plugin->saveCommands();
            $player->sendMessage(TextFormat::GREEN.'[PocketVote] Command added');
        });

        $form->setTitle('New command');
        $form->addLabel('Available variables: player, site, ip');
        $form->addInput('Command', 'Command without slash', null, 'command');
        $form->addLabel('To use a variable prefix it with a percent symbol');
        $form->addToggle('Require permission', false, 'uses_permission');
        $form->addInput('Permission node', 'permission.node', null, 'permission');
        $form->addToggle('Player must be online', false, 'online');
        return $form;
    }
}