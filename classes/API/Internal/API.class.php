<?php
/**
 * Internal API class for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2020 Lee Garner <lee@leegarner.com>
 * @version     v0.4.0
 * @package     mailer
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\API\Internal;
use Mailer\Models\Subscriber;
use Mailer\Models\ApiInfo;
use Mailer\Models\Status;
use Mailer\Models\Campaign;
use Mailer\Notifier;
use Mailer\Config;


/**
 * Internal API driver.
 * @package mailer
 */
class API extends \Mailer\API
{
    /* PHPMailer object, used for inter-function access.
     * @var object */
    private $phpmailer = NULL;


    /**
     * Get a list of members subscribed to a given list.
     *
     * @param   string  $list_id    Mailing List ID
     * @param   string  $status     Member status, default=subscribed
     * @param   mixed   $since      Starting date for subscriptions
     * @param   integer $offset     Offset to retrieve, in case of large lists
     * @param   integer $count      Maximum number of members to retrieve.
     * @return  array       Array of data
     */
    public function listMembers($opts=array())
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['mailer_subscribers']}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['id']] = $A;     // todo, use subscriber model
        }
        return $retval;
    }


    /**
     * Get an array of mailing lists visible to this API key.
     *
     * @param   array   $fields     Fields to retrieve
     * @param   integer $offset     First record offset, for larget datasets
     * @param   integer $count      Number of items to return
     * @return  array       Array of list data
     */
    public function lists($fields=array(), $offset=0, $count=25)
    {
        return array(
            'list_id' => 1,
            'list_name' => 'Main List',
        );
    }


    /**
     * Subscribe an email address to one or more lists.
     * Noop for this API, subscriber is added to the table by the
     * Subscriber class, no other action needed.
     *
     * @param   object  $Sub    Subscriber object
     * @return  integer     Result status
     */
    public function subscribe($Sub)
    {
        if (!self::isValidEmail($Sub->getEmail())) {
            return Status::SUB_INVALID;
        } else {
            return Status::SUB_SUCCESS;
        }
    }


    /**
     * Unsubsubscribe a subscriber from the mailing list.
     *
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists  Array of list IDs
     * @return  boolean     True on success, False on error
     */
    public function unsubscribe($Sub, $lists=array())
    {
        // No API action taken by the Internal mailer.
        return true;
    }


    /**
     * Get the tags associated with a subscriber.
     *
     * @unused
     * @param   string  $email      Subscriber email address
     * @param   string  $list_id    Mailing list ID
     * @return  array       Array of tags
     */
    public function getTags(Subscriber $Sub)
    {
        return array();
    }


    /**
     * Update a subscriber's tags.
     *
     * @unused
     * @param   object  $Sub    Subscriber object
     * @param   array   $lists  Array of email lists
     */
    public function updateTags(Subscriber $Sub)
    {
        return true;
    }


    /**
     * Get information about a specific member by email address.
     * This API just returns the information in the database, no attributes.
     *
     * @param   string  $email      Email address
     * @return  array       Array of member information
     */
    public function getMemberInfo(Subscriber $Sub, $list_id='')
    {
        $retval = new ApiInfo;
        $retval['provider_uid'] = $Sub->getId();
        $retval['email_address'] = $Sub->getEmail();
        $retval['status'] = $Sub->getStatus();
        foreach ($Sub->getAttributes() as $k=>$v) {
            $retval->setAttribute($k, $v);
        }
        return $retval;;
    }


    /**
     * This API does nothing when updateMember() is called.
     *
     * @param   object  $Sub    Subscriber object
     * @return  boolean     Always true
     */
    public function updateMember(Subscriber $Sub, $lists=array())
    {
        return true;
    }


    /**
     * Send a double opt-in notification to a subscriber.
     *
     * @param   object  $Sub    Subscriber object
     * @return  boolean     True on success, False on error
     */
    public static function sendDoubleOptin(Subscriber $Sub)
    {
        return Notifier::Send($Sub->getEmail(), $Sub->getToken());
    }


    /**
     * Get the features available to administrators.
     *
     * @return  array   Array of menus to show
     */
    public function getFeatures()
    {
        return array('mailers', 'subscribers', 'queue');
    }


    /**
     * Check if the API supports synchronizing the local table.
     * Actual providers do support this, the Internal provider does not.
     *
     * @return  boolean     True if synchronization is supported
     */
    public function supportsSync()
    {
        return true;
    }


    public function prepareMailing()
    {
        global $_CONF;

        $T = new \Template(Config::get('pi_path') . 'templates/');
        $T->set_file('msg', 'mailer_email.thtml');
        $T->set_var(array(
            'content'   => $this->mlr_content,
            'pi_url'    => Config::get('url'),
            'mlr_id'    => $this->mlr_id,
            'token'     => $token,
            'email'     => $email,
            'unsub_url' => $unsub_link,
            'show_unsub' => $Mlr->showUnsub()? 'true' : '',
        ) );
        $T->parse('output', 'msg');
        $message = $T->finish($T->get_var('output'));
        $altbody = strip_tags($message);

        $subject = trim($this->mlr_title);
        $subject = COM_emailEscape($subject);

        $this->phpmailer = new \PHPMailer();
        $this->phpmailer->SetLanguage('en');
        $this->phpmailer->CharSet = COM_getCharset();
        if ($_CONF['mail_backend'] == 'smtp') {
            $this->phpmailer->IsSMTP();
            $this->phpmailer->Host     = $_CONF['mail_smtp_host'];
            $this->phpmailer->Port     = $_CONF['mail_smtp_port'];
            if ($_CONF['mail_smtp_secure'] != 'none') {
                $this->phpmailer->SMTPSecure = $_CONF['mail_smtp_secure'];
            }
            if ($_CONF['mail_smtp_auth']) {
                $this->phpmailer->SMTPAuth   = true;
                $this->phpmailer->Username = $_CONF['mail_smtp_username'];
                $this->phpmailer->Password = $_CONF['mail_smtp_password'];
            }
            $this->phpmailer->Mailer = "smtp";

        } elseif ($_CONF['mail_backend'] == 'sendmail') {
            $this->phpmailer->Mailer = "sendmail";
            $this->phpmailer->Sendmail = $_CONF['mail_sendmail_path'];
        } else {
            $this->phpmailer->Mailer = "mail";
        }
        $this->phpmailer->WordWrap = 76;

        // Create the HTML message. Automatically creates the AltBody and
        // inlines any images.
        $thie->phpmailer->IsHTML($this->mailHTML);
        if ($this->mailHTML) {
            $body = COM_filterHTML($message);
            $this->phpmailer->msgHTML($message, $_CONF['path_html']);
        } else {
            $this->phpmailer->Body = $message;
        }

        $this->phpmailer->Subject = $subject;
        $this->phpmailer->From = Config::senderEmail();
        $this->phpmailer->FromName = Config::senderName();

        $this->phpmailer->AddCustomHeader(
            'List-ID:Announcements from ' . $_CONF['site_name']
        );
        $this->phpmailer->AddCustomHeader('List-Archive:<' . Config::get('url') . '>Prior Mailings');
        $this->phpmailer->AddCustomHeader('X-Unsubscribe-Web:<' . $unsub_url . '>');
        $this->phpmailer->AddCustomHeader('List-Unsubscribe:<' . $unsub_url . '>');

        /*$mail->AddAddress($email);

        if(!$mail->Send()) {
            COM_errorLog("Email Error: " . $mail->ErrorInfo);
            return false;
        }*/
        return true;
    }


    private function _addRecipient($email)
    {
        if ($this->phpmailer === NULL) {
            $this->prepairMailing();
        }
        $this->phpmailer->AddAddress($email);
    }


    private function _send()
    {
        if(!$this->phpmailer->Send()) {
            COM_errorLog("Email Error: " . $this->phpmailer->ErrorInfo);
            return false;
        } else {
            return true;
        }
    }


    /**
     * Queue the email campaign for sending.
     *
     * @param   object      $Mlr    Campaign object
     * @param   array|null  $emails Email override addresses
     * @return  boolean     Status from queuing
     */
    public function queueEmail(Mailer $Mlr, $emails=NULL)
    {
        global $_TABLES;

        $mlr_id = DB_escapeString($Mlr->getID());
        if ($emails === NULL) {
            $values = "SELECT '{$mlr_id}', email
                FROM {$_TABLES['mailer_subscribers']}
                WHERE status = " . Status::ACTIVE;
        } elseif (is_array($emails)) {
            $vals = array();
            foreach ($emails as $email) {
                $vals[] = "('{$mlr_id}', '" . DB_escapeString($email) . "')";
            }
            $values = ' VALUES ' . implode(',', $vals);
        } else {
            return false;
        }
        $sql = "INSERT IGNORE INTO {$_TABLES['mailer_queue']}
                (mlr_id, email) $values";
        DB_query($sql);
        if (!DB_error()) {
            DB_query(
                "UPDATE {$_TABLES['mailer_campaigns']}
                SET mlr_sent_time = UNIX_TIMESTAMP()
                WHERE mlr_id = " . $Mlr->getID()
            );
            return true;
        } else {
            return false;
        }
    }


    /**
     * Create the campaign.
     * For this provider, the campaign has already been saved,
     * just update the campaign/provider record.
     *
     * @param   object  $Mlr    Mailer object
     * @return  string      Campaign ID
     */
    public function createCampaign($Mlr)
    {
        $this->saveCampaignInfo($Mlr, $Mlr->getID());
    }


    /**
     * Send the campaign.
     *
     * @param   string  $campaign_id    Campaign ID
     * @param   array   $emails         Email override addresses
     * @param   string  $token          Token string
     * @return  boolean     Status from sending
     */
    public function sendCampaign($campaign_id, $emails=array(), $token='')
    {
        return $this->queueEmail(Campaign::getInstance($campaign_id), $emails);
    }


    /**
     * Send a test message to the current user's email address.
     *
     * @return  boolean     True on success, False on error
     */
    public function sendTest($camp_id)
    {
        global $_USER;

        $Mlr = new Campaign($camp_id);
        return $this->sendEmail($Mlr, $_USER['email']) == 0 ? true : false;
    }


    /**
     * Send a mailer.
     *
     * @param   object  $Mlr    Campaign object
     * @param   string  $email  Optional email address
     * @param   string  $token  Optional token
     * @return  integer     Status code, 0 = success
     */
    public function sendEmail(Campaign $Mlr, $email='', $token='')
    {
        global $_CONF;

        $unsub_url = Config::get('url') . '/index.php?view=unsub&email=' .
            urlencode($email);
        if (!empty($token)) {
            $unsub_url .= '&amp;token=' . urlencode($token);
        }
        $unsub_link = COM_createLink($unsub_url, $unsub_url);

        $T = new \Template(Config::get('pi_path') . 'templates/');
        $T->set_file('msg', 'mailer_email.thtml');
        $T->set_var(array(
            'content'   => PLG_replaceTags($Mlr->getContent()),
            'pi_url'    => Config::get('url'),
            'mlr_id'    => $Mlr->getID(),
            'token'     => $token,
            'email'     => $email,
            'unsub_url' => $unsub_link,
            'show_unsub' => true,
        ) );
        $T->parse('output', 'msg');
        $message = $T->finish($T->get_var('output'));
        $altbody = strip_tags($message);

        $subject = trim($Mlr->getTitle());
        $subject = COM_emailEscape($subject);

        $mail = new \PHPMailer();
        $mail->SetLanguage('en');
        $mail->CharSet = COM_getCharset();
        if ($_CONF['mail_backend'] == 'smtp') {
            $mail->IsSMTP();
            $mail->Host     = $_CONF['mail_smtp_host'];
            $mail->Port     = $_CONF['mail_smtp_port'];
            if ($_CONF['mail_smtp_secure'] != 'none') {
                $mail->SMTPSecure = $_CONF['mail_smtp_secure'];
            }
            if ($_CONF['mail_smtp_auth']) {
                $mail->SMTPAuth   = true;
                $mail->Username = $_CONF['mail_smtp_username'];
                $mail->Password = $_CONF['mail_smtp_password'];
            }
            $mail->Mailer = "smtp";
        } elseif ($_CONF['mail_backend'] == 'sendmail') {
            $mail->Mailer = "sendmail";
            $mail->Sendmail = $_CONF['mail_sendmail_path'];
        } else {
            $mail->Mailer = "mail";
        }
        $mail->WordWrap = 76;

        // Create the HTML message. Automatically creates the AltBody and
        // inlines any images.
        $mail->IsHTML($Mlr->isHTML());
        if ($Mlr->isHTML()) {
            $body = COM_filterHTML($message);
            $mail->msgHTML($message, $_CONF['path_html']);
        } else {
            $mail->Body = $message;
        }

        $mail->Subject = $subject;
        $mail->From = Config::senderEmail();
        $mail->FromName = Config::senderName();
        $mail->AddCustomHeader('List-ID:Announcements from ' . Config::senderName());
        $mail->AddCustomHeader('List-Archive:<' . Config::get('url') . '>Prior Mailings');
        $mail->AddCustomHeader('X-Unsubscribe-Web:<' . $unsub_url . '>');
        $mail->AddCustomHeader('List-Unsubscribe:<' . $unsub_url . '>');
        $mail->AddAddress($email);
        if(!$mail->Send()) {
            COM_errorLog("Email Error: " . $mail->ErrorInfo);
            return -1;
        }
        return 0;
    }

}
