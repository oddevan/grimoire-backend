<?php
/**
 * Card type in GraphQL
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\GraphQL;

use WPGraphQL\AppContext;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class to represent a Card in GraphQL
 */
class GrimoireCard {
	const TYPENAME = 'GrimoireCard';

	/**
	 * GraphQL Description of the object
	 *
	 * @var string
	 */
	protected $description = 'A card in the Grimoire database.';

	/**
	 * Definition of the fields on this object
	 *
	 * @var array
	 */
	protected $fields = [
		'id'        => [
			'type'        => 'String',
			'description' => 'Grimoire ID for this card',
		],
		'name'      => [
			'type'        => 'String',
			'description' => 'Name of the card',
		],
		'sku'       => [
			'type'        => 'Int',
			'description' => 'TCGplayer SKU for this particular card',
		],
		'hash'      => [
			'type'        => 'String',
			'description' => 'MD5 hash of the card\'s distinctive attributes. Used to identify other printings.',
		],
		'guruId'    => [
			'type'        => 'String',
			'description' => 'ID for PokemonTCG.io',
		],
		'printings' => [
			'type'        => [ 'list_of' => self::TYPENAME ],
			'description' => 'Other printings of this card',
		],
		'setName'   => [
			'type'        => 'String',
			'description' => 'Name of set this card belongs to',
		],
		'setSlug'   => [
			'type'        => 'String',
			'description' => 'Slug for set this card belongs to',
		],
		'imgUrl'    => [
			'type'        => 'String',
			'description' => 'URL to an image of this card',
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
			'card',
			[
				'description' => 'Cards in the Grimoire database',
				'type'        => [ 'list_of' => self::TYPENAME ],
				'args'        => [
					'grimoireId' => [
						'type'        => 'String',
						'description' => 'ID of a specific card',
					],
				],
				'resolve'     => [ $this, 'resolve_index_field' ],
			]
		);
	}

	/**
	 * Resolve the `card` field in GraphQL
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

		$base_query = "SELECT
			`card`.`grimoire_id` as `id`,
			`card`.`card_title` as `name`,
			`card`.`tcgplayer_sku` as `sku`,
			`card`.`hash`,
			`card`.`ptcg_id` as `guruId`,
			`card`.`img_url` as `imgUrl`,
			`set`.`name` as `setName`,
			`set`.`permalink` as `setSlug`
		FROM {$wpdb->prefix}pods_card AS `card`
			INNER JOIN {$wpdb->prefix}pods_set AS `set` ON `set`.`id` = `card`.`set_id`";

		if ( empty( $args['grimoireId'] ) ) {
			$results = $wpdb->get_results( $base_query, ARRAY_A ); //phpcs:ignore
			return array_map(
				function( $card ) {
					$card['printings'] = [];
					return $card;
				},
				$results
			);
		}

		$results = $wpdb->get_results(
			$wpdb->prepare( $base_query . "\nWHERE `grimoire_id` = %s", $args['grimoireId'] ), //phpcs:ignore
			ARRAY_A,
		);

		if ( empty( $results ) ) {
			return [];
		}

		$results[0]['printings'] = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					`grimoire_id` as `id`,
					`card_title` as `name`,
					`tcgplayer_sku` as `sku`,
					`hash`,
					`ptcg_id` as `guruId`,
					`card`.`img_url` as `imgUrl`,
					`set`.`name` as `setName`,
					`set`.`permalink` as `setSlug`
				FROM {$wpdb->prefix}pods_card AS `card`
					INNER JOIN {$wpdb->prefix}pods_set AS `set` ON `set`.`id` = `card`.`set_id`
				WHERE `hash` = %s AND `grimoire_id` <> %s",
				$results[0]['hash'],
				$results[0]['id']
			),
			ARRAY_A
		);

		error_log( 'GrimoireCollection: ' . print_r( [ $root, $args, $context, $info->fieldDefinition, $info->fieldName, $info->returnType ], true ) );

		return $results;
	}
}
