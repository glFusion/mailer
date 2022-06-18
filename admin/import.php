<?php
/**
 * Import subscribers from site groups or a CSV file.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.2.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion libraries */
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
use glFusion\Database\Database;
use glFusion\Log\Log;
use Mailer\Config;

// Make sure both plugins are installed and enabled
if (!in_array('mailer', $_PLUGINS)) {
    COM_404();
}

// Only let admin users access this page
if (!plugin_ismoderator_mailer()) {
    // Someone is trying to illegally access this page
    Log::write('system', Log::ERROR,
        "Someone has tried to illegally access the Membership Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR"
    );
    COM_404();
    exit;
}

$action = '';
$content = '';
$txt = '';
$expected = array(
    // Actions to perform
    'do_import', 'export',
    // Views to display
    'import_form',
);
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

switch ($action) {
case 'export':
    $list = Mailer\Util\Importers\CSV::do_export();
    if ($list !== NULL) {
        //echo header('Content-type: text/csv');
        echo header("Content-type: text/plain");
        echo header('Content-Disposition: attachment; filename="mailer_email_export.txt"');
        echo $list;
        exit;
    } else {
        COM_setMsg($LANG_MLR['action_failed'], 'error', true);
        echo COM_refresh(Config::get('admin_url') . '/index.php?subscribers');
    }

case 'do_import':
    switch ($_POST['import_type']) {
    case 'csv':
        $txt = (new Mailer\Util\Importers\CSV)->do_import();
        break;
    case 'glfusion':
        $gl_grp_id = (int)$_POST['from_glfusion'];
        $txt = (new Mailer\Util\Importers\glFusion)->do_import($gl_grp_id);
        break;
    }
    // Fall through to show the form with the output text below it.

case 'import_form':
default:
    $T = new Template(Config::get('pi_path') . '/templates/admin/');
    $T->set_file(array(
        'form' => 'import_form.thtml',
        //'tips' => '../tooltipster.thtml',
    ) );
    $T->set_var(array(
        'plan_sel' => COM_optionList($_TABLES['membership_plans'], 'plan_id,name', '', 1),
        'glfusion_opts' => COM_optionList($_TABLES['groups'], 'grp_id,grp_name', '', 1),
        //'subscription_opts' => COM_optionList($_TABLES['subscr_products'], 'item_id,short_description', '', 1),
        //'doc_url' => MEMBERSHIP_getDocURL('import.html', $_CONF['language']),
        'output_text' => $txt,
    ) );
    //$T->parse('tooltipster_js', 'tips');
    $T->parse('output', 'form');
    $content .= $T->finish ($T->get_var('output'));
    break;
}
$output = Mailer\Menu::siteHeader($LANG_MLR['mailer_admin']);
$output .= Mailer\Menu::Admin('import');
$output .= $content;
$output .= Mailer\Menu::siteFooter();
echo $output;

