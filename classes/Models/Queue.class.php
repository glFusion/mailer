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
use Mailer\API;
use glFusion\Database\Database;
use glFusion\Log\Log;
use glFusion\FieldList;
// For glFusion 2.1.0+
// use glFusion\Notifier;
// Until then, use our own notifier
use Mailer\Notifier;


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
            $db = Database::getInstance();
            try {
                // Insert, ignoring duplicate entries
                $db->conn->executeStatement(
                    "INSERT IGNORE INTO {$_TABLES['mailer_queue']}
                        (mlr_id, email)
                        SELECT '$mlr_id', email
                        FROM {$_TABLES['mailer_subscribers']}
                        WHERE status = " . Status::ACTIVE,
                    array($mlr_id),
                    array(Database::STRING),
                );
                $Mailer->updateSentTime();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }


    /**
     * Add a batch of specific email addresses to a mailer's queue.
     *
     * @param   string  $mlr_id     Campaign ID
     * @param   array   $users      Array of user info (uid, name, email)
     * @return  boolean     True on success, False on error
     */
    public static function addEmails(string $mlr_id, array $users) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        $retval = true;

        foreach ($users as $user) {
            $uid = isset($user['uid']) ? $user['uid'] : 0;
            $name = isset($user['name']) ? $user['name'] : '';
            if (!isset($user['email'])) {
                continue;
            }
            try {
                // Insert, ignoring duplicate entries
                $db->conn->executeStatement(
                    "INSERT IGNORE INTO {$_TABLES['mailer_queue']}
                        (mlr_id, name, email) VALUES (?, ?, ?)",
                    array(
                        $mlr_id,
                        $name,
                        $user['email']),
                    array(
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                    ),
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $retval = false;
            }
        }
        return $retval;
    }


    /**
     * Delete all entries from the queue at once.
     */
    public static function purge() : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        $db->conn->executeQuery("TRUNCATE {$_TABLES['mailer_queue']}");
    }


    /**
     * Resets the last-run timestamp so the queue will be picked up.
     *
     * @param   integer $ts     Timestamp to set
     */
    public static function reset(?int $ts = 0) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        $db->conn->update(
            $_TABLES['vars'],
            array('value' => $ts),
            array('name' => 'mailer_lastrun'),
            array(Database::INTEGER)
        );
    }


    /**
     * Delete multiple records from the queue.
     *
     * @param   array   $q_ids      Array of queue record IDs
     */
    public static function deleteMulti(array $q_ids) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        $db->conn->executeQuery(
            "DELETE FROM {$_TABLES['mailer_queue']}
            WHERE q_id IN (" . implode(',', $q_ids) . ')'
        );
    }


    /**
     * @deprecated
     */
    public static function deleteEmail($mlr_id, $email)
    {
        global $_TABLES;

        $db = Database::getInstance();
        $db->conn->delete(
            $_TABLES['mailer_queue'],
            array(
                'mlr_id' => $mlr_id,
                'email' => $email,
            ),
            array(
                Database::STRING,
                Database::STRING,
            )
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
    public static function process(bool $force=false) : void
    {
        global $_CONF, $_USER, $_TABLES, $LANG_MLR, $_VARS;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();

        // Find out when we last ran, and don't run again if it's too soon
        $lastrun = isset($_VARS['mailer_lastrun']) ? $_VARS['mailer_lastrun'] : NULL;
        if ($lastrun === NULL) {
            // In case our gl_vars value got deleted somehow.
            try {
                $db->conn->insert(
                    $_TABLES['vars'],
                    array(
                        'value' => time(),
                        'name' => 'mailer_lastrun',
                    ),
                    array(
                        Database::INTEGER,
                        Database::STRING,
                    )
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
            $lastrun = 0;
        }

        $now = time();
        if (!$force && ($now - $lastrun < (int)Config::get('queue_interval'))) {
            return;
        }

        // Now update the last-run timestamp
        try {
            $db->conn->update(
                $_TABLES['vars'],
                array(
                    'value' => $now,
                ),
                array(
                    'name' => 'mailer_lastrun',
                ),
                array(
                    Database::INTEGER,
                    Database::STRING,
                )
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }

        // Set the maximum messages to be sent
        if ((int)Config::get('max_per_run') > 0) {
            $qb->setFirstResult(0)->setMaxResults((int)Config::get('max_per_run'));
        }

        // Get the queued entries. Order by mlr_id so we can minimize DB calls.
        try {
            $stmt = $qb->select('q.q_id', 'q.mlr_id', 'q.name', 'q.email', 'e.token')
               ->from($_TABLES['mailer_queue'], 'q')
               ->leftJoin('q', $_TABLES['mailer_subscribers'], 'e', 'q.email=e.email')
               ->orderBy('q.mlr_id', 'ASC')
               ->execute();
            $data = $stmt->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = NULL;
        }

        if (empty($data)) {
            return;
        }

        $N = new Campaign;      // Create a campaign object once
        //$Email = API::getInstance();
        $Email = Notifier::getProvider('Email');

        $mlr_id = '';           // mailer ID used for control-break
        $recipients = array();
        // Loop through the queue, sending the email to each address
        foreach ($data as $A) {
            if ($mlr_id != $A['mlr_id']) {
                if ($mlr_id != '') {    // not the first time through
                    $Email->send();
                    self::_deleteRecipients($recipients);
                    $recipients = array();
                }
                $Email = Notifier::getProvider('Email');

                // New mailer ID, get it
                $mlr_id = $A['mlr_id'];
                if (!$N->Read($mlr_id)) {
                    // Invalid ID, delete all scheduled mailings & quit.
                    // Would be better to re-query the DB and continue with valid
                    // mailings, but for now this function will need to be called
                    // again.
                    $db->conn->delete(
                        $_TABLES['mailer_queue'],
                        array('mlr_id' => $mlr_id),
                        array(Database::STRING)
                    );
                    continue;
                }
                $Email->setMessage($N->getContent(), true)
                      ->setSubject($N->getTitle());
            }
            $Email->addBcc(0, $A['name'], $A['email']);
            $recipients[] = $A['q_id'];
        }

        if (count($recipients) > 0) {
            $Email->send();
            self::_deleteRecipients($recipients);
        }
    }


    /**
     * Delete recipients from the queue in a batch.
     *
     * @param   array   $q_ids  Queue record IDs.
     */
    private static function _deleteRecipients(array $q_ids) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeStatement(
                "DELETE FROM {$_TABLES['mailer_queue']} WHERE q_id IN (?)",
                array($q_ids),
                array(Database::PARAM_INT_ARRAY)
           );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
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
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'deletequeue',
                'align' => 'center',
            ),
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
        $options = array(
            'chkdelete' => true,
            //'chkselect' => true,
            'chkfield' => 'q_id',
            'chkname' => 'deletequeue',
            'chkminimum' => 0,
            'chkall' => true,
            //'chkactions' => $chkactions,
        );
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
            $retval = FieldList::delete(array(
                'delete_url' => "$admin_url/index.php?deletequeue={$A['q_id']}",
                'attr' => array(
                    'onclick' => "return confirm('Do you really want to delete this item?');",
                )
            ) );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
