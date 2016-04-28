# One Time Login #
**Contributors:** danielbachhuber, getpantheon  
**Tags:** login  
**Requires at least:** 4.4  
**Tested up to:** 4.5.1  
**Stable tag:** 0.0.0  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Use WP-CLI to generate a one-time login URL for any user

## Description ##

Need access to a WordPress install but don't want to create a user account for it? Use this plugin and WP-CLI to generate a one-time login URL for any user:

    wp plugin install one-time-login --activate && wp user one-time-login <user>

After you run the command above, you'll see a success message like this:

    Success: Your one-time login URL is: http://wp.dev/wp-login.php?user_id=1&one_time_login_token=eb6f4de94323e589addb9ad3391883e1d6233bc3

Copy the URL, paste it into your web browser, and... voila!

Because it's a one-time login URL, it will only work once. If you need access again, you'll need to run the WP-CLI command again.

## Installation ##

See description for installation and usage instructions.

## Changelog ##

### 0.1.0 (April 28th, 2016) ###

* Initial release.
