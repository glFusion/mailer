<?php
/**
 * Class to create fields for adminlists and other uses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner
 * @package     mailer
 * @version     v0.2.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * Class to create special list fields.
 * @package mailer
 */
class FieldList extends \glFusion\FieldList
{
    /**
     * Return a cached template object to avoid repetitive path lookups.
     *
     * @return  object      Template object
     */
    protected static function init()
    {
        global $_CONF;

        static $t = NULL;

        if ($t === NULL) {
            $t = new \Template(Config::get('pi_path') . '/templates');
            $t->set_file('field', 'fieldlist.thtml');
        } else {
            $t->unset_var('output');
            $t->unset_var('attributes');
        }
        return $t;
    }


    public static function circle($args)
    {
        $t = self::init();
        $t->set_block('field','field-circle');

        if (isset($args['type'])) {
            $t->set_var('type', 'true');
        } else {
            $t->clear_var('type');
        }
        if (isset($args['class'])) {
            $t->set_var('other_cls', $args['class']);
        } else {
            $t->clear_var('other_cls');
        }
        if (isset($args['style'])) {
            $t->set_var('color', $args['style']);
        } else {
            $t->clear_var('color');
        }
        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-circle','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-circle', true);
        return $t->finish($t->get_var('output'));
    }

    public static function ban($args)
    {
        $t = self::init();
        $t->set_block('field','field-ban');

        if (isset($args['class'])) {
            $t->set_var('other_cls', $args['class']);
        }
        if (isset($args['style'])) {
            $t->set_var('color', $args['style']);
        }
        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-ban','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-ban', true);
        return $t->finish($t->get_var('output'));
    }

}
