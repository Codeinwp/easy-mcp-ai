<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'package browse' => array(
        'command' => 'package browse',
        'fields' => array(
            'default' => array(
                'name',
                'description',
                'authors',
                'version',
            ),
            'optional' => array(),
        ),
        'hook' => 'before_wp_load',
        'longdesc' => 'Lists packages available for installation from the [Package Index](http://wp-cli.org/package-index/).
Although the package index will remain in place for backward compatibility reasons, it has been
deprecated and will not be updated further. Please refer to https://github.com/wp-cli/ideas/issues/51
to read about its potential replacement.

## OPTIONS

[--fields=<fields>]
: Limit the output to specific fields. Defaults to all fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - ids
  - json
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each package:

* name
* description
* authors
* version

There are no optionally available fields.

## EXAMPLES

    $ wp package browse --format=yaml
    ---
    10up/mu-migration:
      name: 10up/mu-migration
      description: A set of WP-CLI commands to support the migration of single WordPress instances to multisite
      authors: Nícholas André
      version: dev-main, dev-develop
    aaemnnosttv/wp-cli-dotenv-command:
      name: aaemnnosttv/wp-cli-dotenv-command
      description: Dotenv commands for WP-CLI
      authors: Evan Mattson
      version: v0.1, v0.1-beta.1, v0.2, dev-main, dev-dev, dev-develop, dev-tests/behat
    aaemnnosttv/wp-cli-http-command:
      name: aaemnnosttv/wp-cli-http-command
      description: WP-CLI command for using the WordPress HTTP API
      authors: Evan Mattson
      version: dev-main',
        'name' => 'browse',
        'params' => array(
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
                    'ids',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Browses WP-CLI packages available for installation.',
        'synopsis' => '[--fields=<fields>] [--format=<format>]',
    ),
    'package install' => array(
        'command' => 'package install',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Packages are required to be a valid Composer package, and can be
specified as:

* Package name from WP-CLI\'s package index.
* Git URL accessible by the current shell user.
* Path to a directory on the local machine.
* Local or remote .zip file.

Packages are installed to `~/.wp-cli/packages/` by default. Use the
`WP_CLI_PACKAGES_DIR` environment variable to provide a custom path.

When installing a local directory, WP-CLI simply registers a
reference to the directory. If you move or delete the directory, WP-CLI\'s
reference breaks.

When installing a .zip file, WP-CLI extracts the package to
`~/.wp-cli/packages/local/<package-name>`.

If Github token authorization is required, a GitHub Personal Access Token
(https://github.com/settings/tokens) can be used. The following command
will add a GitHub Personal Access Token to Composer\'s global configuration:
composer config -g github-oauth.github.com <GITHUB_TOKEN>
Once this has been added, the value used for <GITHUB_TOKEN> will be used
for future authorization requests.

## OPTIONS

<name|git|path|zip>
: Name, git URL, directory path, or .zip file for the package to install.
Names can optionally include a version constraint
(e.g. wp-cli/server-command:@stable).

[--insecure]
: Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Install a package hosted at a git URL.
    $ wp package install runcommand/hook

    # Install the latest stable version.
    $ wp package install wp-cli/server-command:@stable

    # Install a package hosted at a GitLab.com URL.
    $ wp package install https://gitlab.com/foo/wp-cli-bar-command.git

    # Install a package in a .zip file.
    $ wp package install google-sitemap-generator-cli.zip',
        'name' => 'install',
        'params' => array(
            array(
                'name' => 'name|git|path|zip',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'insecure',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Installs a WP-CLI package.',
        'synopsis' => '<name|git|path|zip> [--insecure]',
    ),
    'package list' => array(
        'command' => 'package list',
        'fields' => array(
            'default' => array(
                'name',
                'authors',
                'version',
                'update',
                'update_version',
            ),
            'optional' => array(
                'description',
            ),
        ),
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

[--fields=<fields>]
: Limit the output to specific fields. Defaults to all fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - ids
  - json
  - yaml
---

## AVAILABLE FIELDS

These fields will be displayed by default for each package:

* name
* authors
* version
* update
* update_version

These fields are optionally available:

* description

## EXAMPLES

    # List installed packages.
    $ wp package list
    +-----------------------+------------------+----------+-----------+----------------+
    | name                  | authors          | version  | update    | update_version |
    +-----------------------+------------------+----------+-----------+----------------+
    | wp-cli/server-command | Daniel Bachhuber | dev-main | available | 2.x-dev        |
    +-----------------------+------------------+----------+-----------+----------------+',
        'name' => 'list',
        'params' => array(
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
                    'ids',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists installed WP-CLI packages.',
        'synopsis' => '[--fields=<fields>] [--format=<format>]',
    ),
    'package path' => array(
        'command' => 'package path',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'If you want to contribute to a package, this is a great way to jump to it.

## OPTIONS

[<name>]
: Name of the package to get the directory for.

## EXAMPLES

    # Get package path.
    $ wp package path
    /home/person/.wp-cli/packages/

    # Get path to an installed package.
    $ wp package path wp-cli/server-command
    /home/person/.wp-cli/packages/vendor/wp-cli/server-command

    # Change directory to package path.
    $ cd $(wp package path) && pwd
    /home/vagrant/.wp-cli/packages',
        'name' => 'path',
        'params' => array(
            array(
                'name' => 'name',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Gets the path to an installed WP-CLI package, or the package directory.',
        'synopsis' => '[<name>]',
    ),
    'package uninstall' => array(
        'command' => 'package uninstall',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<name>
: Name of the package to uninstall.

[--insecure]
: Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Uninstall package.
    $ wp package uninstall wp-cli/server-command
    Removing require statement for package \'wp-cli/server-command\' from /home/person/.wp-cli/packages/composer.json
    Removing repository details from /home/person/.wp-cli/packages/composer.json
    Removing package directories and regenerating autoloader...
    Success: Uninstalled package.',
        'name' => 'uninstall',
        'params' => array(
            array(
                'name' => 'name',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'insecure',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Uninstalls a WP-CLI package.',
        'synopsis' => '<name> [--insecure]',
    ),
    'package update' => array(
        'command' => 'package update',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## EXAMPLES

    $ wp package update
    Using Composer to update packages...
    ---
    Loading composer repositories with package information
    Updating dependencies
    Resolving dependencies through SAT
    Dependency resolution completed in 0.074 seconds
    Analyzed 1062 packages to resolve dependencies
    Analyzed 22383 rules to resolve dependencies
    Writing lock file
    Generating autoload files
    ---
    Success: Packages updated.',
        'name' => 'update',
        'params' => array(),
        'shortdesc' => 'Updates all installed WP-CLI packages to their latest version.',
        'synopsis' => '',
    ),
);
