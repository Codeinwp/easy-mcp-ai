<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'cap add' => array(
        'command' => 'cap add',
        'fields' => null,
        'longdesc' => '## OPTIONS

<role>
: Key for the role.

<cap>...
: One or more capabilities to add.

[--grant]
: Adds the capability as an explicit boolean value, instead of implicitly defaulting to `true`.
---
default: true
options:
  - true
  - false
---

## EXAMPLES

    # Add \'spectate\' capability to \'author\' role.
    $ wp cap add author spectate
    Success: Added 1 capability to \'author\' role.',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'role',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'cap',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'grant',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Adds capabilities to a given role.',
        'synopsis' => '<role> <cap>... [--grant]',
    ),
    'cap list' => array(
        'command' => 'cap list',
        'fields' => null,
        'longdesc' => '## OPTIONS

<role>
: Key for the role.

[--format=<format>]
: Render output in a particular format.
---
default: list
options:
  - list
  - table
  - csv
  - json
  - count
  - yaml
---

[--show-grant]
: Display all capabilities defined for a role including grant.
---
default: false
---

## EXAMPLES

    # Display alphabetical list of Contributor capabilities.
    $ wp cap list \'contributor\' | sort
    delete_posts
    edit_posts
    level_0
    level_1
    read',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'role',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'list',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'list',
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
            array(
                'name' => 'show-grant',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Lists capabilities for a given role.',
        'synopsis' => '<role> [--format=<format>] [--show-grant]',
    ),
    'cap remove' => array(
        'command' => 'cap remove',
        'fields' => null,
        'longdesc' => '## OPTIONS

<role>
: Key for the role.

<cap>...
: One or more capabilities to remove.

## EXAMPLES

    # Remove \'spectate\' capability from \'author\' role.
    $ wp cap remove author spectate
    Success: Removed 1 capability from \'author\' role.',
        'name' => 'remove',
        'params' => array(
            array(
                'name' => 'role',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'cap',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Removes capabilities from a given role.',
        'synopsis' => '<role> <cap>...',
    ),
);
