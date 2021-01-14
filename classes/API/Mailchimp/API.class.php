<?php
/**
 * Super-simple, minimum abstraction MailChimp API v3 wrapper.
 *
 * @author      Drew McLellan <drew.mclellan@gmail.com>
 * @version     2.2
 * @package     mailchimp
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/MIT
 *              MIT License
 * @filesource
 */
//namespace DrewM\MailChimp;
namespace Mailer\API\Mailchimp;
use Mailer\Config;
use Mailer\Models\Status;


/**
 * MailChimp API v3
 * @see http://developer.mailchimp.com.
 * @see https://github.com/drewm/mailchimp-api.
 */
class API extends \Mailer\API
{
    /** Mailchimp API key.
     * @var string */
    private $api_key;

    /** Mailchimp endpoint.
     * @var string */
    protected $api_endpoint = 'https://<dc>.api.mailchimp.com/3.0';

    protected $cfg_list_key = 'mc_def_list';

    /**
     * Create a new instance.
     *
     * @param   string $api_key Your MailChimp API key
     * @param   string $api_endpoint Optional custom API endpoint
     * @throws  \Exception
     */
    public function __construct()
    {
        $this->api_key = Config::get('mc_api_key');
        if (strpos($this->api_key, '-') === false) {
            throw new \Exception("Invalid MailChimp API key supplied.");
        }
        list(, $data_center) = explode('-', $this->api_key);
        $this->api_endpoint  = str_replace('<dc>', $data_center, $this->api_endpoint);
        $this->setHeaders(array(
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json',
            'Authorization: apikey ' . $this->api_key
        ));
        $this->last_response = array('headers' => null, 'body' => null);
    }


    /**
     * Create a new instance of a Batch request. Optionally with the ID of an existing batch.
     *
     * @param   string  $batch_id Optional ID of an existing batch, if you need to check its status for example.
     * @return  Batch            New Batch object.
     */
    public function new_batch($batch_id = null)
    {
        //require_once dirname(__FILE__) . '/Batch.class.php';
        return new Batch($this, $batch_id);
    }

    /**
     * Get the API Endpoint URL (private variable).
     *
     * @return  string  The url to the API endpoint
     */
    public function getApiEndpoint()
    {
        return $this->api_endpoint;
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
        $params = array(
            'count' => 10,
            'status' => 'subscribed',
            'offset' => 0,
        );
        foreach ($opts as $key=>$val) {
            switch ($key) {
            case 'unsubscribed_since':
                $params['status'] = 'unsubscribed';
            case 'since_last_changed':
            case 'since_timestamp_opt':
            case 'before_last_changed':
                $dt = new \Date($val);
                $params[$key] = $dt->format(\DateTime::ATOM);
                break;
            case 'count':
                $val = min($val, 1000);
                break;
            /*case 'fields':
                continue 2;
                break;*/
            }
            $params[$key] = urlencode($val);
            //$params[$key] = $val;
        }
        $url = http_build_query($params);
        //echo $url;die;
        return $this->get("lists/$list_id/members?$url");
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
        $params = array(
            'fields' => $fields,
            'offset' => $offset,
            'count' => $count,
        );
        return $this->get('lists', $params);
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
    public function unsubscribe($Sub, $lists=array())
    {
        if (empty($lists)) {
            $lists = array(Config::get('mc_def_list'));
        } elseif (!is_array($lists)) {
            $lists = array($lists);
        }
        if (empty($lists)) {
            return true;     // no lists specified, consider success
        }
        $hash = $this->subscriberHash($Sub->getEmail());
        $args = array(
            'email_address' => $Sub->getEmail(),
            'status' => self::_strStatus(Status::UNSUBSCRIBED),
            'merge_fields' => $Sub->getAttributes(array(
                'firstname' => Config::get('mc_mrg_fname'),
                'lastname' => Config::get('mc_mrg_lname')
            ) ),
        );
        foreach ($lists as $list_id) {
            //$status = $this->delete("/lists/$list_id/members/$hash");
            $status = $this->patch("/lists/{$list_id}/members/{$hash}", $args);
            if (!$status) {
                Logger::Audit("Error unsubscribing {$Sub->getEmail()} from $list_id");
            }
        }
        return $status;
    }


    /**
     * Subscribe an email address to one or more lists.
     *
     * @param   string  $email      Email address
     * @param   array   $args       Array of additional args to supply
     * @param   array   $lists      Array of list IDs
     * @return  boolean     True on success, False on error
     */
    //public function subscribe($email, $args=array(), $lists=array())
    public function subscribe($Sub, $lists=array())
    {
        if (empty($lists)) {
            $lists = array(Config::get('mc_def_list'));
        } elseif (!is_array($lists)) {
            $lists = array($lists);
        }
        $args = array(
            'email_address' => $Sub->getEmail(),
            'status' => self::_strStatus($Sub->getStatus()),
            'merge_fields' => $Sub->getAttributes(array(
                'firstname' => Config::get('mc_mrg_fname'),
                'lastname' => Config::get('mc_mrg_lname')
            ) ),
        );
        if (empty($args['merge_fields'])) { // should have names at least
            unset($args['merge_fields']);
        }
        $hash = $this->subscriberHash($args['email_address']);
        foreach ($lists as $list_id) {
            //$status = $this->post("/lists/$list_id/members/", $args);
            $status = $this->put("/lists/$list_id/members/$hash", $args);
        }
        return $status;
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
        $retval = array();
        if (empty($list_id)) {
           if (!empty(Config::get('def_list'))) {
               $list_id = Config::get('def_list');
           } else {
               return $retval;
           }
        }
        $hash = $this->subscriberHash($email);
        $tags = $this->get("/lists/$list_id/members/$hash/tags");
        if (isset($tags['tags']) && is_array($tags['tags'])) {
            foreach ($tags['tags'] as $tag) {
                $retval[] = $tag['name'];
            }
        }
        return $retval;
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
        // Use the default mailing list if none supplied.
        if (empty($lists)) {
            return true;
        } elseif (!is_array($lists)) {
            $lists = array($lists);
        }
        if (empty($tags) || !is_array($tags)) {
            return false;
        }
        $args = array(
            'tags' => $tags,
            'is_syncing' => true,
        );
        $hash = $this->subscriberHash($email);
        foreach ($lists as $list_id) {
            $status = $this->post("/lists/$list_id/members/$hash/tags", $args);
            var_dump($this);die;
            //$status = $this->put("/lists/$list_id/members/$hash", $args);
        }
        return $status;
    }


    /**
     * Get information about a specific member by email address.
     *
     * @param   string  $email      Email address
     * @return  array       Array of member information
     */
    public function getMemberInfo($Sub, $list_id='')
    {
        $retval = array();

        if (empty($list_id)) {
            $list_id = Config::get('mc_def_list');
        }
        if (empty($list_id)) {
            return false;
        }

        $hash = $this->subscriberHash($Sub->getEmail());
        $status = $this->get("/lists/{$list_id}/members/{$hash}");
        if ($status) {
            $data = $this->formatResponse($this->getLastResponse());
            $attributes = array();
            if (isset($data['merge_fields'])) {
                foreach ($data['merge_fields'] as $key=>$val) {
                    // All merge fields are configurable, first and last name
                    // are part of the plugin configuration.
                    switch ($key) {
                    case Config::get('mc_mrg_fname'):
                        $attributes['firstname'] = $val;
                        break;
                    case Config::get('mc_mrg_lname'):
                        $attributes['lastname'] = $val;
                        break;
                    default:
                        $attributes[$key] = $val;
                        break;
                    }
                }
            }
            $retval = array(
                'provider_uid' => $data['id'],
                'email_address' => $data['email_address'],
                'email_type' => $data['email_type'],
                'status' => self::_intStatus($data['status']),
                'attributes' => $attributes,
            );
        }
        return $retval;
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
        if (isset($params['list_id'])) {
            $list_id = $params['list_id'];
            unset($params['list_id']);
        } else {
            $list_id = Config::get('mc_def_list');
        }
        if (empty($list_id)) {
            return false;
        }
        if ($uid > 1) {
            $this->mergePlugins($uid);
        }
        foreach ($params as $key=>$val) {
            switch ($key) {
            case 'status':
                $params[$key] = $this->_strStatus($status);
                break;
            case 'attributes':
                foreach ($val as $k=>$v) {
                    switch ($k) {
                    case 'lastname':
                    case 'firstname':
                        if (!empty(Config::get('mc_mrg_' . $k))) {
                            $this->attributes[Config::get('mc_mrg_' . $k)] = $v;
                        }
                        break;
                    default:
                        $this->attributes[$k] = $v;
                        break;
                    }
                }
                break;
            }
            unset($params[$key]);
        }
        if (!empty($this->attributes)) {
            $params['merge_fields'] = $this->attributes;
        }
        $hash = $this->subscriberHash($email);
        //$response = $this->patch("/lists/$list_id/members/$hash", $params);
        $response = $this->put("/lists/$list_id/members/$hash", $params);
        return $response;
    }


    private function _strStatus($int)
    {
        switch ($int) {
        case Status::ACTIVE:
            return 'subscribed';
        case Status::PENDING:
            return 'pending';
        default:
            return 'unsubscribed';
        }
    }


    private function _intStatus($str)
    {
        switch ($str) {
        case 'subscribed':
            return Status::ACTIVE;
        case 'pending':
            return Status::PENDING;
        case 'unsubscribed':
        case 'cleaned':
        default:
            return Status::UNSUBSCRIBED;
        }
    }

}
