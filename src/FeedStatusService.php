<?php
/**
 * Service class to handle & return Pinterest Feed Status
 *
 * @package     Automattic\WooCommerce\Pinterest
 * @version     x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\Base;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling methods to return Pinterest Feed Status.
 */
class FeedStatusService {

	/**
	 * Get the feed registration status.
	 *
	 * @return string The feed registration state. Possible values:
	 *                - not_registered: Feed is not yet configured on Pinterest.
	 *                - error_fetching_merchant: Could not get merchant info.
	 *                - error_fetching_feed: Could not get feed info.
	 *                - inactive_feed: The feed is registered but inactive.
	 *                - approved: The feed is registered and approved.
	 *                - pending: Product feed pending approval on Pinterest.
	 *                - appeal_pending: Product feed pending approval on Pinterest.
	 *                - declined: The feed is registered but declined by Pinterest.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_feed_registration_status(): string {
		$merchant_id = Pinterest_For_Woocommerce()::get_data( 'merchant_id' );
		$feed_id     = FeedRegistration::get_locally_stored_registered_feed_id();

		try {
			if ( empty( $merchant_id ) || empty( $feed_id ) ) {
				throw new Exception( 'not_registered' );
			}

			$merchant = Base::get_merchant( $merchant_id );
			if ( 'success' !== $merchant['status'] ) {
				throw new Exception( 'error_fetching_merchant' );
			}

			try {
				$feed = Feeds::get_merchant_feed( $merchant_id, $feed_id );
			} catch ( Exception $e ) {
				throw new Exception( 'error_fetching_feed' );
			}
			if ( ! $feed ) {
				throw new Exception( 'error_fetching_feed' );
			}
			if ( 'ACTIVE' !== $feed->feed_status ) {
				throw new Exception( 'inactive_feed' );
			}

			$status = strtolower( $merchant['data']->product_pin_approval_status );
			if ( ! in_array( $status, array( 'approved', 'pending', 'appeal_pending', 'declined' ), true ) ) {
				throw new Exception( 'not_registered' );
			}
		} catch ( Exception $e ) {
			$status = $e->getMessage();
		}

		return $status;
	}

}
