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
class DownloadExport extends BaseEndpoint {
	/**
	 * Route for this endpoint
	 *
	 * @var string
	 */
	protected $route = '/collection/(?P<id>[0-9]+)/export';

	/**
	 * Add extra arguments for the name and permissions callback
	 *
	 * @return array
	 */
	protected function get_args() : array {
		return [
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'id'       => [
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
	 * Make sure collection ID matches the correct format
	 *
	 * @param string $param id parameter from the request.
	 * @return boolean
	 */
	public function validate_number( $param ) : bool {
		return is_numeric( $param );
	}

	/**
	 * Create a CSV for the collection
	 *
	 * @param WP_REST_Request $request Incoming data.
	 * @return array|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		global $wpdb;

		$user_id      = get_current_user_id();
		$collection_id = $request['id'];

		// Check that the collection exists and is owned by the current user. If so, get the name.
		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}pods_collection WHERE `id` = %d && `user_id` = %d", //phpcs:ignore
				$collection_id,
				$user_id
			)
		);
		if ( ! $name ) {
			return new WP_Error(
				'not_found',
				'The indicated collection was not found.',
				[ 'status' => 404 ]
			);
		}

		$db_result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					`card`.`grimoire_id`,
					`card`.`tcgplayer_sku`,
					`card`.`card_title`,
					`set`.`name`,
					`entry`.`quantity`
				FROM {$wpdb->prefix}_pods_entry as `entry`
					LEFT JOIN {$wpdb->prefix}_pods_card as `card` on `entry`.`card_grimoire_id` = `card`.`grimoire_id`
					LEFT JOIN {$wpdb->prefix}_pods_set as `set` on `card`.`set_id` = `set`.`id`
				WHERE `entry`.`collection_id` = %d",
				$collection_id
			),
			ARRAY_A
		);

		if ( $db_result === false ) {
			return new WP_Error(
				'database_error',
				'Database error: ' . BaseModel::get_wpdb_error(),
				[ 'status' => 400 ]
			);
		}

		header("Content-disposition: attachment; filename={$name}.csv");
		header("Content-type: text/csv");
		echo 'Grimoire ID,TCGplayer Near Mint SKU,Name,Set,Quantity' . PHP_EOL;

		foreach( $db_result as $entry ) {
			echo implode(',', $entry) . PHP_EOL;
		}

		die;
	}
}
