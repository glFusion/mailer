<?php
/**
 * Base class for mailer webhooks
 *
 * @author      
 * @version     2.2
 * @package     mailer
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/MIT
 *              MIT License
 * @filesource
 */
namespace Mailer;


/**
 * Base API class
 * @package mailer
 */
class Webhook
{
    protected $provider = '';
    protected $payload = array();


    /**
     * Get an instance of the API class.
     *
     * @author  Lee Garner <lee@leegarner.com>
     * @return  object  Mailchimp API object
     */
    public static function getInstance()
    {
        $cls = '\\Mailer\\API\\' . Config::get('provider') . '\\Webhook';
        $wh = new $cls;
        return $wh;
    }


    public function isUnique($Txn)
    {
        global $_TABLES;

        if (empty($Txn['txn_id'])) {
            return false;
        }

        $type = DB_escapeString($Txn['type']);
        $txn_id = DB_escapeString($Txn['txn_id']);
        $txn_date = DB_escapeString($Txn['txn_date']);
        $count = DB_count(
            $_TABLES['mailer_txn'],
            array('provider', 'type', 'txn_id', 'txn_date'),
            array($this->provider, $type, $txn_id, $txn_date)
        );
        if ($count == 0) {
            $sql = "INSERT INTO {$_TABLES['mailer_txn']} SET
                provider = '{$this->provider}',
                type = '$type',
                txn_date = '$txn_date',
                txn_id = '$txn_id',
                data = '" . DB_escapeString(json_encode($this->payload)) . "'";
            DB_query($sql);
            return true;
        }
        return false;
    }

}
