<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'post-type get' => array(
        'command' => 'post-type get',
        'fields' => array(
            'default' => array(
                'name',
                'label',
                'description',
                'hierarchical',
                'public',
                'capability_type',
                'labels',
                'cap',
                'supports',
            ),
            'optional' => array(
                'count',
            ),
        ),
        'longdesc' => '## OPTIONS

<post-type>
: Post type slug

[--field=<field>]
: Instead of returning the whole taxonomy, returns the value of a single field.

[--fields=<fields>]
: Limit the output to specific fields. Defaults to all fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for the specified post type:

* name
* label
* description
* hierarchical
* public
* capability_type
* labels
* cap
* supports

These fields are optionally available:

* count

## EXAMPLES

    # Get details about the \'page\' post type.
    $ wp post-type get page --fields=name,label,hierarchical --format=json
    {"name":"page","label":"Pages","hierarchical":true}',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'post-type',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Instead of returning the whole taxonomy, returns the value of a single field.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific fields. Defaults to all fields.',
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
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets details about a registered post type.',
        'synopsis' => '<post-type> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'post-type list' => array(
        'command' => 'post-type list',
        'fields' => array(
            'default' => array(
                'name',
                'label',
                'description',
                'hierarchical',
                'public',
                'capability_type',
            ),
            'optional' => array(
                'count',
            ),
        ),
        'longdesc' => '## OPTIONS

[--<field>=<value>]
: Filter by one or more fields (see get_post_types() first parameter for a list of available fields).

[--field=<field>]
: Prints the value of a single field for each post type.

[--fields=<fields>]
: Limit the output to specific post type fields.

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

## AVAILABLE FIELDS

These fields will be displayed by default for each post type:

* name
* label
* description
* hierarchical
* public
* capability_type

These fields are optionally available:

* count

## EXAMPLES

    # List registered post types
    $ wp post-type list --format=csv
    name,label,description,hierarchical,public,capability_type
    post,Posts,,,1,post
    page,Pages,,1,1,page
    attachment,Media,,,1,post
    revision,Revisions,,,,post
    nav_menu_item,"Navigation Menu Items",,,,post

    # List post types with \'post\' capability type
    $ wp post-type list --capability_type=post --fields=name,public
    +---------------+--------+
    | name          | public |
    +---------------+--------+
    | post          | 1      |
    | attachment    | 1      |
    | revision      |        |
    | nav_menu_item |        |
    +---------------+--------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Prints the value of a single field for each post type.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific post type fields.',
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
        'shortdesc' => 'Lists registered post types.',
        'synopsis' => '[--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
);
