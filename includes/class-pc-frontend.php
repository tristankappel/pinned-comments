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
            'i18n'       => array(
                'commentMode'      => __( 'Comment Mode', 'pinned-comments' ),
                'exitCommentMode'  => __( 'Exit Comment Mode', 'pinned-comments' ),
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
            ),
        ) );
    }

    /**
     * Render the comment mode toggle button and overlay container in footer.
     */
    public function render_comment_mode_ui() {
        if ( ! is_singular() ) {
            return;
        }
        ?>
        <div id="pc-toggle-bar">
            <button id="pc-toggle-btn" type="button" aria-pressed="false">
                <span class="pc-toggle-icon" aria-hidden="true"></span>
                <span class="pc-toggle-label"><?php esc_html_e( 'Comment Mode', 'pinned-comments' ); ?></span>
            </button>
        </div>
        <div id="pc-overlay" class="pc-overlay" aria-hidden="true"></div>
        <?php
    }
}
