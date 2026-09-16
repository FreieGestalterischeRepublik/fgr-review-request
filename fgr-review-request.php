<?php
/**
 * Plugin Name:  FGR Review Request
 * Description:  Ein Plugin der Freien Gestalterischen Republik. Verschickt nach abgeschlossenen Kochkursen automatisch eine Erinnerungs-E-Mail mit Bitte um eine Google-Bewertung.
 * Version:      1.0.0
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain:  fgr-review-request
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_RR_VERSION', '1.0.0' );
define( 'FGR_RR_PATH', plugin_dir_path( __FILE__ ) );

require_once FGR_RR_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
$fgr_review_request_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/FreieGestalterischeRepublik/fgr-review-request/',
	__FILE__,
	'fgr-review-request'
);
$fgr_review_request_updater->setBranch( 'main' );
$fgr_review_request_updater->getVcsApi()->enableReleaseAssets();

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
