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
use Mailer\Models\Campaign;
use Mailer\Models\API\Contact;
use Mailer\Models\API\ContactList;
use glFusion\Log\Log;


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
        $this->supports_testing = true;
    }


    /**
     * Get a list of members subscribed to a given list.
     *
     * @param   string  $list_id    Mailing List ID
     * @param   array   $opts       Additional options to consider
     * @return  array       Array of data
     */
    public function listMembers(string $list_id=NULL, array $opts=array()) : array
    {
        if ($list_id == NULL) {
            $list_id = Config::get('sb_def_list');
        }
        $params = array(
            'limit' => 10,
            'offset' => 0,
        );
        foreach ($opts as $key=>$val) {
            switch ($key) {
            case 'since_last_changed':
                $dt = new \Date($val);
                $params['modifiedSince'] = $dt->format(\DateTime::ATOM);
                break;
            case 'count':
                $params['limit'] = min($val, 1000);
                break;
            case 'offset':
                $params[$key] = (int)$val;
                break;
            }
        }
        $retval = array();
        $status = $this->get("contacts/lists/{$list_id}/contacts", $params);
        if ($status) {
            $body = json_decode($this->getLastResponse()['body']);
            if (isset($body->contacts) && is_array($body->contacts)) {
                foreach ($body->contacts as $member) {
                    $info = new Contact;
                    $info['provider_uid'] = $member->id;
                    $info['email_address'] = $member->email;
                    if ($member->emailBlacklisted) {
                        $info['status'] = Status::BLACKLIST;
                    } else {
                        $info['status'] = Status::ACTIVE;
                    }
                    $retval[] = $info;
                }
            }
        }
        return $retval;
    }


    /**
     * Get an array of mailing lists visible to this API key.
     *
     * @param   integer $offset     First record offset, for larget datasets
     * @param   integer $count      Number of items to return
     * @param   array   $fields     Fields to retrieve (not used here)
     * @return  array       Array of list data
     */
    public function lists(int $offset=0, int $count=25, array $fields=array()) : array
    {
        $retval = array();
        $params = array(
            'offset' => $offset,
            'limit' => $count,
        );
        $status = $this->get('contacts/lists', $params);
        if ($status) {
            $body = json_decode($this->getLastResponse()['body'], true);
            if (isset($body['lists']) && is_array($body['lists'])) {
                foreach ($body['lists'] as $list) {
                    $retval[$list['id']] = new ContactList(array(
                        'id' => $list['id'],
                        'name' => $list['name'],
                        'members' => $list['uniqueSubscribers'],
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
     * @param   array   $lists      Array of list IDs
     * @return  boolean     True on success, False on error
     */
    public function unsubscribe($Sub, $lists=array())
    {
        $status = false;
        if (empty($lists) && !empty(Config::get('sb_def_list'))) {
            $lists = array(Config::get('sb_def_list'));
        }
        $args = array(
            'emails' => array($Sub->getEmail()),
        );
        foreach ($lists as $list) {
            $this->post("/contacts/lists/{$list}/contacts/remove", $args);
        }
        $Sub->updateStatus(Status::UNSUBSCRIBED);
        return true;
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
        global $_CONF;

        if (empty($lists)) {
            $lists = array($this->list_id);
        } elseif (!is_array($lists)) {
            $lists = array($lists);
        }
        if (empty($lists)) {
            return Status::SUB_INVALID;
        }
        $lists = array_map('intval', $lists);

        $args = array(
            'email' => $Sub->getEmail(),
            'redirectionUrl' => $_CONF['site_url'] . '/index.php?plugin=mailer&msg=2',
            'templateId' => Config::get('sb_dbo_tpl'),
            'attributes' => $Sub->getAttributes(),
        );
        if ($Sub->getStatus() == Status::ACTIVE) {
            // Not requiring double-opt-in
            $path = 'contacts';
            $args['listIds'] = $lists;
            $args['updateEnabled'] = true;
        } else {
            $path = 'contacts/doubleOptinConfirmation';
            $args['includeListIds'] = $lists;
        }
        $status = $this->post($path, $args);
        if (!$status) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $this->getLastResponse()['body']);
        } else {
            // Now add to the list, existing contacts may not be added
            $args = array(
                'emails' => array($Sub->getEmail()),
            );
            foreach ($lists as $list_id) {
                $status = $this->post('contacts/lists/' . $list_id . '/contacts/add', $args);
                if (!$status) {
                    break;
                }
            }
        }
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
        $attribs = $Sub->getAttributes();
        if ($Sub->getEmail() != $Sub->getOldEmail()) {
            $attribs['EMAIL'] = $Sub->getEmail();
        }
        $params = array(
            'status' => $Sub->getStatus(),
            'attributes' => $attribs,
        );
        $email = urlencode($Sub->getOldEmail());
        $response = $this->put("contacts/$email", $params);
        return $response;
    }


    /**
     * Subscribe new or update existing contact.
     * Sendinblue uses the same function for both.
     *
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists  Array of list IDs
     * @return  integer     Response from API call
     */
    public function subscribeOrUpdate($Sub, $lists=array())
    {
        return $this->subscribe($Sub);
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
            $retval = new Contact;
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


    /**
     * Create the campaign.
     *
     * @param   object  $Mlr    Campaign object
     * @param   string  $email  Optional email address
     * @param   string  $token  Optional token
     * @return  string      Campaign ID
     */
    public function createCampaign(Campaign $Mlr)
    {
        $content = $Mlr->getContent() . '<div style="clear:both;"></div>';

        // Convert image URLs to fully-qualified.
        \LGLib\SmartResizer::create()
            ->withLightbox(false)
            ->withFullUrl(true)
            ->convert($content);

        $args = array(
            'sender' => array(
                'name' => Config::senderName(),
                'email' => Config::senderEmail(),
            ),
            'name'  => $Mlr->getTitle(),
            'htmlContent' => $content,
            'subject' => $Mlr->getTitle(),
            'recipients' => array('listIds' => array((int)Config::get('sb_def_list'))),
            'inlineImageActivation' => true,
        );
        var_dump($args);
        // Create the campaign
        $status = $this->post('emailCampaigns', $args);
        if ($status) {
            $body = json_decode($this->getLastResponse()['body']);
            if (isset($body->id)) {
                $this->saveCampaignInfo($Mlr, $body->id);
                return $body->id;
            }
        } else {
            var_dump($this->getLastResponse());die;
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
    protected function _sendCampaign(Campaign $Mlr, ?array $emails, ?string $token=NULL)
    {
        $status = $this->post('emailCampaigns/' . $Mlr->getProviderCampaignId(). '/sendNow');
        return $status;
    }


    /**
     * Send a test email.
     * This requires a preconfigured test list at Sendinblue.
     *
     * @param   string  $camp_id    Campaign ID
     * @return  boolean     True on success, False on error
     */
    protected function _sendTest(string $camp_id) : bool
    {
        $status = $this->post('emailCampaigns/' . $camp_id . '/sendTest');
        if (!$status) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $this->getLastResponse()['body']);
        }
        return $status;
    }


    /**
     * Delete a campaign.
     *
     * @param   object  $Mlr    Campaign object
     * @return  boolean     Result of deletion request
     */
    public function deleteCampaign(Campaign $Mlr) : bool
    {
        $camp_id = $Mlr->getProviderCampaignId();
        if (!empty($camp_id)) {
            return $this->delete('emailCampaigns/' . $camp_id);
        } else {
            return false;
        }
    }


    /**
     * Check if this API is configured.
     *
     * return   boolean     True if configured, False if not
     */
    public function isConfigured() : bool
    {
        return !empty($this->api_key);
    }

}
