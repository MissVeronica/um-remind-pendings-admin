# UM Remind Pendings Admin
Extension to Ultimate Member with an email template sent daily or weekly with a placeholder {remind-pendings-admin} which creates a list to Remind Admin about all Users Pending for a Review.

## UM Settings -> Email -> Template "Remind Pendings Admin - Daily/Weekly email"
* Daily Reminder to Admin - Click to send the email reminder to admin daily otherwise email is sent weekly.
* Select weekday for reminder email - Default weekday for sending the Reminder email is Monday.
* Select time for reminder email - Default time during the day for scheduling the WP cronjob to send the Reminder email is at 06:00.
* Enable UM Dashboard status - Click to get number of Users waiting for a review and the schedule for next email at the UM dashboard and the possibilty to send extra Admin emails.

## UM Dashboard
* %d Users are waiting for an Admin review
* The next Admin Reminder email is scheduled at %s
* Send Admin Reminder email now

## Email template "remind_pendings_admin.php"
*  The template is copied to the site's active theme's "ultimate-member/email" folder by the plugin at activation.
*  Plugin placeholder: {remind-pendings-admin} - for listing of all Admin review pending Users
*  UM placeholders: https://docs.ultimatemember.com/article/1340-placeholders-for-email-templates

## Translations or Text changes
* Use the "Say What?" plugin with text domain ultimate-member
* https://wordpress.org/plugins/say-what/

## References
* Additional email Recipients https://github.com/MissVeronica/um-additional-email-recipients

## Updates
None

## Installation & Updates
* Install and update by downloading the plugin ZIP file via green "Code" button
* Install as a new Plugin, which you upload in WordPress -> Plugins -> Add New -> Upload Plugin.
* Activate the Plugin

