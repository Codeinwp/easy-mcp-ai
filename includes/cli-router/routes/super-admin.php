<?php
















if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'super-admin add'    => array(
        'route'     => 'own',
        'handler'   => 'Super_Admin_Handler',
        'method'    => 'add',
        'cap'       => 'manage_options',
        'network'   => 'manage_network_users',
        'write'     => true,
        'params'    => array( 'user' ),
        'bulk'      => 50,
        'preflight' => 'preflight_write',
        'describe'  => 'describe_add',
    ),
    'super-admin list'   => array(
        'route'     => 'own',
        'handler'   => 'Super_Admin_Handler',
        'method'    => 'list_admins',
        'cap'       => 'manage_options',
        'network'   => 'manage_network_users',
        'write'     => false,
        'params'    => array( 'format' ),
        'preflight' => 'preflight_list',
    ),
    'super-admin remove' => array(
        'route'     => 'own',
        'handler'   => 'Super_Admin_Handler',
        'method'    => 'remove',
        'cap'       => 'manage_options',
        'network'   => 'manage_network_users',
        'write'     => true,
        'params'    => array( 'user' ),
        'bulk'      => 50,
        'preflight' => 'preflight_remove',
        'describe'  => 'describe_remove',
    ),
);
