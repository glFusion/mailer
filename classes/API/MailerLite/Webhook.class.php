<?php
/**
 * This file contains the MailerLite webhook handler
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner
 * @package     mailer
 * @version     v0.2.0
 * @since       v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\API\MailerLite;
use Mailer\Models\Subscriber;
use Mailer\Models\Status;
use Mailer\Models\Txn;
use Mailer\Logger;
use Mailer\Config;


// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * Class to provide webhook for the Stripe payment processor.
 * @package shop
 */
class Webhook extends \Mailer\Webhook
{
    /** Unmodified payload for signature checking.
     * @var string */
    private $blob = '';

    /** Flag to indicate a testing webhook sent manually.
     * @var boolean */
    private $testing = 0;


    /**
     * Constructor.
     *
     * @param   array   $A  Payload provided by Stripe
     */
    function __construct()
    {
        if (!empty($_POST) && isset($_POST['vars'])) {
            $this->blob = base64_decode($_POST['vars']);
            $this->testing = 1;     // override _verify()
        } else {
            $this->blob = @file_get_contents('php://input');
        }
        $this->payload = json_decode($this->blob, true);
        $this->provider = 'MailerLite';
    }


    /**
     * Perform the necessary actions based on the webhook.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
        if (!$this->_verify()) {
            return;
        }

        foreach ($this->payload['events'] as $event) {
            // The webhook ID is the saved ID of the webhook and is not unique.
            // This webhook handler does not actually avoid duplicate events.
            $Txn = new Txn;
            $Txn['txn_id'] = uniqid();      // MailerLite doesn't provide a txn_id.
            $Txn['txn_date'] = isset($event['timestamp']) ? $event['timestamp'] : time();
            $Txn['data'] = $this->payload;
            $Txn['type'] = isset($event['type']) ? $event['type'] : 'unknown';
            if ($this->isUnique($Txn)) {
                $this->_handleEvent($event);
            }
        }
        return true;
    }


    /**
     * Handle the actual webhook event.
     *
     * @param   array   $event      Event data from webhook
     * @return  boolean     True on success, False on error
     */
    private function _handleEvent(array $event) : bool
    {
        $retval = false;
        $data = isset($event['data']) ? $event['data'] : NULL;
        if (!is_array($data)) {
            return false;
        }

        $parts = explode('.', $event['type']);
        switch($parts[0]) {
        case 'subscriber':
            $subscriber = isset($data['subscriber']) ? $data['subscriber'] : array();
            if (!empty($subscriber)) {
                $sub_id = isset($subscriber['id']) ? $subscriber['id'] : '';
                $email = isset($subscriber['email']) ? $subscriber['email'] : '';
                $type = isset($subscriber['type']) ? $subscriber['type'] : '';
                $Sub = Subscriber::getByEmail($email);
                switch ($parts[1]) {
                case 'added_through_webform':
                case 'create':    // via api
                case 'update':
                    if ($type == 'active') {
                        $status = Status::ACTIVE;
                    } elseif ($type == 'unsubscribed') {
                        $status = Status::UNSUBSCRIBED;
                    } else {
                        $status = Status::PENDING;
                    }
                    $Sub->withStatus($status);
                    if ($Sub->getID() > 0) {
                        // Update the existing record
                        if ($parts[1] == 'update') {
                            $attrs = $Sub->getAttributes();
                            $API = API::getInstance($this->provider);
                            $map_arr = $API->getAttributeMap();
                            foreach ($subscriber['fields'] as $idx=>$fld) {
                                $attr_key = array_search($fld['key'], $map_arr);
                                if (!empty($attr_key)) {
                                    $Sub->setAttribute($attr_key, $fld['value']);
                                } elseif (isset($attrs[strtoupper($fld['key'])])) {
                                    $Sub->setAttribute(strtoupper($fld['key']), $fld['value']);
                                }
                            }
                            $Sub->updateUser();
                        }
                    }
                    $Sub->Save();
                    $retval = true;
                    break;

                case 'add_to_group':
                    $group = isset($data['group']) ? $data['group'] : array();
                    $grp_id = isset($group['id']) ? $group['id'] : '';
                    if ($grp_id == Config::get('ml_def_list')) {
                        $Sub->withStatus(Status::ACTIVE)->Save();
                    }
                    break;

                case 'remove_from_group':
                    $group = isset($data['group']) ? $data['group'] : array();
                    $grp_id = isset($group['id']) ? $group['id'] : '';
                    if ($grp_id != Config::get('ml_def_list')) {
                        break;
                    }
                case 'bounced':
                case 'unsubscribe':
                    if ($Sub->getID() > 0) {
                        $Sub->updateStatus(Status::UNSUBSCRIBED);
                        $retval = true;
                    }
                    break;
                }
            }
            break;

        default:
            Logger::Debug("Unhandled MailerLite event: " . var_export($this->payload,true));
            break;
        }
        return $retval;
    }


    /**
     * Verify the webhook's authenticity.
     *
     * @return  boolean     True if the signature is valid, False if not.
     */
    private function _verify()
    {
        if ($this->testing) {
            return true;
        }

        $apiKey = API::getInstance($this->provider)->getApiKey();
        $calculated = base64_encode(hash_hmac('sha256', $this->blob, $apiKey, true));
        $provided = $_SERVER['HTTP_X_MAILERLITE_SIGNATURE'];
        return $provided == $calculated;
    }

}

