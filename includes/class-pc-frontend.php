<?php
/**
 * Frontend functionality for Pinned Comments.
 *
 * @package PinnedComments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Frontend {

    /**
     * Default accent color.
     */
    const DEFAULT_ACCENT = '#1a73e8';

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_comment_mode_ui' ) );

        if ( self::is_frame_request() ) {
            add_filter( 'show_admin_bar', '__return_false' );
            add_filter( 'body_class', array( $this, 'add_frame_body_class' ) );
            add_action( 'template_redirect', 'nocache_headers' );
        }
    }

    /**
     * Whether the current request renders the page inside the comment mode frame.
     *
     * @return bool
     */
    public static function is_frame_request() {
        return isset( $_GET['pc_frame'] ) && '1' === $_GET['pc_frame'];
    }

    /**
     * Add a body class to frame requests.
     *
     * @param array $classes Body classes.
     * @return array
     */
    public function add_frame_body_class( $classes ) {
        $classes[] = 'pc-frame';
        return $classes;
    }

    /**
     * The configured accent color.
     *
     * @return string
     */
    public static function accent_color() {
        $color = sanitize_hex_color( (string) get_option( 'pc_accent_color', self::DEFAULT_ACCENT ) );
        return $color ? $color : self::DEFAULT_ACCENT;
    }

    /**
     * Darken a hex color, used for hover states.
     *
     * @param string $hex    Hex color.
     * @param float  $factor Multiplier below 1 darkens.
     * @return string
     */
    public static function shade_color( $hex, $factor = 0.78 ) {
        $hex = ltrim( (string) $hex, '#' );

        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
            return self::DEFAULT_ACCENT;
        }

        $parts = array();
        foreach ( array( 0, 2, 4 ) as $offset ) {
            $value   = (int) round( hexdec( substr( $hex, $offset, 2 ) ) * $factor );
            $parts[] = max( 0, min( 255, $value ) );
        }

        return vsprintf( '#%02x%02x%02x', $parts );
    }

    /**
     * Enqueue frontend assets on singular posts/pages.
     */
    public function enqueue_assets() {
        if ( ! is_singular() ) {
            return;
        }

        wp_enqueue_style(
            'pc-frontend',
            PC_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PC_VERSION
        );

        $accent = self::accent_color();
        wp_add_inline_style(
            'pc-frontend',
            sprintf(
                ':root{--pc-accent:%1$s;--pc-accent-hover:%2$s;}',
                $accent,
                self::shade_color( $accent )
            )
        );

        wp_enqueue_script(
            'pc-frontend',
            PC_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            PC_VERSION,
            true
        );

        wp_localize_script( 'pc-frontend', 'pcData', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'pc_nonce' ),
            'postId'       => get_the_ID(),
            'isLoggedIn'   => is_user_logged_in(),
            'userId'       => get_current_user_id(),
            'contentWidth' => (int) get_option( 'pc_content_width', 800 ),
            'mobileWidth'  => (int) get_option( 'pc_mobile_width', 390 ),
            'isFrame'      => self::is_frame_request(),
            'canUpload'    => PC_Uploads::can_upload(),
            'maxImages'    => PC_Uploads::MAX_FILES,
            'i18n'       => array(
                'commentMode'      => __( 'Comment Mode', 'pinned-comments' ),
                'exitCommentMode'  => __( 'Exit Comment Mode', 'pinned-comments' ),
                'desktop'          => __( 'Desktop', 'pinned-comments' ),
                'mobile'           => __( 'Mobile', 'pinned-comments' ),
                'comment'          => __( 'Comment', 'pinned-comments' ),
                'reply'            => __( 'Reply', 'pinned-comments' ),
                'send'             => __( 'Send', 'pinned-comments' ),
                'cancel'           => __( 'Cancel', 'pinned-comments' ),
                'edit'             => __( 'Edit', 'pinned-comments' ),
                'delete'           => __( 'Delete', 'pinned-comments' ),
                'save'             => __( 'Save', 'pinned-comments' ),
                'loginToComment'   => __( 'Log in to leave comments.', 'pinned-comments' ),
                'confirmDelete'    => __( 'Delete this comment and all replies?', 'pinned-comments' ),
                'placeholder'      => __( 'Write a comment...', 'pinned-comments' ),
                'replyPlaceholder' => __( 'Write a reply...', 'pinned-comments' ),
                'edited'           => __( 'edited', 'pinned-comments' ),
                'error'            => __( 'Something went wrong.', 'pinned-comments' ),
                'frameError'       => __( 'The page could not be loaded in comment mode.', 'pinned-comments' ),
                'addImages'        => __( 'Add images', 'pinned-comments' ),
                'removeImage'      => __( 'Remove image', 'pinned-comments' ),
                'maxImagesReached' => sprintf(
                    /* translators: %d: maximum number of images. */
                    __( 'A maximum of %d images per comment.', 'pinned-comments' ),
                    PC_Uploads::MAX_FILES
                ),
                'attachments'      => __( 'Images', 'pinned-comments' ),
            ),
        ) );
    }

    /**
     * Render the comment mode toggle button and the fixed-width stage in the footer.
     *
     * Inside the frame the page is only the commented document itself, so none of
     * this chrome is rendered there.
     */
    public function render_comment_mode_ui() {
        if ( ! is_singular() || self::is_frame_request() ) {
            return;
        }
        ?>
        <div id="pc-toggle-bar">
            <div id="pc-viewport-switch" class="pc-viewport-switch" role="group"
                aria-label="<?php esc_attr_e( 'Comment viewport', 'pinned-comments' ); ?>">
                <button type="button" class="pc-viewport-btn is-active" data-viewport="desktop" aria-pressed="true">
                    <?php esc_html_e( 'Desktop', 'pinned-comments' ); ?>
                </button>
                <button type="button" class="pc-viewport-btn" data-viewport="mobile" aria-pressed="false">
                    <?php esc_html_e( 'Mobile', 'pinned-comments' ); ?>
                </button>
            </div>
            <button id="pc-toggle-btn" type="button" aria-pressed="false">
                <span class="pc-toggle-icon" aria-hidden="true"></span>
                <span class="pc-toggle-label"><?php esc_html_e( 'Comment Mode', 'pinned-comments' ); ?></span>
            </button>
        </div>
        <div id="pc-stage" aria-hidden="true">
            <div id="pc-stage-inner">
                <iframe id="pc-stage-frame" title="<?php esc_attr_e( 'Comment mode preview', 'pinned-comments' ); ?>"></iframe>
            </div>
        </div>
        <?php
    }
}
