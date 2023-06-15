=== One Time Login ===
Contributors: danielbachhuber, aaronjorbin, acali, gdespoulain, masakik
Tags: login
Requires at least: 4.4
Tested up to: 6.2
Stable tag: 0.5.0
Requires PHP: 7.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use WP-CLI to generate a one-time login URL for any user

== Description ==

Need access to a WordPress install but don't want to create a new user account? Use this plugin to generate one-time login URLs for any existing user.
Then, copy the URL, paste it into your web browser, and... voila!

Because they are one-time login URLs, they will only work once. If you need access again, you'll need to run the WP-CLI command again.

=== Using WP CLI to generate OTT URLs ===

==== Example ====

    wp plugin install one-time-login --activate && wp user one-time-login <user> --count=3 --delay-delete --expiry=0

After you run the command above, you'll see a success message like this:

    http://wpdev.test/wp-login.php?user_id=2&one_time_login_token=93974b48e3a418b895fc7ca476f1a607d8b99345

Or like this if you asked for more than one:

	http://wpdev.test/wp-login.php?user_id=1&one_time_login_token=2b9c6f5d71d51d530e397ee9da3b50e4e3dd06e7
	http://wpdev.test/wp-login.php?user_id=1&one_time_login_token=90897da439a116c613fc1c49c372e6b1f7c72ad8
	http://wpdev.test/wp-login.php?user_id=1&one_time_login_token=68c8074743de849db606500c3caa39a7432dc601

==== Parameters ====

* *count*: Generate more than one login token (default: 1);
* *delay-delete*: Delete existing tokens after 15 minutes, instead of immediately.
* *expiry*: Delete existing token after "expiry" minutes from creation, even if not used (default: 0 - never expiry).

=== Using WP API to generate OTT URLs ===

==== Example with cUrl ====

	curl -X POST \
		http://wpdev.test/wp-json/one-time-login/v1/token
		-H 'authorization: Basic YWRtaW46eFRQeUJ5c3hEckhkY3BNYjE2endiQ2tj'
		-H 'cache-control: no-cache'
		-H 'postman-token: 8dcfa79a-401a-2c7d-c593-703e683ce785'
		-d '{
			"user":"admin",
			"count": 3,
			"delay-delete": true
			"expiry": 5
		}'

==== Parameters ====

Just as with WP CLI, you can add the **count**, **delay_delete** and **expiry** parameters to your call.

Feel free to [file issues and pull requests](https://github.com/danielbachhuber/one-time-login) against the project on Github.

== Installation ==

See description for installation and usage instructions.

== Changelog ==

### 0.5.0 (June 15th, 2023) ###
* Introduces `--expiry` flag to delete tokens after "expiry" minutes from creation [[#1](https://github.com/danielbachhuber/one-time-login/issues/1)].

= 0.4.0 (August 30th, 2021) =
* Introduces `one-time-login/v1/token` WP REST API endpoint to generate tokens [[#28](https://github.com/danielbachhuber/one-time-login/pull/28)].

= 0.3.1 (June 1st, 2021) =
* Fires `one_time_login_after_auth_cookie_set` action after the auth cookie is set [[#27](https://github.com/danielbachhuber/one-time-login/pull/27)].

= 0.3.0 (May 24th, 2018) =
* Introduces `--delay-delete` flag to delete old tokens after 15 minutes instead of immediately.
* Improves invalid token message when user is already logged in: "Invalid one-time login token, but you are logged in as 'user_login'. Go to the dashboard instead?".

= 0.2.0 (May 3rd, 2018) =
* Introduces support for multiple one-time login links.
* Links to the login screen from the "Invalid token" error message.

= 0.1.2 (June 11th, 2016) =
* Fires `one_time_login_created` action when login URL is created, and `one_time_login_logged_in` action when user is logged in via one-time login URL.

= 0.1.1 (May 26th, 2016) =
* Bug fix: Pass `$assoc_args` into the command to ensure the `--porcelain` flag actually works.

= 0.1.0 (April 28th, 2016) =
* Initial release.
