<?php
/**
 * Pinterest for WooCommerce Tracking. Conversions API.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\Logger as Logger;
use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Category;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Checkout;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Product;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Search;
use Automattic\WooCommerce\Pinterest\Tracking\Data\User;
use Throwable;

/**
 * Pinterest Conversions API support.
 */
class Conversions implements Tracker {

	const EVENT_MAP = array(
		Tracking::EVENT_PAGE_VISIT    => 'page_visit',
		Tracking::EVENT_SEARCH        => 'search',
		Tracking::EVENT_VIEW_CATEGORY => 'view_category',
		Tracking::EVENT_ADD_TO_CART   => 'add_to_cart',
		Tracking::EVENT_CHECKOUT      => 'checkout',
	);

	/** @var User $user User data object. Data for Conversions API. */
	private $user;

	/**
	 * Pinterest Conversions API class constructor.
	 *
	 * @param User $user User data object to hold ip address and agent string.
	 */
	public function __construct( User $user ) {
		$this->user = $user;
	}

	/**
	 * Track event function implementation. Used to send event data to a destination.
	 *
	 * @param string $event_name Tracking event name.
	 * @param Data   $data       Tracking event data class.
	 *
	 * @throws Throwable In case of an API error.
	 *
	 * @return void
	 */
	public function track_event( string $event_name, Data $data ) {
		$data          = $this->prepare_request_data( $event_name, $data );
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );

		try {
			/* translators: 1: Conversions API event name, 2: JSON encoded event data. */
			$messages = sprintf(
				'Sending Pinterest Conversions API event %1$s with a payload: %2$s',
				$event_name,
				json_encode( $data )
			);
			Logger::log( $messages, 'debug', 'conversions' );

			APIV5::make_request(
				"ad_accounts/{$ad_account_id}/events",
				'POST',
				array( 'data' => array( $data ) )
			);
		} catch ( Throwable $e ) {
			/* translators: 1: Conversions API event name, 2: JSON encoded event data, 3: Error code, 4: Error message. */
			$messages = sprintf(
				'Sending Pinterest Conversions API event %1$s with a payload %2$s has failed with the error %3$d code and %4$s message',
				$event_name,
				json_encode( $data ),
				$e->getCode(),
				$e->getMessage()
			);
			Logger::log( $messages, 'error', 'conversions' );

			throw $e;
		}
	}

	/**
	 * Prepares event data for the request.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name
	 * @param Data   $data
	 *
	 * @return array
	 */
	public function prepare_request_data( string $event_name, Data $data ) {
		switch ( $event_name ) {
			case Tracking::EVENT_SEARCH:
				/** @var Search $data */
				$data = $this->get_search_data( $data );
				break;

			case Tracking::EVENT_PAGE_VISIT:
				/** @var Product $data */
				$data = $this->get_page_visit_data( $data );
				break;

			case Tracking::EVENT_VIEW_CATEGORY:
				/** @var Category $data */
				$data = $this->get_view_category_data( $data );
				break;

			case Tracking::EVENT_ADD_TO_CART:
				/** @var Product $data */
				$data = $this->get_add_to_cart_data( $data );
				break;

			case Tracking::EVENT_CHECKOUT:
				/** @var Checkout $data */
				$data = $this->get_checkout_data( $data );
				break;

			default:
				$data = array(
					'event_id' => $data->get_event_id(),
				);
		}

		$event_name = static::EVENT_MAP[ $event_name ] ?? '';
		return array_merge( $data, $this->get_default_data( $event_name ) );
	}

	/**
	 * Prepares default event data.
	 *
	 * @param string $event_name
	 *
	 * @return array
	 */
	private function get_default_data( string $event_name ) {
		global $wp;

		return array(
			'event_name'       => $event_name,
			'action_source'    => 'web',
			'event_time'       => time(),
			'event_source_url' => home_url( $wp->request ),
			'partner_name'     => 'ss-woocommerce',
			'user_data'        => array(
				'client_ip_address' => $this->user->get_client_ip_address(),
				'client_user_agent' => $this->user->get_client_user_agent(),
			),
			'language'         => 'en',
		);
	}

	/**
	 * Prepares data for the checkout event.
	 *
	 * @since x.x.x
	 *
	 * @param Checkout $data
	 *
	 * @return array
	 */
	private function get_checkout_data( Checkout $data ) {
		return array(
			'event_id'    => $data->get_event_id(),
			'custom_data' => array(
				'currency'    => $data->get_currency(),
				'value'       => $data->get_price(),
				'content_ids' => array_map(
					function ( Product $product ) {
						return $product->get_id();
					},
					$data->get_items()
				),
				'contents'    => array_map(
					function ( Product $product ) {
						return array(
							'id'         => $product->get_id(),
							'item_price' => $product->get_price(),
							'quantity'   => $product->get_quantity(),
						);
					},
					$data->get_items()
				),
				'num_items'   => $data->get_quantity(),
			),
		);
	}

	/**
	 * Prepares data for add to cart event.
	 *
	 * @since x.x.x
	 *
	 * @param Product $data
	 *
	 * @return array
	 */
	private function get_add_to_cart_data( Product $data ) {
		return array(
			'event_id'    => $data->get_event_id(),
			'custom_data' => array(
				'currency'    => $data->get_currency(),
				'value'       => $data->get_price() * $data->get_quantity(),
				'content_ids' => array( $data->get_id() ),
				'contents'    => array(
					array(
						'id'         => $data->get_id(),
						'item_price' => $data->get_price(),
						'quantity'   => $data->get_quantity(),
					),
				),
				'num_items'   => $data->get_quantity(),
			),
		);
	}

	/**
	 * Prepares data for view category event.
	 *
	 * @since x.x.x
	 *
	 * @param Category $data
	 *
	 * @return array
	 */
	private function get_view_category_data( Category $data ) {
		return array(
			'event_id'    => $data->get_event_id(),
			'custom_data' => array(
				'category_name' => $data->get_name(),
			),
		);
	}

	/**
	 * Prepares data for page visit event.
	 *
	 * @since x.x.x
	 *
	 * @param Product|None $data
	 *
	 * @return array
	 */
	private function get_page_visit_data( Data $data ) {
		if ( $data instanceof None ) {
			return array(
				'event_id' => $data->get_event_id(),
			);
		}

		return array(
			'event_id'    => $data->get_event_id(),
			'custom_data' => array(
				'currency'    => $data->get_currency(),
				'value'       => $data->get_price() * $data->get_quantity(),
				'content_ids' => array( $data->get_id() ),
				'contents'    => array(
					array(
						'id'         => $data->get_id(),
						'item_price' => $data->get_price(),
						'quantity'   => $data->get_quantity(),
					),
				),
				'num_items'   => $data->get_quantity(),
			),
		);
	}

	/**
	 * Prepares data for the search event.
	 *
	 * @since x.x.x
	 *
	 * @param Search $data
	 *
	 * @return array
	 */
	private function get_search_data( Search $data ) {
		return array(
			'event_id'    => $data->get_event_id(),
			'custom_data' => array(
				'search_string' => $data->get_search_query(),
			),
		);
	}
}
