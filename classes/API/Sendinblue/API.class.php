<?php
/**
 * Sendinblue API for the Mailer plugin.
 *
 * @author      Lee Garner
 * @version     2.2
 * @package     mailchimp
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/MIT
 *              MIT License
 * @filesource
 */
namespace Mailer\API\Sendinblue;
use Mailer\Models\Subscriber;
use Mailer\Config;
use Mailer\Models\Status;


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
        Subscriber::getByEmail($email)->setStatus(Status::UNSUBSCRIBED);
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
            return true;
        }
        if ($uid > 1) {
            $this->mergePlugins($Sub->getUid());
        }
        $args = array(
            'email' => $Sub->getEmail(),
            'includeListIds' = $lists,
            'redirectionUrl' => $_CONF['site_url'] . '/index.php',
            'templateId' => Config::get('sb_dbo_tpl'),
            'updateEnabled' => true,
            'attributes' => $this->attributes,
        );
        if ($Sub->getStatus() == Status::ACTIVE) {
            // Not requiring double-opt-in
            $path = '/contacts';
        } else {
            $path = '/contacts/doubleOptinConfirmation';
        }
        $status = $this->post($path, $args);
        return $status;
    }


    /**
     * Get information about a specific member by email address.
     *
     * @param   string  $email      Email address
     * @return  array       Array of member information
     */
    public function memberInfo($email)
    {
        $params = array(
            'query' => $email,
        );
        return $this->get('/search-members', $params);
    }


    /**
     * Update the member information for a specific email and list.
     *
     * @param   string  $email      Email address
     * @param   string  $list_id    Mailing List ID
     * @param   array   $params     Array of parameters to update
     * @return      True on success, False on failure
     */
    public function updateMember($email, $uid=1, $params=array())
    {
        $data = array();
        if ($uid > 1) {
            $this->mergePlugins($uid);
        }
        if (isset($params['attributes'])) {
            $params['attributes'] = array_merge($this->attributes, $params['attributes']);
        }
        $email = urlencode($email);
        foreach ($params as $key=>$val) {
            switch ($key) {
            case 'attributes':
                if (!isset($data['attributes'])) {
                    $data['attributes'] = array();
                }
                foreach ($val as $k=>$v) {
                    switch ($k) {
                    case 'firstname':
                    case 'lastname':
                        $data['attributes'][strtoupper($k)] = $v;
                        break;
                    default:
                        $data['attributes'][$k] = $v;
                        break;
                    }
                }
                break;
            default:
                $data[$k] = $v;
                break;
            }
        }
        if (!empty($data)) {
            $response = $this->put("contacts/$email", $data);
            return $response;
        } else {
            return true;    // synthetic response
        }
    }


    /**
     * Get information about a specific member by email address.
     *
     * @param   string  $email      Email address
     * @return  array       Array of member information
     */
    public function getMemberInfo($email, $list_id='')
    {
        $retval = array();
        $email = urlencode($email);
        $status = $this->get("/contacts/{$email}");
        if ($status) {
            $data = $this->formatResponse($this->getLastResponse());
            var_dump($data);
            $attributes = array();
            if (isset($data['attributes'])) {
                // First and Last name are fixed by the provider.
                foreach ($data['attributes'] as $key=>$val) {
                    switch ($key) {
                    case 'FIRSTNAME':
                    case 'LASTNAME':
                        $attributes[strtolower($key)] = $val;
                        break;
                    default:
                        $attributes[$key] = $val;
                        break;
                    }
                }
            }
            if ($data['emailBlacklisted']) {
                $status = Status::UNSUBSCRIBED;
            } else {
                $status = Status::ACTIVE;
            }
            $retval = array(
                'provider_uid' => $data['id'],
                'email_address' => $data['email'],
                'email_type' => '',
                'status' => $status,
                'attributes' => $attributes,
            );
        }
        return $retval;
    }


}
