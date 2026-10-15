<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'search-replace' => array(
        'command' => 'search-replace',
        'fields' => null,
        'longdesc' => 'Searches through all rows in a selection of tables and replaces
appearances of the first string with the second string.

By default, the command uses tables registered to the `$wpdb` object. On
multisite, this will just be the tables for the current site unless
`--network` is specified.

Search/replace intelligently handles PHP serialized data, and does not
change primary key values.

## OPTIONS

<old>
: A string to search for within the database.

<new>
: Replace instances of the first string with this new string.

[<table>...]
: List of database tables to restrict the replacement to. Wildcards are
supported, e.g. `\'wp_*options\'` or `\'wp_post*\'`.

[--dry-run]
: Run the entire search/replace operation and show report, but don\'t save
changes to the database.

[--network]
: Search/replace through all the tables registered to $wpdb in a
multisite install.

[--all-tables-with-prefix]
: Enable replacement on any tables that match the table prefix even if
not registered on $wpdb.

[--all-tables]
: Enable replacement on ALL tables in the database, regardless of the
prefix, and even if not registered on $wpdb. Overrides --network
and --all-tables-with-prefix.

[--export[=<file>]]
: Write transformed data as SQL file instead of saving replacements to
the database. If <file> is not supplied, will output to STDOUT.

[--export_insert_size=<rows>]
: Define number of rows in single INSERT statement when doing SQL export.
You might want to change this depending on your database configuration
(e.g. if you need to do fewer queries). Default: 50

[--skip-tables=<tables>]
: Do not perform the replacement on specific tables. Use commas to
specify multiple tables. Wildcards are supported, e.g. `\'wp_*options\'` or `\'wp_post*\'`.

[--skip-columns=<columns>]
: Do not perform the replacement on specific columns. Use commas to
specify multiple columns.

[--include-columns=<columns>]
: Perform the replacement on specific columns. Use commas to
specify multiple columns.

[--precise]
: Force the use of PHP (instead of SQL) which is more thorough,
but slower.

[--recurse-objects]
: Enable recursing into objects to replace strings. Defaults to true;
pass --no-recurse-objects to disable.

[--verbose]
: Prints rows to the console as they\'re updated.

[--regex]
: Runs the search using a regular expression (without delimiters).
Warning: search-replace will take about 15-20x longer when using --regex.

[--regex-flags=<regex-flags>]
: Pass PCRE modifiers to regex search-replace (e.g. \'i\' for case-insensitivity).

[--regex-delimiter=<regex-delimiter>]
: The delimiter to use for the regex. It must be escaped if it appears in the search string. The default value is the result of `chr(1)`.

[--regex-limit=<regex-limit>]
: The maximum possible replacements for the regex per row (or per unserialized data bit per row). Defaults to -1 (no limit).

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - count
---

[--report]
: Produce report. Defaults to true.

[--report-changed-only]
: Report changed fields only. Defaults to false, unless logging, when it defaults to true.

[--log[=<file>]]
: Log the items changed. If <file> is not supplied or is "-", will output to STDOUT.
Warning: causes a significant slow down, similar or worse to enabling --precise or --regex.

[--before_context=<num>]
: For logging, number of characters to display before the old match and the new replacement. Default 40. Ignored if not logging.

[--after_context=<num>]
: For logging, number of characters to display after the old match and the new replacement. Default 40. Ignored if not logging.

## EXAMPLES

    # Search and replace but skip one column
    $ wp search-replace \'http://example.test\' \'http://example.com\' --skip-columns=guid

    # Run search/replace operation but dont save in database
    $ wp search-replace \'foo\' \'bar\' wp_posts wp_postmeta wp_terms --dry-run

    # Run case-insensitive regex search/replace operation (slow)
    $ wp search-replace \'\\[foo id="([0-9]+)"\' \'[bar id="\\1"\' --regex --regex-flags=\'i\'

    # Turn your production multisite database into a local dev database
    $ wp search-replace --url=example.com example.com example.test \'wp_*options\' wp_blogs wp_site --network

    # Search/replace to a SQL file without transforming the database
    $ wp search-replace foo bar --export=database.sql

    # Bash script: Search/replace production to development url (multisite compatible)
    #!/bin/bash
    if $(wp --url=http://example.com core is-installed --network); then
        wp search-replace --url=http://example.com \'http://example.com\' \'http://example.test\' --recurse-objects --network --skip-columns=guid --skip-tables=wp_users
    else
        wp search-replace \'http://example.com\' \'http://example.test\' --recurse-objects --skip-columns=guid --skip-tables=wp_users
    fi',
        'name' => 'search-replace',
        'params' => array(
            array(
                'name' => 'old',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'new',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'table',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'dry-run',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'all-tables-with-prefix',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'all-tables',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => '',
                'name' => 'export',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => true,
            ),
            array(
                'desc' => 'Define number of rows in single INSERT statement when doing SQL export.',
                'name' => 'export_insert_size',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Do not perform the replacement on specific tables. Use commas to',
                'name' => 'skip-tables',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Do not perform the replacement on specific columns. Use commas to',
                'name' => 'skip-columns',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Perform the replacement on specific columns. Use commas to',
                'name' => 'include-columns',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'precise',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'recurse-objects',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'verbose',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'regex',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Pass PCRE modifiers to regex search-replace (e.g. \'i\' for case-insensitivity).',
                'name' => 'regex-flags',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The delimiter to use for the regex. It must be escaped if it appears in the search string. The default value is the result of `chr(1)`.',
                'name' => 'regex-delimiter',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The maximum possible replacements for the regex per row (or per unserialized data bit per row). Defaults to -1 (no limit).',
                'name' => 'regex-limit',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'count',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'report',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'report-changed-only',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => '',
                'name' => 'log',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => true,
            ),
            array(
                'desc' => 'For logging, number of characters to display before the old match and the new replacement. Default 40. Ignored if not logging.',
                'name' => 'before_context',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'For logging, number of characters to display after the old match and the new replacement. Default 40. Ignored if not logging.',
                'name' => 'after_context',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Searches/replaces strings in the database.',
        'synopsis' => '<old> <new> [<table>...] [--dry-run] [--network] [--all-tables-with-prefix] [--all-tables] [--export[=<file>]] [--export_insert_size=<rows>] [--skip-tables=<tables>] [--skip-columns=<columns>] [--include-columns=<columns>] [--precise] [--recurse-objects] [--verbose] [--regex] [--regex-flags=<regex-flags>] [--regex-delimiter=<regex-delimiter>] [--regex-limit=<regex-limit>] [--format=<format>] [--report] [--report-changed-only] [--log[=<file>]] [--before_context=<num>] [--after_context=<num>]',
    ),
);
