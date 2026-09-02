<?php
/**
 * Admin settings page for Pinned Comments.
 *
 * @package PinnedComments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Admin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Add settings page under Settings menu.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Pinned Comments', 'pinned-comments' ),
            __( 'Pinned Comments', 'pinned-comments' ),
            'manage_options',
            'pinned-comments',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'settings_page_pinned-comments' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'pc-admin',
            PC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PC_VERSION
        );

        wp_enqueue_script(
            'pc-admin',
            PC_PLUGIN_URL . 'assets/js/admin.js',
            array(),
            PC_VERSION,
            true
        );

        wp_localize_script( 'pc-admin', 'pcAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pc_admin_nonce' ),
            'i18n'    => array(
                'confirmDeleteAll' => __( 'Are you sure you want to delete ALL pinned comments? This cannot be undone.', 'pinned-comments' ),
                'deleted'          => __( 'All comments deleted.', 'pinned-comments' ),
                'error'            => __( 'An error occurred.', 'pinned-comments' ),
            ),
        ) );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        global $wpdb;
        $table = PC_Database::table_name();
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        ?>
        <div class="wrap pc-admin-wrap">
            <h1><?php echo esc_html__( 'Pinned Comments', 'pinned-comments' ); ?></h1>
            <p class="pc-admin-description"><?php esc_html_e( 'Figma-style pinned comments for your posts and pages.', 'pinned-comments' ); ?></p>

            <div class="pc-admin-card">
                <h2><?php esc_html_e( 'Statistics', 'pinned-comments' ); ?></h2>
                <p>
                    <strong><?php esc_html_e( 'Total comments:', 'pinned-comments' ); ?></strong>
                    <span id="pc-total-count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
                </p>
            </div>

            <div class="pc-admin-card pc-admin-danger">
                <h2><?php esc_html_e( 'Danger Zone', 'pinned-comments' ); ?></h2>
                <p><?php esc_html_e( 'Delete all pinned comments across all posts and pages. This action is irreversible.', 'pinned-comments' ); ?></p>
                <button type="button" id="pc-delete-all-btn" class="button button-danger">
                    <?php esc_html_e( 'Delete All Comments', 'pinned-comments' ); ?>
                </button>
            </div>
        </div>
        <?php
    }
}
