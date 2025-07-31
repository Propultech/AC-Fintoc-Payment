define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url'
    ],
    function (
        $,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        customer,
        checkoutData,
        additionalValidators,
        url
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Fintoc_Payment/payment/fintoc-form'
            },

            /**
             * Initialize the payment method
             */
            initialize: function () {
                this._super();
                return this;
            },

            /**
             * Get payment method code
             * @returns {String}
             */
            getCode: function () {
                return 'fintoc_payment';
            },

            /**
             * Get payment method title
             * @returns {String}
             */
            getTitle: function () {
                return window.checkoutConfig.payment.fintoc_payment.title;
            },

            /**
             * Check if payment is active
             * @returns {Boolean}
             */
            isActive: function () {
                return true;
            },

            /**
             * Place order handler
             */
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .done(
                            function () {
                                // Redirect to success page
                                window.location.replace(url.build('checkout/onepage/success/'));
                            }
                        ).always(
                            function () {
                                this.isPlaceOrderActionAllowed(true);
                            }.bind(this)
                        );

                    return true;
                }

                return false;
            },

            /**
             * Get place order deferred object
             * @returns {*}
             */
            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrderAction(this.getData(), this.messageContainer)
                );
            },

            /**
             * Get payment method data
             * @returns {Object}
             */
            getData: function () {
                return {
                    'method': this.getCode(),
                    'additional_data': {}
                };
            }
        });
    }
);
