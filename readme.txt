=== Razorpay Subscriptions for WooCommerce ===
Contributors: razorpay
Tags: razorpay, payments, india, woocommerce, ecommerce, recurring, subscriptions
Requires at least: 3.9.2
Requires PHP: 5.6.0
Tested up to: 5.8
Stable tag: 2.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to use Razorpay payment gateway with the WooCommerce Subscriptions plugin. This requires Subscriptions feature to be enabled for your account. Please reach out at <https://razorpay.com/support/> for the same.

== Description ==

This is the official Razorpay Subscriptions payment gateway plugin for WooCommerce. Allows you to accept recurring payments on WooCommerce Subscriptions using the Razorpay Subscriptions API.

This is compatible with WooCommerce>=2.4, including the new 3.0 release. It has been tested upto the 5.4.1 WooCommerce release. This also requires the WooCommerce Subscriptions plugin to be installed on your server. (Tested upto 3.0.9 version of the WooCommerce Subscriptions release).

== Installation ==

1. Install the plugin from the [Wordpress Plugin Directory](https://wordpress.org/plugins/razorpay-subscriptions-for-woocommerce/).
2. To use this plugin correctly, you need to be able to make network requests. Please make sure that you have the php-curl extension installed.
3. Make sure you have the following plugins installed: `WooCommerce Razorpay`, `WooCommerce Subscriptions`, `WooCommerce`.

== Dependencies ==

1. Wordpress v3.9.2 and later
2. WooCommerce v2.4 and later
3. WooCommerce Subscriptions v2.2 and later
4. [Razorpay WooCommerce Plugin](https://wordpress.org/plugins/woo-razorpay/) 2.0.0 and later
5. PHP v5.6.0 and later
6. php-curl

== Configuration ==

1. Visit the WooCommerce settings page, and click on the Checkout/Payment Gateways tab.
2. Click on Razorpay to edit the settings. If you do not see Razorpay in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, name it Credit Card / Debit Card / Internet Banking (this will show up on the payment page your customer sees), add in your Key id and Key Secret.
5. Setup Webhooks as per [this guide](https://github.com/razorpay/razorpay-woocommerce/wiki/Webhooks).

== Frequently Asked Questions ==

1. We currently do not support lifetime subscriptions as of now. The maximum time that a subscription is allowed to run without requiring an authentication again from the customer is 10 years.
2. Please make sure that you have Webhooks setup on the [Razorpay Plugin](https://wordpress.org/plugins/woo-razorpay/) to ensure that recurring payments are marked as paid on WooCommerce.

== Changelog ==

= 2.2.1
* Bug fix: Cancellation of subscription from woo subscription

= 2.2.1
* Bug fix: Fixed the displaying of message in payment popup for yearly subscription

= 2.2.0
* Tested upto WordPress 5.7.2 and WooCommerce 5.4.1 and WooCommerce subscription 3.0.9

= 2.1.0 =
* Support international currency
* Handled duplicate notification for single webhook
* Add feature after cancel subscription from razorpay dashboard, cancelled subscription at woo-commerce dashboard also
* Bug fix: Resolve repetition of new order after successfully retry charge.

= 2.0.0 =
* Fix Support Links
* Handled never expiry subscription exception
* Handled upfront payment in case of single subscription opt
* Compatible with razorpay-woocommerce 2.x

= 1.0.1 =
* Bug fix: disallowing plugin usage if base plugin directory doesn't exist

* Initial Release

== Support ==

Visit [razorpay.com/support](https://razorpay.com/support/) for support requests.

== License ==

The Razorpay WooCommerce Subscriptions plugin is released under the GPLv2 license, same as that
of WordPress. See the LICENSE file for the complete LICENSE text.
