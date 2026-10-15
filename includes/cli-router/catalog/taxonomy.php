<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'taxonomy get' => array(
        'command' => 'taxonomy get',
        'fields' => array(
            'default' => array(
                'name',
                'label',
                'description',
                'object_type',
                'show_tagcloud',
                'hierarchical',
                'public',
                'labels',
                'cap',
            ),
            'optional' => array(
                'count',
            ),
        ),
        'longdesc' => '## OPTIONS

<taxonomy>
: Taxonomy slug.

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

These fields will be displayed by default for the specified taxonomy:

* name
* label
* description
* object_type
* show_tagcloud
* hierarchical
* public
* labels
* cap

These fields are optionally available:

* count

## EXAMPLES

    # Get details of `category` taxonomy.
    $ wp taxonomy get category --fields=name,label,object_type
    +-------------+------------+
    | Field       | Value      |
    +-------------+------------+
    | name        | category   |
    | label       | Categories |
    | object_type | ["post"]   |
    +-------------+------------+

    # Get capabilities of \'post_tag\' taxonomy.
    $ wp taxonomy get post_tag --field=cap
    {"manage_terms":"manage_categories","edit_terms":"manage_categories","delete_terms":"manage_categories","assign_terms":"edit_posts"}',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'taxonomy',
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
        'shortdesc' => 'Gets details about a registered taxonomy.',
        'synopsis' => '<taxonomy> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'taxonomy list' => array(
        'command' => 'taxonomy list',
        'fields' => array(
            'default' => array(
                'name',
                'label',
                'description',
                'object_type',
                'show_tagcloud',
                'hierarchical',
                'public',
            ),
            'optional' => array(
                'count',
            ),
        ),
        'longdesc' => '## OPTIONS

[--<field>=<value>]
: Filter by one or more fields (see get_taxonomies() first parameter for a list of available fields).

[--field=<field>]
: Prints the value of a single field for each taxonomy.

[--fields=<fields>]
: Limit the output to specific taxonomy fields.

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

These fields will be displayed by default for each term:

* name
* label
* description
* object_type
* show_tagcloud
* hierarchical
* public

These fields are optionally available:

* count

## EXAMPLES

    # List all taxonomies.
    $ wp taxonomy list --format=csv
    name,label,description,object_type,show_tagcloud,hierarchical,public
    category,Categories,,post,1,1,1
    post_tag,Tags,,post,1,,1
    nav_menu,"Navigation Menus",,nav_menu_item,,,
    link_category,"Link Categories",,link,1,,
    post_format,Format,,post,,,1

    # List all taxonomies with \'post\' object type.
    $ wp taxonomy list --object_type=post --fields=name,public
    +-------------+--------+
    | name        | public |
    +-------------+--------+
    | category    | 1      |
    | post_tag    | 1      |
    | post_format | 1      |
    +-------------+--------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Prints the value of a single field for each taxonomy.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific taxonomy fields.',
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
        'shortdesc' => 'Lists registered taxonomies.',
        'synopsis' => '[--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
);
