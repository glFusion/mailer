<?php
/**
 * Import current members from a glFusion group into the Subscribers table.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2015-2022 Lee Garner <lee@leegarner.com>
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
use Mailer\Models\Status;


class glFusion extends \Mailer\Util\Importer
{
    /**
     * Import site members into the Subscribers table.
     *
     * @param   integer $grp_id     glFusion group ID to be imported
     * @return  string  Results text
     */
    public function do_import(int $grp_id) : string
    {
        global $_TABLES, $_CONF;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        try {
            $data = $qb->select('ga.ug_uid', 'u.fullname', 'u.email')
                       ->from($_TABLES['group_assignments'], 'ga')
                       ->leftJoin('ga', $_TABLES['users'], 'u', 'u.uid = ga.ug_uid')
                       ->where('ga.ug_main_grp_id = ?')
                       ->andWhere('ga.ug_uid > 1')
                       ->setParameter(0, $grp_id, Database::INTEGER)
                       ->execute()
                       ->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
            $data = array();
        }

        $this->results['to_process'] = count($data);
        foreach ($data as $A) {
            if ($A['ug_uid'] == 10) {
                continue;
            }
            $Sub = Subscriber::getByUid($A['ug_uid']);
            if ($Sub->getID() > 0) {
                $this->results['duplicate']++;
            }
            if ($A['email'] != ''){
                $txt = $A['fullname'] . '(' . $A['ug_uid'] . ')<br />' . LB;
                $status = $Sub->subscribe(Status::ACTIVE);
                if ($status == Status::SUB_SUCCESS) {
                    $Sub->Save();
                    $this->results['success']++;
                    $this->results['imported'][] = $txt;
                } else {
                    $this->results['error']++;
                    $this->results['failures'][] = $txt;
                }
            } else {
                $this->results['invalid']++;
                $this->results['failures'][] = $txt;
            }
        }
        return $this->_formatResults();
    }

}
