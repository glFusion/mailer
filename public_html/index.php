<?php
/**
 * API functions for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2021 Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2008 Wayne Patterson <suprsidr@gmail.com>
 * @package     mailer
 * @version     v0.0.4
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


/**
 * List mailer archives.
 *
 * @return  string    HTML for the mailer archives
 */
function MLR_listArchives()
{
    global $_TABLES, $LANG_ADMIN, $LANG08, $LANG_MLR;

    USES_lib_admin();

    $retval = '';

    $header_arr = array(
        array('text' => $LANG08[32], 'field' => 'unixdate',
                'sort' => 'true'),
        array('text' => $LANG_ADMIN['title'], 'field' => 'mlr_title',
                'sort' => 'true'),
    );
    $defsort_arr = array('field' => 'unixdate', 'direction' => 'DESC');
    $text_arr = array();
    $query_arr = array(
        'table' => 'mailer',
        'sql' => "SELECT mlr_id, mlr_title,
                    UNIX_TIMESTAMP(mlr_date) AS unixdate
                FROM {$_TABLES['mailer_campaigns']} " .
                COM_getPermSQL('WHERE', 0, 2),
        'query_fields' => array('mlr_title', 'mlr_content'),
    );
    $options = array();
    $retval .= ADMIN_list(
        'mailer', 'MLR_public_getListField',
        $header_arr, $text_arr, $query_arr, $defsort_arr,
        '', '', $options
    );
    return $retval;
}


/**
 * Gets the output to display for each field in the mailing archive list.
 *
 * @param   string  $fieldname      Name of the field
 * @param   string  $fieldvalue     Value of the field
 * @param   array   $A              Array of all field=>value pairs
 * @param   array   $icon_arr       Array of admin icons
 * @return  string                  Display value for $fieldname
 */
function MLR_public_getListField($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF;

    switch ($fieldname) {
    case 'mlr_title':
        $retval = COM_createLink(
            $fieldvalue,
            COM_buildUrl(
                Config::get('url') . "/index.php?mode=view&mlr_id={$A['mlr_id']}"
            )
        );
        break;

    case 'unixdate':
        $dt = new \Date($fieldvalue, $_CONF['timezone']);
        $retval = $dt->format('Y-m-d');
        break;

    default:
        $retval = $fieldvalue;
        break;
    }

    return $retval;
}


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
            $content .= MLR_listArchives();
        }
    } else {
        $content .= MLR_listArchives();
    }
    break;

case 'list':
default:
    $content .= $header;
    $content .= COM_startBlock($LANG_MLR['list_title']);
    $content .= MLR_listArchives();
    $content .= COM_endBlock();
    break;
}

$display = Menu::siteHeader('');
$display .= $content;
$display .= Menu::siteFooter();
echo $display;
