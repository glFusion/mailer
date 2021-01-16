# Mailer plugin for glFusion
This plugin allows site users to subscribe and unsubscribe from a specified
mailing list via profile updates. When using an external provider, subscription
and unsubscription requests are received via webhooks to update the user profile.


## Features:
  - Supports multiple list handlers:
    - Mailchimp (https://mailchimp.com/)
    - Sendinblue (https://www.sendinblue.com/)
    - Internal message creation and queuing.
- New members can be automatically or optionally subscribed at registration
- Anyone can subscribe through a PHP block
- Cache update to sync local user accounts with list providers.
- Optional sync to subscribe all local users.
- Merge Fields can be obtained from other plugins, e.g. Membership.
  - Plugins should implement a `plugin_getiteminfo<plugin_name>` function
    which accepts at least the user ID and `merge_fields` as the `what` argument.
    This should return an array containing the user ID and an array of name=value
    pairs for merge fields.
  - Works with version 0.2.0 or later of the Membership plugin.
  - Converts images to inline data using phpmailer.

## Requirements:
  - glFusion vesion 1.7.8 or later
  - LGLib plugin version 1.0.12 or later

