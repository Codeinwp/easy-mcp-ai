<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_site_status = array(
    'route'     => 'own',
    'handler'   => 'Site_Handler',
    'method'    => 'status',
    'cap'       => 'manage_options',
    'network'   => 'manage_sites',
    'write'     => true,
    'params'    => array( 'id', 'slug' ),
    'bulk'      => 50,
    'preflight' => 'preflight_multisite',
    'describe'  => 'describe_status',
);

$emai_site_meta = static function ( string $method, bool $write, array $params, array $extra = array() ): array {
    $route = array(
        'route'     => 'own',
        'handler'   => 'Site_Meta_Handler',
        'method'    => $method,
        'cap'       => 'manage_options',
        'network'   => 'manage_sites',
        'write'     => $write,
        'params'    => $params,
        'preflight' => 'preflight',
    );
    if ( $write ) {
        $route['describe'] = 'describe';
    }
    return array_merge( $route, $extra );
};

$emai_site_option = static function ( string $method, bool $write, array $params ): array {
    $route = array(
        'route'     => 'own',
        'handler'   => 'Site_Option_Handler',
        'method'    => $method,
        'cap'       => 'manage_options',
        'network'   => 'manage_network_options',
        'write'     => $write,
        'params'    => $params,
        'preflight' => 'preflight',
    );
    if ( $write ) {
        $route['describe'] = 'describe';
    }
    return $route;
};

return array(
    'site activate'        => $emai_site_status,
    'site archive'         => $emai_site_status,
    'site create'          => array(
        'route'     => 'own',
        'handler'   => 'Site_Handler',
        'method'    => 'create',
        'cap'       => 'manage_options',
        'network'   => 'create_sites',
        'write'     => true,
        'params'    => array( 'slug', 'title', 'email', 'network_id', 'private', 'porcelain' ),
        'preflight' => 'preflight_multisite',
        'describe'  => 'describe_create',
    ),
    'site deactivate'      => $emai_site_status,
    'site delete'          => array(
        'route'     => 'own',
        'handler'   => 'Site_Handler',
        'method'    => 'delete',
        'cap'       => 'manage_options',
        'network'   => 'delete_sites',
        'write'     => true,
        'params'    => array( 'site-id', 'slug', 'yes', 'keep-tables' ),
        'preflight' => 'preflight_delete',
        'describe'  => 'describe_delete',
    ),
    'site empty'           => array(
        'route'          => 'own',
        'handler'        => 'Site_Handler',
        'method'         => 'empty_',
        'cap'            => 'manage_options',
        'network'        => 'manage_sites',
        'write'          => true,
        'params'         => array( 'yes' ),
        'refused_params' => array(
            'uploads' => "it deletes every file in this site's uploads directory on the server, outside WordPress's media library (plan Q27). Delete attachments with `post delete` instead",
        ),
        'preflight'      => 'preflight_empty',
        'describe'       => 'describe_empty',
    ),
    'site generate'        => array( 'route' => 'refuse', 'reason' => 'Dummy-content generator.' ),
    'site list'            => array(
        'route'     => 'own',
        'handler'   => 'Site_Handler',
        'method'    => 'list_',
        'cap'       => 'manage_options',
        'network'   => 'manage_sites',
        'write'     => false,
        'params'    => array( 'network', 'site__in', 'site_user', 'field', 'fields', 'format' ),
        'generic'   => true,
        'preflight' => 'preflight_list',
    ),
    'site mature'          => $emai_site_status,
    'site meta add'        => $emai_site_meta( 'add', true, array( 'id', 'key', 'value', 'format' ) ),
    'site meta delete'     => $emai_site_meta( 'delete', true, array( 'id', 'key', 'value', 'all' ) ),
    'site meta get'        => $emai_site_meta( 'get', false, array( 'id', 'key', 'single', 'format' ) ),
    'site meta list'       => $emai_site_meta( 'list_', false, array( 'id', 'keys', 'fields', 'format', 'orderby', 'order', 'unserialize' ) ),
    'site meta patch'      => $emai_site_meta( 'patch', true, array( 'action', 'id', 'key', 'key-path', 'value', 'format' ) ),
    'site meta pluck'      => $emai_site_meta( 'pluck', false, array( 'id', 'key', 'key-path', 'format' ) ),
    'site meta update'     => $emai_site_meta( 'update', true, array( 'id', 'key', 'value', 'format' ) ),
    'site option add'      => $emai_site_option( 'add', true, array( 'key', 'value', 'format' ) ),
    'site option delete'   => $emai_site_option( 'delete', true, array( 'key' ) ),
    'site option get'      => $emai_site_option( 'get', false, array( 'key', 'format' ) ),
    'site option list'     => $emai_site_option( 'list_', false, array( 'search', 'site_id', 'field', 'fields', 'format' ) ),
    'site option patch'    => $emai_site_option( 'patch', true, array( 'action', 'key', 'key-path', 'value', 'format' ) ),
    'site option pluck'    => $emai_site_option( 'pluck', false, array( 'key', 'key-path', 'format' ) ),
    'site option update'   => $emai_site_option( 'update', true, array( 'key', 'value', 'format' ) ),
    'site private'         => $emai_site_status,
    'site public'          => $emai_site_status,
    'site spam'            => $emai_site_status,
    'site switch-language' => array(
        'route'   => 'delegate',
        'adapter' => 'Site_Adapter',
        'method'  => 'switch_language',
        'tools'   => array( 'wp_update_site_settings' ),
        'write'   => true,
        'params'  => array( 'language' ),
    ),
    'site unarchive'       => $emai_site_status,
    'site unmature'        => $emai_site_status,
    'site unspam'          => $emai_site_status,
);
