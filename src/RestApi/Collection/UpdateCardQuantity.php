<?php
/**
 * Endpoint for the card page to show collections
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\RestApi\Collection;

use oddEvan\Grimoire\RestApi\BaseEndpoint;
use \WP_REST_Server;
use \WP_REST_Request;
use \WP_REST_Response;
use \WP_Error;

/**
 * Endpoint to show collections
 */
class UpdateCardQuantity extends BaseEndpoint {
	/**
	 * Route for this endpoint
	 *
	 * @var string
	 */
	protected $route = '/collection/(?P<id>[0-9]+)/updatecardquantity';

	/**
	 * Add extra arguments for the card param and permissions callback
	 *
	 * @return array
	 */
	protected function get_args() : array {
		return [
			'methods'             => WP_REST_Server::EDITABLE,
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'id'       => [
					'validate_callback' => [ $this, 'validate_number' ],
				],
				'card_id'  => [
					'validate_callback' => [ $this, 'validate_card_id' ],
				],
				'quantity' => [
					'validate_callback' => [ $this, 'validate_number' ],
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
	 * Make sure collection ID matches the correct format
	 *
	 * @param string $param id parameter from the request.
	 * @return boolean
	 */
	public function validate_number( $param ) : bool {
		return is_numeric( $param );
	}

	/**
	 * Update entry for this collection and card
	 *
	 * @param WP_REST_Request $request Incoming data.
	 * @return array|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		global $wpdb;

		$params = $request->get_json_params();

		$grimoire_id   = $params['card_id'];
		$collection_id = $request['id'];
		$user_id       = get_current_user_id();
		$quantity      = $params['quantity'];

		// Check that the card exists.
		$check = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}pods_card WHERE `grimoire_id` = %s", //phpcs:ignore
				$grimoire_id
			)
		);
		if ( ! $check ) {
			return new WP_Error(
				'not_found',
				'The indicated card was not found.',
				[ 'status' => 404 ]
			);
		}

		// Check that the collection exists and is owned by the current user.
		$check = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}pods_collection WHERE `id` = %d && `user_id` = %d", //phpcs:ignore
				$collection_id,
				$user_id
			)
		);
		if ( ! $check ) {
			return new WP_Error(
				'not_found',
				'The indicated collection was not found.',
				[ 'status' => 404 ]
			);
		}

		// Check for any existing entry.
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}pods_entry WHERE `collecton_id` = %d && `card_grimoire_id` = %s", //phpcs:ignore
				$collection_id,
				$grimoire_id
			)
		);

		$db_result = false;
		if ( $quantity <= 0 ) {
			if ( $existing_id ) {
				$db_result = $wpdb->delete(
					$wpdb->prefix . 'pods_entry',
					[ 'id' => $existing_id ],
					'%d'
				);
			} else {
				// No line, and quantity is zero, so do nothing.
				$db_result = true;
			}
		} elseif ( $existing_id ) {
			$db_result = $wpdb->update(
				$wpdb->prefix . 'pods_entry',
				[
					'quantity' => $quantity,
					'modified' => gmdate( DATE_RFC3339 ),
				],
				[ 'id' => $existing_id ],
				[ '%d', '%s' ],
				'%d'
			);
		} else {
			$db_result = $wpdb->insert(
				$wpdb->prefix . 'pods_entry',
				[
					'collection_id'    => $collection_id,
					'card_grimoire_id' => $grimoire_id,
					'quantity'         => $quantity,
					'created'          => gmdate( DATE_RFC3339 ),
					'modified'         => gmdate( DATE_RFC3339 ),
				],
				[ '%d', '%s', '%d', '%s', '%s' ]
			);
		}

		if ( $db_result === false ) {
			return new WP_Error(
				'database_error',
				'Database error: ' . BaseModel::get_wpdb_error(),
				[ 'status' => 400 ]
			);
		}

		return [
			'id'       => $collection_id,
			'card_id'  => $grimoire_id,
			'quantity' => $quantity,
		];
	}
}
