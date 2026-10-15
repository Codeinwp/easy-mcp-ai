<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'export' => array(
        'command' => 'export',
        'fields' => null,
        'longdesc' => 'Generates one or more WXR files containing authors, terms, posts,
comments, and attachments. WXR files do not include site configuration
(options) or the attachment files themselves.

## OPTIONS

[--dir=<dirname>]
: Full path to directory where WXR export files should be stored. Defaults
to current working directory.

[--stdout]
: Output the whole XML using standard output (incompatible with --dir=)

[--skip_comments]
: Don\'t include comments in the WXR export file.

[--max_file_size=<MB>]
: A single export file should have this many megabytes. -1 for unlimited.
---
default: 15
---

[--filename_format=<format>]
: Use a custom format for export filenames. Defaults to \'{site}.wordpress.{date}.{n}.xml\'.

[--include_once=<before_posts>]
: Include specified export section only in the first export file. Valid options
are categories, tags, nav_menu_items, custom_taxonomies_terms. Separate multiple
sections with a comma. Defaults to none.

[--allow_orphan_terms]
: Export orphaned terms with `parent=0`, instead of throwing an exception.

## FILTERS

[--start_date=<date>]
: Export only posts published after this date, in format YYYY-MM-DD.

[--end_date=<date>]
: Export only posts published before this date, in format YYYY-MM-DD.

[--post_type=<post-type>]
: Export only posts with this post_type. Separate multiple post types with a
comma.
---
default: any
---

[--post_type__not_in=<post-type>]
: Export all post types except those identified. Separate multiple post types
with a comma. Defaults to none.

[--post__in=<pid>]
: Export all posts specified as a comma-separated or space-separated list of IDs.
Post\'s attachments won\'t be exported unless --with_attachments is specified.

[--with_attachments]
: Force including attachments in case --post__in has been specified.

[--start_id=<pid>]
: Export only posts with IDs greater than or equal to this post ID.

[--max_num_posts=<num>]
: Export no more than <num> posts (excluding attachments).

[--author=<author>]
: Export only posts by this author. Can be either user login or user ID.

[--category=<name|id>]
: Export only posts in this category.

[--post_status=<status>]
: Export only posts with this status.

## EXAMPLES

    # Export posts published by the user between given start and end date
    $ wp export --dir=/tmp/ --user=admin --post_type=post --start_date=2011-01-01 --end_date=2011-12-31
    Starting export process...
    Writing to file /tmp/staging.wordpress.2016-05-24.000.xml
    Success: All done with export.

    # Export posts by IDs
    $ wp export --dir=/tmp/ --post__in=123,124,125
    Starting export process...
    Writing to file /tmp/staging.wordpress.2016-05-24.000.xml
    Success: All done with export.

    # Export a random subset of content
    $ wp export --post__in="$(wp post list --post_type=post --orderby=rand --posts_per_page=8 --format=ids)"
    Starting export process...
    Writing to file /var/www/example.com/public_html/staging.wordpress.2016-05-24.000.xml
    Success: All done with export.',
        'name' => 'export',
        'params' => array(
            array(
                'desc' => 'Full path to directory where WXR export files should be stored. Defaults',
                'name' => 'dir',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'stdout',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip_comments',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'default' => 15,
                'desc' => 'A single export file should have this many megabytes. -1 for unlimited.',
                'name' => 'max_file_size',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Use a custom format for export filenames. Defaults to \'{site}.wordpress.{date}.{n}.xml\'.',
                'name' => 'filename_format',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Include specified export section only in the first export file. Valid options',
                'name' => 'include_once',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'allow_orphan_terms',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Export only posts published after this date, in format YYYY-MM-DD.',
                'name' => 'start_date',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Export only posts published before this date, in format YYYY-MM-DD.',
                'name' => 'end_date',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'any',
                'desc' => 'Export only posts with this post_type. Separate multiple post types with a',
                'name' => 'post_type',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Export all post types except those identified. Separate multiple post types',
                'name' => 'post_type__not_in',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Export all posts specified as a comma-separated or space-separated list of IDs.',
                'name' => 'post__in',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'with_attachments',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Export only posts with IDs greater than or equal to this post ID.',
                'name' => 'start_id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Export no more than <num> posts (excluding attachments).',
                'name' => 'max_num_posts',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Export only posts by this author. Can be either user login or user ID.',
                'name' => 'author',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Export only posts in this category.',
                'name' => 'category',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Export only posts with this status.',
                'name' => 'post_status',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Exports WordPress content to a WXR file.',
        'synopsis' => '[--dir=<dirname>] [--stdout] [--skip_comments] [--max_file_size=<MB>] [--filename_format=<format>] [--include_once=<before_posts>] [--allow_orphan_terms] [--start_date=<date>] [--end_date=<date>] [--post_type=<post-type>] [--post_type__not_in=<post-type>] [--post__in=<pid>] [--with_attachments] [--start_id=<pid>] [--max_num_posts=<num>] [--author=<author>] [--category=<name|id>] [--post_status=<status>]',
    ),
);
