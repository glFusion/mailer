<?php
/**
 * Ajax functions for the Mailer plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2008 Wayne Patterson <suprsidr@gmail.com>
 * @package     mailer
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once '../lib-common.php';
if (!in_array('mailer', $_PLUGINS)) {
    COM_404();
    exit;
}
use Mailer\Models\Subscriber;
use Mailer\Models\Status;
use Mailer\Config;

if (isset($_POST['action'])) {
    $action = $_POST['action'];
} elseif (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    $action = '';
}

$output = array(
    'status' => Status::SUB_ERROR,
);

switch ($action) {
case 'add':
    if (isset($_GET['email']) && !empty($_GET['email'])) {
        // Basic checks passed, now try to add the address
        $address = $_GET['email'];
        $Sub = Subscriber::getByEmail($address);
        if ($Sub->getStatus() == Status::BLACKLIST) {
            $output['status'] = Status::BLACKLIST;
            $output['text'] = $LANG_MLR['email_blacklisted'];
        } elseif ($Sub->getID() > 0) {        // email already exists
            $output['status'] = Status::SUB_EXISTS;
            $output['text'] = $LANG_MLR['email_exists'];
        } else {
            $output['status'] = $Sub->subscribe();
            if ($output['status'] == Status::SUB_SUCCESS) {
                $output['text'] = $LANG_MLR['email_success'];
            } else {
                $output['text'] = $LANG_MLR['email_store_error'];
            }
        }
    } else {
        $output['text'] = $LANG_MLR['email_missing'];
    }
    break;
}
echo json_encode($output);
exit;
