<?php
/**
 * Entry point for Grimoire custom code.
 *
 * @package oddevan\Grimoire
 */

namespace oddEvan\Grimoire;

$grimoire_config_file = __DIR__ . '/grimoire-config.php';
if ( file_exists( $grimoire_config_file ) ) {
	include_once $grimoire_config_file;
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
