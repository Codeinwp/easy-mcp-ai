<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'eval-file' => array(
        'command' => 'eval-file',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Note: because code is executed within a method, global variables need
to be explicitly globalized.

## OPTIONS

<file>
: The path to the PHP file to execute.  Use \'-\' to run code from STDIN.

[<arg>...]
: One or more positional arguments to pass to the file. They are placed in the $args variable.

[--skip-wordpress]
: Load and execute file without loading WordPress.

[--use-include]
: Process the provided file via include instead of evaluating its contents.',
        'name' => 'eval-file',
        'params' => array(
            array(
                'name' => 'file',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'arg',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'skip-wordpress',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'use-include',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Loads and executes a PHP file.',
        'synopsis' => '<file> [<arg>...] [--skip-wordpress] [--use-include]',
    ),
);
