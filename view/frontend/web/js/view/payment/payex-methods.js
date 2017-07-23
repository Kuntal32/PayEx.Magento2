define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payex_cc',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-cc-method'
            },
            {
                type: 'payex_bankdebit',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-bankdebit-method'
            },
            {
                type: 'payex_swish',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-swish-method'
            },
            {
                type: 'payex_financing',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-financing-method'
            },
            {
                type: 'payex_partpayment',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-partpayment-method'
            },
            {
                type: 'payex_masterpass',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-masterpass-method'
            },
            {
                type: 'payex_gc',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-gc-method'
            },
            {
                type: 'payex_evc',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-evc-method'
            },
            {
                type: 'payex_mobilepay',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-mobilepay-method'
            },
            {
                type: 'payex_checkout',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-checkout-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
