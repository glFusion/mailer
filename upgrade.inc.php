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
 * @param   boolean $dvlp   True for a developer's upgrade
 * @return  boolean     True on success, False on error
 */
function MLR_do_upgrade($dvlp=false)
{
    global $_TABLES, $_MLR_UPGRADE, $_PLUGIN_INFO;

    $installed_ver = $_PLUGIN_INFO[Mailer\Config::get('pi_name')]['pi_version'];
    $code_ver = plugin_chkVersion_mailer();
    $current_ver = $installed_ver;

    if ($current_ver < '0.0.3') {
        $current_ver = '0.0.3';
        if (!MLR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!MLR_do_set_version($current_ver)) return false;
    }

    if ($current_ver < '0.0.4') {
        $current_ver = '0.0.4';

        if (!_MLRtableHasColumn('mailer_emails', 'domain')) {
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
                    $_MLR_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['mailer_emails']}
                        SET domain = '$domain'
                        WHERE id = '$id'";
                }
            }
        }
        if (!MLR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!MLR_do_set_version($current_ver)) return false;
    }

    // Update any configuration item changes
    USES_lib_install();
    global $mailerConfigData;
    require_once __DIR__ . '/install_defaults.php';
    _update_config(Mailer\Config::get('pi_name'), $mailerConfigData);

    return true;
}


/**
 * Actually perform any sql updates.
 * If there are no SQL statements, then SUCCESS is returned.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   array   $sql        Array of SQL statement(s) to execute
 * @return  integer             0 for success, >0 for failure
 */
function MLR_do_upgrade_sql($version)
{
    global $_MLR_UPGRADE;

    // If no sql statements passed in, return success
    if (!isset($_MLR_UPGRADE[$version])) {
        return true;
    }

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Mailer to version $version");
    foreach ($_MLR_UPGRADE[$version] as $sql) {
        COM_errorLog("Mailer Plugin $version update: SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Mailer plugin update",1);
        }
    }
    return true;
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
 */
function MLR_do_set_version($ver)
{
    global $_TABLES;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '$ver',
            pi_gl_version = '" . Mailer\Config::get('gl_version') . "',
            pi_homepage = '" . Mailer\Config::get('pi_url') . "'
        WHERE pi_name = '" . Mailer\Config::get('pi_name') . "'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the " . Config::get('pi_display_name') . " Plugin version to $ver",1);
        return false;
    } else {
        return true;
    }
}


/**
 * Check if a column exists in a table
 *
 * @param   string  $table      Table Key, defined in shop.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _MLRtableHasColumn($table, $col_name)
{
    global $_TABLES;

    $col_name = DB_escapeString($col_name);
    $res = DB_query("SHOW COLUMNS FROM {$_TABLES[$table]} LIKE '$col_name'");
    return DB_numRows($res) == 0 ? false : true;
}



