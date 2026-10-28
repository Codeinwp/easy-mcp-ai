<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'import' => array(
        'command' => 'import',
        'fields' => null,
        'longdesc' => 'Provides a command line interface to the WordPress Importer plugin, for
performing data migrations.

Use `define( \'IMPORT_DEBUG\', true );` for more verbosity during importing.

## OPTIONS

<file>...
: Path to one or more valid WXR files for importing. Directories are also accepted.

--authors=<authors>
: How the author mapping should be handled. Options are \'create\', \'mapping.csv\', or \'skip\'. The first will create any non-existent users from the WXR file. The second will read author mapping associations from a CSV, or create a CSV for editing if the file path doesn\'t exist. The CSV requires two columns, and a header row like "old_user_login,new_user_login". The last option will skip any author mapping.

[--skip=<data-type>]
: Skip importing specific data. Supported options are: \'attachment\' and \'image_resize\' (skip time-consuming thumbnail generation).

## EXAMPLES

    # Import content from a WXR file
    $ wp import example.wordpress.2016-06-21.xml --authors=create
    Starting the import process...
    Processing post #1 ("Hello world!") (post_type: post)
    -- 1 of 1
    -- Tue, 21 Jun 2016 05:31:12 +0000
    -- Imported post as post_id #1
    Success: Finished importing from \'example.wordpress.2016-06-21.xml\' file.',
        'name' => 'import',
        'params' => array(
            array(
                'name' => 'file',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'How the author mapping should be handled. Options are \'create\', \'mapping.csv\', or \'skip\'. The first will create any non-existent users from the WXR file. The second will read author mapping associations from a CSV, or create a CSV for editing if the file path doesn\'t exist. The CSV requires two columns, and a header row like "old_user_login,new_user_login". The last option will skip any author mapping.',
                'name' => 'authors',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Skip importing specific data. Supported options are: \'attachment\' and \'image_resize\' (skip time-consuming thumbnail generation).',
                'name' => 'skip',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Imports content from a given WXR file.',
        'synopsis' => '<file>... --authors=<authors> [--skip=<data-type>]',
    ),
);
