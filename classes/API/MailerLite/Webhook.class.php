<?php
/**
 * This file contains the MailerLite webhook handler
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner
 * @package     mailer
 * @version     v0.0.4
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

        $isuniq = NULL;
        foreach ($this->payload['events'] as $event) {
            // The webhook ID is the saved ID of the webhook and is not unique.
            // This webhook handler does not actually avoid duplicate events.
            if ($isuniq === NULL) {
                $Txn = new Txn;
                $Txn['txn_id'] = LGLIB_getVar($event, 'webhook_id') . '.' . uniqid();
                $Txn['txn_date'] = LGLIB_getVar($event, 'timestamp');
                $Txn['data'] = $this->payload;
                $Txn['type'] = LGLIB_getVar($event, 'type');
                if (!$this->isUnique($Txn)) {
                    return false;
                }
                $isuniq = true;
            }
            $this->_handleEvent($event);
        }
        return true;
    }


    private function _handleEvent($event)
    {
        $retval = false;
        $data = LGLIB_getVar($event, 'data', 'array');
        if (!is_array($data)) {
            return false;
        }
        $parts = explode('.', $event['type']);
        switch($parts[0]) {
        case 'subscriber':
            $subscriber = LGLIB_getVar($data, 'subscriber', 'array');
            if (is_array($subscriber)) {
                $sub_id = LGLIB_getVar($subscriber, 'id');
                $email = LGLIB_getVar($subscriber, 'email');
                $type = LGLIB_getVar($subscriber, 'type');
                $Sub = Subscriber::getByEmail($email);
                switch ($parts[1]) {
                case 'added_through_webform':
                case 'create':    // via api
                case 'update':
                    if ($type == 'active') {
                        $status = Status::ACTIVE;
                    } elseif ($status == 'unsubscribed') {
                        $status = Status::UNSUBSCRIBED;
                    } else {
                        $status = Status::PENDING;
                    }
                    if ($Sub->getID() == 0) {
                        // Create a new subscriber record
                        $Sub->withStatus($status)->Save();
                    } else {
                        // Update the existing record
                        $Sub->updateStatus($status);
                    }
                    $retval = true;
                    break;
                case 'unsubscribe':
                    $email = LGLIB_getVar($subscriber, 'email');
                    $Sub = Subscriber::getByEmail($email);
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
