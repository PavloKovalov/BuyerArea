CREATE TABLE IF NOT EXISTS `buyerarea_settings` (
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE='utf8_unicode_ci';
/*~query~*/
CREATE TABLE IF NOT EXISTS `buyerarea_userdata` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` INT(10) UNSIGNED NOT NULL,
	`billing_address` TEXT NULL COLLATE 'utf8_unicode_ci',
	`shipping_address` TEXT NULL COLLATE 'utf8_unicode_ci',
	PRIMARY KEY (`id`)
)
COLLATE='utf8_unicode_ci'
ENGINE=MyISAM
ROW_FORMAT=DEFAULT
AUTO_INCREMENT=1;
/*~query~*/
CREATE TABLE IF NOT EXISTS `buyerarea_userhistory` (
	`user_id` INT(10) UNSIGNED NOT NULL,
	`ref_type` ENUM('cart','quote') NOT NULL COLLATE 'utf8_unicode_ci',
	`ref_id` INT(10) UNSIGNED NOT NULL,
	`date` DATETIME NULL DEFAULT NULL
)
COLLATE='utf8_unicode_ci'
ENGINE=MyISAM
ROW_FORMAT=DEFAULT;