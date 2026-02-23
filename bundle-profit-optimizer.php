<?php
/**
 * Plugin Name: Bundle Profit Optimizer
 * Description: Recommends WooCommerce bundle pricing to maximize expected margin.
 * Version: 1.0.0
 * Author: Rashed Hossain
 * Author URI: https://rashed.im/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 * Text Domain: bundle-profit-optimizer
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('BPO_VERSION', '1.0.0');
define('BPO_PLUGIN_FILE', __FILE__);
define('BPO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BPO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BPO_TEXT_DOMAIN', 'bundle-profit-optimizer');

require_once BPO_PLUGIN_DIR . 'includes/class-bpo-optimizer.php';
require_once BPO_PLUGIN_DIR . 'includes/class-bpo-admin.php';
require_once BPO_PLUGIN_DIR . 'includes/class-bpo-plugin.php';

add_action('plugins_loaded', static function () {
    BPO_Plugin::instance()->bootstrap();
});
