<?php
/**
 * WordPress MCP Adapter
 *
 * Tích hợp vào GenSeo SEO Helper — không phải plugin độc lập.
 *
 * @package     mcp-adapter
 * @author      WordPress.org Contributors
 * @copyright   2025 Plugin Contributors
 * @license     GPL-2.0-or-later
 */

declare (strict_types = 1);

namespace WP\MCP;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit();

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * Shortcut constant to the path of this file.
	 */
	define( 'WP_MCP_DIR', plugin_dir_path( __FILE__ ) );

	/**
	 * Version of the plugin.
	 */
	define( 'WP_MCP_VERSION', '0.4.1' );
}

constants();
require_once __DIR__ . '/includes/Autoloader.php';

// If autoloader failed, we cannot proceed.
if ( ! Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( Plugin::class ) ) {
	Plugin::instance();
}
