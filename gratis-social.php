<?php
/**
 * Plugin Name: Gratis Social
 * Plugin URI: https://github.com/juslintek/gratis-social
 * Description: Social sharing buttons (Facebook, X, LinkedIn, Pinterest, Email) with inline, floating sidebar and shortcode display, optional share counts and styling controls. Lightweight, no tracking, no third-party JS SDK.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-social
 */

defined('ABSPATH') || exit;

define('GRATIS_SOCIAL_VERSION', '1.0.0');
define('GRATIS_SOCIAL_FILE', __FILE__);
define('GRATIS_SOCIAL_DIR', plugin_dir_path(__FILE__));
define('GRATIS_SOCIAL_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

// Auto-append share buttons to singular content (inline / floating per settings).
add_filter('the_content', [\Gratis\Social\ShareButtons::class, 'appendToContent']);

// Front-end assets.
add_action('wp_enqueue_scripts', [\Gratis\Social\ShareButtons::class, 'enqueueAssets']);

// [gratis_share] shortcode for manual placement.
add_action('init', [\Gratis\Social\ShareButtons::class, 'registerShortcode']);

// Admin settings page.
add_action('admin_menu', [\Gratis\Social\Settings::class, 'registerMenu']);
add_action('admin_init', [\Gratis\Social\Settings::class, 'registerSettings']);
