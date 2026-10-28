<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'site activate' => array(
        'command' => 'site activate',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to activate. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be activated. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site activate 123
    Success: Site 123 activated.

     $ wp site activate --slug=demo
     Success: Site 123 marked as activated.',
        'name' => 'activate',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be activated. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Activates one or more sites.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site archive' => array(
        'command' => 'site archive',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to archive. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to archive. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site archive 123
    Success: Site 123 archived.

    $ wp site archive --slug=demo
    Success: Site 123 archived.',
        'name' => 'archive',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to archive. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Archives one or more sites.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site create' => array(
        'command' => 'site create',
        'fields' => null,
        'longdesc' => '## OPTIONS

--slug=<slug>
: Path for the new site. Subdomain on subdomain installs, directory on subdirectory installs.

[--title=<title>]
: Title of the new site. Default: prettified slug.

[--email=<email>]
: Email for admin user. User will be created if none exists. Assignment to super admin if not included.

[--network_id=<network-id>]
: Network to associate new site with. Defaults to current network (typically 1).

[--private]
: If set, the new site will be non-public (not indexed)

[--porcelain]
: If set, only the site id will be output on success.

## EXAMPLES

    $ wp site create --slug=example
    Success: Site 3 created: http://www.example.com/example/',
        'name' => 'create',
        'params' => array(
            array(
                'desc' => 'Path for the new site. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Title of the new site. Default: prettified slug.',
                'name' => 'title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Email for admin user. User will be created if none exists. Assignment to super admin if not included.',
                'name' => 'email',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Network to associate new site with. Defaults to current network (typically 1).',
                'name' => 'network_id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'private',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Creates a site in a multisite installation.',
        'synopsis' => '--slug=<slug> [--title=<title>] [--email=<email>] [--network_id=<network-id>] [--private] [--porcelain]',
    ),
    'site deactivate' => array(
        'command' => 'site deactivate',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to deactivate. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be deactivated. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site deactivate 123
    Success: Site 123 deactivated.

    $ wp site deactivate --slug=demo
    Success: Site 123 deactivated.',
        'name' => 'deactivate',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be deactivated. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Deactivates one or more sites.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site delete' => array(
        'command' => 'site delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<site-id>]
: The id of the site to delete. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be deleted. Subdomain on subdomain installs, directory on subdirectory installs.

[--yes]
: Answer yes to the confirmation message.

[--keep-tables]
: Delete the blog from the list, but don\'t drop its tables.

## EXAMPLES

    $ wp site delete 123
    Are you sure you want to delete the http://www.example.com/example site? [y/n] y
    Success: The site at \'http://www.example.com/example\' was deleted.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'site-id',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be deleted. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'yes',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'keep-tables',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Deletes a site in a multisite installation.',
        'synopsis' => '[<site-id>] [--slug=<slug>] [--yes] [--keep-tables]',
    ),
    'site empty' => array(
        'command' => 'site empty',
        'fields' => null,
        'longdesc' => 'Truncates posts, comments, and terms tables to empty a site of its
content. Doesn\'t affect site configuration (options) or users.

If running a persistent object cache, make sure to flush the cache
after emptying the site, as the cache values will be invalid otherwise.

To also empty custom database tables, you\'ll need to hook into command
execution:

```
WP_CLI::add_hook( \'after_invoke:site empty\', function(){
    global $wpdb;
    foreach( array( \'p2p\', \'p2pmeta\' ) as $table ) {
        $table = $wpdb->$table;
        $wpdb->query( "TRUNCATE $table" );
    }
});
```

## OPTIONS

[--uploads]
: Also delete *all* files in the site\'s uploads directory.

[--yes]
: Proceed to empty the site without a confirmation prompt.

## EXAMPLES

    $ wp site empty
    Are you sure you want to empty the site at http://www.example.com of all posts, links, comments, and terms? [y/n] y
    Success: The site at \'http://www.example.com\' was emptied.',
        'name' => 'empty',
        'params' => array(
            array(
                'name' => 'uploads',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'yes',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Empties a site of its content (posts, comments, terms, and meta).',
        'synopsis' => '[--uploads] [--yes]',
    ),
    'site generate' => array(
        'command' => 'site generate',
        'fields' => null,
        'longdesc' => 'Creates a specified number of new sites.

## OPTIONS

[--count=<number>]
: How many sites to generates?
---
default: 100
---

[--slug=<slug>]
: Path for the new site. Subdomain on subdomain installs, directory on subdirectory installs.

[--email=<email>]
: Email for admin user. User will be created if none exists. Assignment to super admin if not included.

[--network_id=<network-id>]
: Network to associate new site with. Defaults to current network (typically 1).

[--private]
: If set, the new site will be non-public (not indexed)

[--format=<format>]
: Render output in a particular format.
---
default: progress
options:
 - progress
 - ids
---

## EXAMPLES

   # Generate 10 sites.
   $ wp site generate --count=10
   Generating sites  100% [================================================] 0:01 / 0:04',
        'name' => 'generate',
        'params' => array(
            array(
                'default' => 100,
                'desc' => 'How many sites to generates?',
                'name' => 'count',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Path for the new site. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Email for admin user. User will be created if none exists. Assignment to super admin if not included.',
                'name' => 'email',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Network to associate new site with. Defaults to current network (typically 1).',
                'name' => 'network_id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'private',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'default' => 'progress',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'progress',
                    'ids',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Generate some sites.',
        'synopsis' => '[--count=<number>] [--slug=<slug>] [--email=<email>] [--network_id=<network-id>] [--private] [--format=<format>]',
    ),
    'site list' => array(
        'command' => 'site list',
        'fields' => array(
            'default' => array(
                'blog_id',
                'url',
                'last_updated',
                'registered',
            ),
            'optional' => array(
                'site_id',
                'domain',
                'path',
                'public',
                'archived',
                'mature',
                'spam',
                'deleted',
                'lang_id',
            ),
        ),
        'longdesc' => '## OPTIONS

[--network=<id>]
: The network to which the sites belong.

[--<field>=<value>]
: Filter by one or more fields (see "Available Fields" section). However,
\'url\' isn\'t an available filter, as it comes from \'home\' in wp_options.

[--site__in=<value>]
: Only list the sites with these blog_id values (comma-separated).

[--site_user=<value>]
: Only list the sites with this user.

[--field=<field>]
: Prints the value of a single field for each site.

[--fields=<fields>]
: Comma-separated list of fields to show.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - count
  - ids
  - json
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each site:

* blog_id
* url
* last_updated
* registered

These fields are optionally available:

* site_id
* domain
* path
* public
* archived
* mature
* spam
* deleted
* lang_id

## EXAMPLES

    # Output a simple list of site URLs
    $ wp site list --field=url
    http://www.example.com/
    http://www.example.com/subdir/',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'The network to which the sites belong.',
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Only list the sites with these blog_id values (comma-separated).',
                'name' => 'site__in',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Only list the sites with this user.',
                'name' => 'site_user',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Prints the value of a single field for each site.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Comma-separated list of fields to show.',
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
                    'count',
                    'ids',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists all sites in a multisite installation.',
        'synopsis' => '[--network=<id>] [--<field>=<value>] [--site__in=<value>] [--site_user=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'site mature' => array(
        'command' => 'site mature',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to set as mature. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be set as mature. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site mature 123
    Success: Site 123 marked as mature.

    $ wp site mature --slug=demo
    Success: Site 123 marked as mature.',
        'name' => 'mature',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be set as mature. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Sets one or more sites as mature.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site meta add' => array(
        'command' => 'site meta add',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<key>
: The name of the meta field to create.

[<value>]
: The value of the meta field. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Add a meta field.',
        'synopsis' => '<id> <key> [<value>] [--format=<format>]',
    ),
    'site meta delete' => array(
        'command' => 'site meta delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

[<key>]
: The name of the meta field to delete.

[<value>]
: The value to delete. If omitted, all rows with key will deleted.

[--all]
: Delete all meta for the object.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Delete a meta field.',
        'synopsis' => '<id> [<key>] [<value>] [--all]',
    ),
    'site meta get' => array(
        'command' => 'site meta get',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<key>
: The name of the meta field to get.

[--single]
: Whether to return a single value.

[--format=<format>]
: Get value in a particular format.
---
default: var_export
options:
  - var_export
  - json
  - yaml
---',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'single',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'default' => 'var_export',
                'desc' => 'Get value in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'var_export',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Get meta field value.',
        'synopsis' => '<id> <key> [--single] [--format=<format>]',
    ),
    'site meta list' => array(
        'command' => 'site meta list',
        'fields' => array(
            'default' => array(
                'blog_id',
                'meta_key',
                'meta_value',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<id>
: ID for the object.

[--keys=<keys>]
: Limit output to metadata of specific keys.

[--fields=<fields>]
: Limit the output to specific row fields. Defaults to id,meta_key,meta_value.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - yaml
  - count
---

[--orderby=<fields>]
: Set orderby which field.
---
default: id
options:
 - id
 - meta_key
 - meta_value
---

[--order=<order>]
: Set ascending or descending order.
---
default: asc
options:
 - asc
 - desc
---

[--unserialize]
: Unserialize meta_value output.',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Limit output to metadata of specific keys.',
                'name' => 'keys',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific row fields. Defaults to id,meta_key,meta_value.',
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
                    'yaml',
                    'count',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'id',
                'desc' => 'Set orderby which field.',
                'name' => 'orderby',
                'optional' => true,
                'options' => array(
                    'id',
                    'meta_key',
                    'meta_value',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'asc',
                'desc' => 'Set ascending or descending order.',
                'name' => 'order',
                'optional' => true,
                'options' => array(
                    'asc',
                    'desc',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'unserialize',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'List all metadata associated with an object.',
        'synopsis' => '<id> [--keys=<keys>] [--fields=<fields>] [--format=<format>] [--orderby=<fields>] [--order=<order>] [--unserialize]',
    ),
    'site meta patch' => array(
        'command' => 'site meta patch',
        'fields' => null,
        'longdesc' => '## OPTIONS

<action>
: Patch action to perform.
---
options:
  - insert
  - update
  - delete
---

<id>
: The ID of the object.

<key>
: The name of the meta field to update.

<key-path>...
: The name(s) of the keys within the value to locate the value to patch.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---',
        'name' => 'patch',
        'params' => array(
            array(
                'name' => 'action',
                'optional' => false,
                'options' => array(
                    'insert',
                    'update',
                    'delete',
                ),
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key-path',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Update a nested value for a meta field.',
        'synopsis' => '<action> <id> <key> <key-path>... [<value>] [--format=<format>]',
    ),
    'site meta pluck' => array(
        'command' => 'site meta pluck',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<key>
: The name of the meta field to get.

<key-path>...
: The name(s) of the keys within the value to locate the value to pluck.

[--format=<format>]
: The output format of the value.
---
default: plaintext
options:
  - plaintext
  - json
  - yaml',
        'name' => 'pluck',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key-path',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The output format of the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Get a nested value from a meta field.',
        'synopsis' => '<id> <key> <key-path>... [--format=<format>]',
    ),
    'site meta update' => array(
        'alias' => 'set',
        'command' => 'site meta update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<key>
: The name of the meta field to update.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Update a meta field.',
        'synopsis' => '<id> <key> [<value>] [--format=<format>]',
    ),
    'site option add' => array(
        'command' => 'site option add',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: The name of the site option to add.

[<value>]
: The value of the site option to add. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---

## EXAMPLES

    # Create a site option by reading a JSON file
    $ wp site option add my_option --format=json < config.json
    Success: Added \'my_option\' site option.',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Adds a site option.',
        'synopsis' => '<key> [<value>] [--format=<format>]',
    ),
    'site option delete' => array(
        'command' => 'site option delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: Key for the site option.

## EXAMPLES

    $ wp site option delete my_option
    Success: Deleted \'my_option\' site option.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Deletes a site option.',
        'synopsis' => '<key>',
    ),
    'site option get' => array(
        'command' => 'site option get',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: Key for the site option.

[--format=<format>]
: Get value in a particular format.
---
default: var_export
options:
  - var_export
  - json
  - yaml
---

## EXAMPLES

    # Get site upload filetypes
    $ wp site option get upload_filetypes
    jpg jpeg png gif mov avi mpg',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'var_export',
                'desc' => 'Get value in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'var_export',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets a site option.',
        'synopsis' => '<key> [--format=<format>]',
    ),
    'site option list' => array(
        'command' => 'site option list',
        'fields' => array(
            'default' => array(
                'meta_key',
                'meta_value',
            ),
            'optional' => array(
                'meta_id',
                'site_id',
                'size_bytes',
            ),
        ),
        'longdesc' => '## OPTIONS

[--search=<pattern>]
: Use wildcards ( * and ? ) to match option name.

[--site_id=<id>]
: Limit options to those of a particular site id.

[--field=<field>]
: Prints the value of a single field.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: The serialization format for the value. total_bytes displays the total size of matching options in bytes.
---
default: table
options:
  - table
  - json
  - csv
  - count
  - yaml
  - total_bytes
---

## AVAILABLE FIELDS

This field will be displayed by default for each matching option:

* meta_key
* meta_value

These fields are optionally available:

* meta_id
* site_id
* size_bytes

## EXAMPLES

    # List all site options beginning with "i2f_"
    $ wp site option list --search="i2f_*"
    +-------------+--------------+
    | meta_key    | meta_value   |
    +-------------+--------------+
    | i2f_version | 0.1.0        |
    +-------------+--------------+',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'Use wildcards ( * and ? ) to match option name.',
                'name' => 'search',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit options to those of a particular site id.',
                'name' => 'site_id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Prints the value of a single field.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific object fields.',
                'name' => 'fields',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'The serialization format for the value. total_bytes displays the total size of matching options in bytes.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'json',
                    'csv',
                    'count',
                    'yaml',
                    'total_bytes',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists site options.',
        'synopsis' => '[--search=<pattern>] [--site_id=<id>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'site option patch' => array(
        'command' => 'site option patch',
        'fields' => null,
        'longdesc' => '## OPTIONS

<action>
: Patch action to perform.
---
options:
  - insert
  - update
  - delete
---

<key>
: The option name.

<key-path>...
: The name(s) of the keys within the value to locate the value to patch.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---',
        'name' => 'patch',
        'params' => array(
            array(
                'name' => 'action',
                'optional' => false,
                'options' => array(
                    'insert',
                    'update',
                    'delete',
                ),
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key-path',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Updates a nested value in an option.',
        'synopsis' => '<action> <key> <key-path>... [<value>] [--format=<format>]',
    ),
    'site option pluck' => array(
        'command' => 'site option pluck',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: The option name.

<key-path>...
: The name(s) of the keys within the value to locate the value to pluck.

[--format=<format>]
: The output format of the value.
---
default: plaintext
options:
  - plaintext
  - json
  - yaml',
        'name' => 'pluck',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'key-path',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The output format of the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets a nested value from an option.',
        'synopsis' => '<key> <key-path>... [--format=<format>]',
    ),
    'site option update' => array(
        'alias' => 'set',
        'command' => 'site option update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: The name of the site option to update.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---

## EXAMPLES

    # Update a site option by reading from a file
    $ wp site option update my_option < value.txt
    Success: Updated \'my_option\' site option.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'value',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'plaintext',
                'desc' => 'The serialization format for the value.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'plaintext',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Updates a site option.',
        'synopsis' => '<key> [<value>] [--format=<format>]',
    ),
    'site private' => array(
        'command' => 'site private',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to set as private. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be set as private. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site private 123
    Success: Site 123 marked as private.

    $ wp site private --slug=demo
    Success: Site 123 marked as private.',
        'name' => 'private',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be set as private. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Sets one or more sites as private.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site public' => array(
        'command' => 'site public',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to set as public. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be set as public. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site public 123
    Success: Site 123 marked as public.

     $ wp site public --slug=demo
     Success: Site 123 marked as public.',
        'name' => 'public',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be set as public. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Sets one or more sites as public.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site spam' => array(
        'command' => 'site spam',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to be marked as spam. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be marked as spam. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site spam 123
    Success: Site 123 marked as spam.',
        'name' => 'spam',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be marked as spam. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Marks one or more sites as spam.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site switch-language' => array(
        'command' => 'site switch-language',
        'fields' => null,
        'longdesc' => '## OPTIONS

<language>
: Language code to activate.

## EXAMPLES

    $ wp site switch-language ja
    Success: Language activated.',
        'name' => 'switch-language',
        'params' => array(
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Activates a given language.',
        'synopsis' => '<language>',
    ),
    'site unarchive' => array(
        'command' => 'site unarchive',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to unarchive. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to unarchive. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site unarchive 123
    Success: Site 123 unarchived.

    $ wp site unarchive --slug=demo
    Success: Site 123 unarchived.',
        'name' => 'unarchive',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to unarchive. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Unarchives one or more sites.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site unmature' => array(
        'command' => 'site unmature',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to set as unmature. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be set as unmature. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site unmature 123
    Success: Site 123 marked as unmature.

    $ wp site unmature --slug=demo
    Success: Site 123 marked as unmature.',
        'name' => 'unmature',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be set as unmature. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Sets one or more sites as immature.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
    'site unspam' => array(
        'command' => 'site unspam',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<id>...]
: One or more IDs of sites to remove from spam. If not provided, you must set the --slug parameter.

[--slug=<slug>]
: Path of the site to be removed from spam. Subdomain on subdomain installs, directory on subdirectory installs.

## EXAMPLES

    $ wp site unspam 123
    Success: Site 123 removed from spam.',
        'name' => 'unspam',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Path of the site to be removed from spam. Subdomain on subdomain installs, directory on subdirectory installs.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Removes one or more sites from spam.',
        'synopsis' => '[<id>...] [--slug=<slug>]',
    ),
);
