<?php
/**
 * Layout for a Subscriber record.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2021 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Models;
use Mailer\Models\Status;
use Mailer\Logger;
use Mailer\API;
use Mailer\Config;


/**
 * A standard representation of the interesting list info fields.
 * @package mailchimp
 */
class Subscriber
{
    /** DB record ID.
     * @var integer */
    private $id = 0;

    /** glFusion user ID.
     * @var integer */
    private $uid = 1;

   /** Date registered.
     * @var string */
    private $dt_reg = '';

    /** Email domain, for grouping outbound emails.
     * @var string */
    private $domain = '';

    /** Full email address.
     * @var string */
    private $email_address = '';

    /** Subscrition status (unsubscribed, pending, active, etc.).
     * @var string */
    private $status = 0;

    /** Security token to validate unsubscribe requests.
     * @var string */
    private $token = '';

    /** Merge fields.
     * @var array */
    private $_merge_fields = array();

    /** User's full name, if known as a glFusion user.
     * @var string */
    private $_fullname = '';

    /** Temporary attributes array used to construct merge fields.
     * @var array */
    private $_attributes = array();

    /** Old email address.
     * Used when changing addresses so that the original subscriber can
     * be found at the API provider.
     * @var string */
    private $_old_email = '';


    /**
     * Initialize the parameters array.
     */
    public function __construct($A = NULL)
    {
        if (is_array($A)) {
            // assumes a DB record or identical layout
            $this->withID($A['id'])
                 ->withUid($A['uid'])
                 ->withEmail($A['email'])
                 ->withRegDate($A['dt_reg'])
                 ->withToken($A['token'])
                 ->withFullname($A['uid'] == 1 ? '' : $A['fullname'])
                 ->withStatus($A['status']);
            $this->_old_email = $A['email'];
        }
    }


    /**
     * Get a subscriber record by user ID.
     *
     * @param   string  $email  Email address
     * @return  object  self
     */
    public static function getByUid($uid)
    {
        $retval = self::_create('uid ', (int)$uid);
        return $retval;
    }


    /**
     * Get a subscriber record by record ID.
     *
     * @param   string  $email  Email address
     * @return  object  self
     */
    public static function getById($id)
    {
        global $_TABLES;

        return self::_create('id', $id);
    }


    /**
     * Get a subscriber record by Email address.
     *
     * @param   string  $email  Email address
     * @return  object  self
     */
    public static function getByEmail($email)
    {
        $retval = self::_create('email', DB_escapeString($email))
            ->withEmail($email)
            ->withOldEmail($email);
        return $retval;
    }


    /**
     * Load a subscriber object from the DB based on a field and value.
     *
     * @param   string  $fld    DB field to query
     * @param   mixed   $value  Value of the field
     * @return  object      Subscriber object
     */
    private static function _create($fld, $value)
    {
        global $_TABLES;

         $sql = "SELECT sub.*, u.uid as gl_uid, u.fullname
            FROM {$_TABLES['mailer_subscribers']} sub
            LEFT JOIN {$_TABLES['users']} u
            ON u.uid = sub.uid WHERE sub.{$fld} = '$value'
            LIMIT 1";
        $res = DB_query($sql);
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $retval = new self($A);
            if ($A['uid'] == 1 && $A['gl_uid'] > 1) {
                // Link the user ID. User may have joined after subscribing.
                $retval->withUid($A['gl_uid']);
            }
        } else {
            // Not found, try to get information if this is a site member.
            $retval = (new self)
                ->withRegDate()
                ->withToken();
            $sql = "SELECT uid, fullname, email
                FROM {$_TABLES['users']}
                WHERE $fld = '$value'";
            $res = DB_query($sql);
            if (DB_numRows($res) == 1) {
                $B = DB_fetchArray($res, false);
                $retval->withUid($B['uid'])
                       ->withEmail($B['email'])
                       ->withFullname($B['fullname']);
            }
        }
        return $retval;
    }


    /**
     * Add email to subscriber list.
     * This is called from the web page and uses the API to update the
     * list provider.
     *
     * @return  integer Status
     */
    public function subscribe($status = NULL)
    {
        if ($status !== NULL) {
            $this->status = (int)$status;
        } else {
            // Do nothing if this address is blacklisted,
            // unless forced by admin.
            if ($this->status == Status::BLACKLIST) {
                return Status::SUB_BLACKLIST;
            }
            $this->status = Status::PENDING;
        }
        $API = API::getInstance();
        $result = $API->subscribe($this);
        if ($result == Status::SUB_SUCCESS) {
            $this->Save();
            if ($this->status == Status::PENDING) {
                $response = $API::sendDoubleOptin($this);
            }
        }
        return $result;
    }


    /**
     * Delete the subscriber record from the database.
     * No API interaction.
     */
    public function delete()
    {
        global $_TABLES;

        DB_delete($_TABLES['mailer_subscribers'], 'id', $this->getID());
        $this->id = 0;
    }


    /**
     * Unsubscribe this subscriber.
     * This is called from the user preferences or other administration
     * form and will submit the unsubscription request to the API.
     *
     * @return  boolean     Result of API operation
     */
    public function unsubscribe()
    {
        $API = API::getInstance();
        $result = $API->unsubscribe($this);
        if ($result) {
            $this->updateStatus(Status::UNSUBSCRIBED);
            Logger::Audit("Unsubscribed {$this->getEmail()}");
        } else {
            Logger::Audit("Failed to unsubscribe {$this->getEmail()}");
            return false;
        }
        return $result;
    }


    /**
     * Save the subscriber record to the database.
     *
     * @return  boolean     True on success, False on error
     */
    public function Save()
    {
        global $_TABLES;

        $sql2 = "email = '" . DB_escapeString($this->email_address) . "',
            uid = {$this->getUid()},
            domain = '" . DB_escapeString($this->domain) . "',
            dt_reg = '" . DB_escapeString($this->dt_reg) . "',
            token = '" . DB_escapeString($this->token) . "',
            status = {$this->getStatus()}";
        if ($this->getID() == 0) {
            $sql1 = "INSERT INTO {$_TABLES['mailer_subscribers']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['mailer_subscribers']} SET ";
            $sql3 = " WHERE id = {$this->getID()}";
            }
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql);
        if (!DB_error()) {
            if ($this->getID() == 0) {
                $this->withID(DB_insertID());
            }
            return true;
        } else {
            return false;
        }
    }


    /**
     * Purge old unconfirmed subscriptions.
     */
    public static function purgeUnconfirmed()
    {
        global $_CONF, $_TABLES;

        $purge_days = (int)Config::get('confirm_period');
        if ($purge_days > 0) {
            $sql = "DELETE FROM {$_TABLES['mailer_subscribers']}
                WHERE status = '" . Status::PENDING . "'
                AND '" . $_CONF['_now']->toMySQL(true) .
                    "' > DATE_ADD(dt_reg, INTERVAL $purge_days DAY)";
            $res = DB_query($sql, 1);
            $nrows = DB_affectedRows($res);
            if ($nrows > 0) {
                Logger::Audit(sprintf('Purged %d unconfirmed subscriptions', $nrows));
            }
        }
    }


    /**
     * Set the subscription record ID.
     *
     * @param   integer $id     Record ID
     * @return  object  $this
     */
    public function withID($id)
    {
        $this->id = (int)$id;
        return $this;
    }


    /**
     * Get the subscription record ID.
     *
     * @return  integer     DB record ID
     */
    public function getID()
    {
        return (int)$this->id;
    }


    /**
     * Set the subscriber's glFusion user ID.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    public function withUid($uid)
    {
        $this->uid = (int)$uid;
        return $this;
    }


    /**
     * Get the subscriber's glFusion user ID.
     *
     * @return  integer     User ID
     */
    public function getUid()
    {
        return (int)$this->uid;
    }


    /**
     * Set the subscriber's email domain.
     *
     * @param   string  $domain     Email domain
     * @return  object  $this
     */
    public function withDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }


    /**
     * Get the email domaiin.
     *
     * @return  string  Email domain
     */
    public function getDomain()
    {
        return $this->domain;
    }


    /**
     * Set the subscriber's email address.
     *
     * @param   string  $email  Email address
     * @return  object  $this
     */
    public function withEmail($email)
    {
        $pieces = explode('@', $email);
        if (count($pieces) == 2) {
            $this->withDomain($pieces[1]);
            $this->email_address = $email;
        }
        return $this;
    }


    /**
     * Get the subscriber's email address.
     *
     * @return  string      Email address
     */
    public function getEmail()
    {
        return $this->email_address;
    }


    /**
     * Set the old email address to be used when the email changes.
     *
     * @param   string  $email  Email address
     * @return  object  $this
     */
    public function withOldEmail($email)
    {
        $this->_old_email = $email;
        return $this;
    }


    /**
     * Get the subscriber's original email address.
     *
     * @return  string      Email address
     */
    public function getOldEmail()
    {
        return $this->_old_email;
    }


    /**
     * Set the registration date as a MySQL datetime string.
     *
     * @param   string  $dt_str     Datetime string, empty for `now`
     * @return  object  $this
     */
    public function withRegDate($dt_str='')
    {
        global $_CONF;
        if ($dt_str == '') {
            $dt_str = $_CONF['_now']->toMySQL(true);
        }
        $this->dt_reg = $dt_str;
        return $this;
    }


    /**
     * Get the date registered.
     *
     * @return  string      Datetime string
     */
    public function getRegDate()
    {
        return $this->dt_reg;
    }


    /**
     * Set the subscription status.
     *
     * @param   integer $status     Status to set
     * @return  object  $this
     */
    public function withStatus($status)
    {
        $this->status = (int)$status;
        return $this;
    }


    /**
     * Set the security token.
     *
     * @param   string  $token      Token, blank to create a new one
     * @return  object  $this
     */
    public function withToken($token='')
    {
        if ($token == '') {
            $token = self::_createToken();
        }
        $this->token = $token;
        return $this;
    }


    /**
     * Get the security token.
     *
     * @return  string      Security token value
     */
    public function getToken()
    {
        return $this->token;
    }


    /**
     * Set the user's fullname.
     *
     * @param   string  $name   Full name
     * @return  object  $this
     */
    public function withFullname($name)
    {
        $this->_fullname = $name;
        return $this;
    }


    /**
     * Get the user's full name.
     *
     * @return  string      Full name
     */
    public function getFullname()
    {
        return $this->_fullname;
    }


    /**
     * Get member information from the API.
     *
     * @return  array   Array of member information
     */
    public function getInfo()
    {
        var_dump($this);die;
        $API = API::getInstance();
        return $API->getMemberInfo($this);
    }


    /**
     * Update a member record.
     *
     * @return  boolean     True on success, False on error
     */
    public function update()
    {
        //$params = $this->getAttributes();
        $API = API::getInstance();
        return $API->updateMember($this);
    }


    /**
     * Set a subscriber's status.
     * Status will not be changed without `$force` being set if the
     * current status is "blacklisted".
     *
     * @param   integer $status New status
     * @param   boolean $force  True to change from blacklisted
     * @return  boolean     True on success, False on error
     */
    public function updateStatus($status, $force=false)
    {
        global $_TABLES;

        $status = (int)$status;
        $this->status = $status;
        $sql = "UPDATE {$_TABLES['mailer_subscribers']} SET
            status = $status
            WHERE id = {$this->getID()}";
        if (!$force) {
            $sql .= ' AND status < ' . Status::BLACKLIST;
        }
        $result = DB_query($sql);
        if (!$result) {
            /*if ($status == Status::PENDING) {
                // if an admin forced the status to Pending, send the double
                // opt-in message so the user can activate.
                API::getInstance()->sendDoubleOptin($this);
        }*/
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get the subscriber's subscription status.
     *
     * @return  integer     Subscription status
     */
    public function getStatus()
    {
        return (int)$this->status;
    }


    /**
     * Create a random token string for this subscriber.
     * Allows anonymous users to view the mailings from an email link.
     *
     * @return  string      Token string
     */
    private static function _createToken()
    {
        $bytes = random_bytes(ceil(6));
        return bin2hex($bytes);
    }


    /**
     * Called when a user's profile is updated.
     * Update the database and provider when the glFusion profile is saved.
     *
     * @return  object  $this
     */
    public function profileUpdated()
    {
        $updates = false;
        if (
            isset($_POST['mailer_old_fullname']) &&
            $_POST['mailer_old_fullname'] != $_POST['fullname']
        ) {
            $updates = true;
            $this->withFullname($_POST['fullname']);
        }
        if (
            isset($_POST['mailer_old_email']) &&
            $_POST['mailer_old_email'] != $_POST['email']
        ) {
            $updates = true;
            $this->withEmail($_POST['email']);
        }
        if ($updates) {
            $this->update();
        }
        return $this;
    }


    /**
     * Import our current users to our subscriber list.
     * Only imports those not already in the email table.
     *
     * @return  string  success message
     */
    public static function importUsers()
    {
        global $_TABLES, $LANG_MLR;

        $sql = "SELECT u.uid as u_uid,u.email,mlr.uid
            FROM {$_TABLES['users']} u
            LEFT JOIN {$_TABLES['mailer_subscribers']} mlr
                ON u.uid = mlr.uid
            WHERE u.uid > 2 AND u.status= 3 AND mlr.uid IS NULL";
        $result = DB_query($sql);
        while ($A = DB_fetchArray($result)) {
            if ($A['email'] != '') {
                $Sub = self::getByEmail($A['email']);
                $Sub->withRegDate()
                    ->withToken(self::_createToken())
                    ->subscribe(Status::ACTIVE);
            }
        }
        return $LANG_MLR['import_complete'];
    }


    /**
     * Get the attributes array (aka merge fields).
     * This could be used publically, but probably won't be.
     *
     * @param   array   $map    Array of original->target names
     * @return  array   Array of name->value pairs
     */
    public function getAttributes($map=array())
    {
        global $_PLUGINS;

        // Get the merge fields from plugins.
        $this->_attributes = self::getPluginAttributes($this->uid);

        // Add in the first and last name, if possible.
        $rc = LGLIB_invokeService('lglib', 'parseName',
            array('name' => $this->_fullname),
            $parts, $svc_msg
        );
        if ($rc == PLG_RET_OK) {
            $this->_attributes['FIRSTNAME'] = $parts['fname'];
            $this->_attributes['LASTNAME'] = $parts['lname'];
        }

        // Update that attribute field names to match the API requirement.
        $attributes = $this->_attributes;
        foreach ($map as $orig=>$repl) {
            if (isset($attributes[$orig]) && !empty($repl)) {
                $attributes[$repl] = $attributes[$orig];
            }
            unset($attributes[$orig]);
        }
        return $attributes;
    }


    /**
     * Get the subscriber's attributes from each plugin.
     * Plugins must support this, and should return at least attribute
     * names for anonymous.
     *
     * @param   integer $uid    glFusion user ID
     * @return  array       Array of key=>value pairs
     */
    public static function getPluginAttributes($uid=1)
    {
        global $_PLUGINS;

        $retval = array();
        if ($uid < 2) {
            return $retval;
        }

        foreach ($_PLUGINS as $pi_name) {
            $output = PLG_callFunctionForOnePlugin(
                'plugin_getMergeFields_' . $pi_name,
                array(1 => $uid)
            );
            if (is_array($output)) {
                foreach ($output as $key=>$value) {
                    $retval[$key] = $value;
                }
            }
        }
        return $retval;
    }


    /**
     * Set an attribute (merge field).
     *
     * @param   array|string    $key    Key name or array of key->value pairs
     * @param   mixed       $val    Value to set, NULL to unset
     */
    public function setAttribute($key, $val=NULL)
    {
        if (is_array($key)) {
            $this->_attributes = array_merge($this->_attributes, $key);
        } elseif ($val !== NULL) {
            $this->_attributes[$key] = $val;
        } elseif (isset($this->_attributes[$key])) {
            unset($this->_attributes[$key]);
        }
        return $this;
    }


    /**
     * Get all the emails that are blacklisted according to the database.
     *
     * @return  array   Array of Subscriber objects
     */
    public static function getBlacklisted()
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['mailer_subscribers']}
            WHERE status = " . Status::BLACKLIST;
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
        }
        return $retval;
    }


    /**
     * Update records when a profile update is received from the list provider.
     * The _attributes array must already be set with the new values.
     */
    public function updateUser($update_sub=false)
    {
        global $_TABLES;

        $update_gl = false;
        $fullname = trim(implode(' ', array(
            LGLIB_getVar($this->_attributes, 'FIRSTNAME'),
            LGLIB_getVar($this->_attributes, 'LASTNAME'),
        ) ) );
        if (!empty($fullname) && $fullname != $this->_fullname) {
            $update_gl = true;
            $this->_fullname = $fullname;
        }
        $email = LGLIB_getVar($this->_attributes, 'EMAIL');
        if (!empty($email) && $email != $this->email_address) {
            $update_gl = true;
            $update_sub = true;
            $this->email_address = $email;
        }

        // If subscriber values were changed, save the subscriber
        if ($update_sub) {
            $this->Save();
        }

        // For site members, update the users table if changed and
        // notify plugins to update their tables if needed.
        if ($this->uid > 1) {
            if ($update_gl) {
                $sql = "UPDATE {$_TABLES['users']} SET
                    fullname = '" . DB_escapeString($this->_fullname) . "',
                    email = '" . DB_escapeString($this->email_address) . "'
                    WHERE uid = {$this->uid}";
                DB_query($sql, 1);
            }
            // Allow plugins to update themselves
            PLG_callFunctionForAllPlugins(
                'updateMergeFields',
                array(
                    1 => $this->uid,
                    2 => $this->_attributes,
                )
            );
        }
    }


    /**
     * Synchronize the local cache table with the provider for a single user.
     * First, delete all list entries and then add all the lists that the
     * member is subscribed to. This is quicker than trying to determine which
     * lists are not in the subscribed group.
     *
     * @param   integer $uid    User ID
     * @return  string          User Email address
     */
    public function syncToProvider()
    {
        global $_TABLES;

        $API = API::getInstance();
        if (!$API->supportsSync()) {
            return true;
        }

        $sql = "SELECT * FROM {$_TABLES['mailer_subscribers']}";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $Sub = new self($A);
            $status = $API->subscribeOrUpdate($Sub);
            if ($status !=- Status::SUB_SUCCESS) {
                COM_errorLog(__FUNCTION__ . ' ' . $Sub->getEmail() . " Status: Failure " . $status);
                COM_errorLog($API->getLastResponse()['body']);
            }
        }
        return true;
    }


    /**
     * Synchronize subscriber data from the API provider.
     * First marks all records as `unsubscribed`, then marks those found by
     * the provider as `subscribed`.
     *
     * @return  integer     Number of subscribers received/processed.
     */
    public static function syncFromProvider()
    {
        global $_TABLES;

        $API = API::getInstance();
        if (!$API->supportsSync()) {
            // Nothing to do for the Internal API
            return 0;
        }

        // Mark all internal records as unsubscribed.
        DB_query("UPDATE {$_TABLES['mailer_subscribers']} SET status = " . Status::UNSUBSCRIBED);

        // Get the subscribers 20 at a time
        $args = array(
            'count' => 20,
            'offset' => 0,
        );

        $processed = 0;
        while (true) {
            $contacts = $API->listMembers(NULL, $args);
            foreach ($contacts as $apiInfo) {
                if ($apiInfo['status'] == Status::ACTIVE) {
                    $Sub = self::getByEmail($apiInfo['email_address']);
                    $Sub->withStatus($apiInfo['status'])->Save();
                    $processed++;
                }
            }
            if (count($contacts) < $args['count']) {
                // Got the last segment
                break;
            }
            $args['offset'] += $args['count'];
        }
        DB_delete(
            $_TABLES['mailer_subscribers'],
            'status',
            Status::UNSUBSCRIBED
        );
        return $processed;
    }


    /**
     * List all the current subscribers.
     *
     * @return  string      HTML for admin list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $_IMAGE_TYPE, $LANG_ADMIN, $LANG_MLR,
            $LANG01;

        $retval = '';
        $outputHandle = \outputHandler::getInstance();
        $outputHandle->addLinkScript(Config::get('url') . '/js/userStatus.js');

        $header_arr = array(      # display 'text' and use table field 'field'
            array(
                'text' => $LANG_MLR['id'],
                'field' => 'id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MLR['email'],
                'field' => 'email',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MLR['site_user'],
                'field' => 'uid',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MLR['list_status'],
                'field' => 'status',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'remove_subscriber',
                'sort' => false,
                'align' => 'center',
            ),
        );
        $defsort_arr = array('field' => 'email', 'direction' => 'asc');
        $text_arr = array(
            'has_extras' => true,
            'form_url' => Config::get('admin_url') . '/index.php?subscribers=x',
        );

        $query_arr = array(
            'table' => 'mailer_subscribers',
            'sql' => "SELECT ml.*, u.uid, u.fullname
                FROM {$_TABLES['mailer_subscribers']} ml
                LEFT JOIN {$_TABLES['users']} u
                    ON ml.uid = u.uid
                WHERE 1=1 ",
            'query_fields' => array('ml.email', 'u.fullname'),
            'default_filter' => COM_getPermSQL('AND', 0, 3)
        );

        $T = new \Template(Config::get('pi_path') . '/templates/admin');
        $T->set_file('chkactions', 'sub_chkactions.thtml');
        $T->parse('output', 'chkactions');
        $chkactions = $T->finish($T->get_var('output'));
        $options = array(
            //'chkdelete' => true,
            'chkselect' => true,
            'chkfield' => 'id',
            'chkname' => 'delsubscriber',
            'chkminimum' => 0,
            'chkall' => true,
            'chkactions' => $chkactions,
        );

        $retval .= ADMIN_list(
            'mailer_subscribers',
            array(__CLASS__, 'getListField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, '', '', $options
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
        case 'remove_subscriber':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-remove uk-text-danger"></i>',
                $admin_url . "/index.php?delsubscriber=x&amp;id={$A['id']}",
                array(
                    'onclick' => "return confirm('Do you really want to delete this item?');",
                )
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
            $onclick4 = "onclick='MLR_toggleUserStatus(\"" . Status::UNSUBSCRIBED.
                "\", \"{$A['id']}\");' ";
            switch ($fieldvalue) {
            case Status::UNSUBSCRIBED:
                $onclick4 = '';
                break;
            case Status::ACTIVE:
                $icon1_cls = 'uk-icon-circle uk-text-success';
                $onclick1 = '';
                break;
            case Status::PENDING:
                $icon2_cls = 'uk-icon-circle uk-text-warning';
                $onclick2 = '';
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
                $onclick3 . '/></i>&nbsp;';
            $retval .= '<i class="uk-icon uk-icon-remove uk-text-danger" ' .
                $onclick4 . '/></i>';
            $retval .= '</div>';
            break;

        case 'uid':
            $retval = COM_getDisplayName($A['uid']);
            if ($A['uid'] > 1) {
                $retval = COM_createLink($retval,
                    $_CONF['site_url'].'/users.php?mode=profile&uid=' . $A['uid']
                );
            }
            $retval .= ' (' . $A['uid'] . ')';
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
