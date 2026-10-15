<?php





























if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_option = static function ( string $method, bool $write, array $params, ?string $network, ?string $preflight, ?string $describe, array $extra = array() ): array {
    $route = array(
        'route'   => 'own',
        'handler' => 'Option_Handler',
        'method'  => $method,
        'cap'     => 'manage_options',
        'network' => $network,
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
    'option add'          => $emai_option( 'add', true, array( 'key', 'value', 'format', 'autoload' ), null, 'preflight_write', 'describe_add' ),
    
    'option delete'       => $emai_option( 'delete', true, array( 'key' ), 'manage_network_options', 'preflight_keys', 'describe_delete', array( 'bulk' => 50 ) ),
    'option get'          => $emai_option( 'get', false, array( 'key', 'format' ), null, 'preflight_read', null ),
    'option get-autoload' => $emai_option( 'get_autoload', false, array( 'key' ), null, 'preflight_read', null ),
    'option list'         => $emai_option( 'list_', false, array( 'search', 'exclude', 'autoload', 'transients', 'unserialize', 'field', 'fields', 'format', 'orderby', 'order' ), 'manage_network_options', null, null ),
    'option patch'        => $emai_option( 'patch', true, array( 'action', 'key', 'key-path', 'value', 'format' ), null, 'preflight_patch', 'describe_patch' ),
    'option pluck'        => $emai_option( 'pluck', false, array( 'key', 'key-path', 'format' ), null, 'preflight_read', null ),
    'option set-autoload' => $emai_option( 'set_autoload', true, array( 'key', 'autoload' ), 'manage_network_options', 'preflight_keys', 'describe_set_autoload' ),
    'option update'       => $emai_option(
        'update',
        true,
        array( 'key', 'value', 'autoload', 'format' ),
        null,
        'preflight_write',
        'describe_update',
        array(
            'delegate_when'      => 'is_site_setting',
            'adapter'            => 'Option_Adapter',
            'delegate_method'    => 'update',
            'delegate_tools'     => array( 'wp_update_site_settings' ),
            'delegate_preflight' => 'preflight_update',
        )
    ),
);
