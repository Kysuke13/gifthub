<?php
/**
 * Pinterest for WooCommerce Rich Pins
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper methods that get/set the various feed state properties.
 */
class ProductFeedStatus {

	const STATE_PROPS = array(
		'status'        => 'pending_config',
		'current_index' => false,
		'last_activity' => 0,
		'product_count' => 0,
		'error_message' => '',
	);

	/**
	 * The array that holds the parameters of the feed
	 *
	 * @var array
	 */
	private static $local_feed = array();

	/**
	 * The array that holds the state of the feed, used as cache.
	 *
	 * @var array
	 */
	private static $state = array();

	/**
	 * Returns the Current state of the Feed generation job.
	 * Status can be one of the following:
	 *
	 * - in_progress              Signifies that we are between iterations and generating the feed.
	 * - generated                The feed is generated, no further action is needed, unless the feed is expired.
	 * - scheduled_for_generation The feed is scheduled to be (re)generated. On this status, the next run of ProductSync::handle_feed_generation() will start the generation process.
	 * - pending_config           The feed was reset or was never configured.
	 * - error                    The generation process returned an error.
	 *
	 * @return array
	 */
	public static function get() {

		$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feeds_';

		foreach ( self::STATE_PROPS as $key => $default_value ) {

			if ( ! isset( self::$state[ $key ] ) || null === self::$state[ $key ] ) {
				self::$state[ $key ] = get_transient( $data_prefix . $key );
			}

			if ( false === self::$state[ $key ] ) {
				self::$state[ $key ] = $default_value;
			} elseif ( null === self::$state[ $key ] ) {
				self::$state[ $key ] = false;
			}
		}

		return self::$state;
	}

	/**
	 * Sets the Current state of the Feed generation job.
	 * See the docblock of self::get() for more info.
	 *
	 * @param array $state The array holding the feed state props to be saved.
	 * @return void
	 */
	public static function set( $state ) {

		$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feeds_';

		$state['last_activity'] = time();

		foreach ( $state as $key => $value ) {
			self::$state[ $key ] = $value;
			set_transient( $data_prefix . $key, ( false === $value ? null : $value ) ); // No expiration.
		}

		if ( ! empty( $state['status'] ) ) {
			do_action( 'pinterest_for_woocommerce_feed_' . $state['status'], $state );
		}
	}


	/**
	 * Stores the given dataset on a transient.
	 *
	 * @param array $dataset The product dataset to be saved.
	 *
	 * @return bool True if the value was set, false otherwise.
	 */
	public static function store_dataset( $dataset ) {
		return set_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset', $dataset, WEEK_IN_SECONDS );
	}

	/**
	 * Returns the stored dataset.
	 *
	 * @return mixed Value of transient.
	 */
	public static function retrieve_dataset() {
		return get_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset' );
	}


	/**
	 * Cleanup the stored dataset.
	 *
	 * @return void
	 */
	public static function feed_data_cleanup() {
		delete_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset' );
	}

	/**
	 * Removes all transients for the given feed_id.
	 *
	 * @return void
	 */
	public static function feed_transients_cleanup() {

		$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feeds_';

		foreach ( self::STATE_PROPS as $key => $default_value ) {
			delete_transient( $data_prefix . $key );
		}

		delete_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset' );
	}
}
