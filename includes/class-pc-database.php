<?php
/**
 * Database operations for Pinned Comments.
 *
 * @package PinnedComments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Database {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get the custom table name.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'pinned_comments';
    }

    /**
     * Create the custom table.
     */
    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            content text NOT NULL,
            x_position float NOT NULL DEFAULT 0,
            y_position float NOT NULL DEFAULT 0,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            modified_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY parent_id (parent_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get a single comment by ID.
     *
     * @param int $id Comment ID.
     * @return object|null
     */
    public static function get_comment( $id ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Get all top-level comments (pins) for a post.
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public static function get_pins_for_post( $post_id ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE post_id = %d AND parent_id = 0 ORDER BY created_at ASC",
                $post_id
            )
        );
    }

    /**
     * Get all replies for a parent comment.
     *
     * @param int $parent_id Parent comment ID.
     * @return array
     */
    public static function get_replies( $parent_id ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE parent_id = %d ORDER BY created_at ASC",
                $parent_id
            )
        );
    }

    /**
     * Get all comments for a post (pins and replies).
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public static function get_all_for_post( $post_id ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE post_id = %d ORDER BY created_at ASC",
                $post_id
            )
        );
    }

    /**
     * Insert a new comment.
     *
     * @param array $data Comment data.
     * @return int|false Inserted ID or false on failure.
     */
    public static function insert( $data ) {
        global $wpdb;
        $table = self::table_name();

        $now = current_time( 'mysql' );

        $defaults = array(
            'post_id'     => 0,
            'user_id'     => get_current_user_id(),
            'content'     => '',
            'x_position'  => 0,
            'y_position'  => 0,
            'parent_id'   => 0,
            'created_at'  => $now,
            'modified_at' => $now,
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $table,
            array(
                'post_id'     => $data['post_id'],
                'user_id'     => $data['user_id'],
                'content'     => $data['content'],
                'x_position'  => $data['x_position'],
                'y_position'  => $data['y_position'],
                'parent_id'   => $data['parent_id'],
                'created_at'  => $data['created_at'],
                'modified_at' => $data['modified_at'],
            ),
            array( '%d', '%d', '%s', '%f', '%f', '%d', '%s', '%s' )
        );

        if ( false === $result ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a comment's content.
     *
     * @param int    $id      Comment ID.
     * @param string $content New content.
     * @return bool
     */
    public static function update_content( $id, $content ) {
        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->update(
            $table,
            array(
                'content'     => $content,
                'modified_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete a comment by ID.
     *
     * @param int $id Comment ID.
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        return false !== $result;
    }

    /**
     * Delete a comment and all its replies.
     *
     * @param int $id Comment ID.
     * @return bool
     */
    public static function delete_thread( $id ) {
        global $wpdb;
        $table = self::table_name();

        // Delete replies.
        $wpdb->delete( $table, array( 'parent_id' => $id ), array( '%d' ) );

        // Delete the comment itself.
        return self::delete( $id );
    }

    /**
     * Delete all comments (all posts).
     *
     * @return int Number of rows deleted.
     */
    public static function delete_all() {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->query( "DELETE FROM $table" );
    }

    /**
     * Delete all comments for a specific post.
     *
     * @param int $post_id Post ID.
     * @return int Number of rows deleted.
     */
    public static function delete_all_for_post( $post_id ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
    }

    /**
     * Check if a user owns a comment.
     *
     * @param int $comment_id Comment ID.
     * @param int $user_id    User ID.
     * @return bool
     */
    public static function is_owner( $comment_id, $user_id ) {
        global $wpdb;
        $table = self::table_name();
        $owner = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM $table WHERE id = %d",
                $comment_id
            )
        );
        return (int) $owner === (int) $user_id;
    }

    /**
     * Format a comment for JSON response.
     *
     * @param object $comment Comment row object.
     * @return array
     */
    public static function format_for_response( $comment ) {
        $user = get_userdata( $comment->user_id );

        return array(
            'id'          => (int) $comment->id,
            'postId'      => (int) $comment->post_id,
            'userId'      => (int) $comment->user_id,
            'authorName'  => $user ? $user->display_name : __( 'Unknown', 'pinned-comments' ),
            'authorAvatar'=> get_avatar_url( $comment->user_id, array( 'size' => 32 ) ),
            'content'     => $comment->content,
            'xPosition'   => (float) $comment->x_position,
            'yPosition'   => (float) $comment->y_position,
            'parentId'    => (int) $comment->parent_id,
            'createdAt'   => $comment->created_at,
            'modifiedAt'  => $comment->modified_at,
            'isOwn'       => (int) $comment->user_id === get_current_user_id(),
        );
    }
}
