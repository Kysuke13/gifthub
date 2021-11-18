<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
/**
 * Class responsible for managing local feed configurations.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     x.x.x
 */
namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling feed files generation.
 */
class LocalFeedConfigs {

	/**
	 * @var array of local feed configurations;
	 */
	private $feeds_configurations = null;

	/**
	 * Class responsible for local feed configurations and handling.
	 *
	 * @since x.x.x
	 * @param array $locations Array of location to generate the feed files for.
	 */
	public function __construct( $locations ) {
		$this->initialize_local_feeds_config( $locations );
	}

	/**
	 * Prepare feed configurations.
	 *
	 * @since x.x.x
	 * @param array $locations Array of location to generate the feed files for.
	 */
	private function initialize_local_feeds_config( $locations ) {

		if ( $this->feeds_configurations ) {
			return;
		}

		$feed_ids = Pinterest_For_Woocommerce()::get_data( 'local_feed_ids' );
		if ( ! $feed_ids ) {
			$feed_ids = array();
		}

		foreach ( $locations as $location ) {
			if ( array_key_exists( $location, $feed_ids ) ) {
				continue;
			}
			$feed_ids[ $location ] = wp_generate_password( 6, false, false );
		}

		Pinterest_For_Woocommerce()::save_data( 'local_feed_ids', $feed_ids );

		$file_name_base = trailingslashit( wp_get_upload_dir()['basedir'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-';
		array_walk(
			$feed_ids,
			function ( &$id, $location ) use ( $file_name_base ) {
				$base = $file_name_base . $id . '-' . $location;
				$id   = array(
					'feed_id'   => $id,
					'feed_file' => $base . '.xml',
					'tmp_file'  => $base . '-tmp.xml',
					'feed_url'  => $base . '.xml',
				);
			}
		);
		$this->feeds_configurations = $feed_ids;
	}

	private function cleanup_local_feed_configs() {
		Pinterest_For_Woocommerce()::save_data( 'local_feed_ids', false );
	}

	/**
	 * Fetch local feed configurations;
	 */
	public function get_configurations() {
		return $this->feeds_configurations;
	}
}
