<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'cli alias add' => array(
        'command' => 'cli alias add',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<key>
: Key for the alias.

[--set-user=<user>]
: Set user for alias.

[--set-url=<url>]
: Set url for alias.

[--set-path=<path>]
: Set path for alias.

[--set-ssh=<ssh>]
: Set ssh for alias.

[--set-http=<http>]
: Set http for alias.

[--grouping=<grouping>]
: For grouping multiple aliases.

[--config=<config>]
: Config file to be considered for operations.
---
default: global
options:
  - global
  - project
---

## EXAMPLES

    # Add alias to global config.
    $ wp cli alias add @prod  --set-ssh=login@host --set-path=/path/to/wordpress/install/ --set-user=wpcli
    Success: Added \'@prod\' alias.

    # Add alias to project config.
    $ wp cli alias add @prod --set-ssh=login@host --set-path=/path/to/wordpress/install/ --set-user=wpcli --config=project
    Success: Added \'@prod\' alias.

    # Add group of aliases.
    $ wp cli alias add @multiservers --grouping=servera,serverb
    Success: Added \'@multiservers\' alias.',
        'name' => 'add',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Set user for alias.',
                'name' => 'set-user',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set url for alias.',
                'name' => 'set-url',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set path for alias.',
                'name' => 'set-path',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set ssh for alias.',
                'name' => 'set-ssh',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set http for alias.',
                'name' => 'set-http',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'For grouping multiple aliases.',
                'name' => 'grouping',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'global',
                'desc' => 'Config file to be considered for operations.',
                'name' => 'config',
                'optional' => true,
                'options' => array(
                    'global',
                    'project',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Creates an alias.',
        'synopsis' => '<key> [--set-user=<user>] [--set-url=<url>] [--set-path=<path>] [--set-ssh=<ssh>] [--set-http=<http>] [--grouping=<grouping>] [--config=<config>]',
    ),
    'cli alias delete' => array(
        'command' => 'cli alias delete',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<key>
: Key for the alias.

[--config=<config>]
: Config file to be considered for operations.
---
options:
  - global
  - project
---

## EXAMPLES

    # Delete alias.
    $ wp cli alias delete @prod
    Success: Deleted \'@prod\' alias.

    # Delete project alias.
    $ wp cli alias delete @prod --config=project
    Success: Deleted \'@prod\' alias.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Config file to be considered for operations.',
                'name' => 'config',
                'optional' => true,
                'options' => array(
                    'global',
                    'project',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Deletes an alias.',
        'synopsis' => '<key> [--config=<config>]',
    ),
    'cli alias get' => array(
        'command' => 'cli alias get',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<key>
: Key for the alias.

## EXAMPLES

    # Get alias.
    $ wp cli alias get @prod
    ssh: dev@somedeve.env:12345/home/dev/',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Gets the value for an alias.',
        'synopsis' => '<key>',
    ),
    'cli alias is-group' => array(
        'command' => 'cli alias is-group',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<key>
: Key for the alias.

## EXAMPLES

    # Checks whether the alias is a group; exit status 0 if it is, otherwise 1.
    $ wp cli alias is-group @prod
    $ echo $?
    1',
        'name' => 'is-group',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Check whether an alias is a group.',
        'synopsis' => '<key>',
    ),
    'cli alias list' => array(
        'command' => 'cli alias list',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

[--format=<format>]
: Render output in a particular format.
---
default: yaml
options:
  - yaml
  - json
  - var_export
---

## EXAMPLES

    # List all available aliases.
    $ wp cli alias list
    ---
    @all: Run command against every registered alias.
    @prod:
      ssh: runcommand@runcommand.io~/webapps/production
    @dev:
      ssh: vagrant@192.168.50.10/srv/www/runcommand.dev
    @both:
      - @prod
      - @dev',
        'name' => 'list',
        'params' => array(
            array(
                'default' => 'yaml',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'yaml',
                    'json',
                    'var_export',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists available WP-CLI aliases.',
        'synopsis' => '[--format=<format>]',
    ),
    'cli alias update' => array(
        'command' => 'cli alias update',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<key>
: Key for the alias.

[--set-user=<user>]
: Set user for alias.

[--set-url=<url>]
: Set url for alias.

[--set-path=<path>]
: Set path for alias.

[--set-ssh=<ssh>]
: Set ssh for alias.

[--set-http=<http>]
: Set http for alias.

[--grouping=<grouping>]
: For grouping multiple aliases.

[--config=<config>]
: Config file to be considered for operations.
---
options:
  - global
  - project
---

## EXAMPLES

    # Update alias.
    $ wp cli alias update @prod --set-user=newuser --set-path=/new/path/to/wordpress/install/
    Success: Updated \'prod\' alias.

    # Update project alias.
    $ wp cli alias update @prod --set-user=newuser --set-path=/new/path/to/wordpress/install/ --config=project
    Success: Updated \'prod\' alias.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'key',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Set user for alias.',
                'name' => 'set-user',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set url for alias.',
                'name' => 'set-url',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set path for alias.',
                'name' => 'set-path',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set ssh for alias.',
                'name' => 'set-ssh',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Set http for alias.',
                'name' => 'set-http',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'For grouping multiple aliases.',
                'name' => 'grouping',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Config file to be considered for operations.',
                'name' => 'config',
                'optional' => true,
                'options' => array(
                    'global',
                    'project',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Updates an alias.',
        'synopsis' => '<key> [--set-user=<user>] [--set-url=<url>] [--set-path=<path>] [--set-ssh=<ssh>] [--set-http=<http>] [--grouping=<grouping>] [--config=<config>]',
    ),
    'cli cache clear' => array(
        'command' => 'cli cache clear',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## EXAMPLES

    $ wp cli cache clear
    Success: Cache cleared.',
        'name' => 'clear',
        'params' => array(),
        'shortdesc' => 'Clears the internal cache.',
        'synopsis' => '',
    ),
    'cli cache prune' => array(
        'command' => 'cli cache prune',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Removes all cached files except for the newest version of each one.

## EXAMPLES

    $ wp cli cache prune
    Success: Cache pruned.',
        'name' => 'prune',
        'params' => array(),
        'shortdesc' => 'Prunes the internal cache.',
        'synopsis' => '',
    ),
    'cli check-update' => array(
        'command' => 'cli check-update',
        'fields' => array(
            'default' => array(
                'version',
                'update_type',
                'package_url',
                'status',
                'requires_php',
            ),
            'optional' => array(),
        ),
        'hook' => 'before_wp_load',
        'longdesc' => 'Queries the GitHub releases API. Returns available versions if there are
updates available, or success message if using the latest release.

## OPTIONS

[--patch]
: Only list patch updates.

[--minor]
: Only list minor updates.

[--major]
: Only list major updates.

[--field=<field>]
: Prints the value of a single field for each update.

[--fields=<fields>]
: Limit the output to specific object fields. Defaults to version,update_type,package_url,status,requires_php.

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

## EXAMPLES

    # Check for update.
    $ wp cli check-update
    Success: WP-CLI is at the latest version.

    # Check for update and new version is available.
    $ wp cli check-update
    +---------+-------------+-------------------------------------------------------------------------------+
    | version | update_type | package_url                                                                   |
    +---------+-------------+-------------------------------------------------------------------------------+
    | 0.24.1  | patch       | https://github.com/wp-cli/wp-cli/releases/download/v0.24.1/wp-cli-0.24.1.phar |
    +---------+-------------+-------------------------------------------------------------------------------+',
        'name' => 'check-update',
        'params' => array(
            array(
                'name' => 'patch',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'minor',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'major',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Prints the value of a single field for each update.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Limit the output to specific object fields. Defaults to version,update_type,package_url,status,requires_php.',
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
        'shortdesc' => 'Checks to see if there is a newer version of WP-CLI available.',
        'synopsis' => '[--patch] [--minor] [--major] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'cli cmd-dump' => array(
        'command' => 'cli cmd-dump',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## EXAMPLES

    # Dump the list of installed commands.
    $ wp cli cmd-dump
    {"name":"wp","description":"Manage WordPress through the command-line.","longdesc":"\\n\\n## GLOBAL PARAMETERS\\n\\n  --path=<path>\\n      Path to the WordPress files.\\n\\n  --ssh=<ssh>\\n      Perform operation against a remote server over SSH (or a container using scheme of "docker" or "docker-compose").\\n\\n  --url=<url>\\n      Pretend request came from given URL. In multisite, this argument is how the target site is specified. \\n\\n  --user=<id|login|email>\\n',
        'name' => 'cmd-dump',
        'params' => array(),
        'shortdesc' => 'Dumps the list of installed commands, as JSON.',
        'synopsis' => '',
    ),
    'cli completions' => array(
        'command' => 'cli completions',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

--line=<line>
: The current command line to be executed.

--point=<point>
: The index to the current cursor position relative to the beginning of the command.

## EXAMPLES

    # Generate tab completion strings.
    $ wp cli completions --line=\'wp eva\' --point=100
    eval
    eval-file',
        'name' => 'completions',
        'params' => array(
            array(
                'desc' => 'The current command line to be executed.',
                'name' => 'line',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The index to the current cursor position relative to the beginning of the command.',
                'name' => 'point',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Generates tab completion strings.',
        'synopsis' => '--line=<line> --point=<point>',
    ),
    'cli has-command' => array(
        'command' => 'cli has-command',
        'fields' => null,
        'hook' => 'after_wp_load',
        'longdesc' => 'This commands checks if a command is registered with WP-CLI.
If the command is found then it returns with exit status 0.
If the command doesn\'t exist, then it will exit with status 1.

## OPTIONS
<command_name>...
: The command

## EXAMPLES

    # The "site delete" command is registered.
    $ wp cli has-command "site delete"
    $ echo $?
    0

    # The "foo bar" command is not registered.
    $ wp cli has-command "foo bar"
    $ echo $?
    1

    # Install a WP-CLI package if not already installed
    $ if ! $(wp cli has-command doctor); then wp package install wp-cli/doctor-command; fi
    Installing package wp-cli/doctor-command (dev-main || dev-master || dev-trunk)
    Updating /home/person/.wp-cli/packages/composer.json to require the package...
    Using Composer to install the package...
    ---
    Success: Package installed.',
        'name' => 'has-command',
        'params' => array(
            array(
                'name' => 'command_name',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Detects if a command exists',
        'synopsis' => '<command_name>...',
    ),
    'cli info' => array(
        'command' => 'cli info',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Helpful for diagnostic purposes, this command shares:

* OS information.
* Shell information.
* PHP binary used.
* PHP binary version.
* php.ini configuration file used (which is typically different than web).
* WP-CLI root dir: where WP-CLI is installed (if non-Phar install).
* WP-CLI global config: where the global config YAML file is located.
* WP-CLI project config: where the project config YAML file is located.
* WP-CLI version: currently installed version.

See [config docs](https://make.wordpress.org/cli/handbook/references/config/) for more details on global
and project config YAML files.

## OPTIONS

[--format=<format>]
: Render output in a particular format.
---
default: list
options:
  - list
  - json
---

## EXAMPLES

    # Display various data about the CLI environment.
    $ wp cli info
    OS:  Linux 4.10.0-42-generic #46~16.04.1-Ubuntu SMP Mon Dec 4 15:57:59 UTC 2017 x86_64
    Shell:   /usr/bin/zsh
    PHP binary:  /usr/bin/php
    PHP version: 7.1.12-1+ubuntu16.04.1+deb.sury.org+1
    php.ini used:    /etc/php/7.1/cli/php.ini
    WP-CLI root dir:    phar://wp-cli.phar
    WP-CLI packages dir:    /home/person/.wp-cli/packages/
    WP-CLI global config:
    WP-CLI project config:
    WP-CLI version: 1.5.0',
        'name' => 'info',
        'params' => array(
            array(
                'default' => 'list',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'list',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Prints various details about the WP-CLI environment.',
        'synopsis' => '[--format=<format>]',
    ),
    'cli param-dump' => array(
        'command' => 'cli param-dump',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

[--with-values]
: Display current values also.

[--format=<format>]
: Render output in a particular format.
---
default: json
options:
  - var_export
  - json
---

## EXAMPLES

    # Dump the list of global parameters.
    $ wp cli param-dump --format=var_export
    array (
      \'path\' =>
      array (
        \'runtime\' => \'=<path>\',
        \'file\' => \'<path>\',
        \'synopsis\' => \'\',
        \'default\' => NULL,
        \'multiple\' => false,
        \'desc\' => \'Path to the WordPress files.\',
      ),
      \'url\' =>
      array (',
        'name' => 'param-dump',
        'params' => array(
            array(
                'name' => 'with-values',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'default' => 'json',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'var_export',
                    'json',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Dumps the list of global parameters, as JSON or in var_export format.',
        'synopsis' => '[--with-values] [--format=<format>]',
    ),
    'cli update' => array(
        'command' => 'cli update',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Default behavior is to check the releases API for the newest stable
version, and prompt if one is available.

Use `--stable` to install or reinstall the latest stable version.

Use `--nightly` to install the latest built version of the master branch.
While not recommended for production, nightly contains the latest and
greatest, and should be stable enough for development and staging
environments.

Only works for the Phar installation mechanism.

## OPTIONS

[--patch]
: Only perform patch updates.

[--minor]
: Only perform minor updates.

[--major]
: Only perform major updates.

[--stable]
: Update to the latest stable release. Skips update check.

[--nightly]
: Update to the latest built version of the master branch. Potentially unstable.

[--yes]
: Do not prompt for confirmation.

[--insecure]
: Retry without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Update CLI.
    $ wp cli update
    You are currently using WP-CLI version 0.24.0. Would you like to update to 0.24.1? [y/n] y
    Downloading from https://github.com/wp-cli/wp-cli/releases/download/v0.24.1/wp-cli-0.24.1.phar...
    New version works. Proceeding to replace.
    Success: Updated WP-CLI to 0.24.1.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'patch',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'minor',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'major',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'stable',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'nightly',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'yes',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'insecure',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Updates WP-CLI to the latest release.',
        'synopsis' => '[--patch] [--minor] [--major] [--stable] [--nightly] [--yes] [--insecure]',
    ),
    'cli version' => array(
        'command' => 'cli version',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## EXAMPLES

    # Display CLI version.
    $ wp cli version
    WP-CLI 0.24.1',
        'name' => 'version',
        'params' => array(),
        'shortdesc' => 'Prints WP-CLI version.',
        'synopsis' => '',
    ),
);
