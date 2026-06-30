=== Society GoVernX ===
Contributors: vishwas-r
Tags: society, management, portal, billing, ledger
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive society management portal for residents, staff, billing, notices, polls, and ledger entries.

== Description ==

A comprehensive, state-of-the-art society management portal for managing flat residents, staff, billing, notices, democracy/polls, and ledger entries.

== Installation ==

1. Upload the `society-governx` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access the setup wizard from the admin menu to initialize your database tables.

== Changelog ==

= 1.0.3 =
* Localized all external CDN stylesheets and font enqueues.
* Added sanitization callbacks to registered plugin settings.
* Escaped all wp_die error outputs.
* Resolved translation function I18n domain constraints.
* Set tested-up-to version to 7.0 and fixed stable tag alignment.

= 1.0.2 =
* Secure input sanitization and unslashing.
* Full WPCS timezone and date alignment.
* Removed UTF-8 BOM bytes to prevent JSON parse errors.
