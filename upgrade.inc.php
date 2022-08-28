<?php
/**
 * Upgrade routines for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2022 Lee Garner <lee@leegarner.com>
 * @package     forms
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once __DIR__ . '/sql/mysql_install.php';
use glFusion\Database\Database;
use glFusion\Log\Log;


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

    $current_ver = $_PLUGIN_INFO[Mailer\Config::PI_NAME]['pi_version'];
    $code_ver = plugin_chkVersion_mailer();

    if (!COM_checkVersion($current_ver, '0.2.0')) {
        $current_ver = '0.2.0';
        if (!MLR_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!MLR_do_set_version($current_ver)) return false;
    }

    // Finally set the version to the code version.
    // This matters if the last update was code-only.
    if (!MLR_do_set_version($code_ver)) return false;

    // Update any configuration item changes
    CTL_clearCache();
    USES_lib_install();
    global $mailerConfigData;
    require_once __DIR__ . '/install_defaults.php';
    _update_config(Mailer\Config::PI_NAME, $mailerConfigData);
    _MLR_remove_old_files();
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

    $db = Database::getInstance();

    // Execute SQL now to perform the upgrade
    Log::write('system', Log::INFO, "--Updating Mailer to version $version");
    foreach ($_MLR_UPGRADE[$version] as $sql) {
        Log::write('system', Log::INFO, "Mailer Plugin $version update: SQL => $sql");
        try {
            $db->conn->executeStatement($sql);
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
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

    $db = Database::getInstance();
    try {
        $db->conn->update(
            $_TABLES['plugins'],
            array(
                'pi_version' => $ver,
                'pi_gl_version' => Mailer\Config::get('gl_version'),
                'pi_homepage' => Mailer\Config::get('pi_url'),
            ),
            array('pi_name' => Mailer\Config::PI_NAME),
            array(
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::STRING,
            )
        );
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        return false;
    }
    return true;
}


/**
 * Check if a column exists in a table.
 *
 * @param   string  $table      Table Key, defined in shop.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _MLRtableHasColumn($table, $col_name)
{
    global $_TABLES;

    $db = Database::getInstance();
    try {
        $data = $db->conn->executeQuery(
            "SHOW COLUMNS FROM {$_TABLES[$table]} LIKE ?",
            array($col_name),
            array(Database::STRING)
        )->fetchAssociative();
    } catch (\Exception $e) {
        $data = false;
    }
    return !empty($data);
}


/**
 * Remove a file, or recursively remove a directory.
 *
 * @param   string  $dir    Directory name
 */
function _MLR_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . '/' . $object)) {
                    _MLR_rmdir($dir . '/' . $object);
                } else {
                    @unlink($dir . '/' . $object);
                    Log::write('system', Log::ERROR, "removed $dir/$object");
                }
            }
        }
        @rmdir($dir);
    } elseif (is_file($dir)) {
        @unlink($dir);
        Log::write('system', Log::ERROR, "removed $dir");
    }
}


/**
 * Remove deprecated files
 * Errors in unlink() and rmdir() are ignored.
 */
function _MLR_remove_old_files()
{
    global $_CONF;

    $paths = array(
        // private/plugins/membership
        __DIR__ => array(
            // 0.2.0
            'classes/Models/MailingList.class.php',
            'classes/Models/ApiInfo.class.php',
            'templates/admin/import_confirm.thtml',
        ),
        // public_html/membership
        $_CONF['path_html'] . 'membership' => array(
            'hook.php',
        ),
        // admin/plugins/membership
        $_CONF['path_html'] . 'admin/plugins/membership' => array(
        ),
    );

    foreach ($paths as $path=>$files) {
        foreach ($files as $file) {
            _MLR_rmdir("$path/$file");
        }
    }
}
