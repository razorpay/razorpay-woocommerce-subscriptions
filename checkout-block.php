<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Razorpay_Subscription_Blocks extends AbstractPaymentMethodType 
{
    protected $name = 'razorpay_subscriptions';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_razorpay_subscriptions_settings', []);
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'razorpay_subscriptions-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout_block.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        if (function_exists('wp_set_script_translations')) 
        {
            wp_set_script_translations('razorpay_subscriptions-blocks-integration');
        }

        return ['razorpay_subscriptions-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => 'Pay by Razorpay Subscription',
            'description' => $this->settings['description'],
        ]; 
    }
}
