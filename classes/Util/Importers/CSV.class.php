<?php
/**
 * Import subscribers from a CSV file.
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
namespace Mailer\Util\Importers;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Mailer\Models\Subscriber;
use Mailer\Config;


class CSV extends \Mailer\Util\Importer
{
    public function do_import() : string
    {
        $list = explode($_POST['delimiter'], $_POST['import_list']);
        $status = isset($_POST['blacklist']) && $_POST['blacklist'] == 1 ?
            Status::BLACKLIST : Status::ACTIVE;
        if (is_array($list)) {
            $this->results['to_process'] = count($list);
            foreach($list as $email){
                if (!empty($email)) {
                    $Sub = new Subscriber;
                    $response = $Sub->withEmail(trim($email))
                                    ->withStatus($status)
                                    ->subscribe();

                    //$status = MLR_addEmail(trim($email), $status);
                    switch ($response) {
                    case Status::SUB_SUCCESS:
                        $this->results['success']++;
                        break;
                    case Status::SUB_INVALID:
                        $this->results['invalid']++;
                        break;
                    case Status::SUB_EXISTS:
                        $this->results['duplicate']++;
                        break;
                    case Status::SUB__ERROR:
                        $this->results['error']++;
                    break;
                    }
                }
            }
        }
        return $this->_formatResults();
    }


    /**
     * Get all subscribers as a CSV string.
     *
     * @return  string      CSV string, NULL on error
     */
    public function do_export() : ?string
    {
        global $_TABLES;

        $retval = '';
        $db = Database::getInstance();
        $list = array();
        try {
            $stmt = $db->conn->executeQuery("SELECT email FROM {$_TABLES['mailer_subscribers']}");
            $data = $stmt->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $list[] = strtolower($A['email']);
            }
            $retval = implode(",", $list);
        } else {
            $retval = NULL;
        }
        return $retval;
    }
}

