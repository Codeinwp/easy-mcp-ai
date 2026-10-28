<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'super-admin add' => array(
        'command' => 'super-admin add',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>...
: One or more user IDs, user emails, or user logins.

## EXAMPLES

    # Grant super-admin privileges to the user.
    $ wp super-admin add superadmin2
    Success: Granted super-admin capabilities to 1 user.',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Grants super admin privileges to one or more users.',
        'synopsis' => '<user>...',
    ),
    'super-admin list' => array(
        'command' => 'super-admin list',
        'fields' => null,
        'longdesc' => '## OPTIONS

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
  - ids
  - yaml
---

## EXAMPLES

    # List user with super-admin capabilities.
    $ wp super-admin list
    supervisor
    administrator',
        'name' => 'list',
        'params' => array(
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
                    'ids',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists users with super admin capabilities.',
        'synopsis' => '[--format=<format>]',
    ),
    'super-admin remove' => array(
        'command' => 'super-admin remove',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>...
: One or more user IDs, user emails, or user logins.

## EXAMPLES

    # Revoke super-admin privileges from the user.
    $ wp super-admin remove superadmin2
    Success: Revoked super-admin capabilities from 1 user.',
        'name' => 'remove',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Removes super admin privileges from one or more users.',
        'synopsis' => '<user>...',
    ),
);
