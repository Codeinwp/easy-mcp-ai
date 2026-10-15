<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'help' => array(
        'command' => 'help',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<command>...]
: Get help on a specific command.

## EXAMPLES

    # get help for `core` command
    wp help core

    # get help for `core download` subcommand
    wp help core download',
        'name' => 'help',
        'params' => array(
            array(
                'name' => 'command',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Gets help on WP-CLI, or on a specific command.',
        'synopsis' => '[<command>...]',
    ),
);
