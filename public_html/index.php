<?php
/**
 * API functions for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2022 Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2008 Wayne Patterson <suprsidr@gmail.com>
 * @package     mailer
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once '../lib-common.php';
if (!in_array('mailer', $_PLUGINS)) {
    COM_404();
    exit;
}
use Mailer\Models\Subscriber;
use Mailer\Models\Status;
use Mailer\Models\Campaign;
use Mailer\Menu;
use Mailer\Config;
use Mailer\Logger;


// MAIN

COM_setArgNames(array('mode', 'mlr_id'));
$mode = COM_applyFilter(COM_getArgument('mode'));
$mlr_id = COM_applyFilter(COM_getArgument('mlr_id'));
$content = '';

$T = new Template(Config::get('pi_path') . '/templates');
$T->set_file('header', 'mailer_title.thtml');
$T->set_var('title', $LANG_MLR['mlr_archive']);
$T->parse('output', 'header');
$header = $T->finish($T->get_var('output'));
$header .= Menu::User($mode);

switch ($mode) {
case 'unsub':
    $msg = '';
    if (
        isset($_GET['email']) && !empty($_GET['email']) &&
        isset($_GET['token']) && !empty($_GET['token'])
    ) {
        $Sub = Subscriber::getByEmail($_GET['email']);
        if ($Sub->getToken() == $_GET['token']) {
            if (isset($_GET['ml_id']) && !empty($_GET['ml_id'])) {
                $ml_id = $_GET['ml_id'];
            } else {
                $ml_id = 'undefined';
            }
            if ($Sub->unsubscribe()) {
                $msg = '&msg=4';    // You've been removed
            } else {
                $msg = '&msg=5';    // Invalid email or token
            }
        }
    }
    echo COM_refresh($_CONF['site_url'] . '?plugin=' . Config::PI_NAME . $msg);
    exit;
    break;

case 'print':
    $N = new Campaign($mlr_id);
    echo $N->printPage();
    exit;
    break;

case 'confirm':
    // User is confirming their subscription
    $msg = '5';     // Default "invalid token" message
    if (isset($_GET['token']) && !empty($_GET['token'])) {
        $Sub = Subscriber::getByEmail($_GET['email']);
        if (
            $Sub->getID() > 0 &&
            $Sub->getStatus() == Status::PENDING &&
            $Sub->getToken() == $_GET['token']
        ) {
            $Sub->updateStatus(Status::ACTIVE);
            $msg = '2';
            Logger::Audit("Confirmed subscription for {$Sub->getEmail()}");
        }
    }
    COM_refresh(
        $_CONF['site_url'] . "?msg=$msg&plugin=" . Config::PI_NAME
    );
    exit;
    break;

case 'bl':
    // User is requesting to be blacklisted
    $msg = '5';     // Default "invalid token" message
    if (isset($_GET['token']) && !empty($_GET['token'])) {
        $Sub = Subscriber::getByEmail($_GET['email']);
        if ($Sub->getID() > 0 &&
            $Sub->getToken() == $_GET['token']
        ) {
            $Sub->updateStatus(Status::BLACKLIST);
            $msg = '3';
            Logger::Audit("User-requested blacklisting of {$Sub->getEmail()}");
        }
    }
    COM_refresh(
        $_CONF['site_url'] . "?msg=$msg&plugin=" . Config::PI_NAME
    );
    exit;
    break;

case 'view':
    // Display the mailer
    $content .= $header;
    if (!empty($mlr_id)) {
        $N = new Campaign($mlr_id);
        if ($N->getID() > 0) {  // confirm page exists
            $content .= $N->displayPage();
        } else {
            $content .= COM_showmessageText($LANG_MLR['not_found']);
            $content .= Campaign::userList();
        }
    } else {
        $content .= Campaign::userList();
    }
    break;

case 'list':
default:
    $content .= $header;
    $content .= COM_startBlock($LANG_MLR['list_title']);
    $content .= Campaign::userList();
    $content .= COM_endBlock();
    break;
}

$display = Menu::siteHeader('');
$display .= $content;
$display .= Menu::siteFooter();
echo $display;
