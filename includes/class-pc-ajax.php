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
        add_action( 'wp_ajax_pc_mark_read', array( $this, 'mark_read' ) );
        add_action( 'wp_ajax_pc_load_all_threads', array( $this, 'load_all_threads' ) );
    }

    /**
     * Load all comments for a post.
     */
    public function load_comments() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $viewport = isset( $_POST['viewport'] ) ? PC_Database::sanitize_viewport( sanitize_key( $_POST['viewport'] ) ) : '';

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'pinned-comments' ) ) );
        }

        $comments = PC_Database::get_all_for_post( $post_id, $viewport );

        // Build thread metadata for root comments: last activity, reply count,
        // and whether the current user has unread activity.
        $thread_meta   = array();
        $replies_by_parent = array();
        $root_ids      = array();
        $user_id       = get_current_user_id();

        foreach ( $comments as $comment ) {
            if ( (int) $comment->parent_id === 0 ) {
                $root_ids[ (int) $comment->id ] = true;
                $thread_meta[ (int) $comment->id ] = array(
                    'lastActivityAt' => $comment->created_at,
                    'replyCount'     => 0,
                );
            } else {
                $replies_by_parent[ (int) $comment->parent_id ][] = $comment;
            }
        }

        foreach ( $replies_by_parent as $parent_id => $replies ) {
            if ( ! isset( $thread_meta[ $parent_id ] ) ) {
                continue;
            }
            foreach ( $replies as $reply ) {
                if ( $reply->created_at > $thread_meta[ $parent_id ]['lastActivityAt'] ) {
                    $thread_meta[ $parent_id ]['lastActivityAt'] = $reply->created_at;
                }
                $thread_meta[ $parent_id ]['replyCount']++;
            }
        }

        if ( $user_id ) {
            $read_map = PC_Database::get_read_map_for_post( $post_id, $user_id, $viewport );
            foreach ( $thread_meta as $root_id => $meta ) {
                $read_at = isset( $read_map[ $root_id ] ) ? $read_map[ $root_id ] : '0000-00-00 00:00:00';
                $thread_meta[ $root_id ]['unread'] = ( $read_at < $meta['lastActivityAt'] );
            }
        }

        $formatted = array();
        foreach ( $comments as $comment ) {
            $formatted[] = PC_Database::format_for_response( $comment, $thread_meta );
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
        $viewport  = isset( $_POST['viewport'] ) ? PC_Database::sanitize_viewport( sanitize_key( $_POST['viewport'] ) ) : 'desktop';

        if ( ! $post_id || ( '' === $content && ! PC_Uploads::has_uploads() ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'pinned-comments' ) ) );
        }

        // Replies always live in the same viewport as their parent pin.
        if ( $parent_id ) {
            $parent = PC_Database::get_comment( $parent_id );
            if ( ! $parent ) {
                wp_send_json_error( array( 'message' => __( 'Parent comment not found.', 'pinned-comments' ) ) );
            }
            $viewport = PC_Database::sanitize_viewport( $parent->viewport );
        }

        $attachments = PC_Uploads::handle( PC_Uploads::MAX_FILES );
        if ( is_wp_error( $attachments ) ) {
            wp_send_json_error( array( 'message' => $attachments->get_error_message() ) );
        }

        $id = PC_Database::insert( array(
            'post_id'     => $post_id,
            'content'     => $content,
            'x_position'  => $x,
            'y_position'  => $y,
            'viewport'    => $viewport,
            'attachments' => $attachments,
            'parent_id'   => $parent_id,
        ) );

        if ( ! $id ) {
            PC_Uploads::delete_files( $attachments );
            wp_send_json_error( array( 'message' => __( 'Failed to create comment.', 'pinned-comments' ) ) );
        }

        $comment = PC_Database::get_comment( $id );

        // The author has already seen what they just wrote.
        $root_id = (int) $comment->parent_id === 0 ? (int) $comment->id : (int) $comment->parent_id;
        PC_Database::mark_read( get_current_user_id(), $root_id );

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

        if ( ! $comment_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'pinned-comments' ) ) );
        }

        if ( ! PC_Database::is_owner( $comment_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You can only edit your own comments.', 'pinned-comments' ) ) );
        }

        $comment = PC_Database::get_comment( $comment_id );
        if ( ! $comment ) {
            wp_send_json_error( array( 'message' => __( 'Invalid comment ID.', 'pinned-comments' ) ) );
        }

        $existing = PC_Database::decode_attachments( $comment->attachments );

        $removed = isset( $_POST['remove_attachments'] ) ? (array) $_POST['remove_attachments'] : array();
        $removed = array_filter( array_map( array( 'PC_Uploads', 'sanitize_relative' ), $removed ) );
        $removed = array_values( array_intersect( $existing, $removed ) );

        $kept = array_values( array_diff( $existing, $removed ) );

        $added = PC_Uploads::handle( PC_Uploads::MAX_FILES - count( $kept ) );
        if ( is_wp_error( $added ) ) {
            wp_send_json_error( array( 'message' => $added->get_error_message() ) );
        }

        $attachments = array_merge( $kept, $added );

        if ( '' === $content && empty( $attachments ) ) {
            PC_Uploads::delete_files( $added );
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'pinned-comments' ) ) );
        }

        $updated = PC_Database::update_comment( $comment_id, $content, $attachments );

        if ( ! $updated ) {
            PC_Uploads::delete_files( $added );
            wp_send_json_error( array( 'message' => __( 'Failed to update comment.', 'pinned-comments' ) ) );
        }

        PC_Uploads::delete_files( $removed );

        $comment = PC_Database::get_comment( $comment_id );
        wp_send_json_success( array( 'comment' => PC_Database::format_for_response( $comment ) ) );
    }

    /**
     * Delete a comment (only own comments, plus replies and their images).
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

    /**
     * Mark a comment thread as read for the current user.
     */
    public function mark_read() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'pinned-comments' ) ) );
        }

        $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
        $user_id    = get_current_user_id();

        if ( ! $comment_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid comment ID.', 'pinned-comments' ) ) );
        }

        $comment = PC_Database::get_comment( $comment_id );
        if ( ! $comment ) {
            wp_send_json_error( array( 'message' => __( 'Comment not found.', 'pinned-comments' ) ) );
        }

        // Always store the read state against the root comment.
        $root_id = (int) $comment->parent_id === 0 ? $comment->id : $comment->parent_id;

        PC_Database::mark_read( $user_id, $root_id );

        wp_send_json_success( array( 'read' => true ) );
    }

    /**
     * Load all comment threads across the site for the overview panel.
     */
    public function load_all_threads() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        global $wpdb;
        $table = PC_Database::table_name();

        // Only include comments attached to publicly published content.
        $comments = $wpdb->get_results(
            "SELECT c.*, p.post_title
            FROM $table c
            INNER JOIN {$wpdb->posts} p ON p.ID = c.post_id
            WHERE p.post_status = 'publish'
            ORDER BY c.created_at ASC"
        );

        $roots_by_id       = array();
        $replies_by_parent = array();
        $user_id           = get_current_user_id();
        $read_map          = $user_id ? PC_Database::get_read_map_for_user( $user_id ) : array();

        foreach ( $comments as $comment ) {
            if ( (int) $comment->parent_id === 0 ) {
                $roots_by_id[ (int) $comment->id ] = $comment;
            } else {
                $replies_by_parent[ (int) $comment->parent_id ][] = $comment;
            }
        }

        $threads = array();
        foreach ( $roots_by_id as $root_id => $root ) {
            $last        = $root->created_at;
            $reply_count = 0;
            $replies     = isset( $replies_by_parent[ $root_id ] ) ? $replies_by_parent[ $root_id ] : array();
            foreach ( $replies as $reply ) {
                if ( $reply->created_at > $last ) {
                    $last = $reply->created_at;
                }
                $reply_count++;
            }

            $read_at = isset( $read_map[ $root_id ] ) ? $read_map[ $root_id ] : '0000-00-00 00:00:00';
            $meta    = array(
                $root_id => array(
                    'lastActivityAt' => $last,
                    'replyCount'     => $reply_count,
                    'unread'         => $user_id && ( $read_at < $last ),
                ),
            );

            $formatted = PC_Database::format_for_response( $root, $meta );
            $formatted['postTitle'] = $root->post_title;
            $formatted['postUrl']   = (string) get_permalink( $root->post_id );

            $threads[] = $formatted;
        }

        wp_send_json_success( array( 'threads' => $threads ) );
    }
}
