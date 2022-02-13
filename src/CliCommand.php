<?php
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
			echo $pk_set['code'] . "\t" . $pk_set['name'] . "\t" . $pk_set['ptcgoCode'] . "\n";
		}

		echo "\n\n";

		echo "TCGPlayer:\nID\tName\tCode\n";
		foreach ( $tcg_sets as $tcg_set ) {
			echo $tcg_set->groupId . "\t" . $tcg_set->name . "\t" . $tcg_set->abbreviation . "\n";
		}
	}
}
