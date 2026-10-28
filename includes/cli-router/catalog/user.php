<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'user add-cap' => array(
        'command' => 'user add-cap',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: User ID, user email, or user login.

<cap>
: The capability to add.

## EXAMPLES

    # Add a capability for a user
    $ wp user add-cap john create_premium_item
    Success: Added \'create_premium_item\' capability for john (16).

    # Add a capability for a user
    $ wp user add-cap 15 edit_product
    Success: Added \'edit_product\' capability for johndoe (15).',
        'name' => 'add-cap',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'cap',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Adds a capability to a user.',
        'synopsis' => '<user> <cap>',
    ),
    'user add-role' => array(
        'command' => 'user add-role',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: User ID, user email, or user login.

[<role>...]
: Add the specified role(s) to the user.

## EXAMPLES

    $ wp user add-role 12 author
    Success: Added \'author\' role for johndoe (12).

    $ wp user add-role 12 author editor
    Success: Added \'author\', \'editor\' roles for johndoe (12).',
        'name' => 'add-role',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'role',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Adds a role for a user.',
        'synopsis' => '<user> [<role>...]',
    ),
    'user application-password create' => array(
        'command' => 'user application-password create',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to create a new application password for.

<app-name>
: Unique name of the application to create an application password for.

[--app-id=<app-id>]
: Application ID to attribute to the application password.

[--porcelain]
: Output just the new password.

## EXAMPLES

    # Create user application password
    $ wp user application-password create 123 myapp
    Success: Created application password.
    Password: ZG1bxdxdzjTwhsY8vK8l1C65

    # Only print the password without any chrome
    $ wp user application-password create 123 myapp --porcelain
    ZG1bxdxdzjTwhsY8vK8l1C65

    # Create user application with a custom application ID for internal tracking
    $ wp user application-password create 123 myapp --app-id=42 --porcelain
    ZG1bxdxdzjTwhsY8vK8l1C65',
        'name' => 'create',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'app-name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Application ID to attribute to the application password.',
                'name' => 'app-id',
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
        'shortdesc' => 'Creates a new application password.',
        'synopsis' => '<user> <app-name> [--app-id=<app-id>] [--porcelain]',
    ),
    'user application-password delete' => array(
        'command' => 'user application-password delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to delete the application password for.

[<uuid>...]
: Comma-separated list of UUIDs of the application passwords to delete.

[--all]
: Delete all of the user\'s application password.

## EXAMPLES

    # Delete an existing application password
    $ wp user application-password delete 123 6633824d-c1d7-4f79-9dd5-4586f734d69e
    Success: Deleted 1 of 1 application password.

    # Delete all of the user\'s application passwords
    $ wp user application-password delete 123 --all
    Success: Deleted all application passwords.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'uuid',
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
        'shortdesc' => 'Delete an existing application password.',
        'synopsis' => '<user> [<uuid>...] [--all]',
    ),
    'user application-password exists' => array(
        'command' => 'user application-password exists',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to check the existence of an application password for.

<app-name>
: Name of the application to check the existence of an application password for.

## EXAMPLES

    # Check if an application password for a given application exists
    $ wp user application-password exists 123 myapp
    $ echo $?
    1

    # Bash script for checking whether an application password exists and creating one if not
    if ! wp user application-password exists 123 myapp; then
        PASSWORD=$(wp user application-password create 123 myapp --porcelain)
    fi',
        'name' => 'exists',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'app-name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Checks whether an application password for a given application exists.',
        'synopsis' => '<user> <app-name>',
    ),
    'user application-password get' => array(
        'command' => 'user application-password get',
        'fields' => array(
            'default' => array(
                'uuid',
                'app_id',
                'name',
                'password',
                'created',
                'last_used',
                'last_ip',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to get the application password for.

<uuid>
: The universally unique ID of the application password.

[--field=<field>]
: Prints the value of a single field for the application password.

[--fields=<fields>]
: Limit the output to specific fields.

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

## EXAMPLES

    # Get a specific application password and only show app name and created timestamp
    $ wp user application-password get 123 6633824d-c1d7-4f79-9dd5-4586f734d69e --fields=name,created
    +--------+------------+
    | name   | created    |
    +--------+------------+
    | myapp  | 1638395611 |
    +--------+------------+',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'uuid',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Prints the value of a single field for the application password.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific fields.',
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
        'shortdesc' => 'Gets a specific application password.',
        'synopsis' => '<user> <uuid> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'user application-password list' => array(
        'command' => 'user application-password list',
        'fields' => array(
            'default' => array(
                'uuid',
                'app_id',
                'name',
                'password',
                'created',
                'last_used',
                'last_ip',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to get application passwords for.

[--<field>=<value>]
: Filter the list by a specific field.

[--field=<field>]
: Prints the value of a single field for each application password.

[--fields=<fields>]
: Limit the output to specific fields.

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

[--orderby=<fields>]
: Set orderby which field.
---
default: created
options:
 - uuid
 - app_id
 - name
 - password
 - created
 - last_used
 - last_ip
---

[--order=<order>]
: Set ascending or descending order.
---
default: desc
options:
 - asc
 - desc
---

## EXAMPLES

    # List user application passwords and only show app name and password hash
    $ wp user application-password list 123 --fields=name,password
    +--------+------------------------------------+
    | name   | password                           |
    +--------+------------------------------------+
    | myapp  | $P$BVGeou1CUot114YohIemgpwxQCzb8O/ |
    +--------+------------------------------------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'user',
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
            array(
                'desc' => 'Prints the value of a single field for each application password.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific fields.',
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
                    'ids',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'created',
                'desc' => 'Set orderby which field.',
                'name' => 'orderby',
                'optional' => true,
                'options' => array(
                    'uuid',
                    'app_id',
                    'name',
                    'password',
                    'created',
                    'last_used',
                    'last_ip',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'desc',
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
        ),
        'shortdesc' => 'Lists all application passwords associated with a user.',
        'synopsis' => '<user> [--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>] [--orderby=<fields>] [--order=<order>]',
    ),
    'user application-password record-usage' => array(
        'command' => 'user application-password record-usage',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to update the application password for.

<uuid>
: The universally unique ID of the application password.

## EXAMPLES

    # Record usage of an application password
    $ wp user application-password record-usage 123 6633824d-c1d7-4f79-9dd5-4586f734d69e
    Success: Recorded application password usage.',
        'name' => 'record-usage',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'uuid',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Record usage of an application password.',
        'synopsis' => '<user> <uuid>',
    ),
    'user application-password update' => array(
        'command' => 'user application-password update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to update the application password for.

<uuid>
: The universally unique ID of the application password.

[--<field>=<value>]
: Update the <field> with a new <value>. Currently supported fields: name.

## EXAMPLES

    # Update an existing application password
    $ wp user application-password update 123 6633824d-c1d7-4f79-9dd5-4586f734d69e --name=newappname
    Success: Updated application password.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'uuid',
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
        'shortdesc' => 'Updates an existing application password.',
        'synopsis' => '<user> <uuid> [--<field>=<value>]',
    ),
    'user check-password' => array(
        'command' => 'user check-password',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email or user ID of the user to check credentials for.

<user_pass>
: A string that contains the plain text password for the user.

[--escape-chars]
: Escape password with `wp_slash()` to mimic the same behavior as `wp-login.php`.

## EXAMPLES

    # Check whether given credentials are valid; exit status 0 if valid, otherwise 1
    $ wp user check-password admin adminpass
    $ echo $?
    1

    # Bash script for checking whether given credentials are valid or not
    if ! $(wp user check-password admin adminpass); then
     notify-send "Invalid Credentials";
    fi',
        'name' => 'check-password',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'user_pass',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'escape-chars',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Checks if a user\'s password is valid or not.',
        'synopsis' => '<user> <user_pass> [--escape-chars]',
    ),
    'user create' => array(
        'command' => 'user create',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user-login>
: The login of the user to create.

<user-email>
: The email address of the user to create.

[--role=<role>]
: The role of the user to create. Default: default role. Possible values
include \'administrator\', \'editor\', \'author\', \'contributor\', \'subscriber\'.

[--user_pass=<password>]
: The user password. Default: randomly generated.

[--user_registered=<yyyy-mm-dd-hh-ii-ss>]
: The date the user registered. Default: current date.

[--display_name=<name>]
: The display name.

[--user_nicename=<nice_name>]
: A string that contains a URL-friendly name for the user. The default is the user\'s username.

[--user_url=<url>]
: A string containing the user\'s URL for the user\'s web site.

[--nickname=<nickname>]
: The user\'s nickname, defaults to the user\'s username.

[--first_name=<first_name>]
: The user\'s first name.

[--last_name=<last_name>]
: The user\'s last name.

[--description=<description>]
: A string containing content about the user.

[--rich_editing=<rich_editing>]
: A string for whether to enable the rich editor or not. False if not empty.

[--send-email]
: Send an email to the user with their new account details.

[--porcelain]
: Output just the new user id.

## EXAMPLES

    # Create user
    $ wp user create bob bob@example.com --role=author
    Success: Created user 3.
    Password: k9**&I4vNH(&

    # Create user without showing password upon success
    $ wp user create ann ann@example.com --porcelain
    4',
        'name' => 'create',
        'params' => array(
            array(
                'name' => 'user-login',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'user-email',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'The role of the user to create. Default: default role. Possible values',
                'name' => 'role',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The user password. Default: randomly generated.',
                'name' => 'user_pass',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date the user registered. Default: current date.',
                'name' => 'user_registered',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The display name.',
                'name' => 'display_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string that contains a URL-friendly name for the user. The default is the user\'s username.',
                'name' => 'user_nicename',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string containing the user\'s URL for the user\'s web site.',
                'name' => 'user_url',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The user\'s nickname, defaults to the user\'s username.',
                'name' => 'nickname',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The user\'s first name.',
                'name' => 'first_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The user\'s last name.',
                'name' => 'last_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string containing content about the user.',
                'name' => 'description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string for whether to enable the rich editor or not. False if not empty.',
                'name' => 'rich_editing',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'send-email',
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
        'shortdesc' => 'Creates a new user.',
        'synopsis' => '<user-login> <user-email> [--role=<role>] [--user_pass=<password>] [--user_registered=<yyyy-mm-dd-hh-ii-ss>] [--display_name=<name>] [--user_nicename=<nice_name>] [--user_url=<url>] [--nickname=<nickname>] [--first_name=<first_name>] [--last_name=<last_name>] [--description=<description>] [--rich_editing=<rich_editing>] [--send-email] [--porcelain]',
    ),
    'user delete' => array(
        'command' => 'user delete',
        'fields' => null,
        'longdesc' => 'On multisite, `wp user delete` only removes the user from the current
site. Include `--network` to also remove the user from the database, but
make sure to reassign their posts prior to deleting the user.

## OPTIONS

<user>...
: The user login, user email, or user ID of the user(s) to delete.

[--network]
: On multisite, delete the user from the entire network.

[--reassign=<user-id>]
: User ID to reassign the posts to.

[--yes]
: Answer yes to any confirmation prompts.

## EXAMPLES

    # Delete user 123 and reassign posts to user 567
    $ wp user delete 123 --reassign=567
    Success: Removed user 123 from http://example.com.

    # Delete all contributors and reassign their posts to user 2
    $ wp user delete $(wp user list --role=contributor --field=ID) --reassign=2
    Success: Removed user 813 from http://example.com.
    Success: Removed user 578 from http://example.com.

    # Delete all contributors in batches of 100 (avoid error: argument list too long: wp)
    $ wp user delete $(wp user list --role=contributor --field=ID | head -n 100)',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'User ID to reassign the posts to.',
                'name' => 'reassign',
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
        ),
        'shortdesc' => 'Deletes one or more users from the current site.',
        'synopsis' => '<user>... [--network] [--reassign=<user-id>] [--yes]',
    ),
    'user exists' => array(
        'command' => 'user exists',
        'fields' => null,
        'longdesc' => 'Displays a success message if the user does exist.

## OPTIONS

<id>
: The ID of the user to check.

## EXAMPLES

    # The user exists.
    $ wp user exists 1337
    Success: User with ID 1337 exists.
    $ echo $?
    0

    # The user does not exist.
    $ wp user exists 10000
    $ echo $?
    1',
        'name' => 'exists',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Verifies whether a user exists.',
        'synopsis' => '<id>',
    ),
    'user generate' => array(
        'command' => 'user generate',
        'fields' => null,
        'longdesc' => 'Creates a specified number of new users with dummy data.

## OPTIONS

[--count=<number>]
: How many users to generate?
---
default: 100
---

[--role=<role>]
: The role of the generated users. Default: default role from WP

[--format=<format>]
: Render output in a particular format.
---
default: progress
options:
  - progress
  - ids
---

## EXAMPLES

    # Add meta to every generated users.
    $ wp user generate --format=ids --count=3 | xargs -d \' \' -I % wp user meta add % foo bar
    Success: Added custom field.
    Success: Added custom field.
    Success: Added custom field.',
        'name' => 'generate',
        'params' => array(
            array(
                'default' => 100,
                'desc' => 'How many users to generate?',
                'name' => 'count',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The role of the generated users. Default: default role from WP',
                'name' => 'role',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
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
        'shortdesc' => 'Generates some users.',
        'synopsis' => '[--count=<number>] [--role=<role>] [--format=<format>]',
    ),
    'user get' => array(
        'command' => 'user get',
        'fields' => array(
            'default' => array(
                'ID',
                'user_login',
                'display_name',
                'user_email',
                'user_registered',
                'roles',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<user>
: User ID, user email, or user login.

[--field=<field>]
: Instead of returning the whole user, returns the value of a single field.

[--fields=<fields>]
: Get a specific subset of the user\'s fields.

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

## EXAMPLES

    # Get user
    $ wp user get 12 --field=login
    supervisor

    # Get user and export to JSON file
    $ wp user get bob --format=json > bob.json',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Instead of returning the whole user, returns the value of a single field.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Get a specific subset of the user\'s fields.',
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
        'shortdesc' => 'Gets details about a user.',
        'synopsis' => '<user> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'user import-csv' => array(
        'command' => 'user import-csv',
        'fields' => null,
        'longdesc' => 'If the user already exists (matching the email address or login), then
the user is updated unless the `--skip-update` flag is used.

## OPTIONS

<file>
: The local or remote CSV file of users to import. If \'-\', then reads from STDIN.

[--send-email]
: Send an email to new users with their account details.

[--skip-update]
: Don\'t update users that already exist.

## EXAMPLES

    # Import users from local CSV file
    $ wp user import-csv /path/to/users.csv
    Success: bobjones created.
    Success: newuser1 created.
    Success: existinguser created.

    # Import users from remote CSV file
    $ wp user import-csv http://example.com/users.csv

    Sample users.csv file:

    user_login,user_email,display_name,role
    bobjones,bobjones@example.com,Bob Jones,contributor
    newuser1,newuser1@example.com,New User,author
    existinguser,existinguser@example.com,Existing User,administrator',
        'name' => 'import-csv',
        'params' => array(
            array(
                'name' => 'file',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'send-email',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-update',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Imports users from a CSV file.',
        'synopsis' => '<file> [--send-email] [--skip-update]',
    ),
    'user list' => array(
        'command' => 'user list',
        'fields' => array(
            'default' => array(
                'ID',
                'user_login',
                'display_name',
                'user_email',
                'user_registered',
                'roles',
            ),
            'optional' => array(
                'user_pass',
                'user_nicename',
                'user_url',
                'user_activation_key',
                'user_status',
                'spam',
                'deleted',
                'caps',
                'cap_key',
                'allcaps',
                'filter',
                'url',
            ),
        ),
        'longdesc' => 'Display WordPress users based on all arguments supported by
[WP_User_Query()](https://developer.wordpress.org/reference/classes/wp_user_query/prepare_query/).

## OPTIONS

[--role=<role>]
: Only display users with a certain role.

[--<field>=<value>]
: Control output by one or more arguments of WP_User_Query().

[--network]
: List all users in the network for multisite.

[--field=<field>]
: Prints the value of a single field for each user.

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

These fields will be displayed by default for each user:

* ID
* user_login
* display_name
* user_email
* user_registered
* roles

These fields are optionally available:

* user_pass
* user_nicename
* user_url
* user_activation_key
* user_status
* spam
* deleted
* caps
* cap_key
* allcaps
* filter
* url

## EXAMPLES

    # List user IDs
    $ wp user list --field=ID
    1

    # List users with administrator role
    $ wp user list --role=administrator --format=csv
    ID,user_login,display_name,user_email,user_registered,roles
    1,supervisor,supervisor,supervisor@gmail.com,"2016-06-03 04:37:00",administrator

    # List users with only given fields
    $ wp user list --fields=display_name,user_email --format=json
    [{"display_name":"supervisor","user_email":"supervisor@gmail.com"}]

    # List users ordered by the \'last_activity\' meta value.
    $ wp user list --meta_key=last_activity --orderby=meta_value_num',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'Only display users with a certain role.',
                'name' => 'role',
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
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Prints the value of a single field for each user.',
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
        'shortdesc' => 'Lists users.',
        'synopsis' => '[--role=<role>] [--<field>=<value>] [--network] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'user list-caps' => array(
        'command' => 'user list-caps',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: User ID, user email, or login.

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

[--origin=<origin>]
: Render output in a particular format.
---
default: all
options:
  - all
  - user
  - role
---

[--exclude-role-names]
: Exclude capabilities that match role names from output.

## EXAMPLES

    $ wp user list-caps 21
    edit_product
    create_premium_item',
        'name' => 'list-caps',
        'params' => array(
            array(
                'name' => 'user',
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
                'default' => 'all',
                'desc' => 'Render output in a particular format.',
                'name' => 'origin',
                'optional' => true,
                'options' => array(
                    'all',
                    'user',
                    'role',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'exclude-role-names',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Lists all capabilities for a user.',
        'synopsis' => '<user> [--format=<format>] [--origin=<origin>] [--exclude-role-names]',
    ),
    'user meta add' => array(
        'command' => 'user meta add',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to add metadata for.

<key>
: The metadata key.

<value>
: The new metadata value.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---

## EXAMPLES

    # Add user meta
    $ wp user meta add 123 bio "Mary is an WordPress developer."
    Success: Added custom field.',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'user',
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
                'optional' => false,
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
        'shortdesc' => 'Adds a meta field.',
        'synopsis' => '<user> <key> <value> [--format=<format>]',
    ),
    'user meta delete' => array(
        'command' => 'user meta delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to delete metadata from.

<key>
: The metadata key.

[<value>]
: The value to delete. If omitted, all rows with key will deleted.

## EXAMPLES

    # Delete user meta
    $ wp user meta delete 123 bio
    Success: Deleted custom field.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'user',
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
        ),
        'shortdesc' => 'Deletes a meta field.',
        'synopsis' => '<user> <key> [<value>]',
    ),
    'user meta get' => array(
        'command' => 'user meta get',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to get metadata for.

<key>
: The metadata key.

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

## EXAMPLES

    # Get user meta
    $ wp user meta get 123 bio
    Mary is an WordPress developer.

    # Get the primary site of a user (for multisite)
    $ wp user meta get 2 primary_blog
    3',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'user',
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
        'shortdesc' => 'Gets meta field value.',
        'synopsis' => '<user> <key> [--format=<format>]',
    ),
    'user meta list' => array(
        'command' => 'user meta list',
        'fields' => array(
            'default' => array(
                'user_id',
                'meta_key',
                'meta_value',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to get metadata for.

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
  - count
  - yaml
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
: Unserialize meta_value output.

## EXAMPLES

    # List user meta
    $ wp user meta list 123 --keys=nickname,description,wp_capabilities
    +---------+-----------------+--------------------------------+
    | user_id | meta_key        | meta_value                     |
    +---------+-----------------+--------------------------------+
    | 123     | nickname        | supervisor                     |
    | 123     | description     | Mary is a WordPress developer. |
    | 123     | wp_capabilities | {"administrator":true}         |
    +---------+-----------------+--------------------------------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'user',
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
                    'count',
                    'yaml',
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
        'shortdesc' => 'Lists all metadata associated with a user.',
        'synopsis' => '<user> [--keys=<keys>] [--fields=<fields>] [--format=<format>] [--orderby=<fields>] [--order=<order>] [--unserialize]',
    ),
    'user meta patch' => array(
        'command' => 'user meta patch',
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
    'user meta pluck' => array(
        'command' => 'user meta pluck',
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
    'user meta update' => array(
        'alias' => 'set',
        'command' => 'user meta update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: The user login, user email, or user ID of the user to update metadata for.

<key>
: The metadata key.

<value>
: The new metadata value.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---

## EXAMPLES

    # Update user meta
    $ wp user meta update 123 bio "Mary is an awesome WordPress developer."
    Success: Updated custom field \'bio\'.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'user',
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
                'optional' => false,
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
        'shortdesc' => 'Updates a meta field.',
        'synopsis' => '<user> <key> <value> [--format=<format>]',
    ),
    'user remove-cap' => array(
        'command' => 'user remove-cap',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: User ID, user email, or user login.

<cap>
: The capability to be removed.

[--force]
: Forcefully remove a capability.

## EXAMPLES

    $ wp user remove-cap 11 publish_newsletters
    Success: Removed \'publish_newsletters\' cap for supervisor (11).

    $ wp user remove-cap 11 publish_posts
    Error: The \'publish_posts\' cap for supervisor (11) is inherited from a role.

    $ wp user remove-cap 11 nonexistent_cap
    Error: No such \'nonexistent_cap\' cap for supervisor (11).

    $ wp user remove-cap 11 publish_newsletters --force
    Success: Removed \'publish_newsletters\' cap for supervisor (11).',
        'name' => 'remove-cap',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'cap',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'force',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Removes a user\'s capability.',
        'synopsis' => '<user> <cap> [--force]',
    ),
    'user remove-role' => array(
        'command' => 'user remove-role',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: User ID, user email, or user login.

[<role>...]
: Remove the specified role(s) from the user.

## EXAMPLES

    $ wp user remove-role 12 author
    Success: Removed \'author\' role for johndoe (12).

    $ wp user remove-role 12 author editor
    Success: Removed \'author\', \'editor\' roles for johndoe (12).',
        'name' => 'remove-role',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'role',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Removes a user\'s role.',
        'synopsis' => '<user> [<role>...]',
    ),
    'user reset-password' => array(
        'command' => 'user reset-password',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>...
: one or more user logins or IDs.

[--skip-email]
: Don\'t send an email notification to the affected user(s).

[--show-password]
: Show the new password(s).

[--porcelain]
: Output only the new password(s).

## EXAMPLES

    # Reset the password for two users and send them the change email.
    $ wp user reset-password admin editor
    Reset password for admin.
    Reset password for editor.
    Success: Passwords reset for 2 users.

    # Reset and display the password.
    $ wp user reset-password editor --show-password
    Reset password for editor.
    Password: N6hAau0fXZMN#rLCIirdEGOh
    Success: Password reset for 1 user.

    # Reset the password for one user, displaying only the new password, and not sending the change email.
    $ wp user reset-password admin --skip-email --porcelain
    yV6BP*!d70wg

    # Reset password for all users.
    $ wp user reset-password $(wp user list --format=ids)
    Reset password for admin.
    Reset password for editor.
    Reset password for subscriber.
    Success: Passwords reset for 3 users.

    # Reset password for all users with a particular role.
    $ wp user reset-password $(wp user list --format=ids --role=administrator)
    Reset password for admin.
    Success: Password reset for 1 user.',
        'name' => 'reset-password',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'skip-email',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'show-password',
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
        'shortdesc' => 'Resets the password for one or more users.',
        'synopsis' => '<user>... [--skip-email] [--show-password] [--porcelain]',
    ),
    'user session destroy' => array(
        'command' => 'user session destroy',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: User ID, user email, or user login.

[<token>]
: The token of the session to destroy. Defaults to the most recently created session.

[--all]
: Destroy all of the user\'s sessions.

## EXAMPLES

    # Destroy the most recent session of the given user.
    $ wp user session destroy admin
    Success: Destroyed session. 3 sessions remaining.

    # Destroy a specific session of the given user.
    $ wp user session destroy admin e073ad8540a9c2...
    Success: Destroyed session. 2 sessions remaining.

    # Destroy all the sessions of the given user.
    $ wp user session destroy admin --all
    Success: Destroyed all sessions.

    # Destroy all sessions for all users.
    $ wp user list --field=ID | xargs -n 1 wp user session destroy --all
    Success: Destroyed all sessions.
    Success: Destroyed all sessions.',
        'name' => 'destroy',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'token',
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
        'shortdesc' => 'Destroy a session for the given user.',
        'synopsis' => '<user> [<token>] [--all]',
    ),
    'user session list' => array(
        'command' => 'user session list',
        'fields' => array(
            'default' => array(
                'token',
                'login_time',
                'expiration_time',
                'ip',
                'ua',
            ),
            'optional' => array(
                'expiration',
                'login',
            ),
        ),
        'longdesc' => 'Note: The `token` field does not return the actual token, but a hash of
it. The real token is not persisted and can only be found in the
corresponding cookies on the client side.

## OPTIONS

<user>
: User ID, user email, or user login.

[--fields=<fields>]
: Limit the output to specific fields.

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
  - ids
---

## AVAILABLE FIELDS

These fields will be displayed by default for each session:

* token
* login_time
* expiration_time
* ip
* ua

These fields are optionally available:

* expiration
* login

## EXAMPLES

    # List a user\'s sessions.
    $ wp user session list admin@example.com --format=csv
    login_time,expiration_time,ip,ua
    "2016-01-01 12:34:56","2016-02-01 12:34:56",127.0.0.1,"Mozilla/5.0..."',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Limit the output to specific fields.',
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
                    'ids',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'List sessions for the given user.',
        'synopsis' => '<user> [--fields=<fields>] [--format=<format>]',
    ),
    'user set-role' => array(
        'command' => 'user set-role',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>
: User ID, user email, or user login.

[<role>]
: Make the user have the specified role. If not passed, the default role is
used.

## EXAMPLES

    $ wp user set-role 12 author
    Success: Added johndoe (12) to http://example.com as author.',
        'name' => 'set-role',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'role',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Sets the user role.',
        'synopsis' => '<user> [<role>]',
    ),
    'user signup activate' => array(
        'command' => 'user signup activate',
        'fields' => null,
        'longdesc' => '## OPTIONS

<signup>...
: The signup ID, user login, user email, or activation key of the signup(s) to activate.

## EXAMPLES

    # Activate signup.
    $ wp user signup activate 2
    Signup 2 activated. Password: bZFSGsfzb9xs
    Success: Activated 1 of 1 signups.',
        'name' => 'activate',
        'params' => array(
            array(
                'name' => 'signup',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Activates one or more signups.',
        'synopsis' => '<signup>...',
    ),
    'user signup delete' => array(
        'command' => 'user signup delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<signup>...]
: The signup ID, user login, user email, or activation key of the signup(s) to delete.

[--all]
: If set, all signups will be deleted.

## EXAMPLES

    # Delete signup.
    $ wp user signup delete 3
    Signup 3 deleted.
    Success: Deleted 1 of 1 signups.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'signup',
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
        'shortdesc' => 'Deletes one or more signups.',
        'synopsis' => '[<signup>...] [--all]',
    ),
    'user signup get' => array(
        'command' => 'user signup get',
        'fields' => array(
            'default' => array(
                'signup_id',
                'domain',
                'path',
                'title',
                'user_login',
                'user_email',
                'registered',
                'activated',
                'active',
                'activation_key',
                'meta',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<signup>
: The signup ID, user login, user email, or activation key.

[--field=<field>]
: Instead of returning the whole signup, returns the value of a single field.

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

## EXAMPLES

    # Get signup.
    $ wp user signup get 1 --field=user_login
    bobuser

    # Get signup and export to JSON file.
    $ wp user signup get bobuser --format=json > bobuser.json',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'signup',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Instead of returning the whole signup, returns the value of a single field.',
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
        'shortdesc' => 'Gets details about a signup.',
        'synopsis' => '<signup> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'user signup list' => array(
        'command' => 'user signup list',
        'fields' => array(
            'default' => array(
                'signup_id',
                'user_login',
                'user_email',
                'registered',
                'active',
                'activation_key',
            ),
            'optional' => array(
                'domain',
                'path',
                'title',
                'activated',
                'meta',
            ),
        ),
        'longdesc' => '## OPTIONS

[--<field>=<value>]
: Filter the list by a specific field.

[--field=<field>]
: Prints the value of a single field for each signup.

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

[--per_page=<per_page>]
: Limits the signups to the given number. Defaults to none.

## AVAILABLE FIELDS

These fields will be displayed by default for each signup:

* signup_id
* user_login
* user_email
* registered
* active
* activation_key

These fields are optionally available:

* domain
* path
* title
* activated
* meta

## EXAMPLES

    # List signup IDs.
    $ wp user signup list --field=signup_id
    1

    # List all signups.
    $ wp user signup list
    +-----------+------------+---------------------+---------------------+--------+------------------+
    | signup_id | user_login | user_email          | registered          | active | activation_key   |
    +-----------+------------+---------------------+---------------------+--------+------------------+
    | 1         | bobuser    | bobuser@example.com | 2024-03-13 05:46:53 | 1      | 7320b2f009266618 |
    | 2         | johndoe    | johndoe@example.com | 2024-03-13 06:24:44 | 0      | 9068d859186cd0b5 |
    +-----------+------------+---------------------+---------------------+--------+------------------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Prints the value of a single field for each signup.',
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
            array(
                'desc' => 'Limits the signups to the given number. Defaults to none.',
                'name' => 'per_page',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists signups.',
        'synopsis' => '[--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>] [--per_page=<per_page>]',
    ),
    'user spam' => array(
        'command' => 'user spam',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>...
: The user login, user email, or user ID of the user(s) to mark as spam.

## EXAMPLES

    # Mark user as spam.
    $ wp user spam 123
    User 123 marked as spam.
    Success: Spammed 1 of 1 users.',
        'name' => 'spam',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Marks one or more users as spam on multisite.',
        'synopsis' => '<user>...',
    ),
    'user term add' => array(
        'command' => 'user term add',
        'fields' => null,
        'longdesc' => 'Append the term to the existing set of terms on the object.

## OPTIONS

<id>
: The ID of the object.

<taxonomy>
: The name of the taxonomy type to be added.

<term>...
: The slug of the term or terms to be added.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: slug
options:
  - slug
  - id
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
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'term',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'slug',
                'desc' => 'Explicitly handle the term value as a slug or id.',
                'name' => 'by',
                'optional' => true,
                'options' => array(
                    'slug',
                    'id',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Add a term to an object.',
        'synopsis' => '<id> <taxonomy> <term>... [--by=<field>]',
    ),
    'user term list' => array(
        'command' => 'user term list',
        'fields' => array(
            'default' => array(
                'term_id',
                'name',
                'slug',
                'taxonomy',
            ),
            'optional' => array(
                'term_taxonomy_id',
                'description',
                'term_group',
                'parent',
                'count',
            ),
        ),
        'longdesc' => '## OPTIONS

<id>
: ID for the object.

<taxonomy>...
: One or more taxonomies to list.

[--field=<field>]
: Prints the value of a single field for each term.

[--fields=<fields>]
: Limit the output to specific row fields.

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
  - ids
---

## AVAILABLE FIELDS

These fields will be displayed by default for each term:

* term_id
* name
* slug
* taxonomy

These fields are optionally available:

* term_taxonomy_id
* description
* term_group
* parent
* count',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'id',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'taxonomy',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Prints the value of a single field for each term.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific row fields.',
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
                    'ids',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'List all terms associated with an object.',
        'synopsis' => '<id> <taxonomy>... [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'user term remove' => array(
        'command' => 'user term remove',
        'fields' => null,
        'longdesc' => '## OPTIONS

<id>
: The ID of the object.

<taxonomy>
: The name of the term\'s taxonomy.

[<term>...]
: The slug of the term or terms to be removed from the object.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: slug
options:
  - slug
  - id
---

[--all]
: Remove all terms from the object.',
        'name' => 'remove',
        'params' => array(
            array(
                'name' => 'id',
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
                'name' => 'term',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'slug',
                'desc' => 'Explicitly handle the term value as a slug or id.',
                'name' => 'by',
                'optional' => true,
                'options' => array(
                    'slug',
                    'id',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Remove a term from an object.',
        'synopsis' => '<id> <taxonomy> [<term>...] [--by=<field>] [--all]',
    ),
    'user term set' => array(
        'command' => 'user term set',
        'fields' => null,
        'longdesc' => 'Replaces existing terms on the object.

## OPTIONS

<id>
: The ID of the object.

<taxonomy>
: The name of the taxonomy type to be updated.

<term>...
: The slug of the term or terms to be updated.

[--by=<field>]
: Explicitly handle the term value as a slug or id.
---
default: slug
options:
  - slug
  - id
---',
        'name' => 'set',
        'params' => array(
            array(
                'name' => 'id',
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
                'name' => 'term',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'slug',
                'desc' => 'Explicitly handle the term value as a slug or id.',
                'name' => 'by',
                'optional' => true,
                'options' => array(
                    'slug',
                    'id',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Set object terms.',
        'synopsis' => '<id> <taxonomy> <term>... [--by=<field>]',
    ),
    'user unspam' => array(
        'command' => 'user unspam',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>...
: The user login, user email, or user ID of the user(s) to remove from spam.

## EXAMPLES

    # Remove user from spam.
    $ wp user unspam 123
    User 123 removed from spam.
    Success: Unspamed 1 of 1 users.',
        'name' => 'unspam',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Removes one or more users from spam on multisite.',
        'synopsis' => '<user>...',
    ),
    'user update' => array(
        'command' => 'user update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<user>...
: The user login, user email or user ID of the user(s) to update.

[--user_pass=<password>]
: A string that contains the plain text password for the user.

[--user_nicename=<nice_name>]
: A string that contains a URL-friendly name for the user. The default is the user\'s username.

[--user_url=<url>]
: A string containing the user\'s URL for the user\'s web site.

[--user_email=<email>]
: A string containing the user\'s email address.

[--display_name=<display_name>]
: A string that will be shown on the site. Defaults to user\'s username.

[--nickname=<nickname>]
: The user\'s nickname, defaults to the user\'s username.

[--first_name=<first_name>]
: The user\'s first name.

[--last_name=<last_name>]
: The user\'s last name.

[--description=<description>]
: A string containing content about the user.

[--rich_editing=<rich_editing>]
: A string for whether to enable the rich editor or not. False if not empty.

[--user_registered=<yyyy-mm-dd-hh-ii-ss>]
: The date the user registered.

[--role=<role>]
: A string used to set the user\'s role.

--<field>=<value>
: One or more fields to update. For accepted fields, see wp_update_user().

[--skip-email]
: Don\'t send an email notification to the user.

## EXAMPLES

    # Update user
    $ wp user update 123 --display_name=Mary --user_pass=marypass
    Success: Updated user 123.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'user',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'A string that contains the plain text password for the user.',
                'name' => 'user_pass',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string that contains a URL-friendly name for the user. The default is the user\'s username.',
                'name' => 'user_nicename',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string containing the user\'s URL for the user\'s web site.',
                'name' => 'user_url',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string containing the user\'s email address.',
                'name' => 'user_email',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string that will be shown on the site. Defaults to user\'s username.',
                'name' => 'display_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The user\'s nickname, defaults to the user\'s username.',
                'name' => 'nickname',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The user\'s first name.',
                'name' => 'first_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The user\'s last name.',
                'name' => 'last_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string containing content about the user.',
                'name' => 'description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string for whether to enable the rich editor or not. False if not empty.',
                'name' => 'rich_editing',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The date the user registered.',
                'name' => 'user_registered',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'A string used to set the user\'s role.',
                'name' => 'role',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => '',
                'optional' => false,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'name' => 'skip-email',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Updates an existing user.',
        'synopsis' => '<user>... [--user_pass=<password>] [--user_nicename=<nice_name>] [--user_url=<url>] [--user_email=<email>] [--display_name=<display_name>] [--nickname=<nickname>] [--first_name=<first_name>] [--last_name=<last_name>] [--description=<description>] [--rich_editing=<rich_editing>] [--user_registered=<yyyy-mm-dd-hh-ii-ss>] [--role=<role>] --<field>=<value> [--skip-email]',
    ),
);
