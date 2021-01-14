<?php
/**
 * Internal API class for the Mailer plugin.
 *
 * @author      Lee Garner
 * @version     2.2
 * @package     mailer
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/MIT
 *              MIT License
 * @filesource
 */
namespace Mailer\API\Internal;
use Mailer\Models\Subscriber;
use Mailer\Logger;
use Mailer\Notifier;


/**
 * Internal API driver.
 * @package mailer
 */
class API extends \Mailer\API
{
    private $id = 0;        // record id
    private $dt_reg = '';   // datetime registered
    private $domain = '';   // email domain
    private $email_addr = ''; // email address
    private $status =  0;   // status, blacklisted, etc.
    private $token = '';    // access token


    /**
     * Get a list of members subscribed to a given list.
     *
     * @param   string  $list_id    Mailing List ID
     * @param   string  $status     Member status, default=subscribed
     * @param   mixed   $since      Starting date for subscriptions
     * @param   integer $offset     Offset to retrieve, in case of large lists
     * @param   integer $count      Maximum number of members to retrieve.
     * @return  array       Array of data
     */
    public function listMembers($opts=array())
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['mailer_emails']}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['id']] = $A;     // todo, use subscriber model
        }
        return $retval;
    }


    /**
     * Get an array of mailing lists visible to this API key.
     *
     * @param   array   $fields     Fields to retrieve
     * @param   integer $offset     First record offset, for larget datasets
     * @param   integer $count      Number of items to return
     * @return  array       Array of list data
     */
    public function lists($fields=array(), $offset=0, $count=25)
    {
        return array(
            'list_id' => 1,
            'list_name' => 'Main List',
        );
    }


    /**
     * Subscribe an email address to one or more lists.
     *
     * @param   string  $email      Email address
     * @param   array   $args       Array of additional args to supply
     * @param   array   $lists      Array of list IDs
     * @return  boolean     True on success, False on error
     */
    public function subscribe($email, $args=array(), $lists=array())
    {
        global $_TABLES, $_MLR_CONF;

        if (empty($lists)) {
            return true;
        } elseif (!is_array($lists)) {
            $lists = array($lists);
        }

        $this->resetErrors();

        $pieces = explode('@', $email);
        if (count($pieces) != 2) {
            $this->addError('Invalid email address');
            return false;
        }
        //$db_email = DB_escapeString($email);
        $Sub = Subscriber::getByEmail($email);

        // Check if the address already exists, and alter the status
        //$id = DB_getItem($_TABLES['mailer_emails'], 'id ', "email='{$db_email}'");
        if ($Sub->getID() > 0) {
            $this->addError('Email already exists');
            return false;
        }

        if (self::isValidDomain($pieces[1])) {
            // Valid domain, add the record
            $Sub->Save();
            /*DB_query("INSERT INTO {$_TABLES['mailer_emails']} (
                `dt_reg`, `domain`, `email`, `token`, `status`
                ) VALUES (
                '{$_MLR_CONF['now']}', '{$domain}', '{$db_email}',
                '$token', $status
                )",
                1
            );

            if (DB_error()) {
                //MLR_auditLog("Error subscribing $email, status $status");
                $this->addError('Error adding ' . $email . ' to database');
                return false;
            } else {
                return true;
            }*/
            return true;
        } else {
            $this->addError('Invalid email domain ' . $domain);
            return false;
        }
    }


    /**
     * Remove a single email address from our list.
     * The token parameter can be filled when this is called from a public
     * page in order to prevent users from unsubscribing other users.
     *
     * @param   string  $email  Email address to remove
     * @param   string  $token  Optional token, to authenticate the removal
     * @return  boolean         True on success, False if user not found
     */
    public function unsubscribe($email, $lists=array()) 
    {
        global $_TABLES;

        // Sanitize the input and create a query to find the existing record id
        $email = DB_escapeString($email);
        $where = "email = '$email' AND status <> " . Status::BLACKLIST;
        /*if ($token != '') {
            $token = DB_escapeString($token);
            $where .= " AND token = '$token'";
        }*/
        $id = (int)DB_getItem($_TABLES['mailer_emails'], 'id', $where);
        if ($id > 0) {
            DB_delete($_TABLES['mailer_emails'], 'id', $id);
            Logger::Audit("Unsubscribed $email (mailer $mlr_id)");
            return true;
        } else {
            $this->addError("Attempted to unsubscribe $email, not a subscriber");
            return false;
        }
    }


    /**
     * Get the tags associated with a subscriber.
     *
     * @param   string  $email      Subscriber email address
     * @param   string  $list_id    Mailing list ID
     * @return  array       Array of tags
     */
    public function getTags($email, $list_id='')
    {
        return array();
    }


    /**
     * Update a subscriber's tags.
     *
     * @param   string  $email  Email address
     * @param   array   $tags   Array of tags (name=>active)
     * @param   array   $lists  Array of email lists
     */
    public function updateTags($email, $tags, $lists=array())
    {
        return true;
    }


    /**
     * Get information about a specific member by email address.
     *
     * @param   string  $email      Email address
     * @return  array       Array of member information
     */
    public function memberInfo($email)
    {
        global $_TABLES;

        $email = DB_escapeString($email);
        $sql = "SELECT * FROM {$_TABLES['mailer_emails']}
            WHERE email = '$email'";
        $res = DB_query($sql);
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $retval = new Subscriber;
            $retval['id'] = $A['id'];
            $retval['email_address'] = $A['email_address'];
            return $retval;
        }
        return false;
    }


    /**
     * This API does nothing when updateMember() is called.
     */
    public function updateMember($email, $params=array())
    {
    }


    /**
     * Send a double opt-in notification to a subscriber.
     *
     * @param   object  $Subscriber     Subscriber object
     * @return  boolean     True on success, False on error
     */
    public static function sendDoubleOptin($Subscriber)
    {
        return Notifier::Send($Subscriber->getEmail(), $Subscriber->getToken());
    }



    public function getFeatures()
    {
        return array('mailers', 'subscribers', 'queue');
    }


}
