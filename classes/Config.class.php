<?php
/**
 * Class to read and manipulate Mailer configuration values.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.2.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer;


/**
 * Class to get plugin configuration data.
 * @package mailer
 */
final class Config
{
    /** Plugin Name.
     */
    public const PI_NAME = 'mailer';

    /** Array of config items (name=>val).
     * @var array */
    private $properties = NULL;

    /** Config class singleton instance.
     * @var object */
    static private $instance = NULL;


    /**
     * Get the configuration object.
     * Creates an instance if it doesn't already exist.
     *
     * @return  object      Configuration object
     */
    public static function getInstance()
    {
        if (self::$instance === NULL) {
            self::$instance = new self;
        }
        return self::$instance;
    }


    /**
     * Create an instance of the configuration object.
     */
    private function __construct()
    {
        global $_CONF, $_MLR_CONF;

        $cfg = \config::get_instance();
        $this->properties = $cfg->get_config(self::PI_NAME);

        $this->properties['pi_name'] = self::PI_NAME;
        $this->properties['pi_display_name'] = 'Mailer';
        $this->properties['pi_url'] = 'http://www.glfusion.org';
        $this->properties['pi_path'] = dirname(__DIR__) . '/';
        $this->properties['url'] = $_CONF['site_url'] . '/' . self::PI_NAME;
        $this->properties['admin_url'] = $_CONF['site_admin_url'] . '/plugins/' . self::PI_NAME;
        $this->properties['webhook_url'] = $this->properties['url'] . '/hooks/hook.php';
        $_MLR_CONF = $this->properties;
    }


    /**
     * Returns a configuration item.
     * Returns all items if `$key` is NULL.
     *
     * @param   string|NULL $key        Name of item to retrieve
     * @param   mixed       $default    Default value if item is not set
     * @return  mixed       Value of config item
     */
    private function _get($key=NULL, $default=NULL)
    {
        if ($key === NULL) {
            return $this->properties;
        } elseif (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
           return $default;
        }
    }


    /**
     * Set a configuration value.
     * Unlike the root glFusion config class, this does not add anything to
     * the database. It only adds temporary config vars.
     *
     * @param   string  $key    Configuration item name
     * @param   mixed   $val    Value to set
     */
    private function _set($key, $val)
    {
        global $_CONF_MLCH;

        $this->properties[$key] = $val;
        $_CONF_MLCH[$key] = $val;
        return $this;
    }


    /**
     * Set a configuration value.
     * Unlike the root glFusion config class, this does not add anything to
     * the database. It only adds temporary config vars.
     *
     * @param   string  $key    Configuration item name
     * @param   mixed   $val    Value to set, NULL to unset
     */
    public static function set($key, $val=NULL)
    {
        return self::getInstance()->_set($key, $val);
    }


    /**
     * Returns a configuration item.
     * Returns all items if `$key` is NULL.
     *
     * @param   string|NULL $key        Name of item to retrieve
     * @param   mixed       $default    Default value if item is not set
     * @return  mixed       Value of config item
     */
    public static function get($key=NULL, $default=NULL)
    {
        return self::getInstance()->_get($key);
    }


    /**
     * Get the email "from" address. Use the noreply address if none supplied.
     *
     * @return  string      Sender email address
     */
    public static function senderEmail()
    {
        global $_CONF;

        static $from_email = NULL;
        if ($from_email === NULL) {
            $from_email = self::get('sender_email');
            if ($from_email == '') {
                $from_email = $_CONF['noreply_email'];
            }
        }
        return $from_email;
    }


    /**
     * Get the email "from" address. Use the noreply address if none supplied.
     *
     * @return  string      Sender email address
     */
    public static function senderName()
    {
        global $_CONF;

        static $from_name= NULL;
        if ($from_name === NULL) {
            $from_name = self::get('sender_name');
            if ($from_name == '') {
                $from_name = $_CONF['site_name'];
            }
        }
        return $from_name;
    }

}
