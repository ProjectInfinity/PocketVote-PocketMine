<?php

namespace ProjectInfinity\PocketVote\cmd\guru;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;
use ProjectInfinity\PocketVote\task\guru\DeleteLinkTask;

class GuDelCommand extends Command {

    private $plugin;

    public function __construct(PocketVote $plugin) {
        parent::__construct('gudel', 'MCPE Guru Delete link command', '/gudel [id]', ['gud']);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, String $commandLabel, array $args): bool {
        if(!$sender->hasPermission('pocketvote.admin')) {
            $sender->sendMessage(TextFormat::RED.'You do not have permission to do that!');
            return true;
        }

        if(count($args) < 1) {
            $sender->sendMessage(TextFormat::RED.'You need to specify an ID to delete, type /gulist to see your links.');
            return true;
        }

        if(!ctype_digit($args[0]) || (int) $args[0] === 0) {
            $sender->sendMessage(TextFormat::RED.'You can only specify whole numbers as link ID.');
            return true;
        }

        $this->plugin->getServer()->getAsyncPool()->submitTask(new DeleteLinkTask($sender->getName(), (int)$args[0]));
        return true;
    }
}