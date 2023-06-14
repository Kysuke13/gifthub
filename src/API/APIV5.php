<?php
/**
 * Pinterest API V5 class
 *
 * @class       Pinterest_For_Woocommerce_API
 * @version     x.x.x
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Automattic\WooCommerce\Pinterest\PinterestApiException as ApiException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API V5 Methods
 */
class APIV5 extends Base {

	const API_DOMAIN = 'https://api.pinterest.com/v5';

	/**
	 * Prepare request
	 *
	 * @param string $endpoint        the endpoint to perform the request on.
	 * @param string $method          eg, POST, GET, PUT etc.
	 * @param array  $payload         Payload to be sent on the request's body.
	 * @param string $api             The specific Endpoints subset.
	 *
	 * @return array
	 */
	public static function prepare_request( $endpoint, $method = 'POST', $payload = array(), $api = '' ) {

		return array(
			'url'         => static::API_DOMAIN . "/{$endpoint}",
			'method'      => $method,
			'args'        => $payload,
			'headers'     => array(
				'Pinterest-Woocommerce-Version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
				'Content-Type'                  => 'application/json',
			),
			'data_format' => 'body',
		);
	}

	/**
	 * Returns basic user information.
	 *
	 * @since x.x.x
	 *
	 * @return array|mixed
	 * @throws ApiException
	 */
	public static function get_account_info() {
		$integration_data = \Pinterest_For_Woocommerce::get_data( 'integration_data', true );
		return self::make_request(
			'user_account',
			'GET'/*,
			json_encode(
				array(
					'ad_account_id' => $integration_data['connected_advertiser_id'] ?? '',
				)
			)*/
		);
	}


	/**
	 * Returns the list of the user's websites.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 * @throws ApiException
	 */
	public static function get_user_websites() {
		return self::make_request(
			'user_account/websites',
			'GET'
		);
	}

	/**
	 * Returns the list of linked businesses.
	 *
	 * @since x.x.x
	 *
	 * @return array|mixed
	 * @throws ApiException
	 */
	public static function get_linked_businesses() {
		return self::make_request( 'user_account/businesses', 'GET' );
	}

	/**
	 * Get the advertiser object from the Pinterest API for the given User ID.
	 *
	 * @since x.x.x
	 *
	 * @return mixed
	 */
	public static function get_advertisers( $pinterest_user = null ) {
		return self::make_request( 'ad_accounts', 'GET' );
	}

	/**
	 * Get the advertiser's tracking tags.
	 *
	 * @param string $ad_account_id the advertiser_id to request the tags for.
	 *
	 * @return mixed
	 */
	public static function get_advertiser_tags( $ad_account_id ) {
		return self::make_request( "ad_accounts/{$ad_account_id}/conversion_tags", 'GET' );
	}

	/**
	 * Returns Pinterest user verification code for website verification.
	 *
	 * @since x.x.x
	 *
	 * @return array {
	 * 		Data needed to verify a website.
	 *
	 * 		@type string $verification_code Code to check against the user claiming the website.
	 * 		@type string $dns_txt_record 	DNS TXT record to check against for the website to be claimed.
	 * 		@type string $metatag 			META tag the verification process searches for the website to be claimed.
	 * 		@type string $filename 			File expected to find on the website being claimed.
	 * 		@type string $file_content 		A full html file to upload to the website in order for it to be claimed.
	 * }
	 * @throws PinterestApiException
	 */
	public static function domain_verification_data(): array {
		return self::make_request( 'user_account/websites/verification' );
	}

	/**
	 * Sends domain verification request to Pinterest.
	 *
	 * @since x.x.x
	 *
	 * @param string $domain Domain to verify.
	 * @return array {
	 * 		Data returned by Pinterest after the verification request.
	 *
	 * 		@type string $website 		Website with path or domain only.
	 * 		@type string $status 		Status of the verification process.
	 * 		@type string $verified_at 	UTC timestamp when the verification happened - sometimes missing.
	 * }
	 * @throws PinterestApiException
	 */
	public static function domain_metatag_verification_request( string $domain ): array {
		return self::make_request(
			'user_account/websites',
			'POST',
			array(
				'website'             => $domain,
				'verification_method' => 'METATAG',
			)
		);
	}
}
