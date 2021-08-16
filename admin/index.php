<?php
/**
 * Administrative entry point for the Mailer plugin.
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

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
use Mailer\Config;
use Mailer\Models\Status;
use Mailer\Models\Campaign;
use Mailer\Models\Queue;
use Mailer\Models\Subscriber;
use Mailer\API;
use Mailer\Menu;

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

USES_lib_admin();


/**
 * Display the form for importing a comma-separated list of users.
 *
 * @return  string      HTML for import form
 */
function MLR_display_import_form()
{
    global $LANG_MLR, $LANG_ADMIN, $_CONF;

    $retval = COM_startBlock($LANG_MLR['importer']);
    $menu_arr = array(
        array('url'=>$_CONF['site_admin_url'],
                'text'=>$LANG_ADMIN['admin_home']),
        array('url'=>'javascript:back()', 'text'=>'Back'),
    );
    $retval .= ADMIN_createMenu($menu_arr, '', plugin_geticon_mailer());

    $T = new Template(Config::get('pi_path') . '/templates/admin');
    $T->set_file('form', 'import.thtml');
    $T->set_var(array(
        //'lang_import'       => $LANG_MLR['import'],
        'lang_import_temp_text' => $LANG_MLR['import_temp_text'],
        'lang_delimiter'    => $LANG_MLR['delimiter'],
        'lang_blacklist'    => $LANG_MLR['import_checkbox'],
        //'lang_cancel'       => $LANG_ADMIN['cancel'],
        'gltoken_name'      => CSRF_TOKEN,
        'gltoken'           => SEC_createToken(),
    ) );
    return $T->parse('output','form');
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
    'import_form', 'import_users', 'import_users_confirm', 'import', 'export',
    // views
    'campaigns', 'subscribers', 'queue',
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

switch ($action) {
case 'api_action':
    // Perform actions for the current API
    $API = Mailer\API::getInstance();
    $content = $API->handleActions(array(
        'get' => $_GET,
        'post' => $_POST,
    ) );
    COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
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
    if (SEC_checkToken()) {
        DB_query("TRUNCATE {$_TABLES['mailer_subscribers']}");
    }
    COM_refresh(Config::get('admin_url') . '/index.php?subscribers');
    $view = 'subscribers';
    break;

case 'clone':
    $M = new Campaign($mlr_id);
    if ($M->isNew()) {     // can't clone a non-existant mailer
        $view = 'campaigns';
        break;
    }
    $status = $M->withID('')
                ->Save();
    COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'delete':
    (new Campaign($mlr_id))->Delete();
    COM_refresh(Config::get('admin_url') . '/index.php?campaigns');
    break;

case 'import':
    $list = explode($_POST['delimiter'], $_POST['import_list']);
    $status = isset($_POST['blacklist']) && $_POST['blacklist'] == 1 ?
            Status::BLACKLIST : Status::ACTIVE;
    if (is_array($list)) {
        $results = array(
            'success' => 0,
            'error' => 0,
            'invalid' => 0,
            'duplicate' => 0,
        );
        foreach($list as $email){
            if (!empty($email)) {
                $Sub = new Subscriber;
                $response = $Sub->withEmail(trim($email))
                    ->withStatus($status)
                    ->subscribe();

                //$status = MLR_addEmail(trim($email), $status);
                switch ($response) {
                case Status::SUB_SUCCESS:
                    $results['success']++;
                    break;
                case Status::SUB_INVALID:
                    $results['invalid']++;
                    break;
                case Status::SUB_EXISTS:
                    $results['duplicate']++;
                    break;
                case Status::SUB__ERROR:
                    $results['error']++;
                break;
                }
            }
        }
        $msg = '';
        foreach ($results as $key => $value) {
            if ($value > 0) {
                $msg .= '<li>' . $LANG_MLR[$key] . ': ' . $value . '</li>' . LB;
            }
        }
        if (!empty($msg)) $msg = '<ul>' . $msg . '</ul>' . LB;
    }
    COM_refresh(Config::get('admin_url') . '/index.php?subscribers');
    break;

case 'import_users':
    $sql = "SELECT `email` FROM {$_TABLES['users']}";
    $result = DB_query($sql);
    $Sub = (new Subscriber)->withStatus(Status::ACTIVE);
    while ($A = DB_fetchArray($result)) {
        if ($A['email'] != ''){
            //MLR_addEmail($A['email'], Status::ACTIVE);
            $Sub->withEmail($A['email'])
                ->withRegDate()
                ->withToken(uniqid())
                ->subscribe(Status::ACTIVE);
        }
    }
    $view = 'subscribers';
    break;

case 'export':
    $list = array();
    $sql = "SELECT email FROM {$_TABLES['mailer_subscribers']}";
    $result = DB_query( $sql );
    while ( $A = DB_fetchArray( $result ) ) {
        $list[] = strtolower($A['email']);
    }
    $export_list = implode(",", $list);

    //echo header('Content-type: text/csv');
    echo header("Content-type: text/plain");
    echo header('Content-Disposition: attachment; filename="mailer_email_export.txt"');
    echo $export_list;
    exit;
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
        $content .= MLR_errorMsg('<ul>' . $M->PrintErrors() . '</ul>');
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

case 'import_form':
    $content .= MLR_display_import_form();
    break;

case 'import_users_confirm':
    // Confirm the import of all site users
    $content .= '<form action="' . $_SERVER['PHP_SELF'] . '" method="POST">' . LB;
    $content .= $LANG_MLR['import_users_confirm'] . '<br />' . LB;
    $content .= '<input type="submit" name="import_users" value="' .
            $LANG_ACCESS['yes'] . '" />' . LB;
    $content .= '<input type="submit" name="subscribers" value="' .
            $LANG_ACCESS['no'] . '" />' . LB;
    $content .= '</form>';
    break;

case 'clear_warning':
    // Display a warning confirmation before clearing the subscriber table.
    $T = new Template(Config::get('pi_path') . '/templates/admin');
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
    $T = new Template(Config::get('pi_path') . '/templates/admin');
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
}

$display = Menu::siteHeader($LANG_MLR['mailer_admin']);
$display .= $content;
$display .= Menu::siteFooter();
echo $display;

?>
