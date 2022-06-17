<?php
/**
 * Webhook handler for notifications from mailing list providers.
 * Updates the Mailer plugin table with subscriptions and removals,
 * and also updates the Users table with email address changes.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
require_once '../../lib-common.php';
use glFusion\Log\Log;

Log::write('mailer', Log::DEBUG, "Got Mailer Webhook Headers: " . var_export($_SERVER,true));
Log::write('mailer', Log::DEBUG, "Got Mailer Webhook GET: " . var_export($_GET, true));
Log::write('mailer', Log::DEBUG, "Got Mailer Webhook POST: " . var_export($_POST, true));
Log::write('mailer', Log::DEBUG, "Got Mailer php:://input: " . var_export(@file_get_contents('php://input'), true));

if (isset($_GET['p'])) {
    $WH = Mailer\Webhook::getInstance($_GET['p']);
    if ($WH->getProvider() == $_GET['p']) {
        $WH->Dispatch();
    } else {
        Log::write('system', Log::ERROR, "Unable to instantiate mailer webhook for {$_GET['p']}");
    }
}

