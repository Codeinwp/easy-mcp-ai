<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_network_meta = static function ( string $method, bool $write, array $params, array $extra = array() ): array {
    $route = array(
        'route'     => 'own',
        'handler'   => 'Network_Meta_Handler',
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
    return array_merge( $route, $extra );
};

return array(
    'network meta add'    => $emai_network_meta( 'add', true, array( 'id', 'key', 'value', 'format' ) ),
    'network meta delete' => $emai_network_meta(
        'delete',
        true,
        array( 'id', 'key', 'value' ),
        array(
            'refused_params' => array(
                'all' => 'it deletes every network option of the network (site_admins, siteurl, the salts WordPress may store there, every plugin\'s network settings), which no wp-admin screen offers and which leaves the network unusable. Delete one key at a time',
            ),
        )
    ),
    'network meta get'    => $emai_network_meta( 'get', false, array( 'id', 'key', 'single', 'format' ) ),
    'network meta list'   => $emai_network_meta( 'list_', false, array( 'id', 'keys', 'fields', 'format', 'orderby', 'order', 'unserialize' ) ),
    'network meta patch'  => $emai_network_meta( 'patch', true, array( 'action', 'id', 'key', 'key-path', 'value', 'format' ) ),
    'network meta pluck'  => $emai_network_meta( 'pluck', false, array( 'id', 'key', 'key-path', 'format' ) ),
    'network meta update' => $emai_network_meta( 'update', true, array( 'id', 'key', 'value', 'format' ) ),
);
