=== Society GoVernX ===
Contributors: vishwas-r
Tags: society, management, portal, billing, ledger, notices, polls, resident-directory, staff-management, facility-booking, rules-regulations, flat-management
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive, state-of-the-art society management portal for managing flat residents, staff, billing, notices, democracy/polls, and ledger entries.

== Description ==

Society GoVernX is a feature-rich, premium WordPress plugin designed to streamline the administration of housing societies, gated communities, and residential complexes. With dedicated modules for administrators and residents, it brings transparency, efficiency, and automated workflows to community management.

### Key Features:
* **Flat Management:** Track flat details, block structure, occupancy types (Owner/Tenant), and allocation.
* **Resident & Member Directory:** Register residents, manage member approvals, and search resident directory.
* **Rules & Regulations:** Post society rules, track violations, issue fines, and collect digital resident acknowledgments.
* **Staff & Vendor Management:** Keep records of society staff members, gatekeepers, and vendors with granular role-based capabilities.
* **Notice Board:** Dispatch real-time notices to residents via in-app feeds, emails, or WhatsApp alerts.
* **Democracy & Polls:** Create community polls, collect votes, and automatically compute results for transparent decision-making.
* **Facility & Amenity Booking:** Add facilities (e.g., clubhouse, gym, pool) and allow residents to book slots online.
* **Vehicle & Parking Management:** Track registered resident vehicles and assign parking bays.
* **Helpdesk & Requests:** Dedicated resident ticketing/request portal (My Requests) with customized approval workflows.
* **Finance, Billing & Ledger:** Generate maintenance bills, record payments, and track society financial ledgers with customizable billing cycles.

### GitHub & Support:
* **GitHub Repository:** [github.com/vishwas-r/Society-GovernX](https://github.com/vishwas-r/Society-GovernX)
* **Issue Tracker:** [github.com/vishwas-r/Society-GovernX/issues](https://github.com/vishwas-r/Society-GovernX/issues)

== Installation ==

1. Upload the entire `society-governx` folder to the `/wp-content/plugins/` directory, or install it directly via the WordPress Admin panel under Plugins > Add New.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Upon activation, access the **Setup Wizard** from the WordPress admin sidebar under **GovernX** to initialize your database tables and seed default settings.
4. To set up the frontend resident dashboard, create a new WordPress Page (e.g., "Resident Portal") and add the following shortcode:
   `[Society_GoVernX_dashboard]`
5. Publish the page. Residents will now be able to log in and access their dashboard from this page.

== Settings & Configurations ==

To configure the plugin, navigate to the **GovernX > Settings** menu in the WordPress Admin Dashboard. The settings are organized into the following tabs:

### 1. Society Profile
* Configure basic society details such as Society Name, Registration Number, Address, and Contact Info.
* Upload the society logo to personalize receipts and emails.

### 2. Bank Settings
* Enter the society's official banking details for maintenance collections: Account Name, Account Number, Bank Name, IFSC Code, Branch, and UPI ID.
* The UPI ID is used to generate dynamic payment links for residents.

### 3. Approval Workflow
* Customize the registration and ticketing approval steps.
* Configure whether new resident registrations, helpdesk requests, or vehicle additions require manual admin approval or are auto-approved.

### 4. Communication Settings
* Configure active notification channels: In-App notifications, Email, or WhatsApp.
* Toggle notification triggers for events like New Notices, Billing/Dues Issued, Polls Published, Rule Violations, or Acknowledgment Reminders.

### 5. Data & Maintenance (Data Portability)
* Import or Export society database tables (e.g., Residents, Rules, notice templates) using CSV formats to easily migrate data.

== Shortcodes ==

The plugin provides the following shortcodes to render portal elements on frontend pages:

* `[Society_GoVernX_dashboard]` - Renders the unified Resident Dashboard containing the notice feed, requests page, rule acknowledgments, facilities booking, and billing/dues log.
* `[Society_GoVernX_notices]` - Renders a standalone public notice board feed.
* `[Society_GoVernX_directory]` - Renders a searchable member directory (accessible only to authorized logged-in residents).

== Changelog ==

= 1.0.3 =
* Localized all external CDN stylesheets and font enqueues.
* Added sanitization callbacks to registered plugin settings.
* Escaped all wp_die error outputs.
* Resolved translation function I18n domain constraints.
* Set tested-up-to version to 7.0 and fixed stable tag alignment.
* Fixed rule version saving and suppressed database error outputs.
* Fixed resident acknowledgment tracking and database get query formats.
* Fixed request details JSON parsing bugs on frontend dashboard.

= 1.0.2 =
* Secure input sanitization and unslashing.
* Full WPCS timezone and date alignment.
* Removed UTF-8 BOM bytes to prevent JSON parse errors.
