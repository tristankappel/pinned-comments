<?php
/**
 * AJAX handlers for Pinned Comments.
 *
 * @package PinnedComments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Ajax {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_pc_load_comments', array( $this, 'load_comments' ) );
        add_action( 'wp_ajax_pc_create_comment', array( $this, 'create_comment' ) );
        add_action( 'wp_ajax_pc_edit_comment', array( $this, 'edit_comment' ) );
        add_action( 'wp_ajax_pc_delete_comment', array( $this, 'delete_comment' ) );
        add_action( 'wp_ajax_pc_delete_all', array( $this, 'delete_all' ) );
    }

    /**
     * Load all comments for a post.
     */
    public function load_comments() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'pinned-comments' ) ) );
        }

        $comments = PC_Database::get_all_for_post( $post_id );
        $formatted = array();

        foreach ( $comments as $comment ) {
            $formatted[] = PC_Database::format_for_response( $comment );
        }

        wp_send_json_success( array( 'comments' => $formatted ) );
    }

    /**
     * Create a new comment or reply.
     */
    public function create_comment() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in to comment.', 'pinned-comments' ) ) );
        }

        $post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $content   = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
        $x         = isset( $_POST['x_position'] ) ? floatval( $_POST['x_position'] ) : 0;
        $y         = isset( $_POST['y_position'] ) ? floatval( $_POST['y_position'] ) : 0;
        $parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;

        if ( ! $post_id || empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'pinned-comments' ) ) );
        }

        $id = PC_Database::insert( array(
            'post_id'    => $post_id,
            'content'    => $content,
            'x_position' => $x,
            'y_position' => $y,
            'parent_id'  => $parent_id,
        ) );

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Failed to create comment.', 'pinned-comments' ) ) );
        }

        $comment = PC_Database::get_comment( $id );
        wp_send_json_success( array( 'comment' => PC_Database::format_for_response( $comment ) ) );
    }

    /**
     * Edit a comment (only own comments).
     */
    public function edit_comment() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'pinned-comments' ) ) );
        }

        $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
        $content    = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
        $user_id    = get_current_user_id();

        if ( ! $comment_id || empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'pinned-comments' ) ) );
        }

        if ( ! PC_Database::is_owner( $comment_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You can only edit your own comments.', 'pinned-comments' ) ) );
        }

        $updated = PC_Database::update_content( $comment_id, $content );

        if ( ! $updated ) {
            wp_send_json_error( array( 'message' => __( 'Failed to update comment.', 'pinned-comments' ) ) );
        }

        $comment = PC_Database::get_comment( $comment_id );
        wp_send_json_success( array( 'comment' => PC_Database::format_for_response( $comment ) ) );
    }

    /**
     * Delete a comment (only own comments, plus replies).
     */
    public function delete_comment() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'pinned-comments' ) ) );
        }

        $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
        $user_id    = get_current_user_id();

        if ( ! $comment_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid comment ID.', 'pinned-comments' ) ) );
        }

        if ( ! PC_Database::is_owner( $comment_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You can only delete your own comments.', 'pinned-comments' ) ) );
        }

        $deleted = PC_Database::delete_thread( $comment_id );

        if ( ! $deleted ) {
            wp_send_json_error( array( 'message' => __( 'Failed to delete comment.', 'pinned-comments' ) ) );
        }

        wp_send_json_success( array( 'deleted' => true ) );
    }

    /**
     * Delete all comments (admin only).
     */
    public function delete_all() {
        check_ajax_referer( 'pc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'pinned-comments' ) ) );
        }

        $count = PC_Database::delete_all();

        wp_send_json_success( array( 'deleted' => $count ) );
    }
}
