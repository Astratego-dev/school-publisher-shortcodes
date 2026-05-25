<?php
/**
 * Plugin Name: School Publisher Shortcodes
 * Description: Book builder and shortcodes for a school-focused publishing website.
 * Version: 0.3.0
 * Author: Astratego
 * Text Domain: school-publisher-shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SPB_VERSION', '0.3.0');
define('SPB_PLUGIN_FILE', __FILE__);
define('SPB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPB_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SPB_PLUGIN_DIR . 'includes/class-spb-plugin.php';

register_activation_hook(__FILE__, array('SPB_Plugin', 'activate'));

add_action('plugins_loaded', array('SPB_Plugin', 'instance'));
