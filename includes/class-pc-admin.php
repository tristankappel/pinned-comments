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
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'pc_settings_group',
            'pc_content_width',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_content_width' ),
                'default'           => 800,
            )
        );

        register_setting(
            'pc_settings_group',
            'pc_mobile_width',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_mobile_width' ),
                'default'           => 390,
            )
        );

        register_setting(
            'pc_settings_group',
            'pc_accent_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_accent_color' ),
                'default'           => PC_Frontend::DEFAULT_ACCENT,
            )
        );
    }

    /**
     * Sanitize the accent color, falling back to the default.
     *
     * @param mixed $value Raw input.
     * @return string
     */
    public function sanitize_accent_color( $value ) {
        $color = sanitize_hex_color( (string) $value );
        return $color ? $color : PC_Frontend::DEFAULT_ACCENT;
    }

    /**
     * Sanitize the content width value.
     *
     * @param mixed $value Raw input.
     * @return int
     */
    public function sanitize_content_width( $value ) {
        $value = absint( $value );
        if ( $value < 200 ) {
            $value = 200;
        }
        if ( $value > 2000 ) {
            $value = 2000;
        }
        return $value;
    }

    /**
     * Sanitize the mobile wrapper width value.
     *
     * @param mixed $value Raw input.
     * @return int
     */
    public function sanitize_mobile_width( $value ) {
        $value = absint( $value );
        if ( $value < 200 ) {
            $value = 200;
        }
        if ( $value > 1200 ) {
            $value = 1200;
        }
        return $value;
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
            array( 'wp-color-picker' ),
            PC_VERSION
        );

        wp_enqueue_script(
            'pc-admin',
            PC_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-color-picker' ),
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
                <h2><?php esc_html_e( 'Appearance', 'pinned-comments' ); ?></h2>
                <p><?php esc_html_e( 'In Comment Mode the page is rendered inside a frame with a fixed viewport width, so the layout – and therefore every pin position – is always identical. Desktop and mobile each have their own wrapper width and their own set of comments.', 'pinned-comments' ); ?></p>
                <form method="post" action="options.php">
                    <?php settings_fields( 'pc_settings_group' ); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="pc_content_width"><?php esc_html_e( 'Desktop Wrapper Width (px)', 'pinned-comments' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="pc_content_width" name="pc_content_width"
                                    value="<?php echo esc_attr( get_option( 'pc_content_width', 800 ) ); ?>"
                                    min="200" max="2000" step="10" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Recommended: 1000–1440. The desktop frame always keeps this exact viewport width; smaller screens scroll horizontally instead of shrinking it.', 'pinned-comments' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="pc_mobile_width"><?php esc_html_e( 'Mobile Wrapper Width (px)', 'pinned-comments' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="pc_mobile_width" name="pc_mobile_width"
                                    value="<?php echo esc_attr( get_option( 'pc_mobile_width', 390 ) ); ?>"
                                    min="200" max="1200" step="10" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Recommended: 320–480. The mobile frame uses this exact viewport width, so the theme renders its real mobile layout.', 'pinned-comments' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="pc_accent_color"><?php esc_html_e( 'Accent Color', 'pinned-comments' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="pc_accent_color" name="pc_accent_color" class="pc-color-picker"
                                    value="<?php echo esc_attr( get_option( 'pc_accent_color', PC_Frontend::DEFAULT_ACCENT ) ); ?>"
                                    data-default-color="<?php echo esc_attr( PC_Frontend::DEFAULT_ACCENT ); ?>" />
                                <p class="description"><?php esc_html_e( 'Used for pins, buttons, links and focus states in the frontend. The hover state is derived automatically.', 'pinned-comments' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Save Settings', 'pinned-comments' ) ); ?>
                </form>
            </div>

            <div class="pc-admin-card">
                <h2><?php esc_html_e( 'Statistics', 'pinned-comments' ); ?></h2>
                <p>
                    <strong><?php esc_html_e( 'Total comments:', 'pinned-comments' ); ?></strong>
                    <span id="pc-total-count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
                </p>
                <p>
                    <strong><?php esc_html_e( 'Desktop:', 'pinned-comments' ); ?></strong>
                    <span id="pc-desktop-count"><?php echo esc_html( number_format_i18n( PC_Database::count_by_viewport( 'desktop' ) ) ); ?></span>
                    &nbsp;&nbsp;
                    <strong><?php esc_html_e( 'Mobile:', 'pinned-comments' ); ?></strong>
                    <span id="pc-mobile-count"><?php echo esc_html( number_format_i18n( PC_Database::count_by_viewport( 'mobile' ) ) ); ?></span>
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
