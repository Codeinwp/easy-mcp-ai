<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'theme activate' => array(
        'command' => 'theme activate',
        'fields' => null,
        'longdesc' => '## OPTIONS

<theme>
: The theme to activate.

## EXAMPLES

    $ wp theme activate twentysixteen
    Success: Switched to \'Twenty Sixteen\' theme.',
        'name' => 'activate',
        'params' => array(
            array(
                'name' => 'theme',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Activates a theme.',
        'synopsis' => '<theme>',
    ),
    'theme auto-updates disable' => array(
        'command' => 'theme auto-updates disable',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<theme>...]
: One or more themes to disable auto-updates for.

[--all]
: If set, auto-updates will be disabled for all themes.

[--enabled-only]
: If set, filters list of themes to only include the ones that have
auto-updates enabled.

## EXAMPLES

    # Disable the auto-updates for a theme
    $ wp theme auto-updates disable twentysixteen
    Theme auto-updates for \'twentysixteen\' disabled.
    Success: Disabled 1 of 1 theme auto-updates.',
        'name' => 'disable',
        'params' => array(
            array(
                'name' => 'theme',
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
        'shortdesc' => 'Disables the auto-updates for a theme.',
        'synopsis' => '[<theme>...] [--all] [--enabled-only]',
    ),
    'theme auto-updates enable' => array(
        'command' => 'theme auto-updates enable',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<theme>...]
: One or more themes to enable auto-updates for.

[--all]
: If set, auto-updates will be enabled for all themes.

[--disabled-only]
: If set, filters list of themes to only include the ones that have
auto-updates disabled.

## EXAMPLES

    # Enable the auto-updates for a theme
    $ wp theme auto-updates enable twentysixteen
    Theme auto-updates for \'twentysixteen\' enabled.
    Success: Enabled 1 of 1 theme auto-updates.',
        'name' => 'enable',
        'params' => array(
            array(
                'name' => 'theme',
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
        'shortdesc' => 'Enables the auto-updates for a theme.',
        'synopsis' => '[<theme>...] [--all] [--disabled-only]',
    ),
    'theme auto-updates status' => array(
        'command' => 'theme auto-updates status',
        'fields' => array(
            'default' => array(
                'name',
                'status',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[<theme>...]
: One or more themes to show the status of the auto-updates of.

[--all]
: If set, the status of auto-updates for all themes will be shown.

[--enabled-only]
: If set, filters list of themes to only include the ones that have
auto-updates enabled.

[--disabled-only]
: If set, filters list of themes to only include the ones that have
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

    # Get the status of theme auto-updates
    $ wp theme auto-updates status twentysixteen
    +---------------+----------+
    | name          | status   |
    +---------------+----------+
    | twentysixteen | disabled |
    +---------------+----------+

    # Get the list of themes that have auto-updates enabled
    $ wp theme auto-updates status --all --enabled-only --field=name
    twentysixteen
    twentyseventeen',
        'name' => 'status',
        'params' => array(
            array(
                'name' => 'theme',
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
        'shortdesc' => 'Shows the status of auto-updates for a theme.',
        'synopsis' => '[<theme>...] [--all] [--enabled-only] [--disabled-only] [--field=<field>] [--format=<format>]',
    ),
    'theme delete' => array(
        'alias' => 'uninstall',
        'command' => 'theme delete',
        'fields' => null,
        'longdesc' => 'Removes the theme or themes from the filesystem.

## OPTIONS

[<theme>...]
: One or more themes to delete.

[--all]
: If set, all themes will be deleted except active theme.

[--force]
: To delete active theme use this.

## EXAMPLES

    $ wp theme delete twentytwelve
    Deleted \'twentytwelve\' theme.
    Success: Deleted 1 of 1 themes.',
        'name' => 'delete',
        'params' => array(
            array(
                'name' => 'theme',
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
                'name' => 'force',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Deletes one or more themes.',
        'synopsis' => '[<theme>...] [--all] [--force]',
    ),
    'theme disable' => array(
        'command' => 'theme disable',
        'fields' => null,
        'longdesc' => 'Removes ability for a theme to be activated from the dashboard of a site
on a WordPress multisite install.

## OPTIONS

<theme>
: The theme to disable.

[--network]
: If set, the theme is disabled on the network level. Note that
individual sites may still have this theme enabled if it was
enabled for them independently.

## EXAMPLES

    # Disable theme
    $ wp theme disable twentysixteen
    Success: Disabled the \'Twenty Sixteen\' theme.

    # Disable theme in network level
    $ wp theme disable twentysixteen --network
    Success: Network disabled the \'Twenty Sixteen\' theme.',
        'name' => 'disable',
        'params' => array(
            array(
                'name' => 'theme',
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
        'shortdesc' => 'Disables a theme on a WordPress multisite install.',
        'synopsis' => '<theme> [--network]',
    ),
    'theme enable' => array(
        'command' => 'theme enable',
        'fields' => null,
        'longdesc' => 'Permits theme to be activated from the dashboard of a site on a WordPress
multisite install.

## OPTIONS

<theme>
: The theme to enable.

[--network]
: If set, the theme is enabled for the entire network

[--activate]
: If set, the theme is activated for the current site. Note that
the "network" flag has no influence on this.

## EXAMPLES

    # Enable theme
    $ wp theme enable twentysixteen
    Success: Enabled the \'Twenty Sixteen\' theme.

    # Network enable theme
    $ wp theme enable twentysixteen --network
    Success: Network enabled the \'Twenty Sixteen\' theme.

    # Network enable and activate theme for current site
    $ wp theme enable twentysixteen --activate
    Success: Enabled the \'Twenty Sixteen\' theme.
    Success: Switched to \'Twenty Sixteen\' theme.',
        'name' => 'enable',
        'params' => array(
            array(
                'name' => 'theme',
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
            array(
                'name' => 'activate',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Enables a theme on a WordPress multisite install.',
        'synopsis' => '<theme> [--network] [--activate]',
    ),
    'theme get' => array(
        'command' => 'theme get',
        'fields' => array(
            'default' => array(
                'name',
                'title',
                'version',
                'status',
                'parent_theme',
                'template_dir',
                'stylesheet_dir',
                'template',
                'stylesheet',
                'screenshot',
                'description',
                'author',
                'tags',
                'theme_root',
                'theme_root_uri',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

<theme>
: The theme to get.

[--field=<field>]
: Instead of returning the whole theme, returns the value of a single field.

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

    $ wp theme get twentysixteen --fields=name,title,version
    +---------+----------------+
    | Field   | Value          |
    +---------+----------------+
    | name    | Twenty Sixteen |
    | title   | Twenty Sixteen |
    | version | 1.2            |
    +---------+----------------+',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'theme',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Instead of returning the whole theme, returns the value of a single field.',
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
        'shortdesc' => 'Gets details about a theme.',
        'synopsis' => '<theme> [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'theme install' => array(
        'command' => 'theme install',
        'fields' => null,
        'longdesc' => '## OPTIONS

<theme|zip|url>...
: One or more themes to install. Accepts a theme slug, the path to a local zip file, or a URL to a remote zip file.

[--version=<version>]
: If set, get that particular version from wordpress.org, instead of the
stable version.

[--force]
: If set, the command will overwrite any installed version of the theme, without prompting
for confirmation.

[--ignore-requirements]
: If set, the command will install the theme while ignoring any WordPress or PHP version requirements
specified by the theme authors.

[--activate]
: If set, the theme will be activated immediately after install.

[--insecure]
: Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Install the latest version from wordpress.org and activate
    $ wp theme install twentysixteen --activate
    Installing Twenty Sixteen (1.2)
    Downloading install package from http://downloads.wordpress.org/theme/twentysixteen.1.2.zip...
    Unpacking the package...
    Installing the theme...
    Theme installed successfully.
    Activating \'twentysixteen\'...
    Success: Switched to \'Twenty Sixteen\' theme.
    Success: Installed 1 of 1 themes.

    # Install from a local zip file
    $ wp theme install ../my-theme.zip

    # Install from a remote zip file
    $ wp theme install http://s3.amazonaws.com/bucketname/my-theme.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef',
        'name' => 'install',
        'params' => array(
            array(
                'name' => 'theme|zip|url',
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
                'name' => 'insecure',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Installs one or more themes.',
        'synopsis' => '<theme|zip|url>... [--version=<version>] [--force] [--ignore-requirements] [--activate] [--insecure]',
    ),
    'theme is-active' => array(
        'command' => 'theme is-active',
        'fields' => null,
        'longdesc' => 'Returns exit code 0 when active, 1 when not active.

## OPTIONS

<theme>
: The plugin to check.

## EXAMPLES

    # Check whether theme is Active; exit status 0 if active, otherwise 1
    $ wp theme is-active twentyfifteen
    $ echo $?
    1',
        'name' => 'is-active',
        'params' => array(
            array(
                'name' => 'theme',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Checks if a given theme is active.',
        'synopsis' => '<theme>',
    ),
    'theme is-installed' => array(
        'command' => 'theme is-installed',
        'fields' => null,
        'longdesc' => 'Returns exit code 0 when installed, 1 when uninstalled.

## OPTIONS

<theme>
: The theme to check.

## EXAMPLES

    # Check whether theme is installed; exit status 0 if installed, otherwise 1
    $ wp theme is-installed hello
    $ echo $?
    1',
        'name' => 'is-installed',
        'params' => array(
            array(
                'name' => 'theme',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Checks if a given theme is installed.',
        'synopsis' => '<theme>',
    ),
    'theme list' => array(
        'command' => 'theme list',
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
            ),
        ),
        'longdesc' => '## OPTIONS

[--<field>=<value>]
: Filter results based on the value of a field.

[--field=<field>]
: Prints the value of a single field for each theme.

[--fields=<fields>]
: Limit the output to specific object fields.

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

[--status=<status>]
: Filter the output by theme status.
---
options:
  - active
  - parent
  - inactive
---

[--skip-update-check]
: If set, the theme update check will be skipped.

## AVAILABLE FIELDS

These fields will be displayed by default for each theme:

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

## EXAMPLES

    # List inactive themes.
    $ wp theme list --status=inactive --format=csv
    name,status,update,version,update_version,auto_update
    twentyfourteen,inactive,none,3.8,,off
    twentysixteen,inactive,available,3.0,3.1,off',
        'name' => 'list',
        'params' => array(
            array(
                'name' => '',
                'optional' => true,
                'repeating' => false,
                'type' => 'generic',
            ),
            array(
                'desc' => 'Prints the value of a single field for each theme.',
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
                    'json',
                    'count',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Filter the output by theme status.',
                'name' => 'status',
                'optional' => true,
                'options' => array(
                    'active',
                    'parent',
                    'inactive',
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
        ),
        'shortdesc' => 'Gets a list of themes.',
        'synopsis' => '[--<field>=<value>] [--field=<field>] [--fields=<fields>] [--format=<format>] [--status=<status>] [--skip-update-check]',
    ),
    'theme mod get' => array(
        'command' => 'theme mod get',
        'fields' => array(
            'default' => array(
                'key',
                'value',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[<mod>...]
: One or more mods to get.

[--field=<field>]
: Returns the value of a single field.

[--all]
: List all theme mods

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - json
  - csv
  - yaml
---

## EXAMPLES

    # Get all theme mods.
    $ wp theme mod get --all
    +------------------+---------+
    | key              | value   |
    +------------------+---------+
    | background_color | dd3333  |
    | link_color       | #dd9933 |
    | main_text_color  | #8224e3 |
    +------------------+---------+

    # Get single theme mod in JSON format.
    $ wp theme mod get background_color --format=json
    [{"key":"background_color","value":"dd3333"}]

    # Get value of a single theme mod.
    $ wp theme mod get background_color --field=value
    dd3333

    # Get multiple theme mods.
    $ wp theme mod get background_color header_textcolor
    +------------------+--------+
    | key              | value  |
    +------------------+--------+
    | background_color | dd3333 |
    | header_textcolor |        |
    +------------------+--------+',
        'name' => 'get',
        'params' => array(
            array(
                'name' => 'mod',
                'optional' => true,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Returns the value of a single field.',
                'name' => 'field',
                'optional' => true,
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
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format.',
                'name' => 'format',
                'optional' => true,
                'options' => array(
                    'table',
                    'json',
                    'csv',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets one or more theme mods.',
        'synopsis' => '[<mod>...] [--field=<field>] [--all] [--format=<format>]',
    ),
    'theme mod list' => array(
        'command' => 'theme mod list',
        'fields' => array(
            'default' => array(
                'key',
                'value',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[--field=<field>]
: Returns the value of a single field.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
  - table
  - json
  - csv
  - yaml
---

## EXAMPLES

    # Gets a list of theme mods.
    $ wp theme mod list
    +------------------+---------+
    | key              | value   |
    +------------------+---------+
    | background_color | dd3333  |
    | link_color       | #dd9933 |
    | main_text_color  | #8224e3 |
    +------------------+---------+',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'Returns the value of a single field.',
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
                    'json',
                    'csv',
                    'yaml',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Gets a list of theme mods.',
        'synopsis' => '[--field=<field>] [--format=<format>]',
    ),
    'theme mod remove' => array(
        'command' => 'theme mod remove',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<mod>...]
: One or more mods to remove.

[--all]
: Remove all theme mods.

## EXAMPLES

    # Remove all theme mods.
    $ wp theme mod remove --all
    Success: Theme mods removed.

    # Remove single theme mod.
    $ wp theme mod remove background_color
    Success: 1 mod removed.

    # Remove multiple theme mods.
    $ wp theme mod remove background_color header_textcolor
    Success: 2 mods removed.',
        'name' => 'remove',
        'params' => array(
            array(
                'name' => 'mod',
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
        'shortdesc' => 'Removes one or more theme mods.',
        'synopsis' => '[<mod>...] [--all]',
    ),
    'theme mod set' => array(
        'command' => 'theme mod set',
        'fields' => null,
        'longdesc' => '## OPTIONS

<mod>
: The name of the theme mod to set or update.

<value>
: The new value.

## EXAMPLES

    # Set theme mod
    $ wp theme mod set background_color 000000
    Success: Theme mod background_color set to 000000.',
        'name' => 'set',
        'params' => array(
            array(
                'name' => 'mod',
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
        ),
        'shortdesc' => 'Sets the value of a theme mod.',
        'synopsis' => '<mod> <value>',
    ),
    'theme path' => array(
        'command' => 'theme path',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<theme>]
: The theme to get the path to. Path includes "style.css" file.
If not set, will return the path to the themes directory.

[--dir]
: If set, get the path to the closest parent directory, instead of the
theme\'s "style.css" file.

## EXAMPLES

    # Get theme path
    $ wp theme path
    /var/www/example.com/public_html/wp-content/themes

    # Change directory to theme path
    $ cd $(wp theme path)',
        'name' => 'path',
        'params' => array(
            array(
                'name' => 'theme',
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
        'shortdesc' => 'Gets the path to a theme or to the theme directory.',
        'synopsis' => '[<theme>] [--dir]',
    ),
    'theme search' => array(
        'command' => 'theme search',
        'fields' => array(
            'default' => array(
                'name',
                'slug',
                'rating',
            ),
            'optional' => array(),
        ),
        'longdesc' => 'Displays themes in the WordPress.org theme directory matching a given
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
: Optional number of results to display. Defaults to 10.

[--field=<field>]
: Prints the value of a single field for each theme.

[--fields=<fields>]
: Ask for specific fields from the API. Defaults to name,slug,author,rating. Acceptable values:

    **name**: Theme Name
    **slug**: Theme Slug
    **version**: Current Version Number
    **author**: Theme Author
    **preview_url**: Theme Preview URL
    **screenshot_url**: Theme Screenshot URL
    **rating**: Theme Rating
    **num_ratings**: Number of Theme Ratings
    **homepage**: Theme Author\'s Homepage
    **description**: Theme Description
    **url**: Theme\'s URL on wordpress.org

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

    $ wp theme search photo --per-page=6
    Success: Showing 6 of 203 themes.
    +----------------------+----------------------+--------+
    | name                 | slug                 | rating |
    +----------------------+----------------------+--------+
    | Photos               | photos               | 100    |
    | Infinite Photography | infinite-photography | 100    |
    | PhotoBook            | photobook            | 100    |
    | BG Photo Frame       | bg-photo-frame       | 0      |
    | fPhotography         | fphotography         | 0      |
    | Photo Perfect        | photo-perfect        | 98     |
    +----------------------+----------------------+--------+',
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
                'desc' => 'Optional number of results to display. Defaults to 10.',
                'name' => 'per-page',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Prints the value of a single field for each theme.',
                'name' => 'field',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Ask for specific fields from the API. Defaults to name,slug,author,rating. Acceptable values:',
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
        'shortdesc' => 'Searches the WordPress.org theme directory.',
        'synopsis' => '<search> [--page=<page>] [--per-page=<per-page>] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'theme status' => array(
        'command' => 'theme status',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<theme>]
: A particular theme to show the status for.

## EXAMPLES

    $ wp theme status twentysixteen
    Theme twentysixteen details:
         Name: Twenty Sixteen
         Status: Inactive
         Version: 1.2
         Author: the WordPress team',
        'name' => 'status',
        'params' => array(
            array(
                'name' => 'theme',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Reveals the status of one or all themes.',
        'synopsis' => '[<theme>]',
    ),
    'theme update' => array(
        'alias' => 'upgrade',
        'command' => 'theme update',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<theme>...]
: One or more themes to update.

[--all]
: If set, all themes that have updates will be updated.

[--exclude=<theme-names>]
: Comma separated list of theme names that should be excluded from updating.

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
: If set, the theme will be updated to the specified version.

[--dry-run]
: Preview which themes would be updated.

[--insecure]
: Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Update multiple themes
    $ wp theme update twentyfifteen twentysixteen
    Downloading update from https://downloads.wordpress.org/theme/twentyfifteen.1.5.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the theme...
    Theme updated successfully.
    Downloading update from https://downloads.wordpress.org/theme/twentysixteen.1.2.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the theme...
    Theme updated successfully.
    +---------------+-------------+-------------+---------+
    | name          | old_version | new_version | status  |
    +---------------+-------------+-------------+---------+
    | twentyfifteen | 1.4         | 1.5         | Updated |
    | twentysixteen | 1.1         | 1.2         | Updated |
    +---------------+-------------+-------------+---------+
    Success: Updated 2 of 2 themes.

    # Exclude themes updates when bulk updating the themes
    $ wp theme update --all --exclude=twentyfifteen
    Downloading update from https://downloads.wordpress.org/theme/astra.1.0.5.1.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the theme...
    Theme updated successfully.
    Downloading update from https://downloads.wordpress.org/theme/twentyseventeen.1.2.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the theme...
    Theme updated successfully.
    +-----------------+----------+---------+----------------+
    | name            | status   | version | update_version |
    +-----------------+----------+---------+----------------+
    | astra           | inactive | 1.0.1   | 1.0.5.1        |
    | twentyseventeen | inactive | 1.1     | 1.2            |
    +-----------------+----------+---------+----------------+
    Success: Updated 2 of 2 themes.

    # Update all themes
    $ wp theme update --all',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'theme',
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
                'desc' => 'Comma separated list of theme names that should be excluded from updating.',
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
                'desc' => 'If set, the theme will be updated to the specified version.',
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
        'shortdesc' => 'Updates one or more themes.',
        'synopsis' => '[<theme>...] [--all] [--exclude=<theme-names>] [--minor] [--patch] [--format=<format>] [--version=<version>] [--dry-run] [--insecure]',
    ),
);
