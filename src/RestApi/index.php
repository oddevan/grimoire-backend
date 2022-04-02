<?php
/**
 * Register REST API-related classes and their hooks.
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\RestApi;

/**
 * Loop through and register the REST API classes
 */
function register_endpoints() {
	$types = [
		Card\UserCollections::class,
		Collection\CreateCollection::class,
		Collection\UpdateCardQuantity::class,
		Collection\UserCollections::class,
	];

	foreach ( $types as $type ) {
		( new $type() )->register();
	}
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_endpoints' );
