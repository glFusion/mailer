<?php
/**
 * This file contains the Sendinblue webhook handler
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
namespace Mailer\API\Sendinblue;
use Mailer\Models\Subscriber;
use Mailer\Models\Status;
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
    /**
     * Constructor.
     *
     * @param   array   $A  Payload provided by Stripe
     */
    function __construct()
    {
        if (!empty($_POST) && isset($_POST['vars'])) {
            $this->payload = json_decode(base64_decode($_POST['vars']), true);    // testing
        } else {
            $this->payload = json_decode(@file_get_contents('php://input'), true);
        }
        $this->provider = 'Sendinblue';
    }


    /**
     * Perform the necessary actions based on the webhook.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
        $retval = false;        // be pessimistic
        switch($this->payload['event']) {
        case 'contact_updated':
            $data = $this->payload['content'][0];
            $email = isset($data['email']) ? $data['email'] : '';
            $force_update = false;
            if (!empty($email)) {
                $Sub = Subscriber::getByEmail($email);
                $attribs = isset($data['attributes']) ? $data['attributes'] : array();
                if (
                    isset($attribs['DOUBLE_OPT-IN']) &&
                    $attribs['DOUBLE_OPT-IN'] == 1
                ) {
                    $Sub->withStatus(Status::ACTIVE);
                    $force_update = true;   // make sure it gets saved
                }
                $sub_attr = $Sub->getAttributes();
                foreach ($sub_attr as $key=>$val) {
                    if (isset($attribs[$key])) {
                        $Sub->setAttribute($key, $attribs[$key]);
                    }
                }
                $Sub->updateUser($force_update);
            }
            break;

        case 'unsubscribe':
        case 'hard_bounce':
            $email = isset($this->payload['email']) ? $this->payload['email'] : '';
            $Sub = Subscriber::getByEmail($email);
            if ($Sub->getID() > 0) {
                $Sub->updateStatus(Status::UNSUBSCRIBED);
                $retval = true;
            }
            break;

        case 'list_addition':
            $email = isset($this->payload['email']) ? $this->payload['email'] : '';
            if (!empty($email)) {
                $Sub = Subscriber::getbyEmail($email);
                if ($Sub->getID() > 0) {    // Make sure it exists
                    $Sub->withStatus(Status::ACTIVE)->Save();
                }
            }
            break;

        case 'contact_deleted':
            $emails = isset($this->payload['email']) && is_array($this->payload['email']) ? $this->payload['email'] : array();
            foreach ($emails as $email) {
                $Sub = Subscriber::getByEmail($email);
                $Sub->delete();
            }
            break;

        default:
            Logger::Debug("Unhandled Sendinblue event: " . var_export($this->payload,true));
            break;
        }
        return $retval;
    }

}
