<?php
/**
 * Import addresses into the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.2.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Util;


/**
 * Base importer class.
 * @package mailer
 */
class Importer
{
    /** Holder for results counters and info.
     * @var array */
    protected $results = array(
        'to_process' => 0,
        'success' => 0,
        'error' => 0,
        'invalid' => 0,
        'duplicate' => 0,
        'imported' => array(),
        'failures' => array(),
    );


    /**
     * Create a display version of the results.
     *
     * @return  string      HTML to show the results of the import
     */
    protected function _formatResults() : string
    {
        global $LANG_MLR;

        $msg = '';
        foreach ($this->results as $key=>$value) {
            if (gettype($value) == 'array' && !empty($value)) {
                $msg .= '<li>' . $LANG_MLR[$key] . ':<ol><li>' . implode('</li><li>', $value) . '</li></ol></li>';
            } elseif (gettype($value) == 'integer' && $value > 0) {
                $msg .= '<li>' . $LANG_MLR[$key] . ': ' . $value . '</li>' . LB;
            }
        }
        if (!empty($msg)) {
            $msg = '<ul>' . $msg . '</ul>' . LB;
        }
        return $msg;
    }

}

