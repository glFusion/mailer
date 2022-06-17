<?php
/**
 * This class implements the Notification for mailer.
 * It simply queues messages to be sent on behalf of this or other plugins.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.3.0
 * @since       v0.3.0
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
class Mailer extends \Mailer\Notifier   // \glFusion\Notifier after 2.1.0
{
    /**
     * Send an email notification.
     * This function simply queues the mail, which will then be sent by
     * Queue::process().
     * Uses the Internal mail provider regardless of the provider configuration,
     * does not work with external list providers.
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
            ->withExpDays((int)($this->exp_ts / 86400))
            ->Save();
        if ($status) {
            Queue::addEmails($Mlr->getID(), $this->prepareRecipients($this->recipients));
            Queue::addEmails($Mlr->getID(), $this->prepareRecipients($this->bcc));
        }
        return $status;
    }

}
