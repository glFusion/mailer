# Mailer plugin for glFusion
This plugin allows site users to subscribe and unsubscribe from a specified
mailing list via profile updates. When using an external provider, subscription
and unsubscription requests are received via webhooks to update the user profile.


## Features:
  - Supports multiple list handlers:
    - Mailchimp (https://mailchimp.com/)
    - Sendinblue (https://www.sendinblue.com/)
    - MailerLite (https://mailerlite.com/)
    - Internal message creation and queuing.
- New members can be automatically or optionally subscribed at registration.
- Anyone can subscribe through a PHP block.
- Cache update to sync local user accounts with list providers and vice-versa.
- Optional sync to subscribe all local users.
- Merge Fields can be obtained from other plugins, e.g. Membership.
  - Plugins should implement a `plugin_getiteminfo<plugin_name>` function.
    which accepts at least the user ID and `merge_fields` as the `what` argument.
    This should return an array containing the user ID and an array of name=value.
    pairs for merge fields.
  - Works with version 0.2.0 or later of the Membership plugin.
  - Converts images to inline data using phpmailer.

## Requirements:
  - glFusion vesion 1.7.8 or later
  - LGLib plugin version 1.0.12 or later

## Provider Setup
Comparison of the free plans by provider.
| Provider | Subscribers | Mail Limit | SMTP Relay| Multiple Users | Multiple Lists |
| --- | --- | --- | :---: | :---: | :---: |
| Mailchimp | 2000 | 10000/mo | No | No | No |
| Sendinblue | No Limit | 300/day | Yes | No | Yes |
| MailerLite | 1000 | 12000/mo | No | Yes | Yes |
| Mailjet (not supported)| No Limit | 200/day, 6000/mo | Yes | No | Yes |

### Mailchimp
  - Create an account and log in at https://mailchimp.com.
  - Create a mailing list ("audience") and enter the list ID in the plugin configuration.
  - Select "All Contacts" > "Settings" > "Audience Fields and Merge Tags".
  - Add text labels and tags for First Name and Last Name. Enter the tags
    in your plugin configuration.
  - Still on the Audience page, select "Settings" > "Webhooks".
  - Click "Create a new Webhook".
    - Webhook URL: `your_site`/mailer/hook.php?p=Mailchimp
    - Events: subscribes, unsubscribes, profile updates, cleaned address, email changed
    - Only send updates when change is made: by a subscriber, by an account admin
  - Open your account settings and select Extras > API Keys.
  - Create an API key and enter it in the plugin configuration.

### Sendinblue
  - Create an account and log in at https://www.sendinblue.com.
  - Create a mailing list and enter the list ID in the plugin configuration.
  - Select "Settings" > "Webhooks" and "Add a new Webhook".
    - URL to post to: `your_site`/mailer/hook.php?p=Sendinblue
    - When message is: unsubscribed
    - When a contact is: Added to the list, Updated, Deleted
  - Select Account Settings (upper right) and "SMTP & API"
  - Create an API key and enter it in the plugin configuration.
    - Note, Sendinblue can also be used as a general SMTP relay for glFusion
      by creating SMTP credentials.

### MailerLite
  - Create an account ang log in at https://app.mailerlite.com.
  - Optionally create a new group. "Groups" represent mailing lists.
  - Click on the account menu icon (top left) and select "Integrations".
  - Click the "Use" button for Developer API.
  - Make a note of your API key and the GroupID for your subscriber group.
    Enter these values in the plugin configuration.
  - Return to the admin page for the Mailer plugin to add webhooks.
    MailerLite does not support adding webhooks via the GUI.
    - Click the question mark in the main menu to expand the dialog.
    - Click the "Create Webhooks" button.
  - There is currently no way to verify webhook creation via the plugin or MailerLite GUI.
    - Visit https://developers.mailerlite.com/reference#get-webhooks-list
    - Enter your API key by clicking the icon next to the "Try It" button.
    - Click the "Try It" button. You can expand the returned values to verify the webhooks.
    There should be six webhooks:
      - subscriber.create
      - subscriber.update
      - subscriber.unsubscribe
      - subscriber.added_through_webform
      - subscriber.complaint (not currently used)
      - campaign.sent (not currently used)

### Mailjet
  - Create an account ang log in at https://mailjet.com.
  - Create or update a Contact List (from the "Contacts" menu).
  - Click on the account menu icon (top right) and select "Account Settings".
  - Click on "Master API Key" in the REST API section.
  - Create a master API key if not already shown and note the API Key and Secret Key values.
    Enter these values in the plugin configuration.
  - Return to the Account Settings and click "Event Notifications" under the REST API section.
  - Select "Spam", "Blocked" and "Unsub" event types and enter your webhook URL for each.
    The URL is http(s)?://<yoursite.com>/mailer/hook.php?p=Mailjet
  - *IMPORTANT* - Go to the Bad Behavior configuration in your site and whitelist the url
    `/mailer/hook.php`.
