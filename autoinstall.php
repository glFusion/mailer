<?php
/**
 * Automatic installation routines for the Mailer plugin.
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

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** @global string $_DB_dbms */
global $_DB_dbms;

$pi_path = dirname(__FILE__);

/** Include plugin functions manually, since it's not installed yet */
require_once $pi_path . '/functions.inc';
/** Include database definitions */
require_once $pi_path . '/sql/'. $_DB_dbms. '_install.php';
use Mailer\Config;


/**
 * Plugin installation options
 * @global  array $INSTALL_plugin['mailer']
 */
$INSTALL_plugin['mailer'] = array(
    'installer' => array(
        'type' => 'installer',
        'version' => '1',
        'mode' => 'install',
    ),
    'plugin' => array(
        'type'      => 'plugin',
        'name'      => Config::PI_NAME,
        'ver'       => Config::get('pi_version'),
        'gl_ver'    => Config::get('gl_version'),
        'url'       => Config::get('pi_url'),
        'display'   => Config::get('pi_display_name')
    ),
    array(
        'type' => 'table',
        'table' => $_TABLES['mailer'],
        'sql'   => $_SQL['mailer'],
    ),
    array(
        'type' => 'table',
        'table' => $_TABLES['mailer_emails'],
        'sql'   => $_SQL['mailer_emails'],
    ),
    array(
        'type' => 'table',
        'table' => $_TABLES['mailer_queue'],
        'sql'   => $_SQL['mailer_queue'],
    ),
    array(
        'type' => 'group',
        'group' => 'mailer Admin',
        'desc' => 'Users in this group can administer the Mailer plugin',
        'variable' => 'admin_group_id',
        'admin' => true,
        'addroot' => true,
    ),
    array(
        'type' => 'feature',
        'feature' => 'mailer.admin',
        'desc' => 'Mailer Administration access',
        'variable' => 'admin_feature_id',
    ),
    array(
        'type' => 'feature',
        'feature' => 'mailer.edit',
        'desc' => 'Mailer Submission & Edit access',
        'variable' => 'edit_feature_id',
    ),
    array(
        'type' => 'mapping',
        'group' => 'admin_group_id',
        'feature' => 'admin_feature_id',
        'log' => 'Adding Admin feature to the admin group',
    ),
    array(
        'type' => 'mapping',
        'group' => 'admin_group_id',
        'feature' => 'edit_feature_id',
        'log' => 'Adding Edit feature to the admin group',
    ),
    array(
        'type' => 'block',
        'name' => 'mailer_subscribe',
        'title' => $LANG_MLR['block_title'],
        'phpblockfn' => 'phpblock_mailer',
        'block_type' => 'phpblock',
        'group_id' => 'admin_group_id',
    ),
);


/**
 * Puts the datastructures for this plugin into the glFusion database
 * Note: Corresponding uninstall routine is in functions.inc
 * @return  boolean True if successful False otherwise
 */
function plugin_install_mailer()
{
    global $INSTALL_plugin;

    COM_errorLog("Attempting to install the " . Config::get('pi_display_name') . " plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[Config::PI_NAME]);
    if ($ret > 0) {
        return false;
    }

    return true;
}



/**
 * Load plugin configuration from database.
 *
 * @return  boolean     true on success, otherwise false
 * @see     plugin_initconfig_mailer
 */
function plugin_load_configuration_mailer()
{
    global $_CONF;

    require_once $_CONF['path_system'] . 'classes/config.class.php';
    require_once __DIR__ . '/install_defaults.php';

    return plugin_initconfig_mailer();
}


/**
 * Add a value to the gl_vars table to indicate the last runtime.
 */
function plugin_postinstall_mailer()
{
    global $_TABLES;

    DB_query("INSERT INTO {$_TABLES['vars']} VALUES ('mailer_lastrun', '0')",1);
    return true;
}

