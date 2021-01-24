<?php
/**
 * Base class for mailer webhooks
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010-2021 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer;


/**
 * Base API class
 * @package mailer
 */
class Webhook
{
    /** Provider name.
     * @var string */
    protected $provider = '';

    /** Data payload.
     * @var array */
    protected $payload = array();


    /**
     * Get an instance of the API class.
     *
     * @author  Lee Garner <lee@leegarner.com>
     * @return  object  Mailchimp API object
     */
    public static function getInstance()
    {
        try {
            $this->provider = Config::get('provider');
            $cls = '\\Mailer\\API\\' . $this->provider . '\\Webhook';
            $wh = new $cls;
        } catch (\Exception $e) {
            COM_errorLog("ERROR: " . print_r($e,true));
            $wh = new self;
            $this->provider = '';
        }
        return $wh;
    }


    /**
     * Get the provider name.
     * Can be used to validate that the webhook was loaded.
     *
     * @return  string      Provider name
     */
    public function getProvider()
    {
        return $this->provider;
    }


    /**
     * Check that this webhook is unique to avoid processing duplicates.
     * Also logs the transaction before returning true.
     *
     * @param   object  $Txn    Transaction object
     * @return  boolean     True if unique, False if duplicate
     */
    public function isUnique($Txn)
    {
        global $_TABLES, $_CONF;

        if (empty($Txn['txn_id'])) {
            return false;
        }

        $type = DB_escapeString($Txn['type']);
        $txn_id = DB_escapeString($Txn['txn_id']);
        if (is_numeric($Txn['txn_date'])) {
            $d = new \Date($Txn['txn_date'], $_CONF['timezone']);
            $txn_date = $d->toMySQL(true);
        } else {
            $txn_date = DB_escapeString($Txn['txn_date']);
        }
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
