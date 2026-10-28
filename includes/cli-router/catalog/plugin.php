<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'plugin activate' => array(
        'command' => 'plugin activate',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to activate.

[--all]
: If set, all plugins will be activated.

[--exclude=<name>]
: Comma separated list of plugin slugs to be excluded from activation.

[--network]
: If set, the plugin will be activated for the entire multisite network.

## EXAMPLES

    # Activate plugin
    $ wp plugin activate hello
    Plugin \'hello\' activated.
    Success: Activated 1 of 1 plugins.

    # Activate plugin in entire multisite network
    $ wp plugin activate hello --network
    Plugin \'hello\' network activated.
    Success: Network activated 1 of 1 plugins.

    # Activate plugins that were recently active.
    $ wp plugin activate $(wp plugin list --recently-active --field=name)
    Plugin \'bbpress\' activated.
    Plugin \'buddypress\' activated.
    Success: Activated 2 of 2 plugins.

    # Activate plugins that were recently active on a multisite.
    $ wp plugin activate $(wp plugin list --recently-active --field=name) --network
    Plugin \'bbpress\' network activated.
    Plugin \'buddypress\' network activated.
    Success: Activated 2 of 2 plugins.',
        'name' => 'activate',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'desc' => 'Comma separated list of plugin slugs to be excluded from activation.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Activates one or more plugins.',
        'synopsis' => '[<plugin>...] [--all] [--exclude=<name>] [--network]',
    ),
    'plugin auto-updates disable' => array(
        'command' => 'plugin auto-updates disable',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to disable auto-updates for.

[--all]
: If set, auto-updates will be disabled for all plugins.

[--enabled-only]
: If set, filters list of plugins to only include the ones that have
auto-updates enabled.

## EXAMPLES

    # Disable the auto-updates for a plugin
    $ wp plugin auto-updates disable hello
    Plugin auto-updates for \'hello\' disabled.
    Success: Disabled 1 of 1 plugin auto-updates.',
        'name' => 'disable',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'name' => 'enabled-only',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Disables the auto-updates for a plugin.',
        'synopsis' => '[<plugin>...] [--all] [--enabled-only]',
    ),
    'plugin auto-updates enable' => array(
        'command' => 'plugin auto-updates enable',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to enable auto-updates for.

[--all]
: If set, auto-updates will be enabled for all plugins.

[--disabled-only]
: If set, filters list of plugins to only include the ones that have
auto-updates disabled.

## EXAMPLES

    # Enable the auto-updates for a plugin
    $ wp plugin auto-updates enable hello
    Plugin auto-updates for \'hello\' enabled.
    Success: Enabled 1 of 1 plugin auto-updates.',
        'name' => 'enable',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'name' => 'disabled-only',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Enables the auto-updates for a plugin.',
        'synopsis' => '[<plugin>...] [--all] [--disabled-only]',
    ),
    'plugin auto-updates status' => array(
        'command' => 'plugin auto-updates status',
        'fields' => array(
            'default' => array(
                'name',
                'status',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to show the status of the auto-updates of.

[--all]
: If set, the status of auto-updates for all plugins will be shown.

[--enabled-only]
: If set, filters list of plugins to only include the ones that have
auto-updates enabled.

[--disabled-only]
: If set, filters list of plugins to only include the ones that have
auto-updates disabled.

[--field=<field>]
: Only show the provided field.

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
---

## EXAMPLES

    # Get the status of plugin auto-updates
    $ wp plugin auto-updates status hello
    +-------+----------+
    | name  | status   |
    +-------+----------+
    | hello | disabled |
    +-------+----------+

    # Get the list of plugins that have auto-updates enabled
    $ wp plugin auto-updates status --all --enabled-only --field=name
    akismet
    duplicate-post',
        'name' => 'status',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'name' => 'enabled-only',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'disabled-only',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Only show the provided field.',
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
                    'yaml',
                    'count',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Shows the status of auto-updates for a plugin.',
        'synopsis' => '[<plugin>...] [--all] [--enabled-only] [--disabled-only] [--field=<field>] [--format=<format>]',
    ),
    'plugin deactivate' => array(
        'command' => 'plugin deactivate',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to deactivate.

[--uninstall]
: Uninstall the plugin after deactivation.

[--all]
: If set, all plugins will be deactivated.

[--exclude=<name>]
: Comma separated list of plugin slugs that should be excluded from deactivation.

[--network]
: If set, the plugin will be deactivated for the entire multisite network.

## EXAMPLES

    # Deactivate plugin
    $ wp plugin deactivate hello
    Plugin \'hello\' deactivated.
    Success: Deactivated 1 of 1 plugins.

    # Deactivate all plugins with exclusion
    $ wp plugin deactivate --all --exclude=hello,wordpress-seo
    Plugin \'contact-form-7\' deactivated.
    Plugin \'ninja-forms\' deactivated.
    Success: Deactivated 2 of 2 plugins.',
        'name' => 'deactivate',
        'params' => array(
            array(
                'name' => 'plugin',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'uninstall',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Comma separated list of plugin slugs that should be excluded from deactivation.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Deactivates one or more plugins.',
        'synopsis' => '[<plugin>...] [--uninstall] [--all] [--exclude=<name>] [--network]',
    ),
    'plugin delete' => array(
        'command' => 'plugin delete',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to delete.

[--all]
: If set, all plugins will be deleted.

[--exclude=<name>]
: Comma separated list of plugin slugs to be excluded from deletion.

## EXAMPLES

    # Delete plugin
    $ wp plugin delete hello
    Deleted \'hello\' plugin.
    Success: Deleted 1 of 1 plugins.

    # Delete inactive plugins
    $ wp plugin delete $(wp plugin list --status=inactive --field=name)
    Deleted \'tinymce-templates\' plugin.
    Success: Deleted 1 of 1 plugins.

    # Delete all plugins excluding specified ones
    $ wp plugin delete --all --exclude=hello-dolly,jetpack
    Deleted \'akismet\' plugin.
    Deleted \'tinymce-templates\' plugin.
    Success: Deleted 2 of 2 plugins.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'desc' => 'Comma separated list of plugin slugs to be excluded from deletion.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Deletes plugin files without deactivating or uninstalling.',
        'synopsis' => '[<plugin>...] [--all] [--exclude=<name>]',
    ),
    'plugin get' => array(
        'command' => 'plugin get',
        'fields' => array(
            'default' => array(
                'name',
                'title',
                'author',
                'version',
                'description',
                'status',
            ),
            'optional' => array(
                'requires_wp',
                'requires_php',
                'requires_plugins',
            ),
        ),
        'longdesc' => '## OPTIONS

<plugin>
: The plugin to get.

[--field=<field>]
: Instead of returning the whole plugin, returns the value of a single field.

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

## AVAILABLE FIELDS

These fields will be displayed by default for the plugin:

* name
* title
* author
* version
* description
* status

These fields are optionally available:

* requires_wp
* requires_php
* requires_plugins

## EXAMPLES

    # Get plugin details.
    $ wp plugin get bbpress --format=json
    {"name":"bbpress","title":"bbPress","author":"The bbPress Contributors","version":"2.6.9","description":"bbPress is forum software with a twist from the creators of WordPress.","status":"active"}',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'plugin',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Instead of returning the whole plugin, returns the value of a single field.',
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
        'shortdesc' => 'Gets details about an installed plugin.',
        'synopsis' => '<plugin> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'plugin install' => array(
        'command' => 'plugin install',
        'fields' => null,
        'longdesc' => '## OPTIONS

<plugin|zip|url>...
: One or more plugins to install. Accepts a plugin slug, the path to a local zip file, or a URL to a remote zip file.

[--version=<version>]
: If set, get that particular version from wordpress.org, instead of the
stable version.

[--force]
: If set, the command will overwrite any installed version of the plugin, without prompting
for confirmation.

[--ignore-requirements]
:If set, the command will install the plugin while ignoring any WordPress or PHP version requirements
specified by the plugin authors.

[--activate]
: If set, the plugin will be activated immediately after install.

[--activate-network]
: If set, the plugin will be network activated immediately after install

[--insecure]
: Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Install the latest version from wordpress.org and activate
    $ wp plugin install bbpress --activate
    Installing bbPress (2.5.9)
    Downloading install package from https://downloads.wordpress.org/plugin/bbpress.2.5.9.zip...
    Using cached file \'/home/vagrant/.wp-cli/cache/plugin/bbpress-2.5.9.zip\'...
    Unpacking the package...
    Installing the plugin...
    Plugin installed successfully.
    Activating \'bbpress\'...
    Plugin \'bbpress\' activated.
    Success: Installed 1 of 1 plugins.

    # Install the development version from wordpress.org
    $ wp plugin install bbpress --version=dev
    Installing bbPress (Development Version)
    Downloading install package from https://downloads.wordpress.org/plugin/bbpress.zip...
    Unpacking the package...
    Installing the plugin...
    Plugin installed successfully.
    Success: Installed 1 of 1 plugins.

    # Install from a local zip file
    $ wp plugin install ../my-plugin.zip
    Unpacking the package...
    Installing the plugin...
    Plugin installed successfully.
    Success: Installed 1 of 1 plugins.

    # Install from a remote zip file
    $ wp plugin install http://s3.amazonaws.com/bucketname/my-plugin.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
    Downloading install package from http://s3.amazonaws.com/bucketname/my-plugin.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
    Unpacking the package...
    Installing the plugin...
    Plugin installed successfully.
    Success: Installed 1 of 1 plugins.

    # Update from a remote zip file
    $ wp plugin install https://github.com/envato/wp-envato-market/archive/master.zip --force
    Downloading install package from https://github.com/envato/wp-envato-market/archive/master.zip
    Unpacking the package...
    Installing the plugin...
    Renamed Github-based project from \'wp-envato-market-master\' to \'wp-envato-market\'.
    Plugin updated successfully
    Success: Installed 1 of 1 plugins.

    # Forcefully re-install all installed plugins
    $ wp plugin install $(wp plugin list --field=name) --force
    Installing Akismet (3.1.11)
    Downloading install package from https://downloads.wordpress.org/plugin/akismet.3.1.11.zip...
    Unpacking the package...
    Installing the plugin...
    Removing the old version of the plugin...
    Plugin updated successfully
    Success: Installed 1 of 1 plugins.',
        'name' => 'install',
        'params' => array(
            array(
                'name' => 'plugin|zip|url',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'If set, get that particular version from wordpress.org, instead of the',
                'name' => 'version',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'force',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'ignore-requirements',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'activate',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'activate-network',
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
        'shortdesc' => 'Installs one or more plugins.',
        'synopsis' => '<plugin|zip|url>... [--version=<version>] [--force] [--ignore-requirements] [--activate] [--activate-network] [--insecure]',
    ),
    'plugin is-active' => array(
        'command' => 'plugin is-active',
        'fields' => null,
        'longdesc' => 'Returns exit code 0 when active, 1 when not active.

## OPTIONS

<plugin>
: The plugin to check.

[--network]
: If set, check if plugin is network-activated.

## EXAMPLES

    # Check whether plugin is Active; exit status 0 if active, otherwise 1
    $ wp plugin is-active hello
    $ echo $?
    1',
        'name' => 'is-active',
        'params' => array(
            array(
                'name' => 'plugin',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Checks if a given plugin is active.',
        'synopsis' => '<plugin> [--network]',
    ),
    'plugin is-installed' => array(
        'command' => 'plugin is-installed',
        'fields' => null,
        'longdesc' => 'Returns exit code 0 when installed, 1 when uninstalled.

## OPTIONS

<plugin>
: The plugin to check.

## EXAMPLES

    # Check whether plugin is installed; exit status 0 if installed, otherwise 1
    $ wp plugin is-installed hello
    $ echo $?
    1',
        'name' => 'is-installed',
        'params' => array(
            array(
                'name' => 'plugin',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Checks if a given plugin is installed.',
        'synopsis' => '<plugin>',
    ),
    'plugin list' => array(
        'command' => 'plugin list',
        'fields' => array(
            'default' => array(
                'name',
                'status',
                'update',
                'version',
                'update_version',
                'auto_update',
            ),
            'optional' => array(
                'update_package',
                'update_id',
                'title',
                'description',
                'file',
                'author',
                'tested_up_to',
                'requires',
                'requires_php',
                'wporg_status',
                'wporg_last_updated',
            ),
        ),
        'longdesc' => 'Displays a list of the plugins installed on the site with activation
status, whether or not there\'s an update available, etc.

Use `--status=dropin` to list installed dropins (e.g. `object-cache.php`).

## OPTIONS

[--<field>=<value>]
: Filter results based on the value of a field.

[--field=<field>]
: Prints the value of a single field for each plugin.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - count
  - json
  - yaml
---

[--status=<status>]
: Filter the output by plugin status.
---
options:
  - active
  - active-network
  - dropin
  - inactive
  - must-use
---

[--skip-update-check]
: If set, the plugin update check will be skipped.

[--recently-active]
: If set, only recently active plugins will be shown and the status filter will be ignored.

## AVAILABLE FIELDS

These fields will be displayed by default for each plugin:

* name
* status
* update
* version
* update_version
* auto_update

These fields are optionally available:

* update_package
* update_id
* title
* description
* file
* author
* tested_up_to
* requires
* requires_php
* wporg_status
* wporg_last_updated

## EXAMPLES

    # List active plugins on the site.
    $ wp plugin list --status=active --format=json
    [{"name":"dynamic-hostname","status":"active","update":"none","version":"0.4.2","update_version":"","auto_update":"off"},{"name":"tinymce-templates","status":"active","update":"none","version":"4.8.1","update_version":"","auto_update":"off"},{"name":"wp-multibyte-patch","status":"active","update":"none","version":"2.9","update_version":"","auto_update":"off"},{"name":"wp-total-hacks","status":"active","update":"none","version":"4.7.2","update_version":"","auto_update":"off"}]

    # List plugins on each site in a network.
    $ wp site list --field=url | xargs -I % wp plugin list --url=%
    +---------+----------------+-----------+---------+-----------------+------------+
    | name    | status         | update    | version | update_version | auto_update |
    +---------+----------------+-----------+---------+----------------+-------------+
    | akismet | active-network | none      | 5.3.1   |                | on          |
    | hello   | inactive       | available | 1.6     | 1.7.2          | off         |
    +---------+----------------+-----------+---------+----------------+-------------+
    +---------+----------------+-----------+---------+----------------+-------------+
    | name    | status         | update    | version | update_version | auto_update |
    +---------+----------------+-----------+---------+----------------+-------------+
    | akismet | active-network | none      | 5.3.1   |                | on          |
    | hello   | inactive       | available | 1.6     | 1.7.2          | off         |
    +---------+----------------+-----------+---------+----------------+-------------+

    # Check whether plugins are still active on WordPress.org
    $ wp plugin list --fields=name,wporg_status,wporg_last_updated
    +--------------------+--------------+--------------------+
    | name               | wporg_status | wporg_last_updated |
    +--------------------+--------------+--------------------+
    | akismet            | active       | 2023-12-11         |
    | user-switching     | active       | 2023-11-17         |
    | wordpress-importer | active       | 2023-04-28         |
    | local              |              |                    |
    +--------------------+--------------+--------------------+

    # List recently active plugins on the site.
    $ wp plugin list --recently-active --field=name --format=json
    ["akismet","bbpress","buddypress"]',
        'name' => 'list',
        'params' => array(
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Prints the value of a single field for each plugin.',
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
                    'count',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Filter the output by plugin status.',
                'name' => 'status',
                'optional' => true,
                'options' => array(
                    'active',
                    'active-network',
                    'dropin',
                    'inactive',
                    'must-use',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'skip-update-check',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'recently-active',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Gets a list of plugins.',
        'synopsis' => '[--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>] [--status=<status>] [--skip-update-check] [--recently-active]',
    ),
    'plugin path' => array(
        'command' => 'plugin path',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>]
: The plugin to get the path to. If not set, will return the path to the
plugins directory.

[--dir]
: If set, get the path to the closest parent directory, instead of the
plugin file.

## EXAMPLES

    $ cd $(wp plugin path) && pwd
    /var/www/wordpress/wp-content/plugins',
        'name' => 'path',
        'params' => array(
            array(
                'name' => 'plugin',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'dir',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Gets the path to a plugin or to the plugin directory.',
        'synopsis' => '[<plugin>] [--dir]',
    ),
    'plugin search' => array(
        'command' => 'plugin search',
        'fields' => array(
            'default' => array(
                'name',
                'slug',
                'rating',
            ),
            'optional' => array(),
        ),
        'longdesc' => 'Displays plugins in the WordPress.org plugin directory matching a given
search query.

## OPTIONS

<search>
: The string to search for.

[--page=<page>]
: Optional page to display.
---
default: 1
---

[--per-page=<per-page>]
: Optional number of results to display.
---
default: 10
---

[--field=<field>]
: Prints the value of a single field for each plugin.

[--fields=<fields>]
: Ask for specific fields from the API. Defaults to name,slug,author_profile,rating. Acceptable values:

    **name**: Plugin Name
    **slug**: Plugin Slug
    **version**: Current Version Number
    **author**: Plugin Author
    **author_profile**: Plugin Author Profile
    **contributors**: Plugin Contributors
    **requires**: Plugin Minimum Requirements
    **tested**: Plugin Tested Up To
    **compatibility**: Plugin Compatible With
    **rating**: Plugin Rating in Percent and Total Number
    **ratings**: Plugin Ratings for each star (1-5)
    **num_ratings**: Number of Plugin Ratings
    **homepage**: Plugin Author\'s Homepage
    **description**: Plugin\'s Description
    **short_description**: Plugin\'s Short Description
    **sections**: Plugin Readme Sections: description, installation, FAQ, screenshots, other notes, and changelog
    **downloaded**: Plugin Download Count
    **last_updated**: Plugin\'s Last Update
    **added**: Plugin\'s Date Added to wordpress.org Repository
    **tags**: Plugin\'s Tags
    **versions**: Plugin\'s Available Versions with D/L Link
    **donate_link**: Plugin\'s Donation Link
    **banners**: Plugin\'s Banner Image Link
    **icons**: Plugin\'s Icon Image Link
    **active_installs**: Plugin\'s Number of Active Installs
    **contributors**: Plugin\'s List of Contributors
    **url**: Plugin\'s URL on wordpress.org

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - count
  - json
  - yaml
---

## EXAMPLES

    $ wp plugin search dsgnwrks --per-page=20 --format=json
    Success: Showing 3 of 3 plugins.
    [{"name":"DsgnWrks Instagram Importer Debug","slug":"dsgnwrks-instagram-importer-debug","rating":0},{"name":"DsgnWrks Instagram Importer","slug":"dsgnwrks-instagram-importer","rating":84},{"name":"DsgnWrks Twitter Importer","slug":"dsgnwrks-twitter-importer","rating":80}]

    $ wp plugin search dsgnwrks --fields=name,version,slug,rating,num_ratings
    Success: Showing 3 of 3 plugins.
    +-----------------------------------+---------+-----------------------------------+--------+-------------+
    | name                              | version | slug                              | rating | num_ratings |
    +-----------------------------------+---------+-----------------------------------+--------+-------------+
    | DsgnWrks Instagram Importer Debug | 0.1.6   | dsgnwrks-instagram-importer-debug | 0      | 0           |
    | DsgnWrks Instagram Importer       | 1.3.7   | dsgnwrks-instagram-importer       | 84     | 23          |
    | DsgnWrks Twitter Importer         | 1.1.1   | dsgnwrks-twitter-importer         | 80     | 1           |
    +-----------------------------------+---------+-----------------------------------+--------+-------------+',
        'name' => 'search',
        'params' => array(
            array(
                'name' => 'search',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'default' => 1,
                'desc' => 'Optional page to display.',
                'name' => 'page',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 10,
                'desc' => 'Optional number of results to display.',
                'name' => 'per-page',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Prints the value of a single field for each plugin.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Ask for specific fields from the API. Defaults to name,slug,author_profile,rating. Acceptable values:',
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
                    'count',
                    'json',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Searches the WordPress.org plugin directory.',
        'synopsis' => '<search> [--page=<page>] [--per-page=<per-page>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'plugin status' => array(
        'command' => 'plugin status',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>]
: A particular plugin to show the status for.

## EXAMPLES

    # Displays status of all plugins
    $ wp plugin status
    5 installed plugins:
      I akismet                3.1.11
      I easy-digital-downloads 2.5.16
      A theme-check            20160523.1
      I wen-logo-slider        2.0.3
      M ns-pack                1.0.0
    Legend: I = Inactive, A = Active, M = Must Use

    # Displays status of a plugin
    $ wp plugin status theme-check
    Plugin theme-check details:
        Name: Theme Check
        Status: Active
        Version: 20160523.1
        Author: Otto42, pross
        Description: A simple and easy way to test your theme for all the latest WordPress standards and practices. A great theme development tool!',
        'name' => 'status',
        'params' => array(
            array(
                'name' => 'plugin',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Reveals the status of one or all plugins.',
        'synopsis' => '[<plugin>]',
    ),
    'plugin toggle' => array(
        'command' => 'plugin toggle',
        'fields' => null,
        'longdesc' => 'If the plugin is active, then it will be deactivated. If the plugin is
inactive, then it will be activated.

## OPTIONS

<plugin>...
: One or more plugins to toggle.

[--network]
: If set, the plugin will be toggled for the entire multisite network.

## EXAMPLES

    # Akismet is currently activated
    $ wp plugin toggle akismet
    Plugin \'akismet\' deactivated.
    Success: Toggled 1 of 1 plugins.

    # Akismet is currently deactivated
    $ wp plugin toggle akismet
    Plugin \'akismet\' activated.
    Success: Toggled 1 of 1 plugins.',
        'name' => 'toggle',
        'params' => array(
            array(
                'name' => 'plugin',
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
        ),
        'shortdesc' => 'Toggles a plugin\'s activation state.',
        'synopsis' => '<plugin>... [--network]',
    ),
    'plugin uninstall' => array(
        'command' => 'plugin uninstall',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to uninstall.

[--deactivate]
: Deactivate the plugin before uninstalling. Default behavior is to warn and skip if the plugin is active.

[--skip-delete]
: If set, the plugin files will not be deleted. Only the uninstall procedure
will be run.

[--all]
: If set, all plugins will be uninstalled.

[--exclude=<name>]
: Comma separated list of plugin slugs to be excluded from uninstall.

## EXAMPLES

    $ wp plugin uninstall hello
    Uninstalled and deleted \'hello\' plugin.
    Success: Uninstalled 1 of 1 plugins.

    # Uninstall all plugins excluding specified ones
    $ wp plugin uninstall --all --exclude=hello-dolly,jetpack
    Uninstalled and deleted \'akismet\' plugin.
    Uninstalled and deleted \'tinymce-templates\' plugin.
    Success: Uninstalled 2 of 2 plugins.',
        'name' => 'uninstall',
        'params' => array(
            array(
                'name' => 'plugin',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'deactivate',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-delete',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'all',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Comma separated list of plugin slugs to be excluded from uninstall.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Uninstalls one or more plugins.',
        'synopsis' => '[<plugin>...] [--deactivate] [--skip-delete] [--all] [--exclude=<name>]',
    ),
    'plugin update' => array(
        'alias' => 'upgrade',
        'command' => 'plugin update',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to update.

[--all]
: If set, all plugins that have updates will be updated.

[--exclude=<name>]
: Comma separated list of plugin names that should be excluded from updating.

[--minor]
: Only perform updates for minor releases (e.g. from 1.3 to 1.4 instead of 2.0)

[--patch]
: Only perform updates for patch releases (e.g. from 1.3 to 1.3.3 instead of 1.4)

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - csv
  - json
  - summary
---

[--version=<version>]
: If set, the plugin will be updated to the specified version.

[--dry-run]
: Preview which plugins would be updated.

[--insecure]
: Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    $ wp plugin update bbpress --version=dev
    Installing bbPress (Development Version)
    Downloading install package from https://downloads.wordpress.org/plugin/bbpress.zip...
    Unpacking the package...
    Installing the plugin...
    Removing the old version of the plugin...
    Plugin updated successfully.
    Success: Updated 1 of 2 plugins.

    $ wp plugin update --all
    Enabling Maintenance mode...
    Downloading update from https://downloads.wordpress.org/plugin/akismet.3.1.11.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the plugin...
    Plugin updated successfully.
    Downloading update from https://downloads.wordpress.org/plugin/nginx-champuru.3.2.0.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the plugin...
    Plugin updated successfully.
    Disabling Maintenance mode...
    +------------------------+-------------+-------------+---------+
    | name                   | old_version | new_version | status  |
    +------------------------+-------------+-------------+---------+
    | akismet                | 3.1.3       | 3.1.11      | Updated |
    | nginx-cache-controller | 3.1.1       | 3.2.0       | Updated |
    +------------------------+-------------+-------------+---------+
    Success: Updated 2 of 2 plugins.

    $ wp plugin update --all --exclude=akismet
    Enabling Maintenance mode...
    Downloading update from https://downloads.wordpress.org/plugin/nginx-champuru.3.2.0.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the plugin...
    Plugin updated successfully.
    Disabling Maintenance mode...
    +------------------------+-------------+-------------+---------+
    | name                   | old_version | new_version | status  |
    +------------------------+-------------+-------------+---------+
    | nginx-cache-controller | 3.1.1       | 3.2.0       | Updated |
    +------------------------+-------------+-------------+---------+',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'desc' => 'Comma separated list of plugin names that should be excluded from updating.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'minor',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'patch',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
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
                    'summary',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'If set, the plugin will be updated to the specified version.',
                'name' => 'version',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'dry-run',
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
        'shortdesc' => 'Updates one or more plugins.',
        'synopsis' => '[<plugin>...] [--all] [--exclude=<name>] [--minor] [--patch] [--format=<format>] [--version=<version>] [--dry-run] [--insecure]',
    ),
    'plugin verify-checksums' => array(
        'command' => 'plugin verify-checksums',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to verify.

[--all]
: If set, all plugins will be verified.

[--strict]
: If set, even "soft changes" like readme.txt changes will trigger
checksum errors.

[--version=<version>]
: Verify checksums against a specific plugin version.

[--format=<format>]
: Render output in a specific format.
---
default: table
options:
  - table
  - json
  - csv
  - yaml
  - count
---

[--insecure]
: Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

[--exclude=<name>]
: Comma separated list of plugin names that should be excluded from verifying.

## EXAMPLES

    # Verify the checksums of all installed plugins
    $ wp plugin verify-checksums --all
    Success: Verified 8 of 8 plugins.

    # Verify the checksums of a single plugin, Akismet in this case
    $ wp plugin verify-checksums akismet
    Success: Verified 1 of 1 plugins.',
        'name' => 'verify-checksums',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'name' => 'strict',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Verify checksums against a specific plugin version.',
                'name' => 'version',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a specific format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'json',
                    'csv',
                    'yaml',
                    'count',
                ),
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
            array(
                'desc' => 'Comma separated list of plugin names that should be excluded from verifying.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Verifies plugin files against WordPress.org\'s checksums.',
        'synopsis' => '[<plugin>...] [--all] [--strict] [--version=<version>] [--format=<format>] [--insecure] [--exclude=<name>]',
    ),
);
