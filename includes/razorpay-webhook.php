<?php

require_once __DIR__.'/../razorpay-payments.php';
require_once __DIR__.'/../razorpay-sdk/Razorpay.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors;

class RZP_Subscription_Webhook
{
    const RAZORPAY_SUBSCRIPTION_ID = 'razorpay_subscription_id';

    /**
     * Handling the payment authorized webhook
     *
     * This only gets called if
     *
     * @param $data
     */
    protected function paymentAuthorized(array $data)
    {
        //
        // Order entity should be sent as part of the webhook payload
        //
        $orderId = $data['payload']['payment']['entity']['notes']['woocommerce_order_id'];

        $paymentId = $data['payload']['payment']['entity']['id'];

        if (isset($data['payload']['payment']['entity']['subscription_id']) === true)
        {
            $this->processSubscription($orderId, $paymentId);
        }
    }

    /**
     * Currently we handle only subscription failures using this webhook
     *
     * @param $data
     */
    protected function paymentFailed(array $data)
    {
        //
        // Order entity should be sent as part of the webhook payload
        //
        $orderId = $data['payload']['payment']['entity']['notes']['woocommerce_order_id'];

        $paymentId = $data['payload']['payment']['entity']['id'];

        if (isset($data['payload']['payment']['subscription_id']) === true)
        {
            $this->processSubscription($orderId, $paymentId, false);
        }
    }

    /**
     * Handling the subscription charged webhook
     *
     * @param $data
     */
    protected function subscriptionCharged(array $data)
    {
        //
        // Order entity should be sent as part of the webhook payload
        //
        $orderId = $data['payload']['subscription']['entity']['notes']['woocommerce_order_id'];

        $this->processSubscription($orderId);

        exit;
    }

    /**
     * Helper method used to handle all subscription processing
     *
     * @param $orderId
     * @param $paymentId
     * @param $success
     */
    protected function processSubscription($orderId, $paymentId, $success = true)
    {
        //
        // If success is false, automatically process subscription failure
        //
        if ($success === false)
        {
            return $this->processSubscriptionFailed($orderId);
        }

        $subscriptionId = get_post_meta($orderId, self::RAZORPAY_SUBSCRIPTION_ID)[0];

        $api = $this->razorpay->getRazorpayApiInstance();

        try
        {
            $subscription = $api->subscription->fetch($subscriptionId);
        }
        catch (Exception $e)
        {
            $message = $e->getMessage();

            return 'RAZORPAY ERROR: Subscription fetch failed with the message \'' . $message . '\'';
        }

        $this->processSubscriptionSuccess($orderId, $subscription, $paymentId);

        exit;
    }

    /**
     * In the case of successful payment, we mark the subscription successful
     *
     * @param $wcSubscription
     * @param $subscription
     */
    protected function processSubscriptionSuccess($orderId, $subscription, $paymentId)
    {
        //
        // This method is used to process the subscription's recurring payment
        //
        $wcSubscription = wcs_get_subscriptions_for_order($orderId);

        $wcSubscriptionId = array_keys($wcSubscription)[0];

        //
        // We will only process one subscription per order
        //
        $wcSubscription = array_values($wcSubscription)[0];

        if (count($wcSubscription) > 1)
        {
            $log = array(
                'Error' => 'There are more than one subscription products in this order'
            );

            write_log($log);

            exit;
        }

        $paymentCount = $wcSubscription->get_completed_payment_count();

        //
        // The subscription is completely paid for
        //
        if ($paymentCount === $subscription->total_count)
        {
            return;
        }
        else if ($paymentCount + 1 === $subscription->paid_count)
        {
            //
            // If subscription has been paid for on razorpay's end, we need to mark the
            // subscription payment to be successful on woocommerce's end
            //
            WC_Subscriptions_Manager::prepare_renewal($wcSubscriptionId);

            $wcSubscription->payment_complete($paymentId);
        }
    }

    /**
     * In the case of payment failure, we mark the subscription as failed
     *
     * @param $orderId
     */
    protected function processSubscriptionFailed($orderId)
    {
        WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($orderId);
    }
}
