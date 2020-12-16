<?php
/**
 * Remove unused CSS and reduce the total web page load time. You can add the clean CSS manually or use the PurifyCSS API.
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.webperftools.com/purifycss/purifycss-wordpress-plugin/
 * @since             1.0.0
 * @package           Purifycss
 *
 * @wordpress-plugin
 * Plugin Name:       PurifyCSS
 * Plugin URI:        https://www.webperftools.com/purifycss/purifycss-wordpress-plugin/
 * Description:       Remove unused CSS and reduce the total web page load time. You can add the clean CSS manually or use the PurifyCSS API.
 * Version:           1.0.4
 * Author:            Webperftools
 * Author URI:        https://www.webperftools.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       purifycss
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PURIFYCSS_VERSION', '1.0.4' );

require plugin_dir_path( __FILE__ ) . 'includes/Purifycss.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_purifycss() {

	$plugin = new Purifycss();
	$plugin->run();
	

}
run_purifycss();

function plugin_settings_link($links) {
	$settings_link = '<a href="options-general.php?page=purifycss-plugin">'.__('Settings').'</a>'; 
	array_unshift( $links, $settings_link ); 
	return $links; 
}

$plugin_file = plugin_basename(__FILE__); 
add_filter( "plugin_action_links_$plugin_file", 'plugin_settings_link' );
