<?php
/**
 * Plugin Name:  FGR Review Request
 * Description:  Ein Plugin der Freien Gestalterischen Republik. Verschickt nach abgeschlossenen Kochkursen automatisch eine Erinnerungs-E-Mail mit Bitte um eine Google-Bewertung.
 * Version:      1.0.1
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain:  fgr-review-request
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_RR_VERSION', '1.0.1' );
define( 'FGR_RR_PATH', plugin_dir_path( __FILE__ ) );

// Update-Checker: fragt die zentrale FGR-Update-API ab (nicht direkt GitHub,
// wegen des GitHub-API-Rate-Limits bei vielen Kundenseiten auf derselben IP).
require_once FGR_RR_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
$fgr_review_request_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://fgr-plugins-api.fgr.design/fgr-review-request.json',
	__FILE__,
	'fgr-review-request'
);

// Auto-Update: WordPress' täglicher Update-Cron installiert neue Versionen
// dieses Plugins automatisch, kein manueller Klick auf jeder Seite nötig.
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	if ( isset( $item->slug ) && $item->slug === 'fgr-review-request' ) {
		return true;
	}
	return $update;
}, 10, 2 );

require_once FGR_RR_PATH . 'includes/class-fgr-rr-settings.php';
require_once FGR_RR_PATH . 'includes/class-fgr-rr-core.php';

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		new FGR_RR_Settings();
		new FGR_RR_Core();
	}
);
