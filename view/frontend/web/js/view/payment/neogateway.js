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
                type: 'neogateway',
                component: 'Smp_Neogateway/js/view/payment/method-renderer/neogateway'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);