<?php
/**
 * Endpoint for the card page to show collections
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\RestApi\Collection;

use oddEvan\Grimoire\Model\BaseModel;
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
	protected $route = '/collection/usercollections';

	/**
	 * Add extra arguments for the card param and permissions callback
	 *
	 * @return array
	 */
	protected function get_args() : array {
		return [
			'permission_callback' => [ $this, 'check_permissions' ],
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
	 * Get all collections owned by the current user
	 *
	 * @param WP_REST_Request $request Incoming data.
	 * @return array|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		global $wpdb;

		$collections = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					`id`,
					`name`,
					`permalink` AS `slug`,
					`is_public` AS `isPublic`
				FROM {$wpdb->prefix}pods_collection AS `collection`
				WHERE
					`user_id` = %d", //phpcs:ignore
				get_current_user_id()
			),
			ARRAY_A
		);

		if ( $collections === false ) {
			return new WP_Error(
				[
					'status'  => 400,
					'message' => 'Database error: ' . BaseModel::get_wpdb_error(),
				]
			);
		}

		foreach ( $collections as $collection ) {
			$cards = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						`entries`.`quantity` as `quantity`,
						`entries`.`card_grimoire_id` as `id`,
						`cards`.`name` as `name`,
						`sets`.`name` as `set_name`,
						`sets`.`permalink` as `set_permalink`
					FROM {$wpdb->prefix}pods_entry AS `entries`
						LEFT JOIN {$wpdb->prefix}pods_card AS `cards` ON `cards`.`grimoire_id` = `entries`.`card_grimoire_id`
						LEFT JOIN {$wpdb->prefix}pods_set AS `sets` ON `sets`.`id` = `cards`.`set_id`
					WHERE
						`entries`.`collection_id` = %d", //phpcs:ignore
					$collection['id']
				),
				ARRAY_A
			);

			if ( $cards === false ) {
				return new WP_Error(
					[
						'status'  => 400,
						'message' => 'Database error: ' . BaseModel::get_wpdb_error(),
					]
				);
			}

			$collection['cards'] = array_map(
				function( $card ) {
					return [
						'quantity' => $card['quantity'],
						'card'     => [
							'id'      => $card['id'],
							'name'    => $card['name'],
							'setName' => $card['set_name'],
							'setSlug' => $card['set_slug'],
						],
					];
				}
			);
		}

		return $results ? $results : [];
	}
}
