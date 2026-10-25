=== Demfilz Request Info ===
Contributors: demfilz
Tags: enquiry, request info, contact form, popup, woocommerce
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a "Request Info" button to products, pages and posts that opens a popup enquiry form. Requests are emailed to you and stored in the dashboard.

== Description ==

Demfilz Request Info adds a clean "Request Info" button to your WooCommerce products, pages and posts. Clicking it opens a popup enquiry form with Name, Company, Phone, Email, Suburb, Postcode and Details fields.

**Highlights**

* Works on WooCommerce products (after the add-to-cart area), pages and posts - each can be switched on or off.
* Place the button anywhere with the `[request_info_button text="..."]` shortcode.
* Logged-in users are pre-filled automatically (name, email, and their billing company, phone, suburb and postcode when available).
* The Details field is pre-filled with the page or product name and link, so you always know what the enquiry is about.
* Every request is emailed to you as a clean HTML email (with Reply-To set to the sender) and stored in the dashboard under Request Info, with the page it came from.
* Spam protection built in: honeypot field and a per-visitor rate limit. No CAPTCHAs, no external services.
* Customisable button text and colours, and a customisable success message.
* Lightweight vanilla JavaScript - no jQuery, no external requests, accessible (focus trap, ESC to close, reduced-motion support).

WooCommerce is optional: without it the button simply appears on pages and posts.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/demfilz-request-info`, or install through the WordPress plugins screen.
2. Activate the plugin.
3. Go to Request Info > Settings to choose where the button appears and who receives the requests.

== Frequently Asked Questions ==

= Where are the requests stored? =

Under Request Info > All Requests in the WordPress admin. Each request records the name, company, phone, email, suburb, postcode, details and the page or product it was sent from. Requests are also emailed to the configured recipient (the site admin email by default).

= How do I put the button somewhere specific? =

Use the shortcode `[request_info_button]` anywhere shortcodes work. An optional `text` attribute overrides the button label: `[request_info_button text="Ask about this"]`.

= Does it need WooCommerce? =

No. With WooCommerce active the button can appear on single product pages; without it the plugin works on pages and posts.

= Which details are pre-filled for logged-in users? =

Name (first/last or display name) and email from the user profile, plus billing company, phone, suburb (billing city) and postcode when WooCommerce billing details exist. Visitors can edit every field before sending.

= Does the plugin call any external services? =

No. Everything runs on your own site and emails are sent through WordPress.

== Screenshots ==

1. The Request Info button on a product page.
2. The popup enquiry form.
3. Requests stored in the dashboard.
4. The settings screen.

== Changelog ==

= 1.0.1 =
* HTML notification emails.
* Removed the button icons for a cleaner look.
* The form resets 10 seconds after a successful send so another request can be submitted.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.1 =
HTML emails, cleaner buttons, form auto-reset after sending.
