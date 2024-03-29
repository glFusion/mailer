<?php
/**
 * Class to handle logging.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.1.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer;
use glFusion\Log\Log;


/**
 * Class for Mailer Cache.
 * @package mailer
 */
class Logger
{
    /**
     * Write a log file entry to the specified file.
     *
     * @param   string  $logentry   Log text to be written
     * @param   string  $logfile    Log filename, 'mailer.log' by default
     */
    private static function write($logentry, $logfile='')
    {
        global $_CONF, $_USER, $LANG01;

        if ($logentry == '') {
            return;
        }

        // A little sanitizing
        $logentry = str_replace(
            array('<'.'?', '?'.'>'),
            array('(@', '@)'),
            $logentry);
        $timestamp = strftime( '%c' );
        if ($logfile == '') {
            $logfile = Config::PI_NAME . '.log';
        }
        $logfile = $_CONF['path_log'] . $logfile;

        // Can't open the log file?  Return an error
        if (!$file = fopen($logfile, 'a')) {
            Log::write('system', Log::ERROR, "Unable to open $logfile");
            return;
        }

        // Get the user name if it's not anonymous
        if (isset($_USER['uid'])) {
            $byuser = $_USER['uid'] . '-'.
                COM_getDisplayName(
                    $_USER['uid'],
                    $_USER['username'], $_USER['fullname']
                );
        } else {
            $byuser = 'anon';
        }
        $byuser .= '@' . $_SERVER['REMOTE_ADDR'];

        // Write the log entry to the file
        fputs($file, "$timestamp ($byuser) - $logentry\n");
        fclose($file);
    }


    /**
     * Write an entry to the Audit log.
     *
     * @param   string  $msg        Message to log
     */
    public static function Audit($msg)
    {
        $logfile = Config::PI_NAME . '.log';
        self::write($msg, $logfile);
    }


    /**
     * Write an entry to the system log.
     * Just a wrapper for COM_errorLog().
     *
     * @param   string  $msg        Message to log
     */
    public static function System($msg)
    {
        Log::write('system', Log::ERROR, Config::PI_NAME . ':: ' . $msg);
    }


    /**
     * Write a debug log message.
     * Uses the System() function if debug logging is enabled.
     *
     * @param   string  $msg        Message to log
     */
    public static function Debug($msg) : void
    {
        if ((int)Config::get('log_level') <= 100) {
            self::Audit('DEBUG: ' . $msg);
        }
    }

}
