<?php
/**
 * Entry point for Grimoire custom code.
 *
 * @package oddevan\Grimoire
 */

namespace oddEvan\Grimoire;

use Pokemon\Pokemon;

$grimoire_config_file = __DIR__ . '/grimoire-config.php';
if ( file_exists( $grimoire_config_file ) ) {
	include_once $grimoire_config_file;
}

if ( ! class_exists( 'oddEvan\Grimoire\CliCommand' ) && file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( defined( 'POKEMONTCG_IO_KEY' ) ) {
	Pokemon::Options( [ 'verify' => false ] );
	Pokemon::ApiKey( POKEMONTCG_IO_KEY );
}

add_filter(
	'pods_admin_capabilities',
	function ( $pods_admin_capabilities, $cap ) {
		$pods_admin_capabilities[] = 'administrator';
		return $pods_admin_capabilities;
	},
	10,
	2
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'grimoire', new CliCommand() );
}

/**
 * Filter card save in the GUI to calculate the hash
 *
 * @param array $pieces Card to modify.
 * @param bool  $is_new_item Ignored; we want this to run every time.
 * @return array Modified card
 */
function set_card_hash( $pieces, $is_new_item ) {
	$hash_data = $pieces['fields']['hash_data']->get_field_value();

	$pieces['fields']['hash']->set_field_value( md5( $hash_data . '' ) );
	error_log( print_r( $pieces['fields'], true ) );
	return $pieces;
}
add_filter( 'pods_api_pre_save_pod_item_card', __NAMESPACE__ . '\set_card_hash', 10, 2 );

require_once __DIR__ . '/src/GraphQL/index.php';
