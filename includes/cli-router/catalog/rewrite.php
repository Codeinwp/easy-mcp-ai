<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'rewrite flush' => array(
        'command' => 'rewrite flush',
        'fields' => null,
        'longdesc' => 'Resets WordPress\' rewrite rules based on registered post types, etc.

To regenerate a .htaccess file with WP-CLI, you\'ll need to add the mod_rewrite module
to your wp-cli.yml or config.yml. For example:

```
apache_modules:
  - mod_rewrite
```

## OPTIONS

[--hard]
: Perform a hard flush - update `.htaccess` rules as well as rewrite rules in database. Works only on single site installs.

## EXAMPLES

    $ wp rewrite flush
    Success: Rewrite rules flushed.',
        'name' => 'flush',
        'params' => array(
            array(
                'name' => 'hard',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Flushes rewrite rules.',
        'synopsis' => '[--hard]',
    ),
    'rewrite list' => array(
        'command' => 'rewrite list',
        'fields' => array(
            'default' => array(
                'match',
                'query',
                'source',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[--match=<url>]
: Show rewrite rules matching a particular URL.

[--source=<source>]
: Show rewrite rules from a particular source.

[--fields=<fields>]
: Limit the output to specific fields. Defaults to match,query,source.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - count
  - yaml
---

## EXAMPLES

    $ wp rewrite list --format=csv
    match,query,source
    ^wp-json/?$,index.php?rest_route=/,other
    ^wp-json/(.*)?,index.php?rest_route=/$matches[1],other
    category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$,index.php?category_name=$matches[1]&feed=$matches[2],category
    category/(.+?)/(feed|rdf|rss|rss2|atom)/?$,index.php?category_name=$matches[1]&feed=$matches[2],category
    category/(.+?)/embed/?$,index.php?category_name=$matches[1]&embed=true,category',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'Show rewrite rules matching a particular URL.',
                'name' => 'match',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Show rewrite rules from a particular source.',
                'name' => 'source',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific fields. Defaults to match,query,source.',
                'name' => 'fields',
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
                    'csv',
                    'json',
                    'count',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets a list of the current rewrite rules.',
        'synopsis' => '[--match=<url>] [--source=<source>] [--fields=<fields>] [--format=<format>]',
    ),
    'rewrite structure' => array(
        'command' => 'rewrite structure',
        'fields' => null,
        'longdesc' => 'Sets the post permalink structure to the specified pattern.

To regenerate a .htaccess file with WP-CLI, you\'ll need to add
the mod_rewrite module to your [WP-CLI config](https://make.wordpress.org/cli/handbook/config/#config-files).
For example:

```
apache_modules:
  - mod_rewrite
```

## OPTIONS

<permastruct>
: The new permalink structure to apply.

[--category-base=<base>]
: Set the base for category permalinks, i.e. \'/category/\'.

[--tag-base=<base>]
: Set the base for tag permalinks, i.e. \'/tag/\'.

[--hard]
: Perform a hard flush - update `.htaccess` rules as well as rewrite rules in database.

## EXAMPLES

    $ wp rewrite structure \'/%year%/%monthnum%/%postname%/\'
    Success: Rewrite structure set.',
        'name' => 'structure',
        'params' => array(
            array(
                'name' => 'permastruct',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Set the base for category permalinks, i.e. \'/category/\'.',
                'name' => 'category-base',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set the base for tag permalinks, i.e. \'/tag/\'.',
                'name' => 'tag-base',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'hard',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Updates the permalink structure.',
        'synopsis' => '<permastruct> [--category-base=<base>] [--tag-base=<base>] [--hard]',
    ),
);
