(function($) {

    $('document').ready(function() {

        let variation_id;
        $('form.cart').on('found_variation', function(e, variation) {
            variation_id = variation.variation_id;
        });

        $( 'input.qty:not(.product-quantity input.qty)' ).on('change', function() {
            const val = parseInt($(this).val());
            const pricing_rules = window._dynamic_pricing.product_rules;

            let the_rule = pricing_rules.find(function(item) {
                const from = parseInt(item.from);
                const to = (item.to === '*' || item.to === '') ? '*' : parseInt(item.to);

                if (from <= val && to === '*') {
                    return item;
                }

                if (from <= val && item.to >= val) {
                    return item;
                }

                return false;

            });

            if (!the_rule) {

                const variation_rules = window._dynamic_pricing.variation_rules;

                if (variation_rules && variation_rules[variation_id]) {
                     the_rule = variation_rules[variation_id].find(function(item) {
                        const from = parseInt(item.from);
                        const to = (item.to === '*' || item.to === '') ? '*' : parseInt(item.to);

                        if (from <= val && to === '*') {
                            return item;
                        }

                        if (from <= val && item.to >= val) {
                            return item;
                        }

                        return false;

                    });
                }
                if (!the_rule) {
                    the_rule = {
                        'type': 'fixed_price',
                        'amount': window._dynamic_pricing.base_price
                    }
                }
            }

            let the_func;
            the_func = updateSimplePrice;

            if (the_rule.type === 'fixed_price') {
                the_func(the_rule.amount);
            }
        });
    });

    function updateSimplePrice(price) {

        const formattedPrice = accounting.formatMoney(price, {
                symbol: wc_dynamic_pricing_params.currency_format_symbol,
                decimal: wc_dynamic_pricing_params.currency_format_decimal_sep,
                thousand: wc_dynamic_pricing_params.currency_format_thousand_sep,
                precision: wc_dynamic_pricing_params.currency_format_num_decimals,
                format: wc_dynamic_pricing_params.currency_format
            }
        );

        $('p.price > .woocommerce-Price-amount').text(formattedPrice);
        $('.woocommerce-variation-price .woocommerce-Price-amount').text(formattedPrice);

        console.log(formattedPrice);
    }

})(jQuery);
