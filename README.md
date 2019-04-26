# PocketVote for PocketMine

The fully automated voting solution for PocketMine servers.

Add your server for free on https://pocketvote.io
Getting started is super easy!

1. Download the latest version of the plugin from https://poggit.pmmp.io/p/pocketvote
2. Start your server once after putting the plugin into your plugins directory
3. Sign up at https://pocketvote.io
4. Add your server
5. Click `show secrets`
6. Take the information shown and enter it into your `config.yml`, place secret into secret field and identity/identifier in the identity field.


PocketMine Plugin Documentation
===============================

This file is continuously updated. If you feel something is missing or
incorrect, please open an issue.

Commands
========

| Command                         | Permission       | Description                                                                                                                                                                                                                                                                                                                                   |
|---------------------------------|------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| /vote                           | pocketvote.vote  | Shows the MCPE.guru voting link associated with the server.                                                                                                                                                                                                                                                                                   |
| /vote top                       | pocketvote.vote  | Shows the top voters the past month.                                                                                                                                                                                                                                                                                                          |
| /pocketvote identity [identity] | pocketvote.admin | This sets your plugin’s identity to the one you provided. Get your server’s identity from the server dashboard at pocketvote.io.                                                                                                                                                                                                              |
| /pocketvote secret [secret]     | pocketvote.admin | This sets your plugin’s secret to the one you provided. Get your server’s secret from the server dashboard at pocketvote.io.                                                                                                                                                                                                                  |
| /pocketvote cmd list            | pocketvote.admin | Lists the commands that will run when a player votes.                                                                                                                                                                                                                                                                                         |
| /pocketvote cmd add [command]   | pocketvote.admin | Adds a command that is ran when a player votes. The following variables can be used in the command and will be replaced by the correct data: **%player**, **%ip** and **%site**. *Note that this command runs immediately as a player votes, regardless of whether they are online or not.*                                                   |
| /pocketvote cmd remove [id]     | pocketvote.admin | Removes the specified command. Use ‘cmd list’ to find the command id.                                                                                                                                                                                                                                                                         |
| /pocketvote cmdo list           | pocketvote.admin | Lists the commands that will run when a player votes and is online.                                                                                                                                                                                                                                                                           |
| /pocketvote cmdo add [command]  | pocketvote.admin | Adds a command that is ran when a player votes and is online. The following variables can be used in the command and will be replaced by the correct data: **%player**, **%ip** and **%site**. *Note that this command runs only when the player is online! If they are not online while voting this command will not run until they log on.* |
| /pocketvote cmdo remove [id]    | pocketvote.admin | Removes the specified command. Use ‘cmdo list’ to find the command id.                                                                                                                                                                                                                                                                        |
| /pocketvote link [name]         | pocketvote.admin | Attempts to set your MCPE.guru link to the provided name. Your link will look something like this mcpe.guru/[name].                                                                                                                                                                                                                           |
| TODO: MCPE.guru commands!       |                  |                                                                                                                                                                                                                                                                                                                                               |

Configuration
=============

| Key                         | Description                                                                                                                                                                                                                                                |
|-----------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| version                     | Used to track how the configuration should be altered in order to be completely up to date.                                                                                                                                                                |
| identity                    | The identity given to you from [PocketVote.io](https://pocketvote.io) which is used to identify your server.                                                                                                                                               |
| secret                      | The secret given to you from [PocketVote.io](https://pocketvote.io) which is given to voting sites.                                                                                                                                                        |
| lock                        | If set to true, the commands to alter secret or identity is disabled.                                                                                                                                                                                      |
| vote-expiration             | An amount of time to keep votes that have not been redeemed by a player, specified in **days**.                                                                                                                                                            |
| onvote.run-cmd              | A list of commands that are to be ran immediately as a vote is retrieved. The following variables can be used in the commands: %player, %site, %ip.                                                                                                        |
| onvote.online-cmd           | A list of commands that are to be ran as soon as the target player is online. The following variables can be used in the commands: %player, %site, %ip.                                                                                                    |
| votes                       | A list of votes waiting for players to log on. Please leave this alone.                                                                                                                                                                                    |
