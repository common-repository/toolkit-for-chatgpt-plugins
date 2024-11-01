<?php
/**
 * Plugin Name: Toolkit for ChatGPT Plugins
 * Plugin URI: https://www.dcsdigital.co.uk/
 * Description: Easily add your WordPress and WooCommerce website to ChatGPT's plugin directory with our Toolkit for ChatGPT Plugins. Brought to you by DCS Digital, an approved WooExpert company.
 * Version: 1.0.0
 * Author: DCS Digital
 * Author URI: http://www.dcsdigital.co.uk/
 * Text Domain: toolkit-for-chatgpt-plugins
 * WC requires at least: 6.9.4
 * WC tested up to: 7.5.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'includes/rest-endpoints.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin.php';

// Add a settings link on the Plugin page
$basename = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_{$basename}", 'toolkit_for_chatgpt_plugins_add_settings_link' );

function toolkit_for_chatgpt_plugins_add_settings_link( $links ) {
    // Define the URL for the settings page.
    $settings_url = admin_url( 'options-general.php?page=toolkit-for-chatgpt-plugins' );
    
    // Create the "Settings" link.
    $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'my-plugin' ) . '</a>';
    
    // Add the "Settings" link to the beginning of the array of links.
    array_unshift( $links, $settings_link );
    
    return $links;
}