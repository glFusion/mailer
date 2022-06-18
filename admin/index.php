<?php
/**
 * Administrative entry point for the Mailer plugin.
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

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
use Mailer\Config;
use Mailer\Models\Status;
use Mailer\Models\Campaign;
use Mailer\Models\Queue;
use Mailer\Models\Subscriber;
use Mailer\API;
use Mailer\Menu;
use glFusion\Database\Database;

if (!SEC_hasRights('mailer.admin,mailer.edit', 'OR')) {
    $display = Menu::siteHeader($LANG_MLR['access_denied']);
    $display .= COM_startBlock(
        $LANG_MLR['access_denied'],
        '',
        COM_getBlockTemplate('_msg_block', 'header')
    );
    $display .= $LANG_MLR['access_denied_msg'];
    $display .= COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
    $display .= Mailer::siteFooter();
    COM_accessLog("User {$_USER['username']} tried to illegally access the mailers administration screen.");
    echo $display;
    exit;
}


// MAIN
$expected = array(
    // actions
    'blacklist_x', 'blacklist',
    'whitelist_x', 'whitelist', 'active',
    'delsubscriber',
    'edit', 'clone', 'mlr_save',
    'delete', 'sendnow', 'sendtest',
    'deletequeue', 'purgequeue', 'resetqueue', 'flushqueue',
    'clear_warning', 'clearsub', 'api_action',
    'syncfrom_warning', 'syncfrom',
    'import', 'export',
    // views
    'campaigns', 'subscribers', 'queue', 'maintenance',
);
$action = Config::get('def_adm_view');
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}

$mlr_id = '';
if (isset($_REQUEST['mlr_id'])) {
    $mlr_id = COM_applyFilter($_REQUEST['mlr_id']);
}
$email = '';
if (isset($_REQUEST['email'])) {
    $email = COM_applyFilter($_REQUEST['email']);
}

$content = '';
$msg = '';
switch ($action) {
case 'api_action':
    // Perform actions for the current API
    $API = Mailer\API::getInstance();
    $action_content = $API->handleActions(array(
        'get' => $_GET,
        'post' => $_POST,
    ) );
    $view = 'maintenance';
    //COM_refresh(Config::get('admin_url') . '/index.php?maintenance');
    break;

case 'sendtest':
    $Mailer = new Campaign($actionval);
    if (!$Mailer->isNew()) {
        $Mailer->sendTest();
    }
    COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'sendnow':
    $Mailer = new Campaign($_GET['mlr_id']);
    if (!$Mailer->isNew()) {
        API::getInstance()->sendCampaign($Mailer);
        //$Mailer->queueIt();
    }
    COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'delsubscriber':
    if (isset($_POST['delsubscriber']) && is_array($_POST['delsubscriber'])) {
        $del_subs = array();
        foreach ($_POST['delsubscriber'] as $idx=>$sub_id) {
            $Sub = Subscriber::getById($sub_id);
            $Sub->unsubscribe();
            $Sub->delete();
        }
    } elseif (isset($_REQUEST['id'])) {
        $Sub = Subscriber::getById($_REQUEST['id']);
        if ($Sub->getID() > 0) {
            $Sub->unsubscribe();
            $Sub->delete();
        }
    }
    $view = 'subscribers';
    break;

case 'blacklist_x':     // @deprecated
case 'blacklist':
    if (isset($_POST['delsubscriber']) && is_array($_POST['delsubscriber'])) {
        foreach ($_POST['delsubscriber'] as $id) {
            Subscriber::getById($id)->updateStatus(Status::BLACKLIST);
        }
    }
    $view = 'subscribers';
    break;

case 'whitelist_x':     // @deprecated
case 'whitelist':
case 'active':
    if (isset($_POST['delsubscriber']) && is_array($_POST['delsubscriber'])) {
        foreach ($_POST['delsubscriber'] as $id) {
            Subscriber::getById($id)->updateStatus(Status::ACTIVE, true);
        }
    }
    $view = 'subscribers';
    break;

case 'clearsub':
    $db = Database::getInstance();
    if (SEC_checkToken()) {
        $db->conn->executeQuery("TRUNCATE {$_TABLES['mailer_subscribers']}");
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?maintenance');
    break;

case 'clone':
    $M = new Campaign($mlr_id);
    if ($M->isNew()) {     // can't clone a non-existant mailer
        $view = 'campaigns';
        break;
    }
    $status = $M->withID('')->Save();
    echo COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'delete':
    (new Campaign($mlr_id))->Delete();
    COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'syncfrom':
    if (SEC_checkToken()) {
        Mailer\Models\Subscriber::syncFromProvider();
    }
    COM_refresh(Config::get('admin_url') . '/index.php?subscribers');
    $view = 'subscribers';
    break;

case 'mlr_save':
    $mlr_id = isset($_POST['mlr_id']) ? $_POST['mlr_id'] : '';
    $M = new Campaign($mlr_id);
    $status = $M->Save($_POST);
    if (!$status) {
        $content .= Menu::Admin('campaigns');
        $content .= Menu::adminCampaigns('edit');
        $content .= COM_showMessageText('<ul>' . $M->PrintErrors() . '</ul>', 'Error', true, 'error');
        $content .= $M->Edit();
        $view = 'none';     // Editing it here, no other display
    } else {
        COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    }
    break;

case 'deletequeue':     // delete an item from the queue
    if (!is_array($actionval)) {
        $actionval = array($actionval);
    }
    Queue::deleteMulti($actionval);
    COM_refresh(Config::get('admin_url') . '/index.php?queue');
    break;

case 'purgequeue':
    Queue::purge();
    COM_refresh(Config::get('admin_url') . '/index.php?queue');
    break;

case 'resetqueue':
    Queue::reset();
    COM_refresh(Config::get('admin_url') . '/index.php?queue');
    break;

case 'flushqueue':
    Queue::process(true);
    COM_refresh(Config::get('admin_url') . '/index.php?queue');
    break;

case 'subscribers':
case 'campaigns':
case 'queue':
    $view = $action;
    $features = API::getInstance()->getFeatures();
    if (!in_array($view, $features)) {
        $view = $features[0];
    }
    break;
default:
    $view = $action;
    break;
}

// Now create the content to be displayed
$content .= Menu::Admin($view);
switch ($view) {
case 'edit':
    $M = new Campaign($mlr_id);
    $content .= Menu::adminCampaigns($view);
    $content .= $M->Edit();
    break;

case 'campaigns':
    $content .= Menu::adminCampaigns($view);
    $content .= Campaign::adminList();
    break;

case 'subscribers':
    $content .= Menu::adminSubscribers($view);
    $content .= Subscriber::adminList();
    break;

case 'queue':
    $content .= Menu::adminQueue($view);
    $content .= Queue::adminList();
    break;

case 'clear_warning':
    // Display a warning confirmation before clearing the subscriber table.
    $T = new Template(Config::get('pi_path') . 'templates/admin');
    $T->set_file('form', 'clear_sub.thtml');
    $T->set_var(array(
        'action_url' => Config::get('admin_url') . '/index.php',
        'token_name' => CSRF_TOKEN,
        'token_value' => SEC_createToken(),
    ) );
    $T->parse('output', 'form');
    $content .= $T->finish($T->get_var('output'));
    break;

case 'syncfrom_warning':
    // Display a warning confirmation before syncing records from the list provider.
    $T = new Template(Config::get('pi_path') . 'templates/admin');
    $T->set_file('form', 'sync_from_provider.thtml');
    $T->set_var(array(
        'action_url' => Config::get('admin_url') . '/index.php',
        'token_name' => CSRF_TOKEN,
        'token_value' => SEC_createToken(),
    ) );
    $T->parse('output', 'form');
    $content .= $T->finish($T->get_var('output'));
    //$view = 'syncfrom';
    break;

case 'maintenance':
    $API = Mailer\API::getInstance();
    $actions = $API->getMaintenanceLinks();
    $T = new Template(Config::get('pi_path') . 'templates/admin');
    $T->set_file('funcs', 'maintenance.thtml');
    $T->set_var(array(
        'admin_url' => Config::get('admin_url'). '/index.php',
        'provider_name' => $API->getName(),
    ) );
    if (count($actions)) {
        $T->set_var('has_provider_actions', true);
        $T->set_block('funcs', 'ProviderActions', 'Actions');
        foreach ($actions as $action) {
            $T->set_var(array(
                'action' => $action['action'],
                'text' => $action['text'],
                'dscp' => $action['dscp'],
                'style' => $action['style'],
            ) );
            $T->parse('Actions', 'ProviderActions', true);
        }
    }
    $T->parse('output', 'funcs');
    $content .= $T->finish($T->get_var('output'));
    if (isset($action_content)) {
        $content .= $action_content;
    }
    break;
}

$display = Menu::siteHeader($LANG_MLR['mailer_admin']);
$display .= $content;
$display .= Menu::siteFooter();
echo $display;

