<?php
/**
 * Table definitions and other static config variables.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      Wayne Patterson <suprsidr@gmail.com>
 * @copyright   Copyright (C) 2009 Wayne Patterson <suprsidr@gmail.com>
 * @copyright   Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.0.3
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

/**
 * Global array of table names from glFusion
 * @global  array $_TABLES
 */
global $_TABLES;

/**
*   Global table name prefix
*   @global string $_DB_table_prefix
*/
global $_DB_table_prefix;

$_TABLES['mailer']          = $_DB_table_prefix . 'mailer';
$_TABLES['mailer_emails']   = $_DB_table_prefix . 'mailer_emails';
$_TABLES['mailer_queue']    = $_DB_table_prefix . 'mailer_queue';
$_TABLES['mailer_txn']      = $_DB_table_prefix . 'mailer_txn';

Mailer\Config::set('pi_version', '0.0.4');
Mailer\Config::set('gl_version', '1.7.8');

