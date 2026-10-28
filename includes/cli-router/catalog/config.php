<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'config create' => array(
        'command' => 'config create',
        'fields' => null,
        'longdesc' => 'Creates a new wp-config.php with database constants, and verifies that
the database constants are correct.

## OPTIONS

--dbname=<dbname>
: Set the database name.

--dbuser=<dbuser>
: Set the database user.

[--dbpass=<dbpass>]
: Set the database user password.

[--dbhost=<dbhost>]
: Set the database host.
---
default: localhost
---

[--dbprefix=<dbprefix>]
: Set the database table prefix.
---
default: wp_
---

[--dbcharset=<dbcharset>]
: Set the database charset.
---
default: utf8
---

[--dbcollate=<dbcollate>]
: Set the database collation.
---
default:
---

[--locale=<locale>]
: Set the WPLANG constant. Defaults to $wp_local_package variable.

[--extra-php]
: If set, the command copies additional PHP code into wp-config.php from STDIN.

[--skip-salts]
: If set, keys and salts won\'t be generated, but should instead be passed via `--extra-php`.

[--skip-check]
: If set, the database connection is not checked.

[--force]
: Overwrites existing files, if present.

[--config-file=<path>]
: Specify the file path to the config file to be created. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

[--insecure]
: Retry API download without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Standard wp-config.php file
    $ wp config create --dbname=testing --dbuser=wp --dbpass=securepswd --locale=ro_RO
    Success: Generated \'wp-config.php\' file.

    # Enable WP_DEBUG and WP_DEBUG_LOG
    $ wp config create --dbname=testing --dbuser=wp --dbpass=securepswd --extra-php <<PHP
    define( \'WP_DEBUG\', true );
    define( \'WP_DEBUG_LOG\', true );
    PHP
    Success: Generated \'wp-config.php\' file.

    # Avoid disclosing password to bash history by reading from password.txt
    # Using --prompt=dbpass will prompt for the \'dbpass\' argument
    $ wp config create --dbname=testing --dbuser=wp --prompt=dbpass < password.txt
    Success: Generated \'wp-config.php\' file.',
        'name' => 'create',
        'params' => array(
            array(
                'desc' => 'Set the database name.',
                'name' => 'dbname',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set the database user.',
                'name' => 'dbuser',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set the database user password.',
                'name' => 'dbpass',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'localhost',
                'desc' => 'Set the database host.',
                'name' => 'dbhost',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'wp_',
                'desc' => 'Set the database table prefix.',
                'name' => 'dbprefix',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'utf8',
                'desc' => 'Set the database charset.',
                'name' => 'dbcharset',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => '',
                'desc' => 'Set the database collation.',
                'name' => 'dbcollate',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set the WPLANG constant. Defaults to $wp_local_package variable.',
                'name' => 'locale',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'extra-php',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-salts',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-check',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'force',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Specify the file path to the config file to be created. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'insecure',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Generates a wp-config.php file.',
        'synopsis' => '--dbname=<dbname> --dbuser=<dbuser> [--dbpass=<dbpass>] [--dbhost=<dbhost>] [--dbprefix=<dbprefix>] [--dbcharset=<dbcharset>] [--dbcollate=<dbcollate>] [--locale=<locale>] [--extra-php] [--skip-salts] [--skip-check] [--force] [--config-file=<path>] [--insecure]',
    ),
    'config delete' => array(
        'command' => 'config delete',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<name>
: Name of the wp-config.php constant or variable.

[--type=<type>]
: Type of the config value to delete. Defaults to \'all\'.
---
default: all
options:
  - constant
  - variable
  - all
---

[--config-file=<path>]
: Specify the file path to the config file to be modified. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

## EXAMPLES

    # Delete the COOKIE_DOMAIN constant from the wp-config.php file.
    $ wp config delete COOKIE_DOMAIN
    Success: Deleted the constant \'COOKIE_DOMAIN\' from the \'wp-config.php\' file.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'all',
                'desc' => 'Type of the config value to delete. Defaults to \'all\'.',
                'name' => 'type',
                'optional' => true,
                'options' => array(
                    'constant',
                    'variable',
                    'all',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the file path to the config file to be modified. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Deletes a specific constant or variable from the wp-config.php file.',
        'synopsis' => '<name> [--type=<type>] [--config-file=<path>]',
    ),
    'config edit' => array(
        'command' => 'config edit',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

[--config-file=<path>]
: Specify the file path to the config file to be edited. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

## EXAMPLES

    # Launch system editor to edit wp-config.php file
    $ wp config edit

    # Edit wp-config.php file in a specific editor
    $ EDITOR=vim wp config edit',
        'name' => 'edit',
        'params' => array(
            array(
                'desc' => 'Specify the file path to the config file to be edited. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Launches system editor to edit the wp-config.php file.',
        'synopsis' => '[--config-file=<path>]',
    ),
    'config get' => array(
        'command' => 'config get',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<name>
: Name of the wp-config.php constant or variable.

[--type=<type>]
: Type of config value to retrieve. Defaults to \'all\'.
---
default: all
options:
  - constant
  - variable
  - all
---

[--format=<format>]
: Get value in a particular format.
Dotenv is limited to non-object values.
---
default: var_export
options:
  - var_export
  - json
  - yaml
  - dotenv
---

[--config-file=<path>]
: Specify the file path to the config file to be read. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

## EXAMPLES

    # Get the table_prefix as defined in wp-config.php file.
    $ wp config get table_prefix
    wp_',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'all',
                'desc' => 'Type of config value to retrieve. Defaults to \'all\'.',
                'name' => 'type',
                'optional' => true,
                'options' => array(
                    'constant',
                    'variable',
                    'all',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
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
                    'dotenv',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the file path to the config file to be read. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets the value of a specific constant or variable defined in wp-config.php file.',
        'synopsis' => '<name> [--type=<type>] [--format=<format>] [--config-file=<path>]',
    ),
    'config has' => array(
        'command' => 'config has',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<name>
: Name of the wp-config.php constant or variable.

[--type=<type>]
: Type of the config value to set. Defaults to \'all\'.
---
default: all
options:
  - constant
  - variable
  - all
---

[--config-file=<path>]
: Specify the file path to the config file to be checked. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

## EXAMPLES

    # Check whether the DB_PASSWORD constant exists in the wp-config.php file.
    $ wp config has DB_PASSWORD',
        'name' => 'has',
        'params' => array(
            array(
                'name' => 'name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'all',
                'desc' => 'Type of the config value to set. Defaults to \'all\'.',
                'name' => 'type',
                'optional' => true,
                'options' => array(
                    'constant',
                    'variable',
                    'all',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the file path to the config file to be checked. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Checks whether a specific constant or variable exists in the wp-config.php file.',
        'synopsis' => '<name> [--type=<type>] [--config-file=<path>]',
    ),
    'config is-true' => array(
        'command' => 'config is-true',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'This determination is made by evaluating the retrieved value via boolval().

## OPTIONS

<name>
: Name of the wp-config.php constant or variable.

[--type=<type>]
: Type of config value to retrieve. Defaults to \'all\'.
---
default: all
options:
  - constant
  - variable
  - all
---

[--config-file=<path>]
: Specify the file path to the config file to be read. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

## EXAMPLES

    # Assert if MULTISITE is true
    $ wp config is-true MULTISITE
    $ echo $?
    0',
        'name' => 'is-true',
        'params' => array(
            array(
                'name' => 'name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 'all',
                'desc' => 'Type of config value to retrieve. Defaults to \'all\'.',
                'name' => 'type',
                'optional' => true,
                'options' => array(
                    'constant',
                    'variable',
                    'all',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the file path to the config file to be read. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Determines whether value of a specific defined constant or variable is truthy.',
        'synopsis' => '<name> [--type=<type>] [--config-file=<path>]',
    ),
    'config list' => array(
        'command' => 'config list',
        'fields' => array(
            'default' => array(
                'name',
                'value',
                'type',
            ),
            'optional' => array(),
        ),
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

[<filter>...]
: Name or partial name to filter the list by.

[--fields=<fields>]
: Limit the output to specific fields. Defaults to all fields.

[--format=<format>]
: Render output in a particular format.
Dotenv is limited to non-object values.
---
default: table
options:
  - table
  - csv
  - json
  - yaml
  - dotenv
---

[--strict]
: Enforce strict matching when a filter is provided.

[--config-file=<path>]
: Specify the file path to the config file to be read. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

## EXAMPLES

    # List constants and variables defined in wp-config.php file.
    $ wp config list
    +------------------+------------------------------------------------------------------+----------+
    | key              | value                                                            | type     |
    +------------------+------------------------------------------------------------------+----------+
    | table_prefix     | wp_                                                              | variable |
    | DB_NAME          | wp_cli_test                                                      | constant |
    | DB_USER          | root                                                             | constant |
    | DB_PASSWORD      | root                                                             | constant |
    | AUTH_KEY         | r6+@shP1yO&$)1gdu.hl[/j;7Zrvmt~o;#WxSsa0mlQOi24j2cR,7i+QM/#7S:o^ | constant |
    | SECURE_AUTH_KEY  | iO-z!_m--YH$Tx2tf/&V,YW*13Z_HiRLqi)d?$o-tMdY+82pK$`T.NYW~iTLW;xp | constant |
    +------------------+------------------------------------------------------------------+----------+

    # List only database user and password from wp-config.php file.
    $ wp config list DB_USER DB_PASSWORD --strict
    +------------------+-------+----------+
    | key              | value | type     |
    +------------------+-------+----------+
    | DB_USER          | root  | constant |
    | DB_PASSWORD      | root  | constant |
    +------------------+-------+----------+

    # List all salts from wp-config.php file.
    $ wp config list _SALT
    +------------------+------------------------------------------------------------------+----------+
    | key              | value                                                            | type     |
    +------------------+------------------------------------------------------------------+----------+
    | AUTH_SALT        | n:]Xditk+_7>Qi=>BmtZHiH-6/Ecrvl(V5ceeGP:{>?;BT^=[B3-0>,~F5z$(+Q$ | constant |
    | SECURE_AUTH_SALT | ?Z/p|XhDw3w}?c.z%|+BAr|(Iv*H%%U+Du&kKR y?cJOYyRVRBeB[2zF-`(>+LCC | constant |
    | LOGGED_IN_SALT   | +$@(1{b~Z~s}Cs>8Y]6[m6~TnoCDpE>O%e75u}&6kUH!>q:7uM4lxbB6[1pa_X,q | constant |
    | NONCE_SALT       | _x+F li|QL?0OSQns1_JZ{|Ix3Jleox-71km/gifnyz8kmo=w-;@AE8W,(fP<N}2 | constant |
    +------------------+------------------------------------------------------------------+----------+',
        'name' => 'list',
        'params' => array(
            array(
                'name' => 'filter',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
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
                    'dotenv',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'strict',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Specify the file path to the config file to be read. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists variables, constants, and file includes defined in wp-config.php file.',
        'synopsis' => '[<filter>...] [--fields=<fields>] [--format=<format>] [--strict] [--config-file=<path>]',
    ),
    'config path' => array(
        'command' => 'config path',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## EXAMPLES

    # Get wp-config.php file path
    $ wp config path
    /home/person/htdocs/project/wp-config.php',
        'name' => 'path',
        'params' => array(),
        'shortdesc' => 'Gets the path to wp-config.php file.',
        'synopsis' => '',
    ),
    'config set' => array(
        'command' => 'config set',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<name>
: Name of the wp-config.php constant or variable.

<value>
: Value to set the wp-config.php constant or variable to.

[--add]
: Add the value if it doesn\'t exist yet.
This is the default behavior, override with --no-add.

[--raw]
: Place the value into the wp-config.php file as is, instead of as a quoted string.

[--anchor=<anchor>]
: Anchor string where additions of new values are anchored around.
Defaults to "/* That\'s all, stop editing!".
The special case "EOF" string uses the end of the file as the anchor.

[--placement=<placement>]
: Where to place the new values in relation to the anchor string.
---
default: \'before\'
options:
  - before
  - after
---

[--separator=<separator>]
: Separator string to put between an added value and its anchor string.
The following escape sequences will be recognized and properly interpreted: \'\\n\' => newline, \'\\r\' => carriage return, \'\\t\' => tab.
Defaults to a single EOL ("\\n" on *nix and "\\r\\n" on Windows).

[--type=<type>]
: Type of the config value to set. Defaults to \'all\'.
---
default: all
options:
  - constant
  - variable
  - all
---

[--config-file=<path>]
: Specify the file path to the config file to be modified. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

## EXAMPLES

    # Set the WP_DEBUG constant to true.
    $ wp config set WP_DEBUG true --raw
    Success: Updated the constant \'WP_DEBUG\' in the \'wp-config.php\' file with the raw value \'true\'.',
        'name' => 'set',
        'params' => array(
            array(
                'name' => 'name',
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
                'name' => 'add',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'raw',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Anchor string where additions of new values are anchored around.',
                'name' => 'anchor',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'before',
                'desc' => 'Where to place the new values in relation to the anchor string.',
                'name' => 'placement',
                'optional' => true,
                'options' => array(
                    'before',
                    'after',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Separator string to put between an added value and its anchor string.',
                'name' => 'separator',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'all',
                'desc' => 'Type of the config value to set. Defaults to \'all\'.',
                'name' => 'type',
                'optional' => true,
                'options' => array(
                    'constant',
                    'variable',
                    'all',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Specify the file path to the config file to be modified. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Sets the value of a specific constant or variable defined in wp-config.php file.',
        'synopsis' => '<name> <value> [--add] [--raw] [--anchor=<anchor>] [--placement=<placement>] [--separator=<separator>] [--type=<type>] [--config-file=<path>]',
    ),
    'config shuffle-salts' => array(
        'command' => 'config shuffle-salts',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

[<keys>...]
: One ore more keys to shuffle. If none are provided, this falls back to the default WordPress Core salt keys.

[--force]
: If an unknown key is requested to be shuffled, add it instead of throwing a warning.

[--config-file=<path>]
: Specify the file path to the config file to be modified. Defaults to the root of the
WordPress installation and the filename "wp-config.php".

[--insecure]
: Retry API download without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Get new salts for your wp-config.php file
    $ wp config shuffle-salts
    Success: Shuffled the salt keys.

    # Add a cache key salt to the wp-config.php file
    $ wp config shuffle-salts WP_CACHE_KEY_SALT --force
    Success: Shuffled the salt keys.',
        'name' => 'shuffle-salts',
        'params' => array(
            array(
                'name' => 'keys',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'force',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Specify the file path to the config file to be modified. Defaults to the root of the',
                'name' => 'config-file',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'insecure',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Refreshes the salts defined in the wp-config.php file.',
        'synopsis' => '[<keys>...] [--force] [--config-file=<path>] [--insecure]',
    ),
);
