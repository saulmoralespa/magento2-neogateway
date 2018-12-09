define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $, quote, customer, validator) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Smp_Neogateway/payment/neogateway'
            },

            getCode: function() {
                return 'neogateway';
            },

            isActive: function() {
                return true;
            },
            getData: function () {
                var number = this.creditCardNumber().replace(/\D/g,'');
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'card_number': number,
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_bin': number.substring(0, 6),
                        'cc_last_4': number.substring(number.length-4, number.length),
                        'cvc': this.creditCardVerificationNumber(),
                        'card_holder_name': $("#card_holder_name").val()
                    }
                };

                return data;
            },
            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);