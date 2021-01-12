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

global $_MLR_DEFAULT;
$_MLR_DEFAULT = array(
    'displayblocks' =>  3,          // show left & right blocks
    'filter_html'   =>  0,
    'censor'        =>  1,
    'confirm_period' => 3,          // Days allowed for confirmations
    'max_per_run'   =>  100,        // Max mailings done at once
    'queue_interval' => 1800,       // Time in seconds between runs
    'exp_days'      =>  60,         // default expiration
    'email_from'    =>  'noreply_mail', // noreply_mail or site_email
    'def_register_sub' => 1,        // New registrats will subscribe
    'del_user_unsub' => 1,          // Unsubscribe deleted users?
    'default_perms' =>  array(3, 2, 2, 2),
);


/**
 * Initialize Mailer plugin configuration.
 *
 * Creates the database entries for the configuation if they don't already
 * exist. Initial values will be taken from $_MLR_CONF if available (e.g. from
 * an old config.php), uses $_MLR_DEFAULT otherwise.
 *
 * @return  boolean     true: success; false: an error occurred
 */
function plugin_initconfig_mailer()
{
    global $_MLR_CONF, $_MLR_DEFAULT;

    if (is_array($_MLR_CONF) && (count($_MLR_CONF) > 1)) {
        $_MLR_DEFAULT = array_merge($_MLR_DEFAULT, $_MLR_CONF);
    }

    $c = config::get_instance();
    if (!$c->group_exists($_MLR_CONF['pi_name'])) {

        $c->add('sg_main', NULL, 'subgroup',
                0, 0, NULL, 0, true, $_MLR_CONF['pi_name']);
        $c->add('fs_main', NULL, 'fieldset',
                0, 0, NULL, 0, true, $_MLR_CONF['pi_name']);

        $c->add('displayblocks', $_MLR_DEFAULT['displayblocks'], 'select',
                0, 0, 13, 50, true, $_MLR_CONF['pi_name']);
        $c->add('filter_html', $_MLR_DEFAULT['filter_html'], 'select',
                0, 0, 0, 80, true, $_MLR_CONF['pi_name']);
        $c->add('censor', $_MLR_DEFAULT['censor'], 'select',
                0, 0, 0, 90, true, $_MLR_CONF['pi_name']);
        $c->add('confirm_period', $_MLR_DEFAULT['confirm_period'],
                'text', 0, 0, NULL, 130, true, $_MLR_CONF['pi_name']);
        $c->add('exp_days', $_MLR_DEFAULT['exp_days'],
                'text', 0, 0, NULL, 140, true, $_MLR_CONF['pi_name']);
        $c->add('email_from', $_MLR_DEFAULT['email_from'], 'select',
                0, 0, 14, 150, true, $_MLR_CONF['pi_name']);
        $c->add('def_register_sub', $_MLR_DEFAULT['def_register_sub'], 'select',
                0, 0, 4, 160, true, $_MLR_CONF['pi_name']);

        $c->add('fs_queue', NULL, 'fieldset', 0, 10, NULL, 0, true, 
                $_MLR_CONF['pi_name']);
        $c->add('max_per_run', $_MLR_DEFAULT['max_per_run'],
                'text', 0, 10, NULL, 10, true, $_MLR_CONF['pi_name']);
        $c->add('queue_interval', $_MLR_DEFAULT['queue_interval'],
                'text', 0, 10, NULL, 20, true, $_MLR_CONF['pi_name']);

        $c->add('fs_permissions', NULL, 'fieldset',
                0, 20, NULL, 0, true, $_MLR_CONF['pi_name']);
        $c->add('default_permissions', $_MLR_DEFAULT['default_permissions'],
                '@select', 0, 20, 12, 120, true, $_MLR_CONF['pi_name']);
        return true;
    } else {
        return false;
    }

}

?>
