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

require_once plugin_dir_path( __FILE__ ) . 'includes/class-pc-uploads.php';

$table = $wpdb->prefix . 'pinned_comments';

// Remove the uploaded images before dropping the table.
$rows = $wpdb->get_col( "SELECT attachments FROM $table WHERE attachments IS NOT NULL AND attachments != ''" );
foreach ( (array) $rows as $row ) {
    $files = json_decode( (string) $row, true );
    if ( is_array( $files ) ) {
        PC_Uploads::delete_files( $files );
    }
}

$wpdb->query( "DROP TABLE IF EXISTS $table" );

delete_option( 'pc_version' );
delete_option( 'pc_db_version' );
delete_option( 'pc_content_width' );
delete_option( 'pc_mobile_width' );
delete_option( 'pc_accent_color' );
