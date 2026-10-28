<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_transient = static function ( string $method, bool $write, array $params, ?string $preflight, ?string $describe ): array {
    $route = array(
        'route'   => 'own',
        'handler' => 'Transient_Handler',
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
    return $route;
};

return array(
    'transient delete' => $emai_transient( 'delete', true, array( 'key', 'network', 'all', 'expired' ), 'preflight_delete', 'describe_delete' ),
    'transient get'    => $emai_transient( 'get', false, array( 'key', 'format', 'network' ), 'preflight_read', null ),
    'transient list'   => $emai_transient( 'list_', false, array( 'search', 'exclude', 'network', 'unserialize', 'human-readable', 'fields', 'format' ), null, null ),
    'transient patch'  => $emai_transient( 'patch', true, array( 'action', 'key', 'key-path', 'value', 'format', 'expiration', 'network' ), 'preflight_patch', 'describe_patch' ),
    'transient pluck'  => $emai_transient( 'pluck', false, array( 'key', 'key-path', 'format', 'network' ), 'preflight_read', null ),
    'transient set'    => $emai_transient( 'set', true, array( 'key', 'value', 'expiration', 'network' ), 'preflight_write', 'describe_set' ),
    'transient type'   => $emai_transient( 'type', false, array(), null, null ),
);
