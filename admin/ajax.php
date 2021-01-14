<?php
//  $Id: ajax.php 19 2010-09-17 04:13:37Z root $
/**
 *  Common AJAX functions
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 *  @package    mailer
 *  @version    0.0.1
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 *  @filesource
 */

/**
 *  Include required glFusion common functions
 */
require_once '../../../lib-common.php';

// This is for administrators only
if (!SEC_hasRights('mailer.admin')) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the forms AJAX functions.");
    exit;
}

$base_url = MLR_ADMIN_URL;

switch ($_GET['action']) {
case 'userstatus':
    $id = (int)$_GET['id'];
    $newval = (int)$_GET['newval'];
    $retval = array(
        'id' => $id,
        'icon1_cls' => 'uk-icon-circle-o',
        'icon2_cls' => 'uk-icon-circle-o',
        'icon3_cls' => 'uk-icon-circle-o',
    );
    $icon1 = 'black.png';
    $icon2 = 'black.png';
    $icon3 = 'black.png';
    switch ($newval) {
    case MLR_STAT_PENDING:
        $status = 'Pending';
        $icon2 = 'yellow.png';
        $retval['icon2_cls'] = 'uk-icon-circle uk-text-warning';
        break;
    case MLR_STAT_ACTIVE:
        $status = 'Active';
        $icon1 = 'green.png';
        $retval['icon1_cls'] = 'uk-icon-circle uk-text-success';
        break;
    case MLR_STAT_BLACKLIST:
        $status = 'Blacklisted';
        $icon3 = 'red.png';
        $retval['icon3_cls'] = 'uk-icon-circle uk-text-danger';
        break;
    default:
        exit;
    }

    $email = DB_getItem($_TABLES['mailer_emails'], 'email', "id='$id'");
    if ($email) {
        // Toggle the is_origin flag between 0 and 1
        DB_query("UPDATE {$_TABLES['mailer_emails']}
                SET status = '$newval'
                WHERE id = '$id'");
        MLR_auditLog("Changed $email to status $status");
    } else {
        MLR_auditLog("Attempted to change invalid email id: $id to $status");
    }

    echo json_encode($retval);
    exit;

default:
    exit;
}

?>
