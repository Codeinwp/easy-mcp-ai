<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'maintenance-mode activate' => array(
        'command' => 'maintenance-mode activate',
        'fields' => null,
        'hook' => 'after_wp_load',
        'longdesc' => '## OPTIONS

[--force]
: Force maintenance mode activation operation.

## EXAMPLES

    $ wp maintenance-mode activate
    Enabling Maintenance mode...
    Success: Activated Maintenance mode.',
        'name' => 'activate',
        'params' => array(
            array(
                'name' => 'force',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Activates maintenance mode.',
        'synopsis' => '[--force]',
    ),
    'maintenance-mode deactivate' => array(
        'command' => 'maintenance-mode deactivate',
        'fields' => null,
        'hook' => 'after_wp_load',
        'longdesc' => '## EXAMPLES

    $ wp maintenance-mode deactivate
    Disabling Maintenance mode...
    Success: Deactivated Maintenance mode.',
        'name' => 'deactivate',
        'params' => array(),
        'shortdesc' => 'Deactivates maintenance mode.',
        'synopsis' => '',
    ),
    'maintenance-mode is-active' => array(
        'command' => 'maintenance-mode is-active',
        'fields' => null,
        'hook' => 'after_wp_load',
        'longdesc' => '## EXAMPLES

    $ wp maintenance-mode is-active
    $ echo $?
    1',
        'name' => 'is-active',
        'params' => array(),
        'shortdesc' => 'Detects maintenance mode status.',
        'synopsis' => '',
    ),
    'maintenance-mode status' => array(
        'command' => 'maintenance-mode status',
        'fields' => null,
        'hook' => 'after_wp_load',
        'longdesc' => '## EXAMPLES

    $ wp maintenance-mode status
    Maintenance mode is active.',
        'name' => 'status',
        'params' => array(),
        'shortdesc' => 'Displays maintenance mode status.',
        'synopsis' => '',
    ),
);
