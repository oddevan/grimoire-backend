<?php //phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Class to handle WP_CLI commands
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire;

use Pokemon\Pokemon;
use \WP_CLI;
use \WP_Query;
use \WP_CLI_Command;
use oddEvan\Grimoire\TcgPlayerHelper;

/**
 * Class to handle the WP-CLI commands. May refactor logic out to different class eventually.
 *
 * @since 0.1.0
 */
class CliCommand extends WP_CLI_Command {

	/**
	 * Helper object for accessing the TCGPlayer API
	 *
	 * @var TcgPlayerHelper tcgp_helper Object for accessing TCGPlayer API
	 */
	private $tcgp_helper = false;

	/**
	 * Construct the object
	 *
	 * @author Evan Hildreth
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->tcgp_helper = new TcgPlayerHelper();
	}

	/**
	 * Display all sets from both sources in a tabular data format for easy import
	 * into a spreadsheet program.
	 */
	public function sets_for_spreadsheet() {
		\WP_CLI::log( 'Querying pokemontcg.io...' );

		$pk_sets = Pokemon::Set()->all();

		\WP_CLI::log( 'Querying TCGplayer...' );

		$tcg_sets = $this->tcgp_helper->get_sets();

		echo "\n\n\n";

		echo "Pokemon TCG Developers:\nID\tName\tCode\n";
		foreach ( $pk_sets as $set_obj ) {
			$pk_set = $set_obj->toArray();
			echo $pk_set['id'] . "\t" . $pk_set['name'] . "\t" . $pk_set['ptcgoCode'] . "\n";
		}

		echo "\n\n";

		echo "TCGPlayer:\nID\tName\tCode\n";
		foreach ( $tcg_sets as $tcg_set ) {
			echo $tcg_set['groupId'] . "\t" . $tcg_set['name'] . "\t" . $tcg_set['abbreviation'] . "\n";
		}
	}

	/**
	 * Imports the given sets from TCGplayer
	 *
	 * ## OPTIONS
	 *
	 * <sets>
	 * : One or more API IDs of sets to import.
	 *
	 *
	 * [--set=<set>]
	 * : ID of the set in Grimoire's database. Set must exist and have a prefix.
	 *
	 * [--ptcg=<ptcg>]
	 * : Text prefix to use when inferring an id for PokemonTCG.io
	 *
	 * [--overwrite]
	 * : Include to overwrite any existing data. When a card is brought from the API, its Grimoire ID is generated. If a card with the same ID is already in the database, the data from the API is ignored unless this option is present.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import Fusion Strike
	 *     $ wp grimoire import 2906 --prefix=fus --ptcg=swsh8 --overwrite
	 *
	 * @param array $args Set IDs to import.
	 * @param array $assoc_args Options for this import.
	 */
	public function import( array $args, array $assoc_args ) {
		global $wpdb;

		$overwrite = ! empty( $assoc_args['overwrite'] );
		$set_id    = $assoc_args['set'] ?? '';
		$ptcg_set  = $assoc_args['ptcg'] ?? '';

		$id_prefix = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `card_key` FROM {$wpdb->prefix}pods_set WHERE id = %d",
				$set_id
			)
		);

		if ( ! $id_prefix ) {
			WP_CLI::error( 'Please provide a valid Set ID with the --set argument.' );
		}

		$id_prefix = strtolower( $id_prefix );

		WP_CLI::log( 'TCGplayer set IDs: ' . implode( ', ', $args ) );
		WP_CLI::log( "ID Prefix: $id_prefix  PTCG.io Set: $ptcg_set" );
		WP_CLI::log( 'Duplicate cards will be ' . WP_CLI::colorize( $overwrite ? '%roverwritten%n' : '%gignored%n' ) . '.' );

		foreach ( $args as $set ) {
			WP_CLI::log( "Importing set $set..." );
			$this->import_single_set( $set, $overwrite, $id_prefix, $ptcg_set, $set_id );
		}
	}

	/**
	 * Import the given set from TCGplayer
	 *
	 * @param int    $tcgp_set TCGplayer API ID of the set to import.
	 * @param bool   $overwrite True if duplicate cards should overwrite the existing DB entry.
	 * @param string $id_prefix Prefix to use for creating the Grimoire ID.
	 * @param string $ptcg_set Prefix to use for inferring the PokemonTCG.io ID.
	 * @param int    $set_id ID of the Set in the database.
	 */
	private function import_single_set( int $tcgp_set, bool $overwrite, string $id_prefix, string $ptcg_set, int $set_id ) {
		$quantity = 100;
		$offset   = 0;
		$cards    = $this->tcgp_helper->get_cards_from_set( $tcgp_set, $quantity, $offset );

		while ( ! empty( $cards ) ) {
			foreach ( $cards as $card ) {
				$card_info   = $this->parse_tcg_card_info( $card );
				$card_number = $card_info['card_number'] ?? '0';
				$grimoire_id = "pkm-$id_prefix-" . $card_number . $this->id_extra( $card );
				$db_id       = $this->get_db_id( $grimoire_id );

				if ( ! $card_number || $card_number === '0' ) {
					continue;
				}

				if ( $overwrite || ! $db_id ) {
					$hash_data = wp_json_encode(
						[
							'name' => $card['cleanName'] ?? $card['name'],
							'type' => $card_info['type'] ?? '',
							'data' => $card_info['attacks'] ?? $card['text'] ?? '',
						],
						JSON_PRETTY_PRINT
					);

					$to_load = [
						'grimoire_id'   => $grimoire_id,
						'card_title'    => $card['name'],
						'tcgplayer_sku' => $card_info['sku'],
						'hash_data'     => $hash_data,
						'hash'          => md5( $hash_data ),
						'set_id'        => $set_id,
						'img_url'       => $card['imageUrl'],
						'sequence'      => $this->get_sequence( $card_info['card_number'] ?? '0' ),
					];
					$result  = $this->import_card( $db_id, $to_load, [ '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ] );
					if ( $result === false ) {
						WP_CLI::error( 'Error importing ' . $to_load['card_title'] );
					}
					WP_CLI::success( 'Imported ' . $to_load['card_title'] );

					if ( ! empty( $card_info['parallel_sku'] ) ) {
						$to_load['grimoire_id']   = $grimoire_id . '-r';
						$to_load['card_title']    = $card['name'] . ' [Reverse Holo]';
						$to_load['tcgplayer_sku'] = $card_info['parallel_sku'];
						$to_load['sequence']      = $to_load['sequence'] . 'r';

						$result = $this->import_card( $db_id, $to_load, [ '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ] );
						if ( $result === false ) {
							WP_CLI::error( 'Error importing ' . $to_load['card_title'] );
						}
						WP_CLI::success( 'Imported ' . $to_load['card_title'] );
					}
				}
			}

			$offset += $quantity;
			$cards   = $this->tcgp_helper->get_cards_from_set( $tcgp_set, $quantity, $offset );
		}
	}

	/**
	 * Import the given card to the database.
	 *
	 * @param integer $db_id If supplied and not falsy, will update the given database ID.
	 * @param array   $args Array of [column => value] pairs for passing to $wpdb.
	 * @param array   $arg_formats Array of format strings corresponding to $args.
	 * @return int|bool Returns false if there was an error, otherwise number of rows affected.
	 */
	private function import_card( int $db_id, array $args, array $arg_formats ) {
		global $wpdb;
		$wpdb->show_errors();

		if ( ! isset( $args['modified'] ) ) {
			$args['modified'] = gmdate( DATE_RFC3339 );
			$arg_formats[]    = '%s';
		}

		if ( $db_id ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'pods_card',
				$args,
				[ 'id' => $db_id ],
				$arg_formats,
				[ '%d' ]
			);
			$wpdb->hide_errors();
			return $result;
		}

		if ( ! isset( $args['created'] ) ) {
			$args['created'] = gmdate( DATE_RFC3339 );
			$arg_formats[]   = '%s';
		}

		$result = $wpdb->insert( $wpdb->prefix . 'pods_card', $args, $arg_formats );
		$wpdb->hide_errors();
		return $result;
	}

	/**
	 * Get the cleaned card number from the TCGplayer string. Removes any leading zeros
	 * and total numbers. E.g. '001/134' => '1'; 'SWSH042' => 'swsh42'
	 *
	 * @param string $raw_card_number Card number from the TCGplayer API.
	 * @return string Formatted card number.
	 */
	private function get_card_number( string $raw_card_number ) : string {
		$card_number    = $this->get_sequence( $raw_card_number );
		$number_matches = [];
		if ( preg_match( '/([a-z]+)0+([1-9]+)/', strtolower( $card_number ), $number_matches ) ) {
			$card_number = $number_matches[1] . $number_matches[2];
		}
		if ( strpos( $card_number, '0' ) === 0 ) {
			$card_number = ltrim( $card_number, '0' );
		}
		return $card_number ?? '0';
	}

	/**
	 * Get a minimally processed card number for ordering cards in a set. Removes total numbers
	 * and converts to lowercase.
	 *
	 * @param string $raw_card_number Card number from the TCGplayer API.
	 * @return string Formatted card number.
	 */
	private function get_sequence( string $raw_card_number ) : string {
		$card_number = strtolower( $raw_card_number );
		if ( strpos( $card_number, '/' ) > 0 ) {
			$card_number = substr( $card_number, 0, strpos( $card_number, '/' ) );
		}
		return $card_number ?? '0';
	}

	/**
	 * Parse the Extended Data from TCGPlayer
	 *
	 * @param array $tcgp_card Parsed JSON from TCGPlayer.
	 * @return array parsed Extended Data
	 */
	private function parse_tcg_card_info( $tcgp_card ) : array {
		$card_info = [];

		foreach ( $tcgp_card['extendedData'] as $edat ) {
			switch ( $edat['name'] ) {
				case 'Number':
					$card_info['card_number'] = $this->get_card_number( $edat['value'] );
					break;
				case 'Attack 1':
				case 'Attack 2':
				case 'Attack 3':
				case 'Attack 4':
					$card_info['attacks'][] = $this->parse_attack( $edat['value'] );
					break;
				case 'Card Type':
					$card_info['type'] = $edat['value'];
					break;
				case 'CardText':
					$card_info['text'] = $edat['value'];
					break;
			}
		}

		$printings = array_filter(
			$tcgp_card['skus'],
			function( $value ) {
				return 1 === $value['languageId'] && 1 === $value['conditionId'];
			}
		);
		foreach ( $printings as $sku ) {
			if ( 77 === $sku['printingId'] ) {
				$card_info['parallel_sku'] = $sku['skuId'];
			} else {
				$card_info['sku'] = $sku['skuId'];
			}
		}

		return $card_info;
	}

	/**
	 * Get attack info from the text from TCGPlayer.
	 *
	 * @param string $raw_text Text from TCGPlayer's API to be parsed.
	 * @return array Parsed data from the text.
	 */
	private function parse_attack( $raw_text ) {
		$stripped_text = wp_strip_all_tags( $raw_text );
		$matches       = [];
		$text          = '';

		preg_match( '/\[([0-9A-Z]+)\+?\]\s((\w+\s)+)(\(([0-9x]+)\+?\))?/', $stripped_text, $matches );

		if ( strpos( $stripped_text, "\n" ) > 0 ) {
			$text = substr( $stripped_text, strpos( $stripped_text, "\n" ) + 1 );
		}

		return [
			'cost'        => $matches[1] ?? 0,
			'name'        => $matches[2] ?? '',
			'base_damage' => $matches[5] ?? 0,
		];
	}

	/**
	 * Get the database ID field for the given Grimoire ID; 0 if not found.
	 *
	 * @param string $grimoire_id Grimoire ID to search.
	 * @return integer 0 or database ID.
	 */
	private function get_db_id( string $grimoire_id ) : int {
		global $wpdb;

		$db_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}pods_card WHERE `grimoire_id` = %s",
				$grimoire_id
			)
		);

		return $db_id ?? 0;
	}

	/**
	 * Add any extra flourishes to the card ID based on any attributes
	 *
	 * @param array $card Card object from TCGplayer.
	 * @return string Anything extra to add to the id
	 */
	private function id_extra( $card ) : string {
		if ( str_ends_with( $card['name'], ' [Staff]' ) ) {
			return '-s';
		}

		return '';
	}
}
