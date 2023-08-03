<?php

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Tracking\Data;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Category;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Checkout;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Product;
use Automattic\WooCommerce\Pinterest\Tracking\Tag;
use Automattic\WooCommerce\Pinterest\Tracking\Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tracker responsible for tracking events on the website and call corresponding trackers to send events to
 * their systems.
 */
class Tracking {

	const EVENT_CHECKOUT      = 'Checkout';

	const EVENT_ADD_TO_CART   = 'AddToCart';

	const EVENT_PAGE_VISIT    = 'PageVisit';

	const EVENT_SEARCH        = 'Search';

	const EVENT_VIEW_CATEGORY = 'ViewCategory';

	/**
	 * @var array $trackers A list of available trackers.
	 */
	private $trackers = array();

	public function __construct() {
		// Tracks page visit events.
		add_action('wp_footer', array( $this, 'handle_page_visit' ) );

		// Tracks category visit events.
		add_action( 'wp_footer', array( $this, 'handle_view_category' ) );

		// Tracks add to cart events.
		add_action( 'woocommerce_add_to_cart', array( $this, 'handle_add_to_cart' ), 10, 6 );

		// Tracks checkout events.
		add_action( 'woocommerce_checkout_order_created', array( $this, 'handle_checkout' ), 10, 2 );

		add_action( '', array( $this, 'handle_search' ) );
	}

	/**
	 * Used as a callback for the wp_footer hook.
	 *
	 * @return void
	 */
	public function handle_page_visit() {
		$data = new None();
		if ( is_product() ) {
			$product = wc_get_product();
			$data    = new Product(
				$product->get_id(),
				$product->get_name(),
				wc_get_product_category_list( $product->get_id() ),
				'brand',
				$product->get_price(),
				get_woocommerce_currency(),
				1
			);
		}
		$this->maybe_track_event( static::EVENT_PAGE_VISIT, $data );
	}

	/**
	 * Used as a callback for the wp_footer hook.
	 *
	 * @return void
	 */
	public function handle_view_category() {
		if ( ! is_product_category() ) {
			return;
		}
		$queried_object = get_queried_object();
		$data           = new Category( $queried_object->term_id, $queried_object->name );
		$this->maybe_track_event( static::EVENT_VIEW_CATEGORY, $data );
	}

	/**
	 * Used as a callback for the woocommerce_add_to_cart hook.
	 *
	 * @return void
	 */
	public function handle_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id ) {
		$object_id = empty( $variation_id ) ? $product_id : $variation_id;
		$product   = wc_get_product( $object_id );
		$data      = new Product(
			$product->get_id(),
			$product->get_name(),
			wc_get_product_category_list( $product->get_id() ),
			'brand',
			$product->get_price(),
			get_woocommerce_currency(),
			$quantity
		);
		$this->maybe_track_event( static::EVENT_ADD_TO_CART, $data );
	}

	/**
	 * Used as a callback for the woocommerce_checkout_order_created hook.
	 *
	 * @return void
	 */
	public function handle_checkout( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$items    = array();
		$total_quantity = 0;
		foreach ( $order->get_items() as $order_item ) {
			if ( ! method_exists( $order_item, 'get_product' ) ) {
				continue;
			}

			$product       = $order_item->get_product();
			$product_price = $product->get_price();
			$terms         = wc_get_object_terms( $product->get_id(), 'product_cat' );
			$categories    = ! empty( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();

			$items[] = new Product(
				$product->get_id(),
				$order_item->get_name(),
				$categories,
				'brand',
				$product_price,
				get_woocommerce_currency(),
				$order_item->get_quantity()
			);
			/*$items[] = array(
				'product_id'       => $product->get_id(),
				'product_name'     => $order_item->get_name(),
				'product_price'    => $product_price,
				'product_quantity' => $order_item->get_quantity(),
				'product_category' => $categories,
			);*/

			$total_quantity += $order_item->get_quantity();
		}

		$data = new Checkout(
			$order_id,
			$order->get_total(),
			$total_quantity,
			$order->get_currency(),
			$items
		);
		$this->maybe_track_event( static::EVENT_CHECKOUT, $data );
	}

	public function handle_search() {
		if ( ! is_search() ) {
			return;
		}

		$data = new Search( get_search_query() );
		$this->maybe_track_event( static::EVENT_SEARCH, $data );
	}

	public function maybe_track_event( string $event_name, Data $data ) {
		$is_tracking_enabled             = apply_filters( 'woocommerce_pinterest_disable_tracking', false );
		$is_tracking_conversions_enabled = Pinterest_For_Woocommerce()::get_setting( 'track_conversions' );
		$is_tracked_site                 = ! wp_doing_cron() && ! is_admin();

		if ( $is_tracking_enabled && $is_tracking_conversions_enabled && $is_tracked_site ) {
			foreach ( $this->get_trackers() as $tracker ) {
				// Skip Pinterest tag tracking if tag is not active.
				if ( $tracker instanceof Tag && ! Tag::get_active_tag() ) {
					continue;
				}
				$tracker->track_event( $event_name, $data );
			}
			return true;
		}
		return false;
	}

	/**
	 * Returns an array of registered trackers.
	 *
	 * @since x.x.x
	 *
	 * @return Tracker[]
	 */
	public function get_trackers() {
		$this->trackers[] = new Tag();
		// $this->trackers[] = new PinterestConversions( new Conversions\UserData( WC_Geolocation::get_ip_address(), wc_get_user_agent() ), new Conversions\NoData() );
		return $this->trackers;
	}

	/**
	 * Adds a tracker to the array of trackers.
	 *
	 * @param Tracker $tracker
	 *
	 * @return void
	 */
	public function add_tracker( Tracker $tracker ) {
		$this->trackers[] = $tracker;
	}

	/**
	 * Removes a tracker.
	 *
	 * @param string $tracker Tracker class name to be removed. e.g. PinterestTag::class
	 * @return void
	 */
	public function remove_tracker( string $tracker ) {
		$this->trackers = array_filter(
			$this->trackers,
			function( $item ) use ( $tracker ) {
				return get_class( $item ) !== $tracker;
			}
		);
	}
}
