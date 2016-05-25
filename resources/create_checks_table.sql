CREATE TABLE IF NOT EXISTS `pocketvote_checks` (
	`server_hash` VARCHAR(255) NOT NULL,
	`vote_id` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	`timestamp` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	INDEX `server_hash` (`server_hash`),
	INDEX `FK_vote_id_pocketvote_votes` (`vote_id`),
	CONSTRAINT `FK_vote_id_pocketvote_votes` FOREIGN KEY (`vote_id`) REFERENCES `pocketvote_votes` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
)
ENGINE=InnoDB
;