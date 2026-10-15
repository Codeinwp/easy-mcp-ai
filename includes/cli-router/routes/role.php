<?php
















if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'role create' => array(
        'route'     => 'own',
        'handler'   => 'Role_Handler',
        'method'    => 'create',
        'cap'       => 'promote_users',
        'network'   => 'manage_network_options',
        'write'     => true,
        'params'    => array( 'role-key', 'role-name', 'clone' ),
        'preflight' => 'preflight_write',
        'describe'  => 'describe_create',
    ),
    'role delete' => array(
        'route'     => 'own',
        'handler'   => 'Role_Handler',
        'method'    => 'delete',
        'cap'       => 'promote_users',
        'network'   => 'manage_network_options',
        'write'     => true,
        'params'    => array( 'role-key' ),
        'preflight' => 'preflight_delete',
        'describe'  => 'describe_delete',
    ),
    'role exists' => array(
        'route'   => 'own',
        'handler' => 'Role_Handler',
        'method'  => 'exists',
        'cap'     => 'list_users',
        'network' => null,
        'write'   => false,
        'answer'  => true,
        'params'  => array( 'role-key' ),
    ),
    'role list'   => array(
        'route'   => 'own',
        'handler' => 'Role_Handler',
        'method'  => 'list_roles',
        'cap'     => 'list_users',
        'network' => null,
        'write'   => false,
        'params'  => array( 'fields', 'field', 'format' ),
    ),
    'role reset'  => array(
        'route'     => 'own',
        'handler'   => 'Role_Handler',
        'method'    => 'reset',
        'cap'       => 'promote_users',
        'network'   => 'manage_network_options',
        'write'     => true,
        'params'    => array( 'role-key', 'all' ),
        'bulk'      => 50,
        'preflight' => 'preflight_write',
        'describe'  => 'describe_reset',
    ),
);
