<?php
/**
 *  Common AJAX functions
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2010-2021 Lee Garner <lee@leegarner.com>
 *  @package    mailer
 *  @version    0.1.0
 *  @license    http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 *  @filesource
 */
use Mailer\Models\Status;
use Mailer\Models\Subscriber;


/**
 *  Include required glFusion common functions
 */
require_once '../../../lib-common.php';

// This is for administrators only
if (!SEC_hasRights('mailer.admin')) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the forms AJAX functions.");
    exit;
}

switch ($_GET['action']) {
case 'userstatus':
    $id = (int)$_GET['id'];
    $newval = (int)$_GET['newval'];
    $retval = array(
        'id' => $id,
        'icon1_cls' => 'uk-icon-circle-o',
        'icon2_cls' => 'uk-icon-circle-o',
        'icon3_cls' => 'uk-icon-circle-o',
        'icon4_cls' => 'uk-icon uk-icon-remove uk-text-danger',
    );
    $icon1 = 'black.png';
    $icon2 = 'black.png';
    $icon3 = 'black.png';

    $Sub = Subscriber::getById($id);
    switch ($newval) {
    case Status::UNSUBSCRIBED:
        $status = 'Unsubscribed';
        $Sub->unsubscribe();
        $Sub->updateStatus(STATUS::UNSUBSCRIBED, true);
        break;
    case Status::PENDING:
        $status = 'Pending';
        $icon2 = 'yellow.png';
        $retval['icon2_cls'] = 'uk-icon-circle uk-text-warning';
        $Sub->unsubscribe();
        $Sub->updateStatus(Status::PENDING, true);
        break;
    case Status::ACTIVE:
        $status = 'Active';
        $icon1 = 'green.png';
        $retval['icon1_cls'] = 'uk-icon-circle uk-text-success';
        $Sub->subscribe(Status::ACTIVE);
        break;
    case Status::BLACKLIST:
        $status = 'Blacklisted';
        $icon3 = 'red.png';
        $retval['icon3_cls'] = 'uk-icon-circle uk-text-danger';
        $Sub->unsubscribe();
        $Sub->updateStatus(Status::BLACKLIST, true);
        break;
    default:
        exit;
    }

    if ($Sub->getStatus() == $newval) {
        Mailer\Logger::Audit("Changed {$Sub->getEmail()} to status $status");
    } else {
        Mailer\Logger::Audit("Error changing status for {$Sub->getEmail()} to $status");
    }
    echo json_encode($retval);
    exit;

default:
    exit;
}
