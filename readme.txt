=== Bulk Plugin Installation ===
Contributors: beewh
Donate link: https://www.beewh.com
Tags: admin, automation, install, bulk, upload
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allows you to install one or more plugins simply by typing their names or download URLs in a textarea.

== Description ==

This plugin is an improvement to the current WordPress plugin installation methods. It allows you to install one or more plugins simply by typing their names or download URLs in a textarea.

Maintained and updated by **[Bee Web Hosting](https://www.beewh.com)**.

All credit goes to **improvingtheweb**. This plugin is only a modification of **Improved Plugin Installation**. The idea was to run the original plugin in the newer versions of WordPress. That's it. Also, we cut off the bookmarklet feature.

Thank you very much for the contributions: drewapicture

**Example**

The installation form will accept any of these inputs: 

* Contact Form 7
* https://wordpress.org/plugins/contact-form-7/
* https://downloads.wordpress.org/plugin/contact-form-7.zip

Plugins don't need to be hosted at wordpress.org. As long as you use the direct download URL, third party plugins will install without a problem.

== Installation ==

1. Download the plugin.
2. Unzip the plugin and upload the directory to `wp-content/plugins`.
3. Activate the plugin.
4. Go to "Plugins &raquo; Bulk Install" to use.

== Frequently Asked Questions ==

Nothing yet.

== Screenshots ==

Nothing yet.

== Changelog ==

= 2.0.1 =
* Fixed a bug where ZIP file URLs from the WordPress repository were not parsed correctly.
* Fixed a PHP syntax error caused by an unescaped apostrophe in error messages.
* Resolved Plugin Check tool warnings and security formatting standards.

= 2.0 =
* Rewritten to be fully compatible with PHP 8+ and WordPress 7.
* Replaced deprecated hooks with a modern standard WordPress admin submenu.
* Added full support for modern HTTPS URLs in the WordPress plugin repository.
* Updated developer information to Bee Web Hosting (https://www.beewh.com).

= 1.1 =
* Converted the plugin file to use WordPress coding standards, including converting spaces to tabs.
* Spanish translation added.
* POT file added.

= 1.0 =
* First version.

== Upgrade Notice ==

= 2.0 =
Major update to ensure compatibility with modern PHP 8+ and WordPress 6.x / 7.x.