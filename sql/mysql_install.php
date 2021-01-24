<?php
/**
 * Table definitions for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2008 Wayne Patterson <suprsidr@gmail.com>
 * @package     mailer
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

$_SQL= array(
'mailer' => "CREATE TABLE {$_TABLES['mailer_campaigns']} (
 `mlr_id` varchar(40) NOT NULL DEFAULT '',
  `mlr_uid` mediumint(8) NOT NULL DEFAULT 1,
  `mlr_title` varchar(128) NOT NULL DEFAULT '',
  `mlr_content` text NOT NULL,
  `mlr_hits` mediumint(8) unsigned NOT NULL DEFAULT 0,
  `mlr_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `mlr_sent_time` int(10) unsigned NOT NULL DEFAULT 0,
  `mlr_format` varchar(20) NOT NULL DEFAULT '',
  `commentcode` tinyint(4) NOT NULL DEFAULT 0,
  `owner_id` mediumint(8) unsigned NOT NULL DEFAULT 1,
  `group_id` mediumint(8) unsigned NOT NULL DEFAULT 1,
  `perm_owner` tinyint(1) unsigned NOT NULL DEFAULT 3,
  `perm_group` tinyint(1) unsigned NOT NULL DEFAULT 2,
  `perm_members` tinyint(1) unsigned NOT NULL DEFAULT 2,
  `perm_anon` tinyint(1) unsigned NOT NULL DEFAULT 2,
  `mlr_help` varchar(255) DEFAULT '',
  `mlr_nf` tinyint(1) unsigned DEFAULT 0,
  `mlr_inblock` tinyint(1) unsigned DEFAULT 1,
  `postmode` varchar(16) NOT NULL DEFAULT 'html',
  `exp_days` int(5) DEFAULT 0,
  `show_unsub` tinyint(1) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`mlr_id`),
  KEY `mailer_mlr_uid` (`mlr_uid`),
  KEY `mailer_mlr_date` (`mlr_date`)
) ENGINE=MyISAM",

'mailer_emails' => "CREATE TABLE {$_TABLES['mailer_subscribers']} (
  `sub_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT 1,
  `dt_reg` datetime DEFAULT NULL,
  `domain` varchar(96) DEFAULT NULL,
  `email` varchar(96) DEFAULT NULL,
  `status` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`sub_id`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_domain` (`domain`),
  KEY `idx_uid` (`uid`)
) ENGINE=MyISAM",

'mailer_queue' => "CREATE TABLE `{$_TABLES['mailer_queue']}` (
  `mlr_id` varchar(40) NOT NULL,
  `email` varchar(96) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  UNIQUE KEY `mlr_id` (`mlr_id`,`email`)
) ENGINE=MyISAM",

'mailer_txn' => "CREATE TABLE `{$_TABLES['mailer_txn']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(40) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `txn_id` varchar(128) DEFAULT NULL,
  `txn_date` datetime DEFAULT NULL,
  `data` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_txn` (`provider`,`type`,`txn_id`,`txn_date`)
) ENGINE=MyISAM",

'mailer_provider_campaigns' => "CREATE TABLE `{$_TABLES['mailer_provider_campaigns']}` (
  `mlr_id` varchar(20) NOT NULL,
  `provider` varchar(20) NOT NULL,
  `provider_mlr_id` varchar(20) NOT NULL,
  `tested` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`mlr_id`,`provider`)
) ENGINE=MyISAM",
);

global $_MLR_UPGRADE;
$_MLR_UPGRADE = array(
    '0.0.3' => array(
        "ALTER TABLE {$_TABLES['mailer_subscribers']} ADD UNIQUE `email_key` (`email`)",
    ),
    '0.0.4' => array(
        "ALTER TABLE {$_TABLES['mailer_subscribers']} ADD domain varchar(96) AFTER dt_reg",
        "ALTER TABLE {$_TABLES['mailer_subscribers']} ADD INDEX `idx_domain` (`domain`)",
    ),
);

