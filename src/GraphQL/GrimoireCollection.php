<?php
/**
 * Collection type in GraphQL
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\GraphQL;

use WPGraphQL\AppContext;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class to represent a Collection in GraphQL
 */
class GrimoireCollection {
	const TYPENAME = 'GrimoireCollection';

	/**
	 * GraphQL Description of the object
	 *
	 * @var string
	 */
	protected $description = 'A collection in the Grimoire database.';

	/**
	 * Definition of the fields on this object
	 *
	 * @var array
	 */
	protected $fields = [
		'id'        => [
			'type'        => 'Int',
			'description' => 'ID for this collection',
		],
		'name'      => [
			'type'        => 'String',
			'description' => 'Name of the collection',
		],
		'is_public' => [
			'type'        => 'Boolean',
			'description' => 'True if collection is visible to the public',
		],
		'hashes'    => [
			'type'        => [ 'list_of' => 'GrimoireCollectionHashLineItem' ],
			'description' => 'Any hashes belonging to this collection',
		],
		'cards'     => [
			'type'        => [ 'list_of' => 'GrimoireCollectionCardLineItem' ],
			'description' => 'Any cards belonging to this collection',
		],
	];

	/**
	 * Register the type and index field
	 */
	public function register() {
		$this->register_type();
		$this->register_index_field();
	}

	/**
	 * Register the type with GraphQL
	 */
	protected function register_type() {
		register_graphql_object_type(
			'GrimoireCollectionHashLineItem',
			[
				'description' => 'Line item linking a hash and a collection',
				'fields'      => [
					'hash'     => [
						'type'        => 'String',
						'description' => 'Hash that is part of the collection',
					],
					'quantity' => [
						'type'        => 'Int',
						'description' => 'Quantity for this line item',
					],
				],
			]
		);
		register_graphql_object_type(
			'GrimoireCollectionCardLineItem',
			[
				'description' => 'Line item linking a card and a collection',
				'fields'      => [
					'card'     => [
						'type'        => GrimoireCard::TYPENAME,
						'description' => 'Card that is part of the collection',
					],
					'quantity' => [
						'type'        => 'Int',
						'description' => 'Quantity for this line item',
					],
				],
			]
		);

		register_graphql_object_type(
			self::TYPENAME,
			[
				'description' => $this->description,
				'fields'      => $this->fields,
			]
		);
	}

	/**
	 * Register and resolve the index field
	 */
	protected function register_index_field() {
		register_graphql_field(
			'RootQuery',
			'collection',
			[
				'description' => 'Collections in the Grimoire database',
				'type'        => [ 'list_of' => self::TYPENAME ],
				'args'        => [
					'id' => [
						'type'        => 'Int',
						'description' => 'ID of a specific collection',
					],
				],
				'resolve'     => [ $this, 'resolve_index_field' ],
			]
		);
	}

	/**
	 * Resolve the `collection` field in GraphQL
	 *
	 * @see https://www.wpgraphql.com/docs/graphql-resolvers/#resolver-arguments
	 *
	 * @param mixed       $root Root object of the GQL query.
	 * @param array       $args Arguments provided to the field.
	 * @param AppContext  $context AppContext for the query.
	 * @param ResolveInfo $info Info about the current state of the resolve tree.
	 * @return array Results of the database call.
	 */
	public function resolve_index_field( $root, array $args, AppContext $context, ResolveInfo $info ) {
		global $wpdb;

		$query = "SELECT
			`id`,
			`name`
		FROM {$wpdb->prefix}pods_collection";

		if ( ! empty( $args['id'] ) ) {
			$query = $wpdb->prepare( $query . "\nWHERE `id` = %s", $args['id'] ); //phpcs:ignore
		}

		$results = $wpdb->get_results(
			$query, //phpcs:ignore
			ARRAY_A,
		);

		if ( empty( $results ) ) {
			return [];
		}

		error_log( 'GrimoireCollection: ' . print_r( [ $root, $args, $context, $info->fieldDefinition, $info->fieldName, $info->returnType ], true ) );

		return $results;
	}
}
