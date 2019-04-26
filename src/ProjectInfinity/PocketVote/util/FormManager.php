<?php

declare(strict_types = 1);

namespace ProjectInfinity\PocketVote\util;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\utils\TextFormat;
use ProjectInfinity\PocketVote\PocketVote;

class FormManager {

    private $plugin;

    public function __construct(PocketVote $plugin) {
        $this->plugin = $plugin;
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
}