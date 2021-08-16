<?php
/**
 * Mailjet API for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2020 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.1.1
 * @since       v0.1.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\API\Mailjet;
use Mailer\Config;
use Mailer\Models\Subscriber;
use Mailer\Models\Status;
use Mailer\Models\ApiInfo;
use Mailer\Models\Campaign;


/**
 * Mailjet API v2
 * @see https//mailerjet.com
 */
class API extends \Mailer\API
{
    private $api_secret = '';
    private $api_key = '';

    protected $cfg_list_key = 'mj_def_list';

    /**
     * Create a new instance.
     *
     * @param   string $api_key Your MailChimp API key
     * @param   string $api_endpoint Optional custom API endpoint
     * @throws  \Exception
     */
    public function __construct()
    {
        $this->api_key = Config::get('mj_api_key');
        $this->api_secret = Config::get('mj_api_secret');
        $this->api_endpoint = 'https://api.mailjet.com/v3/REST';
        $this->last_response = array('headers' => null, 'body' => null);
        $this->setHeaders(array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->api_key . ':' . $this->api_secret),
        ));
    }


    /**
     * Get supported administrative features to show as menu options.
     * Possibilities are `campaigns`, `subscribers` and `queue`.
     * Third-party APIs only support subscriber management, the Internal API
     * supports all three.
     *
     * @return  array       Array of supported feature keys
     */
    public function getFeatures()
    {
        return array('campaigns', 'subscribers');
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
        $params = array(
            'Sort' => 'ID',
            'Limit' => 10,
            'Offset' => 0,
            'ContactsList' => Config::get('mj_def_list'),
        );
        if (isset($opts['limit'])) {
            $params['Limit'] = (int)$opts['limit'];
        }
        if (isset($opts['offset'])) {
            $params['Offset'] = (int)$opts['offset'];
        }
        if ($list_id === NULL) {
            $list_id = $this->list_id;
        }

        $retval = array();
        $status = $this->get('/contact', $params);
        if ($status) {
            $body = json_decode($this->getLastResponse()['body']);
            if (isset($body->Data) && is_array($body->Data)) {
                foreach ($body->Data as $idx=>$member) {
                    $info = new ApiInfo;
                    if (isset($member->IsOptInPending) && $member->IsOptInPending) {
                        $status = Status::PENDING;
                    } elseif (isset($member->IsExcludedFromCampaigns) && $member->IsExcludedFromCampaigns) {
                        $status = Status::UNSUBSCRIBED;
                    } elseif (isset($member->IsSpamComplaining) && $member->IsSpamComplaining) {
                        $status = Status::BLACKLIST;
                    } else {
                        $status = Status::ACTIVE;
                    }
                    $info['provider_uid'] = $member->ID;
                    $info['email_address'] = $member->Email;
                    $info['status'] = $status;
                    $retval[$info['provider_uid']] = $info;
                }
            }
        }
        // Now get the fields. Mailjet requires a separate API call for this.
        $status = $this->get('/contactdata', $params);
        if ($status) {
            $meta = $this->formatResponse($this->getLastResponse())['Data'];
            foreach ($meta as $idx=>$member) {
                $retval[$member['ID']]['attributes'] = self::_attrFromFields($member['Data']);
                $retval[$info['provider_uid']] = $info;
            }
        }
        return $retval;
    }


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
        if (empty($lists)) {
            $lists = array(Config::get('mj_def_list'));
        }
        $retval = true;
        $cl = array();
        foreach ($lists as $list_id) {
            $cl[] = array(
                'ListID' => $list_id,
                'Action' => 'remove',
            );
        }
        $args = array(
            'ContactsLists' => $cl,
        );
        $response = $this->post('contact/' . $Sub->getEmail(). '/managecontactslists', $args);
        if (!$response) {
            // set final return code
            $retval = false;
        }
        return $retval;
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

        $status = Status::SUB_SUCCESS;
        $fields = $Sub->getAttributes($this->getAttributeMap());
        $fields = array_change_key_case($fields, CASE_LOWER);
        $info = $this->getMemberInfo($Sub);
        if (empty($info)) {
            $args = array(
                'Name' => $Sub->getFullname(),
                'Email' => $Sub->getEmail(),
                'Action' => 'addnoforce',
            );
            $status = $this->post('contact/', $args);
        }
        if ($status == Status::SUB_SUCCESS) {
            /*switch ($Sub->getStatus()) {
            case Status::SUBSCRIBED:
                $isSubscribed = true;
                break;
            case Status::PENDING:
            default:
                $IsSubscribed = false;
                break;
            }*/
            $args = array(
                'ContactID' => $info['provider_uid'],
                'ContactAlt' => $Sub->getEmail(),
                'IsUnsubscribed' => false,
            );
            foreach ($lists as $list_id) {
                $args['ListID'] = $list_id;
                $stat1 = $this->post('listrecipient', $args);
                $data = $this->formatResponse($this->getLastResponse());
                if (!$stat1 && isset($data['ErrorMessage'])) {
                    $failed = true;
                    switch ($data['ErrorMessage']) {
                    case 'A duplicate ListRecipient already exists.':
                        $failed = false;    // duplicate OK.
                        break;
                    default:
                        // log the error but continue to try other groups
                        COM_errorLog("Mailjet error: " . $this->getLastResponse()['body']);
                        $status = Status::SUB_ERROR;
                        break;
                    }
                }
                $this->updateMember($Sub);
            }
        }
        /*if (!$status) {
            COM_errorLog("Mailjet error: " . $this->getLastResponse()['body']);
        }*/
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
        $params = array(
            'Data' => array(),
        );
        foreach ($Sub->getAttributes($this->getAttributeMap()) as $key=>$val) {
            $key = strtolower($key);
            $params['Data'][] = array(
                'Name' => $key,
                'Value' => $val,
            );
        }
        $email = urlencode($Sub->getEmail());
        $response = $this->put("contactdata/$email", $params);
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
        $status = $this->get("contact/{$email}");

        if ($status) {
            $data = $this->formatResponse($this->getLastResponse())['Data'][0];
            $retval = new ApiInfo;
            $retval['provider_uid'] = $data['ID'];
            $retval['email_address'] = $data['Email'];
            $retval['email_type'] = 'html';
            $retval['status'] = self::_statusFromResponse($data);
            $status = $this->get('contactdata/' . $data['ID']);
            if ($status) {
                $meta = $this->formatResponse($this->getLastResponse())['Data'][0]['Data'];
                $retval['attributes'] = self::_attrFromFields($meta);
            }
        }
        return $retval;
    }


    /**
     * Create the campaign.
     * - Create a template and get the template ID.
     * - Create the campaign using the template ID and save to the DB.
     *
     * @param   object  $Mlr    Mailer object
     * @param   string  $email  Optional email address
     * @param   string  $token  Optional token
     * @return  string      Campaign ID
     */
    public function createCampaign(Campaign $Mlr)
    {
        global $LANG_MLR, $LANG_LOCALE;

        /*$args = array(
            'Description' => $Mlr->getTitle(),
            'IsTextPartGenerationEnabled' => true,
            'Locale' => $LANG_LOCALE,
            'Name' => $Mlr->getTitle(),
            'Purposes' => array('marketing'),
        );
        $status = $this->post('template', $args);
        if (!$status) {
            COM_errorLog($this->getLastResponse()['body']);
            return $status;
        }
        $body = $this->formatResponse($this->getLastResponse())['Data'][0];
        $template_id = $body->ID;
        */

        // See if a campaign has already been created
        $campaign_id = $Mlr->getProviderCampaignId();
        if (!empty($campaign_id)) {
            // Verify that the campaign exists
            $status = $this->get('campaigndraft/' . $campaign_id);
            $body = $this->formatResponse($this->getLastResponse());
            if (!isset($body['Count']) || $body['Count'] != 1) {
                $campaign_id = '';
            }
        }

        if (empty($campaign_id)) {
            // No campaign created, or an invalid one is associated with the
            // mailing. Create a new campaign.
            $args = array(
                'ContactsListID' => $this->list_id,
                'ReplyEmail' => Config::senderEmail(),
                'SenderEmail' => Config::senderEmail(),
                'SenderName' => Config::senderName(),
                'Subject' => $Mlr->getTitle(),
                'Locale' => $LANG_LOCALE,
                'IsTextPartIncluded' => true,
                'Title' => $Mlr->getID(),
            );
            $status = $this->post('campaigndraft', $args);
            if (!$status) {
                COM_errorLog($this->getLastResponse()['body']);
                return $status;
            }
            $body = $this->formatResponse($this->getLastResponse())['Data'][0];
            if (isset($body['ID']) && !empty($body['ID'])) {
                $campaign_id = $body['ID'];
                $this->saveCampaignInfo($Mlr, $campaign_id);
            }
        }

        // At this point there should be a valid campaign_id
        if (!empty($campaign_id)) {
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
                'Html-part' => $html,
                'Text-part' => $text,
            );
            $status = $this->post('campaigndraft/' . $campaign_id . '/detailcontent', $args);
            if (!$status) {
                COM_errorLog($this->getLastResponse()['body']);
                return $status;
            }
        }
        return $campaign_id;
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
        $status = $this->post('campaigndraft/' . $Mlr->getProviderCampaignId(). '/send');
        if (!$status) {
            COM_errorLog($this->getLastResponse()['body']);
        }
        return $status;
    }


    /**
     * Send a test email. Not supported by Mailjet.
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
        return true;    // Mailjet does not delete campaigns
    }


    /**
     * Convert an internal integer status value to a Mailjet status string.
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
     * Convert a Mailjet status string to a Status model integer.
     *
     * @param   string  $str    Mailchimp status value
     * @return  integer     Plugin status value from the Status model.
     */
    private static function _statusFromResponse($data)
    {
        if (
            isset($data['IsExcludedFromCampaigns']) &&
            $data['IsExcludedFromCampaigns']
        ) {
            return Status::UNSUBSCRIBED;
        } elseif (
            isset($data['IsOptinPending']) &&
            $data['IsOptinPending']
        ) {
            return Status::PENDING;
        } else {
            return Status::ACTIVE;
        }
    }


    /**
     * Get the attributes from Mailjet field arrays.
     *
     * @param   array   $fields     Field array returned from Mailjet
     * @return  array       Array of attributes
     */
    private static function _attrFromFields($fields)
    {
        $retval = array();
        $attributes = Subscriber::getPluginAttributes();
        foreach ($fields as $attr) {
            switch ($attr['Name']) {
            case Config::get('mj_mrg_lname'):
                $retval['LASTNAME'] = $attr['Value'];
                break;
            case Config::get('mj_mrg_fname'):
                $retval['FIRSTNAME'] = $attr['Value'];
                break;
            default:
                $name = strtoupper($attr['Name']);
                if (array_key_exists($name, $attributes)) {
                    $retval[$name] = $attr['Value'];
                }
                break;
            }
        }
        return $retval;
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
     * Get the mapping of internal attribute names to Mailchimp merge fields.
     *
     * @return  array       Array of internal=>mailchimp pairs
     */
    public function getAttributeMap()
    {
        return array(
            'FIRSTNAME' => Config::get('mj_mrg_fname'),
            'LASTNAME' => Config::get('mj_mrg_lname')
        );
    }

}
