<?php
/**
 * Class to manage mailing lists.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2020 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Models;
use Mailer\Config;


/**
 * Class for mailer items.
 * @package mailer
 */
class Mailer
{
    /** Indicate whether the current user is an administrator/
     * @var boolean */
    private $isAdmin = 0;

    /** Flag to indicate whether the mailer item is new or an edit/
     * @var boolean */
    public $isNew = 1;

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
    public function __construct($mlr_id='')
    {
        global $_USER, $_CONF;

        $this->isNew = true;

        $mlr_id = COM_sanitizeID($mlr_id, false);
        if ($mlr_id == '') {
            $this->mlr_id = COM_makeSid();
            $this->mlr_uid = $_USER['uid'];
            $this->mlr_title = '';
            $this->mlr_hits = 0;
            $this->mlr_content = '';
            $this->mlr_date = $_CONF['_now']->toMySQL(true);
            $this->mlr_help = '';
            $this->mlr_inblock = 0;
            $this->mlr_nf = 0;
            $this->postmode = 'text';
            $this->mlr_format = Config::get('displayblocks');
            $this->exp_days = Config::get('exp_days');
            $this->commentcode = -1;
            $this->unixdate = 0;
            $this->owner_id = $_USER['uid'];
            $this->group_id = isset($_GROUPS['mailer Admin']) ?
                    $_GROUPS['mailer Admin'] :
                    SEC_getFeatureGroup('mailer.edit');
            $A = array();
            SEC_setDefaultPermissions($A, Config::get('default_permissions'));
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


    public function withID($id)
    {
        $this->mlr_id = $id;
        return $this;
    }


    public function getID()
    {
        return $this->mlr_id;
    }


    public function withTitle($title)
    {
        $this->mlr_title = $title;
        return $this;
    }


    public function getTitle()
    {
        return $this->mlr_title;
    }

    public function withContent($content)
    {
        $this->mlr_content = $content;
        return $this;
    }

    public function getContent()
    {
        return $this->mlr_content;
    }

    public function withDate($date)
    {
        $this->mlr_date = $date;
        return $This;
    }

    public function getDate()
    {
        return $this->date;
    }



    /**
     * Set a property's value.
     *
     * @param   string  $var    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    public function __set($var, $value='')
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
    public function __get($var)
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
    public function SetVars($row, $fromDB=false)
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
    public function Read($mlr_id = '')
    {
        global $_TABLES;

        if ($mlr_id == '') {
            $mlr_id = $this->mlr_id;
        } else {
            $mlr_id = COM_sanitizeID($mlr_id, false);
        }

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
    public function Save($A = '')
    {
        global $_TABLES, $_CONF;

        if (is_array($A)) {
            $this->SetVars($A, false);
        }

        if (Config::get('censor') == 1) {
            $this->mlr_content = COM_checkWords($this->mlr_content);
            $this->mlr_title = COM_checkWords($this->mlr_title);
        }
        if (Config::get('filter_html') == 1) {
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

        $sql .= "mlr_id = '" . DB_escapeString($this->mlr_id) . "',
                mlr_uid = '" . (int)$this->mlr_uid . "',
                mlr_title='" . DB_escapeString($this->mlr_title) . "',
                mlr_content='" . DB_escapeString($this->mlr_content) . "',
                mlr_hits='" . (int)$this->mlr_hits . "',
                mlr_date='" . $_CONF['_now']->toMySQL(true) . "',
                mlr_sent_time = $this->mlr_sent_time,
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

        // Queue immediately or send a test if requested
        if (isset($A['mlr_sendnow'])) {
            Queue::addMailer($this->mlr_id);
        } elseif (isset($A['mlr_sendtest'])) {
            $this->mailIt();
        }
        return DB_Error() ? false : true;
    }


    /**
     * Delete the current mailer record from the database.
     */
    public function Delete()
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
    public function isValidRecord()
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
    public function Access()
    {
        $access = SEC_hasAccess(
            $this->owner_id, $this->group_id,
            $this->perm_owner, $this->perm_group,
            $this->perm_members, $this->perm_anon
        );
        return $access;
    }


    /**
     * Check that the current user has at least a specified access level.
     *
     * @param   integer     Required access level, default=3
     * @return  boolean     True if the user has access, False if not.a
     * @see     Mailer::Access()
     */
    public function hasAccess($level=3)
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
    public function Edit()
    {

        global $_CONF, $_TABLES, $_USER, $_GROUPS,
               $LANG21, $LANG_MLR, $LANG_ACCESS, $LANG_ADMIN, $LANG24,
               $LANG_postmodes, $MESSAGE;

        $retval = '';

        $T = new \Template(__DIR__ . '/../templates/admin');
        if (
            isset($_CONF['advanced_editor']) &&
            ($_CONF['advanced_editor'] == 1)
        ) {
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
            'lang_allowedhtml'      => Config::get('filter_html') == 1 ?
                                    COM_allowedHTML() : $LANG_MLR['all_html_allowed'],
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
    public function PrintErrors()
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
    public function UpdateHits($count=1)
    {
        global $_TABLES;

        $count = (int)$count;

        // increment hit counter for page
        DB_query("UPDATE {$_TABLES['mailer']}
                SET mlr_hits = mlr_hits + $count
                WHERE mlr_id = '$this->mlr_id'");
    }


    public function updateSentTime($ts=NULL)
    {
        global $_TABLES, $_CONF;

        if ($ts === NULL) {
            $ts = $_CONF['_now']->toUnix();
        }
        $ts = (int)$ts;
        DB_query("UPDATE {$_TABLES['mailer']} SET 
            mlr_sent_time= $ts
            WHERE mlr_id = '{$this->mlr_id}'");
    }


    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Create a printable version of the mailing.
     * Should be opened in a new window, it has no site header or footer.
     *
     * @return  string      HTML for printable page
     */
    public function printPage()
    {
        global $_CONF, $LANG01;

        $T = new \Template(MLR_PI_PATH . 'templates/');
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
    public function displayPage()
    {
        global $_CONF, $_TABLES, $_USER,
           $LANG01, $LANG11, $LANG_MLR, $_IMAGE_TYPE;

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

        $T = new \Template(MLR_PI_PATH . 'templates/');
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

        if (Config::get('show_date') == 1) {
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
    public function queueIt($emails=NULL)
    {
        global $_TABLES;

        if ($this->mlr_id == '') return false;

        $mlr_id = COM_sanitizeID($mlr_id, false);
        if ($emails === NULL) {
            $values = "SELECT '{$this->mlr_id}', email
            FROM {$_TABLES['mailer_emails']}
            WHERE status = " . Status::ACTIVE;
        } elseif (is_array($emails)) {
            $vals = array();
            foreach ($emails as $email) {
                $vals[] = "('{$mlr_id}', '" . DB_escapeString($email) . "')";
            }
            $values = ' VALUES ' . implode(',', $vals);
        } else {
            return false;
        }
        $sql = "INSERT INTO {$_TABLES['mailer_queue']}
                (mlr_id, email) $values";
        DB_query($sql);
        return DB_error() ? false : true;
    }


    /**
     * Send the mailing to a single address, current user by default.
     * Doesn't use COM_mail since this needs to add headers specific to
     * mailing lists.
     *
     * @param   string  $email  Optional email address
     */
    public function mailIt($email='', $token='')
    {
        global $LANG_MLR, $_CONF, $_USER, $_TABLES;

        // Don't mail invalid mailers
        if (!$this->isValidRecord()) return false;

        if ($email == '') $email = $_USER['email'];

        // Get the users' token for the unsubscribe link
        if (empty($token)) {
            $token = DB_getItem(
                $_TABLES['mailer_emails'],
                'token',
                "email='" . DB_escapeString($email) . "'"
            );
        }
        $unsub_url =  MLR_URL . '/index.php?view=unsub&email=' .
            urlencode($email) . '&amp;token=' . urlencode($token) .
            '&amp;mlr_id=' . urlencode($this->mlr_id);
        $unsub_link = COM_createLink($unsub_url, $unsub_url);

        $T = new \Template(__DIR__ . '/../templates/');
        $T->set_file('msg', 'mailer_email.thtml');
        $T->set_var(array(
            'content'   => $this->mlr_content,
            'pi_url'    => Config::get('url'),
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
        $fromEmail = isset($_CONF[Config::get('email_from')]) ?
            $_CONF[Config::get('email_from')] : $_CONF['noreply_mail'];

        $subject = trim($this->mlr_title);
        $subject = COM_emailEscape($subject);

        $mail = new \PHPMailer();
        $mail->SetLanguage('en');
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
        $mail->AddCustomHeader('List-Archive:<' . Config::get('url') . '>Prior Mailings');
        $mail->AddCustomHeader('X-Unsubscribe-Web:<' . $unsub_url . '>');
        $mail->AddCustomHeader('List-Unsubscribe:<' . $unsub_url . '>');

        $mail->AddAddress($email);

        if(!$mail->Send()) {
            COM_errorLog("Email Error: " . $mail->ErrorInfo);
            return false;
        }
        return true;
    }
    
    
    /**
     * List all the saved messages.
     *
     * @return  string      HTML for admin list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $_IMAGE_TYPE, $LANG_ADMIN, $LANG_MLR;

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
                'text' => $LANG_MLR['date'],
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
            $LANG_MLR['mailerlist'] . ' ' . Config::get('pi_name') . ' v. ' .
                Config::get('pi_version'),
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
            'mailer_listmailers',
            array(__CLASS__, 'getListField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', $options
        );
        $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $retval;
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
    public static function getListField($fieldname, $fieldvalue, $A, $icon_arr)
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
                '<i class="uk-icon uk-icon-envelope"></i>',
                $admin_url . "/index.php?sendnow=x&amp;mlr_id={$A['mlr_id']}",
                array(
                    'onclick' => "return confirm('{$LANG_MLR['conf_sendnow']}');",
                )
            );
            break;

        case 'delete':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-remove uk-text-danger"></i>',
                $admin_url . "/index.php?delete=x&amp;mlr_id={$A['mlr_id']}",
                array(
                    'onclick' => "return confirm('{$LANG_MLR['conf_delete']}');",
                )
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
        case 'mlr_date':
            if ($fieldvalue == 0) {
                $retval = $LANG_MLR['never'];
            } else {
                $dt = new \Date($fieldvalue, $_CONF['timezone']);
                $retval = $dt->toMySQL(true);
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

}