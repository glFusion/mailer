<?php
/**
 * MailerLite API for the Mailer plugin.
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
namespace Mailer\API\MailerLite;
use Mailer\Config;
use Mailer\Models\Subscriber;
use Mailer\Models\Status;
use Mailer\Models\ApiInfo;
use Mailer\Models\Campaign;


/**
 * MailerLite API v2
 * @see http://developers.mailerlite.com.
 */
class API extends \Mailer\API
{
    private $api_key = '';

    protected $cfg_list_key = 'ml_def_list';

    /**
     * Create a new instance.
     *
     * @param   string $api_key Your MailChimp API key
     * @param   string $api_endpoint Optional custom API endpoint
     * @throws  \Exception
     */
    public function __construct()
    {
        $this->api_key = Config::get('ml_api_key');
        $this->api_endpoint = 'https://api.mailerlite.com/api/v2';
        $this->last_response = array('headers' => null, 'body' => null);
        $this->setHeaders(array(
            'Accept: application/json',
            'Content-Type: application/json',
            'X-MailerLite-ApiKey: ' . $this->api_key,
        ));
    }


    /**
     * Get a list of members subscribed to a given list.
     *
     * @param   string  $list_id    Mailing List ID
     * @param   array   $opts       Array of limit, offset, etc. options
     * @return  array       Array of ApiInfo objects
     */
    public function listMembers($list_id=NULL, $opts=array())
    {
        $retval = array();
        $params = array(
            'limit' => 10,
            'offset' => 0,
        );
        if (isset($opts['count'])) {
            $params['limit'] = (int)$opts['count'];
        }
        if (isset($opts['offset'])) {
            $params['offset'] = (int)$opts['offset'];
        }
        if ($list_id === NULL) {
            $list_id = $this->list_id;
        }

        $status = $this->get('groups/'. $list_id . '/subscribers/active', $params);
        if ($status) {
            $body = json_decode($this->getLastResponse()['body']);
            if (is_array($body)) {
                foreach ($body as $idx=>$member) {
                    $info = new ApiInfo;
                    $info['provider_uid'] = $member->id;
                    $info['email_address'] = $member->email;
                    $info['status'] = self::_intStatus($member->type);
                    $info['attributes'] = self::_attrFromFields($member->fields);
                    $retval[] = $info;
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
    /*public function listsForEmail($email, $fields=array(), $offset=0, $count=25)
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
    }*/


    /**
     * Unsubscribe an email address from one or more lists.
     *
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists      Array of list IDs
     * @return  boolean     True on success, False on error
     */
    public function unsubscribe($Sub, $lists=array())
    {
        $Sub->updateStatus(Status::UNSUBSCRIBED);
        return $this->updateMember($Sub);
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

        // Field names must be lower case
        $fields = $Sub->getAttributes($this->getAttributeMap());
        $args = array(
            'email' => $Sub->getEmail(),
            'resubscribe' => true,
            'type' => self::_strStatus($Sub->getStatus()),
            'fields' => $fields,
        );
        $status = Status::SUB_SUCCESS;
        foreach ($lists as $list_id) {
            $stat1 = $this->post('groups/' . $list_id . '/subscribers', $args);
            if (!$stat1) {
                // log the error but continue to try other groups
                COM_errorLog("MailerLite error: " . $this->getLastResponse()['body']);
                $status = Status::SUB_ERROR;
            }
        }
        return $status;
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
        $old_email = $Sub->getOldEmail();
        $new_email = $Sub->getEmail();

        $attribs = $Sub->getAttributes();
        $ml_flds = $this->getAttributeMap();
        $params = new \stdClass;
        $params->fields = new \stdClass;
        foreach ($attribs as $key=>$val) {
            switch($key) {
            case 'FIRSTNAME':
                $key = 'name';
                break;
            case 'LASTNAME':
                $key = 'last_name';
                break;
            default:
                $key = strtolower($key);
                break;
            }
            $params->fields->$key = $val;
        }
        //$params->fields->email = $Sub->getEmail();
        $old_email = urlencode($old_email);
        if ($new_email != $old_email) {
            // unsubscribe the original subscriber, then subscribe the new.
            $params->type = 'unsubscribed';
            $response = $this->put("subscribers/$old_email", $params);
            $params->type = 'active';
            $params->email = $new_email;
            $response = $this->post("subscribers", $params);
        } else {
            // Just update the existing subscriber
            $new_email = urlencode($new_email);
            $params->type = self::_strStatus($Sub->getStatus());
            $response = $this->put("subscribers/$new_email", $params);
        }
        return $response;
    }


    /**
     * Subscribe new or update existing contact.
     *
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists  Array of list IDs
     * @return  integer     Response from API call
     */
    public function subscribeOrUpdate($Sub, $lists=array())
    {
        return $this->subscribe($Sub, $lists);
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
        $status = $this->get("subscribers/{$email}");
        if ($status) {
            $data = $this->formatResponse($this->getLastResponse());
            $fields = LGLIB_getVar($data, 'fields', 'array');
            $retval = new ApiInfo;
            $retval['provider_uid'] = $data['id'];
            $retval['email_address'] = $data['email'];
            $retval['email_type'] = 'html';
            $retval['status'] = self::_intStatus(LGLIB_getVar($data, 'type'));
            $retval['attributes'] = self::_attrFromFields($fields);
        }
        return $retval;
    }


    /**
     * Create the campaign.
     *
     * @param   object  $Mlr    Mailer object
     * @param   string  $email  Optional email address
     * @param   string  $token  Optional token
     * @return  string      Campaign ID
     */
    public function createCampaign(Campaign $Mlr)
    {
        global $LANG_MLR;

        $args = array(
            'type' => 'regular',
            'groups' => array($this->list_id),
            'from' => Config::senderEmail(),
            'from_name' => Config::senderName(),
            'subject' => $Mlr->getTitle(),
        );
        // Create the campaign
        $status = $this->post('campaigns', $args);
        if ($status) {
            $body = json_decode($this->getLastResponse()['body']);
            if (isset($body->id)) {
                $this->saveCampaignInfo($Mlr, $body->id);
                $content = $Mlr->getContent() . '<div style="clear:both;"></div>';
                // Convert image URLs to fully-qualified.
                \LGLib\SmartResizer::create()
                    ->withLightbox(false)
                    ->withFullUrl(true)
                    ->convert($content);
                $html = $content . '<p><a href="{$unsubscribe}">' .
                    $LANG_MLR['unsubscribe'] . '</a>';
                $text = strip_tags($content) . "\n\n" .
                    '{$unsubscribe} - ' . $LANG_MLR['unsubscribe'] . "\n" .
                    '{$url} - URL to your HTML newsletter';

                $args = array(
                    'html' => $html,
                    'plain' => $text,
                );
                $status = $this->put('campaigns/' . $body->id . '/content', $args);
                return $body->id;
            }
        }
        return NULL;
    }


    /**
     * Send the campaign.
     *
     * @param   string  $Mlr        Campaign Mailer
     * @param   array   $emails     Email addresses (optional)
     * @param   string  $token      Token (not used)
     */
    public function sendCampaign($Mlr, $emails=array(), $token='')
    {
        $status = $this->post('campaigns/' . $Mlr->getProviderCampaignId(). '/actions/send');
        if (!$status) {
            COM_errorLog($this->getLastResponse()['body']);
        }
        return $status;
    }


    /**
     * Send a test email. Not supported by MailerLite.
     *
     * @param   string  $camp_id    Campaign ID
     * @return  boolean     True on success, False on error
     */
    public function sendTest($camp_id)
    {
        return false;
    }


    /**
     * Delete a campaign.
     *
     * @param   object  $Mlr    Campaign object
     * @return  boolean     Result of deletion request
     */
    public function deleteCampaign(Campaign $Mlr)
    {
        $camp_id = $Mlr->getProviderCampaignId();
        if (!empty($camp_id)) {
            return $this->delete('campaigns/' . $camp_id);
        } else {
            return false;
        }
    }


    /**
     * Convert an internal integer status value to a MailerLite status string.
     *
     * @param   integer $int    Plugin status value from the Status model.
     * @return  string      Corresponding status used by Mailchimp
     */
    private static function _strStatus($int)
    {
        switch ($int) {
        case Status::ACTIVE:
            return 'active';
        case Status::PENDING:
            return 'unconfirmed';
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
    private static function _intStatus($str)
    {
        switch ($str) {
        case 'active':
            return Status::ACTIVE;
        case 'unconfirmed':
            return Status::PENDING;
        case 'unsubscribed':
        default:
            return Status::UNSUBSCRIBED;
        }
    }


    /**
     * Get the attributes from MailerLite field arrays.
     *
     * @param   array   $fields     Field array returned from MailerLite
     * @return  array       Array of attributes
     */
    private static function _attrFromFields($fields)
    {
        $retval = array();
        $attributes = Subscriber::getPluginAttributes();
        foreach ($fields as $idx=>$info) {
            switch ($info->key) {
            case 'last_name':
                $retval['LASTNAME'] = $info->value;
                break;
            case 'name':
                $retval['FIRSTNAME'] = $info->value;
                break;
            default:
                if (array_key_exists(strtoupper($info->key), $attributes)) {
                    $retval[strtoupper($info->key)] = $info->value;
                }
                break;
            }
        }
        return $retval;
    }


    /**
     * Get the mapping of internal attribute names to Mailchimp merge fields.
     *
     * @return  array       Array of internal=>mailchimp pairs
     */
    public function getAttributeMap()
    {
        return array(
            'FIRSTNAME' => 'name',
            'LASTNAME' => 'last_name',
        );
    }


    /**
     * Create the webhooks.
     * Webhooks can only be created via API for MailerLite.
     */
    public function createWebhooks()
    {
        $events = array(
            'subscriber.create',
            'subscriber.update',
            'subscriber.unsubscribe',
            'subscriber.added_through_webform',
            'subscriber.complaint',
            'campaign.sent',
        );
        $args = array(
            'url' => Config::get('url') . '/hook.php?p=MailerLite',
        );
        foreach ($events as $event) {
            $args['event'] = $event;
            $this->post('webhooks', $args);
        }
    }


    /**
     * Delete all webhooks.
     */
    public function deleteWebhooks()
    {
        $status = $this->get('webhooks');
        if ($status) {
            $body = json_decode($this->getLastResponse()['body']);
            if (isset($body->webhooks) && is_array($body->webhooks)) {
                foreach ($body->webhooks as $hook) {
                    $this->delete('webhooks/' . $hook->id);
                }
            }
        }
    }


    /**
     * Get the API key. Needed for webhook validation.
     *
     * @return  string      API key
     */
    public function getApiKey()
    {
        return $this->api_key;
    }


    /**
     * Show links in the admin menu to add and delete webhooks.
     *
     * @return  string      HTML for additional header info
     */
    public function getMenuHelp()
    {
        $retval = '<a class="uk-button uk-button-success" href="' .
            Config::get('admin_url') . '/index.php?api_action=addwebhooks">' .
            'Add Webhooks</a>&nbsp;&nbsp;';
        $retval .= '<a class="uk-button uk-button-danger" href="' .
            Config::get('admin_url') . '/index.php?api_action=delwebhooks">' .
            'Delete Webhooks</a>';
        return '<p>' . $retval . '</p>';
    }


    /**
     * Handler for actions called via the admin menu.
     */
    public function handleActions($opts)
    {
        $action = $opts['get']['api_action'];
        if ($action == 'addwebhooks') {
            $this->createWebhooks();
        } elseif ($action == 'delwebhooks') {
            $this->deleteWebhooks();
        }
    }

}
