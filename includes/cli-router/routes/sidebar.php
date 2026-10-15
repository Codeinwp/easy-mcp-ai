<?php










if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'sidebar list' => array(
        'route'   => 'delegate',
        'adapter' => 'Sidebar_Adapter',
        'method'  => 'list_sidebars',
        'tools'   => array( 'wp_list_sidebars' ),
        'write'   => false,
        'params'  => array( 'fields', 'format' ),
    ),
);
