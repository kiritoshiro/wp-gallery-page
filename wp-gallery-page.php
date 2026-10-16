<?php
/**
 * Plugin Name: Page Gallery Grid
 * Plugin URI: https://github.com/kiritoshiro/wp-gallery-page
 * Description: A gallery of selected pages with a big featured page, a YouTube playlist and a latest-posts panel. Use the shortcode [page_gallery].
 * Version: 3.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Adventistai
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/kiritoshiro/wp-gallery-page
 * Text Domain: wp-gallery-page
 */

defined( 'ABSPATH' ) || exit;

define( 'WPGP_VERSION', '3.0.0' );
define( 'WPGP_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-gallery.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-updater.php';

WPGP_Gallery::register();
WPGP_Admin::register();
WPGP_Updater::register();
