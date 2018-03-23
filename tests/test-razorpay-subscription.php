<?php

include_once  __DIR__ . '/..' .'/razorpay-subscriptions.php';


class RazorpaySubscriptionTest extends WP_UnitTestCase
{
    public function setUp()
    {
        $this->rzpObject = new WC_Razorpay_Subscription();
    }

    public function test_paymentmethodsSupported()
    {
        $suportedFeature = [
            'subscriptions',
            'subscription_reactivation',
            'subscription_suspension',
            'subscription_cancellation'
        ];

        foreach ($suportedFeature as $feature)
        {
            $this->assertTrue($this->rzpObject->supports($feature));
        }
    }

    public function test_razorpaySubscriptionAvailable()
    {
        $installed_payment_methods = WC()->payment_gateways->payment_gateways();

        $this->assertContains('razorpay_subscriptions', array_keys($installed_payment_methods));
    }

    public function test_Settings()
    {
        $this->assertEquals('MasterCard/Visa Credit Card', $this->rzpObject->settings['title']);
        $this->assertEquals('Setup automatic recurring billing on a MasterCard or Visa Credit Card',
                             $this->rzpObject->settings['description']);
    }

    public function test_nonSubscriptionGateway()
    {
        $installed_payment_methods = WC()->payment_gateways->payment_gateways();

        $rzpSubscriptionGateway = $installed_payment_methods['razorpay_subscriptions'];

        $availableGateways = $this->rzpObject->disable_non_subscription($installed_payment_methods);

        $this->assertNotContains($rzpSubscriptionGateway, $availableGateways);
    }


}
