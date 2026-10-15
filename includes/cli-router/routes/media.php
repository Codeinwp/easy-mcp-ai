<?php



























if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'media fix-orientation' => array(
        'route'    => 'own',
        'handler'  => 'Media_Handler',
        'method'   => 'fix_orientation',
        'cap'      => 'upload_files',
        'network'  => null,
        'write'    => true,
        'bulk'           => 25,
        'params'         => array( 'attachment-id', 'dry-run' ),
        'describe'       => 'describe_fix_orientation',
        'preflight'      => 'preflight_fix_orientation',
        'read_only_when' => 'is_dry_run',
    ),
    'media image-size'      => array(
        'route'   => 'own',
        'handler' => 'Media_Handler',
        'method'  => 'image_size',
        'cap'     => 'upload_files',
        'network' => null,
        'write'   => false,
        'params'  => array( 'fields', 'format' ),
    ),
    'media import'          => array(
        'route'          => 'delegate',
        'adapter'        => 'Media_Adapter',
        'method'         => 'import',
        'tools'          => array( 'wp_upload_media_from_url' ),
        'write'          => true,
        'bulk'           => 25,
        'plan'           => 'plan_import',
        'preflight'      => 'preflight_import',
        'params'         => array( 'file', 'post_id', 'post_name', 'file_name', 'title', 'caption', 'alt', 'desc', 'preserve-filetime', 'featured_image', 'porcelain' ),
        'refused_params' => array(
            'skip-copy' => 'it applies only to local files, which wp_cli_run does not import; with a URL WP-CLI would store the URL itself as the attachment\'s file',
        ),
    ),
    'media regenerate'      => array(
        'route'    => 'own',
        'handler'  => 'Media_Handler',
        'method'   => 'regenerate',
        'cap'      => 'upload_files',
        'network'  => null,
        'write'    => true,
        'bulk'      => 25,
        'params'    => array( 'attachment-id', 'image_size', 'skip-delete', 'only-missing', 'delete-unknown', 'yes' ),
        'describe'  => 'describe_regenerate',
        'preflight' => 'preflight_regenerate',
    ),
);
