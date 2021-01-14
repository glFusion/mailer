<?php
/**
 * Class to send notifications to subscribers.
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
namespace Mailer;


/**
 * Notification class to send opt-in emails to subscribers.
 * @package shop
 */
class Notifier
{
    /**
     * Send an email to the administrator and/or buyer.
     *
     * @param   string  $status     Order status (pending, paid, etc.)
     * @param   string  $gw_msg     Optional gateway message to include with email
     */
    public static function Send($email, $token)
    {
        global $_CONF, $LANG_MLR;

        $title = $_CONF['site_name'] . ' ' . $LANG_MLR['confirm_title'];

        // TODO - use a template for this
        $templatepath = MLR_PI_PATH . 'templates/';
        $lang = $_CONF['language'];
        if (is_file($templatepath . $lang . '/confirm_sub.thtml')) {
            $T = new Template($templatepath . $lang);
        } else {
            $T = new Template($templatepath . 'english/');
        }   
        $T->set_file('message', 'confirm_sub.thtml');
        $T->set_var(array(
            'pi_url'        => MLR_URL,
            'email'         => urlencode($email),
            'token'         => $token,
            'confirm_period' => $_MLR_CONF['confirm_period'],
            'site_name'     => $_CONF['site_name'],
        ) );

        $T->parse('output', 'message');
        $body = $T->finish($T->get_var('output'));
        $altbody = strip_tags($body);

        // Create the "from" address using the site or noreply mail address
        $fromEmail = isset($_CONF[$_MLR_CONF['email_from']]) ?
            $_CONF[$_MLR_CONF['email_from']] : $_CONF['noreply_mail'];
        $from = array($fromEmail, $_CONF['site_name']);

        COM_mail($email, $title, $body, $from, true, 0, '', $altbody);
    }

}
