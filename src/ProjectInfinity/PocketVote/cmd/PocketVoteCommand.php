<?php

namespace ProjectInfinity\PocketVote\cmd;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;
use ProjectInfinity\PocketVote\task\guru\SetLinkNameTask;
use ProjectInfinity\PocketVote\task\DiagnoseTask;
use ProjectInfinity\PocketVote\util\FormManager;

class PocketVoteCommand extends Command implements PluginIdentifiableCommand {

    private $plugin;

    public function __construct(PocketVote $plugin) {
        parent::__construct('pocketvote', 'PocketVote general management command', '/pocketvote [option]', ['pv']);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!$sender->hasPermission('pocketvote.admin')) {
            $sender->sendMessage(TextFormat::RED.'You do not have permission to administer PocketVote.');
            return true;
        }

        if(count($args) === 0) {
            if($sender instanceof Player) {
                $this->plugin->getFormManager()->createMainMenuForm($sender);
                return true;
            }
            $sender->sendMessage(TextFormat::AQUA.'Specify an action: SECRET, IDENTITY, DIAGNOSE, CMD, CMDO, LINK');
            return true;
        }

        switch(strtoupper($args[0])) {
            
            case 'IDENTITY':
                if($this->plugin->lock) {
                    $sender->sendMessage(TextFormat::RED.'This command has been locked.');
                    return true;
                }
                if(!isset($args[1])) {
                    $sender->sendMessage(TextFormat::RED.'No identity specified. Get one at pocketvote.io');
                    return true;
                }
                $this->plugin->identity = $args[1];
                $sender->sendMessage(TextFormat::GREEN.'Successfully set identity.');
                $this->plugin->getConfig()->set('identity', $this->plugin->identity);
                $this->plugin->saveConfig();
                break;
            
            case 'SECRET':
                if($this->plugin->lock) {
                    $sender->sendMessage(TextFormat::RED.'This command has been locked.');
                    return true;
                }
                if(!isset($args[1])) {
                    $sender->sendMessage(TextFormat::RED.'No secret specified. Get one at pocketvote.io');
                    return true;
                }
                $this->plugin->secret = $args[1];
                $sender->sendMessage(TextFormat::GREEN.'Successfully set secret.');
                $this->plugin->getConfig()->set('secret', $this->plugin->secret);
                $this->plugin->saveConfig();
                break;

            case 'DIAGNOSE':
                $sender->sendMessage(TextFormat::GREEN.'Scheduling a diagnosis...');
                $this->plugin->getServer()->getAsyncPool()->submitTask(new DiagnoseTask($this->plugin->getDescription()->getVersion(), $sender->getName()));
                break;

            case 'CMD':
                if(count($args) < 2) {
                    $sender->sendMessage(TextFormat::RED.'You need to specify a command. Variables: %player, %ip, %site');
                    return true;
                }
                switch(strtoupper($args[1])) {

                    case 'LIST':
                        $i = 0;
                        $color = true;
                        $sender->sendMessage(TextFormat::YELLOW.'cmd, these run instantly:');
                        foreach($this->plugin->onVote as $onVote) {
                            $i++;
                            if($onVote['player-online']) continue;
                            $sender->sendMessage(($color ? TextFormat::WHITE : TextFormat::GRAY).$i.'. /'.$onVote['cmd']);
                        }
                        break;
                    
                    case 'ADD':
                        if(count($args) < 3) {
                            $sender->sendMessage(TextFormat::RED.'You need to specify a command to add.');
                            return true;
                        }
                        if($this->plugin->lock) {
                            $sender->sendMessage(TextFormat::RED.'This command has been locked.');
                            return true;
                        }
                        unset($args[0], $args[1]);
                        if(strpos($args[2], '/') === 0) $args[2] = substr($args[2], 1, strlen($args[2]));
                        $this->plugin->onVote[] = ['cmd' => implode(' ', $args), 'player-online' => false];
                        $sender->sendMessage(TextFormat::GREEN.'Successfully added command.');
                        $this->plugin->getConfig()->set('onvote', array_values($this->plugin->onVote));
                        $this->plugin->saveConfig();
                        break;

                    case 'REMOVE':
                        if(count($args) < 3 || !is_numeric($args[2])) {
                            $sender->sendMessage(TextFormat::RED.'You need to specify the ID of the command to remove.');
                            return true;
                        }
                        if($this->plugin->lock) {
                            $sender->sendMessage(TextFormat::RED.'This command has been locked.');
                            return true;
                        }
                        $i = 0;
                        foreach($this->plugin->onVote as $key => $onVote) {
                            $i++;
                            if($onVote['player-online']) continue;
                            if((int) $args[2] > $i) continue;
                            if((int) $args[2] === $i) {
                                unset($this->plugin->onVote[$key]);
                                $sender->sendMessage(TextFormat::GREEN.'Deleted '.$onVote['cmd'].'.');
                                $this->plugin->getConfig()->set('onvote', array_values($this->plugin->onVote));
                                $this->plugin->saveConfig();
                                return true;
                            }
                        }
                        break;

                    default:
                        $sender->sendMessage(TextFormat::RED.'Invalid option. Use list, add or remove.');
                }
                break;

            case 'CMDO':
                if(count($args) < 2) {
                    $sender->sendMessage(TextFormat::RED.'You need to specify a command. Variables: %player, %ip, %site');
                    return true;
                }
                switch(strtoupper($args[1])) {

                    case 'LIST':
                        $i = 0;
                        $color = true;
                        $sender->sendMessage(TextFormat::YELLOW.'cmdo, these run when the player is online:');
                        foreach($this->plugin->onVote as $onVote) {
                            $i++;
                            if(!$onVote['player-online']) continue;
                            $sender->sendMessage(($color ? TextFormat::WHITE : TextFormat::GRAY).$i.'. /'.$onVote['cmd']);
                        }
                        break;

                    case 'ADD':
                        if(count($args) < 3) {
                            $sender->sendMessage(TextFormat::RED.'You need to specify a command to add.');
                            return true;
                        }
                        unset($args[0], $args[1]);
                        if(strpos($args[2], '/') === 0) $args[2] = substr($args[2], 1, strlen($args[2]));
                        $this->plugin->onVote[] = ['cmd' => implode(' ', $args), 'player-online' => true];
                        $sender->sendMessage(TextFormat::GREEN.'Successfully added command.');
                        $this->plugin->getConfig()->set('onvote', array_values($this->plugin->onVote));
                        $this->plugin->saveConfig();
                        break;

                    case 'REMOVE':
                        if(count($args) < 3 || !is_numeric($args[2])) {
                            $sender->sendMessage(TextFormat::RED.'You need to specify the ID of the command to remove.');
                            return true;
                        }
                        $i = 0;
                        foreach($this->plugin->onVote as $key => $onVote) {
                            $i++;
                            if((int) $args[2] > $i) continue;
                            if((int) $args[2] === $i) {
                                unset($this->plugin->onVote[$key]);
                                $sender->sendMessage(TextFormat::GREEN.'Deleted '.$onVote['cmd'].'.');
                                $this->plugin->getConfig()->set('onvote', array_values($this->plugin->onVote));
                                $this->plugin->saveConfig();
                                return true;
                            }
                        }
                        break;

                    default:
                        $sender->sendMessage(TextFormat::RED.'Invalid option. Use list, add or remove.');
                }
                break;

            case 'LINK':
                if(count($args) < 2) {
                    $sender->sendMessage(TextFormat::RED.'You need to specify a name. /pv link [name]');
                    return true;
                }
                $this->getPlugin()->getServer()->getAsyncPool()->submitTask(new SetLinkNameTask($sender->getName(), $args[1]));
                break;

            default:
                $sender->sendMessage(TextFormat::RED.'Invalid option. Specify SECRET, IDENTITY, DIAGNOSE, CMD, CMDO or LINK.');
        }
        return true;
    }

    public function getPlugin(): Plugin {
        return $this->plugin;
    }
}