<?php
/**
 * Plugin Name: Optimization Detective Intrinsic Dimensions
 * Plugin URI: https://github.com/westonruter/od-intrinsic-dimensions
 * Description: Supplies width and height attributes to IMG and VIDEO tags that lack them according to their intrinsic dimensions. This reduces Cumulative Layout Shift (CLS).
 * Requires at least: 6.5
 * Requires PHP: 7.2
 * Requires Plugins: optimization-detective
 * Version: 0.3.0
 * Author: Weston Ruter
 * Author URI: https://weston.ruter.net/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: od-intrinsic-dimensions
 * Update URI: https://github.com/westonruter/od-intrinsic-dimensions
 * GitHub Plugin URI: https://github.com/westonruter/od-intrinsic-dimensions
 *
 * @package od-intrinsic-dimensions
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

const OD_INTRINSIC_DIMENSIONS_VERSION = '0.2.0';

add_action(
	'od_init',
	static function ( string $optimization_detective_version ): void {
		$required_od_version = '1.0.0-beta4';
		if ( ! version_compare( $optimization_detective_version, $required_od_version, '>=' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					global $pagenow;
					if ( ! in_array( $pagenow, array( 'index.php', 'plugins.php' ), true ) ) {
						return;
					}
					wp_admin_notice(
						esc_html(
							sprintf(
								/* translators: %s is plugin name */
								__( 'The %s plugin requires a newer version of the Optimization Detective plugin. Please update your plugins.', 'od-intrinsic-dimensions' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
								plugin_basename( __FILE__ )
							)
						),
						array( 'type' => 'warning' )
					);
				}
			);
			return;
		}

		require_once __DIR__ . '/helper.php';

		add_action( 'od_register_tag_visitors', 'odid_register_tag_visitor' );
		add_filter( 'od_extension_module_urls', 'odid_filter_extension_module_urls' );
		add_filter( 'od_url_metric_schema_element_item_additional_properties', 'odid_add_element_item_schema_properties' );
	}
);
