CREATE TABLE IF NOT EXISTS `{TABLE_PREFIX}reactions` (
    `reaction_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `title` varchar(100) NOT NULL DEFAULT '',
    `image_url` varchar(255) NOT NULL DEFAULT '',
    `image_url_2x` varchar(255) NOT NULL DEFAULT '',
    `sprite_mode` tinyint(1) NOT NULL DEFAULT '0',
    `sprite_params` text,
    `text_color` varchar(10) NOT NULL DEFAULT '#0084FF',
    `reaction_score` int(11) NOT NULL DEFAULT '0',
    `reaction_type` enum('positive','neutral','negative') NOT NULL DEFAULT 'positive',
    `display_order` int(10) unsigned NOT NULL DEFAULT '0',
    `active` tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`reaction_id`),
    KEY `display_order` (`display_order`),
    KEY `active` (`active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{TABLE_PREFIX}reaction_content` (
    `reaction_content_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `reaction_id` int(10) unsigned NOT NULL,
    `content_type` varchar(25) NOT NULL DEFAULT 'post',
    `content_id` int(10) unsigned NOT NULL,
    `reaction_user_id` int(10) unsigned NOT NULL,
    `reaction_date` int(10) unsigned NOT NULL,
    `content_user_id` int(10) unsigned NOT NULL,
    `is_counted` tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`reaction_content_id`),
    UNIQUE KEY `content_reaction_user` (`content_type`,`content_id`,`reaction_user_id`),
    KEY `reaction_id` (`reaction_id`),
    KEY `content` (`content_type`,`content_id`),
    KEY `reaction_user_id` (`reaction_user_id`),
    KEY `content_user_id` (`content_user_id`),
    KEY `reaction_date` (`reaction_date`),
    KEY `is_counted` (`is_counted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Table to track reaction-reputation entries for display formatting
CREATE TABLE IF NOT EXISTS `{TABLE_PREFIX}reaction_reputation` (
    `reputation_rid` int(10) unsigned NOT NULL,
    `reaction_content_id` int(10) unsigned NOT NULL,
    `from_uid` int(10) unsigned NOT NULL,
    `to_uid` int(10) unsigned NOT NULL,
    `reaction_id` int(10) unsigned NOT NULL,
    `content_type` varchar(25) NOT NULL DEFAULT 'post',
    `content_id` int(10) unsigned NOT NULL,
    `dateline` int(10) unsigned NOT NULL,
    PRIMARY KEY (`reputation_rid`),
    KEY `reaction_content_id` (`reaction_content_id`),
    KEY `from_uid` (`from_uid`),
    KEY `to_uid` (`to_uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
