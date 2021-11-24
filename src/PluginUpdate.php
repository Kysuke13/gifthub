<?php
/**
 * Plugin update procedures
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for performing plugin update procedures.
 */
class PluginUpdate {

	/**
	 * Perform plugin update procedures.
	 *
	 * @param string $old_version Plugin version from which we start the update.
	 * @return void
	 */
	public static function update( $old_version ) {

		if ( version_compare( '2.0.0', $old_version, '>' ) ) {
			self::update_to_2_0_0();
		}
	}

	/**
	 * Update plugin to the version 2.0.0
	 *
	 * @return void
	 */
	public static function update_to_2_0_0() {
		as_unschedule_all_actions( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-handle-sync', array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_unschedule_all_actions( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-feed-generation', array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		$feed_id = Pinterest_For_Woocommerce()::get_data( 'local_feed_id' );
	}
}
