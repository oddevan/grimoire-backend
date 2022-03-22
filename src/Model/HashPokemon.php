<?php
/**
 * Model class for Pokémon card info.
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\Model;

/**
 * Model class for Pokémon card info. Based on the hash.
 */
class HashPokemon extends BaseModel {
	const TABLENAME = 'pods_hash_pokemon';

	/**
	 * Get the table name with the current WP prefix
	 *
	 * @return string
	 */
	protected function full_table_name() : string {
		global $wpdb;
		return $wpdb->prefix . self::TABLENAME;
	}

	/**
	 * Initialize a new Card. If a `hash` is passed, it will
	 * be loaded with that card's info from the database.
	 *
	 * @param string $hash Optional; hash value to initialize instance with.
	 */
	public function __construct( string $hash = '' ) {
		$this->data = [
			'hash'         => $hash,
			'name'         => '',
			'permalink'    => '',
			'api_id'       => '',
			'card_name'    => '',
			'supertype'    => '',
			'subtype'      => '',
			'is_standard'  => false,
			'is_expanded'  => false,
			'is_unlimited' => false,
		];

		$this->data_formats = [
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		];

		if ( $hash ) {
			global $wpdb;
			$tablename = $this->full_table_name();

			$db_result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT `id` FROM $tablename WHERE `hash` = %s", //phpcs:ignore
					$hash
				)
			);

			if ( $db_result ) {
				$this->db_id = $db_result;
				$this->load();
			}
		}
	}

	/**
	 * Check if a hash exists with this slug. Different hashs can have
	 * the same title, so we will need to qualify things. If the object
	 * has a db_id, it will exclude itself.
	 *
	 * @param string $permalink Slug to check.
	 * @return boolean true if this slug exists
	 */
	public function check_permalink( string $permalink ) : bool {
		global $wpdb;
		$tablename = $wpdb->prefix . self::TABLENAME;

		$db_result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM $tablename WHERE `permalink` = %s AND `id` <> %d", //phpcs:ignore
				$permalink,
				$this->db_id
			)
		);

		return $db_result && $db_result > 0;
	}
}
