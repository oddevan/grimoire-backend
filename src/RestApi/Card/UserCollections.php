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
class UserCollections extends BaseEndpoint {
	/**
	 * Route for this endpoint
	 *
	 * @var string
	 */
	protected $route = '/card/(?P<card>[a-zA-Z0-9-]+)/usercollections';

	/**
	 * Add extra arguments for the card param and permissions callback
	 *
	 * @return array
	 */
	protected function get_args() : array {
		return [
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'card' => [
					'validate_callback' => [ $this, 'validate_card_id' ],
				],
			],
		];
	}

	/**
	 * Check to see if this is an authenticated request.
	 *
	 * @return boolean
	 */
	public function check_permissions() : bool {
		return current_user_can( 'read' );
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
	 * Get all collections owned by the current user with this card.
	 *
	 * @param WP_REST_Request $request Incoming data.
	 * @return array|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		global $wpdb;

		$grimoire_id = $request['card'];

		$check = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}pods_card WHERE `grimoire_id` = %s", //phpcs:ignore
				$grimoire_id
			)
		);

		if ( ! $check ) {
			return new WP_Error(
				[
					'status'  => 404,
					'message' => 'The indicated card was not found.',
				]
			);
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					`collection`.`id`,
					`collection`.`name`,
					`entry`.`quantity`
				FROM {$wpdb->prefix}pods_entry AS `entry`
					INNER JOIN {$wpdb->prefix}pods_collection AS `collection` ON `entry`.`collection_id` = `collection`.`id`
				WHERE
				  `entry`.`card_grimoire_id` = %s AND
					`collection`.`user_id` = %d", //phpcs:ignore
				$grimoire_id,
				get_current_user_id()
			),
			ARRAY_A
		);

		return $results ? $results : [];
	}
}
