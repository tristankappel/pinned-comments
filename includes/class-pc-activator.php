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
        update_option( 'pc_version', PC_VERSION );
    }
}
