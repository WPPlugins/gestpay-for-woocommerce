=== Gestpay for WooCommerce ===
Contributors: easynolo
Tags: woocommerce, payment gateway, payment, credit card, gestpay, gestpay starter, gestpay pro, gestpay professional, banca sella, sella.it, easynolo, iframe, direct payment gateway
Requires at least: 4.0.1
Tested up to: 4.7.5
Stable tag: 20170602
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Gestpay for WooCommerce extends WooCommerce providing the payment gateway for Gestpay.

== Description ==

Gestpay for WooCommerce is a payment gateway for WooCommerce which allows you to use Gestpay on your WooCommerce-powered website.

There are four operational modes in this plugin, which depends on Gestpay version you are using:

* Gestpay Starter
* Gestpay Professional
* Gestpay Professional On Site
* Gestpay Professional iFrame

[Click here to read the full usage documentation on Gestpay](http://docs.gestpay.it/plugins/gestpay-for-woocommerce/ "Gestpay for WooCommerce - Usage Documentation").

== Actions and filters list ==

Here is a list of filters and actions used in this plugin:

= Actions =

* gestpay_before_processing_order
* gestpay_after_order_completed
* gestpay_after_order_failed
* gestpay_before_order_settle
* gestpay_order_settle_success
* gestpay_order_settle_fail
* gestpay_before_order_refund
* gestpay_order_refund_success
* gestpay_order_refund_fail
* gestpay_before_order_delete
* gestpay_order_delete_success
* gestpay_order_delete_fail

= Filters =

* gestpay_gateway_parameters
* gestpay_encrypt_parameters
* gestpay_settings_tab
* gestpay_my_cards_template

== Installation ==

1. Ensure you have the WooCommerce 2.6+ plugin installed
2. Search "Gestpay for WooCommerce" or upload and install the zip file, in the same way you'd install any other plugin.
3. Read the [usage documentation on Gestpay](http://docs.gestpay.it/plugins/gestpay-for-woocommerce/ "Gestpay for WooCommerce - Usage Documentation").

== Changelog ==

= 20170602 =
* Fix error "-1" that happens when using the S2S notify URL.
* Verified compatibility with WooCommerce Subscriptions 2.2.7

= 20170508 =
* Fix - Moved ini_set( 'serialize_precision', 2 ) to the Helper, to avoid rounding conflicts.
* Checks - Verified compatibility with WooCommerce v 3.0.5

= 20170502 =
* Fix - Verify if class WC_Subscriptions_Cart exists before disabling extra Gestpay payment types.

= 20170427 =
* Checks - Verified compatibility with WooCommerce version 2.6.14 and 3.0.4
* Checks - Verified compatibility with WooCommerce Subscriptions version 2.1.4 and 2.2.5
* Feature - Added support for Tokenization+Authorization (here called "On-Site") and iFrame services.
* Feature - Added support for 3D Secure and not 3D Secure payments.
* Feature - Added endpoint to handle cardholder's cards/tokens for the "On-Site" version.
* Feature - Added Refund/Settle/Delete S2S actions for transactions.
* Feature - Added more filters and actions.
* Feature - Disable extra Gestpay payment methods when paying a subscription.
* Fix - Correctly loading of plugin localization.
* Fix - Show/Hide Pro options on the configuration page.
* Fix - Removed extra payment "upmobile", which is not used anymore.

= 20170224 =
* First public release.