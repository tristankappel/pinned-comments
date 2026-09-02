<?php
/**
 * Deactivator class for Pinned Comments.
 *
 * @package PinnedComments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Deactivator {

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
