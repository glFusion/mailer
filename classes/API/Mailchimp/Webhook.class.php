<?php
/**
 * This file contains the Mailchimp webhook handler
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
namespace Mailer\API\Mailchimp;
use Mailer\Models\Subscriber;
use Mailer\Models\Status;
use Mailer\Models\Txn;
use Mailer\Logger;
use Mailer\API;
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
    /**
     * Constructor.
     *
     * @param   array   $A  Payload provided by Stripe
     */
    function __construct()
    {
        if (isset($_POST['vars'])) {    // testing
            $this->payload = json_decode(base64_decode($_POST['vars']),true);
        } else {
            $this->payload = $_POST;
        }
        $this->provider = 'Mailchimp';
    }


    /**
     * Perform the necessary actions based on the webhook.
     *
     * @return  boolean     True on success, False on error
     */
    public function Dispatch()
    {
        $retval = false;        // be pessimistic

        if (!isset($this->payload['type'])) {
            Logger::Audit("Invalid Mailchimp Webhook Payload " .
                var_export($this->payload,true));
            return;
        }
        $action = $this->payload['type'];
        $data = LGLIB_getVar($this->payload, 'data', 'array');
        $Txn = new Txn;
        $Txn['txn_id'] = LGLIB_getVar($data, 'id');
        $Txn['txn_date'] = LGLIB_getVar($this->payload, 'fired_at');
        $Txn['data'] = $data;
        $Txn['type'] = $action;
        if (!$this->isUnique($Txn)) {
            return $retval;
        }

        switch($action) {
        case 'subscribe':
            $email = LGLIB_getVar($data, 'email');
            $list_id = LGLIB_getVar($data, 'list_id');
            if (
                empty($email) ||
                empty($list_id) ||
                $list_id != Config::get('mc_def_list')
            ) {
                Logger::Audit("Webhook $action: Empty email address or invalid list ID received.");
                return;
            }
            $Sub = Subscriber::getByEmail($email);
            $Sub->withStatus(Status::ACTIVE)->Save();
            Logger::Audit("Webhook $action: $email subscribed to $list_id");
            break;

        case 'unsubscribe':
        case 'cleaned':
            $email = LGLIB_getVar($data, 'email');
            $list_id = LGLIB_getVar($data, 'list_id');
            if (
                empty($email) ||
                empty($list_id) ||
                $list_id != Config::get('mc_def_list')
            ) {
                Logger::Audit("Webhook $action: Empty email address or invalid list ID received.");
                return;
            }
            $Sub = Subscriber::getByEmail($email);
            $Sub->withStatus(Status::UNSUBSCRIBED)->Save();
            Logger::Audit("Webhook $action: $email unsubscribed from $list_id");
            break;
        
        case 'upemail':
            if ($_CONF_MLCH['handle_upemail']) {
                // Handle email address changes.
                if (empty($_POST['data']['old_email'])) {
                    Logger::Audit('Webhook: Missing old_email');
                    exit;
                }
                if (empty($_POST['data']['new_email'])) {
                    Logger::Audit('Webhook: Missing old_email');
                    exit;
                }
                $old_email = DB_escapeString($_POST['data']['old_email']);
                $new_email = DB_escapeString($_POST['data']['new_email']);

                // Check that the new address isn't in use already
                $newUser = Subscriber::getByEmail($new_email);
                if ($newUser->getUid() > 0) {
                    Logger::Audit("Webhook: new address $new_email already used by $uid");
                    exit;
                }

                // Get the user ID belonging to the old address
                $oldUser = Subscriber::getByEmail($old_email);
                if ($oldUser->getUid() < 2) {
                    Logger::Audit("Webhook: old address $new_email not found");
                    exit;
                }

                // Perform the update
                DB_query("UPDATE {$_TABLES['users']}
                    SET email = '$new_email'
                    WHERE uid = {$oldUser->getEmail()}"
                );
                Logger::Audit("Webhook: updated user {$oldUser->getEmail()} email from $old_email to $new_email");
            }
            break;
        
        case 'Xprofile':
            // Before enabling profile updates, check to make sure that callbacks to
            // Mailchimp won't create a loop.
            $groups = array();
            if (is_array($data['merges'])) {
                if (is_array($_POST['data']['merges']['GROUPINGS'])) {
                    foreach ($_POST['data']['merges']['GROUPINGS'] as $id=>$data) {
                        $groups[$data['name']] = explode(', ', $data['groups']);
                    }
                }
            }
            //Mailchimp\Logger::System(print_r($groups, true));
            break;

        default:
            Logger::Debug("Unhandled Mailchimp event: " . var_export($this->payload,true));
            break;
        }
        return $retval;
    }

}
