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

$_SQL['mailer'] = "CREATE TABLE {$_TABLES['mailer']} (
  `mlr_id` varchar(40) NOT NULL default '',
  `mlr_uid` mediumint(8) NOT NULL default '1',
  `mlr_title` varchar(128) NOT NULL default '',
  `mlr_content` text NOT NULL,
  `mlr_hits` mediumint(8) unsigned NOT NULL default '0',
  `mlr_date` datetime NOT NULL default '0000-00-00 00:00:00',
  `mlr_sent_time` int(10) unsigned NOT NULL default '0',
  `mlr_format` varchar(20) NOT NULL default '',
  `commentcode` tinyint(4) NOT NULL default '0',
  `owner_id` mediumint(8) unsigned NOT NULL default '1',
  `group_id` mediumint(8) unsigned NOT NULL default '1',
  `perm_owner` tinyint(1) unsigned NOT NULL default '3',
  `perm_group` tinyint(1) unsigned NOT NULL default '2',
  `perm_members` tinyint(1) unsigned NOT NULL default '2',
  `perm_anon` tinyint(1) unsigned NOT NULL default '2',
  `mlr_help` varchar(255) default '',
  `mlr_nf` tinyint(1) unsigned default '0',
  `mlr_inblock` tinyint(1) unsigned default '1',
  `postmode` varchar(16) NOT NULL default 'html',
  `exp_days` int(5) default '0',
  PRIMARY KEY  (`mlr_id`),
  KEY `mailer_mlr_uid` (`mlr_uid`),
  KEY `mailer_mlr_date` (`mlr_date`)
)";

$_SQL['mailer_emails'] = "
CREATE TABLE {$_TABLES['mailer_emails']} (
  `id` mediumint(8) NOT NULL auto_increment,
  `dt_reg` DATETIME,
  `domain` varchar(96) default NULL,
  `email` varchar(96) default NULL,
  `status` tinyint(1) unsigned NOT NULL default '0',
  `token` varchar(255) default NULL,
  PRIMARY KEY  (id),
  KEY `idx_domain` (`domain`)
)";

$_SQL['mailer_queue'] = "
CREATE TABLE `{$_TABLES['mailer_queue']}` (
  `mlr_id` varchar(40) NOT NULL,
  `email` varchar(96) NOT NULL,
  `ts` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  UNIQUE KEY `mlr_id` (`mlr_id`,`email`)
) ENGINE=MyISAM
";

