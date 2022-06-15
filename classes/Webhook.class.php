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
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Base Webhook class
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
     * Get an instance of the Webhook class.
     *
     * @param   string  $provider   Specific provider to instantiate
     * @return  object  Mailchimp Webhook object
     */
    public static function getInstance($provider = NULL)
    {
        static $wh = NULL;
        if ($wh === NULL) {
            if ($provider == NULL) {
                $provider = Config::get('provider');
            }
            try {
                $cls = '\\Mailer\\API\\' . $provider . '\\Webhook';
                $wh = new $cls;
                $wh->withProvider($provider);
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $wh = new self;
            }
        }
        return $wh;
    }


    /**
     * Set the provider name.
     *
     * @param   string  $name   API provider name
     * @return  object  $this
     */
    protected function withProvider($name)
    {
        $this->provider = $name;
        return $this;
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

        if (is_numeric($Txn['txn_date'])) {
            $d = new \Date($Txn['txn_date'], $_CONF['timezone']);
            $txn_date = $d->toMySQL(true);
        } else {
            $txn_date = $Txn['txn_date'];
        }
        $count = $db->getCount(
            $_TABLES['mailer_txn'],
            array('provider', 'type', 'txn_id', 'txn_date'),
            array($this->provider, $Txn['type'], $Txn['txn_id'], $txn_date)
        );
        if ($count == 0) {
            try {
                $db->conn->executeQuery(
                    "INSERT INTO {$_TABLES['mailer_txn']} SET
                    provider = ?,
                    type = ?,
                    txn_date = ?,
                    txn_id = ?,
                    data = ?",
                    array(
                        $this->provider,
                        $Txn['type'],
                        $txn_date,
                        $Txn['txn_id'],
                        json_encode($this->payload),
                    ),
                    array(
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                        Database::STRING,
                    )
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }

}
