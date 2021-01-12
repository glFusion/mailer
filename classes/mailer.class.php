<?php
/**
 * Class to manage mailing lists.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */


/**
 * Class for mailer items.
 * @package mailer
 */
class Mailer
{
    /** Indicate whether the current user is an administrator/
     * @var boolean */
    private $isAdmin;

    /** Flag to indicate whether the mailer item is new or an edit/
     * @var boolean */
    public $isNew;

    /** Array of error messages.
     * @var mixed */
    public $Errors = array();

    /** Indicate that mail will be sent as HTML.
     * @var boolean */
    private $mailHTML = true;

    /** Properties.
     * @var array */
    private $properties = array();


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id     Optional type ID
     */
    function __construct($mlr_id='')
    {
        global $_MLR_CONF, $_USER;

        $this->isNew = true;

        $mlr_id = COM_sanitizeID($mlr_id, false);
        if ($mlr_id == '') {
            $this->mlr_id = COM_makeSid();
            $this->mlr_uid = $_USER['uid'];
            $this->mlr_title = '';
            $this->mlr_hits = 0;
            $this->mlr_content = '';
            $this->mlr_date = date('Y-m-d');
            $this->mlr_help = '';
            $this->mlr_inblock = 0;
            $this->mlr_nf = 0;
            $this->postmode = 'text';
            $this->mlr_format = $_MLR_CONF['displayblocks'];
            $this->exp_days = $_MLR_CONF['exp_days'];
            $this->commentcode = -1;
            $this->unixdate = 0;
            $this->owner_id = $_USER['uid'];
            $this->group_id = isset($_GROUPS['mailer Admin']) ?
                    $_GROUPS['mailer Admin'] :
                    SEC_getFeatureGroup('mailer.edit');
            $A = array();
            SEC_setDefaultPermissions($A, $_MLR_CONF['default_permissions']);
            $this->perm_owner = $A['perm_owner'];
            $this->perm_group = $A['perm_group'];
            $this->perm_members = $A['perm_members'];
            $this->perm_anon = $A['perm_anon'];
        } else {
            $this->mlr_id = $mlr_id;
            if (!$this->Read()) {
                $this->mlr_id = '';
            } else {
                $this->mlr_old_id = $this->mlr_id;
            }
        }

        $this->isAdmin = SEC_hasRights('mailer.admin') ? 1 : 0;
        $this->show_unsub = 1;   // always set, but may be overridden
    }


    /**
     * Set a property's value.
     *
     * @param   string  $var    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    function __set($var, $value='')
    {
        switch ($var) {
        case 'mlr_id':
        case 'mlr_old_id';
            $this->properties[$var] = COM_sanitizeID($value, false);
            break;

        case 'mlr_title':
        case 'mlr_content':
        case 'mlr_date':
        case 'mlr_postmode':
        case 'mlr_help':
        case 'postmode':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'mlr_uid':
        case 'mlr_hits':
        case 'mlr_sent_time':
        case 'perm_owner':
        case 'perm_group':
        case 'perm_members':
        case 'perm_anon':
        case 'owner_id':
        case 'group_id':
        //case 'commentcode':
        case 'unixdate':
        case 'mlr_format':
            // Integer values
            $this->$var = (int)$value;
            break;

        case 'exp_days':
            $this->properties[$var] = (int)$value;
            if ($this->$var < 0) $this->$var = 0;
            break;

        case 'mlr_inblock':
        case 'mlr_nf':
        case 'show_unsub':
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
     * Get the value of a property.
     *
     * @param   string  $var    Property name
     * @return  mixed           Property value, NULL if undefined
     */
    function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row        Array of values, from DB or $_POST
     * @param   boolean $fromDB     True if read from DB, false if from $_POST
     */
    function SetVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        foreach ($row as $name=>$value) {
            $this->$name = $value;
        }

        if (!$fromDB) {
            list($this->perm_owner, $this->perm_group,
                $this->perm_members, $this->perm_anon) =
                SEC_getPermissionValues($row['perm_owner'], $row['perm_group'],
                    $row['perm_members'], $row['perm_anon']);
        }
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Optional ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    function Read($mlr_id = '')
    {
        global $_TABLES;

        if ($mlr_id == '')
            $mlr_id = $this->mlr_id;
        else
            $mlr_id = COM_sanitizeID($mlr_id, false);

        if ($mlr_id == '') {
            $this->error = 'Invalid ID in Read()';
            return false;
        }

        $result = DB_query("SELECT *,
                UNIX_TIMESTAMP(mlr_date) AS unixdate
                FROM {$_TABLES['mailer']}
                WHERE mlr_id='$mlr_id'" .
                COM_getPermSQL('AND', 0, 2));
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row, true);
            $this->isNew = false;
            return true;
        }
    }


    /**
     * Save the current values to the database.
     * Appends error messages to the $Errors property.
     *
     * @param   array   $A      Optional array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    function Save($A = '')
    {
        global $_TABLES, $_MLR_CONF;

        if (is_array($A)) {
            $this->SetVars($A, false);
        }

        if ($_MLR_CONF['censor'] == 1) {
            $this->mlr_content = COM_checkWords($this->mlr_content);
            $this->mlr_title = COM_checkWords($this->mlr_title);
        }
        if ($_MLR_CONF['filter_html'] == 1) {
            $this->mlr_content = COM_checkHTML($this->mlr_content);
        }
        $this->mlr_title = strip_tags($this->mlr_title);

        if (!$this->isValidRecord()) return false;

        // Insert or update the record, as appropriate
        if ($this->isNew) {
            $sql = "INSERT INTO {$_TABLES['mailer']} SET ";
            $sql1 = '';
            $this->mlr_id = COM_makeSid();
        } else {
            if ($this->mlr_old_id == '') {
                $this->mlr_old_id = $this->mlr_id;
            }
            $sql = "UPDATE {$_TABLES['mailer']} SET ";
            $sql1 = " WHERE mlr_id='" . DB_escapeString($this->mlr_old_id)."'";
        }

        $sent_time = 0;
        if (isset($A['mlr_sendnow'])) {
            $this->queueIt($this->mlr_id);
            $sent_time = time();
        } elseif (isset($A['mlr_sendtest'])) {
            $this->mailIt();
        }
        $sql .= "mlr_id = '" . DB_escapeString($this->mlr_id) . "',
                mlr_uid = '" . (int)$this->mlr_uid . "',
                mlr_title='" . DB_escapeString($this->mlr_title) . "',
                mlr_content='" . DB_escapeString($this->mlr_content) . "',
                mlr_hits='" . (int)$this->mlr_hits . "',
                mlr_date='" . DB_escapeString($this->mlr_date) . "',
                mlr_sent_time = $sent_time,
                mlr_format ='" . (int)$this->mlr_format . "',
                commentcode='" . (int)$this->commentcode . "',
                owner_id='" . (int)$this->owner_id . "',
                group_id='" . (int)$this->group_id . "',
                perm_owner='" . (int)$this->perm_owner . "',
                perm_group='" . (int)$this->perm_group . "',
                perm_members='" . (int)$this->perm_members . "',
                perm_anon='" . (int)$this->perm_anon . "',
                mlr_help = '" . DB_escapeString($this->mlr_help) . "',
                mlr_nf = '" . (int)$this->mlr_nf . "',
                mlr_inblock = '" . (int)$this->mlr_inblock . "',
                postmode = '" . DB_escapeString($this->mlr_postmode) . "',
                exp_days = '" . (int)$this->exp_days . "',
                show_unsub = '{$this->show_unsub}' " .
                $sql1;
        DB_query($sql);
        return DB_Error() ? false : true;

    }


    /**
     * Delete the current mailer record from the database.
     */
    function Delete()
    {
        global $_TABLES;

        if ($this->mlr_id == '' || !$this->isAdmin)
            return false;

        // Delete any pending emails
        DB_delete($_TABLES['mailer_queue'], 'mlr_id', $this->mlr_id);

        // Delete the mailer
        DB_delete($_TABLES['mailer'], 'mlr_id', $this->mlr_id);
        $this->mlr_id = '';

        return true;
    }


    /**
     * Determines if the current record is valid.
     *
     * @return  boolean     True if ok, False when first test fails.
     */
    function isValidRecord()
    {
        global $LANG_MLR;

        // Check that basic required fields are filled in
        if ($this->mlr_title == '')
            $this->Errors[] = $LANG_MLR['err_missing_title'];

        if ($this->mlr_content == '')
            $this->Errors[] = $LANG_MLR['err_missing_content'];

        if (!empty($this->Errors)) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * Get the current user's access level to this item.
     *
     * @return  integer     Access level
     */
    function Access()
    {
        $access = SEC_hasAccess($this->owner_id, $this->group_id,
                    $this->perm_owner, $this->perm_group,
                    $this->perm_members, $this->perm_anon);
        return $access;
    }


    /**
     * Check that the current user has at least a specified access level.
     *
     * @param   integer     Required access level, default=3
     * @return  boolean     True if the user has access, False if not.a
     * @see     Mailer::Access()
     */
    function hasAccess($level=3)
    {
        if (SEC_hasRights('mailer.admin, mailer.edit', 'OR'))
            return true;        // Admin has all rights

        return $this->Access() >= $level ? true : false;

    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id     Optional ID, current record used if zero
     * @return  string          HTML for edit form
     */
    function Edit()
    {

        global $_CONF, $_TABLES, $_USER, $_GROUPS, $_MLR_CONF,
               $LANG21, $LANG_MLR, $LANG_ACCESS, $LANG_ADMIN, $LANG24,
               $LANG_postmodes, $MESSAGE;

        $retval = '';

        $T = new Template(MLR_PI_PATH . 'templates/admin');
        if (isset($_CONF['advanced_editor']) &&
                ($_CONF['advanced_editor'] == 1)) {
            $editor_type = '_advanced';
            $postmode_adv = 'selected="selected"';
            $postmode_html = '';
        } else {
            $editor_type = '';
            $postmode_adv = '';
            $postmode_html = 'selected="selected"';
        }

        $T->set_file('form', "editor{$editor_type}.thtml");

        if ($editor_type == '_advanced') {
            $T->set_var('show_adveditor', '');
            $T->set_var('show_htmleditor', 'none');
            $post_options = "<option value=\"html\" $postmode_html>{$LANG_postmodes['html']}</option>";
            $post_options .= "<option value=\"adveditor\" $postmode_adv>{$LANG24[86]}</option>";
            $T->set_var('post_options',$post_options );
        } else {
            $T->set_var('show_adveditor', 'none');
            $T->set_var('show_htmleditor', '');
        }

        $ownername = COM_getDisplayName($this->owner_id);
        $authorname = COM_getDisplayName($this->mlr_uid);
        $curtime = COM_getUserDateTimeFormat($this->unixdate);
        $T->set_var(array(
            'change_editormode'     => 'onchange="change_editmode(this);"',
            //'comment_options'       => COM_optionList($_TABLES['commentcodes'],
            //                        'code,name', $A['commentcode']),
            'owner_username'        => DB_getItem($_TABLES['users'],
                                      'username',"uid = {$this->owner_id}"),
            'owner_name'            => $ownername,
            'owner'                 => $ownername,
            'owner_id'              => $this->owner_id,
            'group_dropdown'        => SEC_getGroupDropdown($this->group_id, 3),
            'permissions_editor'    => SEC_getPermissionsHTML(
                                $this->perm_owner, $this->perm_group,
                                $this->perm_members, $this->perm_anon),
            'start_block_editor'    => COM_startBlock($LANG_MLR['mailereditor']), '',
                                COM_getBlockTemplate('_admin_block', 'header'),
            'username'              => DB_getItem($_TABLES['users'],
                                      'username', "uid = {$this->mlr_uid}"),
            'name'                  => $authorname,
            'author'                => $authorname,
            'mlr_uid'                => $this->mlr_uid,
            'mlr_id'                 => $this->mlr_id,
            'mlr_old_id'             => $this->mlr_old_id,
            'example_url'           => COM_buildURL(MLR_URL .
                                '/index.php?page=' . $this->mlr_id),
            'mlr_help'               => $this->mlr_help,
            'inblock_checked'       => $this->mlr_inblock ? 'checked="checked"' : '',
            'block'.$this->mlr_format.'_sel' => 'selected="selected"',
            'mlr_formateddate'       => $curtime[0],
            'mlr_date'               => $curtime[1],
            'mlr_title'              => htmlspecialchars($this->mlr_title),
            'mlr_content'            => htmlspecialchars($this->mlr_content),
            'lang_allowedhtml'      => $_MLR_CONF['filter_html'] == 1 ?
                                    COM_allowedHTML() : $LANG_MLR['all_htmlr_allowed'],
            'mlr_hits'               => (int)$this->mlr_hits,
            'exp_days'               => (int)$this->exp_days,
            'mlr_hits_formatted'     => COM_numberFormat($this->mlr_hits),
            'end_block'             => COM_endBlock(
                                COM_getBlockTemplate('_admin_block', 'footer')),
            'gltoken_name'          => CSRF_TOKEN,
            'gltoken'               => SEC_createToken(),
        ) );

        if($this->mlr_sent_time != '') {
            $sent_time = date('Y-m-d H:i;s', $this->mlr_sent_time);
            $T->set_var(array(
                'mlr_sent_time_formated'     => $sent_time,
                'mlr_sent_time'              => $this->mlr_sent_time,
            ) );
        }

        if (SEC_hasRights('mailer.admin') && $this->oldid) {
            $T->set_var('candelete', 'true');
        }

        $T->parse('output','form');
        $retval = $T->finish($T->get_var('output'));
        @setcookie($_CONF['cookie_name'].'fckeditor',
                SEC_createTokenGeneral('advancededitor'),
                time() + 1200, $_CONF['cookie_path'],
               $_CONF['cookiedomain'], $_CONF['cookiesecure']);

        return $retval;

    }   // function Edit()


    /**
     * Create a formatted display-ready version of the error messages.
     *
     * @return  string      Formatted error messages.
     */
    function PrintErrors()
    {
        $retval = '';
        foreach($this->Errors as $key=>$msg) {
            $retval .= "<li>$msg</li>\n";
        }
        return $retval;
    }


    /**
     * Update the views counter.
     *
     * @param  integer $count      Number to add, one by default
     */
    function UpdateHits($count=1)
    {
        global $_TABLES;

        $count = (int)$count;

        // increment hit counter for page
        DB_query("UPDATE {$_TABLES['mailer']}
                SET mlr_hits = mlr_hits + $count
                WHERE mlr_id = '$this->mlr_id'");
    }


    /**
     * Create a printable version of the mailing.
     * Should be opened in a new window, it has no site header or footer.
     *
     * @return  string      HTML for printable page
     */
    function printPage()
    {
        global $_CONF, $LANG01;

        $T = new Template(MLR_PI_PATH . 'templates/');
        $T->set_file('print', 'printable.thtml');

        $mlr_url = COM_buildUrl(MLR_URL . '/index.php?page=' . $this->mlr_id);
        $T->set_var(array(
            'site_name'         => $_CONF['site_name'],
            'site_slogan'       => $_CONF['site_slogan'],
            'mlr_title'          => $this->mlr_title,
            'mlr_url'            => $mlr_url,
            'mlr_content'        => PLG_replacetags($this->mlr_content),
            'mlr_hits'           => COM_numberFormat($this->mlr_hits),
            'theme'             => $_CONF['theme'],
        ) );
        $T->parse('output', 'print');

        return $T->finish($T->get_var('output'));
    }


    /**
     * Create the more interactive display version of the page.
     *
     * @return  string      HTML for the page
     */
    function displayPage()
    {
        global $_CONF, $_TABLES, $_USER,
           $LANG01, $LANG11, $LANG_MLR, $_IMAGE_TYPE, $_MLR_CONF;

        $retval = '';

        if ($this->mlr_inblock == 1) {
            $header = COM_startBlock($this->mlr_title,
                        $this->mlr_help,
                        COM_getBlockTemplate('_mailer_block', 'header'));
            $footer = COM_endBlock(COM_getBlockTemplate('_mailer_block',
                                                       'footer'));
        } else {
            $header = '';
            $footer = '';
        }

        $T = new Template(MLR_PI_PATH . 'templates/');
        $T->set_file('page', 'mailer.thtml');

        if ($_CONF['hideprintericon'] == 0) {
            $icon_url = $_CONF['layout_url'] . '/images/print.' . $_IMAGE_TYPE;
            $attr = array('title' => $LANG_MLR['printable_format']);
            $printicon = COM_createImage($icon_url, $LANG01[65], $attr);
            $print_url = COM_buildURL(MLR_URL .'/index.php?page=' .
                        $this->mlr_id . '&amp;mode=print');
            $icon = COM_createLink($printicon, $print_url);
            $T->set_var('print_icon', $icon);
        }
        if ($this->hasAccess(3)) {
            $icon_url = $_CONF['layout_url'] . '/images/edit.' . $_IMAGE_TYPE;
            $attr = array('title' => $LANG_MLR['edit']);
            $editiconhtml = COM_createImage($icon_url, $LANG_MLR['edit'], $attr);
            $attr = array('class' => 'editlink','title' => $LANG_MLR['edit']);
            $url = MLR_ADMIN_URL . '/index.php?edit=x&amp;mlr_id=' . $this->mlr_id;
            $icon = '&nbsp;' . COM_createLink($editiconhtml, $url, $attr);
            $T->set_var('edit_icon', $icon);
        }

        $curtime = COM_getUserDateTimeFormat($this->mlr_date);
        $lastupdate = $LANG_MLR['lastupdated']. ' ' . $curtime[0];
        $T->set_var(array(
                'content'           => $this->mlr_content,
                'title'             => $this->mlr_title,
                'info_separator'    => 'hidden',
                'mlr_date'          => $curtime[0],
        ) );

        if ($_MLR_CONF['show_date'] == 1) {
            $T->set_var('lastupdate', $lastupdate);
        }

        $retval = $header . $T->finish($T->parse('output', 'page')) . $footer;
        return $retval;
    }


    /**
     * Queue this mailer for sending.
     * Gathers all subscribed email addresses and addes them to the queue
     * table.
     *
     * @param   string  $mlr_id     Optional mailer ID, current if empty
     */
    function queueIt($mlr_id='')
    {
        global $_TABLES;

        // If no mlr_id supplied, use the current object if instantiated
        if ($mlr_id == '' && is_object($this)) {
            $mlr_id = $this->mlr_id;
        }
        // Still no mlr_id?  Then there's a problem
        if ($mlr_id == '') return;

        $mlr_id = COM_sanitizeID($mlr_id, false);
        $sql = "INSERT INTO {$_TABLES['mailer_queue']}
                (mlr_id, email)
            SELECT '$mlr_id', email
            FROM {$_TABLES['mailer_emails']}
            WHERE status = " . MLR_STAT_ACTIVE;
        DB_query($sql);
    }


    /**
     * Send the mailing to a single address, current user by default.
     * Doesn't use COM_mail since this needs to add headers specific to
     * mailing lists.
     *
     * @param   string  $email  Optional email address
     */
    function mailIt($email='', $token='')
    {
        global $LANG_MLR, $_CONF, $_USER, $_TABLES, $_MLR_CONF;

        //MLR_sendLock($mlr_id);

        // Don't mail invalid mailers
        if (!$this->isValidRecord()) return false;

        require_once $_CONF['path'] . 'lib/phpmailer/class.phpmailer.php';

        if ($email == '') $email = $_USER['email'];

        // Get the users' token for the unsubscribe link
        if (empty($token)) {
            $token = DB_getItem($_TABLES['mailer_emails'], 'token',
                "email='" . DB_escapeString($email) . "'");
        }
        $unsub_url =  MLR_URL . '/index.php?view=unsub&email=' .
            urlencode($email) . '&amp;token=' . urlencode($token) .
            '&amp;mlr_id=' . urlencode($this->mlr_id);
        $unsub_link = COM_createLink($unsub_url, $unsub_url);

        $T = new Template(MLR_PI_PATH . 'templates/');
        $T->set_file('msg', 'mailer_email.thtml');
        $T->set_var(array(
            'content'   => $this->mlr_content,
            'pi_url'    => MLR_URL,
            'mlr_id'    => $this->mlr_id,
            'token'     => $token,
            'email'     => $email,
            'unsub_url' => $unsub_link,
            'show_unsub' => $this->show_unsub ? 'true' : '',
        ) );
        $T->parse('output', 'msg');
        $message = $T->finish($T->get_var('output'));
        $altbody = strip_tags($message);

        // Create the "from" address using the site or noreply mail address
        $fromEmail = isset($_CONF[$_MLR_CONF['email_from']]) ?
            $_CONF[$_MLR_CONF['email_from']] : $_CONF['noreply_mail'];
        //$from = array($fromEmail, $_CONF['site_name']);

        // Append static footer text to the mailer
        /*$body .= sprintf($LANG_MLR['trouble_viewing'],
                        MLR_URL . "/index.php?page={$this->mlr_id}");
        $body .= '<br /><a href="' . MLR_URL .
            "/index.php?view=delete&email=$email&token=$token\">" .
            $LANG_MLR['unsubscribe'] . '</a>';
        $altbody = strip_tags($body);*/

        // Create the "from" address using the site or noreply mail address
        /*$fromEmail = isset($_CONF[$_MLR_CONF['email_from']]) ?
            $_CONF[$_MLR_CONF['email_from']] : $_CONF['noreply_mail'];
        //$from = array($fromEmail, $_CONF['site_name']);*/

        $subject = trim($this->mlr_title);
        $subject = COM_emailEscape($subject);

        $mail = new PHPMailer();
        $mail->SetLanguage('en',$_CONF['path'].'lib/phpmailer/language/');
        $mail->CharSet = COM_getCharset();
        if ($_CONF['mail_backend'] == 'smtp') {
            $mail->IsSMTP();
            $mail->Host     = $_CONF['mail_smtp_host'];
            $mail->Port     = $_CONF['mail_smtp_port'];
            if ($_CONF['mail_smtp_secure'] != 'none') {
                $mail->SMTPSecure = $_CONF['mail_smtp_secure'];
            }
            if ($_CONF['mail_smtp_auth']) {
                $mail->SMTPAuth   = true;
                $mail->Username = $_CONF['mail_smtp_username'];
                $mail->Password = $_CONF['mail_smtp_password'];
            }
            $mail->Mailer = "smtp";

        } elseif ($_CONF['mail_backend'] == 'sendmail') {
            $mail->Mailer = "sendmail";
            $mail->Sendmail = $_CONF['mail_sendmail_path'];
        } else {
            $mail->Mailer = "mail";
        }
        $mail->WordWrap = 76;
        $mail->IsHTML($this->mailHTML);
        if ($this->mailHTML) {
            $mail->Body = COM_filterHTML($message);
        } else {
            $mail->Body = $message;
        }
        $mail->AltBody = $altBody;

        $mail->Subject = $subject;
        $mail->From = $fromEmail;
        $mail->FromName = $_CONF['site_name'];

        $mail->AddCustomHeader('List-ID:Announcements from ' .
                $_CONF['site_name']);
        $mail->AddCustomHeader('List-Archive:<' . MLR_URL . '>Prior Mailings');
        $mail->AddCustomHeader('X-Unsubscribe-Web:<' . $unsub_url . '>');
        $mail->AddCustomHeader('List-Unsubscribe:<' . $unsub_url . '>');

        $mail->AddAddress($email);

        if(!$mail->Send()) {
            COM_errorLog("Email Error: " . $mail->ErrorInfo);
            return false;
        }
        return true;

        /*if (!COM_mail($email, $this->mlr_title, $body, $from, true, 0, '', $altbody)) {
            COM_errorLog( "There has been a mail error sending to " . $email, 1);
        }*/

    }

}
