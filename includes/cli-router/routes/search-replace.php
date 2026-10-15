<?php
















if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'search-replace' => array(
        'route'          => 'delegate',
        'adapter'        => 'Search_Replace_Adapter',
        'method'         => 'run',
        'tools'          => array( 'wp_privileged_search_replace_run' ),
        'write'          => true,
        'preflight'      => 'preflight_run',
        'params'         => array(
            'old',
            'new',
            'table',
            'dry-run',
            'network',
            'all-tables-with-prefix',
            'all-tables',
            'skip-tables',
            'skip-columns',
            'include-columns',
            'precise',
            'recurse-objects',
            'regex',
            'regex-flags',
            'regex-delimiter',
            'regex-limit',
            'format',
            'report',
            'report-changed-only',
            'log',
            'before_context',
            'after_context',
        ),
        'refused_params' => array(
            'export'             => 'it writes an SQL export of the database, to a file on the server or to the output, and wp_cli_run offers no database export',
            'export_insert_size' => 'it applies only to --export, which wp_cli_run refuses',
            'verbose'            => 'WP-CLI prints how long each column took, and the search-replace tool reports no timings; the report table already lists every column searched',
        ),
    ),
);
