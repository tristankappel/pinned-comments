<?php
/**
 * Image uploads for Pinned Comments.
 *
 * Files are stored in their own folder inside wp-content/uploads and are
 * deliberately not registered in the media library.
 *
 * @package PinnedComments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Uploads {

    /**
     * Maximum number of images per comment.
     */
    const MAX_FILES = 5;

    /**
     * Maximum file size per image in bytes.
     */
    const MAX_SIZE = 5242880; // 5 MB.

    /**
     * Upload folder inside the uploads basedir.
     */
    const SUBDIR = 'pinned-comments';

    /**
     * Allowed image types.
     *
     * @return array
     */
    public static function allowed_mimes() {
        return array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
        );
    }

    /**
     * Whether the current user may attach images.
     *
     * @return bool
     */
    public static function can_upload() {
        return is_user_logged_in() && current_user_can( 'upload_files' );
    }

    /**
     * Whether the request contains uploaded files.
     *
     * @return bool
     */
    public static function has_uploads() {
        return isset( $_FILES['pc_images'] ) && ! empty( $_FILES['pc_images']['name'] );
    }

    /**
     * Handle the uploaded files from $_FILES['pc_images'].
     *
     * @param int $limit Maximum number of files to accept.
     * @return array|WP_Error List of relative file paths.
     */
    public static function handle( $limit ) {
        if ( ! self::has_uploads() ) {
            return array();
        }

        if ( ! self::can_upload() ) {
            return new WP_Error( 'pc_upload_denied', __( 'You are not allowed to upload images.', 'pinned-comments' ) );
        }

        $limit = min( (int) $limit, self::MAX_FILES );
        if ( $limit < 1 ) {
            return new WP_Error(
                'pc_upload_limit',
                sprintf(
                    /* translators: %d: maximum number of images. */
                    __( 'A comment can have a maximum of %d images.', 'pinned-comments' ),
                    self::MAX_FILES
                )
            );
        }

        $files = self::normalize( $_FILES['pc_images'] );
        if ( count( $files ) > $limit ) {
            return new WP_Error(
                'pc_upload_limit',
                sprintf(
                    /* translators: %d: maximum number of images. */
                    __( 'A comment can have a maximum of %d images.', 'pinned-comments' ),
                    self::MAX_FILES
                )
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = array();
        add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

        foreach ( $files as $file ) {
            if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
                $error = new WP_Error( 'pc_upload_failed', __( 'The image could not be uploaded.', 'pinned-comments' ) );
                break;
            }

            if ( (int) $file['size'] > self::MAX_SIZE ) {
                $error = new WP_Error(
                    'pc_upload_size',
                    sprintf(
                        /* translators: %s: maximum file size. */
                        __( 'Each image may be at most %s.', 'pinned-comments' ),
                        size_format( self::MAX_SIZE )
                    )
                );
                break;
            }

            $result = wp_handle_upload(
                $file,
                array(
                    'test_form' => false,
                    'mimes'     => self::allowed_mimes(),
                )
            );

            if ( isset( $result['error'] ) ) {
                $error = new WP_Error( 'pc_upload_failed', $result['error'] );
                break;
            }

            // Make sure the file really is an image and not just named like one.
            $size = @getimagesize( $result['file'] );
            if ( empty( $size[0] ) ) {
                wp_delete_file( $result['file'] );
                $error = new WP_Error( 'pc_upload_type', __( 'Only images can be attached.', 'pinned-comments' ) );
                break;
            }

            $uploaded[] = self::to_relative( $result['file'] );
        }

        remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

        if ( isset( $error ) ) {
            self::delete_files( $uploaded );
            return $error;
        }

        return array_values( array_filter( $uploaded ) );
    }

    /**
     * Move uploads into the plugin's own folder, keeping the year/month structure.
     *
     * @param array $dirs Upload directory data.
     * @return array
     */
    public static function filter_upload_dir( $dirs ) {
        $subdir = '/' . self::SUBDIR . ( isset( $dirs['subdir'] ) ? $dirs['subdir'] : '' );

        $dirs['subdir'] = $subdir;
        $dirs['path']   = $dirs['basedir'] . $subdir;
        $dirs['url']    = $dirs['baseurl'] . $subdir;

        return $dirs;
    }

    /**
     * Turn PHP's $_FILES structure for multiple files into a flat list.
     *
     * @param array $input Raw $_FILES entry.
     * @return array
     */
    private static function normalize( $input ) {
        $files = array();

        if ( ! is_array( $input['name'] ) ) {
            return array( $input );
        }

        $count = count( $input['name'] );
        for ( $i = 0; $i < $count; $i++ ) {
            if ( '' === $input['name'][ $i ] ) {
                continue;
            }
            $files[] = array(
                'name'     => $input['name'][ $i ],
                'type'     => $input['type'][ $i ],
                'tmp_name' => $input['tmp_name'][ $i ],
                'error'    => $input['error'][ $i ],
                'size'     => $input['size'][ $i ],
            );
        }

        return $files;
    }

    /**
     * Convert an absolute path into a path relative to the uploads basedir.
     *
     * @param string $path Absolute file path.
     * @return string
     */
    private static function to_relative( $path ) {
        $uploads = wp_get_upload_dir();
        $path    = wp_normalize_path( $path );
        $basedir = trailingslashit( wp_normalize_path( $uploads['basedir'] ) );

        if ( 0 !== strpos( $path, $basedir ) ) {
            return '';
        }

        return substr( $path, strlen( $basedir ) );
    }

    /**
     * Validate a stored relative path before it is used or deleted.
     *
     * @param mixed $value Relative path.
     * @return string Empty string when invalid.
     */
    public static function sanitize_relative( $value ) {
        $value = ltrim( wp_normalize_path( (string) $value ), '/' );

        if ( 0 !== strpos( $value, self::SUBDIR . '/' ) ) {
            return '';
        }
        if ( false !== strpos( $value, '..' ) ) {
            return '';
        }
        if ( ! preg_match( '#^[A-Za-z0-9/._-]+$#', $value ) ) {
            return '';
        }

        $allowed = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp' );
        if ( ! in_array( strtolower( pathinfo( $value, PATHINFO_EXTENSION ) ), $allowed, true ) ) {
            return '';
        }

        return $value;
    }

    /**
     * Public URL for a stored file.
     *
     * @param string $relative Relative file path.
     * @return string
     */
    public static function url_for( $relative ) {
        $relative = self::sanitize_relative( $relative );
        if ( ! $relative ) {
            return '';
        }
        $uploads = wp_get_upload_dir();
        return trailingslashit( $uploads['baseurl'] ) . $relative;
    }

    /**
     * Delete stored files.
     *
     * @param array $relatives Relative file paths.
     */
    public static function delete_files( $relatives ) {
        if ( empty( $relatives ) ) {
            return;
        }

        $uploads = wp_get_upload_dir();
        $basedir = trailingslashit( wp_normalize_path( $uploads['basedir'] ) );

        foreach ( (array) $relatives as $relative ) {
            $relative = self::sanitize_relative( $relative );
            if ( ! $relative ) {
                continue;
            }
            $path = $basedir . $relative;
            if ( file_exists( $path ) ) {
                wp_delete_file( $path );
            }
        }
    }
}
