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
class CreateCollection extends BaseEndpoint {
	/**
	 * Route for this endpoint
	 *
	 * @var string
	 */
	protected $route = '/collection/create';

	/**
	 * Add extra arguments for the name and permissions callback
	 *
	 * @return array
	 */
	protected function get_args() : array {
		return [
			'methods'             => WP_REST_Server::EDITABLE,
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'name' => [
					'validate_callback' => [ $this, 'validate_string' ],
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
	 * Make sure collection name matches the correct format
	 *
	 * @param string $param name parameter from the request.
	 * @return boolean
	 */
	public function validate_string( $param ) : bool {
		return preg_match( '/^[^\n]+$/', $param ) === 1;
	}

	/**
	 * Update entry for this collection and card
	 *
	 * @param WP_REST_Request $request Incoming data.
	 * @return array|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		global $wpdb;

		$user_id      = get_current_user_id();
		$display_name = $request['name'];
		$slug         = sanitize_title( $display_name );

		// Check that the collection does not already exist.
		$check = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}pods_collection WHERE `permalink` = %s AND `user_id` = %d", //phpcs:ignore
				$slug,
				$user_id
			)
		);
		if ( $check ) {
			return new WP_Error(
				[
					'status'  => 409,
					'message' => 'A collection with this (or very similar) name already exists.',
				]
			);
		}

		$db_result     = false;
			$db_result = $wpdb->insert(
				$wpdb->prefix . 'pods_collection',
				[
					'name'      => $display_name,
					'permalink' => $slug,
					'user_id'   => $user_id,
					'created'   => gmdate( DATE_RFC3339 ),
					'modified'  => gmdate( DATE_RFC3339 ),
				],
				[ '%s', '%s', '%d', '%s', '%s' ]
			);

		if ( $db_result === false ) {
			return new WP_Error(
				[
					'status'  => 400,
					'message' => 'Database error: ' . BaseModel::get_wpdb_error(),
				]
			);
		}

		return [
			'id'   => $wpdb->insert_id,
			'name' => $display_name,
			'slug' => $slug,
		];
	}
}
