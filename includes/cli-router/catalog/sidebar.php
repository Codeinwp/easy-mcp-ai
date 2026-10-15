<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'sidebar list' => array(
        'command' => 'sidebar list',
        'fields' => array(
            'default' => array(
                'name',
                'id',
                'description',
            ),
            'optional' => array(
                'class',
                'before_widget',
                'after_widget',
                'before_title',
                'after_title',
            ),
        ),
        'longdesc' => '## OPTIONS

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - ids
  - count
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each sidebar:

* name
* id
* description

These fields are optionally available:

* class
* before_widget
* after_widget
* before_title
* after_title

## EXAMPLES

    $ wp sidebar list --fields=name,id --format=csv
    name,id
    "Widget Area",sidebar-1
    "Inactive Widgets",wp_inactive_widgets',
        'name' => 'list',
        'params' => array(
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
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'json',
                    'ids',
                    'count',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists registered sidebars.',
        'synopsis' => '[--fields=<fields>] [--format=<format>]',
    ),
);
