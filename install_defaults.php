<?php
/**
 * Default items for the Mailer plugin.
 *
 * Initial Installation Defaults used when loading the online configuration
 * records. These settings are only used during the initial installation
 * and not referenced any more once the plugin is installed
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2011 Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2008 Wayne Patterson <suprsidr@gmail.com>
 * @package     mailer
 * @version     v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die('This file can not be used on its own!');
}

/** @var global config data */
global $mailerConfigData;
$mailerConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'provider',
        'default_value' => 'Internal',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 5,
        'sort' => 10,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'def_register_sub',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 4,
        'sort' => 90,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'del_user_unsub',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 100,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'dbl_optin_members',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 110,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'log_level',
        'default_value' => 300,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 120,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'blk_show_subs',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 130,
        'set' => true,
        'group' => 'mailer',
    ),

    // Internal queue options
    array(
        'name' => 'fs_internal',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => NULL,
        'sort' => 5,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'displayblocks',
        'default_value' => 3,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 13,
        'sort' => 10,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'filter_html',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'censor',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'queue_interval',
        'default_value' => 1800,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'email_from',
        'default_value' => 'noreply_email',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 14,
        'sort' => 50,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'exp_days',
        'default_value' => 60,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'confirm_period',
        'default_value' => 3,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 0,
        'sort' => 70,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'max_per_run',
        'default_value' => 100,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'default_permissions',
        'default_value' => array (3, 2, 2, 2),
        'type' => '@select',
        'subgroup' => 0,
        'fieldset' => 5,
        'selection_array' => 12,
        'sort' => 90,
        'set' => true,
        'group' => 'mailer',
    ),

    // Mailchimp integration
    array(
        'name' => 'fs_mailchimp',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'mc_api_key',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'mc_def_list',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'mc_mrg_fname',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'mc_mrg_lname',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'mailer',
    ),

    // Sendinblue integration
    array(
        'name' => 'fs_sendinblue',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'sb_api_key',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'sb_def_list',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'mailer',
    ),
    array(
        'name' => 'sb_dbo_tpl',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'mailer',
    ),

);


/**
 * Initialize Mailer plugin configuration
 *
 * @return  boolean             True
 */
function plugin_initconfig_mailer()
{
    global $mailerConfigData;

    $c = config::get_instance();
    if (!$c->group_exists('mailer')) {
        USES_lib_install();
        foreach ($mailerConfigData AS $cfgItem) {
            _addConfigItem($cfgItem);
        }
    } else {
        COM_errorLog('initconfig error: Mailer config group already exists');
    }
    return true;
}

