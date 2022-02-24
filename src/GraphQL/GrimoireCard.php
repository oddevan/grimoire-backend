<?php
/**
 * Card type in GraphQL
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\GraphQL;

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
		'id'   => [
			'type'        => 'String',
			'description' => 'Grimoire ID for this card',
		],
		'name' => [
			'type'        => 'String',
			'description' => 'Name of the card',
		],
		'sku'  => [
			'type'        => 'Int',
			'description' => 'TCGplayer SKU for this particular card',
		],
		'hash' => [
			'type'        => 'String',
			'description' => 'MD5 hash of the card\'s distinctive attributes. Used to identify other printings.',
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
				'resolve'     => [ $this, 'resolve_index_field' ],
			]
		);
	}

	/**
	 * Resolver for the index field
	 *
	 * @return array
	 */
	public function resolve_index_field() {
		global $wpdb;

		$result = $wpdb->get_results(
			"SELECT
				`grimoire_id` as `id`,
				`card_title` as `name`,
				`tcgplayer_sku` as `sku`,
				`hash`
			FROM {$wpdb->prefix}pods_card;",
			ARRAY_A
		);

		return $result;
	}
}
