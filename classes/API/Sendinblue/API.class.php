<?php
/**
 * Sendinblue API for the Mailer plugin.
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
namespace Mailer\API\Sendinblue;
use Mailer\Config;
use Mailer\Models\Subscriber;
use Mailer\Models\Status;
use Mailer\Models\ApiInfo;


/**
 * Sendinblue API v3
 * @see http://developers.sendinblue.com.
 */
class API extends \Mailer\API
{
    private $api_key = '';
    protected $cfg_list_key = 'sb_def_list';

    /**
     * Create a new instance.
     *
     * @param   string $api_key Your MailChimp API key
     * @param   string $api_endpoint Optional custom API endpoint
     * @throws  \Exception
     */
    public function __construct()
    {
        $this->api_key = Config::get('sb_api_key');
        $this->api_endpoint = 'https://api.sendinblue.com/v3';
        $this->last_response = array('headers' => null, 'body' => null);
        $this->setHeaders(array(
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $this->api_key,
        ));
    }


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
    public function listMembers($list_id, $opts=array())
    {
        $retval = array();
        $response = $this->get("contacts/lists/$list_id/contacts");
        if (is_array($response) && isset($response['contacts'])) {
            foreach ($response['contacts'] as $resp) {
                $retval[$resp['id']] = new Subscriber;
                $retval[$resp['id']]['id'] = $resp['id'];
                $retval[$resp['id']]['email_address'] = $resp['email'];
                $retval[$resp['id']]['merge_fields'] = $resp['attributes'];
            }
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
    public function lists($offset=0, $count=25)
    {
        $retvla = array();
        $params = array(
            'offset' => $offset,
            'limit' => $count,
        );
        $response = $this->get('contacts/lists', $params);
        if (is_array($response) && isset($response['lists'])) {
            foreach ($response['lists'] as $resp) {
                $retval[$resp['id']] = new MailingList(array(
                    'id' => $resp['id'],
                    'name' => $resp['name'],
                    'members' => $resp['totalSubscribers'],
                ) );
            }
        }
        return $retval;
    }


    /**
     * Get an array of lists for which a specific email address is subscribed.
     *
     * @param   string  $email      Email address
     * @param   array   $fields     Fields to retrieve
     * @param   integer $offset     First record offset, for larget datasets
     * @param   integer $count      Number of items to return
     * @return  array       Array of list data
     */
    public function listsForEmail($email, $fields=array(), $offset=0, $count=25)
    {
        if (empty($fields)) {
            $fields = array('lists');
        };
        $params = array(
            'fields' => $fields,
            'offset' => $offset,
            'count' => $count,
            'email' => $email,
        );
        return $this->get('lists', $params);
    }


    /**
     * Unsubscribe an email address from one or more lists.
     *
     * @param   string  $email      Email address
     * @param   array   $lists      Array of list IDs
     * @return  boolean     True on success, False on error
     */
    public function unsubscribe($email='', $lists=array())
    {
        $status = false;
        if (empty($lists) && !empty($Config::get('sb_def_list'))) {
            $lists = array(Config::get('sb_def_list'));
        }
        $args = array(
            'emails' => array($email),
        );
        foreach ($lists as $list) {
            $this->put("/contacts/lists/{$list}/contacts/remove", $args);
        }
        Subscriber::getByEmail($email)->updateStatus(Status::UNSUBSCRIBED);
        return true;
    }


    /**
     * Subscribe an email address to one or more lists.
     *
     * @param   string  $email      Email address
     * @param   array   $args       Array of additional args to supply
     * @param   array   $lists      Array of list IDs
     * @return  boolean     True on success, False on error
     */
    public function subscribe($Sub, $lists=array())
    {
        global $_CONF;

        if (empty($lists)) {
            $lists = array($this->list_id);
        } elseif (!is_array($lists)) {
            $lists = array($lists);
        }
        if (empty($lists)) {
            return Status::SUB_INVALID;
        }

        $args = array(
            'email' => $Sub->getEmail(),
            'includeListIds' => $lists,
            'redirectionUrl' => $_CONF['site_url'] . '/index.php',
            'templateId' => Config::get('sb_dbo_tpl'),
            'updateEnabled' => true,
            'attributes' => $Sub->getAttributes(),
        );
        if ($Sub->getStatus() == Status::ACTIVE) {
            // Not requiring double-opt-in
            $path = '/contacts';
        } else {
            $path = '/contacts/doubleOptinConfirmation';
        }
        $status = $this->post($path, $args);
        return $status ? Status::SUB_SUCCESS : Status::SUB_ERROR;
    }


    /**
     * Update the member information for a specific email and list.
     *
     * @param   string  $email      Email address
     * @param   string  $list_id    Mailing List ID
     * @param   array   $params     Array of parameters to update
     * @return      True on success, False on failure
     */
    public function updateMember($Sub, $lists=array())
    {
        $params = array(
            'status' => $Sub->getStatus(),
            'attributes' => $Sub->getAttributes(),
        );
        $email = urlencode($Sub->getEmail());
        $response = $this->put("contacts/$email", $params);
        return $response;
    }


    /**
     * Get information about a specific member by email address.
     *
     * @param   object  $Sub    Subscriber object
     * @return  array       Array of member information
     */
    public function getMemberInfo($Sub, $list_id='')
    {
        $retval = array();
        $email = urlencode($Sub->getEmail());
        $status = $this->get("/contacts/{$email}");
        if ($status) {
            $data = $this->formatResponse($this->getLastResponse());
            $attributes = isset($data['attributes']) ? $data['attributes'] : array();
            if ($data['emailBlacklisted']) {
                $status = Status::UNSUBSCRIBED;
            } else {
                $status = Status::ACTIVE;
            }
            $retval = new ApiInfo;
            $retval['provider_uid'] = $data['id'];
            $retval['email_address'] = $data['email'];
            $retval['email_type'] = '';
            $retval['status'] = $status;
            foreach ($attributes as $k=>$v) {
                $retval->setAttribute($k, $v);
            }
        }
        return $retval;
    }

}
