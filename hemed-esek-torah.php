<?php
/**
 * Plugin Name: Hemed Esek Torah
 * Plugin URI: https://hemed.chepti.com/
 * Description: Loader for the Hemed Esek Torah plugin folder.
 * Version: 1.0.0
 * Author: Chepti
 * Text Domain: hemed-esek-torah
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hemed_esek_torah_plugin_file = __DIR__ . '/hemed-esek-torah/hemed-esek-torah.php';

if ( file_exists( $hemed_esek_torah_plugin_file ) ) {
	require_once $hemed_esek_torah_plugin_file;
}
