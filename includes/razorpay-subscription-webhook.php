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
     * @throws WC_Data_Exception
     */
    protected function paymentAuthorized(array $data)
    {
        //
        // Order entity should be sent as part of the webhook payload
        //

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
     * @return string|void
     * @throws WC_Data_Exception
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

    /**
     * Method for subscription cancelled webhook
     *
     * @param array $data
     * @return string|void
     */
    protected function subscriptionCancelled(array $data)
    {
        //
        // Order entity should be sent as part of the webhook payload
        //

        $subscriptionId = $data['payload']['subscription']['entity']['id'];

        // Process subscription cancellation this way
        if (empty($subscriptionId) === false)
        {
            return $this->cancelSubscription($subscriptionId);
        }

    }

    /**
     * Helper method to get subscription ID
     *
     * @param $invoiceId
     * @param $event
     * @return mixed
     */
    protected function getSubscriptionId($invoiceId, $event)
    {
        try
        {
            $invoice = $this->api->invoice->fetch($invoiceId);
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

        return $invoice->subscription_id;
    }

    /**
     * Helper method used to handle all subscription processing
     *
     * @param $paymentId
     * @param $subscriptionId
     * @param bool $success
     * @return string
     * @throws WC_Data_Exception
     */
    protected function processSubscription($paymentId, $subscriptionId, $success = true)
    {
        $subscription = null;

        try
        {
            $subscription = $this->api->subscription->fetch($subscriptionId);
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

            exit;
        }

        $this->processSubscriptionSuccess($orderId, $subscription, $paymentId);

        exit;
    }

    /**
     * In the case of successful payment, we mark the subscription successful
     *
     * @param $orderId
     * @param $subscription
     * @param $paymentId
     * @return bool|void
     */
    protected function processSubscriptionSuccess($orderId, $subscription, $paymentId)
    {
        //
        // This method is used to process the subscription's recurring payment
        //
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
            return;
        }

        $paymentCount = $wcSubscription->get_completed_payment_count();

        //For single period subscription we are not setting the upfront amount
        if (($subscription->total_count == 1) and ($paymentCount == 1) and ($subscription->paid_count == 0))
        {
            return true;
        }

        //If webhook trigger on first payment of subscription, then only mark payment completed
        if(($paymentCount == 1) and ($subscription->paid_count == 1) and ($subscription->auth_attempts == 0)) {

            if ($wcSubscription->needs_payment() === true)
            {
                $wcSubscription->payment_complete($paymentId);

                error_log("Subscription Charged successfully");
            }

            return;
        }

        //if this is authentication payment count
        if ($subscription->paid_count == 0)
        {
            return;
        }

        else
        {
            //
            // If subscription has been paid for on razorpay's end, we need to mark the
            // subscription payment to be successful on woocommerce's end
            //
            WC_Subscriptions_Manager::prepare_renewal($wcSubscriptionId);

            if ($wcSubscription->needs_payment() === true)
            {
                $wcSubscription->payment_complete($paymentId);

                $this->update_next_payment_date($subscription, $wcSubscription);

                error_log("Subscription Charged successfully");
            }

        }
    }

    /**
     * In the case of payment failure, we mark the subscription as failed
     *
     * @param $orderId
     * @param $subscription
     * @param $paymentId
     * @throws WC_Data_Exception
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

            $renewal_order->set_transaction_id( $paymentId );

            $renewal_order->save();
        }
    }

    /**
     * Get renewal order for specific transaction.
     *
     * @param $subscription
     * @param $transaction_id
     * @return |null
     */
    protected function get_renewal_order_by_transaction_id($subscription, $transaction_id ) {

        $orders = $subscription->get_related_orders( 'all', 'renewal' );
        $renewal_order = null;

        foreach ($orders as $order) {
            if ( $order->get_transaction_id() == $transaction_id ) {
                $renewal_order = $order;
                break;
            }
        }

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

    /**
     * Make request to cancelled subscription
     *
     * @param $subscriptionId
     * @return string
     */
    protected function cancelSubscription($subscriptionId)
    {
        $subscription = null;

        try
        {
            $subscription = $this->api->subscription->fetch($subscriptionId);
        }
        catch (Exception $e)
        {
            $message = $e->getMessage();

            return "RAZORPAY ERROR: Subscription fetch failed with the message $message";
        }

        $orderId = $subscription->notes[WC_Razorpay::WC_ORDER_ID];

        $this->cancelSubscriptionSuccess($orderId);

        exit;
    }

    /**
     * Change order status from active to cancelled when subscription cancelled event called.
     *
     * @param $orderId
     */
    protected function cancelSubscriptionSuccess($orderId)
    {
        $wcSubscription = $this->get_woocoommerce_subscriptions_for_order($orderId);

        if ($wcSubscription === null)
        {
            return;
        }

        $wcSubscription = array_values($wcSubscription)[0];

        if ( $wcSubscription->has_status( 'active' ))
        {
            $wcSubscription->update_status( 'cancelled' );

            error_log("Subscription cancelled successfully");
        }

    }

    /**
     * Update next payment date for subscription
     *
     * @param $subscription
     * @param $wcSubscription
     */
    protected static function update_next_payment_date($subscription, $wcSubscription)
    {
        if($subscription->paid_count === $subscription->total_count) {

            $new_payment_date = 0;

        } else {

            $new_payment_timestamp = $subscription->current_end;

            $new_payment_timestamp = ( is_numeric( $new_payment_timestamp ) ) ? $new_payment_timestamp : wcs_date_to_time( $new_payment_timestamp );

            $new_payment_date = get_date_from_gmt( date( 'Y-m-d H:i:s', $new_payment_timestamp ), 'Y-m-d H:i:s' );

        }

        try {
            $wcSubscription->update_dates( array('next_payment_date' => $new_payment_date) );

            error_log("Next payment date updated successfully");

        } catch ( Exception $e ) {
            error_log('invalid-date', $e->getMessage());
        }
    }
}
