<?php

/*
Plugin Name: Razorpay Subscriptions for WooCommerce
Plugin URI: https://razorpay.com
Description: Razorpay Subscriptions for WooCommerce
Version: 2.4.1
Stable tag: 2.4.1
Author: Razorpay
Author URI: https://razorpay.com
*/

if ( ! defined( 'ABSPATH' ) )
{
    exit; // Exit if accessed directly
}

define('RAZORPAY_WOOCOMMERCE_PLUGIN', 'woo-razorpay');

$pluginRoot = WP_PLUGIN_DIR . '/' . RAZORPAY_WOOCOMMERCE_PLUGIN;

if ( ! is_dir( $pluginRoot ) )
{
    define('RAZORPAY_WOOCOMMERCE_PLUGIN_FROM_GIT', 'razorpay-woocommerce');
    $pluginRoot = WP_PLUGIN_DIR . '/' . RAZORPAY_WOOCOMMERCE_PLUGIN_FROM_GIT;

    if(! is_dir( $pluginRoot )){
        return;
    }
}

require_once $pluginRoot . '/woo-razorpay.php';
require_once $pluginRoot . '/razorpay-sdk/Razorpay.php';
require_once __DIR__ . '/includes/razorpay-subscription-webhook.php';
require_once __DIR__ . '/includes/Errors/SubscriptionErrorCode.php';
require_once __DIR__ . '/includes/razorpay-subscriptions.php';
require_once __DIR__ . '/includes/razorpay-subscription-debug.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

// Load this after the woo-razorpay plugin
add_action('plugins_loaded', 'woocommerce_razorpay_subscriptions_init', 20);
add_action('admin_post_nopriv_rzp_wc_webhook', 'razorpay_webhook_subscription_init', 20);

register_activation_hook(__FILE__, 'razorpaySubscriptionPluginActivated');
register_deactivation_hook(__FILE__, 'razorpaySubscriptionPluginDeactivated');

add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil'))
    {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('woocommerce_blocks_loaded', 'razorpay_subscription_woocommerce_block_support');

function razorpay_subscription_woocommerce_block_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType'))
    {
        require_once dirname( __FILE__ ) . '/checkout-block.php';

        add_action(
          'woocommerce_blocks_payment_method_type_registration',
          function(PaymentMethodRegistry $payment_method_registry) {
            $container = Automattic\WooCommerce\Blocks\Package::container();
            $container->register(
                WC_Razorpay_Subscription_Blocks::class,
                function() {
                    return new WC_Razorpay_Subscription_Blocks();
                }
            );
            $payment_method_registry->register($container->get(WC_Razorpay_Subscription_Blocks::class));
          }
        );
    }
}

function woocommerce_razorpay_subscriptions_init()
{
    if (!class_exists('WC_Payment_Gateway'))
    {
        return;
    }

    class WC_Razorpay_Subscription extends WC_Razorpay
    {
        /**
         * Unique ID for the gateway
         * @var string
         */
        public $id = 'razorpay_subscriptions';

        /**
         * Title of the payment method shown on the admin page.
         * @var string
         */
        public $method_title = 'Razorpay Subscriptions';

        /**
         * This array controls what settings are visible to the user
         * @var array
         */
        protected $visibleSettings = array(
            'enabled',
            'title',
            'description',
        );

        /**
         * Contains all supported methods of woocommerce subscriptions
         * @var array
         */
        public $supports = array(
            'subscriptions',
            'subscription_reactivation',
            'subscription_suspension',
            'subscription_cancellation',
        );

        /**
         * Instance of the class RZP_subscriptions found in __DIR__ . 'includes/razorpay-subscriptions'
         * It is used to make all subscriptions related API calls to the Razorpay's API
         * @var RZP_Subscriptions
         */
        protected $subscriptions;

        public $setting;

        const RAZORPAY_SUBSCRIPTION_ID       = 'razorpay_subscription_id';
        const DEFAULT_LABEL                  = 'MasterCard/Visa Credit Card';
        const DEFAULT_DESCRIPTION            = 'Setup automatic recurring billing on a MasterCard or Visa Credit Card';

        public function __construct()
        {
            parent::__construct();

            $this->icon = plugins_url('images/logo.png', __FILE__);

            $this->mergeSettingsWithParentPlugin();

            $this->setupExtraHooks();
        }

        private function mergeSettingsWithParentPlugin()
        {
            // Easiest way to read config of a different plugin
            // is to initialize it
            $wcRazorpay = new WC_Razorpay(false);

            $parentSettings = array(
                'key_id',
                'key_secret',
                'order_success_message',
            );

            foreach ($parentSettings as $key)
            {
                $this->settings[$key] = $wcRazorpay->settings[$key];
            }

            $this->setting['webhook_secret'] = (empty($wcRazorpay->getSetting('webhook_secret')) === false)
                                                ? $wcRazorpay->getSetting('webhook_secret')
                                                : get_option('webhook_secret');

            if (empty($this->setting['webhook_secret']) === true) {
                $this->setting['webhook_secret'] = get_option('rzp_webhook_secret');
                if (empty($this->setting['webhook_secret']) === false) {
                    $wcRazorpay->update_option('webhook_secret', $this->setting['webhook_secret']);
                }
            }
        }

        protected function setupExtraHooks()
        {
            add_action('woocommerce_subscription_status_cancelled', array(&$this, 'subscription_cancelled'));
            add_action( 'woocommerce_subscription_status_pending-cancel',  array(&$this, 'subscription_cancelled'));
            add_action( 'woocommerce_subscription_status_on-hold',  array(&$this, 'subscription_on_hold'));
            add_action( 'woocommerce_subscription_status_on-hold_to_active',  array(&$this, 'subscription_reactivate'));

            // Hide Subscriptions Gateway for non-subscription payments
            add_filter('woocommerce_available_payment_gateways', array($this, 'disable_non_subscription'), 20);
        }

        public function disable_non_subscription($availableGateways)
        {
            $enable = false;

            if (class_exists(WC_Subscriptions_Cart::class) and
                WC_Subscriptions_Cart::cart_contains_subscription())
            {
                $enable = true;
            }

            if ($enable === false)
            {
                if (isset($availableGateways[$this->id]))
                {
                    unset($availableGateways[$this->id]);
                }
            }
            else
            {
                if (isset($availableGateways['razorpay']))
                {
                    unset($availableGateways['razorpay']);
                }
            }

            return $availableGateways;
        }

        public function admin_options()
        {
            echo '<h3>'.__('Razorpay Subscriptions Payment Gateway', $this->id) . '</h3>';
            echo '<p>'.__('Allows recurring payments by MasterCard/Visa Credit Cards') . '</p>';
            echo '<table class="form-table">';

            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        protected function getSubscriptionSessionKey($orderId)
        {
            return self::RAZORPAY_SUBSCRIPTION_ID . $orderId;
        }

        protected function getRazorpayPaymentParams($order, $orderId)
        {
            global $woocommerce;

            $this->subscriptions = new RZP_Subscriptions($this->getSetting('key_id'), $this->getSetting('key_secret'));

            try
            {
                $subscriptionId = null;

                if ($this->isHposEnabled())
                {
                    $subscriptionId = $order->get_meta(self::RAZORPAY_SUBSCRIPTION_ID);
                }
                else
                {
                    $subscriptionId = get_post_meta($orderId, self::RAZORPAY_SUBSCRIPTION_ID, true);
                }

                if (empty($subscriptionId) === false)
                {
                    $sessionKey = $this->getSubscriptionSessionKey($orderId);

                    $woocommerce->session->set($sessionKey, $subscriptionId);
                }
                else
                {
                    $subscriptionId = $this->subscriptions->createSubscription($orderId);
                }

                rzpSubscriptionErrorLog("getRazorpayPaymentParams triggered and subscription created with id:-".json_encode($subscriptionId));

                if ($this->isHposEnabled())
                {
                    $order->update_meta_data(self::RAZORPAY_SUBSCRIPTION_ID, $subscriptionId);
                    $order->save();
                }
                else
                {
                    update_post_meta($orderId, self::RAZORPAY_SUBSCRIPTION_ID, $subscriptionId);
                }
            }
            catch (Exception $e)
            {
                $message = $e->getMessage();
                rzpSubscriptionErrorLog("Woocommerce orderId: $orderId Subscription creation failed with the following message: $message");

                throw new Exception("RAZORPAY ERROR: Subscription creation failed with the following message: '$message'");
            }

            return [
                'recurring'       => 1,
                'subscription_id' => $subscriptionId,
            ];
        }

        public function init_form_fields()
        {
            parent::init_form_fields();

            $fields = $this->form_fields;

            unset($fields['payment_action']);

            $this->form_fields = $fields;
        }

        protected function getDisplayAmount($order)
        {
            return $this->subscriptions->getDisplayAmount($order);
        }

        protected function verifySignature($orderId)
        {
            global $woocommerce;

            $api = $this->getRazorpayApiInstance();

            $sessionKey = $this->getSubscriptionSessionKey($orderId);

            $attributes = array(
                self::RAZORPAY_PAYMENT_ID       => $_POST[self::RAZORPAY_PAYMENT_ID],
                self::RAZORPAY_SIGNATURE        => $_POST[self::RAZORPAY_SIGNATURE],
                self::RAZORPAY_SUBSCRIPTION_ID  => $woocommerce->session->get($sessionKey),
            );

            $api->utility->verifyPaymentSignature($attributes);

            if ($this->isHposEnabled())
            {
                $order = wc_get_order($orderId);
                $order->update_meta_data(self::RAZORPAY_SUBSCRIPTION_ID, $attributes[self::RAZORPAY_SUBSCRIPTION_ID]);
                $order->save();
            }
            else
            {
                update_post_meta($orderId, self::RAZORPAY_SUBSCRIPTION_ID, $attributes[self::RAZORPAY_SUBSCRIPTION_ID]);
            }
        }

        public function subscription_cancelled($subscription)
        {
            try {
                $this->subscriptions = new RZP_Subscriptions($this->getSetting('key_id'), $this->getSetting('key_secret'));

                $parentOrder = $subscription->get_parent();

                if (empty($parentOrder) === true)
                {
                    $log = array(
                        'Error' => 'Unable to cancel the order ' . $parentOrder,
                    );

                    error_log(json_encode($log));

                    return;
                }

                $subscriptionId = '';
                if ($this->isHposEnabled())
                {
                    $order = wc_get_order($parentOrder->get_id());
                    $subscriptionId = $order->get_meta(self::RAZORPAY_SUBSCRIPTION_ID);
                }
                else
                {
                    $subscriptionId = get_post_meta($parentOrder->get_id(), self::RAZORPAY_SUBSCRIPTION_ID)[0];
                }

                //Canceling the subscription value
                //0 (default): Cancel the subscription immediately.
                //1: Cancel the subscription at the end of the current billing cycle.
                $subscriptionCycleEndAt = ['cancel_at_cycle_end' => 0];
                if($subscription->get_status() == "pending-cancel"){
                    $subscriptionCycleEndAt['cancel_at_cycle_end'] = 1;
                }

                $this->subscriptions->cancelSubscription($subscriptionId,$subscriptionCycleEndAt);
            }catch (Exception $e) {
                return new WP_Error('Razorpay Error: ', __($e->getMessage(), 'woocommerce-subscription'));
            }
        }

        function subscription_on_hold($subscription){
            try {
                $this->subscriptions = new RZP_Subscriptions($this->getSetting('key_id'), $this->getSetting('key_secret'));

                $parentOrder = $subscription->get_parent();

                if (empty($parentOrder) === true)
                {
                    $log = array(
                        'Error' => 'Unable to pause the order ' . $parentOrder,
                    );

                    error_log(json_encode($log));

                    return;
                }

                $subscriptionId = '';
                if ($this->isHposEnabled())
                {
                    $order = wc_get_order($parentOrder->get_id());
                    $subscriptionId = $order->get_meta(self::RAZORPAY_SUBSCRIPTION_ID);
                }
                else
                {
                    $subscriptionId = get_post_meta($parentOrder->get_id(), self::RAZORPAY_SUBSCRIPTION_ID)[0];
                }

                $subscriptionPauseAt = ['pause_at' => 'now'];

                $this->subscriptions->pauseSubscription($subscriptionId,$subscriptionPauseAt);
            }catch (Exception $e) {
                return new WP_Error('Razorpay Error: ', __($e->getMessage(), 'woocommerce-subscription'));
            }
        }

        function  subscription_reactivate($subscription){
            try {
                $this->subscriptions = new RZP_Subscriptions($this->getSetting('key_id'), $this->getSetting('key_secret'));

                $parentOrder = $subscription->get_parent();

                if (empty($parentOrder) === true)
                {
                    $log = array(
                        'Error' => 'Unable to reactivate the order ' . $parentOrder,
                    );

                    error_log(json_encode($log));

                    return;
                }

                $subscriptionId = '';
                if ($this->isHposEnabled())
                {
                    $order = wc_get_order($parentOrder->get_id());
                    $subscriptionId = $order->get_meta(self::RAZORPAY_SUBSCRIPTION_ID);
                }
                else
                {
                    $subscriptionId = get_post_meta($parentOrder->get_id(), self::RAZORPAY_SUBSCRIPTION_ID)[0];
                }

                $subscriptionResumeAt = ['resume_at' => 'now'];

                $this->subscriptions->resumeSubscription($subscriptionId,$subscriptionResumeAt);
            }catch (Exception $e) {
                return new WP_Error('Razorpay Error: ', __($e->getMessage(), 'woocommerce-subscription'));
            }
        }

        function isHposEnabled()
        {
            return (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') and
                    OrderUtil::custom_orders_table_usage_is_enabled());
        }
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_razorpay_subscriptions_gateway(array $methods)
    {
        $methods[] = 'WC_Razorpay_Subscription';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_razorpay_subscriptions_gateway');
}

function razorpay_webhook_subscription_init()
{
    $rzpWebhook = new RZP_Subscription_Webhook();

    $rzpWebhook->process();
}

function razorpaySubscriptionPluginActivated()
{
    $rzp = new WC_Razorpay();
    $api = $rzp->getRazorpayApiInstance();

    try
    {
        $features = $api->request->request("GET", "accounts/me/features");

        foreach ($features['assigned_features'] as $feature)
        {
            if ($feature['name'] === 'subscriptions'
                and $feature['entity_type'] === 'merchant')
            {
                update_option('rzp_subscription_webhook_enable_flag', true);
                break;
            }
        }
    }
    catch (Exception $e)
    {
        rzpSubscriptionErrorLog("Unable to add rzp_subscription_webhook_enable_flag: " . $e->getMessage());
    }
}

function razorpaySubscriptionPluginDeactivated()
{
    $subscriptionWebhookFlag =  get_option('rzp_subscription_webhook_enable_flag');

    if ($subscriptionWebhookFlag)
    {
        delete_option('rzp_subscription_webhook_enable_flag');
    }
}
