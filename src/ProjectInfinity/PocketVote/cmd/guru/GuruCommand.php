<?php

namespace ProjectInfinity\PocketVote\cmd\guru;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class GuruCommand extends Command {

    public function __construct() {
        parent::__construct('guru', 'MCPE Guru help command', '/guru', ['gu']);
    }

    public function execute(CommandSender $sender, String $commandLabel, array $args): bool {
        if(!$sender->hasPermission('pocketvote.admin')) {
            $sender->sendMessage(TextFormat::RED.'You do not have permission to do that.');
            return true;
        }

        $sender->sendMessage(TextFormat::AQUA.'### MCPE Guru commands ###');

        $sender->sendMessage('/guru '.TextFormat::GRAY.'Shows this command');
        $sender->sendMessage('/gulist '.TextFormat::GRAY.'Lists links you\'ve created, use this to find link ids');
        $sender->sendMessage('/guadd [optional title] [link]'.TextFormat::GRAY.'Adds a link');
        $sender->sendMessage('/gudel [link ID obtained using /gulist] '.TextFormat::GRAY.'Deletes the specified link');

        return true;
    }
}