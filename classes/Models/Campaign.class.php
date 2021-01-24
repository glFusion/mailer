<?php
/**
 * Class to manage mailing campaigns.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2021 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Models;
use Mailer\Config;
use Mailer\API;


/**
 * Class for mailer items.
 * @package mailer
 */
class Campaign
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

    /** Mailer record ID.
     * @var string */
    private $mlr_id = '';

    /** Mailer title/subject.
     * @var string */
    private $mlr_title = '';

    /** Owner (author) ID.
     * @var integer */
    private $owner_id = 0;

    /** Group ID for access control.
     * @var integer */
    private $group_id = 13;

    /** Owner permission.
     * @var integer */
    private $perm_owner = 3;

    /** Group permission.
     * @var integer */
    private $perm_group = 3;

    /** Members permission.
     * @var integer */
    private $perm_members = 2;

    /** Anonymous permission.
     * @var integer */
    private $perm_anon = 2;

    /** Days to keep mailer online.
     * @var integer */
    private $exp_days = 90;

    /** Last sent timestamp.
     * @var integer */
    private $mlr_sent_time = 0;

    /** Mailer body content.
     * @var string */
    private $mlr_content = '';

    /** Email provider name.
     * @var string */
    private $provider = '';

    /** Email provider campaign ID.
     * @var string */
    private $provider_mlr_id = '';

    /** Status of test email.
     * @var boolean */
    private $tested = 0;


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

        $mlr_id = COM_sanitizeId($mlr_id, false);
        if ($mlr_id == '') {
            $this->mlr_title = '';
            $this->mlr_content = '';
            $this->mlr_date = $_CONF['_now']->toUnix();
            $this->exp_days = Config::get('exp_days');
            $this->owner_id = $_USER['uid'];
            $this->group_id = SEC_getFeatureGroup('mailer.edit');
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
            }
        }
        $this->isAdmin = SEC_hasRights('mailer.admin') ? 1 : 0;
    }


    /**
     * Set the mailer record ID.
     *
     * @param   string  $id     Mailer ID
     * @return  object  $this
     */
    public function withID($id)
    {
        $this->mlr_id = $id;
        return $this;
    }


    /**
     * Get the mailer record ID.
     *
     * @return  string      Record ID
     */
    public function getID()
    {
        return $this->mlr_id;
    }


    /**
     * Set the mailer title.
     *
     * @param   string  $title  Title string
     * @return  object  $this
     */
    public function withTitle($title)
    {
        $this->mlr_title = $title;
        return $this;
    }


    /**
     * Get the mailer title.
     *
     * @return  string      Title string
     */
    public function getTitle()
    {
        return $this->mlr_title;
    }


    /**
     * Set the mailer content.
     *
     * @param   string  $content    Content
     * @return  object  $this
     */
    public function withContent($content)
    {
        $this->mlr_content = $content;
        return $this;
    }


    /**
     * Get the mailer content.
     *
     * @return  string      Mailer content
     */
    public function getContent()
    {
        return $this->mlr_content;
    }


    /**
     * Set the mailer date.
     *
     * @param   string  $date   Date string
     * @return  object  $this
     */
    public function withDate($date)
    {
        $this->mlr_date = (int)$date;
        return $this;
    }


    /**
     * Get the mailer date.
     *
     * @return  string      Mailer date
     */
    public function getDate()
    {
        return $this->date;
    }


    /**
     * Check if this mailer is HTML-formatted.
     *
     * @return  boolean     True if HTML, False if Text
     */
    public function isHTML()
    {
        return $this->mailHTML;
    }


    /**
     * Set a property's value.
     *
     * @param   string  $var    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    public function X__set($var, $value='')
    {
        switch ($var) {

        case 'mlr_hits':
        case 'mlr_sent_time':
        case 'perm_owner':
        case 'perm_group':
        case 'perm_members':
        case 'perm_anon':
        case 'owner_id':
        case 'group_id':
            // Integer values
            $this->$var = (int)$value;
            break;

        case 'exp_days':
            $this->properties[$var] = (int)$value;
            if ($this->$var < 0) $this->$var = 0;
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
        echo $var;die;
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
    public function setVars($row, $fromDB=false)
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

        $sql = "SELECT mlr.*, prv.provider, prv.provider_mlr_id, prv.tested
            FROM {$_TABLES['mailer_campaigns']} mlr
            LEFT JOIN {$_TABLES['mailer_provider_campaigns']} prv
                ON prv.mlr_id = mlr.mlr_id AND prv.provider = '" . Config::get('provider') .
            "' WHERE mlr.mlr_id ='" . DB_escapeString($mlr_id) . "'" .
            COM_getPermSQL('AND', 0, 2);
        $result = DB_query($sql);
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->setVars($row, true);
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
            $this->setVars($A, false);
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
        if ($this->mlr_id == '') {
            $sql1 = "INSERT INTO {$_TABLES['mailer_campaigns']} SET ";
            $sql3 = '';
            $this->mlr_id = COM_makeSid();
        } else {
            $sql1 = "UPDATE {$_TABLES['mailer_campaigns']} SET ";
            $sql3 = " WHERE mlr_id = '" . $this->getID() . "'";
        }

        $sql2 = "mlr_id = " . $this->getID() . ",
                mlr_title='" . DB_escapeString($this->mlr_title) . "',
                mlr_content='" . DB_escapeString($this->mlr_content) . "',
                mlr_date = UNIX_TIMESTAMP(),
                mlr_sent_time = " . (int)$this->mlr_sent_time . ",
                owner_id='" . (int)$this->owner_id . "',
                group_id='" . (int)$this->group_id . "',
                perm_owner='" . (int)$this->perm_owner . "',
                perm_group='" . (int)$this->perm_group . "',
                perm_members='" . (int)$this->perm_members . "',
                perm_anon='" . (int)$this->perm_anon . "',
                exp_days = '" . (int)$this->exp_days . "'";
        $sql = $sql1 . $sql2 . $sql3;
        DB_query($sql);

        if (!DB_error()) {
            $API = API::getInstance();
            $status = $API->createCampaign($this);
            if ($status) {
                $API->saveCampaignID();
            }
        }

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
     *
     * @return  boolean     True after deleteion, False if unable to delete
     */
    public function Delete()
    {
        global $_TABLES;

        if ($this->isNew() == '' || !$this->isAdmin) {
            return false;
        }

        // Delete any pending emails
        DB_delete($_TABLES['mailer_queue'], 'mlr_id', $this->mlr_id);

        // Delete from the provider cross-ref table
        // TODO: Maybe delete from the remote provider as well?
        DB_delete($_TABLES['mailer_provider_campaigns'], 'mlr_id', $this->mlr_id);

        // Delete the mailer
        DB_delete($_TABLES['mailer_campaigns'], 'mlr_id', $this->mlr_id);
        $this->mlr_id = '';

        return true;
    }


    /**
     * Purge expired mailings.
     */
    public static function purgeExpired()
    {
        global $_CONF, $_TABLES;

        /*$select = "DELETE FROM {$_TABLES['mailer_campaigns']}
            WHERE exp_days > 0
            AND '" . $_CONF['_now']->toMySQL(true) .
            "' > DATE_ADD(mlr_date, INTERVAL exp_days DAY)";*/
        $sql = "DELETE t1, t2
            FROM {$_TABLES['mailer_campaigns']} t1
            INNER JOIN {$_TABLES['mailer_provider_campaigns']} t2
                ON t1.mlr_id = t2.mlr_id
            WHERE t1.exp_days > 0
            AND '" . $_CONF['_now']->toMySQL(true) .
            "' > DATE_ADD(t1.mlr_date, INTERVAL t1.exp_days DAY)";
        echo $sql;die;
        DB_query($sql, 1);
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
        global $_CONF, $_TABLES, $_USER,
               $LANG_MLR, $LANG_ACCESS, $LANG_ADMIN, $LANG24;

        $retval = '';

        $T = new \Template(Config::get('pi_path') . 'templates/admin');
        $T->set_file('form', "editor.thtml");
        // Set up the wysiwyg editor, if available
        $pi_name = Config::PI_NAME;
        $tpl_var = $pi_name . '_editor';
        SEC_setCookie(
            $_CONF['cookie_name'].'adveditor',
            SEC_createTokenGeneral('advancededitor'),
            time() + 1200,
            $_CONF['cookie_path'],
            $_CONF['cookiedomain'],
            $_CONF['cookiesecure'],
            false
        );
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor($pi_name, $tpl_var, 'ckeditor_' . $pi_name . '.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor($pi_name, $tpl_var, 'tinymce_' . $pi_name . '.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        $authorname = COM_getDisplayName($this->owner_id);
        $mlrtime = COM_getUserDateTimeFormat($this->mlr_date);
        $T->set_var(array(
            'owner_username'        => DB_getItem(
                $_TABLES['users'],
                'username',
                "uid = {$this->owner_id}"
            ),
            'owner_id'              => $this->owner_id,
            'group_dropdown'        => SEC_getGroupDropdown($this->group_id, 3),
            'permissions_editor'    => SEC_getPermissionsHTML(
                                $this->perm_owner, $this->perm_group,
                                $this->perm_members, $this->perm_anon),
            'start_block_editor'    => COM_startBlock($LANG_MLR['mailereditor']), '',
                                COM_getBlockTemplate('_admin_block', 'header'),
            'owner_name'            => $authorname,
            'mlr_id'                => $this->mlr_id,
            'example_url'           => COM_buildURL(Config::get('url') .
                                '/index.php?page=' . $this->mlr_id),
            'mlr_formateddate'       => $mlrtime[0],
            'mlr_date'               => $mlrtime[1],
            'mlr_title'              => htmlspecialchars($this->mlr_title),
            'content'               => htmlspecialchars($this->mlr_content),
            'lang_allowedhtml'      => Config::get('filter_html') == 1 ?
                                    COM_allowedHTML() : $LANG_MLR['all_html_allowed'],
            'exp_days'               => (int)$this->exp_days,
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

        if (SEC_hasRights('mailer.admin') && $this->mlr_id) {
            $T->set_var('candelete', 'true');
        }

        $T->parse('output','form');
        $retval = $T->finish($T->get_var('output'));
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
     * Update the sent timestamp to the current time.
     *
     * @return  object  $this
     */
    public function updateSentTime()
    {
        global $_TABLES, $_CONF;

        if ($ts === NULL) {
            $ts = $_CONF['_now']->toUnix();
        }
        $ts = (int)$ts;
        DB_query("UPDATE {$_TABLES['mailer_campaigns']} SET
            mlr_sent_time= UNIX_TIMESTAMP()
            WHERE mlr_id = '{$this->mlr_id}'");
        return $this;
    }


    /**
     * Quick way to check if this is a new record.
     *
     * @return  boolean     1 if new, 0 if not.
     */
    public function isNew()
    {
        return $this->mlr_id == '' ? 1 : 0;
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

        $T = new \Template(Config::get('pi_path') . 'templates/');
        $T->set_file('print', 'printable.thtml');

        $mlr_url = COM_buildUrl(Config::get('url') . '/index.php?page=' . $this->mlr_id);
        $T->set_var(array(
            'site_name'         => $_CONF['site_name'],
            'site_slogan'       => $_CONF['site_slogan'],
            'mlr_title'          => $this->mlr_title,
            'mlr_url'            => $mlr_url,
            'mlr_content'        => PLG_replacetags($this->mlr_content),
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
        $T = new \Template(Config::get('pi_path') . 'templates/');
        $T->set_file('page', 'mailer.thtml');
        $curtime = COM_getUserDateTimeFormat($this->mlr_date);
        $lastupdate = $LANG_MLR['lastupdated']. ' ' . $curtime[0];
        $T->set_var(array(
            'content'           => $this->mlr_content,
            'title'             => $this->mlr_title,
            'info_separator'    => 'hidden',
            'mlr_date'          => $curtime[0],
            'mlr_id'            => $this->mlr_id,
            'can_print'         => $_CONF['hideprintericon'] == 0,
            'can_edit'          => $this->hasAccess(3),
        ) );

        if (Config::get('show_date') == 1) {
            $T->set_var('lastupdate', $lastupdate);
        }

        $retval = $T->finish($T->parse('output', 'page'));
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
        if ($this->mlr_id == '') {
            return false;
        }
        $API = API::getInstance();
        return $API->queueEmail($this, $emails);
    }


    /**
     * Send a test email.
     */
    public function sendTest()
    {
        global $_TABLES;

        if ($this->mlr_id == '') {
            return false;
        }
        $API = API::getInstance();
        $camp_id = DB_getItem(
            $_TABLES['mailer_provider_campaigns'],
            'provider_mlr_id',
            "mlr_id = '{$this->getID()}' AND provider = '{$API->getName()}'"
        );
        if (empty($camp_id)) {
            $camp_id = $API->createCampaign($this);
            if (empty($camp_id)) {
                return false;
            }
        }
        // Now there should be a campaign ID
        if ($API->sendTest($camp_id)) {
            DB_query("UPDATE {$_TABLES['mailer_provider_campaigns']}
                SET tested = 1
                WHERE mlr_id = '{$this->getID()}' AND provider = '{$API->getName()}'"
            );
            return true;
        } else {
            return false;
        }
    }


    /**
     * Send the mailing to a single address, current user by default.
     * Doesn't use COM_mail since this needs to add headers specific to
     * mailing lists.
     *
     * @param   string  $email  Optional email address
     * @param   string  $token  Optional token
     * @return  integer     Status code from API::sendEmail()
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
                $_TABLES['mailer_subscribers'],
                'token',
                "email='" . DB_escapeString($email) . "'"
            );
        }

        $API = API::getInstance();
        return $API->sendEmail($this, $email, $token);
    }


    /**
     * Change the ownership of all mailers when a user is deleted.
     *
     * @param   integer $old_uid    Original user ID
     * @param   integer $new_uid    New user ID, 0 for any root group member
     */
    public static function changeOwner($old_uid, $new_uid = 0)
    {
        global $_TABLES;

        if (
            DB_count($_TABLES['mailer_campaigns'], 'owner_id', $old_uid) == 0
        ) {
            // No mailings owned by this user, nothing to do.
            return;
        }

        if ($new_uid == 0) {
            // assign ownership to a user from the Root group
            $res = DB_query(
                "SELECT DISTINCT ug_uid
                FROM {$_TABLES['group_assignments']}
                WHERE ug_main_grp_id = 1
                    AND ug_uid IS NOT NULL
                    AND ug_uid <> $old_uid
                ORDER BY ug_uid ASC LIMIT 1"
            );
            if (DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
                $new_uid = (int)$A['ug_uid'];
            }
        }
        if ($new_uid > 0) {
            DB_query(
                "UPDATE {$_TABLES['mailer_campaigns']} SET
                    owner_id = $rootuser
                WHERE owner_id = $uid"
            );
        } else {
            COM_errorLog("Mailer: Error finding root user");
        }
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
                'text' => 'Tested',
                'field' => 'tested',
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
                'field' => 'owner_id',
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

        $text_arr = array(
            'has_extras' => true,
            'form_url' => Config::get('admin_url') . '/index.php?mailers=x',
        );

        $provider_id = API::getInstance()->getName();
        $query_arr = array(
            'table' => 'mailer',
            'sql' => "SELECT mlr.*, prv.provider, prv.provider_mlr_id, prv.tested
                FROM {$_TABLES['mailer_campaigns']} mlr
                LEFT JOIN {$_TABLES['mailer_provider_campaigns']} prv
                    ON prv.mlr_id = mlr.mlr_id AND prv.provider = '$provider_id'
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

        case 'tested':
            if ($fieldvalue == 1) {
                $icon = 'uk-icon-check';
                $cls = 'uk-text-success';
            } else {
                $icon = 'uk-icon-remove';
                $cls = '';
            }
            $retval = COM_createLink(
                '<i class="uk-icon ' . $icon . '"></i>',
                $admin_url . '/index.php?sendtest=' . $A['mlr_id'],
                array(
                    'class' => 'tooltip ' . $cls,
                    'title' => 'Send a test now',
                )
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
                $_CONF['site_url'] . '/mailer/index.php?mode=view&mor_id=' . $A['mlr_id']
            );
            $retval = COM_createLink(
                $A['mlr_title'],
                $url,
                array('title'=>$LANG_MLR['title_display'])
            );
            break;

        case 'owner_id':
            $retval = COM_getDisplayName ($A['owner_id']);
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

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
