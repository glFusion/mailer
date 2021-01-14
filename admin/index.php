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
use Mailer\Models\Mailer;
use Mailer\Models\Queue;
use Mailer\Models\Subscriber;
use Mailer\API;

if (!SEC_hasRights('mailer.admin,mailer.edit', 'OR')) {
    $display = MLR_siteHeader ($LANG_MLR['access_denied']);
    $display .= COM_startBlock ($LANG_MLR['access_denied'], '',
                        COM_getBlockTemplate ('_msg_block', 'header'));
    $display .= $LANG_MLR['access_denied_msg'];
    $display .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
    $display .= MLR_siteFooter ();
    COM_accessLog ("User {$_USER['username']} tried to illegally access the mailers administration screen.");
    echo $display;
    exit;
}

USES_lib_admin();

if (!empty($_POST) && GVERSION < '1.3.0') $_POST = MLR_stripslashes($_POST);


/**
 * List all the saved messages.
 *
 * @return  string      HTML for admin list
 */
function MLR_list_mailers()
{
    global $_CONF, $_TABLES, $_IMAGE_TYPE, $LANG_ADMIN, $LANG_MLR, $_MLR_CONF;

    $retval = '';

    $header_arr = array(      # display 'text' and use table field 'field'
        array(
            'text' => $LANG_ADMIN['edit'],
            'field' => 'edit',
            'sort' => false,
            'align' => 'center',
        ),
        array(
            'text' => $LANG_ADMIN['copy'],
            'field' => 'copy',
            'sort' => false,
            'align' => 'center',
        ),
        array(
            'text' => $LANG_MLR['send'],
            'field' => 'send',
            'sort' => false,
            'align' => 'center',
        ),
        array(
            'text' => $LANG_MLR['mlr_id'],
            'field' => 'mlr_id',
            'sort' => true,
        ),
        array(
            'text' => $LANG_ADMIN['title'],
            'field' => 'mlr_title',
            'sort' => true,
        ),
        array(
            'text' => $LANG_MLR['writtenby'],
            'field' => 'mlr_uid',
            'sort' => false,
        ),
        array(
            'text' => $LANG_MLR['writtenby'],
            'field' => 'mlr_date',
            'sort' => false,
        ),
        array(
            'text' => $LANG_MLR['last_sent'],
            'field' => 'mlr_sent_time',
            'sort' => true,
        ),
        array(
            'text' => $LANG_ADMIN['delete'],
            'field' => 'delete',
            'sort' => false,
            'align' => 'center',
        ),
    );
    $defsort_arr = array(
        'field' => 'mlr_date',
        'direction' => 'desc',
    );

    $menu_arr = array (
        array(
            'url' => Config::get('admin_url') . '/index.php?edit=x',
            'text' => $LANG_ADMIN['create_new'],
        ),
        array(
            'url' => $_CONF['site_admin_url'],
            'text' => $LANG_ADMIN['admin_home'],
        ),
    );

    $retval .= COM_startBlock(
        $LANG_MLR['mailerlist'] . " ({$_MLR_CONF['pi_name']} v. {$_MLR_CONF['pi_version']})",
        '',
        COM_getBlockTemplate('_admin_block','header')
    );

    $retval .= ADMIN_createMenu($menu_arr, $LANG_MLR['instructions'], plugin_geticon_mailer());

    $text_arr = array(
        'has_extras' => true,
        'form_url' => MLR_ADMIN_URL . '/index.php?mailers=x',
    );

    $query_arr = array(
        'table' => 'mailer',
        'sql' => "SELECT *
                FROM {$_TABLES['mailer']}
                WHERE 1=1 " .
                COM_getPermSQL('AND', 0, 3),
        'query_fields' => array('mlr_title', 'mlr_id'),
    );

    $options = array();

    $retval .= ADMIN_list(
        'mailer', 'MLR_getListField_mailer',
        $header_arr, $text_arr, $query_arr, $defsort_arr,
        '', '', $options
    );
    $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));

    return $retval;
}


/**
 * List all the current subscribers.
 *
 * @return  string      HTML for admin list
 */
function MLR_list_subscribers()
{
    global $_CONF, $_TABLES, $_IMAGE_TYPE, $LANG_ADMIN, $LANG_MLR, $_MLR_CONF,
            $LANG01;

    $retval = '<script type="text/javascript" src="' . MLR_URL . 
        '/js/userStatus.js"></script>';

    $header_arr = array(      # display 'text' and use table field 'field'
        array('text' => $LANG_MLR['id'], 'field' => 'id', 'sort' => true),
        array('text' => $LANG_MLR['email'], 'field' => 'email', 'sort' => true),
        array('text' => $LANG_MLR['site_user'], 'field' => 'uid', 'sort' => true),
        array('text' => $LANG_MLR['list_status'], 
                'field' => 'status', 'sort' => false, 'align' => 'center'),
        array('text' => $LANG_ADMIN['delete'], 'field' => 'remove_subscriber', 
                'sort' => false, 'align' => 'center'),
    );
    $defsort_arr = array('field' => 'email', 'direction' => 'asc');
    $menu_arr = array (
        array('url' => MLR_ADMIN_URL . '/index.php?import_form=x',
              'text' => $LANG_MLR['import']),
        array('url' => MLR_ADMIN_URL . '/index.php?import_users_confirm=x',
              'text' => $LANG_MLR['import_current_users']),
        array('url' => MLR_ADMIN_URL . '/index.php?export=x',
              'text' => $LANG_MLR['export']),
        array('url' => MLR_ADMIN_URL . '/index.php?clear_warning=x',
              'text' => $LANG_MLR['clear']),
    );

    $retval .= COM_startBlock($LANG_MLR['subscriberlist'] .
                " ({$_MLR_CONF['pi_name']} v. {$_MLR_CONF['pi_version']})",
                '', COM_getBlockTemplate('_admin_block', 'header'));

    $retval .= ADMIN_createMenu($menu_arr, '', plugin_geticon_mailer());

    $text_arr = array(
        'has_extras' => true,
        'form_url' => MLR_ADMIN_URL . '/index.php?subscribers=x',
    );

    $query_arr = array(
        'table' => 'mailer_emails',
        'sql' => "SELECT ml.*, u.uid, u.fullname 
                FROM {$_TABLES['mailer_emails']} ml
                LEFT JOIN {$_TABLES['users']} u
                    ON ml.email = u.email
                WHERE 1=1 ",
        'query_fields' => array('ml.email', 'u.fullname'),
        'default_filter' => COM_getPermSQL('AND', 0, 3)
    );

    $chkactions ='<input name="delbutton" type="image" src="'
        . $_CONF['layout_url'] . '/images/admin/delete.' . $_IMAGE_TYPE
        . '" style="vertical-align:text-bottom;" title="' . $LANG01[124]
        . '" onclick="return confirm(\'' . $LANG01[125] . '\');"'
        . '/>&nbsp;' . $LANG_ADMIN['delete'] . '&nbsp;';
    $chkactions .= '<input name="blacklist" type="image" src="'
        . MLR_ADMIN_URL . '/images/red.png'
        . '" style="vertical-align:text-bottom;" title="'
        . $LANG_MLR['blacklist']
        . '" onclick="return confirm(\'' . $LANG_MLR['conf_black'] . '\');"'
        . '/>&nbsp;' . $LANG_MLR['blacklist'] . '&nbsp;';
    $chkactions .= '<input name="whitelist" type="image" src="'
        . MLR_ADMIN_URL . '/images/green.png'
        . '" style="vertical-align:text-bottom;" title="'
        . $LANG_MLR['whitelist']
        . '" onclick="return confirm(\'' . $LANG_MLR['conf_white'] . '\');"'
        . '/>&nbsp;' . $LANG_MLR['whitelist'] . '&nbsp;';

    $options = array(
        //'chkdelete' => true,
        'chkselect' => true,
        'chkfield' => 'id',
        'chkname' => 'delsubscriber',
        'chkminimum' => 0,
        'chkall' => true,
        'chkactions' => $chkactions,
    );
 
    $retval .= ADMIN_list('mailer_subscribers', 
            'MLR_getListField_mailer',
            $header_arr, $text_arr, $query_arr, $defsort_arr, '', '', $options);
    $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));

    return $retval;
}


/**
 * List all the currently queued messages.
 *
 * @return  string      HTML for admin list
 */
function MLR_list_queue()
{
    global $_CONF, $_TABLES, $_IMAGE_TYPE, $LANG_ADMIN, $LANG_MLR, $_MLR_CONF;

    $retval = '';

    $header_arr = array(      # display 'text' and use table field 'field'
        array('text' => $LANG_MLR['mlr_id'], 'field' => 'mlr_id', 'sort' => true),
        array('text' => $LANG_MLR['email'], 'field' => 'email', 'sort' => true),
        array('text' => $LANG_ADMIN['delete'], 'field' => 'deletequeue', 'sort' => false),
    );
    $defsort_arr = array('field' => 'mlr_id', 'direction' => 'asc');

    $menu_arr = array (
        array('url' => MLR_ADMIN_URL . '/index.php?purgequeue=x',
              'text' => $LANG_MLR['purge_queue']),
        array('url' => MLR_ADMIN_URL . '/index.php?resetqueue=x',
              'text' => $LANG_MLR['reset_queue']),
        array('url' => MLR_ADMIN_URL . '/index.php?flushqueue=x',
              'text' => $LANG_MLR['flush_queue']),
        array('url' => $_CONF['site_admin_url'],
              'text' => $LANG_ADMIN['admin_home'])
    );

    $retval .= COM_startBlock($LANG_MLR['mailerlist'] .  
                " ({$_MLR_CONF['pi_name']} v. {$_MLR_CONF['pi_version']})",
                '', COM_getBlockTemplate('_admin_block', 'header'));

    $retval .= ADMIN_createMenu($menu_arr, $LANG_MLR['instr_admin_queue'], plugin_geticon_mailer());

    $text_arr = array(
        'has_extras' => true,
        'form_url' => MLR_ADMIN_URL . '/index.php?queue=x'
    );

    $query_arr = array(
        'table' => 'mailer_queue',
        'sql' => "SELECT * FROM {$_TABLES['mailer_queue']} WHERE 1=1 ",
        'query_fields' => array('email', 'mlr_id'),
        'default_filter' => COM_getPermSQL ('AND', 0, 3)
    );

    $options = array();
    $defsort_arr = array('field' => 'ts,email', 'direction' => 'ASC');

    $retval .= ADMIN_list('mailer', 'MLR_getListField_mailer',
                          $header_arr, $text_arr, $query_arr, $defsort_arr,
                        '', '', $options);
    $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));

    return $retval;
}



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

    $T = new Template(MLR_PI_PATH . 'templates/admin');
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


/**
 * Get the display value for a list field, either mailer or subscriber.
 *
 * @param   string  $fieldname      Name of the field
 * @param   string  $fieldvalue     Value of the field
 * @param   array   $A              Array of all field name=>value pairs
 * @param   array   $icon_arr       Array of admin icons
 * @return  string                  Display value for $fieldname
 */
function MLR_getListField_mailer($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ADMIN, $LANG_MLR, $_TABLES, $_IMAGE_TYPE;

    $retval = '';
    static $admin_url = NULL;
    if ($admin_url === NULL) {
        $admin_url = Config::get('admin_url');
    }
    switch($fieldname) {
    case 'edit':
        $retval = COM_createLink(
            $icon_arr['edit'],
            $admin_url . "/index.php?edit=x&amp;mlr_id={$A['mlr_id']}"
        );
        break;

    case 'copy':
        $retval = COM_createLink(
            $icon_arr['copy'],
            $admin_url . "/index.php?clone=x&amp;mlr_id={$A['mlr_id']}"
        );
        break;

    case 'send':
        $retval = COM_createLink(
            "<img src=\"{$_CONF['layout_url']}/images/admin/mail.png\" 
            height=\"16\" width=\"16\" border=\"0\"
            onclick=\"return confirm('{$LANG_MLR['conf_sendnow']}');\">",
            $admin_url . "/index.php?sendnow=x&amp;mlr_id={$A['mlr_id']}"
        );
        break;

    case 'delete':
        $retval = COM_createLink(
            "<img src=\"{$_CONF['layout_url']}/images/admin/delete.png\" 
            height=\"16\" width=\"16\" border=\"0\"
            onclick=\"return confirm('{$LANG_MLR['conf_delete']}');\">",
            $admin_url . "/index.php?delete=x&amp;mlr_id={$A['mlr_id']}"
        );
        break;

    case 'deletequeue':     // Delete an entry from the queue
        $retval = COM_createLink(
            "<img src=\"{$_CONF['layout_url']}/images/admin/delete.png\" 
            height=\"16\" width=\"16\" border=\"0\"
            onclick=\"return confirm('Do you really want to delete this item?');\">",
            $admin_url . "/index.php?deletequeue=x&amp;mlr_id={$A['mlr_id']}&amp;email={$A['email']}"
        );
        break;

    case 'remove_subscriber':
        $retval = COM_createLink(
            "<img src=\"{$_CONF['layout_url']}/images/admin/delete.png\" 
            height=\"16\" width=\"16\" border=\"0\"
            onclick=\"return confirm('Do you really want to delete this item?');\">",
            $admin_url . "/index.php?delsubscriber=x&amp;sub_id={$A['id']}"
        );
        break;

    case 'status':
        $icon1_cls = 'uk-icon-circle-o';
        $icon2_cls = 'uk-icon-circle-o';
        $icon3_cls = 'uk-icon-circle-o';
        $onclick1 = "onclick='MLR_toggleUserStatus(\"" . Status::ACTIVE . 
                "\", \"{$A['id']}\");' ";
        $onclick2 = "onclick='MLR_toggleUserStatus(\"" . Status::PENDING .
                "\", \"{$A['id']}\");' ";
        $onclick3 = "onclick='MLR_toggleUserStatus(\"" . Status::BLACKLIST . 
                "\", \"{$A['id']}\");' ";
        switch ($fieldvalue) {
        case Status::PENDING:
            $icon2_cls = 'uk-icon-circle uk-text-warning';
            $onclick2 = '';
            break;
        case Status::ACTIVE:
            $icon1_cls = 'uk-icon-circle uk-text-success';
            $onclick1 = '';
            break;
        case Status::BLACKLIST:
            $icon3_cls = 'uk-icon-circle uk-text-danger';
            $onclick3 = '';
            break;
        default:
            break;
        }
        $retval = '<div id="userstatus' . $A['id']. '">' .
            '<i class="uk-icon ' . $icon1_cls . '" ' .
            $onclick1 . '/></i>&nbsp;';
        $retval .= '<i class="uk-icon ' . $icon2_cls . '" ' .
            $onclick2 . '/></i>&nbsp;';
        $retval .= '<i class="uk-icon ' . $icon3_cls . '" ' .
            $onclick3 . '/></i>';
        $retval .= '</div>';
        break;

    case 'mlr_title':
        $url = COM_buildUrl(
            $_CONF['site_url'] . '/mailer/index.php?page=' . $A['mlr_id']
        );
        $retval = COM_createLink(
            $A['mlr_title'], 
            $url, 
            array('title'=>$LANG_MLR['title_display'])
        );
        break;

    case 'mlr_uid':
        $retval = COM_getDisplayName ($A['mlr_uid']);
        break;

    case 'mlr_centerblock':
        if ($A['mlr_centerblock']) {
            switch ($A['mlr_where']) {
            case '1': $where = $LANG_MLR['centerblock_top']; break;
            case '2': $where = $LANG_MLR['centerblock_feat']; break;
            case '3': $where = $LANG_MLR['centerblock_bottom']; break;
            default:  $where = $LANG_MLR['centerblock_entire']; break;
            }
            $retval = $where;
        } else {
            $retval = $LANG_MLR['centerblock_no'];
        }
        break;

    case 'mlr_sent_time':
        if ($fieldvalue == 0) {
            $retval = $LANG_MLR['never'];
        } else {
            $retval = strftime($_CONF['daytime'], $fieldvalue);
        }
        break;

    case 'uid':
        if (!empty($A['uid'])) {
            $retval = COM_createLink(COM_getDisplayName($A['uid']),
                $_CONF['site_url'].'/users.php?mode=profile&uid=' . $A['uid']) .
                ' (' . $A['uid'] . ')';
        }
        break;
    default:
        $retval = $fieldvalue;
        break;
    }

    return $retval;
}


// MAIN
$expected = array(
    'blacklist_x', 'whitelist_x', 'delsubscriber',
    'mailers', 'subscribers', 'queue', 
    'edit', 'clone', 'mlr_save', 
    'delete', 'sendnow',
    'deletequeue', 'purgequeue', 'resetqueue', 'flushqueue',
    'clear_warning', 'clearsub',
    'import_form', 'import_users', 'import_users_confirm', 'import', 'export',
);
$action = 'mailers';
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
case 'sendnow':
    $view = 'mailers';
    $mld_id = COM_sanitizeID($_GET['mlr_id'], false);
    if (empty($mlr_id)) break;
    Queue::addMailer($mlr_id);
    break;

case 'delsubscriber':
    if (is_array($_POST['delsubscriber'])) {
        $del_subs = array();
        foreach ($_POST['delsubscriber'] as $idx=>$sub_id) {
            $del_subs[] = (int)$sub_id;
        }
        $sub_list = join(',', $del_subs);
        $sql = "DELETE FROM {$_TABLES['mailer_emails']}
            WHERE id IN ($sub_list)";
        DB_query($sql);
    } else {
        $del_sub = (int)$_REQUEST['sub_id'];
        if ($del_sub > 0) {
            DB_delete($_TABLES['mailer_emails'], 'id', $del_sub);
        }
    }
    $view = 'subscribers';
    break;

case 'blacklist_x':
    if (is_array($_POST['delsubscriber'])) {
        foreach ($_POST['delsubscriber'] as $id) {
            Subscriber::setStatus($id, Status::BLACKLIST);
        }
    }
    $view = 'subscribers';
    break;

case 'whitelist_x':
    if (is_array($_POST['delsubscriber'])) {
        foreach ($_POST['delsubscriber'] as $id) {
            Subscriber::setStatus($id, Status::ACTIVE);
        }
    }
    $view = 'subscribers';
    break;

case 'clearsub':
    if (SEC_checkToken()) {
        DB_query("TRUNCATE {$_TABLES['mailer_emails']}");
    }
    $view = 'subscribers';
    break;

case 'edit':
    $view = 'edit';
    break;

case 'clone':
    $M = new Mailer($mlr_id);
    if ($M->isNew)      // can't clone a non-existant mailer
        break;
    $M->mlr_id = COM_makeSid();
    $M->mlr_title = $M->mlr_title . ' -Copy';
    $M->unixdate = time();
    $M->mlr_date = date('Y-m-d H:i;s');
    $M->isNew = true;
    $status = $M->Save();
    $view = 'mailers';
    break;

case 'delete':
    (new Mailer($mlr_id))->Delete();
    COM_refresh(Config::get('admin_url') . '/index.php?mailes');
    $view = 'mailers';
    break;
 
case 'import_form':
    $view = 'import_form';
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
                case MLR_ADD_SUCCESS:
                    $results['success']++;
                    break;
                case MLR_ADD_INVALID:
                    $results['invalid']++;
                    break;
                case MLR_ADD_EXISTS:
                    $results['duplicate']++;
                    break;
                case MLR_ADD_ERROR:
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
    $view = 'subscribers';
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
    $sql = "SELECT email FROM {$_TABLES['mailer_emails']}";
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


case 'mode':
echo 'DEPRECATED: mode';break;
    switch ($actionval) {
    case $LANG_ADMIN['delete']:
        if (!empty ($LANG_ADMIN['delete']) && SEC_checkToken()) {
            if (empty ($mlr_id) || (is_numeric ($mlr_id) && ($mlr_id == 0))) {
                COM_errorLog ('Attempted to delete mailer mlr_id=' . $mlr_id);
            } else {
                $args = array('mlr_id' => $mlr_id);
                MLR_invokeService('mailer', 'delete', $args, $display, $svc_msg);
            }
        }
        break;

    case 'import':
        if (SEC_checkToken()) {
            $display .= MLR_siteHeader($LANG_MLR['importer']);
            $display .= COM_startBlock($LANG_MLR['importer']);
            $display .= MLR_import($_POST['import_list'], $_POST['delimiter'], 
                            (isset($_POST['blacklist']) && $_POST['blacklist'] != '')?1:0);
            $display .= COM_endBlock();
            $display .= MLR_siteFooter();
            $display .= COM_refresh ($_CONF['site_admin_url'] . '/plugins/mailer/index.php');
        }
        break;

    case 'import_users':
        $display .= MLR_siteHeader($LANG_MLR['importer']);
        $display .= COM_startBlock($LANG_MLR['importer']);
        $display .= Subscriber::importUsers();
        $display .= COM_endBlock();
        $display .= MLR_siteFooter();
        $display .= COM_refresh ($_CONF['site_admin_url'] . '/plugins/mailer/index.php');
        break;

    case 'remove_subscriber':
        $success = MLR_unsubscribe($email);
        if ($success){
            $display = MLR_siteHeader();
            $display .= COM_startBlock($LANG_MLR['removed_title']);
            $display .= sprintf($LANG_MLR['removed_msg'], $email);
            $display .= COM_endBlock();
            $display .= MLR_siteFooter();
            $display .= COM_refresh ($_CONF['site_admin_url'] . '/plugins/mailer/index.php');
        }
        break;

    case 'clear_warning':
        $display = MLR_siteHeader();
        $display .= COM_startBlock($LANG_MLR['are_you_sure']);
        //require_once $_CONF['path_system'].'lib-admin.php';
        $menu_arr = array(array('url'=>$_CONF['site_admin_url'], 'text'=>$LANG_ADMIN['admin_home']),
                array('url' => $_CONF['site_admin_url'] . '/plugins/mailer/index.php?mode=export',
                'text' => $LANG_MLR['export']), array('url'=>'javascript:back()', 'text'=>'Back'));
        list($blackListEmails, $blackListDomains) = MLR_fetchBlacklist();
        list($emails) = MLR_fetchWhitelist();
        $display .= ADMIN_createMenu($menu_arr, '', plugin_geticon_mailer());
        $display .= $LANG_MLR['blacklisted_emails'] . 
                    implode(',', $blackListEmails)."<br />";
        $display .= $LANG_MLR['blacklisted_domains'] . 
                    implode(',', $blackListDomains)."<br />";
        $display .= $LANG_MLR['whitelisted_emails'] .
                    wordwrap(implode(',', $emails), 120, "<br />", true);
        $display .= '<form action="' . Config::get('admin_url') . 
            'index.php" method="post"><input type="submit" value="' . 
            $LANG_MLR['clear'] . 
            '" name="iclearsub"/><input type="hidden" name="' . CSRF_TOKEN .
            '" value="'.SEC_createToken().'"/></form>';
        $display .= COM_endBlock();
        $display .= MLR_siteFooter();
        break;

    case $LANG_MLR['clear']:
        if (SEC_checkToken()) {
            $display = MLR_truncateList();
            $display .= COM_refresh ($_CONF['site_admin_url'] . '/plugins/mailer/index.php');
        }
        break;

    case $LANG_ADMIN['save']:
        if (!empty ($LANG_ADMIN['save']) && SEC_checkToken()) {
            if (!empty ($mlr_id)) {
                if (!isset ($_POST['mlr_onmenu'])) {
                    $_POST['mlr_onmenu'] = '';
                }
                if (!isset ($_POST['mlr_php'])) {
                    $_POST['mlr_php'] = '';
                }
                if (!isset ($_POST['mlr_nf'])) {
                    $_POST['mlr_nf'] = '';
                }
                if (!isset ($_POST['mlr_centerblock'])) {
                    $_POST['mlr_centerblock'] = '';
                }
                $help = '';
                if (isset ($_POST['mlr_help'])) {
                    $mlr_help = COM_sanitizeUrl ($_POST['mlr_help'], array ('http', 'https'));
                }
                if (!isset ($_POST['mlr_inblock'])) {
                    $_POST['mlr_inblock'] = '';
                }
                $mlr_uid = COM_applyFilter ($_POST['mlr_uid'], true);
                if ($mlr_uid == 0) {
                    $mlr_uid = $_USER['uid'];
                }
                if (!isset ($_POST['postmode'])) {
                    $_POST['postmode'] = '';
                }
                $display .= submitmailer ($mlr_id, $mlr_uid, $_POST['mlr_title'],
                    $_POST['mlr_content'], COM_applyFilter ($_POST['mlr_hits'], true),
                    COM_applyFilter ($_POST['mlr_format']), $_POST['mlr_onmenu'],
                    $_POST['mlr_label'], COM_applyFilter ($_POST['commentcode'], true),
                    COM_applyFilter ($_POST['owner_id'], true),
                    COM_applyFilter ($_POST['group_id'], true), $_POST['perm_owner'],
                    $_POST['perm_group'], $_POST['perm_members'], $_POST['perm_anon'],
                    $_POST['mlr_php'], $_POST['mlr_nf'],
                    COM_applyFilter ($_POST['mlr_old_id']), $_POST['mlr_centerblock'],
                    $mlr_help, COM_applyFilter ($_POST['mlr_tid']),
                    COM_applyFilter ($_POST['mlr_where'], true), $_POST['mlr_inblock'],
                    COM_applyFilter ($_POST['postmode']), $_POST['mlr_sent_time']);
                if (isset($_POST['mlr_sendnow'])) {
                    //$display .= MLR_mailIt($mlr_id, $_POST['mlr_title'], $_POST['mlr_content']);
                    $display .= MLR_queueIt($mlr_id, $_POST['mlr_title'], $_POST['mlr_content']);
                }
                if (isset($_POST['mlr_sendtest'])) {
                    //$display .= MLR_mailIt($mlr_id, $_POST['mlr_title'], $_POST['mlr_content'], true);
                    
                }
            } else {
                $display = COM_refresh ($_CONF['site_admin_url'] . '/index.php');
            }
        }
        break;

    case 'listsubscribers':
    case 'listmailers':
    default:
        $view = $actionval;
        break;
    }
    break;

case 'mlr_save':
    $mlr_old_id = isset($_POST['mlr_old_id']) ? $_POST['mlr_old_id'] : '';
    $M = new Mailer($mlr_old_id);
    $status = $M->Save($_POST);
    if (!$status) {
        $content .= MLR_errorMsg('<ul>' . $M->PrintErrors() . '</ul>');
        $content .= $M->Edit();
        $view = 'none';     // Editing it here, no other display
    } else {
        $view = 'mailers';  // Success, return to list
    }
    break;

case 'deletequeue':     // delete an item from the queue
    if (isset($_GET['email']) && !empty($_GET['email']) && !empty($mlr_id)) {
        Queue::deleteEmail($mlr_id, $_GET['email']);
    }
    COM_refresh(Config::get('admin_url') . '/index.php?queues');
    break;

case 'purgequeue':
    Queue::purge();
    COM_refresh(Config::get('admin_url') . '/index.php?queues');
    break;

case 'resetqueue':
    Queue::reset();
    COM_refresh(Config::get('admin_url') . '/index.php?queues');
    break;

case 'flushqueue':
    Queue::process(true);
    COM_refresh(Config::get('admin_url') . '/index.php?queues');
    break;

case 'subscribers':
case 'mailers':
case 'queue':
default:
    $view = $action;
    break;

}

// Now create the content to be displayed
switch ($view) {
case 'edit':
    $M = new Mailer($mlr_id);
    $content .= $M->Edit();
    break;

case 'mailers':
case 'subscribers';
case 'queue':
    USES_class_navbar();
    $features = API::getInstance()->getFeatures();
    $navmenu = new navbar;
    foreach ($features as $feature) {
        $navmenu->add_menuitem(
            $LANG_MLR[$feature],
            Config::get('admin_url'). '/index.php?' . $feature
        );
    }
/*    if (in_array('mailers')) {
        $navmenu->add_menuitem('Mailers', Config::get('admin_url'). '/index.php?mailers=x');
    }
    if (in_array($features, 'subscribers')) {
        $navmenu->add_menuitem('Subscribers', Config::get('admin_url') . '/index.php?subscribers=x');
    }
    if (in_array($features, 'queues')) {
        $navmenu->add_menuitem('Queue', Config::get('admin_url') . '/index.php?queue=x');
    }*/
    if ($view == 'mailers') {
        $content = Mailer::adminList();
        $navmenu->set_selected('Mailers');
    } elseif ($view == 'queue') {
        $content = Queue::adminList();
        $navmenu->set_selected('Queue');
    } else {
        $content .= Subscriber::adminList();
        $navmenu->set_selected('Subscribers');
    }
 
    $content = $navmenu->generate() . $content;
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
    $content .= COM_startBlock($LANG_MLR['are_you_sure']);
    $menu_arr = array(
        array(
            'url' => $_CONF['site_admin_url'],
            'text'=>$LANG_ADMIN['admin_home'],
        ),
        array(
            'url' => Config::get('admin_url'). '/index.php?export=x',
            'text' => $LANG_MLR['export'],
        ), 
        array(
            'url'=>'javascript:back()',
            'text'=>'Back',
        )
    );
    list($blackListEmails, $blackListDomains) = MLR_fetchBlacklist();
    list($emails) = MLR_fetchWhitelist();
    $content .= ADMIN_createMenu($menu_arr, '', plugin_geticon_mailer());
    $content .= $LANG_MLR['blacklisted_emails'] .
                implode(',', $blackListEmails)."<br />";
    $content .= $LANG_MLR['blacklisted_domains'] .
                implode(',', $blackListDomains)."<br />";
    $content .= $LANG_MLR['whitelisted_emails'] .
                wordwrap(implode(',', $emails), 120, "<br />", true);
    $content .= '<form action="' . Config::get('admin_url') . 
            '/index.php" method="post"><input type="submit" value="' . 
            $LANG_MLR['clear'] . 
            '" name="clearsub"/><input type="hidden" name="' . CSRF_TOKEN .
            '" value="'.SEC_createToken().'"/></form>';
    $content .= COM_endBlock();
    break;

}

$display = MLR_siteHeader($LANG_MLR['mailer_admin']);
if (!empty($msg)) {
    $display .= COM_showMessagetext($msg, '', true);
}
$display .= $content;
$display .= MLR_siteFooter();
echo $display;

?>
