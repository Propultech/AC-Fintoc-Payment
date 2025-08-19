define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url'
    ],
    function (
        $,
        Component,
        placeOrderAction,
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
             * Place order handler
             */
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }

                var self = this;

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .done(
                            function (response) {
                                console.log(response);
                                // Get the order ID from the response
                                var orderId = response;

                                // Create Fintoc transaction and redirect
                                $.ajax({
                                    url: url.build('fintoc/checkout/create'),
                                    type: 'POST',
                                    dataType: 'json',
                                    success: function (response) {
                                        console.log(response);
                                        if (response.success && response.redirect_url) {

                                            // Redirect to Fintoc checkout page
                                            window.location.href = response.redirect_url;
                                        } else {
                                            // Handle error
                                            self.isPlaceOrderActionAllowed(true);
                                            alert(response.error || 'An error occurred while processing your payment. Please try again.');
                                        }
                                    },
                                    error: function () {
                                        self.isPlaceOrderActionAllowed(true);
                                        alert('An error occurred while processing your payment. Please try again.');
                                    }
                                });
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
