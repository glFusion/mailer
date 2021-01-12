<?php
/**
 * Upgrade routines for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 * @package     forms
 * @version     v0.0.2
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */

// Required to get the config values
global $_CONF, $_MLR_CONF;


/**
 * Perform the upgrade starting at the current version.
 * Nothing needs to be done here for just a code update.
 *
 * @param   string  $current_ver    Current installed version to be upgraded
 * @return  integer                 Error code, 0 for success
 */
function MLR_do_upgrade($current_ver)
{
    global $_MLR_CONF, $_TABLES;

    $error = 0;

    require_once dirname(__FILE__) . '/install_defaults.php';

    $c = config::get_instance();

    if ($current_ver < '0.0.2.1') {
        if ($c->group_exists($_MLR_CONF['pi_name'])) {
            $c->add('def_register_sub', $_MLR_DEFAULT['def_register_sub'], 
                'select', 0, 0, 4, 160, true, $_MLR_CONF['pi_name']);
            $c->del('subscribe_new_users', $_MLR_CONF['pi_name']);
            $c->del('allow_php', $_MLR_CONF['pi_name']);
            $c->del('sort_by', $_MLR_CONF['pi_name']);
            $c->del('sort_menu_by', $_MLR_CONF['pi_name']);
        }
    }

    if ($current_ver < '0.0.3') {
        $sql = array(
            "ALTER TABLE {$_TABLES['mailer_emails']}
            ADD UNIQUE `email_key` (`email`)",
        );
        $error = MLR_do_upgrade_sql('0.0.3', $sql);
        if ($error) return $error;
    }

    if ($current_ver < '0.0.4') {
        if ($c->group_exists($_MLR_CONF['pi_name'])) {
            $c->del('show_hits', $_MLR_CONF['pi_name']);
            $c->del('show_date', $_MLR_CONF['pi_name']);
            $c->del('atom_max_items', $_MLR_CONF['pi_name']);
            $c->del('in_block', $_MLR_CONF['pi_name']);
        }

        $sql = array(
            "ALTER TABLE {$_TABLES['mailer_emails']}
            ADD domain varchar(96) AFTER dt_reg",
            "ALTER TABLE {$_TABLES['mailer_emails']}
            ADD INDEX `idx_domain` (`domain`)",
        );
        $error = MLR_do_upgrade_sql('0.0.4', $sql);
        if ($error) return $error;
        
        // Clean up bad domains and set the domain in the emails table
        $sql = "SELECT id, email FROM {$_TABLES['mailer_emails']}";
        $res = DB_query($sql, 1);
        while ($A = DB_fetchArray($res, false)) {
            $pieces = split('@', $A['email']);
            $id = (int)$A['id'];
            if (count($pieces) != 2 || !MLR_isValidDomain($pieces[1])) {
                COM_errorLog("Invalid domain, deleted {$A['email']}");
                DB_delete($_TABLES['mailer_emails'], 'id', $id);
            } else {
                $domain = DB_escapeString($pieces[1]);
                DB_query("UPDATE {$_TABLES['mailer_emails']}
                    SET domain = '$domain'
                    WHERE id = '$id'", 1);
            }
        }
    }

    return $error;
}


/**
 * Actually perform any sql updates.
 * If there are no SQL statements, then SUCCESS is returned.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   array   $sql        Array of SQL statement(s) to execute
 * @return  integer             0 for success, >0 for failure
 */
function MLR_do_upgrade_sql($version='Undefined', $sql='')
{
    global $_TABLES, $_MLR_CONF;

    // We control this, so it shouldn't happen, but just to be safe...
    if ($version == 'Undefined') {
        COM_errorLog("Error updating {$_MLR_CONF['pi_name']} - Undefined Version");
        return 1;
    }

    // If no sql statements passed in, return success
    if (!is_array($sql))
        return 0;

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Mailer to version $version");
    foreach ($sql as $statement) {
        COM_errorLOG("Mailer Plugin $version update: SQL => $statement");
        DB_query($statement, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Mailer plugin update",1);
            return 1;
            break;
        }
    }

    return 0;

}


?>
