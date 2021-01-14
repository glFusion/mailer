<?php
/**
 * Define parameters to send to the email provider.
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
use Mailer\Config;


/**
 * API Parameters to be collected and passed to Mailchimp's API.
 * @package mailchimp
 */
class ApiParams implements \ArrayAccess
{
    /** Properties array. Sent to the API as an array.
     * @var array */
    private $properties = array();
/*        $this->properties = array(
            'id' => Config::get('def_list'),
            'email_type' => 'html',
            'status' => Config::get('dbl_optin_members') ? 'pending' : 'subscribed',
            //'double_optin' => true,
            'update_existing' => true,
            'merge_fields' => array(),
        );
 */

    /**
     * Initialize the parameters array.
     */
    public function __construct($params = NULL)
    {
        if (is_array($params)) {
            $this->properties = $params;
        /*} else {
            $this->properties = array(
                $API->getFieldname['attributes'] => array();
            );*/
        }
    }


    /**
     * Generic setter function for other parameters.
     *
     * @param   string  $key    Parameter name
     * @param   mixed   $value  Parameter value
     * @return  object  $this
     */
    public function set($key, $value)
    {
        if ($value === NULL) {
            unset($this->properties[$key]);
        } else {
            $this->properties[$key] = $value;
        }
        return $this;
    }


    /**
     * Get all the parameters to send to Mailchimp.
     *
     * @return  array       Properties array
     */
    public function get()
    {
        return $this->properties;
    }


    /**
     * Get the API parameters as an array.
     * Remove merge_fields if empty as passing an empty array to Mailchimp
     * causes an error.
     *
     * @return  array   Array of parameters
     */
    public function toArray()
    {
        if (empty($this->properties['merge_fields'])) {
            unset($this->properties['merge_fields']);
        }
        return $this->properties;
    }


    /**
     * Get the merge fields from plugins.
     * Each plugin returns an array of name=>value pairs.
     * Merge fields are added to the static array to be retrieved via
     * `self::get()`.
     *
     * @param   integer $uid    User ID
     */
    public function mergePlugins($uid)
    {
        global $_PLUGINS;

        foreach ($_PLUGINS as $pi_name) {
            $output = PLG_callFunctionForOnePlugin(
                'plugin_getMergeFields_' . $pi_name,
                array(1 => $uid)
            );
            if (is_array($output)) {
                foreach ($output as $name=>$value) {
                    $this->addMerge($name, $value);
                }
            }
        }
        return $this;
    }


    public function addMerge($key, $value)
    {
        if (!empty($value)) {
            $this->properties['merge_fields'][$key] = $value;
        }
        return $this;
    }


    public function offsetSet($key, $value)
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
    }

}
