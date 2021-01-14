<?php
/**
 * Layout for a Subscriber record.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.0.4
 * @since       v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Models;
use Mailer\API;
use Mailer\Models\Status;
use Mailer\Config;


/**
 * A standard representation of the interesting list info fields.
 * @package mailchimp
 */
class Subscriber
{
    private $sub_id = 0;
    private $uid = 1;
    private $dt_reg = '';
    private $domain = '';
    private $email_address = '';
    private $status = 0;
    private $token = '';
    private $merge_fields = array();
    private $fullname = '';     // from users table
    private $_attributes = array(); // ephemeral


    /**
     * Initialize the parameters array.
     */
    public function __construct($A = NULL)
    {
        if (is_array($A)) {
            // assumes a DB record or identical layout
            $this->withID($A['sub_id'])
                 ->withUid($A['uid'])
                 ->withEmail($A['email'])
                 ->withRegDate($A['dt_reg'])
                 ->withToken($A['token'])
                 ->withFullname($A['uid'] == 1 ? '' : $A['fullname'])
                 ->withStatus($A['status']);
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

        $sql = "SELECT * FROM {$_TABLES['mailer_emails']}
            WHERE sub_id = " . (int)$id;
        $res = DB_query($sql);
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $retval = new self($A);
        } else {
            $retval = (new self)
                ->withRegDate()
                ->withToken();
        }
        return $retval;
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
            ->withEmail($email);
        return $retval;
    }


    private static function _create($fld, $value)
    {
        global $_TABLES;

         $sql = "SELECT me.*, u.fullname
            FROM {$_TABLES['mailer_emails']} me
            LEFT JOIN {$_TABLES['users']} u
            ON u.uid = me.uid WHERE me.{$fld} = '$value'";
        $res = DB_query($sql);
        if (DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            $retval = new self($A);
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
        // Do nothing if this address is blacklisted.
        if ($this->status == Status::BLACKLIST) {
            return false;
        }

        if ($status !== NULL) {
            $this->status = (int)$status;
        } else {
            $this->status = Status::PENDING;
        }
        $API = API::getInstance();
        $result = $API->subscribe($this);
        if ($result) {
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

        DB_delete($_TABLES['mailer_emails'], 'sub_id', $this->getID());
        $this->sub_id = 0;
    }


    /**
     * Unsubscribe this subscriber.
     * This is called from the user preferences or other administration
     * form and will submit the unsubscription request to the API.
     */
    public function unsubscribe()
    {
        $API = API::getInstance();
        $result = $API->unsubscribe($this);
        if ($result) {
            $this->setStatus(Status::UNSUBSCRIBED);
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
            $sql1 = "INSERT INTO {$_TABLES['mailer_emails']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['mailer_emails']} SET ";
            $sql3 = " WHERE sub_id = {$this->getID()}";
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
     * Whitelist an email address or domain.
     * This just sets the status as Active.
     *
     * @param   string  $email      Email address.
     */
    public static function whitelist($email)
    {
        global $_TABLES;

        $pieces = explode('@', $email);
        if ($pieces[0] == '*') {
            // Update all emails in the domain
            DB_query(
                "UPDATE {$_TABLES['mailer_emails']}
                SET status='" . Status::ACTIVE . "'
                WHERE domain = '" . DB_escapeString($pieces[1]) . "'",
                1
            );
        } else {
            DB_query("UPDATE {$_TABLES['mailer_emails']} SET
                status = '" . Status::ACTIVE. "'
                WHERE email = '" . DB_escapeString($email) . "'",
            1);
        }
        MLR_auditLog("Whitelisted $email");
    }

    
    /**
     * Add email to blacklist.
     *
     * @param   string  $email  Email to add
     */
    public static function blacklist($email)
    {
        global $_TABLES;

        $pieces = explode('@', $email);
        if ($pieces[0] == '*') {
            // Update status of all addreses with this domain
            DB_query("UPDATE {$_TABLES['mailer_emails']}
                SET status='" . Status::BLACKLIST . "'
                WHERE domain = '" . DB_escapeString($pieces[1]) . "'", 1);
        } else {
            DB_query("UPDATE {$_TABLES['mailer_emails']}
                SET status = '" . Status::BLACKLIST . "'
                WHERE email = '" . DB_escapeString($email) . "'");
        }
        MLR_auditLog("Blacklisted $email");
    }

    /*public function offsetSet($key, $value)
    {
        if ($value === NULL) {
            unset($this->properties[$key]);
        } else {
            $this->properties[$key] = $value;
        }
    }

    public function offsetExists($key)
    {
        return isset($this->properties[$key]);
    }

    public function offsetUnset($key)
    {
        unset($this->properties[$key]);
    }

    public function offsetGet($key)
    {
        return isset($this->properties[$key]) ? $this->properties[$key] : null;
    }*/


    public function withID($sub_id)
    {
        $this->sub_id = (int)$sub_id;
        return $this;
    }


    public function getID()
    {
        return (int)$this->sub_id;
    }


    public function withUid($uid)
    {
        $this->uid = (int)$uid;
        return $this;
    }

    public function getUid()
    {
        return (int)$this->uid;
    }

    public function withDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function withEmail($email)
    {
        $pieces = explode('@', $email);
        if (count($pieces) == 2) {
            $this->withDomain($pieces[1]);
            $this->email_address = $email;
        }
        return $this;
    }

    public function getEmail()
    {
        return $this->email_address;
    }

    public function withRegDate($dt_str='')
    {
        global $_CONF;
        if ($dt_str == '') {
            $dt_str = $_CONF['_now']->toMySQL(true);
        }
        $this->dt_reg = $dt_str;
        return $this;
    }

    public function getRegDate()
    {
        return $this->dt_reg;
    }


    public function withStatus($status)
    {
        $this->status = (int)$status;
        return $this;
    }

    public function withToken($token='')
    {
        if ($token == '') {
            $token = self::_createToken();
        }
        $this->token = $token;
        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }


    public function withFullname($name)
    {
        $this->fullname = $name;
        return $this;
    }


    /**
     * Get member information.
     *
     * @return  array   Array of member information
     */
    public function getInfo()
    {
        $API = API::getInstance();
        return $API->getMemberInfo($this);
    }


    /**
     * Update a member record.
     *
     * @param   array   $params     Parameters to update
     */
    public function updateMember($params = array())
    {
        if (!isset($params['attributes'])) {
            $params['attributes'] = array();
        }
        $rc = LGLIB_invokeService('lglib', 'parseName',
            array('name' => $this->fullname),
            $parts, $svc_msg
        );
        if ($rc == PLG_RET_OK) {
            $params['attributes']['firstname'] = $parts['fname'];
            $params['attributes']['lastname'] = $parts['lname'];
        }
        $API = API::getInstance();
        $API->updateMember($this->email_address, $this->uid, $params);
    }


    /**
     * Set a subscriber's status.
     * Status will not be changed without `$force` being set if the
     * current status is "blacklisted".
     *
     * @param   integer $status New status
     * @param   boolean $force  True to change from blacklisted
     */
    public function setStatus($status, $force=false)
    {
        global $_TABLES;

        $status = (int)$status;
        $this->status = $status;
        $sql = "UPDATE {$_TABLES['mailer_emails']} SET
            status = $status
            WHERE sub_id = {$this->getID()}";
        if (!$force) {
            $sql .= ' AND status < ' . Status::BLACKLIST;
        }
        DB_query($sql);
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
     * Import our current users to our subscriber list.
     *
     * @return  string  success message
     */
    public static function importUsers()
    {
        global $_TABLES, $LANG_MLR;

        $sql = "SELECT `email` FROM {$_TABLES['users']}";
        $result = DB_query($sql);
        while ($A = DB_fetchArray($result)) {
            if ($A['email'] != '') {
                echo "here";die;
                $Sub = new self;
                $Sub->withEmail($A['email'])
                     ->withRegDate()
                     ->withToken(self::_createToken());
                 var_dump($Sub);die;
                     //->subscribe(Status::ACTIVE);
            }
        }
        return $LANG_MLR['import_complete'];
    }


    /**
     * Get the attributs array (aka merge fields).
     *
     * @param   array   $map    Array of original->target names
     * @return  array   Array of name->value pairs
     */
    public function getAttributes($map=array())
    {
        global $_PLUGINS;

        $rc = LGLIB_invokeService('lglib', 'parseName',
            array('name' => $this->fullname),
            $parts, $svc_msg
        );
        if ($rc == PLG_RET_OK) {
            $this->_attributes['firstname'] = $parts['fname'];
            $this->_attributes['lastname'] = $parts['lname'];
        }
        
        // Get the merge fields from plugins.
        if ($this->uid > 1) {
            foreach ($_PLUGINS as $pi_name) {
                $output = PLG_callFunctionForOnePlugin(
                    'plugin_getMergeFields_' . $pi_name,
                    array(1 => $this->uid)
                );
                if (is_array($output)) {
                    foreach ($output as $key=>$value) {
                        $this->_attributes[$key] = $value;
                    }
                }
            }
        }
        $attributes = $this->_attributes;
        foreach ($map as $orig=>$repl) {
            if (isset($attributes[$orig])) {
                $attributes[$repl] = $attributes[$orig];
                unset($attributes[$orig]);
            }
        }
        return $attributes;
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
                'field' => 'sub_id',
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
        $menu_arr = array (
            array(
                'url' => MLR_ADMIN_URL . '/index.php?import_form=x',
                'text' => $LANG_MLR['import'],
            ),
            array(
                'url' => MLR_ADMIN_URL . '/index.php?import_users_confirm=x',
                'text' => $LANG_MLR['import_current_users'],
            ),
            array(
                'url' => MLR_ADMIN_URL . '/index.php?export=x',
                'text' => $LANG_MLR['export'],
            ),
            array(
                'url' => MLR_ADMIN_URL . '/index.php?clear_warning=x',
                'text' => $LANG_MLR['clear'],
            ),
        );

        $retval .= COM_startBlock(
            $LANG_MLR['subscriberlist'] . ' ' . Config::get('pi_name') . ' v. ' .
                Config::get('pi_version'),
            '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
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
            'chkfield' => 'sub_id',
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
        case 'remove_subscriber':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-remove uk-text-danger"></i>',
                $admin_url . "/index.php?delsubscriber=x&amp;sub_id={$A['sub_id']}",
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
                "\", \"{$A['sub_id']}\");' ";
            $onclick2 = "onclick='MLR_toggleUserStatus(\"" . Status::PENDING .
                "\", \"{$A['sub_id']}\");' ";
            $onclick3 = "onclick='MLR_toggleUserStatus(\"" . Status::BLACKLIST . 
                "\", \"{$A['sub_id']}\");' ";
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
            $retval = '<div id="userstatus' . $A['sub_id']. '">' .
                '<i class="uk-icon ' . $icon1_cls . '" ' .
                $onclick1 . '/></i>&nbsp;';
            $retval .= '<i class="uk-icon ' . $icon2_cls . '" ' .
                $onclick2 . '/></i>&nbsp;';
            $retval .= '<i class="uk-icon ' . $icon3_cls . '" ' .
                $onclick3 . '/></i>';
            $retval .= '</div>';
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
