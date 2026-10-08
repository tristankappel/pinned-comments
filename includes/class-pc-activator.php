<?php
/**
 * Activator class for Pinned Comments.
 *
 * @package PinnedComments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Activator {

    public static function activate() {
        PC_Database::create_table();
        add_option( 'pc_content_width', 800 );
        add_option( 'pc_mobile_width', 390 );
        add_option( 'pc_accent_color', PC_Frontend::DEFAULT_ACCENT );
        update_option( 'pc_db_version', PC_VERSION );
        update_option( 'pc_version', PC_VERSION );
    }
}
