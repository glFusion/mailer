<?php
/**
 * Web service functions for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2021 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
 * Send an email to one or more addresses on behalf of another plugin.
 * Creates a mailer and queues it for the specified addresses.  The mailer
 * is owned by 'admin' and is not visible to any other users.  It also
 * expires after 4 days, which should be more than enough time to process
 * the queue.
 *
 * @param  array   $args       Array of array('email'), 'subject', 'message'
 * @param  string  $subject    Email subject
 * @param  string  $msg        Email message body
 */
function service_queueMessage_mailer($args, &$output, &$svc_msg)
{
    global $_TABLES;

    // Does not support remote web services, must be local only.
    if ($args['gl_svc'] !== false) return PLG_RET_PERMISSION_DENIED;

    $addr = isset($args['emails']) ? $args['emails'] : '';
    $subject = isset($args['subject']) ? $args['subject'] : '';
    $message = isset($args['message']) ? $args['message'] : '';

    // If only one address is passed in, convert it to an array
    if (!is_array($addr)) {
        $addr = array($addr);
    }

    // All components must be provided
    if (empty($addr) || empty($message) || empty($subject)) {
        return PLG_RET_ERROR;
    }

    // Create the mail item, overriding permissions, expiration and
    // disabling the unsubscribe link (which is meaningless here).
    $M = new Mailer\Models\Campaign();
    $M->mlr_title = $subject;
    $M->mlr_content = $message;
    $M->owner_id = 2;
    $M->perm_owner = 3;
    $M->perm_group = 0;
    $M->perm_members = 0;
    $M->perm_anon = 0;
    $M->exp_days = 4;
    if (isset($args['show_unsub']) && $args['show_unsub'] == 0) {
        // hide the unsubscribe links only if explicitly requested
        $M->show_unsub = 0;
    }
    $M->Save();

    // Now queue the message for delivery
    if (!$M->queueIt($addr)) {
        return PLG_RET_ERROR;
    } else {
        return PLG_RET_OK;
    }
}
