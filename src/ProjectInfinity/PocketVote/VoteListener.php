<?php

namespace ProjectInfinity\PocketVote;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\event\VoteDispatchEvent;
use ProjectInfinity\PocketVote\event\VoteEvent;

class VoteListener implements Listener {
    
    private $plugin;
    private $vm;
    
    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
        $this->vm = $plugin->getVoteManager();
    }

    /**
     * @priority LOWEST
     * @param VoteEvent $event
     */
    public function onVoteEvent(VoteEvent $event): void {
        if($event->isCancelled()) return;

        $isPlayerOnline = $event->isOnline();
        $hasOnlineCommands = false;
        $sender = new ConsoleCommandSender($this->plugin->getServer(), $this->plugin->getServer()->getLanguage());

        foreach($this->plugin->onVote as $onVote) {
            if(!$isPlayerOnline && $onVote['player-online']) {
                $hasOnlineCommands = true;
                continue;
            }

            $cmd = str_replace(['%player', '%ip', '%site'], [$event->getPlayer(), $event->getIp(), $event->getSite()], $onVote['cmd']);

            if($isPlayerOnline && isset($onVote['permission'])) {
                foreach($this->plugin->getServer()->getOnlinePlayers() as $player) {
                    if(strtolower($event->getPlayer()) !== strtolower($player->getName())) continue;
                    if(!$player->hasPermission($onVote['permission'])) continue;
                    // Found player with permission, execute command.
                    break;
                }
            }

            $this->plugin->getServer()->dispatchCommand($sender, $cmd);
        }
        
        if(!$isPlayerOnline && $hasOnlineCommands) {
            $this->vm->addVote($event->getPlayer(), $event->getSite(), $event->getIp());
            $this->vm->commit();
            return;
        }
        
    }

    /**
     * @priority LOWEST
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        // Check if identity and secret has been set. If not show GUI.
        if($this->plugin->identity === '' && $this->plugin->secret === '' && $this->plugin->getServer()->isOp($event->getPlayer()->getName())) {
            $form = new CustomForm(static function(Player $player, ?array $data) {
                if(!$data) {
                    $player->sendMessage(TextFormat::YELLOW.'[PocketVote] OK! We\'ll remind you next time you log in.');
                    return;
                }
                if($data['secret'] === '' || $data['identity'] === '') {
                    $player->sendMessage(TextFormat::RED.'Make sure you entered your secret and identity correctly.');
                    return;
                }
                $localPlugin = PocketVote::getPlugin();
                $localPlugin->secret = $data['secret'];
                $localPlugin->identity = $data['identity'];
                $localPlugin->getConfig()->set('secret', $localPlugin->secret);
                $localPlugin->getConfig()->set('identity', $localPlugin->identity);
                $localPlugin->saveConfig();
                $player->sendMessage(TextFormat::GREEN.'Secret and identity has been set. Try running "/pocketvote diagnose" to confirm that it works.');
            });
            $form->setTitle('PocketVote First Time Setup');
            $form->addLabel('PocketVote is not currently setup. Go to pocketvote.io and sign up, then add your server and go to the settings tab. Once there click the show secrets button and copy both messages into the boxes below.');
            $form->addInput('Secret', 'Paste secret...', '', 'secret');
            $form->addInput('Identity', 'Paste identity...', '', 'identity');
            $event->getPlayer()->sendForm($form);
        }
        if(PocketVote::$hasVRC) $this->vm->scheduleVRCTask($event->getPlayer()->getName()); # TODO: This should be possible to disable and only allow through commands.
        if(!$this->vm->hasVotes($event->getPlayer()->getName())) return;

        $sender = new ConsoleCommandSender($this->plugin->getServer(), $this->plugin->getServer()->getLanguage());
        foreach($this->vm->getVotes($event->getPlayer()->getName()) as $key => $vote) {
            if($this->plugin->expiration > 0 && (time() >= $vote['expires'])) {
                $this->vm->removeVote($key);
                continue;
            }
            $voteDispatchEvent = new VoteDispatchEvent($this->plugin, $vote['player'], $vote['ip'], $vote['site']);
            $voteDispatchEvent->call();

            # Iterate all commands.
            foreach($this->plugin->onVote as $onVote) {
                if(!$onVote['player-online']) continue;
                $cmd = str_replace(['%player', '%ip', '%site'], [$vote['player'], $vote['ip'], $vote['site']], $onVote['cmd']);

                if(isset($onVote['permission']) && !$event->getPlayer()->hasPermission($onVote['permission'])) continue;
                $this->plugin->getServer()->dispatchCommand($sender, $cmd);
            }
            $this->vm->removeVote($key);
        }
        $this->vm->commit();
    }

}