<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'role create' => array(
        'command' => 'role create',
        'fields' => null,
        'longdesc' => '## OPTIONS

<role-key>
: The internal name of the role.

<role-name>
: The publicly visible name of the role.

[--clone=<role>]
: Clone capabilities from an existing role.

## EXAMPLES

    # Create role for Approver.
    $ wp role create approver Approver
    Success: Role with key \'approver\' created.

    # Create role for Product Administrator.
    $ wp role create productadmin "Product Administrator"
    Success: Role with key \'productadmin\' created.',
        'name' => 'create',
        'params' => array(
            array(
                'name' => 'role-key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'role-name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Clone capabilities from an existing role.',
                'name' => 'clone',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Creates a new role.',
        'synopsis' => '<role-key> <role-name> [--clone=<role>]',
    ),
    'role delete' => array(
        'command' => 'role delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<role-key>
: The internal name of the role.

## EXAMPLES

    # Delete approver role.
    $ wp role delete approver
    Success: Role with key \'approver\' deleted.

    # Delete productadmin role.
    $ wp role delete productadmin
    Success: Role with key \'productadmin\' deleted.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'role-key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Deletes an existing role.',
        'synopsis' => '<role-key>',
    ),
    'role exists' => array(
        'command' => 'role exists',
        'fields' => null,
        'longdesc' => 'Exits with return code 0 if the role exists, 1 if it does not.

## OPTIONS

<role-key>
: The internal name of the role.

## EXAMPLES

    # Check if a role exists.
    $ wp role exists editor
    Success: Role with ID \'editor\' exists.',
        'name' => 'exists',
        'params' => array(
            array(
                'name' => 'role-key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Checks if a role exists.',
        'synopsis' => '<role-key>',
    ),
    'role list' => array(
        'command' => 'role list',
        'fields' => array(
            'default' => array(
                'name',
                'role',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[--fields=<fields>]
: Limit the output to specific object fields.

[--field=<field>]
: Prints the value of a single field.

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

These fields will be displayed by default for each role:

* name
* role

There are no optional fields.

## EXAMPLES

    # List roles.
    $ wp role list --fields=role --format=csv
    role
    administrator
    editor
    author
    contributor
    subscriber',
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
                'desc' => 'Prints the value of a single field.',
                'name' => 'field',
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
        'shortdesc' => 'Lists all roles.',
        'synopsis' => '[--fields=<fields>] [--field=<field>] [--format=<format>]',
    ),
    'role reset' => array(
        'command' => 'role reset',
        'fields' => null,
        'longdesc' => 'Uses WordPress\' `populate_roles()` function to put one or more
roles back into the state they were at in the a fresh
WordPress install. Removes any capabilities that were added,
and restores any capabilities that were removed. Custom roles
are not affected.

## OPTIONS

[<role-key>...]
: The internal name of one or more roles to reset.

[--all]
: If set, all default roles will be reset.

## EXAMPLES

    # Reset three roles.
    $ wp role reset administrator author contributor
    Restored 1 capability to and removed 0 capabilities from \'administrator\' role.
    No changes necessary for \'author\' role.
    No changes necessary for \'contributor\' role.
    Success: 1 of 3 roles reset.

    # Reset a custom role.
    $ wp role reset custom_role
    Custom role \'custom_role\' not affected.
    Error: Must specify a default role to reset.

    # Reset all default roles.
    $ wp role reset --all
    Success: All default roles reset.',
        'name' => 'reset',
        'params' => array(
            array(
                'name' => 'role-key',
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
        'shortdesc' => 'Resets any default role to default capabilities.',
        'synopsis' => '[<role-key>...] [--all]',
    ),
);
