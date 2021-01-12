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

if (GVERSION < '1.3.0') {
    if (!empty($_GET)) $_GET = MLR_stripslashes($_GET);
    if (!empty($_POST)) $_GET = MLR_stripslashes($_POST);
}


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
    $success = MLR_addEmail($address);
    switch ($success) {
    case MLR_ADD_SUCCESS:
        //$message = $LANG_MLR['email_success'];
        $message = '1';
        break;
    case MLR_ADD_MISSING:
        //$message = $LANG_MLR['email_missing'];
        $message = '5';
        break;
    case MLR_ADD_EXISTS:
        //$message = $LANG_MLR['email_exists'];
        $message = '6';
        break;
    case MLR_ADD_BLACKLIST:
        //$message = $LANG_MLR['email_blacklisted'];
        $message = '7';
        break;
    default:
    case MLR_ADD_ERROR:
        //$message = $LANG_MLR['email_store_error'];
        $message = '8';
        break;
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
        $retval = COM_createLink($fieldvalue,
                MLR_URL . "/index.php?page={$A['mlr_id']}");
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

if($display_mode == 'print') $view = 'print';

$content = '';
$blockformat = 9;      // By default, use global config for block selection

switch ($view) {
case 'add':
    $content = MLR_storeAddress();
    if ($display_mode == 'success') {
        // called from a normal link.  Assume $content
        // contains a PLG_mailer message ID
        echo COM_refresh($_CONF['site_url'] .
                "?msg=$content&plugin={$_MLR_CONF['pi_name']}");
        //$display .= COM_startBlock($LANG_MLR['success_title']);
        //$display .= $LANG_MLR['add_success'];
        //$display .= COM_endBlock();
    } else {
        $content = $PLG_mailer_MESSAGE{$content};
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
    if (isset($_GET['email']) && !empty($_GET['email']) &&
        isset($_GET['token']) && !empty($_GET['token'])) {
        if (isset($_GET['ml_id']) && !empty($_GET['ml_id'])) {
            $ml_id = $_GET['ml_id'];
        } else {
            $ml_id = 'undefined';
        }
        if (MLR_unsubscribe($_GET['email'], $_GET['token'], $ml_id))
            $msg = '&msg=4';    // You've been removed
        else
            $msg = '&msg=5';    // Invalid email or token
    } else {
        $msg = '';
    }
    echo COM_refresh($_CONF['site_url'] . '?plugin='.$_MLR_CONF['pi_name'].$msg);
    exit;
    break;

/*case 'javascript':
    $content = header('content-type: application/x-javascript');
    $content .= MLR_display_javascript();
    echo $content;
    exit;
    break;*/

case 'print':
    USES_mailer_class_mailer();
    $N = new Mailer($page);
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
        if ($status !== NULL && $status == MLR_STAT_PENDING) {
            DB_query("UPDATE {$_TABLES['mailer_emails']}
                SET status = '" . MLR_STAT_ACTIVE . "'
                WHERE token='$token'");
            $msg = '2';
            MLR_auditLog("Confirmed subscription for $email");
        }
    }
    echo COM_refresh($_CONF['site_url'] . 
            "?msg=$msg&plugin={$_MLR_CONF['pi_name']}");
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
                    SET status = '" . MLR_STAT_BLACKLIST . "'
                    WHERE token='$token'");
            $msg = '3';
            MLR_auditLog("User-requested blacklisting of $email");
        }
    }
    echo COM_refresh($_CONF['site_url'] . 
            "?msg=$msg&plugin={$_MLR_CONF['pi_name']}");
    exit;
    break;
    
default:
    // Display the mailer
    if (!empty($page)) {
        USES_mailer_class_mailer();
        $N = new Mailer($page);
        if (!$N->isNew) {
            // isNew will be true if the mlr_id was invalid
            $blockformat = $N->mlr_format;  // let the mailer pick the blocks

            /*if ($page == 'print') {
                $content = $N->printPage();
            } else {*/
                $content = $N->displayPage();
//            }

            $N->UpdateHits();
        } else {
            $content .= COM_showmessageText($LANG_MLR['not_found']);
            $content .= MLR_listArchives();
        }

    } else {
        $content .= MLR_listArchives();
    }
}

$display = MLR_siteHeader('', '', $blockformat);
$display .= $content;
$display .= MLR_siteFooter($blockformat);
echo $display;

?>
