<?php
/**
 * Internal API class for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2020 Lee Garner <lee@leegarner.com>
 * @version     v0.4.0
 * @package     mailer
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\API\Internal;
use Mailer\Models\Subscriber;
use Mailer\Models\ApiInfo;
use Mailer\Models\Status;
use Mailer\Notifier;


/**
 * Internal API driver.
 * @package mailer
 */
class API extends \Mailer\API
{
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
     * Noop for this API, subscriber is added to the table by the
     * Subscriber class, no other action needed.
     *
     * @param   object  $Sub    Subscriber object
     * @return  integer     Result status
     */
    public function subscribe($Sub)
    {
        if (!self::isValidEmail($Sub->getEmail())) {
            return Status::SUB_INVALID;
        } else {
            return Status::SUB_SUCCESS;
        }
    }


    /**
     * Remove a single email address from our list.
     *
     * @param   object  $Sub    Subscriber object
     * @return  boolean         True on success, False if user not found
     */
    public function unsubscribe($email, $lists=array()) 
    {
        return true;
    }


    /**
     * Get the tags associated with a subscriber.
     *
     * @unused
     * @param   string  $email      Subscriber email address
     * @param   string  $list_id    Mailing list ID
     * @return  array       Array of tags
     */
    public function getTags(Subscriber $Sub)
    {
        return array();
    }


    /**
     * Update a subscriber's tags.
     *
     * @unused
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists  Array of email lists
     */
    public function updateTags(Subscriber $Sub)
    {
        return true;
    }


    /**
     * Get information about a specific member by email address.
     * This API just returns the information in the database, no attributes.
     *
     * @param   string  $email      Email address
     * @return  array       Array of member information
     */
    public function getMemberInfo(Subscriber $Sub, $list_id='')
    {
        $retval = new ApiInfo;
        $retval['provider_uid'] = $Sub->getId();
        $retval['email_address'] = $Sub->getEmail();
        $retval['status'] = $Sub->getStatus();
        foreach ($Sub->getAttributes() as $k=>$v) {
            $retval->setAttribute($k, $v);
        }
        return $retval;;
    }


    /**
     * This API does nothing when updateMember() is called.
     *
     * @param   object  $Sub    Subscriber object
     * @return  boolean     Always true
     */
    public function updateMember(Subscriber $Sub)
    {
        return true;
    }


    /**
     * Send a double opt-in notification to a subscriber.
     *
     * @param   object  $Sub    Subscriber object
     * @return  boolean     True on success, False on error
     */
    public static function sendDoubleOptin(Subscriber $Sub)
    {
        return Notifier::Send($Sub->getEmail(), $Sub->getToken());
    }


    /**
     * Get the features available to administrators.
     *
     * @return  array   Array of menus to show
     */
    public function getFeatures()
    {
        return array('mailers', 'subscribers', 'queue');
    }

}
