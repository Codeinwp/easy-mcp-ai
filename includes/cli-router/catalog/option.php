<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'option add' => array(
        'command' => 'option add',
        'fields' => null,
        'longdesc' => 'Errors if the option already exists.

## OPTIONS

<key>
: The name of the option to add.

[<value>]
: The value of the option to add. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---

[--autoload=<autoload>]
: Should this option be automatically loaded.
---
options:
  - \'on\'
  - \'off\'
  - \'yes\'
  - \'no\'
---

## EXAMPLES

    # Create an option by reading a JSON file.
    $ wp option add my_option --format=json < config.json
    Success: Added \'my_option\' option.',
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
            array(
                'desc' => 'Should this option be automatically loaded.',
                'name' => 'autoload',
                'optional' => true,
                'options' => array(
                    'on',
                    'off',
                    'yes',
                    'no',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Adds a new option value.',
        'synopsis' => '<key> [<value>] [--format=<format>] [--autoload=<autoload>]',
    ),
    'option delete' => array(
        'command' => 'option delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>...
: Key for the option.

## EXAMPLES

    # Delete an option.
    $ wp option delete my_option
    Success: Deleted \'my_option\' option.

    # Delete multiple options.
    $ wp option delete option_one option_two option_three
    Success: Deleted \'option_one\' option.
    Success: Deleted \'option_two\' option.
    Warning: Could not delete \'option_three\' option. Does it exist?',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Deletes an option.',
        'synopsis' => '<key>...',
    ),
    'option get' => array(
        'command' => 'option get',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: Key for the option.

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

    # Get option.
    $ wp option get home
    http://example.com

    # Get blog description.
    $ wp option get blogdescription
    A random blog description

    # Get blog name
    $ wp option get blogname
    A random blog name

    # Get admin email.
    $ wp option get admin_email
    someone@example.com

    # Get option in JSON format.
    $ wp option get active_plugins --format=json
    {"0":"dynamically-dynamic-sidebar\\/dynamically-dynamic-sidebar.php","1":"monster-widget\\/monster-widget.php","2":"show-current-template\\/show-current-template.php","3":"theme-check\\/theme-check.php","5":"wordpress-importer\\/wordpress-importer.php"}',
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
        'shortdesc' => 'Gets the value for an option.',
        'synopsis' => '<key> [--format=<format>]',
    ),
    'option get-autoload' => array(
        'command' => 'option get-autoload',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: The name of the option to get \'autoload\' of.

## EXAMPLES

    # Get the \'autoload\' value for an option.
    $ wp option get-autoload blogname
    yes',
        'name' => 'get-autoload',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Gets the \'autoload\' value for an option.',
        'synopsis' => '<key>',
    ),
    'option list' => array(
        'command' => 'option list',
        'fields' => array(
            'default' => array(
                'option_name',
                'option_value',
            ),
            'optional' => array(
                'autoload',
                'size_bytes',
            ),
        ),
        'longdesc' => '## OPTIONS

[--search=<pattern>]
: Use wildcards ( * and ? ) to match option name.

[--exclude=<pattern>]
: Pattern to exclude. Use wildcards ( * and ? ) to match option name.

[--autoload=<value>]
: Match only autoload options when value is on, and only not-autoload option when off.

[--transients]
: List only transients. Use `--no-transients` to ignore all transients.

[--unserialize]
: Unserialize option values in output.

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

[--orderby=<fields>]
: Set orderby which field.
---
default: option_id
options:
 - option_id
 - option_name
 - option_value
---

[--order=<order>]
: Set ascending or descending order.
---
default: asc
options:
 - asc
 - desc
---

## AVAILABLE FIELDS

This field will be displayed by default for each matching option:

* option_name
* option_value

These fields are optionally available:

* autoload
* size_bytes

## EXAMPLES

    # Get the total size of all autoload options.
    $ wp option list --autoload=on --format=total_bytes
    33198

    # Find biggest transients.
    $ wp option list --search="*_transient_*" --fields=option_name,size_bytes | sort -n -k 2 | tail
    option_name size_bytes
    _site_transient_timeout_theme_roots 10
    _site_transient_theme_roots 76
    _site_transient_update_themes   181
    _site_transient_update_core 808
    _site_transient_update_plugins  6645

    # List all options beginning with "i2f_".
    $ wp option list --search="i2f_*"
    +-------------+--------------+
    | option_name | option_value |
    +-------------+--------------+
    | i2f_version | 0.1.0        |
    +-------------+--------------+

    # Delete all options beginning with "theme_mods_".
    $ wp option list --search="theme_mods_*" --field=option_name | xargs -I % wp option delete %
    Success: Deleted \'theme_mods_twentysixteen\' option.
    Success: Deleted \'theme_mods_twentyfifteen\' option.
    Success: Deleted \'theme_mods_twentyfourteen\' option.',
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
                'desc' => 'Pattern to exclude. Use wildcards ( * and ? ) to match option name.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Match only autoload options when value is on, and only not-autoload option when off.',
                'name' => 'autoload',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'transients',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'unserialize',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
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
            array(
                'default' => 'option_id',
                'desc' => 'Set orderby which field.',
                'name' => 'orderby',
                'optional' => true,
                'options' => array(
                    'option_id',
                    'option_name',
                    'option_value',
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
        ),
        'shortdesc' => 'Lists options and their values.',
        'synopsis' => '[--search=<pattern>] [--exclude=<pattern>] [--autoload=<value>] [--transients] [--unserialize] [--field=<field>] [--fields=<fields>] [--format=<format>] [--orderby=<fields>] [--order=<order>]',
    ),
    'option patch' => array(
        'command' => 'option patch',
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
---

## EXAMPLES

    # Add \'bar\' to the \'foo\' key on an option with name \'option_name\'
    $ wp option patch insert option_name foo bar
    Success: Updated \'option_name\' option.

    # Update the value of \'foo\' key to \'new\' on an option with name \'option_name\'
    $ wp option patch update option_name foo new
    Success: Updated \'option_name\' option.

    # Set nested value of \'bar\' key to value we have in the patch file on an option with name \'option_name\'.
    $ wp option patch update option_name foo bar < patch
    Success: Updated \'option_name\' option.

    # Update the value for the key \'not-a-key\' which is not exist on an option with name \'option_name\'.
    $ wp option patch update option_name foo not-a-key new-value
    Error: No data exists for key "not-a-key"

    # Update the value for the key \'foo\' without passing value on an option with name \'option_name\'.
    $ wp option patch update option_name foo
    Error: Please provide value to update.

    # Delete the nested key \'bar\' under \'foo\' key on an option with name \'option_name\'.
    $ wp option patch delete option_name foo bar
    Success: Updated \'option_name\' option.',
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
    'option pluck' => array(
        'command' => 'option pluck',
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
  - yaml
---',
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
    'option set-autoload' => array(
        'command' => 'option set-autoload',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: The name of the option to set \'autoload\' for.

<autoload>
: Should this option be automatically loaded.
---
options:
  - \'on\'
  - \'off\'
  - \'yes\'
  - \'no\'
---

## EXAMPLES

    # Set the \'autoload\' value for an option.
    $ wp option set-autoload abc_options no
    Success: Updated autoload value for \'abc_options\' option.',
        'name' => 'set-autoload',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'autoload',
                'optional' => false,
                'options' => array(
                    'on',
                    'off',
                    'yes',
                    'no',
                ),
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Sets the \'autoload\' value for an option.',
        'synopsis' => '<key> <autoload>',
    ),
    'option update' => array(
        'alias' => 'set',
        'command' => 'option update',
        'fields' => null,
        'longdesc' => '## OPTIONS

<key>
: The name of the option to update.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--autoload=<autoload>]
: Requires WP 4.2. Should this option be automatically loaded.
---
options:
  - \'on\'
  - \'off\'
  - \'yes\'
  - \'no\'
---

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
  - plaintext
  - json
---

## EXAMPLES

    # Update an option by reading from a file.
    $ wp option update my_option < value.txt
    Success: Updated \'my_option\' option.

    # Update one option on multiple sites using xargs.
    $ wp site list --field=url | xargs -n1 -I {} sh -c \'wp --url={} option update my_option my_value\'
    Success: Updated \'my_option\' option.
    Success: Updated \'my_option\' option.

    # Update site blog name.
    $ wp option update blogname "Random blog name"
    Success: Updated \'blogname\' option.

    # Update site blog description.
    $ wp option update blogdescription "Some random blog description"
    Success: Updated \'blogdescription\' option.

    # Update admin email address.
    $ wp option update admin_email someone@example.com
    Success: Updated \'admin_email\' option.

    # Set the default role.
    $ wp option update default_role author
    Success: Updated \'default_role\' option.

    # Set the timezone string.
    $ wp option update timezone_string "America/New_York"
    Success: Updated \'timezone_string\' option.',
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
                'desc' => 'Requires WP 4.2. Should this option be automatically loaded.',
                'name' => 'autoload',
                'optional' => true,
                'options' => array(
                    'on',
                    'off',
                    'yes',
                    'no',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
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
        'shortdesc' => 'Updates an option value.',
        'synopsis' => '<key> [<value>] [--autoload=<autoload>] [--format=<format>]',
    ),
);
