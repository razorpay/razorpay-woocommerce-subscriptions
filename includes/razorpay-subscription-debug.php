<?php

/**
 * @param string $level
 * 'error': Error conditions.
 * 'info': Informational messages.
 * @param string $message Message to log.
 */
define('RAZORPAY_SUBSCRIPTION_LOG_NAME', 'razorpay-subscription-log');

function rzpSubscriptionLog($level, $message)
{
    $logger = wc_get_logger();
    $logger->log($level, $message, array('source' => RAZORPAY_SUBSCRIPTION_LOG_NAME));
}

/**
 * @param string $message Message to log.
 */
function rzpSubscriptionErrorLog($message)
{
    rzpSubscriptionLog('error', $message);
}

/**
 * @param string $message Message to log.
 */
function rzpSubscriptionInfoLog($message)
{
    rzpSubscriptionLog('info', $message);
}
