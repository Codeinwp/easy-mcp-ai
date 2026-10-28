<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'widget add' => array(
        'command' => 'widget add',
        'fields' => null,
        'longdesc' => 'Creates a new widget entry in the database, and associates it with the
sidebar.

## OPTIONS

<name>
: Widget name.

<sidebar-id>
: ID for the corresponding sidebar.

[<position>]
: Widget\'s current position within the sidebar. Defaults to last

[--<field>=<value>]
: Widget option to add, with its new value

## EXAMPLES

    # Add a new calendar widget to sidebar-1 with title "Calendar"
    $ wp widget add calendar sidebar-1 2 --title="Calendar"
    Success: Added widget to sidebar.',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'sidebar-id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'position',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
        ),
        'shortdesc' => 'Adds a widget to a sidebar.',
        'synopsis' => '<name> <sidebar-id> [<position>] [--<field>=<value>]',
    ),
    'widget deactivate' => array(
        'command' => 'widget deactivate',
        'fields' => null,
        'longdesc' => 'Moves widgets to Inactive Widgets.

## OPTIONS

<widget-id>...
: Unique ID for the widget(s)

## EXAMPLES

    # Deactivate the recent-comments-2 widget.
    $ wp widget deactivate recent-comments-2
    Success: 1 widget deactivated.',
        'name' => 'deactivate',
        'params' => array(
            array(
                'name' => 'widget-id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Deactivates one or more widgets from an active sidebar.',
        'synopsis' => '<widget-id>...',
    ),
    'widget delete' => array(
        'command' => 'widget delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<widget-id>...
: Unique ID for the widget(s)

## EXAMPLES

    # Delete the recent-comments-2 widget from its sidebar.
    $ wp widget delete recent-comments-2
    Success: Deleted 1 of 1 widgets.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'widget-id',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Deletes one or more widgets from a sidebar.',
        'synopsis' => '<widget-id>...',
    ),
    'widget list' => array(
        'command' => 'widget list',
        'fields' => array(
            'default' => array(
                'name',
                'id',
                'position',
                'options',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<sidebar-id>
: ID for the corresponding sidebar.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - ids
  - json
  - count
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each widget:

* name
* id
* position
* options

There are no optionally available fields.

## EXAMPLES

    $ wp widget list sidebar-1 --fields=name,id --format=csv
    name,id
    meta,meta-5
    search,search-3',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'sidebar-id',
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
                    'ids',
                    'json',
                    'count',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists widgets associated with a sidebar.',
        'synopsis' => '<sidebar-id> [--fields=<fields>] [--format=<format>]',
    ),
    'widget move' => array(
        'command' => 'widget move',
        'fields' => null,
        'longdesc' => 'Changes the order of a widget in its existing sidebar, or moves it to a
new sidebar.

## OPTIONS

<widget-id>
: Unique ID for the widget

[--position=<position>]
: Assign the widget to a new position.

[--sidebar-id=<sidebar-id>]
: Assign the widget to a new sidebar

## EXAMPLES

    # Change position of widget
    $ wp widget move recent-comments-2 --position=2
    Success: Widget moved.

    # Move widget to Inactive Widgets
    $ wp widget move recent-comments-2 --sidebar-id=wp_inactive_widgets
    Success: Widget moved.',
        'name' => 'move',
        'params' => array(
            array(
                'name' => 'widget-id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Assign the widget to a new position.',
                'name' => 'position',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Assign the widget to a new sidebar',
                'name' => 'sidebar-id',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Moves the position of a widget.',
        'synopsis' => '<widget-id> [--position=<position>] [--sidebar-id=<sidebar-id>]',
    ),
    'widget reset' => array(
        'command' => 'widget reset',
        'fields' => null,
        'longdesc' => 'Removes all widgets from the sidebar and places them in Inactive Widgets.

## OPTIONS

[<sidebar-id>...]
: One or more sidebars to reset.

[--all]
: If set, all sidebars will be reset.

## EXAMPLES

    # Reset a sidebar
    $ wp widget reset sidebar-1
    Success: Sidebar \'sidebar-1\' reset.

    # Reset multiple sidebars
    $ wp widget reset sidebar-1 sidebar-2
    Success: Sidebar \'sidebar-1\' reset.
    Success: Sidebar \'sidebar-2\' reset.

    # Reset all sidebars
    $ wp widget reset --all
    Success: Sidebar \'sidebar-1\' reset.
    Success: Sidebar \'sidebar-2\' reset.
    Success: Sidebar \'sidebar-3\' reset.',
        'name' => 'reset',
        'params' => array(
            array(
                'name' => 'sidebar-id',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Resets sidebar.',
        'synopsis' => '[<sidebar-id>...] [--all]',
    ),
    'widget update' => array(
        'command' => 'widget update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<widget-id>
: Unique ID for the widget

[--<field>=<value>]
: Field to update, with its new value

## EXAMPLES

    # Change calendar-1 widget title to "Our Calendar"
    $ wp widget update calendar-1 --title="Our Calendar"
    Success: Widget updated.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'widget-id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
        ),
        'shortdesc' => 'Updates options for an existing widget.',
        'synopsis' => '<widget-id> [--<field>=<value>]',
    ),
);
