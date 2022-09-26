<?php
/**
 * Pinterest for WooCommerce Ads Credits.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.10
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\Base;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling ad credits.
 */
class AdCredits {

	/**
	 * Hardcoded offer code as an initial approach.
	 * TODO: Add the rest of offer codes or perhaps moving the logic to a separate class, where we can get codes by country, etc.
	 */
	const OFFER_CODE = 'TESTING_WOO_FUTURE';


	/**
	 * Redeem Ad Credits.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public static function redeem_credits() {

		if ( ! Pinterest_For_Woocommerce()::get_data( 'is_advertiser_connected' ) ) {
			// Advertiser not connected, we can't check if credits were redeemed.
			return false;
		}

		$advertiser_id = Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' );

		if ( false === $advertiser_id ) {
			// No advertiser id stored. But we are connected. This is an abnormal state that should not happen.
			Logger::log( __( 'Advertiser connected but the connection id is missing.', 'pinterest-for-woocommerce' ) );
			return false;
		}

		$offer_code = self::OFFER_CODE;

		try {
			$result = Base::validate_ads_offer_code( $advertiser_id, $offer_code );
			if ( 'success' !== $result['status'] ) {
				return false;
			}

			$redeem_credits_data = (array) $result['data'];

			if ( ! isset( $redeem_credits_data[ $offer_code ] ) ) {
				// No data for the requested offer code.
				Logger::log( __( 'There is no available data for the requested offer code.', 'pinterest-for-woocommerce' ) );
				return false;
			}

			$offer_code_credits_data = $redeem_credits_data[ $offer_code ];

			if ( ! $offer_code_credits_data->success && 2322 !== $offer_code_credits_data->error_code ) {
				Logger::log( $offer_code_credits_data->failure_reason, 'error' );
				return false;
			}

			return true;

		} catch ( Throwable $th ) {

			Logger::log( $th->getMessage(), 'error' );
			return false;

		}
	}

}
