<?php
/**
 * Plugin Name: GapNext WP
 * Plugin URI:  https://nicolasbossi.com
 * Description: Multi-standard gap analysis audit tool for quality consultants.
 * Version:     1.2.1
 * Author:      Nicolas Bossi
 * Author URI:  https://nicolasbossi.com
 * Text Domain: gapnext-wp
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Constants
define( 'GAPNEXT_WP_VERSION', '1.2.0' );
define( 'GAPNEXT_WP_DIR',     plugin_dir_path( __FILE__ ) );
define( 'GAPNEXT_WP_URL',     plugin_dir_url( __FILE__ ) );
define( 'GAPNEXT_WP_SLUG',    'gapnext-wp' );

// Autoload classes
spl_autoload_register( function( $class ) {
    $prefix = 'GapNext_';
    if ( strpos( $class, $prefix ) !== 0 ) return;
    $name = strtolower( str_replace( [ $prefix, '_' ], [ '', '-' ], $class ) );
    $file = GAPNEXT_WP_DIR . 'includes/class-' . $name . '.php';
    if ( file_exists( $file ) ) require_once $file;
} );

// Activation / deactivation
register_activation_hook( __FILE__, [ 'GapNext_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'GapNext_Installer', 'deactivate' ] );

// Boot
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'gapnext-wp', false, GAPNEXT_WP_SLUG . '/languages' );
    // Run dbDelta if DB version is behind current plugin version
    if ( get_option( 'gapnext_wp_db_version' ) !== GAPNEXT_WP_VERSION ) {
        GapNext_Installer::activate();
    }
    new GapNext_Admin();
    new GapNext_Checklist();
    new GapNext_Results();
    new GapNext_Ajax();
    new GapNext_Draft_Reminder();
    new GapNext_Audit_Manager();
    new GapNext_Client_Role();
    new GapNext_Remediation_Ajax();
} );
