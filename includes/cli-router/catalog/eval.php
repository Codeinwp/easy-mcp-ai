<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'eval' => array(
        'command' => 'eval',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Note: because code is executed within a method, global variables need
to be explicitly globalized.

## OPTIONS

<php-code>
: The code to execute, as a string.

[--skip-wordpress]
: Execute code without loading WordPress.

## EXAMPLES

    # Display WordPress content directory.
    $ wp eval \'echo WP_CONTENT_DIR;\'
    /var/www/wordpress/wp-content

    # Generate a random number.
    $ wp eval \'echo rand();\' --skip-wordpress
    479620423',
        'name' => 'eval',
        'params' => array(
            array(
                'name' => 'php-code',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'skip-wordpress',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Executes arbitrary PHP code.',
        'synopsis' => '<php-code> [--skip-wordpress]',
    ),
);
