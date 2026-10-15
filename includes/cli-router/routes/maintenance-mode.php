<?php




















if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'maintenance-mode activate'   => array( 'route' => 'refuse', 'reason' => 'Answers 503 to every request for up to 10 minutes, this MCP endpoint included, so its undo cannot reach the site.' ),
    'maintenance-mode deactivate' => array(
        'route'     => 'own',
        'handler'   => 'Maintenance_Mode_Handler',
        'method'    => 'deactivate',
        'cap'       => 'manage_options',
        'network'   => 'manage_network_options',
        'write'     => true,
        'params'    => array(),
        'preflight' => 'preflight_deactivate',
        'describe'  => 'describe_deactivate',
    ),
    'maintenance-mode is-active'  => array(
        'route'   => 'own',
        'handler' => 'Maintenance_Mode_Handler',
        'method'  => 'is_active',
        'cap'     => 'manage_options',
        'write'   => false,
        'params'  => array(),
        'answer'  => true,
    ),
    'maintenance-mode status'     => array(
        'route'   => 'own',
        'handler' => 'Maintenance_Mode_Handler',
        'method'  => 'status',
        'cap'     => 'manage_options',
        'write'   => false,
        'params'  => array(),
    ),
);
