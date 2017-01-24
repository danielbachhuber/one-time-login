# One Time Login #
**Contributors:** danielbachhuber, getpantheon  
**Tags:** login  
**Requires at least:** 4.4  
**Tested up to:** 4.7.1  
**Stable tag:** 0.1.2  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Use WP-CLI to generate a one-time login URL for any user

## Description ##

Need access to a WordPress install but don't want to create a new user account? Use this plugin and WP-CLI to generate a one-time login URL for any existing user:

    wp plugin install one-time-login --activate && wp user one-time-login <user>

After you run the command above, you'll see a success message like this:

    Success: Your one-time login URL is: http://wp.dev/wp-login.php?user_id=1&one_time_login_token=eb6f4de94323e589addb9ad3391883e1d6233bc3

Copy the URL, paste it into your web browser, and... voila!

Because it's a one-time login URL, it will only work once. If you need access again, you'll need to run the WP-CLI command again.

Feel free to [file issues and pull requests](https://github.com/runcommand/one-time-login) against the project on Github.

## Installation ##

See description for installation and usage instructions.

## Changelog ##

### 0.1.2 (June 11th, 2016) ###

* Fires `one_time_login_created` action when login URL is created, and `one_time_login_logged_in` action when user is logged in via one-time login URL.

### 0.1.1 (May 26th, 2016) ###

* Bug fix: Pass `$assoc_args` into the command to ensure the `--porcelain` flag actually works.

### 0.1.0 (April 28th, 2016) ###

* Initial release.
