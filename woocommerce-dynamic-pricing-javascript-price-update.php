<?php
/**
 * Plugin Name:       WooCommerce Dynamic Pricing JavaScript Price Update
 * Plugin URI:        https://github.com/lucasstark/woocommerce-dynamic-pricing-javascript-price-update
 * Description:       Attempts to update the displayed price on the single product page as the user changes quantities.
 * Version:           1.0.0
 * Author:            Lucas Stark
 * Author URI:        https://elementstark.com
 * Requires at least: 4.6
 * Tested up to:      5.2.3
 * WC requires at least: 3.0.0
 * WC tested up to: 3.7.1
 *
 * Text Domain: woocommerce-dynamic-pricing-javascript-price-update
 * Domain Path: /languages/
 *
 * @package WC_Dynamic_Pricing_Table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Returns the main instance of WC_Dynamic_Pricing_JavaScript_Price_Update to prevent the need to use globals.
 *
 * @return  object WC_Dynamic_Pricing_JavaScript_Price_Update
 * @since   1.0.0
 */
function WC_Dynamic_Pricing_JavaScript_Price_Update() {
	return WC_Dynamic_Pricing_JavaScript_Price_Update::instance();
}

WC_Dynamic_Pricing_JavaScript_Price_Update();

final class WC_Dynamic_Pricing_JavaScript_Price_Update {

	private static $instance;

	public static function instance() {
		if ( self::$instance == null ) {
			self::$instance = new WC_Dynamic_Pricing_JavaScript_Price_Update();
		}
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'load_scripts' ] );
		add_action( 'wp_footer', [ $this, 'generate_pricing_json' ] );
	}

	public function load_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2' );
		wp_enqueue_script( 'wc-dynamic-pricing-js-price-update', $this->plugin_url() . '/assets/js/scripts.js', [ 'jquery', 'accounting' ], '1.0', true );

		// Accounting
		wp_localize_script( 'accounting', 'accounting_params', array(
			'mon_decimal_point' => wc_get_price_decimal_separator()
		) );

		$wc_params = array(
			'currency_format_num_decimals' => wc_get_price_decimals(),
			'currency_format_symbol'       => get_woocommerce_currency_symbol(),
			'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
			'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
			'currency_format'              => esc_attr( str_replace( array( '%1$s', '%2$s' ), array(
				'%s',
				'%v'
			), get_woocommerce_price_format() ) ), // For accounting JS
		);

		wp_localize_script( 'wc-dynamic-pricing-js-price-update', 'wc_dynamic_pricing_params', $wc_params );

	}

	public function generate_pricing_json() {
		if ( ! class_exists( 'WC_Dynamic_Pricing_Table' ) ) {
			return;
		}

		if ( ! is_product() ) {
			return;
		}

		$instance = WC_Dynamic_Pricing_Table::instance();
		$product  = wc_get_product();

		$array_rule_sets = $instance->get_pricing_array_rule_sets();

		$json_prices     = array(
			'base_price'      => $product->get_price(),
			'variation_rules' => array(),
			'product_rules'   => array()
		);

		if ( $array_rule_sets && is_array( $array_rule_sets ) ) {
			if ( apply_filters( 'woocommerce_dynamic_pricing_table_filter_rules', true ) ) {
				$valid_rules = apply_filters( 'woocommerce_dynamic_pricing_table_get_filtered_rules', $instance->filter_rulesets( $array_rule_sets ), $array_rule_sets );
			} else {
				$valid_rules = $array_rule_sets;
			}

			foreach ( $valid_rules as $pricing_rule_set ) {
				if ( $pricing_rule_set['mode'] == 'continuous' ) :
					$prices = array();
					foreach ( $pricing_rule_set['rules'] as $key => $value ) {

						// Checks if a product discount group max quantity field is less than 1.
						$max_quantity = 0;
						if ( $pricing_rule_set['rules'][ $key ]['to'] < 1 ) {
							$max_quantity = 99999999;
						} else {
							$max_quantity = $pricing_rule_set['rules'][ $key ]['to'];
						}

						$min_quantity = $pricing_rule_set['rules'][ $key ]['from'];
						switch ( $pricing_rule_set['rules'][ $key ]['type'] ) {
							case 'price_discount':
								$amount = $pricing_rule_set['rules'][ $key ]['amount'];
								break;

							case 'percentage_discount':
								$amount = $pricing_rule_set['rules'][ $key ]['amount'];
								break;
							case 'fixed_price':
								$amount = apply_filters( 'wc_dynamic_pricing_table_get_fixed_price', $pricing_rule_set['rules'][ $key ]['amount'] );
								break;
							default:
								$amount = 0;
								break;
						}

						$prices[] = [
							'from'   => $min_quantity,
							'to'     => $max_quantity,
							'type'   => $pricing_rule_set['rules'][ $key ]['type'],
							'amount' => $amount
						];

					}

					if ( isset( $pricing_rule_set['variation_rules'] ) && ! empty( $pricing_rule_set['variation_rules'] ) ) {
						if ( isset( $pricing_rule_set['variation_rules']['args']['variations'] ) && ! empty( $pricing_rule_set['variation_rules']['args']['variations'] ) ) {
							foreach ( $pricing_rule_set['variation_rules']['args']['variations'] as $variation_id ) {
								$json_prices['variation_rules'][ $variation_id ] = $prices;
							}
						}
					} else {
						$json_prices['product_rules'] = $prices;
					}


				endif;
			}

			echo '<script type="text/javascript">';
			echo 'window._dynamic_pricing = ' . json_encode( $json_prices );
			echo '</script>';

		}
	}


	/** Helper functions ***************************************************** */

	/**
	 * Get the plugin url.
	 *
	 * @access public
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

}
