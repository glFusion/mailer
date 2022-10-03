<?php
/**
 * This class sends specific notifications to users.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.2.0
 * @since       v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Notifiers;
use Mailer\Models\Campaign;
use Mailer\Models\Queue;
use Mailer\Config;
// This will be used with glFusion 2.1.0+
// Until then, use our own email notifier
use glFusion\Notifier;


/**
 * Notification class to send emails to subscribers.
 * @package shop
 */
class Confirmation
{
    /**
     * Send an opt-in confirmation to the subscriber
     *
     * @param   string  $email  Subscriber's email address
     * @param   string  $token  Token included to validate unsubscribe requests
     * @return  boolean     True on success (always), False may be added later
     */
    public static function send(string $email, string $token) : bool
    {
        global $_CONF, $LANG_MLR;

        $templatepath = Config::get('pi_path') . '/templates/';
        $lang = $_CONF['language'];
        if (is_file($templatepath . $lang . '/confirm_sub.thtml')) {
            $T = new \Template($templatepath . $lang);
        } else {
            $T = new \Template($templatepath . 'english/');
        }   
        $T->set_file('message', 'confirm_sub.thtml');
        $T->set_var(array(
            'pi_url'        => Config::get('url') ,
            'email'         => urlencode($email),
            'token'         => $token,
            'confirm_period' => Config::get('confirm_period'),
            'site_name'     => $_CONF['site_name'],
        ) );
        $T->parse('output', 'message');
        $body = $T->finish($T->get_var('output'));

        if (GVERSION > '2.0.1') {
            $Emailer = Notifier::getProvider('Email');
            $Emailer->setMessage($body, true)
                  ->setSubject($_CONF['site_name'] . ' ' . $LANG_MLR['confirm_title'])
                  ->addRecipient(0, '', $email)
                  ->setFromEmail(Config::senderEmail())
                  ->setFromName(Config::senderName())
                  ->send();
        } else {
            $msgData = array(
                'to' => $email,
                'bcc' => '',
                'from' => array(
                    'email' => Config::senderEmail(),
                    'name' => Config::senderName(),
                ),
                'htmlmessage' => $body,
                'subject' => $_CONF['site_name'] . ' ' . $LANG_MLR['confirm_title'],
            );
            COM_emailNotification($msgData);
        }
        return true;
    }

}
