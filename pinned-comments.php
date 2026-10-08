<?php
/**
 * Plugin Name:       Pinned Comments
 * Plugin URI:        https://tristankappel.com
 * Description:       Figma-style pinned comments for posts and pages. Enter comment mode, click anywhere on the content, and drop a pin with a comment thread.
 * Version:           1.3.1
 * Author:            Tristan Kappel
 * Author URI:        https://tristankappel.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pinned-comments
 * Domain Path:       /languages
 *
 * @package PinnedComments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PC_VERSION', '1.3.1' );
define( 'PC_PLUGIN_FILE', __FILE__ );
define( 'PC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once PC_PLUGIN_DIR . 'includes/class-pc-uploads.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-database.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-activator.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-deactivator.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-admin.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-frontend.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-ajax.php';

/**
 * Activation hook.
 */
function pc_activate() {
    PC_Activator::activate();
}
register_activation_hook( __FILE__, 'pc_activate' );

/**
 * Deactivation hook.
 */
function pc_deactivate() {
    PC_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'pc_deactivate' );

/**
 * Init plugin classes.
 */
function pc_init() {
    PC_Database::instance();
    PC_Database::maybe_upgrade();
    PC_Admin::instance();
    PC_Frontend::instance();
    PC_Ajax::instance();
}
add_action( 'plugins_loaded', 'pc_init' );
