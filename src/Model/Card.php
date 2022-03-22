<?php
/**
 * Model class for Grimoire Cards
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\Model;

/**
 * Model class for Grimoire Cards
 */
class Card extends BaseModel {
	const TABLENAME = 'pods_card';

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
	 * Initialize a new Card. If a `grimoire_id` is passed, it will
	 * be loaded with that card's info from the database.
	 *
	 * @param string $grimoire_id Optional; Grimoire ID to initialize instance with.
	 */
	public function __construct( string $grimoire_id = '' ) {
		$this->data = [
			'grimoire_id'   => $grimoire_id,
			'card_title'    => '',
			'tcgplayer_sku' => '',
			'ptcg_id'       => '',
			'hash'          => '',
			'hash_data'     => '',
			'set_id'        => '',
			'img_url'       => '',
		];

		$this->data_formats = [
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
		];

		if ( $grimoire_id ) {
			global $wpdb;
			$tablename = $this->full_table_name();

			$db_result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT `id` FROM $tablename WHERE `grimoire_id` = %s", //phpcs:ignore
					$grimoire_id
				)
			);

			if ( $db_result ) {
				$this->db_id = $db_result;
				$this->load();
			}
		}
	}
}
