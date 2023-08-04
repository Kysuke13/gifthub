<?php
/**
 * @package Pinterest_For_Woocommerce/API/Conversions
 */
namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

/**
 * @link https://developers.pinterest.com/docs/conversions/best/#Required,%20recommended,%20and%20optional%20fields
 *
 * @since x.x.x
 */
class User extends Data {

	/**
	 * @var string User's IP address.
	 */
	private string $client_ip_address;

	/**
	 * @var string User's user agent.
	 */
	private string $client_user_agent;

	public function __construct( string $client_ip_address, string $client_user_agent ) {
		$this->client_ip_address = $client_ip_address;
		$this->client_user_agent = $client_user_agent;
	}

	/**
	 * @return string
	 */
	public function get_client_ip_address(): string {
		return $this->client_ip_address;
	}

	/**
	 * @return string
	 */
	public function get_client_user_agent(): string {
		return $this->client_user_agent;
	}
}
