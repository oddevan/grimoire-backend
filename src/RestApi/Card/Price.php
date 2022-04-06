<?php
/**
 * Endpoint for the card page to show collections
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\RestApi\Card;

use oddEvan\Grimoire\RestApi\BaseEndpoint;
use \WP_REST_Request;
use \WP_REST_Response;
use \WP_Error;

/**
 * Endpoint to show collections
 */
class Price extends BaseEndpoint {
	/**
	 * Route for this endpoint
	 *
	 * @var string
	 */
	protected $route = '/card/(?P<card>[a-zA-Z0-9-]+)/price';

	/**
	 * Add extra arguments for the card param and permissions callback
	 *
	 * @return array
	 */
	protected function get_args() : array {
		return [
			'permission_callback' => '__return_true',
			'args'                => [
				'card' => [
					'validate_callback' => [ $this, 'validate_card_id' ],
				],
			],
		];
	}

	/**
	 * Make sure Card matches the correct format
	 *
	 * @param string $param card parameter from the request.
	 * @return boolean
	 */
	public function validate_card_id( string $param ) : bool {
		return preg_match( '/^pkm-[a-z]{3}-[a-z0-9-]+$/', $param ) === 1;
	}

	/**
	 * Get the latest cached market price for this card
	 *
	 * @param WP_REST_Request $request Incoming data.
	 * @return array|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		global $wpdb;

		$grimoire_id = $request['card'];

		$price = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `price` FROM {$wpdb->prefix}pods_card WHERE `grimoire_id` = %s", //phpcs:ignore
				$grimoire_id
			)
		);

		if ( false === $price ) {
			return new WP_Error(
				'not_found',
				'The indicated card was not found.',
				[ 'status' => 404 ]
			);
		}

		$result = [
            'id' => $grimoire_id,
            'price' => $price ? $price : -1,
        ];
        
        return $result;
	}
}
