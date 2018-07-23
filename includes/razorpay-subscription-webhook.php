<?php

use Razorpay\Api\Errors;

class RZP_Subscription_Webhook extends RZP_Webhook
{
    /**
     * Process a Razorpay Subscription Webhook. We exit in the following cases:
     * - Successful processed
     * - Exception while fetching the invoice or subscription entity
     *
     * It passes on the webhook in the following cases:
     * - invoice_id is not set in the json
     * - Not a subscription webhook
     * - Invalid JSON
     * - Signature mismatch
     * - Secret isn't setup
     * - Event not recognized
     */

    /**
     * @var WC_Razorpay
     */
    protected $razorpay;

    /**
     * Processes a payment authorized webhook
     *
     * @param array $data
     * @return string|void
     */
    protected function paymentAuthorized(array $data)
    {
        //
        // Order entity should be sent as part of the webhook payload
        //

        error_log("Hook Starts");

        $paymentId = $data['payload']['payment']['entity']['id'];

        if (isset($data['payload']['payment']['entity']['invoice_id']) === true)
        {
            $invoiceId = $data['payload']['payment']['entity']['invoice_id'];

            $subscriptionId = $this->getSubscriptionId($invoiceId, $data['event']);

            // Process subscription this way
            if (empty($subscriptionId) === false)
            {
                return $this->processSubscription($paymentId, $subscriptionId);
            }
        }
    }

    /**
     * Currently we handle only subscription failures using this webhook
     *
     * @param array $data
     * @return string|null
     */
    protected function paymentFailed(array $data)
    {
        $paymentId = $data['payload']['payment']['entity']['id'];

        if (isset($data['payload']['payment']['entity']['invoice_id']) === true)
        {
            $invoiceId = $data['payload']['payment']['entity']['invoice_id'];

            $subscriptionId = $this->getSubscriptionId($invoiceId, $data['event']);

            // Process subscription this way
            if (empty($subscriptionId) === false)
            {
                return $this->processSubscription($paymentId, $subscriptionId, false);
            }
        }
    }

    protected function getSubscriptionId($invoiceId, $event)
    {
        $startTime = time();
        $api = $this->razorpay->getRazorpayApiInstance();

        try
        {
            $invoice = $api->invoice->fetch($invoiceId);
        }
        catch (Exception $e)
        {
            $log = array(
                'message' => $e->getMessage(),
                'data'    => $invoiceId,
                'event'   => $event
            );

            error_log(json_encode($log));

            exit;
        }

        $endTime = time();

        error_log('it took'. ($endTime - $startTime) . 'ms to execute'.  __FUNCTION__);
        return $invoice->subscription_id;
    }

    /**
     * Helper method used to handle all subscription processing
     *
     * @param string $paymentId
     * @param $subscriptionId
     * @param bool $success
     * @return string|void
     */
    protected function processSubscription($paymentId, $subscriptionId, $success = true)
    {

        error_log('Starting function'.  __FUNCTION__);

       $startTime = time();

        $api = $this->razorpay->getRazorpayApiInstance();

        $subscription = null;

        try
        {
            error_log('Fetching Razorpay Subscription');
            $timeBeforeApiFetch = time();

            $subscription = $api->subscription->fetch($subscriptionId);

            $timeAfterApiFetch = time();

            $timeDiff = $timeAfterApiFetch - $timeBeforeApiFetch;

            error_log('it took  '. $timeDiff . ' ms to fetch Razorpay subscription');

        }
        catch (Exception $e)
        {
            $message = $e->getMessage();

            return "RAZORPAY ERROR: Subscription fetch failed with the message $message";
        }


        $orderId = $subscription->notes[WC_Razorpay::WC_ORDER_ID];

        //
        // If success is false, automatically process subscription failure
        //
        if ($success === false)
        {
            $this->processSubscriptionFailed($orderId, $subscription, $paymentId);

           error_log("Subscription Failed");

            exit;
        }

        $this->processSubscriptionSuccess($orderId, $subscription, $paymentId);

        $endTime  = time();

        error_log('It took ' . ($endTime - $startTime). ' ms to process. '.  __FUNCTION__ );

        error_log('Completed Process Subscription');

        exit;
    }

    /**
     * In the case of successful payment, we mark the subscription successful
     *
     * @param $orderId
     * @param $subscription
     * @param $paymentId
     */
    protected function processSubscriptionSuccess($orderId, $subscription, $paymentId)
    {
        //
        // This method is used to process the subscription's recurring payment
        //

        error_log('Starting with  ' . __FUNCTION__ . ' Time  is now '.  time());

        $wcSubscription = $this->get_woocoommerce_subscriptions_for_order($orderId);

        if ($wcSubscription === null)
        {
            return;
        }

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

            error_log(json_encode($log));

            return;
        }

        $renewal_order = $this->get_renewal_order_by_transaction_id( $wcSubscription, $paymentId );

        if (empty($renewal_order) === false)
        {
            error_log('Renewal Order Exists');

            return;
        }

        $timeBefFetchingCompletedOrder = time();

        $paymentCount = $wcSubscription->get_completed_payment_count();

        $ttimeAfterCompletingOrder = time();

        error_log('it takes '.  ($timeBefFetchingCompletedOrder - $timeBefFetchingCompletedOrder). 'to get completed Payment Count');

        //For single period subscription we are not setting the upfront amount
        if (($subscription->total_count == 1) and ($paymentCount == 1) and ($subscription->paid_count == 0))
        {
            error_log('The Code executed till ' . __LINE__ . ' Time taken is '.  time());

            return true;
        }

        // The subscription is completely paid for
        if ($paymentCount === $subscription->total_count + 1)
        {
            error_log('The Code executed till ' . __LINE__ . ' Time taken is '.  time());


            return;
        }

        //if this is authentication payment count
        if ($subscription->paid_count == 0)
        {
            error_log('The Code executed till ' . __LINE__ . ' Time taken is '.  time());

            return;
        }

        else
        {
            error_log('The Code executed till ' . __LINE__ . ' Time taken is '.  time());

            $timeBeforeRenewalOrder = time();
            //
            // If subscription has been paid for on razorpay's end, we need to mark the
            // subscription payment to be successful on woocommerce's end
            //
            WC_Subscriptions_Manager::prepare_renewal($wcSubscriptionId);

            $timeAfterRenewalOrder = time();

            $diff = $timeAfterRenewalOrder - $timeBeforeRenewalOrder;

            error_log('It takes' . $diff . 'ms to process renewal order');

            error_log('Renewal Order Created');

            if ($wcSubscription->needs_payment() === true)
            {
                error_log("need Payment");

                $timeBeforePaymentComplete = time();

                $wcSubscription->payment_complete($paymentId);

                error_log("Payment Complete");

                $timeAfterPaymentComplte = time();

                error_log('takes ' . ($timeAfterPaymentComplte - $timeBeforePaymentComplete) .'to update order status to payment complete');

            }

        }

        error_log('Completed'.  __FUNCTION__);

    }

    /**
     * In the case of payment failure, we mark the subscription as failed
     *
     * @param $orderId
     */
    protected function processSubscriptionFailed($orderId, $subscription, $paymentId)
    {
        $wcSubscription = $this->get_woocoommerce_subscriptions_for_order($orderId);

        if ($wcSubscription === null)
        {
            return;
        }

        $wcSubscription = array_values($wcSubscription)[0];


        $is_first_payment = ( $wcSubscription->get_completed_payment_count() < 1 );

        if (!$is_first_payment)
        {
            if ( $wcSubscription->has_status( 'active' ) )
            {
                $wcSubscription->update_status( 'on-hold' );
            }

            $renewal_order = $this->get_renewal_order_by_transaction_id( $wcSubscription, $paymentId );

            if ( is_null( $renewal_order ) ) {
                $renewal_order = wcs_create_renewal_order( $wcSubscription );
            }

            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $renewal_order->set_payment_method( $available_gateways['razorpay'] );
        }
    }

    protected function get_renewal_order_by_transaction_id($subscription, $transaction_id ) {

        $currentTime = time();
        $orders = $subscription->get_related_orders( 'all', 'renewal' );
        $renewal_order = null;

        error_log('The Code executed till ' . __LINE__ . ' Time taken is '.  time());

        foreach ($orders as $order) {
            if ( $order->get_transaction_id() == $transaction_id ) {
                $renewal_order = $order;
                break;
            }
        }

        error_log('The Code executed till ' . __LINE__ . ' Time taken is '.  time());

        error_log('It took '.  ($currentTime - $currentTime) . 'ms to execute  ' . __FUNCTION__ );

        return $renewal_order;
    }

    protected function get_woocoommerce_subscriptions_for_order($orderId)
    {
        $wcSubscription = wcs_get_subscriptions_for_order($orderId);

        if (empty($wcSubscription) === true)
        {
             $log = array(
                'Error' =>  'woocommerce Subscription Not Found  woocommerce Order Id -'. $orderId,
             );

             error_log(json_encode($log));

            return null;
        }

        return $wcSubscription;
    }
}
