<?php
/**
 * This class provides two functions.
 * 1. Send confirmations to subscribers via the glFusion Email notifier.
 * 2. Queue messages to be sent on behalf of this or other plugins.
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
namespace Mailer;
use Mailer\Models\Campaign;
use Mailer\Models\Queue;


/**
 * Notification class to send emails to subscribers.
 * @package shop
 */
class Notifier extends \glFusion\Notifiers\Email
{
    /**
     * Send an opt-in confirmation to the subscriber
     *
     * @param   string  $email  Subscriber's email address
     * @param   string  $token  Token included to validate unsubscribe requests.
     */
    public static function sendConfirmation($email, $token)
    {
        global $_CONF, $LANG_MLR;

        //$title = $_CONF['site_name'] . ' ' . $LANG_MLR['confirm_title'];

        // TODO - use a template for this
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

        $Emailer = \glFusion\Notifier::getProvider('Email');
        $Emailer->setMessage($body, true)
              ->setSubject($_CONF['site_name'] . ' ' . $LANG_MLR['confirm_title'])
              ->addRecipient(0, '', $email)
              ->setFromEmail(Config::senderEmail())
              ->setFromName(Config::senderName())
              ->send();
    }


    /**
     * Send an email notification.
     * This function simply queues the mail, which will then be sent by
     * Queue::process().
     *
     * @return  boolean     True on success, False on error
     */
    public function send() : bool
    {
        $status = false;

        // Create the mailing and recipient list.
        $Mlr = new Campaign;
        $status = $Mlr->withContent($this->htmlmessage)
            ->withTitle($this->subject)
            ->withTemplate(false)
            ->withProvider('Internal')
            ->withGroup(0)      // no user access to the mailer
            ->withExpDays(5)    // probably don't need to keep even this long
            ->Save();
        if ($status) {
            Queue::addEmails($Mlr->getID(), $this->prepareRecipients($this->recipients));
            Queue::addEmails($Mlr->getID(), $this->prepareRecipients($this->bcc));
        }
        return $status;
    }

}
