<?php
/**
 * API functions for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2008 Wayne Patterson <suprsidr@gmail.com>
 * @package     mailer
 * @version     v0.0.1
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
use Mailer\Models\Mailer;
use Mailer\Menu;


/**
 * Save an email address.
 * Gets the address directoy from $_GET.
 */
function MLR_storeAddress()
{
    global $LANG_MLR, $_MLR_CONF;

    $message = '&nbsp;';

    if (!isset($_GET['email'])) {
        //$message = $LANG_MLR['email_missing'];
        return '10';
    }

    $address = $_GET['email'];
    if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*$/i", $address)) {
        //$message = $LANG_MLR['email_format_error'];
        return '9';
    }

    // Basic checks passed, now try to add the address
    $Sub = Subscriber::getByEmail($address);
    if ($Sub->getID() > 0) {        // email already exists
        $message = '6';
    } elseif ($Sub->subscribe()) {
        $message = '1';
    }
    return $message;
}


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

    $menu_arr = array();

    $text_arr = array();

    $query_arr = array(
        'table' => 'mailer',
        'sql' => "SELECT mlr_id, mlr_title,
                    UNIX_TIMESTAMP(mlr_date) AS unixdate
                FROM {$_TABLES['mailer']} " .
                COM_getPermSQL('WHERE', 0, 2),
        'query_fields' => array('mlr_title', 'mlr_content'),
    );

    $options = array();

    $retval .= ADMIN_createMenu($menu_arr, $LANG_MLR['instr_archive'],
        plugin_geticon_mailer());

    $retval .= ADMIN_list('mailer', 'MLR_public_getListField',
                          $header_arr, $text_arr, $query_arr, $defsort_arr,
                        '', '', $options);
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
            Config::get('url') . "/index.php?page={$A['mlr_key']}"
        );
        break;

    case 'unixdate':
        $retval = strftime($_CONF['dateonly'], $fieldvalue);
        break;

    default:
        $retval = $fieldvalue;
        break;
    }

    return $retval;
}


// MAIN

COM_setArgNames(array('page', 'mode', 'view', 'email'));
$page = COM_applyFilter(COM_getArgument('page'));
$display_mode = COM_applyFilter(COM_getArgument('mode'));
$view = COM_applyFilter(COM_getArgument('view'));
if($display_mode == 'print') {
    $view = 'print';
}
$content = '';

switch ($view) {
case 'add':
    $message = '&nbsp;';
    if (!isset($_GET['email'])) {
        //$message = $LANG_MLR['email_missing'];
        return '10';
    }

    // Basic checks passed, now try to add the address
    $address = $_GET['email'];
    $Sub = Subscriber::getByEmail($address);
    if ($Sub->getStatus() == Status::ACTIVE) {        // email already exists
        $message = '6';
    } elseif ($Sub->getStatus() == Status::BLACKLIST) {
        $message = 0;
    } else {
        $status = $Sub->subscribe();
        if ($status == Status::SUB_SUCCESS) {
            $message = '1';
        } elseif ($status == Status::SUB_INVALID) {
            $message = '9';
        } else {
            $message = '8';
        }
    }
    if ($display_mode == 'success') {
        // called from a normal link.  Assume $content
        // contains a PLG_mailer message ID
        echo COM_refresh(
            $_CONF['site_url'] . "?msg=$message&plugin=" . Config::PI_NAME
        );
    } else {
        echo $content;
    }
    exit;
    break;

case 'list':
    $content .= COM_startBlock($LANG_MLR['list_title']);
    $content .= MLR_listArchives();
    $content .= COM_endBlock();
    break;

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

/*case 'javascript':
    $content = header('content-type: application/x-javascript');
    $content .= MLR_display_javascript();
    echo $content;
    exit;
    break;*/

case 'print':
    $N = Mailer::getById($page);
    //$content = MLR_returnMailer($page, $view);
    //echo $content;
    echo $N->printPage();
    exit;
    break;

case 'confirm':
    // User is confirming their subscription
    $msg = '5';     // Default "invalid token" message
    if (isset($_GET['token']) && !empty($_GET['token'])) {
        $token = DB_escapeString($_GET['token']);
        $email = DB_escapeString($_GET['email']);
        $status = DB_getItem($_TABLES['mailer_emails'], 'status',
                "email='$email' AND token='$token'");
        if ($status !== NULL && $status == Status::PENDING) {
            DB_query("UPDATE {$_TABLES['mailer_emails']}
                SET status = '" . Status::ACTIVE . "'
                WHERE token='$token'");
            $msg = '2';
            Mailer\Logger::Audit("Confirmed subscription for $email");
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
        $token = DB_escapeString($_GET['token']);
        $email = DB_getItem($_TABLES['mailer_emails'], 'email',
                "token='$token'");
        if ($email != NULL) {
            DB_query("UPDATE {$_TABLES['mailer_emails']}
                    SET status = '" . Status::BLACKLIST . "'
                    WHERE token='$token'");
            $msg = '3';
            Mailer\Logger::Audit("User-requested blacklisting of $email");
        }
    }
    COM_refresh(
        $_CONF['site_url'] . "?msg=$msg&plugin=" . Config::PI_NAME
    );
    exit;
    break;

default:
    // Display the mailer
    if (!empty($page)) {
        $N = Mailer::getById($page);
        if ($N->getID() > 0) {  // confirm page exists
            $content = $N->displayPage();
            $N->UpdateHits();
        } else {
            $content .= COM_showmessageText($LANG_MLR['not_found']);
            $content .= MLR_listArchives();
        }
    } else {
        $content .= MLR_listArchives();
    }
}

$display = Menu::siteHeader('');
$display .= $content;
$display .= Menu::siteFooter();
echo $display;

?>