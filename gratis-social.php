<?php
/**
 * Plugin Name: Gratis Social
 * Description: Social sharing buttons + follow links. Lightweight, no tracking. Replaces Social Warfare ($29/yr).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 * Text Domain: gratis-social
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';

add_filter('the_content', [\Gratis\Social\ShareButtons::class, 'appendToContent']);
add_action('wp_head', [\Gratis\Social\ShareButtons::class, 'inlineCSS']);
