=== Razorpay Subscriptions for WooCommerce ===
Contributors: razorpay
Tags: razorpay, payments, india, woocommerce, ecommerce, recurring, subscriptions
Requires at least: 3.9.2
Requires PHP: 5.6.0
Tested up to: 6.6
Stable tag: 2.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to use Razorpay payment gateway with the WooCommerce Subscriptions plugin. This requires Subscriptions feature to be enabled for your account. Please reach out at <https://razorpay.com/support/> for the same.

== Description ==

This is the official Razorpay Subscriptions payment gateway plugin for WooCommerce. Allows you automatically charge customers on a recurring basis, based on a billing cycle that you control. You can easily create and manage Subscriptions and get instant alerts on payment activity as well as the status of Subscriptions.

Razorpay Subscription Plugin helps you to start accepting recurring payments on your WordPress website:
- Very quick and merchant friendly integration
- Via Credit Card, Debit Card, Net Banking and UPI payment methods
- No set-up costs are involved. It’s a free plugin

This is compatible with WooCommerce>=2.4, including the new 9.0 release. It has been tested upto the 9.1.2 WooCommerce release. This also requires the WooCommerce Subscriptions plugin to be installed on your server. (Tested upto 6.5.0 version of the WooCommerce Subscriptions release).

== Installation ==

1. Install the plugin from the [Wordpress Plugin Directory](https://wordpress.org/plugins/razorpay-subscriptions-for-woocommerce/).
2. To use this plugin correctly, you need to be able to make network requests. Please make sure that you have the php-curl extension installed.
3. Make sure you have the following plugins installed: `WooCommerce Razorpay`, `WooCommerce Subscriptions`, `WooCommerce`.

== Dependencies ==

1. Wordpress v3.9.2 and later
2. WooCommerce v2.4 and later
3. WooCommerce Subscriptions v2.2 and later
4. [Razorpay WooCommerce Plugin](https://wordpress.org/plugins/woo-razorpay/) 2.8.2 and later
5. PHP v5.6.0 and later
6. php-curl

== Configuration ==

1. Visit the WooCommerce settings page, and click on the Checkout/Payment Gateways tab.
2. Click on Razorpay to edit the settings. If you do not see Razorpay in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, name it Credit Card / Debit Card / Internet Banking (this will show up on the payment page your customer sees), add in your Key id and Key Secret.
5. Setup Webhooks as per [this guide](https://github.com/razorpay/razorpay-woocommerce/wiki/Webhooks).

== Frequently Asked Questions ==

= What is Razorpay? =

Razorpay is a full-stack payments solution that enables thousands of online and offline businesses to accept, process and disburse payments on the web and mobile apps.

= What’s a WordPress Plugin? =

A WordPress plugin is a piece of code that you may use to enhance the features and functionality of your current WordPress site.

= What is Subscription? =

Subscriptions does not require any intervention from the customer. It is an automated payment collection system that requires a one-time approval from the customer via an authentication transaction.
Merchants can automatically charge customers based on a billing cycle that they  control.

= What does the Razorpay Subscription plugin for WordPress do? =

It helps you add recurring payments to your WordPress website or blog with a simple integration.

= Do I need a Razorpay account for using plugins? =

Yes, you will have to sign-up for a Razorpay account. Here’s a quick guide for you.

= Can I accept UPI and credit card payments on my WordPress website? =

Yes, with Razorpay Subscription Plugin you can accept payments via multiple payment methods such as  UPI, Credit/Debit cards and Net-banking.

= Can I accept international payments? =

Yes, you can accept international payments with Razorpay Subscriptions. Here’s a quick guide for you.

= How do I enable Multi-currency support =

Please get multi-currency enabled for your account. Once you have it enabled, you can install any plugin version higher than 2.0.0, which comes with native multi-currency support.

= Is it safe to collect payments from Razorpay? =

Safe money movement with our 100% secure ecosystem guarded with PCI DSS compliance.

= Where can I find a report and analysis of all transactions? =

You can download all of your transactions with the details of your customers from your Razorpay dashboard.

= What is the platform fee for using Razorpay to accept payments? =

We offer a simple, transparent pricing of 2% fee per transaction amount. However, if you’d like a customised plan for your business, you can read more here.

= Does this support webhooks? =

Please make sure that you have Webhooks setup on the [Razorpay Plugin](https://wordpress.org/plugins/woo-razorpay/) to ensure that recurring payments are marked as paid on WooCommerce.

= Is there a limit on how many years a Subscription can remain active or a limit on maximum number of billing cycles for a subscription? =

We support Subscriptions for a maximum duration of 100 years.
The number of billing cycles depends if the subscription is billed daily, weekly, monthly or yearly.

== Changelog ==

= 2.4.1 =
* Updated documentation.

= 2.4.0 =
* Added, Validation for Api key and secret.
* Added, On-hold subscription as a cancellable status.
* Added, Support for Checkout blocks.
* Fixed, Duplicate Subscriptions fix.
* Fixed, Repeat api call for webhook.

= 2.3.9 =
* Added WC_Subscriptions_Cart check

= 2.3.8 =
* Fixed Tax calculation for signup fee

= 2.3.7 =
* Fixed Renewal order creation bug

= 2.3.6 =
* Added support for HPOS.
* Tested upto Wordpress 6.2.2 and Woocommerce subscription 5.3.1.

= 2.3.5 =
* Fixed multiple webhook API calls.
* Added subscription.charged webhook event to fix the woocommerce subscription status update
* Tested upto WordPress 6.1.1 and WooCommerce subscription 4.5.1.

= 2.3.4 =
* Fixed multiple payment options on checkout.
* Tested upto WordPress 6.0.1 and WooCommerce subscription 4.0.2.

= 2.3.3 =
* Add Auto Enable Webhooks feature.
* This feature is compatible with Razorpay Woocommerce plugin 3.7.1.
* Tested upto WordPress 5.9.3 and WooCommerce subscription 4.0.2.

= 2.3.2 =
* Added woocommerce debug log for subscription webhooks.
* Tested upto WordPress 5.9.2 and WooCommerce 6.2.1 and WooCommerce subscription 4.0.2.

= 2.3.1 =
* Bug fix: Fixed place order issue regarding the authentication.
* Tested upto WordPress 5.9.1 and WooCommerce 6.2.1 and WooCommerce subscription 4.0.2.

= 2.3.0 =
* Added new features of subscription like pause and resume the subscription.
* Added subscription webhook events(pause, resume & cancel) and integrated.
* Tested upto WordPress 5.8.2 and WooCommerce 5.9.0 and WooCommerce subscription 3.1.6

= 2.2.2 =
* Bug fix: Cancellation of subscription from woo subscription

= 2.2.1 =
* Bug fix: Fixed the displaying of message in payment popup for yearly subscription

= 2.2.0 =
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

The Razorpay WooCommerce Subscriptions plugin is released under the GPLv2 license, same as that of WordPress. See the LICENSE file for the complete LICENSE text.