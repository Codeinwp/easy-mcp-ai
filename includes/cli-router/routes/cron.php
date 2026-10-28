<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_cron = static function ( string $method, bool $write, array $params, ?string $preflight, ?string $describe, ?string $network, array $extra = array() ): array {
    $route = array(
        'route'   => 'own',
        'handler' => 'Cron_Handler',
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
    'cron event delete'     => $emai_cron( 'delete', true, array( 'hook', 'due-now', 'exclude', 'all' ), 'preflight_delete', 'describe_delete', 'manage_network_options', array( 'bulk' => 50 ) ),
    'cron event list'       => $emai_cron( 'list_', false, array( 'fields', 'field', 'format' ), 'preflight_list', null, 'manage_network_options', array( 'generic' => true ) ),
    'cron event run'        => array(
        'route'   => 'delegate',
        'adapter' => 'Cron_Adapter',
        'method'  => 'run',
        'tools'   => array( 'wp_run_cron_event' ),
        'write'   => true,
        'params'  => array( 'hook', 'due-now', 'exclude', 'all' ),
        'plan'    => 'plan_run',
        'bulk'    => 50,
    ),
    'cron event schedule'   => $emai_cron( 'schedule', true, array( 'hook', 'next-run', 'recurrence' ), 'preflight_hook', 'describe_schedule', 'manage_network_options', array( 'generic' => true ) ),
    'cron event unschedule' => $emai_cron( 'unschedule', true, array( 'hook' ), 'preflight_hook', 'describe_unschedule', 'manage_network_options' ),
    'cron schedule list'    => $emai_cron( 'schedule_list', false, array( 'fields', 'field', 'format' ), null, null, null ),
    'cron test'             => array(
        'route'  => 'refuse',
        'reason' => 'its loopback to wp-cron.php waits inside this request: on one PHP worker it fails after its timeout (measured), which a filter can lengthen. Use the real wp binary.',
    ),
);
