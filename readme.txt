=== Joburg Meet-up Quiz ===
Contributors: distinct
Tags: quiz, game, leaderboard, wordpress-quiz
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

An interactive WordPress quiz game with leaderboard functionality and bonus rounds.

== Description ==

Test your WordPress knowledge with this fun and interactive quiz game! Features include:

* Multiple-choice questions about WordPress
* Real-time scoring system
* Global leaderboard with filtering options
* Bonus round "Whack-a-Wapuu" mini-game
* Persistent player statistics
* Time-based scoring mechanics

Perfect for WordPress meetups, training sessions, or just for fun!

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/distinct-jhb-quiz` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the shortcode [distinct_jhb_quiz] to display the quiz
4. Use the shortcode [distinct_jhb_leaderboard] to display the leaderboard

== Frequently Asked Questions ==

= How do I add the quiz to my page? =

Use the shortcode [distinct_jhb_quiz] in any post or page.

= How do I display the leaderboard? =

Use the shortcode [distinct_jhb_leaderboard] where you want the leaderboard to appear.

= Can I customize the quiz questions? =

Currently, questions are hardcoded but future versions will include an admin interface for custom questions.

== Screenshots ==

1. Quiz interface
2. Leaderboard display
3. Bonus round game
4. Admin settings page

== Changelog ==

= 1.1.0 =
* Added caching for improved performance
* Fixed security issues with database queries
* Improved error handling
* Added proper escaping for output
* Added admin settings for leaderboard time ranges

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
This version includes important security fixes and performance improvements. All users should upgrade.

== Privacy Policy ==

This plugin stores the following user data:
* First name
* Last name
* Quiz scores and attempts

This data is used solely for leaderboard functionality and is visible to all users viewing the leaderboard.