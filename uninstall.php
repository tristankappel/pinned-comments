<?php
/**
 * Uninstall Pinned Comments.
 *
 * @package PinnedComments
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table = $wpdb->prefix . 'pinned_comments';
$wpdb->query( "DROP TABLE IF EXISTS $table" );

delete_option( 'pc_version' );
delete_option( 'pc_content_width' );
