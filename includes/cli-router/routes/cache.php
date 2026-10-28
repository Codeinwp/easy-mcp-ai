<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_cache = static function ( string $method, bool $write, array $params, ?string $preflight, ?string $describe, array $extra = array() ): array {
    $route = array(
        'route'   => 'own',
        'handler' => 'Cache_Handler',
        'method'  => $method,
        'cap'     => 'manage_options',
        'network' => 'manage_network_options',
        'write'   => $write,
        'params'  => $params,
    );
    if ( null !== $preflight ) {
        $route['preflight'] = $preflight;
    }
    if ( null !== $describe ) {
        $route['describe'] = $describe;
    }
    return array_merge( $route, $extra );
};

return array(
    'cache add'         => $emai_cache( 'add', true, array( 'key', 'value', 'group', 'expiration' ), 'preflight_group_third', 'describe_store' ),
    'cache decr'        => $emai_cache( 'decr', true, array( 'key', 'offset', 'group' ), 'preflight_group_third', 'describe_step' ),
    'cache delete'      => $emai_cache( 'delete', true, array( 'key', 'group' ), 'preflight_group_second', 'describe_delete' ),
    'cache flush'       => $emai_cache( 'flush', true, array(), null, 'describe_flush' ),
    'cache flush-group' => $emai_cache( 'flush_group', true, array( 'group' ), 'preflight_group_first', 'describe_flush_group' ),
    'cache get'         => $emai_cache( 'get', false, array( 'key', 'group' ), 'preflight_group_second', null ),
    'cache incr'        => $emai_cache( 'incr', true, array( 'key', 'offset', 'group' ), 'preflight_group_third', 'describe_step' ),
    'cache patch'       => $emai_cache( 'patch', true, array( 'action', 'key', 'key-path', 'value', 'group', 'format' ), 'preflight_group_flag', 'describe_patch' ),
    'cache pluck'       => $emai_cache( 'pluck', false, array( 'key', 'key-path', 'group', 'format' ), 'preflight_group_flag', null ),
    'cache replace'     => $emai_cache( 'replace', true, array( 'key', 'value', 'group', 'expiration' ), 'preflight_group_third', 'describe_store' ),
    'cache set'         => $emai_cache( 'set', true, array( 'key', 'value', 'group', 'expiration' ), 'preflight_group_third', 'describe_store' ),
    'cache supports'    => $emai_cache( 'supports', false, array( 'feature' ), null, null, array( 'answer' => true ) ),
    'cache type'        => $emai_cache( 'type', false, array(), null, null ),
);
