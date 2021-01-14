<?php
/**
 * Define email statuses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.0.4
 * @since       v0.0.4
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Mailer\Models;


/**
 * The state of the order.
 * @package mailer
 */
class Status
{
    /** User has not subscribed.
     */
    public const UNSUBSCRIBED = 0;

    /** Requested subscription, awaiting double opt-in.
     */
    public const PENDING = 1;

    /** Normal recipient status.
     */
    public const ACTIVE = 2;

    /** Blacklisted, cannot receive emails.
     */
    public const BLACKLIST = 32;
}
