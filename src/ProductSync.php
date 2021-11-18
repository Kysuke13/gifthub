<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
/**
 * Pinterest for WooCommerce Catalog Syncing
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use \Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler as ActionSchedulerProxy;
/**
 * Class Handling registration & generation of the XML product feed.
 */
class ProductSync {

	const ACTION_HANDLE_SYNC     = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-handle-sync';
	const ACTION_FEED_GENERATION = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-feed-generation';

	/**
	 * The time in seconds to consider the feed expired,
	 * and schedule regeneration.
	 */
	const FEED_EXPIRY = DAY_IN_SECONDS;

	/**
	 * The time in seconds to wait after a failed feed generation attempt,
	 * before attempting a retry.
	 */
	const WAIT_ON_ERROR_BEFORE_RETRY = HOUR_IN_SECONDS;

	/**
	 * Feed File Generator Instance
	 *
	 * @var $feed_generator FeedGenerator
	 */
	private static $feed_generator = null;

	/**
	 * Initiate class.
	 */
	public static function maybe_init() {

		if ( ! self::is_product_sync_enabled() && ! self::get_registered_feed_id() ) {
			return;
		}

		$locations = array( Pinterest_For_Woocommerce()::get_base_country() ?? 'US' ); // Replace with multiple countries array for multiple feed config.
		// Start Feed File Generator.
		self::initialize_feed_generator( $locations );

		// Hook the Scheduled actions.
		add_action( self::ACTION_HANDLE_SYNC, array( __CLASS__, 'handle_feed_registration' ) );
		add_action( self::ACTION_FEED_GENERATION, array( __CLASS__, 'handle_feed_generation' ) );

		add_action( 'pinterest-feed-start', array( __CLASS__, 'start_feed_generator' ) );

		if ( false === as_next_scheduled_action( 'pinterest-feed-start', array() ) ) {
			$interval = 10 * MINUTE_IN_SECONDS;
			as_schedule_recurring_action( time() + $interval, $interval, 'pinterest-feed-start' );
		}

		if ( self::is_product_sync_enabled() ) {

			// Schedule the main feed control task.
			if ( false === as_next_scheduled_action( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
				$interval = 10 * MINUTE_IN_SECONDS;
				as_schedule_recurring_action( time() + 10, $interval, self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
			}

			self::reschedule_if_expired();
			self::reschedule_if_errored();
			$state = ProductFeedStatus::get();

			if ( $state ) {
				// If local is not generated, or needs to be regenerated, schedule regeneration.
				if ( 'starting' === $state['status'] || 'in_progress' === $state['status'] ) {
					self::trigger_async_feed_generation();
				} elseif ( 'scheduled_for_generation' === $state['status'] || 'pending_config' === $state['status'] ) {
					self::feed_reschedule();
				}
			}

			/**
			 * Mark feed as needing re-generation whenever a product is edited or changed.
			 */
			add_action( 'edit_post', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );

			if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
				add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
				add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
			}

			// If feed is dirty on completion of feed generation, reschedule it.
			add_action( 'pinterest_for_woocommerce_feed_generated', array( __CLASS__, 'reschedule_if_dirty' ) );

			add_action( 'pinterest_for_woocommerce_feed_generated', array( __NAMESPACE__ . '\ProductFeedStatus', 'feed_data_cleanup' ) );
			add_action( 'pinterest_for_woocommerce_feed_error', array( __NAMESPACE__ . '\ProductFeedStatus', 'feed_data_cleanup' ) );

			// If feed is generated, but not yet registered, register it as soon as possible using an async task.
			add_action( 'pinterest_for_woocommerce_feed_generated', array( __CLASS__, 'trigger_async_feed_registration_asap' ) );
		} else {
			self::handle_feed_deregistration();
		}
	}

	/**
	 * Initialize the FeedGenerator instance.
	 *
	 * @since x.x.x
	 * @param array $locations Array of location to generate the feed files for.
	 */
	private static function initialize_feed_generator( $locations ) {
		$action_scheduler          = new ActionSchedulerProxy();
		$local_feed_configurations = new LocalFeedConfigs( $locations );
		self::$feed_generator      = new FeedGenerator( $action_scheduler, $local_feed_configurations );
		self::$feed_generator->init();
	}

	/**
	 * Deletes the XML file that is configured in the settings and deletes the feed_job option.
	 *
	 * @return void
	 */
	public static function feed_reset() {

		$local_feed = ProductFeedStatus::get_local_feed();

		if ( isset( $local_feed['feed_file'] ) && file_exists( $local_feed['feed_file'] ) ) {
			unlink( $local_feed['feed_file'] );
		}

		if ( isset( $local_feed['tmp_file'] ) && file_exists( $local_feed['tmp_file'] ) ) {
			unlink( $local_feed['tmp_file'] );
		}

		ProductFeedStatus::feed_transients_cleanup( $local_feed['feed_id'] );

		Pinterest_For_Woocommerce()::save_data( 'local_feed_id', false );
		Pinterest_For_Woocommerce()::save_data( 'feed_data_cache', false );

		self::log( 'Product feed reset and files deleted.' );
	}


	/**
	 * Checks if the feed file for the configured (In $state var) feed exists.
	 * This could be true as the feed is being generated, if its not the 1st time
	 * its been generated.
	 *
	 * @return bool
	 */
	public static function feed_file_exists() {

		$local_feed = ProductFeedStatus::get_local_feed();

		return isset( $local_feed['feed_file'] ) && file_exists( $local_feed['feed_file'] );
	}


	/**
	 * Schedules the regeneration process of the XML feed.
	 *
	 * @param boolean $force Forces rescheduling even when the status indicates that its not needed.
	 *
	 * @return void
	 */
	public static function feed_reschedule( $force = false ) {

		$state = ProductFeedStatus::get();

		if ( ! $force && isset( $state['status'] ) && in_array( $state['status'], array( 'in_progress', 'starting' ), true ) ) {
			return;
		}

		if ( 'scheduled_for_generation' !== $state['status'] ) {
			ProductFeedStatus::set(
				array(
					'status' => 'scheduled_for_generation',
				)
			);
		}

		if ( self::trigger_async_feed_generation( $force ) ) {
			self::log( 'Feed generation (re)scheduled.' );
		}
	}


	/**
	 * Logs Sync related messages separately.
	 *
	 * @param string $message The message to be logged.
	 * @param string $level   The level of the message.
	 *
	 * @return void
	 */
	private static function log( $message, $level = 'debug' ) {
		Logger::log( $message, $level, 'product-sync' );
	}


	/**
	 * Checks if the feature is enabled, and all requirements are met.
	 *
	 * @return boolean
	 */
	public static function is_product_sync_enabled() {

		$domain_verified  = Pinterest_For_Woocommerce()::is_domain_verified();
		$tracking_enabled = $domain_verified && Pinterest_For_Woocommerce()::is_tracking_configured();

		return (bool) $domain_verified && $tracking_enabled && Pinterest_For_Woocommerce()::get_setting( 'product_sync_enabled' );
	}


	/**
	 * Returns the feed profile ID if it's registered. Returns `false` otherwise.
	 *
	 * @return string|boolean
	 */
	public static function get_registered_feed_id() {
		return Pinterest_For_Woocommerce()::get_data( 'feed_registered' ) ?? false;
	}


	/**
	 * Check if the feed is registered based on the plugin's settings.
	 * If not, try to register it,
	 * Log issues.
	 * Potentially report issues.
	 *
	 * Should be run on demand when settings change,
	 * and on a scheduled basis.
	 *
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function handle_feed_registration() {

		if ( ! self::feed_file_exists() ) {
			self::log( 'Feed didn\'t fully generate yet. Retrying later.', 'debug' );
			// Feed is not generated yet, lets wait a bit longer.
			return true;
		}

		$local_feed = ProductFeedStatus::get_local_feed();

		$feed_args = array(
			'feed_location'             => $local_feed['feed_url'],
			'feed_format'               => 'XML',
			'feed_default_currency'     => get_woocommerce_currency(),
			'default_availability_type' => 'IN_STOCK',
			'country'                   => Pinterest_For_Woocommerce()::get_base_country() ?? 'US',
			'locale'                    => str_replace( '_', '-', determine_locale() ),
		);

		try {
			$registered = self::register_feed( $feed_args );

			if ( $registered ) {
				return true;
			}

			throw new \Exception( esc_html__( 'Could not register feed.', 'pinterest-for-woocommerce' ) );

		} catch ( \Throwable $th ) {
			self::log( $th->getMessage(), 'error' );
			return false;
		}

	}


	/**
	 * Handles de-registration of the feed.
	 * $feed_args are needed so that they are passed to update_merchant_feed() in order to perform the update.
	 * Running this, sets the feed to 'DISABLED' in Pinterest, deletes the local XML file and the option holding the feed
	 * status of the feed generation job.
	 *
	 * @return void
	 */
	private static function handle_feed_deregistration() {
		self::feed_reset();
		self::cancel_jobs();
		Pinterest_For_Woocommerce()::save_data( 'feed_registered', false );
	}

	/**
	 * If the feed is not already registered, schedules an async action to registrer it asap.
	 *
	 * @return void
	 */
	public static function trigger_async_feed_registration_asap() {

		if ( self::get_registered_feed_id() ) {
			return;
		}

		self::log( 'running trigger_async_feed_registration_asap' );

		as_unschedule_all_actions( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_enqueue_async_action( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}


	/**
	 * Make API request to add_merchant_feed.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 * @param array  $feed_args   The arguments used to create the feed.
	 *
	 * @return string|bool
	 */
	private static function do_add_merchant_feed( $merchant_id, $feed_args ) {
		$feed = API\Base::add_merchant_feed( $merchant_id, $feed_args );

		if ( $feed && 'success' === $feed['status'] && isset( $feed['data']->location_config->full_feed_fetch_location ) ) {
			self::log( 'Added merchant feed: ' . $feed_args['feed_location'] );
			return $feed['data']->id;
		}

		return false;
	}


	/**
	 * Handles feed registration using the given arguments.
	 * Will try to create a merchant if none exists.
	 * Also if a different feed is registered, it will update using the URL in the
	 * $feed_args.
	 *
	 * @param array $feed_args The arguments used to create the feed.
	 *
	 * @return boolean|string
	 *
	 * @throws \Exception PHP Exception.
	 */
	private static function register_feed( $feed_args ) {

		// Get merchant object.
		$merchant   = Merchants::get_merchant( $feed_args );
		$registered = false;

		if ( ! empty( $merchant['data']->id ) && 'declined' === $merchant['data']->product_pin_approval_status ) {
			$registered = false;
			self::log( 'Pinterest returned a Declined status for product_pin_approval_status' );
		} elseif ( ! empty( $merchant['data']->id ) && ! isset( $merchant['data']->product_pin_feed_profile ) ) {
			// No feed registered, but we got a merchant.
			$registered = self::do_add_merchant_feed( $merchant['data']->id, $feed_args );
		} elseif ( $feed_args['feed_location'] === $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location ) {
			// Feed registered.
			$registered = $merchant['data']->product_pin_feed_profile->id;
			self::log( 'Feed registered for merchant: ' . $feed_args['feed_location'] );
		} else {
			$product_pin_feed_profile    = $merchant['data']->product_pin_feed_profile;
			$product_pin_feed_profile_id = false;
			$prev_registered             = self::get_registered_feed_id();
			if ( false !== $prev_registered ) {
				try {
					$feed                        = API\Base::get_merchant_feed( $merchant['data']->id, $prev_registered );
					$product_pin_feed_profile_id = $feed['data']->feed_profile_id;
				} catch ( \Throwable $e ) {
					$product_pin_feed_profile_id = false;
				}
			}

			if ( false === $product_pin_feed_profile_id ) {
				$configured_path = dirname( $product_pin_feed_profile->location_config->full_feed_fetch_location );
				$local_path      = dirname( $feed_args['feed_location'] );

				if ( $configured_path === $local_path && $feed_args['country'] === $product_pin_feed_profile->country && $feed_args['locale'] === $product_pin_feed_profile->locale ) {
					// We can assume we're on the same site.

					$product_pin_feed_profile_id = $product_pin_feed_profile->id;
				}
			}

			if ( false !== $product_pin_feed_profile_id ) { // We update a feed, if we have one matching our site.
				// We cannot change the country or locale, so we remove that from the parameters to send.
				$update_feed_args = $feed_args;
				unset( $update_feed_args['country'] );
				unset( $update_feed_args['locale'] );

				// Actually do the update.
				$feed = API\Base::update_merchant_feed( $product_pin_feed_profile->merchant_id, $product_pin_feed_profile_id, $update_feed_args );

				if ( $feed && 'success' === $feed['status'] && isset( $feed['data']->location_config->full_feed_fetch_location ) ) {
					$registered = $feed['data']->id;
					self::log( 'Merchant\'s feed updated to current location: ' . $feed_args['feed_location'] );
				}
			} else {
				// We cannot infer that a feed exists, therefore we create a new one.
				$registered = self::do_add_merchant_feed( $merchant['data']->id, $feed_args );
			}
		}

		Pinterest_For_Woocommerce()::save_data( 'feed_registered', $registered );

		return $registered;
	}

	/**
	 * Check if Given ID is of a product and if yes, mark feed as dirty.
	 *
	 * @param integer $product_id The product ID.
	 *
	 * @return void
	 */
	public static function mark_feed_dirty( $product_id ) {
		if ( ! wc_get_product( $product_id ) ) {
			return;
		}

		Pinterest_For_Woocommerce()::save_data( 'feed_dirty', true );
	}


	/**
	 * Check if feed is marked and dirty, and reschedule feed generation.
	 *
	 * @return void
	 */
	public static function reschedule_if_dirty() {

		if ( Pinterest_For_Woocommerce()::get_data( 'feed_dirty' ) ) {
			Pinterest_For_Woocommerce()::save_data( 'feed_dirty', false );
			self::log( 'Feed is dirty.' );
			self::feed_reschedule();
		}
	}


	/**
	 * Check if feed is expired, and reschedule feed generation.
	 *
	 * @return void
	 */
	public static function reschedule_if_expired() {

		$state = ProductFeedStatus::get();

		if ( ( 'generated' === $state['status'] && $state['last_activity'] < ( time() - self::FEED_EXPIRY ) ) ) {
			self::log( 'Feed is expired.' );
			self::feed_reschedule();
		}
	}

	/**
	 * Check if feed is expired, and reschedule feed generation.
	 *
	 * @return void
	 */
	public static function reschedule_if_errored() {

		$state = ProductFeedStatus::get();

		if ( ( 'error' === $state['status'] && $state['last_activity'] < ( time() - self::WAIT_ON_ERROR_BEFORE_RETRY ) ) ) {
			self::log( 'Retrying feed generation after error.' );
			self::feed_reschedule();
		}
	}

	/**
	 * Cancels the scheduled product sync jobs.
	 *
	 * @return void
	 */
	public static function cancel_jobs() {
		as_unschedule_all_actions( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_unschedule_all_actions( self::ACTION_FEED_GENERATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}

	public static function start_feed_generator() {
		// START: New feed generator entry point.
		if ( ! self::$feed_generator->is_running() ) {
			self::$feed_generator->queue_start();
		}
		// END: New feed generator entry point.
	}
}
