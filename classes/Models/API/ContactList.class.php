<?php
/**
 * Define a standard layout for a contact list.
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
 * Mailing list information class.
 * @package mailchimp
 */
class ContactList implements \ArrayAccess
{
    private $properties = array(
        'list_id' => '',
        'list_name' => '',
        'member_count' => '',
    );

    /**
     * Instantiate a list object populated with data from getAll().
     *
     * @param   array   $A      Array of list data from getAll()
     */
    public function __construct($A=array())
    {
        if (!empty($A)) {
            $this->properties['list_id'] = $A['id'];
            $this->properties['list_name'] = $A['name'];
            $this->properties['member_count'] = (int)$A['members'];
        }
    }


    /**
     * Get the number of members subscribed to the list.
     *
     * @return  integer     Member count
     */
    public function getMemberCount()
    {
        return (int)$this->member_count;
    }


    /**
     * Get the list ID.
     *
     * @return  string      List ID
     */
    public function getID()
    {
        return $this->list_id;
    }


    /**
     * Get the name of the list.
     *
     * @return  string      List name
     */
    public function getName()
    {
        return $this->list_name;
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
