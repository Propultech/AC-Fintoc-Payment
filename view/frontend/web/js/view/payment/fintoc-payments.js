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
                type: 'fintoc_payment',
                component: 'Fintoc_Payment/js/view/payment/method-renderer/fintoc-method'
            }
        );
        return Component.extend({});
    }
);
