<?php
/**
 * Register GraphQL-related classes and their hooks.
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\GraphQL;

/**
 * Loop through and register the GraphQL types
 */
function register_types() {
	$types = [
		GrimoireCard::class,
		GrimoireCollection::class,
	];

	foreach ( $types as $type ) {
		( new $type() )->register();
	}
}
add_action( 'graphql_register_types', __NAMESPACE__ . '\register_types' );
