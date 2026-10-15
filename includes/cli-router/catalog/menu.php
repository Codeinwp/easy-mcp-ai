<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'menu create' => array(
        'command' => 'menu create',
        'fields' => null,
        'longdesc' => '## OPTIONS

<menu-name>
: A descriptive name for the menu.

[--porcelain]
: Output just the new menu id.

## EXAMPLES

    $ wp menu create "My Menu"
    Success: Created menu 200.',
        'name' => 'create',
        'params' => array(
            array(
                'name' => 'menu-name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Creates a new menu.',
        'synopsis' => '<menu-name> [--porcelain]',
    ),
    'menu delete' => array(
        'command' => 'menu delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<menu>...
: The name, slug, or term ID for the menu(s).

## EXAMPLES

    $ wp menu delete "My Menu"
    Deleted menu \'My Menu\'.
    Success: Deleted 1 of 1 menus.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'menu',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Deletes one or more menus.',
        'synopsis' => '<menu>...',
    ),
    'menu item add-custom' => array(
        'command' => 'menu item add-custom',
        'fields' => null,
        'longdesc' => '## OPTIONS

<menu>
: The name, slug, or term ID for the menu.

<title>
: Title for the link.

<link>
: Target URL for the link.

[--description=<description>]
: Set a custom description for the menu item.

[--attr-title=<attr-title>]
: Set a custom title attribute for the menu item.

[--target=<target>]
: Set a custom link target for the menu item.

[--classes=<classes>]
: Set a custom link classes for the menu item.

[--position=<position>]
: Specify the position of this menu item.

[--parent-id=<parent-id>]
: Make this menu item a child of another menu item.

[--porcelain]
: Output just the new menu item id.

## EXAMPLES

    $ wp menu item add-custom sidebar-menu Apple http://apple.com
    Success: Menu item added.',
        'name' => 'add-custom',
        'params' => array(
            array(
                'name' => 'menu',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'title',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'link',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Set a custom description for the menu item.',
                'name' => 'description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom title attribute for the menu item.',
                'name' => 'attr-title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom link target for the menu item.',
                'name' => 'target',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom link classes for the menu item.',
                'name' => 'classes',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the position of this menu item.',
                'name' => 'position',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Make this menu item a child of another menu item.',
                'name' => 'parent-id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Adds a custom menu item.',
        'synopsis' => '<menu> <title> <link> [--description=<description>] [--attr-title=<attr-title>] [--target=<target>] [--classes=<classes>] [--position=<position>] [--parent-id=<parent-id>] [--porcelain]',
    ),
    'menu item add-post' => array(
        'command' => 'menu item add-post',
        'fields' => null,
        'longdesc' => '## OPTIONS

<menu>
: The name, slug, or term ID for the menu.

<post-id>
: Post ID to add to the menu.

[--title=<title>]
: Set a custom title for the menu item.

[--link=<link>]
: Set a custom url for the menu item.

[--description=<description>]
: Set a custom description for the menu item.

[--attr-title=<attr-title>]
: Set a custom title attribute for the menu item.

[--target=<target>]
: Set a custom link target for the menu item.

[--classes=<classes>]
: Set a custom link classes for the menu item.

[--position=<position>]
: Specify the position of this menu item.

[--parent-id=<parent-id>]
: Make this menu item a child of another menu item.

[--porcelain]
: Output just the new menu item id.

## EXAMPLES

    $ wp menu item add-post sidebar-menu 33 --title="Custom Test Post"
    Success: Menu item added.',
        'name' => 'add-post',
        'params' => array(
            array(
                'name' => 'menu',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'post-id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Set a custom title for the menu item.',
                'name' => 'title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom url for the menu item.',
                'name' => 'link',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom description for the menu item.',
                'name' => 'description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom title attribute for the menu item.',
                'name' => 'attr-title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom link target for the menu item.',
                'name' => 'target',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom link classes for the menu item.',
                'name' => 'classes',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the position of this menu item.',
                'name' => 'position',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Make this menu item a child of another menu item.',
                'name' => 'parent-id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Adds a post as a menu item.',
        'synopsis' => '<menu> <post-id> [--title=<title>] [--link=<link>] [--description=<description>] [--attr-title=<attr-title>] [--target=<target>] [--classes=<classes>] [--position=<position>] [--parent-id=<parent-id>] [--porcelain]',
    ),
    'menu item add-term' => array(
        'command' => 'menu item add-term',
        'fields' => null,
        'longdesc' => '## OPTIONS

<menu>
: The name, slug, or term ID for the menu.

<taxonomy>
: Taxonomy of the term to be added.

<term-id>
: Term ID of the term to be added.

[--title=<title>]
: Set a custom title for the menu item.

[--link=<link>]
: Set a custom url for the menu item.

[--description=<description>]
: Set a custom description for the menu item.

[--attr-title=<attr-title>]
: Set a custom title attribute for the menu item.

[--target=<target>]
: Set a custom link target for the menu item.

[--classes=<classes>]
: Set a custom link classes for the menu item.

[--position=<position>]
: Specify the position of this menu item.

[--parent-id=<parent-id>]
: Make this menu item a child of another menu item.

[--porcelain]
: Output just the new menu item id.

## EXAMPLES

    $ wp menu item add-term sidebar-menu post_tag 24
    Success: Menu item added.',
        'name' => 'add-term',
        'params' => array(
            array(
                'name' => 'menu',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'term-id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Set a custom title for the menu item.',
                'name' => 'title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom url for the menu item.',
                'name' => 'link',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom description for the menu item.',
                'name' => 'description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom title attribute for the menu item.',
                'name' => 'attr-title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom link target for the menu item.',
                'name' => 'target',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom link classes for the menu item.',
                'name' => 'classes',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the position of this menu item.',
                'name' => 'position',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Make this menu item a child of another menu item.',
                'name' => 'parent-id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'porcelain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Adds a taxonomy term as a menu item.',
        'synopsis' => '<menu> <taxonomy> <term-id> [--title=<title>] [--link=<link>] [--description=<description>] [--attr-title=<attr-title>] [--target=<target>] [--classes=<classes>] [--position=<position>] [--parent-id=<parent-id>] [--porcelain]',
    ),
    'menu item delete' => array(
        'command' => 'menu item delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<db-id>...
: Database ID for the menu item(s).

## EXAMPLES

    $ wp menu item delete 45
    Success: Deleted 1 of 1 menu items.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'db-id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Deletes one or more items from a menu.',
        'synopsis' => '<db-id>...',
    ),
    'menu item list' => array(
        'command' => 'menu item list',
        'fields' => array(
            'default' => array(
                'db_id',
                'type',
                'title',
                'link',
                'position',
            ),
            'optional' => array(
                'menu_item_parent',
                'object_id',
                'object',
                'type',
                'type_label',
                'target',
                'attr_title',
                'description',
                'classes',
                'xfn',
            ),
        ),
        'longdesc' => '## OPTIONS

<menu>
: The name, slug, or term ID for the menu.

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
  - count
  - ids
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each menu item:

* db_id
* type
* title
* link
* position

These fields are optionally available:

* menu_item_parent
* object_id
* object
* type
* type_label
* target
* attr_title
* description
* classes
* xfn

## EXAMPLES

    $ wp menu item list main-menu
    +-------+-----------+-------------+---------------------------------+----------+
    | db_id | type      | title       | link                            | position |
    +-------+-----------+-------------+---------------------------------+----------+
    | 5     | custom    | Home        | http://example.com              | 1        |
    | 6     | post_type | Sample Page | http://example.com/sample-page/ | 2        |
    +-------+-----------+-------------+---------------------------------+----------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'menu',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
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
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'csv',
                    'json',
                    'count',
                    'ids',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets a list of items associated with a menu.',
        'synopsis' => '<menu> [--fields=<fields>] [--format=<format>]',
    ),
    'menu item update' => array(
        'command' => 'menu item update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<db-id>
: Database ID for the menu item.

[--title=<title>]
: Set a custom title for the menu item.

[--link=<link>]
: Set a custom url for the menu item.

[--description=<description>]
: Set a custom description for the menu item.

[--attr-title=<attr-title>]
: Set a custom title attribute for the menu item.

[--target=<target>]
: Set a custom link target for the menu item.

[--classes=<classes>]
: Set a custom link classes for the menu item.

[--position=<position>]
: Specify the position of this menu item.

[--parent-id=<parent-id>]
: Make this menu item a child of another menu item.

## EXAMPLES

    $ wp menu item update 45 --title=WordPress --link=\'http://wordpress.org\' --target=_blank --position=2
    Success: Menu item updated.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'db-id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Set a custom title for the menu item.',
                'name' => 'title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom url for the menu item.',
                'name' => 'link',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom description for the menu item.',
                'name' => 'description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom title attribute for the menu item.',
                'name' => 'attr-title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom link target for the menu item.',
                'name' => 'target',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set a custom link classes for the menu item.',
                'name' => 'classes',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the position of this menu item.',
                'name' => 'position',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Make this menu item a child of another menu item.',
                'name' => 'parent-id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Updates a menu item.',
        'synopsis' => '<db-id> [--title=<title>] [--link=<link>] [--description=<description>] [--attr-title=<attr-title>] [--target=<target>] [--classes=<classes>] [--position=<position>] [--parent-id=<parent-id>]',
    ),
    'menu list' => array(
        'command' => 'menu list',
        'fields' => array(
            'default' => array(
                'term_id',
                'name',
                'slug',
                'locations',
                'count',
            ),
            'optional' => array(
                'term_group',
                'term_taxonomy_id',
                'taxonomy',
                'description',
                'parent',
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
  - count
  - ids
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each menu:

* term_id
* name
* slug
* count

These fields are optionally available:

* term_group
* term_taxonomy_id
* taxonomy
* description
* parent
* locations

## EXAMPLES

    $ wp menu list
    +---------+----------+----------+-----------+-------+
    | term_id | name     | slug     | locations | count |
    +---------+----------+----------+-----------+-------+
    | 200     | My Menu  | my-menu  |           | 0     |
    | 177     | Top Menu | top-menu | primary   | 7     |
    +---------+----------+----------+-----------+-------+',
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
                    'count',
                    'ids',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets a list of menus.',
        'synopsis' => '[--fields=<fields>] [--format=<format>]',
    ),
    'menu location assign' => array(
        'command' => 'menu location assign',
        'fields' => null,
        'longdesc' => '## OPTIONS

<menu>
: The name, slug, or term ID for the menu.

<location>
: Location\'s slug.

## EXAMPLES

    $ wp menu location assign primary-menu primary
    Success: Assigned location primary to menu primary-menu.',
        'name' => 'assign',
        'params' => array(
            array(
                'name' => 'menu',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'location',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Assigns a location to a menu.',
        'synopsis' => '<menu> <location>',
    ),
    'menu location list' => array(
        'command' => 'menu location list',
        'fields' => array(
            'default' => array(
                'location',
                'description',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

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
  - ids
---

## AVAILABLE FIELDS

These fields will be displayed by default for each location:

* name
* description

## EXAMPLES

    $ wp menu location list
    +----------+-------------------+
    | location | description       |
    +----------+-------------------+
    | primary  | Primary Menu      |
    | social   | Social Links Menu |
    +----------+-------------------+',
        'name' => 'list',
        'params' => array(
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
                    'ids',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists locations for the current theme.',
        'synopsis' => '[--format=<format>]',
    ),
    'menu location remove' => array(
        'command' => 'menu location remove',
        'fields' => null,
        'longdesc' => '## OPTIONS

<menu>
: The name, slug, or term ID for the menu.

<location>
: Location\'s slug.

## EXAMPLES

    $ wp menu location remove primary-menu primary
    Success: Removed location from menu.',
        'name' => 'remove',
        'params' => array(
            array(
                'name' => 'menu',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'location',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Removes a location from a menu.',
        'synopsis' => '<menu> <location>',
    ),
);
