<?php
/**
 * Mailchimp API provider.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\API\Mailchimp;
use Mailer\Config;
use Mailer\Models\Status;
use Mailer\Models\API\Contact;
use Mailer\Models\API\ContactList;
use Mailer\Models\Campaign;
use Mailer\Models\Subscriber;
use Mailer\Logger;
use glFusion\Log\Log;


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

    /** Subscriber Hash (provider's user ID).
     * @var string */
    private $hash = '';

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
        $this->supports_testing = true;
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
     * Get the mapping of internal attribute names to Mailchimp merge fields.
     *
     * @return  array       Array of internal=>mailchimp pairs
     */
    public function getAttributeMap()
    {
        return array(
            'FIRSTNAME' => Config::get('mc_mrg_fname'),
            'LASTNAME' => Config::get('mc_mrg_lname')
        );
    }


    /**
     * Get a list of members subscribed to a given list.
     *
     * @param   string  $list_id    Mailing List ID
     * @param   array   $opts       Additional options to check
     * @return  array       Array of data
     */
    public function listMembers(string $list_id=NULL, array $opts=array()) : array
    {
        if ($list_id == NULL) {
            $list_id = Config::get('mc_def_list');
        }
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
            //case 'since_timestamp_opt':
            //case 'before_last_changed':
                $dt = new \Date($val);
                $params[$key] = $dt->format(\DateTime::ATOM);
                break;
            case 'count':
                $val = min($val, 1000);
                break;
            }
            $params[$key] = urlencode($val);
        }
        $url = http_build_query($params);
        $status = $this->get("lists/$list_id/members?$url");
        $retval = array();
        if ($status) {
            $body = json_decode($this->getLastResponse()['body']);
            if (isset($body->members) && is_array($body->members)) {
                foreach ($body->members as $member) {
                    $info = new Contact;
                    $info['provider_uid'] = $member->id;
                    $info['email_address'] = $member->email_address;
                    $info['status'] = self::_intStatus($member->status);
                    $retval[] = $info;
               }
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
    public function lists(int $offset=0, int $count=25, array $fields=array()) : array
    {
        $params = array(
            'fields' => $fields,
            'offset' => $offset,
            'count' => $count,
        );
        $status = $this->get('lists', $params);
        if ($status) {
            $body = json_decode($this->getLastResponse()['body'], true);
            if (isset($body['lists']) && is_array($body['lists'])) {
                foreach ($body['lists'] as $list) {
                    $retval[$list['id']] = new ContactList(array(
                        'id' => $list['id'],
                        'name' => $list['name'],
                        'members' => $list['stats']['member_count'],
                    ) );
                }
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
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists  Array of list IDs
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
            'merge_fields' => $Sub->getAttributes($this->getAttributeMap()),
        );
        if (empty($args['merge_fields'])) {
            unset($args['merge_fields']);
        }
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
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists      Array of list IDs
     * @return  boolean     True on success, False on error
     */
    public function subscribe(Subscriber $Sub, array $lists=array()) : bool
    {
        if (empty($lists)) {
            $lists = array(Config::get('mc_def_list'));
        } elseif (!is_array($lists)) {
            $lists = array($lists);
        }
        $args = array(
            'email_address' => $Sub->getEmail(),
            'status' => self::_strStatus($Sub->getStatus()),
            'merge_fields' => $Sub->getAttributes($this->getAttributeMap()),
        );
        if (empty($args['merge_fields'])) { // should have names at least
            unset($args['merge_fields']);
        }
        $hash = $this->subscriberHash($args['email_address']);
        foreach ($lists as $list_id) {
            //$status = $this->post("/lists/$list_id/members/", $args);
            $status = $this->put("/lists/$list_id/members/$hash", $args);
        }
        if ($status) {
            return Status::SUB_SUCCESS;
        } else {
            return Status::SUB_ERROR;
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

        $retval = new Contact;
        $hash = $this->subscriberHash($Sub->getEmail());
        $status = $this->get("/lists/{$list_id}/members/{$hash}");
        if ($status) {
            $data = $this->formatResponse($this->getLastResponse());
            $retval['provider_uid'] = $data['id'];
            $retval['email_address'] = $data['email_address'];
            $retval['status'] = self::_intStatus($data['status']);
            $attributes = array();
            if (isset($data['merge_fields'])) {
                foreach ($data['merge_fields'] as $key=>$val) {
                    // All merge fields are configurable, first and last name
                    // are part of the plugin configuration.
                    switch ($key) {
                    case Config::get('mc_mrg_fname'):
                        $retval->setAttribute('FIRSTNAME', $val);
                        break;
                    case Config::get('mc_mrg_lname'):
                        $retval->setAttribute('LASTNAME', $val);
                        break;
                    default:
                        $retval->setAttribute($key, $val);
                        break;
                    }
                }
            }
        }
        return $retval;
    }


    /**
     * Update the member information for a specific email and list.
     *
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists  Array of list IDs
     * @return  boolean     True on success, False on failure
     */
    public function updateMember($Sub, $lists=array())
    {
        if (empty($lists)) {
            $lists = array(Config::get('mc_def_list'));
        } elseif (!is_array($lists)) {
            $lists = array($lists);
        }
        if (empty($lists)) {
            return false;
        }
        $args = array(
            'email_address' => $Sub->getEmail(),
            'status' => self::_strStatus($Sub->getStatus()),
            'merge_fields' => $Sub->getAttributes($this->getAttributeMap()),
        );
        if (empty($args['merge_fields'])) {
            unset($args['merge_fields']);
        }
        $hash = $this->subscriberHash($Sub->getOldEmail());
        foreach ($lists as $list_id) {
            //$response = $this->patch("/lists/$list_id/members/$hash", $args);
            $response = $this->put("/lists/{$list_id}/members/$hash", $args);
        }
        return $response;
    }


    /**
     * Subscribe new or update an existing contact.
     *
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists  Array of list IDs
     * @return  integer     Response from API call
     */
    public function subscribeOrUpdate($Sub, $lists=array())
    {
        if ($Sub->getStatus() == Status::BLACKLIST) {
            $this->updateMember($Sub);  // update status only
        } else {
            $this->subscribe($Sub);
        }
    }


    /**
     * Convert an internal integer status value to a Mailchimp status string.
     *
     * @param   integer $int    Plugin status value from the Status model.
     * @return  string      Corresponding status used by Mailchimp
     */
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


    /**
     * Convert a Mailchimp status string to a Status model integer.
     *
     * @param   string  $str    Mailchimp status value
     * @return  integer     Plugin status value from the Status model.
     */
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


    /**
     * Create a campaign.
     *
     * @param   object  $Mlr    Campaign object
     * @return  string      Campaign ID
     */
    public function createCampaign(Campaign $Mlr)
    {
        global $_CONF;

        $content = $Mlr->getContent();

        // Convert image URLs to fuly-qualified
        \LGLib\SmartResizer::create()
            ->withLightbox(false)
            ->withFullUrl(true)
            ->convert($content);

        $args = array(
            'type' => 'regular',
            'settings' => array(
                'subject_line' => $Mlr->getTitle(),
                'title' => $Mlr->getTitle(),
                'inline_css' => true,
                'from_name' => Config::senderName(),
                'reply_to' => Config::senderEmail(),
            ),
            'recipients' => array(
                'list_id' => Config::get('mc_def_list'),
            ),
            'content_type' => 'template',
        );
        $status = $this->post('/campaigns', $args);
        if (!$status) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $this->getLastResponse()['body']);
        }
        $body = json_decode($this->getLastResponse()['body']);
        if (isset($body->id)) {
            $args = array(
                'html' => $content,
            );
            $status = $this->put('/campaigns/' . $body->id . '/content', $args);
            if (!$status) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $this->getLastResponse()['body']);
                return NULL;
            } else {
                $this->saveCampaignInfo($Mlr, $body->id);
                return $body->id;
            }
        } else {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . "Error getting last response from Mailchimp campaign creation");
            return NULL;
        }
    }


    /**
     * Send a previously-created campaign.
     *
     * @param   string  $Mlr        Campaign Mailer
     * @param   array   $emails     Email addresses override
     * @param   string  $token      Campaign token
     * @return  boolean     Status from sending
     */
    protected function _sendCampaign(Campaign $Mlr, ?array $emails, ?string $token=NULL)
    {
        $status = $this->post('/campaigns/' . $Mlr->getProviderCampaignId() . '/actions/send');
        return $status;
    }


    /**
     * Send a test email.
     * This uses the current user's email address.
     *
     * @param   string  $camp_id    Campaign ID
     * @return  boolean     True on success, False on error
     */
    protected function _sendTest(string $camp_id) : bool
    {
        global $_USER;

        $args = array(
            'test_emails' => array($_USER['email']),
            'send_type' => 'html',
        );
        $status = $this->post('campaigns/' . $camp_id . '/actions/test', $args);
        if (!$status) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $this->getLastResponse()['body']);
        }
        return $status;
    }


    /**
     * Check if this API is configured.
     *
     * return   boolean     True if configured, False if not
     */
    public function isConfigured() : bool
    {
        if (empty($this->api_key) || strpos($this->api_key, '-') === false) {
            return false;
        }
        return true;
    }

}
