<?php
/**
 * Class to manage the email queue for the Internal provider.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.0.4
 * @since       v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Models;
use Mailer\Config;


/**
 * Queue processing class.
 * @package shop
 */
class Queue
{
    /**
     * Add a mailer to the queue for sending.
     *
     * @param   string  $mlr_id     Mailer record ID
     */
    public static function addMailer($mlr_id)
    {
        global $_TABLES;

        $mlr_id = COM_sanitizeID($mlr_id, false);
        $Mailer = new Mailer($mlr_id);
        if (!$Mailer->isNew()) {
            // Insert, ignoring duplicate entries
            $sql = "INSERT IGNORE INTO {$_TABLES['mailer_queue']}
                (mlr_id, email)
                SELECT '$mlr_id', email
                FROM {$_TABLES['mailer_emails']}
                WHERE status = " . Status::ACTIVE;
            DB_query($sql);
            $Mailer->updateSentTime();
            return true;
        } else {
            return false;
        }
    }


    public static function purge()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['mailer_queue']}");
    }


    public static function reset()
    {
        global $_TABLES;

        DB_query("UPDATE {$_TABLES['vars']}
            SET value = '0'
            WHERE name='mailer_lastrun'");
    }


    public static function deleteEmail($mlr_id, $email)
    {
        global $_TABLES;

        DB_delete(
            $_TABLES['mailer_queue'],
            array('mlr_id','email'),
            array(DB_escapeString($mlr_id), DB_escapeString($email))
        );
    }


    /**
     * Process the mail queue.
     * First, check to see if the configured interval has passed. Then
     * see if there are any messages to send. Finally, update the last-run
     * value and process the messages.
     *
     * There's a reason we don't batch by domain & use BCC: each message has
     * a custom unsubscribe footer.
     *
     * @param   boolean $force  True to force immediate processing of all items
     */
    public static function process($force=false)
    {
        global $_CONF, $_USER, $_TABLES, $LANG_MLR;

        if (!$force) {
            // Find out when we last ran, and don't run again if it's too soon
            $lastrun = DB_getItem(
                $_TABLES['vars'],
                'value',
                "name='mailer_lastrun'"
            );
            if ($lastrun === NULL) {
                // In case our gl_vars value got deleted somehow.
                DB_query("INSERT INTO {$_TABLES['vars']} (name, value)
                    VALUES ('mailer_lastrun', 0)
                    ON DUPLICATE KEY UPDATE value = 0", 1);
                $lastrun = 0;
            }
            $now = time();
            if ($now - $lastrun < (int)Config::get('queue_interval')) {
                return;
            }

            // Set the maximum messages to be sent
            if ((int)Config::get('max_per_run') > 0) {
                $sql_limit = ' LIMIT ' . (int)Config::get('max_per_run');
            } else {
                $sql_limit = '';
            }
        } else {
            // Force all items to be processed
            $sql_limit = '';
        }

        // Get the queued entries. Order by mlr_id so we can minimize DB calls.
        $sql = "SELECT q.mlr_id, q.email, e.token
            FROM {$_TABLES['mailer_queue']} q
            LEFT JOIN {$_TABLES['mailer_emails']} e
            ON q.email = e.email
            ORDER BY q.mlr_id ASC
            $sql_limit ";
        $res = DB_query($sql);
        if (!$res || DB_numRows($res) == 0) {
            return;
        }

        $N = new Mailer();
        $mlr_id = '';           // mailer ID used for control-break
        // Loop through the queue, sending the email to each address
        while ($A = DB_fetchArray($res, false)) {
            if ($mlr_id != $A['mlr_id']) {
                // New mailer ID, get it
                $mlr_id = $A['mlr_id'];

                if (!$N->Read($mlr_id)) {
                    // Invalid ID, delete all scheduled mailings & quit.
                    // Would be better to re-query the DB and continue with valid
                    // mailings, but for now this function will need to be called
                    // again.
                    DB_delete($_TABLES['mailer_queue'], 'mlr_id', $mlr_id);
                    continue;
                }
            }
            $N->mailIt($A['email'], $A['token']);

            // todo: Make this more efficient.
            // This is a DB call for every email address, better to batch them
            // but this protects against a connection issue mid-queue.
            DB_delete(
                $_TABLES['mailer_queue'],
                array('mlr_id', 'email'),
                array($mlr_id, $A['email'])
            );
        }

        // Update the last-run timestamp
        DB_query("UPDATE {$_TABLES['vars']}
            SET value = UNIX_TIMESTAMP()
            WHERE name = 'mailer_lastrun'" );
    }


    /**
     * List all the currently queued messages.
     *
     * @return  string      HTML for admin list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $_IMAGE_TYPE, $LANG_ADMIN, $LANG_MLR;

        $retval = '';

        $header_arr = array(      # display 'text' and use table field 'field'
            array('text' => $LANG_MLR['mlr_id'], 'field' => 'mlr_id', 'sort' => true),
            array('text' => $LANG_MLR['email'], 'field' => 'email', 'sort' => true),
            array('text' => $LANG_ADMIN['delete'], 'field' => 'deletequeue', 'sort' => false),
        );
        $defsort_arr = array('field' => 'mlr_id', 'direction' => 'asc');

        $text_arr = array(
            'has_extras' => true,
            'form_url' => Config::get('admin_url') . '/index.php?queue=x'
        );

        $query_arr = array(
            'table' => 'mailer_queue',
            'sql' => "SELECT * FROM {$_TABLES['mailer_queue']} WHERE 1=1 ",
            'query_fields' => array('email', 'mlr_id'),
            'default_filter' => COM_getPermSQL ('AND', 0, 3)
        );

        $options = array();
        $defsort_arr = array('field' => 'ts,email', 'direction' => 'ASC');

        $retval .= ADMIN_list(
            'mailer_queuelist',
            array(__CLASS__, 'getListField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', $options
        );
        return $retval;
    }


    /**
     * Get the display value for a list field, either mailer or subscriber.
     *
     * @param   string  $fieldname      Name of the field
     * @param   string  $fieldvalue     Value of the field
     * @param   array   $A              Array of all field name=>value pairs
     * @param   array   $icon_arr       Array of admin icons
     * @return  string                  Display value for $fieldname
     */
    public static function getListField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ADMIN, $LANG_MLR, $_TABLES, $_IMAGE_TYPE;

        $retval = '';
        static $admin_url = NULL;
        if ($admin_url === NULL) {
            $admin_url = Config::get('admin_url');
        }
        switch($fieldname) {
        case 'deletequeue':     // Delete an entry from the queue
            $retval = COM_createLink(
                "<img src=\"{$_CONF['layout_url']}/images/admin/delete.png\"
                height=\"16\" width=\"16\" border=\"0\"
                onclick=\"return confirm('Do you really want to delete this item?');\">",
                $admin_url . "/index.php?deletequeue=x&amp;mlr_id={$A['mlr_id']}&amp;email={$A['email']}"
            );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
