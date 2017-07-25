<?php

namespace ProjectInfinity\PocketVote\cmd;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;
use ProjectInfinity\PocketVote\task\guru\SetLinkNameTask;
use ProjectInfinity\PocketVote\task\DiagnoseTask;

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
                $this->plugin->getServer()->getScheduler()->scheduleAsyncTask(new DiagnoseTask($this->plugin->getDescription()->getVersion(), $sender->getName()));
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
                        foreach($this->plugin->cmds as $cmd) {
                            $i++;
                            $sender->sendMessage(($color ? TextFormat::WHITE : TextFormat::GRAY).$i.'. /'.$cmd);
                        }
                        break;
                    
                    case 'ADD':
                        if(count($args) < 3) {
                            $sender->sendMessage(TextFormat::RED.'You need to specify a command to add.');
                            return true;
                        }
                        unset($args[0], $args[1]);
                        if(strpos($args[2], '/') === 0) $args[2] = substr($args[2], 1, strlen($args[2]));
                        $this->plugin->cmds[] = implode(' ', $args);
                        $sender->sendMessage(TextFormat::GREEN.'Successfully added command.');
                        $this->plugin->getConfig()->setNested('onvote.run-cmd', array_values($this->plugin->cmds));
                        $this->plugin->saveConfig();
                        break;

                    case 'REMOVE':
                        if(count($args) < 3 or !is_numeric($args[2])) {
                            $sender->sendMessage(TextFormat::RED.'You need to specify the ID of the command to remove.');
                            return true;
                        }
                        $i = 0;
                        foreach($this->plugin->cmds as $key => $cmd) {
                            $i++;
                            if((int) $args[2] > $i) continue;
                            if((int) $args[2] === $i) {
                                unset($this->plugin->cmds[$key]);
                                $sender->sendMessage(TextFormat::GREEN.'Deleted '.$cmd.'.');
                                $this->plugin->getConfig()->setNested('onvote.run-cmd', array_values($this->plugin->cmds));
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
                        foreach($this->plugin->cmdos as $cmd) {
                            $i++;
                            $sender->sendMessage(($color ? TextFormat::WHITE : TextFormat::GRAY).$i.'. /'.$cmd);
                        }
                        break;

                    case 'ADD':
                        if(count($args) < 3) {
                            $sender->sendMessage(TextFormat::RED.'You need to specify a command to add.');
                            return true;
                        }
                        unset($args[0], $args[1]);
                        if(strpos($args[2], '/') === 0) $args[2] = substr($args[2], 1, strlen($args[2]));
                        $this->plugin->cmdos[] = implode(' ', $args);
                        $sender->sendMessage(TextFormat::GREEN.'Successfully added command.');
                        $this->plugin->getConfig()->setNested('onvote.online-cmd', array_values($this->plugin->cmdos));
                        $this->plugin->saveConfig();
                        break;

                    case 'REMOVE':
                        if(count($args) < 3 or !is_numeric($args[2])) {
                            $sender->sendMessage(TextFormat::RED.'You need to specify the ID of the command to remove.');
                            return true;
                        }
                        $i = 0;
                        foreach($this->plugin->cmdos as $key => $cmd) {
                            $i++;
                            if((int) $args[2] > $i) continue;
                            if((int) $args[2] === $i) {
                                unset($this->plugin->cmdos[$key]);
                                $sender->sendMessage(TextFormat::GREEN.'Deleted '.$cmd.'.');
                                $this->plugin->getConfig()->setNested('onvote.online-cmd', array_values($this->plugin->cmdos));
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
                $this->getPlugin()->getServer()->getScheduler()->scheduleAsyncTask(new SetLinkNameTask($sender->getName(), $args[1]));
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