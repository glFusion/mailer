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
 *
 * Based on the Newsletter plugin for Geeklog (1.0.8) by Wayne Patterson
 */

if (!defined ('GVERSION')) {
    die('This file can not be used on its own.');
}

spl_autoload_register(function ($class)
{
    // project-specific namespace prefix
    $prefix = 'Mailer\\';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/classes/';

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.class.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
use Mailer\Config;
use Mailer\Models\Subscriber;
use Mailer\Models\Campaign;
use Mailer\Models\Status;
use Mailer\Models\Queue;
use glFusion\Database\Database;
use glFusion\Log\Log;
Config::getInstance();

/**
 * Language file include.
 */
$lang = $_CONF['language'];
$langfile = __DIR__ . '/language/' . $lang . '.php';
if (file_exists($langfile)) {
    require_once $langfile;
} else {
    $lang = 'english';
    require_once __DIR__ . '/language/english_utf-8.php';
}
if (file_exists(__DIR__ . '/language/custom/' . $lang. '.php')) {
    // Include custom language strings, if available
    include_once __DIR__ . '/language/custom/' . $lang. '.php';
}

require_once __DIR__ . '/mailer.php';
if (is_file(__DIR__ . '/config.php')) {
    include_once __DIR__ . '/config.php';
}

Log::config('mailer',
    array(
      'type'  => 'file',
      'path'  => $_CONF['path_log'],
      'file'  => 'mailer.log',
      'level' => Config::get('log_level'),
    )
);

/**
 * This will put an option for mailer in the command and control block.
 *
 * @return  mixed   Array(text, url, icon) or false if not allowed
 */
function plugin_cclabel_mailer()
{
    global $LANG_MLR;

    if (plugin_ismoderator_mailer()) {
        return array(
            $LANG_MLR['mailer'],
            Config::get('admin_url') . '/index.php',
            plugin_geticon_mailer()
        );
    } else {
        return false;
    }
}


/**
 * Return the current version of code.
 * Used by the plugin admin to determine if an upgrade needs to be done.
 *
 * @return  string      Current code version
 */
function plugin_chkVersion_mailer()
{
    return Config::get('pi_version');
}


/**
 * Check if comments are supported. They aren't.
 *
 * @return  boolean     False
 */
function plugin_commentsupport_mailer()
{
    return false;
}


/**
 * See if this is an admin or moderator.
 *
 * @return  boolean     True for admins, False for regular users
 */
function plugin_ismoderator_mailer()
{
    static $isMod = NULL;
    if ($isMod === NULL) {
        $isMod = SEC_hasRights('mailer.admin,mailer.edit', 'OR');
    }
    return $isMod;
}


/**
 * Get the administrative menu option for this plugin.
 *
 * @return  array   Text=>Url for menu option
 */
function plugin_getadminoption_mailer()
{
    global $_CONF, $_TABLES, $LANG_MLR;

    if (plugin_ismoderator_mailer()) {
        $db = Database::getInstance();
        $stmt = $db->conn->executeQuery(
            "SELECT count(*) AS cnt
            FROM {$_TABLES['mailer_campaigns']}" .
            $db->getPermSQL('WHERE', 0, 3)
        );
        $A = $stmt->fetch(Database::ASSOCIATIVE);
        return array(
            $LANG_MLR['mailer'],
            Config::get('admin_url') . '/index.php',
            (int)$A['cnt']
        );
    }
}


/**
 * Action when an new user is created.
 * Subscribe to the default list if so configured.
 *
 * @param  integer $uid    User id of the new user account
 */
function plugin_user_create_mailer($uid)
{
    if (isset($_POST['mailer_subscribe']) && $_POST['mailer_subscribe'] == 1) {
        $Sub = Subscriber::getByUid((int)$uid); // Get the subscriber in case it exists
        if ($Sub->subscribe()) {
            $Sub->Save();
        }
    }
}


/**
 * Unsubscribe deleted users and update campaigns owned by them.
 *
 * @param   integer $uid    User id of deleted user
 */
function plugin_user_delete_mailer($uid)
{
    global $_TABLES;

    // First, unsubscribe the user from any lists, if configured
    if (Config::get('del_user_unsub')) {
        $Sub = Subscriber::getByUid($uid);
        $Sub->unsubscribe();
    }

    Campaign::changeOwner($uid);
}


/**
 * Create an edit form in the Account Settings section.
 * Implemented in glFusion 1.1.5
 *
 * @param   integer $uid        User ID
 * @param   string  $panel      Panel being populated
 * @param   string  $fieldset   Name of fieldset being populated
 * @return  string              HTML for edit form. Never NULL.
 */
function plugin_profileedit_mailer($uid, $panel, $fieldset)
{
    global $_TABLES;

    $retval = '';

    if ($panel == 'privacy' && $fieldset == 'privacy') {
        $chk = '';
        $Sub = Subscriber::getByUid($uid);
        $oldsub = 0;
        if ($Sub->getUid() > 1) {
            if ($Sub->getStatus() == Status::BLACKLIST) {
                // Blacklisted, don't show the option at all
                return $retval;
            } elseif ($Sub->getStatus() == Status::ACTIVE) {
                $chk = 'checked="checked"';
                $oldsub = 1;
            }
        }
        $T = new Template(__DIR__ . '/templates/');
        $T->set_file('profile', 'profile.thtml');
        $T->set_var(array(
            'mailer_checked' => $chk,
            'mailer_oldsub' => $oldsub,
            'mailer_old_email' => $Sub->getEmail(),
            'mailer_old_fullname' => $Sub->getFullname(),
        ) );
        $T->parse('output', 'profile');
        $retval .= $T->finish($T->get_var('output'));
    }
    return $retval;
}


/**
 * Save the profile information when edited via Account Settings.
 *
 * @param   integer $uid    User ID being saved
 */
function plugin_profilesave_mailer($uid=0)
{
    global $_USER, $_TABLES;

    if ($uid == 0) {
        $uid = (int)$_USER['uid'];
    } else {
        $uid = (int)$uid;
    }

    $Sub = Subscriber::getByUid($uid);
    $Sub->profileUpdated($_POST);
    if ($Sub->getID() == 0) {
        $Sub->Save();       // make sure the record is created
    }

    if (
        isset($_POST['mailer_subscribe']) &&
        (!isset($_POST['mailer_oldsub']) || empty($_POST['mailer_oldsub']))
    ) {
        // User requesting addition, double opt-in not needed
        if (Config::get('dbl_optin_members')) {
            $Sub->subscribe();
        } else {
            // Not requiring double-optin for members
            $Sub->subscribe(Status::ACTIVE);
        }
    } elseif (
        !isset($_POST['mailer_subscribe']) &&
        (isset($_POST['mailer_oldsub']) && !empty($_POST['mailer_oldsub']))
    ) {
        // subscription checkbox not checked but was previously
        $Sub->unsubscribe();
    }
}


/**
 * Take action when another plugin has saved an item.
 * This expectes to update subscriber info, so will only act if
 * `merge_fields` is provided.
 *
 * @param   string  $id     Item ID
 * @param   string  $type   Item Type (plugin name)
 * @param   string  $old_id Old item ID, in case it was changed
 */
function plugin_itemsaved_mailer($id, $type, $old_id = '')
{
    $contentInfo = PLG_getItemInfo(
        $type,
        $id,
        'id,status,merge_fields',
        2
    );

    if (
        $contentInfo &&
        !empty($contentInfo['id']) &&
        (!empty($contentInfo['status']) || !empty($contentInfo['merge_fields']))
    ) {
        $Sub = Subscriber::getByUid($contentInfo['id']);
        if ($Sub->getId() > 0 && $Sub->getEmail() != '') {
            $Sub->update();
        }
    }
}


/**
 * Actions to perform when the plugin is enabled or disabled.
 *
 * @param   boolean $enable true = we're being enabled, false = disabled
 */
function plugin_enablestatechange_mailer ($enable)
{
    global $_TABLES;

    $is_enabled = $enable ? 1 : 0;
    $db = Database::getInstance();

    // toggle Mailer blocks
    $db->conn->executeQuery(
        "UPDATE {$_TABLES['blocks']}
        SET is_enabled = ?
        WHERE type = 'phpblock'
        AND phpblockfn LIKE 'phpblock_mailer%'",
        array($is_enabled),
        array(Database::INTEGER)
    );
}


/**
 * Automatic uninstallation function for plugins.
 *
 * @return   array
 */
function plugin_autouninstall_mailer ()
{
    $out = array (
        // give the name of the tables, without $_TABLES[]
        'tables' => array(
            'mailer_campaigns',
            'mailer_subscribers',
            'mailer_queue',
            'mailer_txn',
            'mailer_provider_campaigns',
        ),
        // give the full name of the group, as in the db
        'groups' => array('Mailer Admin'),
        // give the full name of the feature, as in the db
        'features' => array('mailer.edit', 'mailer.delete'),
        // give the full name of the block, including 'phpblock_', etc
        'php_blocks' => array('phpblock_mailer'),
        // give all vars with their name
        'vars'=> array('mailer_lastrun'),
        // give the full name of the plugins config data
        'config_data' => array('mailer')
    );
    return $out;
}


/**
 * Create a block for entering an email address to subscribe.
 * Always shown for Anonymous users, but only for members if they're not
 * already subscribed.
 *
 * @return  string      HTML for subscription block
 */
function phpblock_mailer()
{
    global $_CONF, $LANG_MLR, $_USER, $_TABLES;

    if (!Config::get('blk_show_subs') && !COM_isAnonUser()) {
        // Anon always sees the block, no way to know if they're subscribed
        $db = Database::getInstance();
        $c = $db->getCount($_TABLES['mailer_subscribers'], 'email', $_USER['email']);
        if ($c > 0) return '';
    }

    $T = new Template(__DIR__ . '/templates/');
    $T->set_file('mailer_block', 'block.thtml');
    $T->set_var(array(
        'block_text' => sprintf($LANG_MLR['block_text'], $_CONF['site_url']),
        'block_button_text' => $LANG_MLR['block_button_text'],
        'block_link_text'   => $LANG_MLR['block_link_text'],
        'site_name' => $_CONF['site_name'],
    ) );
    $T->parse('output', 'mailer_block');
    return $T->finish($T->get_var('output'));
}


function phpblock_mailer_small()
{
    global $_CONF,$LANG_MLR;

    $T = new Template(__DIR__ . '/templates/');
    $T->set_file('mailer_block_sm', 'block_small.thtml');
    $T->set_var(array(
        'block_text_small'  => $LANG_MLR['block_text_small'],
        'block_button_text' => $LANG_MLR['block_button_text'],
        'block_link_text'   => $LANG_MLR['block_link_text'],
    ) );
    //$T->set_var( 'site_name', $_CONF['site_name'] );
    $T->parse('output', 'mailer_block_sm');
    return $T->finish($T->get_var('output'));
}


/**
 * Set template variables.
 *
 * @param    string  $templatename   name of template, e.g. 'header'
 * @param    ref     $template       reference of actual template
 */
function plugin_templatesetvars_mailer($templatename, &$template)
{
    if ($templatename == 'registration') {
        $existingPluginFields = $template->get_var('plugin_fields');
        $pluginFields = '';
        $chk = '';
        switch (Config::get('def_register_sub')) {
        case 1:     // checked selection
            $chk = 'checked="checked"';
        case 2:     // unchecked
            $T = new Template(__DIR__ . '/templates/');
            $T->set_file('mailer_block', 'registration.thtml');
            $T->set_var('chk', $chk);
            $T->parse('output', 'mailer_block');
            $pluginFields = $T->finish($T->get_var('output'));
            break;
        case 3:     // Automatic subscription, don't need a template for this
            $pluginFields =
                '<input type="hidden" name="mailer_subscribe value="1" />' . LB;
            break;
        }
        $template->set_var('plugin_fields', $existingPluginFields . $pluginFields);
    }
}


/**
 * Add CSS to the header.
 *
 * @return  array   Array of files to add.
 */
function plugin_getheadercss_mailer()
{
    global $_CONF;

    $styles = array();
    $files = array('style.css',);

    $pi_css = __DIR__ . '/css/';
    $layout_css = "{$_CONF['path_layout']}plugins/" . Config::PI_NAME . '/';

    foreach ($files as $file) {
        if (@file_exists($layout_css . $file)) {
            $styles[] = $layout_css . $file;
        } elseif (@file_exists($pi_css . 'custom/' . $file)) {
            $styles[] = $pi_css . 'custom/' . $file;
        } else {
            $styles[] = $pi_css . $file;
        }
    }

    return $styles;
}


/*function plugin_getheadercode_mailer()
{
    global $_CONF, $LANG_MLR;
    $headHtml = '';

    $headHtml .= '<script type="text/javascript" src="' .
        MLR_URL . '/index.php?view=javascript"></script>'."\n";

    return $headHtml;
}*/


/**
 * Return the URL to the plugin's icon.
 *
 * @return  string      URL to the icon
 */
function plugin_geticon_mailer()
{
    return Config::get('url') . '/images/mailer.png';
}


/**
 * Update the Mailer plugin.
 *
 * @param   boolean $dvlp   True for a development upgrade
 * @return  integer Number of message to display (true = generic success msg)
 */
function plugin_upgrade_mailer($dvlp=false)
{
    require_once __DIR__ . '/upgrade.inc.php';
    return MLR_do_upgrade($dvlp);
}


/**
 * Perform scheduled functions.
 * - Delete unconfirmed subscriptions
 * - Process the mail queue
 * - Delete expired mailings
 */
function plugin_runScheduledTask_mailer()
{
    // Purge old unconfirmed subscriptions
    Subscriber::purgeUnconfirmed();

    // Delete expired mailings
    Campaign::purgeExpired();

    // Process the mail queue
    Queue::process();
}


/**
 * Get the URL to the localized configuration help file.
 *
 * @return  array   Array of (url, window_type)
 */
function plugin_getconfigelementhelp_mailer($option, $doclang='english')
{
    $doc_url = MLR_getDocURL('config.html', $doclang);
    if (empty($doc_url)) {
        return false;
    }

    if (!empty($option)) {
        $doc_url .= '#desc_' . $option;
    }
    return array($doc_url, 0);

}


/**
 * Get the link to a documentation file.
 * Returns the URL to the file, or to the directory if no file requested.
 *
 * @see     plugin_getconfigelementhelp_mailer()
 * @param   string  $file       HTML file
 * @param   string  $doclang    Documentation language
 * @return  string              URL to document directory or file
 */
function MLR_getDocURL($file, $doclang='')
{
    global $_CONF;

    if ($doclang == '') $doclang = $_CONF['language'];
    $doc_path = $_CONF['path_html'] . Config::PI_NAME .
                "/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = Config::get('url') . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = Config::get('url') . "/docs/$doclang/";
    }

    return $doc_url;
}

require_once __DIR__ . '/services.inc.php';

/**
 * Return true if this plugin supports webservices.
 *
 * @return  boolean     True, if webservices are supported
 */
function plugin_wsEnabled_mailer()
{
    return false;
}

//glFusion\Notifier::Register('mailer', 'Mailer\\Notifiers\Mailer', $LANG_MLR['pi_dscp']);
