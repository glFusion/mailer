<?php
/**
 * English language strings for the Mailer plugin.
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


global $LANG32;

$LANG_MLR = array(
    'mailers' => 'Mailers',
    'mlr_archive' => 'Archived Mailings',
    'subscribers' => 'Subscribers',
    'queue' => 'Queue',
    'list' => 'List',
    'newpage' => 'New Page',
    'admin' => 'Admin',
    'adminhome' => 'Admin Home',
    'mailer' => 'Mailer',
    'mailereditor' => 'Mailing Editor',
    'writtenby' => 'Written By',
    'date' => 'Last Updated',
    'title' => 'Title',
    'content' => 'Content',
    'hits' => 'Hits',
    'mailerlist' => 'Mailing List',
    'block_title' => 'Mailing List',
    'block_text' => 'Enter your email <img style="border:0px solid; float:right;height:40px;width:40px;" src="%s/mailer/images/mailer.png"/>address below and we&#39;ll keep you up-to-date on our news, events &amp; other specials.',
    'block_button_text' => 'Sign Up',
    'block_link_text' => 'Archives',
    'success_title' => 'Thank You',
    'list_title' => 'Mailer Archives',
    'add_success' => 'Your email address has been successfully added to our list.',
    'confirm_needed' => 'Your email address has been added to our database.  Watch your email for a confirmation message and a url that you\'ll need to visit to confirm your subscription.',
    'confirm_title' => 'Mailing List Confirmation',
    'url' => 'URL',
    'edit' => 'Edit',
    'lastupdated' => 'Last Updated',
    'pageformat' => 'Page Format',
    'bothblocks' => 'Left and Right Blocks',
    'rightblocks' => 'Right Blocks',
    'noblocks' => 'No Blocks',
    'leftblocks' => 'Left Blocks',
    'addtomenu' => 'Add To Menu',
    'label' => 'Label',
    'nopages' => 'No mailers are in the system yet',
    'save' => 'save',
    'preview' => 'preview',
    'delete' => 'delete',
    'cancel' => 'cancel',
    'email' => 'Email',
    'access_denied' => 'Access Denied',
    'access_denied_msg' => 'You are illegally trying access one of the Mailer administration pages.  Please note that all attempts to illegally access this page are logged',
    'all_html_allowed' => 'All HTML is allowed',
    'results' => 'Mailer Results',
    'author' => 'Author',
    'no_title_or_content' => 'You must at least fill in the <b>Title</b> and <b>Content</b> fields.',
    'no_such_page_anon' => 'Please log in..',
    'no_page_access_msg' => "This could be because you're not logged in, or not a member of {$_CONF['site_name']}. Please <a href=\"{$_CONF['site_url']}/users.php?mode=new\"> become a member</a> of {$_CONF['site_name']} to receive full membership access",
    'php_msg' => 'PHP: ',
    'php_warn' => 'Warning: PHP code in your page will be evaluated if you enable this option. Use with caution !!',
    'exit_msg' => 'Exit Type: ',
    'exit_info' => 'Enable for Login Required Message.  Leave unchecked for normal security check and message.',
    'deny_msg' => 'Access to this page is denied.  Either the page has been moved/removed or you do not have sufficient permissions.',
    'stats_headline' => 'Top Ten Mailer',
    'stats_page_title' => 'Page Title',
    'stats_hits' => 'Hits',
    'stats_no_hits' => 'It appears that there are no mailers on this site or no one has ever viewed them.',
    'id' => 'ID',
    'mlr_id' => 'Mailer ID',
    'duplicate_id' => 'The ID you chose for this mailer is already in use. Please select another ID.',
    'instructions' => 'To modify or delete a mailer, click on that page\'s edit icon below. To view a mailer, click on the title of the page you wish to view. To create a new mailer, click on "Create New" above. Click on on the copy icon to create a copy of an existing page.',
    'instr_archive' => 'We keep recent mailings available here for review.  Click on the title to view the original message.',
    'instr_admin_queue' => 'Individual items may be deleted from the queue provided they haven\'t been processed since this page was loaded.  To remove ALL messages from the queue, click "Purge Queue" above. To reset the "last sent" timestamp, click "Reset Queue".  To send ALL messages immediately, click "Flush Queue".',
    'centerblock' => 'Centerblock: ',
    'centerblock_msg' => 'When checked, this Mailer will be displayed as a center block on the index page.',
    'purge_queue'   => 'Purge Queue',
    'reset_queue'   => 'Reset Queue',
    'flush_queue'   => 'Flush Queue',
    'topic' => 'Topic: ',
    'position' => 'Position: ',
    'all_topics' => 'All',
    'no_topic' => 'Homepage Only',
    'position_top' => 'Top Of Page',
    'position_feat' => 'After Featured Story',
    'position_bottom' => 'Bottom Of Page',
    'position_entire' => 'Entire Page',
    'head_centerblock' => 'Centerblock',
    'centerblock_no' => 'No',
    'centerblock_top' => 'Top',
    'centerblock_feat' => 'Feat. Story',
    'centerblock_bottom' => 'Bottom',
    'centerblock_entire' => 'Entire Page',
    'inblock_msg' => 'In a block: ',
    'inblock_info' => 'Wrap Mailer in a block.',
    'title_edit' => 'Edit page',
    'title_copy' => 'Make a copy of this page',
    'title_display' => 'Display page',
    'select_php_none' => 'do not execute PHP',
    'select_php_return' => 'execute PHP (return)',
    'select_php_free' => 'execute PHP',
    'php_not_activated' => 'The use of PHP in mailers is not activated. Please see the <a href="' . $_CONF['site_url'] . '/docs/mailer.html#php">documentation</a> for details.',
    'printable_format' => 'Printable Format',
    'edit' => 'Edit',
    'copy' => 'Copy',
    'limit_results' => 'Limit Results',
    'search' => 'Search',
    'submit' => 'Submit',
    'sendnow' => 'Mail this mailer now?',
    'sendtest' => 'Send a test to your email address?',
    'send' => 'Send',
    'last_sent' => 'Last Sent',
    'removed_title' => 'Removed',
    'removed_msg' => 'Your email address has been removed from our announcement mailing list.',
    'import_checkbox' => 'Add to Blacklist',
    'blacklisted_title' => 'Blacklisted',
    'blacklisted_msg' => 'Email address %s has been added to our blacklist.',
    'whitelisted_title' => 'Whitelisted',
    'whitelist' => 'Whitelist',
    'blacklist' => 'Blacklist',
    'subscribe' => 'Subscribe',
    'whitelisted_msg' => 'Email address %s has been added to our whitelist.',
    'trouble_viewing' => 'Trouble viewing this mailer? View it online',
    'unsubscribe' => 'Unsubscribe',
    'subscriberlist' => 'Subscribers',
    'list_status' => 'List Status',
    'list_status_hover_message' => 'Add to %slist',
    'email_format_error' => '<strong>Error</strong>: An invalid email address was provided.',
    'email_store_error' => '<strong>Error</strong>: There was an error storing your email address.',
    'email_success' => 'Thanks for signing up!',
    'email_missing' => 'Please enter an email address.',
    'email_exists' => 'You\'re already signed up.',
    'email_blacklisted' => 'This address cannot be subscribed.',
    'adding_msg' => 'Adding email address...',
    'block_text_small' => 'Join our mailing list!',
    'import' => 'Import',
    'export' => 'Export',
    'clear' => 'Clear Subscriber List',
    'import_temp_text' => 'Copy/paste your import list here.',
    'delimiter' => 'Delimiter',
    'importer' => 'Mailer Email List Import',
    'import_complete' => 'Import Complete',
    'user_edit_checkbox_label' => 'Subscribe to Announcements?',
    'sorry_no_archives_yet' => 'Sorry no archives yet.',
    'user_menu_subscribe' => 'Subscribe to Announcements',
    'user_menu_unsubscribe' => 'Unsubscribe from Announcements',
    'import_current_users' => 'Import Current Users',
    'import_users_confirm' => 'Are you sure that you want to import ALL site users?',
    'are_you_sure'=> 'Are you sure you want to clear your subscribers?',
    'truncate_complete' => 'Subscribers cleared',
    'blacklisted_emails' => 'Blacklisted Emails: ',
    'blacklisted_domains' => 'Blacklisted Domains: ',
    'whitelisted_emails' => 'Whitelisted Emails: ',
    'mailer_admin' => 'Mailer Administration',
    'config' => 'Configuration',
    'config_location_message' => 'Mailer configuration options are now available via the <a href="%s/configuration.php">site configuration panel</a>.',
    'expires_in' => 'Expires in (days, 0 for unlimited)',
    'err_missing_title' => 'A title is required',
    'err_missing_content' => 'The content field cannot be empty',
    'not_found' => 'The item that you were looking for has been removed or is otherwise not available.',
    'statuses' => array('Pending', 'Active', 'Blacklisted'),
    'conf_sendnow' => 'Do you really want to send this item to all recipients?',
    'conf_delete' => 'Do you really want to delete this item?',
    'site_user' => 'Site User',
    'conf_black' => 'Are you sure you want to blacklist these subscribers?',
    'conf_white' => 'Are you sure you want to activate these subscribers?',
    'success' => 'Success',
    'error' => 'Error',
    'invalid' => 'Invalid Address',
    'duplicate' => 'Duplicate Address',
    'never' => 'Never',
);


// Messages for the plugin upgrade
$PLG_mailer_MESSAGE3001 = 'Plugin upgrade not supported.';
$PLG_mailer_MESSAGE3002 = $LANG32[9];
$PLG_mailer_MESSAGE1   = 'Your email address has been successfully added to our list.';
$PLG_mailer_MESSAGE2   = 'You are now subscribed to our announcement list.';
$PLG_mailer_MESSAGE3   = 'Your email address has been permantely blocked from our announcement list.';
$PLG_mailer_MESSAGE4   = 'You have unsubscribed from our announcements.';
$PLG_mailer_MESSAGE5   = 'Invalid email or token specified. You may need to re-subscribe.';
$PLG_mailer_MESSAGE6    = $LANG_MLR['email_exists'];
$PLG_mailer_MESSAGE7    = $LANG_MLR['email_blacklisted'];
$PLG_mailer_MESSAGE8    = $LANG_MLR['email_store_error'];
$PLG_mailer_MESSAGE9    = $LANG_MLR['email_format_error'];
$PLG_mailer_MESSAGE10   = $LANG_MLR['email_missing'];

// Localization of the Admin Configuration UI
$LANG_configsections['mailer'] = array(
    'label' => 'Mailer',
    'title' => 'Mailer Configuration'
);

$LANG_confignames['mailer'] = array(
    'filter_html' => 'Filter HTML?',
    'censor' => 'Censor Content?',
    'default_permissions' => 'Page Default Permissions',
    'max_per_run' => 'Max. items mailed at once',
    'queue_interval' => 'Delay (seconds) between queue processing',
    'displayblocks'  => 'Display glFusion Blocks',
    'confirm_period' => 'Time limit (days) for subscribers to confirm',
    'exp_days' => 'Default number of days to keep messages (0=infinite)',
    'sender_email' => 'Sender address to use in mailings',
    'sender_name' => 'Sender name to use in mailings',
    'def_register_sub' => 'Show subscription option on signup form',
    'del_user_unsub' => 'Unsubscribe users upon deletion',
    'dbl_optin_members' => 'Double Opt-In for Members',
    'log_level' => 'Logging Level',
    'blk_show_subs' => 'Show signup block to subscribers',
    'provider' => 'List Provider',
    'mc_api_key' => 'Mailchimp API Key',
    'mc_def_list' => 'Default List ID',
    'mc_mrg_fname' => 'Firstname Merge Fieldname',
    'mc_mrg_lname' => 'Lastname Merge Fieldname',
    'sb_api_key' => 'Mailchimp API Key',
    'sb_def_list' => 'Default List ID',
    'sb_dbo_tpl' => 'Double Opt-In Template ID',
);

$LANG_configsubgroups['mailer'] = array(
    'sg_main' => 'Main Settings',
);

$LANG_fs['mailer'] = array(
    'fs_main' => 'Mailer Main Settings',
    'fs_queue' => 'Mail Queue Settings',
    'fs_permissions' => 'Default Permissions',
    'fs_internal' => 'Internal',
    'fs_mailchimp' => 'Mailchimp',
    'fs_sendinblue' => 'Sendinblue',
);

// Note: entries 0, 1, 9, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['mailer'] = array(
    0 => array(
        'Yes' => 1,
        'No' => 0,
    ),
    4 => array(
        'No' => 0,
        'No- Subscribe Automatically' => 3,
        'Yes- Checked' => 1,
        'Yes- Unchecked' => 2,
    ),
    5 => array(
        'Internal' => 'Internal',
        'Mailchimp' => 'Mailchimp',
        'Sendinblue' => 'Sendinblue',
    ),
    6 => array(
        '100 DEBUG' => 100,
        '200 INFO' => 200,
        '250 NOTICE' => 250,
        '300 WARNING' => 300,
        '400 ERROR' => 400,
        '500 CRITICAL' => 500,
        '550 ALERT' => 550,
        '600 EMERGENCY' => 600,
    ),
    12 => array('No access' => 0, 'Read-Only' => 2, 'Read-Write' => 3),
    13 => array('None' => 0, 'Left' => 1, 'Right' => 2, 'Both' => 3),
    14 => array('Site E-Mail' => 'site_mail', 'No-Reply Address' => 'noreply_mail'),
);

