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


/**
 * API Parameters to be collected and passed to Mailchimp's API.
 * @package mailchimp
 */
class ApiInfo implements \ArrayAccess
{
    /** Properties array. Sent to the API as an array.
     * @var array */
    private $properties = array(
        'provider_uid' => '',
        'email_address' => '',
        'status' => '',
        'attributes' => array(
            'FIRSTNAME' => '',
            'LASTNAME' => '',
        ),
    );


    public function setAttribute($key, $value)
    {
        $this->properties['attributes'][$key] = $value;
        return $this;
    }


    public function getAttribute($key=NULL)
    {
        if ($key === NULL) {
            return $this->properties['attributes'];
        } elseif (isset($this->properties['attributes'][$key])) {
            return $this->properties['attributes'][$key];
        } else {
            return NULL;
        }
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
        return $this->properties;
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
