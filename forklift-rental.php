<?php
/**
 * Plugin Name: Forklift Rental Reservation System
 * Plugin URI:  https://yoursite.com
 * Description: Complete forklift rental reservation system with e-signature, PDF generation, payments, and admin order management.
 * Version:     1.0.5
 * Author:      Your Name
 * License:     GPL2
 * Text Domain: forklift-rental
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FRS_VERSION',    '1.0.8' );
define( 'FRS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FRS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FRS_PLUGIN_DIR . 'includes/class-frs-installer.php';
require_once FRS_PLUGIN_DIR . 'includes/class-frs-settings.php';
require_once FRS_PLUGIN_DIR . 'includes/class-frs-order.php';
require_once FRS_PLUGIN_DIR . 'includes/class-frs-pdf.php';
require_once FRS_PLUGIN_DIR . 'includes/class-frs-email.php';
require_once FRS_PLUGIN_DIR . 'includes/class-frs-payment.php';
require_once FRS_PLUGIN_DIR . 'admin/class-frs-admin.php';
require_once FRS_PLUGIN_DIR . 'public/class-frs-public.php';

register_activation_hook( __FILE__, array( 'FRS_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FRS_Installer', 'deactivate' ) );

function frs_init() {
    FRS_Installer::upgrade();
    new FRS_Admin();
    new FRS_Public();
}
add_action( 'plugins_loaded', 'frs_init' );
