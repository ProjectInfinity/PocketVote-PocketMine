# PocketVote for PocketMine
The best fast, extensible and scalable voting solution for PocketMine servers.

PocketVote acts as a smart-funnel for voting. You can use as many voting sites as you want and your server will never communicate with more than PocketVote's servers.

Because PocketVote acts as a funnel we're also capable of turning off the stream of votes if your sever goes offline which means no votes are lost!

Are you sold yet? If not here's a short list of some of our features:
* Automated voting (no commands needed)
* Your very own https://mcpe.guru vote link that can be managed in-game
* Run **any** command when a vote is received
    * Limit commands to users which certain permissions
    * Choose whether to run command immediately or wait for the player to be online
* Voting statistics
* Third-party support through custom events and the PocketVote API


Start voting today!

# Table of Contents
1. [Getting started](#getting-started)
2. [Commands](COMMANDS.md)
3. [Permissions](PERMISSIONS.md)
4. [Configuration](#configuration)
5. [Developers](DEVELOPERS.md)
6. [Support (Discord)](https://discord.gg/B4WHSSq)
7. [Plugins with PocketVote support](#third-party-plugins)

#### Getting started
1. Download the latest version of the plugin from https://poggit.pmmp.io/p/pocketvote
2. Start your server once after putting the plugin into your plugins directory
3. Sign up at https://pocketvote.io
4. Add your server
5. Click settings and then `show secrets`
6. Take the information shown and enter it into your `config.yml`, place secret into secret field and identity/identifier in the identity field.
Since **PocketVote v3.0** you can also do this via the GUI that shows up when a OP logs in while both secret and identity is not set.



Configuration
=============

| Key                         | Description                                                                                                                                                                                                                                                |
|-----------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| version                     | Used to track how the configuration should be altered in order to be completely up to date.                                                                                                                                                                |
| identity                    | The identity given to you from [PocketVote.io](https://pocketvote.io) which is used to identify your server.                                                                                                                                               |
| secret                      | The secret given to you from [PocketVote.io](https://pocketvote.io) which is given to voting sites.                                                                                                                                                        |
| lock                        | If set to true, the commands to alter secret or identity is disabled.                                                                                                                                                                                      |
| vote-expiration             | An amount of time to keep votes that have not been redeemed by a player, specified in **days**.                                                                                                                                                            |
| onvote                      | A list of commands that are to be ran when a vote is retrieved. The following variables can be used in the commands: %player, %site, %ip.                                                                                                        |
| votes                       | A list of votes waiting for players to log on. Please leave this alone.                                                                                                                                                                                    |

Third party plugins
===================
Made a plugin that supports PocketVote? [Let me know! Open an issue on GitHub](https://github.com/ProjectInfinity/PocketVote-PocketMine/issues/new).

* [PocketLotto](https://poggit.pmmp.io/p/PocketLotto) - A lottery plugin for PocketMine with support for PocketVote.
* [TopVoter (as of next version)](https://poggit.pmmp.io/p/topvoter) - A plugin that lists top voters using FloatingTextParticles.