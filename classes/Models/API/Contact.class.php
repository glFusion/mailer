<?php
/**
 * Define a standard layout for a single contact.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.3.0
 * @since       v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Models\API;


/**
 * API Parameters to be collected and passed to Mailchimp's API.
 * @package mailchimp
 */
class Contact implements \ArrayAccess
{
    /** Properties array. Sent to the API as an array.
     * @var array */
    private $properties = array(
        'provider_uid' => '',
        'email_address' => '',
        'email_type' => 'html',
        'status' => '',
        'attributes' => array(
            'FIRSTNAME' => '',
            'LASTNAME' => '',
        ),
    );


    /**
     * Dynamically set an attribute value.
     *
     * @param   string  $key    Attribute name
     * @param   string  $value  Attribute value
     * @return  object  $this
     */
    public function setAttribute($key, $value)
    {
        $this->properties['attributes'][$key] = $value;
        return $this;
    }


    /**
     * Get a single attribute, or all attributes if none requested.
     *
     * @param   string  $key    Attribute name, NULL to retrieve all
     * @return  mixed       Attribute value, all attributes, or NULL if invalid
     */
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


    /**
     * ArrayAccess - Set an attribute value.
     *
     * @param   string  $key    Attribute name
     * @param   string  $value  Attribute value
     */
    public function offsetSet($key, $value)
    {
        if ($value === NULL) {
            unset($this->properties[$key]);
        } else {
            $this->properties[$key] = $value;
        }
    }


    /**
     * ArrayAccess - check if an attribute is set.
     *
     * @param   string  $key    Attribute name
     * @return  boolean     True if set, False if not
     */
    public function offsetExists($key)
    {
        return isset($this->properties[$key]);
    }


    /**
     * ArrayAccess - Remove an attribute.
     *
     * @param   string  $key    Attribute name
     */
    public function offsetUnset($key)
    {
        unset($this->properties[$key]);
    }


    /**
     * ArrayAccess - Retrieve an attribute.
     *
     * @param   string  $key    Attribute name
     * @return  mixed       Attribute value, NULL if not set
     */
    public function offsetGet($key)
    {
        return isset($this->properties[$key]) ? $this->properties[$key] : null;
    }

}
