<?php


















if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'cap add'    => array(
        'route'     => 'own',
        'handler'   => 'Cap_Handler',
        'method'    => 'add',
        'cap'       => 'promote_users',
        'network'   => 'manage_network_options',
        'write'     => true,
        'params'    => array( 'role', 'cap', 'grant' ),
        'bulk'      => 50,
        'preflight' => 'preflight_add',
        'describe'  => 'describe_add',
    ),
    'cap list'   => array(
        'route'   => 'own',
        'handler' => 'Cap_Handler',
        'method'  => 'list_caps',
        'cap'     => 'list_users',
        'network' => null,
        'write'   => false,
        'params'  => array( 'role', 'format', 'show-grant' ),
    ),
    'cap remove' => array(
        'route'     => 'own',
        'handler'   => 'Cap_Handler',
        'method'    => 'remove',
        'cap'       => 'promote_users',
        'network'   => 'manage_network_options',
        'write'     => true,
        'params'    => array( 'role', 'cap' ),
        'bulk'      => 50,
        'preflight' => 'preflight_remove',
        'describe'  => 'describe_remove',
    ),
);
