const subscription_settings = window.wc.wcSettings.getSetting('razorpay_subscriptions_data', {});

const subscription_label = window.wp.htmlEntities.decodeEntities(subscription_settings.title) || window.wp.i18n.__('Razorpay Subscription for woocommerce', 'razorpay_subscriptions');

const subscription_Content = () => {
    return window.wp.htmlEntities.decodeEntities(subscription_settings.description || '');
};

const subscription_Block_Gateway = {
    name: 'razorpay_subscriptions',
    label: subscription_label,
    content: Object(window.wp.element.createElement)(subscription_Content, null),
    edit: Object(window.wp.element.createElement)(subscription_Content, null),
    canMakePayment: () => true,
    ariaLabel: subscription_label,
    supports: {
        features: subscription_settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(subscription_Block_Gateway);
