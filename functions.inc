<?php
/**
 * API functions for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2011 Lee Garner <lee@leegarner.com>
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
Config::getInstance();

define('MLR_PI_PATH', __DIR__ . '/');

define('MLR_STAT_PENDING',    0);
define('MLR_STAT_ACTIVE',     1);
define('MLR_STAT_BLACKLIST',  2);

// Status codes for adding new addresses
define('MLR_ADD_SUCCESS',     0);
define('MLR_ADD_ERROR',       1);
define('MLR_ADD_MISSING',     2);
define('MLR_ADD_EXISTS',      3);
define('MLR_ADD_BLACKLIST',   4);
define('MLR_ADD_INVALID',     5);

define('MLR_BLOCK_LEFT',      0);
define('MLR_BLOCK_RIGHT',     1);
define('MLR_BLOCK_BOTH',      2);
define('MLR_BLOCK_NONE',      3);

/**
 * Language file include.
 */
$lang = $_CONF['language'];
$langfile = MLR_PI_PATH . 'language/' . $lang . '.php';
if (file_exists($langfile)) {
    require_once $langfile;
} else {
    $lang = 'english';
    require_once __DIR__ . '/language/english.php';
}
if (file_exists(__DIR__ . '/language/custom/' . $lang. '.php')) {
    // Include custom language strings, if available
    include_once __DIR__ . '/language/custom/' . $lang. '.php';
}

require_once __DIR__ . '/mailer.php';
if (is_file(__DIR__ . '/config.php')) {
    include_once __DIR__ . '/config.php';
}

define('MLR_URL', $_CONF['site_url'] . '/' . Config::get('pi_name'));
define('MLR_ADMIN_URL',
    $_CONF['site_admin_url'] . '/plugins/' . Config::get('pi_name')
);

/**
 * This will put an option for mailer in the command and control block.
 *
 * @return  mixed   Array(text, url, icon) or false if not allowed
 */
function plugin_cclabel_mailer()
{
    global $_CONF, $LANG_MLR;

    if (SEC_hasRights('mailer.admin,mailer.edit', 'OR')) {
        return array ($LANG_MLR['mailer'],
                Config::get('admin_url') . '/index.php',
                plugin_geticon_mailer());
    }

    return false;
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
 * Get the administrative menu option for this plugin.
 *
 * @return  array   Text=>Url for menu option
 */
function plugin_getadminoption_mailer()
{
    global $_CONF, $_TABLES, $LANG_MLR;

    if (SEC_hasRights('mailer.admin,mailer.edit', 'OR')) {
        $result = DB_query("SELECT count(*) AS cnt
            FROM {$_TABLES['mailer']}" .
            COM_getPermSQL('WHERE', 0, 3));
        $A = DB_fetchArray ($result);
        return array(
            $LANG_MLR['mailer'],
            Config::get('admin_url') . '/index.php',
            (int)$A['cnt']
        );
    }
}


/**
 * Get the user menu option to subsubscribe or unsubscribe.
 *
 * @return  array   Text=>URL for menu
 */
function plugin_getuseroption_mailer()
{
    global $_USER, $LANG_MLR, $_CONF, $_TABLES;

    $Sub = Subscriber::getByEmail($_USER['email']);
    if ($Sub->getID() == 0 || $Sub->getStatus() == Mailer\Models\Status::UNSUBSCRIBED) {
        // User isn't subscribed
        return array(
            $LANG_MLR['user_menu_subscribe'],
            Config::get('url') . '/index.php?view=add&amp;email=' .
                urlencode($_USER['email']) . '&amp;mode=success',
            0
        );
    } else {
        // User is subscribed, offer unsubscribe link
        return array(
            $LANG_MLR['user_menu_unsubscribe'],
            Config::get('url') . '/index.php?view=unsub&amp;email=' .
                urlencode($_USER['email']) . '&amp;token=' . $Sub->getToken(),
                0
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
    global $_TABLES;

    if (isset($_POST['mailer_subscribe']) && $_POST['mailer_subscribe'] == 1) {
        $email = DB_getItem($_TABLES['users'], 'email', "uid = '{$uid}'");
        MLR_addEmail($email);
    }
}


/**
 * Unsubscribe deleted users and update mailers owned by them.
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
        //$email = DB_getItem($_TABLES['users'], 'email', "uid = '{$uid}'");
        //MLR_unsubscribe($email);
    }

    // Then find any mailings owned or authored by this user and change them.
    if (
        DB_count($_TABLES['mailer'], 'mlr_uid', $uid) +
        DB_count($_TABLES['mailer'], 'owner_id', $uid) == 0
    ) {
        return;
    }

    // assign ownership to a user from the Root group
    $rootgroup = (int)DB_getItem(
        $_TABLES['groups'],
        'grp_id',
        "grp_name = 'Root'"
    );
    $result = DB_query(
        "SELECT DISTINCT ug_uid
        FROM {$_TABLES['group_assignments']}
        WHERE ug_main_grp_id = $rootgroup
        ORDER BY ug_uid LIMIT 1"
    );
    $A = DB_fetchArray($result, false);
    $rootuser = (int)$A['ug_uid'];

    if ($rootuser > 0) {
        DB_query(
            "UPDATE {$_TABLES['mailer']} SET
                mlr_uid = $rootuser,
                owner_id = $rootuser
            WHERE mlr_uid = $uid OR owner_id = $uid"
        );
    } else {
        COM_errorLog("Mailer: Error finding root user");
    }
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
        if ($Sub->getUid() > 1) {
            if ($Sub->getStatus() == Mailer\Models\Status::BLACKLIST) {
                // Blacklisted, don't show the option at all
                return $retval;
            } elseif ($Sub->getStatus() == Mailer\Models\Status::ACTIVE) {
                $chk = 'checked="checked"';
            }
        }
        $T = new Template(MLR_PI_PATH . 'templates/');
        $T->set_file('profile', 'profile.thtml');
        $T->set_var('mailer_checked', $chk);
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
    var_dump(debug_backtrace(0));die;

    if ($uid == 0) {
        $uid = (int)$_USER['uid'];
    } else {
        $uid = (int)$uid;
    }
    $Sub = Mailer\Models\Subscriber::getByUid($uid);
    if (isset($_POST['mailer_subscribe'])) {
        // User requesting addition, double opt-in not needed
        if (Config::get('dbl_optin_members')) {
            $Sub->subscribe();
        } else {
            // Not requiring double-optin for members
            $Sub->subscribe(Mailer\Models\Status::ACTIVE);
        }
    } else {
        $Sub->unsubscribe();
    }

}


function plugin_itemsaved_mailer($id, $type, $old_id = '')
{
    global $_TABLES;

    $contentInfo = PLG_getItemInfo(
        $type, $id,
        'id,status,merge_fields',
        2
    );

    if ($contentInfo) {
        if ($contentInfo['id'] > 1) {
            $Sub = Subscriber::getByUid($contentInfo['id']);
//            $Sub->updateMember();
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

    // toggle Mailer blocks
    DB_query("UPDATE {$_TABLES['blocks']}
        SET is_enabled = $is_enabled
        WHERE (type = 'phpblock')
        AND (phpblockfn LIKE 'phpblock_mailer%')"
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
        'tables' => array('mailer', 'mailer_emails',
                        'mailer_queue'),
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

    if (!COM_isAnonUser()) {
        // Anon always sees the block, no way to know if they're subscribed
        $c = DB_count($_TABLES['mailer_emails'], 'email', $_USER['email']);
        if ($c > 0) return '';
    }

    $T = new Template(MLR_PI_PATH . 'templates/');
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

    $T = new Template(MLR_PI_PATH . 'templates/');
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
    /*if ($templatename == 'header') {
        $template->set_var('button_mailer', $LANG_MLR['mailer']);
        $template->set_var('mailer_sm', phpblock_mailer_small());
    }*/
    if ($templatename == 'registration') {
        $chk = '';
        switch (Config::get('def_register_sub')) {
        case 1:     // checked selection
            $chk = 'checked="checked"';

        case 2:     // unchecked
            $existingPluginFields = $template->get_var('plugin_fields');

            $T = new Template(MLR_PI_PATH . 'templates/');
            $T->set_file('mailer_block', 'registration.thtml');
            $T->set_var('chk', $chk);
            $T->parse('output', 'mailer_block');
            $pluginFields = $T->finish($T->get_var('output'));

            $template->set_var('plugin_fields',
                    $existingPluginFields . $pluginFields);
            break;

        case 3:     // Automatic subscription, don't need a template for this
            $pluginFields =
                '<input type="hidden" name="mailer_subscribe value="1"'.
                XHTML . '>'.LB;
            break;
        }
    }

}


function plugin_getheadercss_mailer()
{
    global $_CONF;

    $styles = array();
    $files = array('style.css',);

    $pi_css = MLR_PI_PATH . 'css/';
    $layout_css = "{$_CONF['path_layout']}plugins/" . Config::get('pi_name') . '/';

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
    global $_IMAGE_TYPE;

    return MLR_URL . '/images/mailer.'.$_IMAGE_TYPE;
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
 * Add email to subscriber list.
 * First checks if the email exists, then if it's a valid domain.  If ok,
 * then it's added to the database with a unique token
 *
 * @return  integer Status
 */
function XMLR_addEmail($email, $status = MLR_STAT_PENDING)
{
    global $_TABLES;

    $pieces = explode('@', $email);
    if (count($pieces) != 2) {
        // has to have exactly one '@' symbol
        return MLR_ADD_INVALID;
    }
    $db_email = DB_escapeString($email);

    // Check if the address already exists, and alter the status
    $id = DB_getItem($_TABLES['mailer_emails'], 'id ', "email='{$db_email}'");
    if ($id > 0) {
        //  Do we need to blacklist or whitelist if already exists?
        //  Only when admin imports directly
        if ($pieces[0] == '*' && $status == MLR_STAT_ACTIVE){
            MLR_whitelist($email);
        }
        if ($status == MLR_STAT_BLACKLIST) {
            MLR_blacklist($email);
        }
        return MLR_ADD_EXISTS;
    }

    if (MLR_isValidDomain($pieces[1])) {
        // Valid domain, add the record

        $token = MLR_genToken($email);
        $domain = DB_escapeString($pieces[1]);
        $status = (int)$status;

        DB_query("INSERT INTO {$_TABLES['mailer_emails']} (
                `dt_reg`, `domain`, `email`, `token`, `status`
            ) VALUES (
                '{$_MLR_CONF['now']}', '{$domain}', '{$db_email}',
                '$token', $status
            )", 1);

        if (DB_error()) {
            MLR_auditLog("Error subscribing $email, status $status");
            return MLR_ADD_ERROR;
        }
        if ($status == MLR_STAT_PENDING) {
            MLR_sendConfirmation($email, $token);
        }

        if ($status == MLR_STAT_BLACKLIST || MLR_checkBlacklist($email)) {
            MLR_blacklist($email);
        } elseif ($pieces[0] == '*' && $status == MLR_STAT_ACTIVE) {
            MLR_whitelist($email);
        }

        MLR_auditLog("Subscribed $email, status $status");
        return MLR_ADD_SUCCESS;
    } else {
        return MLR_ADD_INVALID;
    }
}


/**
 * Check email against blacklist.
 *
 * @return  boolean     True if blacklisted, False if not
 */
function MLR_checkBlacklist($email)
{
    global $_TABLES;

    $pieces = explode('@', $email);
    if ($pieces[0] != '*') {
        // Only check email, not domains
        $email = DB_escapeString($email);
        if (DB_count($_TABLES['mailer_emails'], 'id', "email='$email'") == 1) {
            return true;
        }
    }
    return false;
}


/**
 * Add email to blacklist.
 *
 * @param   string  $email  Email to add
 */
function XMLR_blacklist($email)
{
    global $_TABLES;

    $pieces = explode('@', $email);
    if ($pieces[0] == '*') {
        // Update status of all addreses with this domain
        DB_query("UPDATE {$_TABLES['mailer_emails']}
                SET status='" . MLR_STAT_BLACKLIST . "'
                WHERE domain = '" . DB_escapeString($pieces[1]) . "'", 1);
    } else {
        DB_query("UPDATE {$_TABLES['mailer_emails']}
                SET status = '" . MLR_STAT_BLACKLIST . "'
                WHERE email = '" . DB_escapeString($email) . "'");
    }
    MLR_auditLog("Blacklisted $email");
}


/**
 * Add email to whitelist.
 */
function XMLR_whitelist($email)
{
    global $_TABLES;

    $pieces = explode('@', $email);
    if ($pieces[0] == '*') {
        DB_query("UPDATE {$_TABLES['mailer_emails']}
                SET status='" . MLR_STAT_ACTIVE . "'
                WHERE domain = '" . DB_escapeString($pieces[1]) . "'", 1);
    } else {
        DB_query("UPDATE {$_TABLES['mailer_emails']} SET
                status = '" . MLR_STAT_ACTIVE . "'
                WHERE email = '" . DB_escapeString($email) . "'", 1);
    }
    MLR_auditLog("Whitelisted $email");
}


/**
 * Set a subscriber's status.
 *
 * @param   integer $id     Record ID to update
 * @param   integer $status New status
 */
function XMLR_setStatus($id, $status)
{
    global $_TABLES;

    $id = (int)$id;
    $status = (int)$status;

    DB_query("UPDATE {$_TABLES['mailer_emails']} SET
            status = $status
            WHERE id = $id");
}


/**
 * Fetch blacklist.
 *
 * @return  array   Array(email_array, domain_array)
 */
function X_MLR_fetchBlacklist()
{
    global $_TABLES;

    $blackListDomains = $blackListEmails = array ();
    $sql = "SELECT `email`
            FROM {$_TABLES['mailer_emails']}
            WHERE status = '" . MLR_STAT_BLACKLIST . "'";
    $result = DB_query($sql);
    while ($A = DB_fetchArray($result)) {
        $pieces = explode('@', $A['email']);
        $blackListDomains[] = $pieces[0] == '*' ? $pieces[1] : $A['email'];
        //($pieces[0] == '*')?$blackListDomains[] = $pieces[1] : $blackListEmails[] = $A['email'];
    }
    return array($blackListEmails, $blackListDomains);
}


/**
 * Fetch whitelist.
 *
 * @return  array   Array(email_array, domain_array)
 */
function X_MLR_fetchWhitelist()
{
    global $_TABLES;

    $whitelistEmails = array ();
    $sql = "SELECT `email` FROM {$_TABLES['mailer_emails']}
            WHERE status = '" . MLR_STAT_ACTIVE . "'";
    $result = DB_query($sql);
    while ($A = DB_fetchArray($result))
    {
        if (!preg_match('/\*@/', $A['email'])) {
            $whitelistEmails[] = $A['email'];
        }
    }
    return array($whitelistEmails);
}


/**
 * Process the mail queue.
 * First, check to see if the configured interval has passed.  Then
 * see if there are any messages to send.  Finally, update the last-run
 * value and process the messages.
 *
 * There's a reason we don't batch by domain & use BCC: each message has
 * a custom unsubscribe footer.
 *
 * @param   boolean $force  True to force immediate processing of all items
 */
function XMLR_processQueue($force=false)
{
    global $_CONF, $_USER, $_TABLES, $LANG_MLR;

    if (!$force) {
        // Find out when we last ran, and don't run again if it's too soon
        $lastrun = DB_getItem($_TABLES['vars'], 'value',
                "name='mailer_lastrun'");
        if ($lastrun === NULL) {
            // In case our gl_vars value got deleted somehow.
            DB_query("INSERT INTO {$_TABLES['vars']} (name, value)
                    VALUES ('mailer_lastrun', 0)
                    ON DUPLICATE KEY UPDATE value = 0", 1);
            $lastrun = 0;
        }
        $now = time();
        if ($now - $lastrun < (int)Config::get('queue_interval')) {
            return;
        }

        // Set the maximum messages to be sent
        if ((int)Config::get('max_per_run') > 0) {
            $sql_limit = ' LIMIT ' . (int)Config::get('max_per_run');
        } else {
            $sql_limit = '';
        }
    } else {
        // Force all items to be processed
        $sql_limit = '';
    }

    // Get the queued entries.  Order by mlr_id so we can minimize DB calls
    // to retrieve mailer data.
    $sql = "SELECT q.mlr_id, q.email, e.token
            FROM {$_TABLES['mailer_queue']} q
            LEFT JOIN {$_TABLES['mailer_emails']} e
            ON q.email = e.email
            ORDER BY q.mlr_id ASC
            $sql_limit ";
    $res = DB_query($sql);
    if (!$res || DB_numRows($res) == 0) {
        return;
    }

    $N = new Mailer();

    $mlr_id = '';           // mailer ID used for control-break

    // Loop through the queue, sending the email to each address
    while ($A = DB_fetchArray($res, false)) {

        if ($mlr_id != $A['mlr_id']) {
            // New mailer ID, get it
            $mlr_id = $A['mlr_id'];

            if (!$N->Read($mlr_id)) {
                // Invalid ID, delete all scheduled mailings & quit.
                // Would be better to re-query the DB and continue with valid
                // mailings, but for now this function will need to be called
                // again.
                DB_delete($_TABLES['mailer_queue'], 'mlr_id', $mlr_id);
                break;
            }
        }

        /*$T = new Template(MLR_PI_PATH . 'templates/');
        $T->set_file('msg', 'mailer_email.thtml');
        $T->set_var(array(
            'content'   => $N->mlr_content,
            'pi_url'    => MLR_URL,
            'mlr_id'    => $N->mlr_id,
            'token'     => $A['token'],
            'email'     => $A['email'],
        ) );
        $T->parse('output', 'msg');
        $body = $T->finish($T->get_var('output'));
        $altbody = strip_tags($body);

        // Create the "from" address using the site or noreply mail address
        $fromEmail = isset($_CONF[$_MLR_CONF['email_from']]) ?
            $_CONF[$_MLR_CONF['email_from']] : $_CONF['noreply_mail'];
        $from = array($fromEmail, $_CONF['site_name']);

        if (!COM_mail($A['email'], $N->mlr_title, $body, $from, true,
                0, '', $altbody)) {
            COM_errorLog("Mail error sending to " . $A['email'], 1);
        }
        */

        $N->mailIt($A['email'], $A['token']);

        DB_delete($_TABLES['mailer_queue'], array('mlr_id', 'email'),
            array($mlr_id, $A['email']));

    }   // while

    // Update the last-run timestamp
    DB_query("UPDATE {$_TABLES['vars']}
            SET value = UNIX_TIMESTAMP()
            WHERE name = 'mailer_lastrun'" );
}


/**
 * Send a confirmation message when an email address is added.
 *
 * @param   string  $email  Email added and to which the message is sent
 * @param   string  $token  Unique token for the confirmation url
 */
function XMLR_sendConfirmation($email, $token)
{
    global $_CONF, $LANG_MLR;

    $title = $_CONF['site_name'] . ' ' . $LANG_MLR['confirm_title'];

    // TODO - use a template for this
    $templatepath = MLR_PI_PATH . 'templates/';
    $lang = $_CONF['language'];
    if (is_file($templatepath . $lang . '/confirm_sub.thtml')) {
        $T = new Template($templatepath . $lang);
    } else {
        $T = new Template($templatepath . 'english/');
    }
    $T->set_file('message', 'confirm_sub.thtml');
    $T->set_var(array(
        'pi_url'        => MLR_URL,
        'email'         => urlencode($email),
        'token'         => $token,
        'confirm_period' => $_MLR_CONF['confirm_period'],
        'site_name'     => $_CONF['site_name'],
    ) );

    $T->parse('output', 'message');
    $body = $T->finish($T->get_var('output'));
    $altbody = strip_tags($body);

    // Create the "from" address using the site or noreply mail address
    $fromEmail = isset($_CONF[$_MLR_CONF['email_from']]) ?
            $_CONF[$_MLR_CONF['email_from']] : $_CONF['noreply_mail'];
    $from = array($fromEmail, $_CONF['site_name']);

    COM_mail($email, $title, $body, $from, true, 0, '', $altbody);
}


/**
 * Update the last-sent timestamp on a message.
 *
 * @param   string  $mlr_id     Mailing ID
 */
function MLR_sendLock($mlr_id)
{
    global $_TABLES;

    $date_time = time();
    DB_query("UPDATE {$_TABLES['mailer']}
        SET mlr_sent_time = $date_time
        WHERE (mlr_id = '{$mlr_id}')");
}


/**
 * Remove a single email address from our list.
 * The token parameter can be filled when this is called from a public
 * page in order to prevent users from unsubscribing other users.
 *
 * @param   string  $email  Email address to remove
 * @param   string  $token  Optional token, to authenticate the removal
 * @param   string  $mlr_id Mailer ID from where the link came, for logging
 * @return  boolean         True on success, False if user not found
 */
function MLR_unsubscribe($email, $token = '', $mlr_id = '')
{
    global $_TABLES;

    // Sanitize the input and create a query to find the existing record id
    $email = DB_escapeString($email);
    $where = "email = '$email' AND status <> " . MLR_STAT_BLACKLIST;
    if ($token != '') {
        $token = DB_escapeString($token);
        $where .= " AND token = '$token'";
    }

    $id = (int)DB_getItem($_TABLES['mailer_emails'], 'id', $where);

    if ($id > 0) {
        DB_delete($_TABLES['mailer_emails'], 'id', $id);
        MLR_auditLog("Unsubscribed $email (mailer $mlr_id)");
        return true;
    //} else {
    //    MLR_auditLog("Attempted to unsubscribe $email, not a subscriber (mailer $mlr_id)");
    }
    return false;
}


/**
 * Import our current users to our subscriber list.
 *
 * @return  string  success message
 */
function MLR_importUsers()
{
    global $_TABLES, $LANG_MLR;

    $sql = "SELECT `email` FROM {$_TABLES['users']}";
    $result = DB_query($sql);
    while ($A = DB_fetchArray($result)) {
        if($A['email'] != ''){
            MLR_addEmail($A['email'], MLR_STAT_ACTIVE);
        }
    }
    return $LANG_MLR['import_complete'];
}


/**
 * Generate a unique, SQL-safe token.
 *
 * @param   string  $base   Optional unique base value to encode
 * @return  string          Token value
 */
function MLR_genToken($base='')
{
    if ($base == '') {
        $base = COM_makeSID();
    }
    return md5($base);
}


/**
 * Get the site header, with or without left blocks according to config.
 *
 * @see     function COM_siteHeader()
 * @param   string  $title  Optional text for the title bar
 * @param   string  $meta   Optional additional meta text
 * @param   integer $blockvar Optional- override block spec per mailer
 * @return  string          HTML for site header
 */
function MLR_siteHeader($title='', $meta='', $blockvar=9)
{
    global $_MLR_CONF;

    if ($blockvar == 9) $blockvar = $_MLR_CONF['displayblocks'];

    switch ($blockvar) {
        case 0:     // None
        case 2:     // Right Only
            $retval = COM_siteHeader('none',$title,$meta);
            break;
        case 1:     // Left Only
        case 3:     // Both
        default:
            $retval = COM_siteHeader('menu',$title,$meta);
            break;
    }
    return $retval;
}


/**
 * Get the site footer, with or without right blocks according to config.
 *
 * @see     function COM_siteFooter()
 * @param   integer $blockvar Optional- override block spec per mailer
 * @return  string      HTML for site footer
 */
function MLR_siteFooter($blockvar=9)
{
    global $_MLR_CONF;

    if ($blockvar == 9) $blockvar = $_MLR_CONF['displayblocks'];

    switch ($blockvar) {
        case 0:    // None
        case 1:    // Left Only
            $retval = COM_siteFooter();
            break;
        case 2:    // Right Only
        case 3:    // Both
        default:
            $retval = COM_siteFooter(true);
            break;
    }
    return $retval;
}


/**
 * Perform scheduled functions.
 * - Delete unconfirmed subscriptions
 * - Process the mail queue
 * - Delete expired mailings
 */
function plugin_runScheduledTask_mailer()
{
    global $_TABLES, $_MLR_CONF;

    // Purge old unconfirmed subscriptions.
    $purge_days = (int)$_MLR_CONF['confirm_period'];
    if ($purge_days > 0) {
        $sql = "DELETE FROM {$_TABLES['mailer_emails']}
                WHERE status = '" . Mailer\Models\Status::PENDING . "'
                AND '{$_MLR_CONF['now']}' > DATE_ADD(dt_reg, INTERVAL $purge_days DAY)";
        $res = DB_query($sql, 1);
        $nrows = DB_affectedRows($res);
        if ($nrows > 0) {
            MLR_auditLog(sprintf('Purged %d unconfirmed subscriptions', $nrows));
        }
    }

    // Delete expired mailings
    $sql = "DELETE FROM {$_TABLES['mailer']}
            WHERE exp_days > 0
            AND '{$_MLR_CONF['now']}' > DATE_ADD(mlr_date, INTERVAL exp_days DAY)";
    DB_query($sql, 1);

    Mailer\Queue::process();
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
    global $_CONF, $_MLR_CONF;

    if ($doclang == '') $doclang = $_CONF['language'];
    $doc_path = $_CONF['path_html'] . $_MLR_CONF['pi_name'] .
                "/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = MLR_URL . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = MLR_URL . "/docs/$doclang/";
    }

    return $doc_url;
}


/**
 * Display a formatted error message.
 *
 * @param   string  $msg    Error message to display
 * @param   string  $type   Type of message, used for style and header
 * @param   string  $header Optional header text.
 * @return  string          Formatted error message
 */
function MLR_errorMsg($msg = '', $header = '')
{
    global $LANG01;

    // Require a valid message
    if ($msg == '')
        return '';

    // If no header text specified, we'll put in a default.
    $class = 'alert';
    if ($header == '') $header = $LANG01[34];   // "Error"

    return "<span class=\"$class\">"
            . COM_startBlock($header)
            . $msg
            . COM_endBlock()
            . '</span>';

}


/**
 * Log activity to a plugin-specific log file.
 *
 * @param   string  $logentry   Text to log
 */
function MLR_auditLog($logentry = '')
{
    global $_CONF, $_USER, $LANG01, $_MLR_CONF;

    if ($logentry == '')
        return '';

    // A little sanitizing
    $logentry = str_replace(
            array('<?', '?>'),
            array('(@', '@)'),
            $logentry);

    $timestamp = strftime( '%c' );
    $logfile = $_CONF['path_log'] . $_MLR_CONF['pi_name'] . '.log';

    // Can't open the log file?  Return an error
    if (!$file = fopen($logfile, 'a')) {
        COM_errorLog("Unable to open {$_MLR_CONF['pi_name']}.log");
        return;
    }

    // Get the user name if it's not anonymous
    if (isset($_USER['uid'])) {
        $byuser = $_USER['uid'] . '-'.
                COM_getDisplayName($_USER['uid'],
                    $_USER['username'], $_USER['fullname']);
    } else {
        $byuser = 'anon';
    }
    $byuser .= '@' . $_SERVER['REMOTE_ADDR'];

    // Write the log entry to the file
    fputs($file, "$timestamp ($byuser) - $logentry\n");
    fclose($file);

}


/**
 * Validate a domain.
 * Note that getmxrr() is not available on Windows.
 *
 * @param   string  $domain     Domain to check
 * @return  boolean     True for a valid domain, False if invalid
 */
function MLR_isValidDomain($domain)
{
    return @getmxrr($domain, $mxrecords);
}


require_once MLR_PI_PATH . '/services.inc.php';

/**
 * Return true if this plugin supports webservices.
 *
 * @return  boolean     True, if webservices are supported
 */
function plugin_wsEnabled_mailer()
{
    return false;
}

/**
 * Invoke a service from another plugin.
 * This is our own version of PLG_invokeService() so we don't have to enable
 * web services.
 *
 * @param   string  $type   Plugin name
 * @param   string  $action Plugin function
 * @param   array   $args   Array of arguments to pass to the plugin
 * @param   mixed   &$output    Pointer to output values
 * @param   mixed   &$svc_msg   Service message (unused)
 * @return  integer Return value, see lib-plugins.php
 */
function MLR_invokeService($type, $action, $args, &$output, &$svc_msg)
{
    global $_CONF;

    $retval = PLG_RET_ERROR;

    $output  = '';
    $svc_msg = '';

    // Check if the plugin type and action are valid
    $function = 'service_' . $action . '_' . $type;
    if (function_exists($function)) {
        if (!isset($args['gl_svc'])) {
            $args['gl_svc'] = false;
        }
        $retval = $function($args, $output, $svc_msg);
    }

    return $retval;
}

