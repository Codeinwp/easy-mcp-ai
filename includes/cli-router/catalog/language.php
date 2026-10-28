<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'language core activate' => array(
        'command' => 'language core activate',
        'fields' => null,
        'longdesc' => '**Warning: `wp language core activate` is deprecated. Use `wp site switch-language` instead.**

## OPTIONS

<language>
: Language code to activate.

## EXAMPLES

    # Activate the given language.
    $ wp language core activate ja
    Success: Language activated.',
        'name' => 'activate',
        'params' => array(
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Activates a given language.',
        'synopsis' => '<language>',
    ),
    'language core install' => array(
        'command' => 'language core install',
        'fields' => null,
        'longdesc' => 'Downloads the language pack from WordPress.org. Find your language code at: https://translate.wordpress.org/

## OPTIONS

<language>...
: Language code to install.

[--activate]
: If set, the language will be activated immediately after install.

## EXAMPLES

    # Install the Brazilian Portuguese language.
    $ wp language core install pt_BR
    Downloading translation from https://downloads.wordpress.org/translation/core/6.5/pt_BR.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the translation...
    Translation updated successfully.
    Language \'pt_BR\' installed.
    Success: Installed 1 of 1 languages.',
        'name' => 'install',
        'params' => array(
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'name' => 'activate',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Installs a given language.',
        'synopsis' => '<language>... [--activate]',
    ),
    'language core is-installed' => array(
        'command' => 'language core is-installed',
        'fields' => null,
        'longdesc' => 'Returns exit code 0 when installed, 1 when uninstalled.

## OPTIONS

<language>
: The language code to check.

## EXAMPLES

    # Check whether the German language is installed; exit status 0 if installed, otherwise 1.
    $ wp language core is-installed de_DE
    $ echo $?
    1',
        'name' => 'is-installed',
        'params' => array(
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Checks if a given language is installed.',
        'synopsis' => '<language>',
    ),
    'language core list' => array(
        'command' => 'language core list',
        'fields' => array(
            'default' => array(
                'language',
                'english_name',
                'native_name',
                'status',
                'update',
                'updated',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[--field=<field>]
: Display the value of a single field

[--<field>=<value>]
: Filter results by key=value pairs.

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
---

## AVAILABLE FIELDS

These fields will be displayed by default for each translation:

* language
* english_name
* native_name
* status
* update
* updated

## EXAMPLES

    # List language,english_name,status fields of available languages.
    $ wp language core list --fields=language,english_name,status
    +----------------+-------------------------+-------------+
    | language       | english_name            | status      |
    +----------------+-------------------------+-------------+
    | ar             | Arabic                  | uninstalled |
    | ary            | Moroccan Arabic         | uninstalled |
    | az             | Azerbaijani             | uninstalled |',
        'name' => 'list',
        'params' => array(
            array(
                'desc' => 'Display the value of a single field',
                'name' => 'field',
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
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists all available languages.',
        'synopsis' => '[--field=<field>] [--<field>=<value>] [--fields=<fields>] [--format=<format>]',
    ),
    'language core uninstall' => array(
        'command' => 'language core uninstall',
        'fields' => null,
        'longdesc' => '## OPTIONS

<language>...
: Language code to uninstall.

## EXAMPLES

    # Uninstall the Japanese core language pack.
    $ wp language core uninstall ja
    Success: Language uninstalled.',
        'name' => 'uninstall',
        'params' => array(
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Uninstalls a given language.',
        'synopsis' => '<language>...',
    ),
    'language core update' => array(
        'command' => 'language core update',
        'fields' => null,
        'longdesc' => '## OPTIONS

[--dry-run]
: Preview which translations would be updated.

## EXAMPLES

    # Update installed core languages packs.
    $ wp language core update
    Updating \'Japanese\' translation for WordPress 6.4.3...
    Downloading translation from https://downloads.wordpress.org/translation/core/6.4.3/ja.zip...
    Translation updated successfully.
    Success: Updated 1/1 translation.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'dry-run',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Updates installed languages for core.',
        'synopsis' => '[--dry-run]',
    ),
    'language plugin install' => array(
        'command' => 'language plugin install',
        'fields' => null,
        'longdesc' => 'Downloads the language pack from WordPress.org.

## OPTIONS

[<plugin>]
: Plugin to install language for.

[--all]
: If set, languages for all plugins will be installed.

<language>...
: Language code to install.

[--format=<format>]
: Render output in a particular format. Used when installing languages for all plugins.
---
default: table
options:
  - table
  - csv
  - json
  - summary
---

## EXAMPLES

    # Install the Japanese language for Akismet.
    $ wp language plugin install akismet ja
    Downloading translation from https://downloads.wordpress.org/translation/plugin/akismet/4.0.3/ja.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the translation...
    Translation updated successfully.
    Language \'ja\' installed.
    Success: Installed 1 of 1 languages.',
        'name' => 'install',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format. Used when installing languages for all plugins.',
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
        ),
        'shortdesc' => 'Installs a given language for a plugin.',
        'synopsis' => '[<plugin>] [--all] <language>... [--format=<format>]',
    ),
    'language plugin is-installed' => array(
        'command' => 'language plugin is-installed',
        'fields' => null,
        'longdesc' => 'Returns exit code 0 when installed, 1 when uninstalled.

## OPTIONS

<plugin>
: Plugin to check for.

<language>...
: The language code to check.

## EXAMPLES

    # Check whether the German language is installed for Akismet; exit status 0 if installed, otherwise 1.
    $ wp language plugin is-installed akismet de_DE
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
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Checks if a given language is installed.',
        'synopsis' => '<plugin> <language>...',
    ),
    'language plugin list' => array(
        'command' => 'language plugin list',
        'fields' => array(
            'default' => array(
                'plugin',
                'language',
                'english_name',
                'native_name',
                'status',
                'update',
                'updated',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to list languages for.

[--all]
: If set, available languages for all plugins will be listed.

[--field=<field>]
: Display the value of a single field.

[--<field>=<value>]
: Filter results by key=value pairs.

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
---

## AVAILABLE FIELDS

These fields will be displayed by default for each translation:

* plugin
* language
* english_name
* native_name
* status
* update
* updated

## EXAMPLES

    # List available language packs for the plugin.
    $ wp language plugin list hello-dolly --fields=language,english_name,status
    +----------------+-------------------------+-------------+
    | language       | english_name            | status      |
    +----------------+-------------------------+-------------+
    | ar             | Arabic                  | uninstalled |
    | ary            | Moroccan Arabic         | uninstalled |
    | az             | Azerbaijani             | uninstalled |',
        'name' => 'list',
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
                'desc' => 'Display the value of a single field.',
                'name' => 'field',
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
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists all available languages for one or more plugins.',
        'synopsis' => '[<plugin>...] [--all] [--field=<field>] [--<field>=<value>] [--fields=<fields>] [--format=<format>]',
    ),
    'language plugin uninstall' => array(
        'command' => 'language plugin uninstall',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>]
: Plugin to uninstall language for.

[--all]
: If set, languages for all plugins will be uninstalled.

<language>...
: Language code to uninstall.

[--format=<format>]
: Render output in a particular format. Used when installing languages for all plugins.
---
default: table
options:
  - table
  - csv
  - json
  - summary
---

## EXAMPLES

    # Uninstall the Japanese plugin language pack for Hello Dolly.
    $ wp language plugin uninstall hello-dolly ja
    Language \'ja\' for \'hello-dolly\' uninstalled.
    +-------------+--------+-------------+
    | name        | locale | status      |
    +-------------+--------+-------------+
    | hello-dolly | ja     | uninstalled |
    +-------------+--------+-------------+
    Success: Uninstalled 1 of 1 languages.',
        'name' => 'uninstall',
        'params' => array(
            array(
                'name' => 'plugin',
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
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format. Used when installing languages for all plugins.',
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
        ),
        'shortdesc' => 'Uninstalls a given language for a plugin.',
        'synopsis' => '[<plugin>] [--all] <language>... [--format=<format>]',
    ),
    'language plugin update' => array(
        'command' => 'language plugin update',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<plugin>...]
: One or more plugins to update languages for.

[--all]
: If set, languages for all plugins will be updated.

[--dry-run]
: Preview which translations would be updated.

## EXAMPLES

    # Update all installed language packs for all plugins.
    $ wp language plugin update --all
    Updating \'Japanese\' translation for Akismet 3.1.11...
    Downloading translation from https://downloads.wordpress.org/translation/plugin/akismet/3.1.11/ja.zip...
    Translation updated successfully.
    Success: Updated 1/1 translation.',
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
                'name' => 'dry-run',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Updates installed languages for one or more plugins.',
        'synopsis' => '[<plugin>...] [--all] [--dry-run]',
    ),
    'language theme install' => array(
        'command' => 'language theme install',
        'fields' => null,
        'longdesc' => 'Downloads the language pack from WordPress.org.

## OPTIONS

[<theme>]
: Theme to install language for.

[--all]
: If set, languages for all themes will be installed.

<language>...
: Language code to install.

[--format=<format>]
: Render output in a particular format. Used when installing languages for all themes.
---
default: table
options:
  - table
  - csv
  - json
  - summary
---

## EXAMPLES

    # Install the Japanese language for Twenty Seventeen.
    $ wp language theme install twentyseventeen ja
    Downloading translation from https://downloads.wordpress.org/translation/theme/twentyseventeen/1.3/ja.zip...
    Unpacking the update...
    Installing the latest version...
    Translation updated successfully.
    Language \'ja\' installed.
    Success: Installed 1 of 1 languages.',
        'name' => 'install',
        'params' => array(
            array(
                'name' => 'theme',
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
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format. Used when installing languages for all themes.',
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
        ),
        'shortdesc' => 'Installs a given language for a theme.',
        'synopsis' => '[<theme>] [--all] <language>... [--format=<format>]',
    ),
    'language theme is-installed' => array(
        'command' => 'language theme is-installed',
        'fields' => null,
        'longdesc' => 'Returns exit code 0 when installed, 1 when uninstalled.

## OPTIONS

<theme>
: Theme to check for.

<language>...
: The language code to check.

## EXAMPLES

    # Check whether the German language is installed for Twenty Seventeen; exit status 0 if installed, otherwise 1.
    $ wp language theme is-installed twentyseventeen de_DE
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
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Checks if a given language is installed.',
        'synopsis' => '<theme> <language>...',
    ),
    'language theme list' => array(
        'command' => 'language theme list',
        'fields' => array(
            'default' => array(
                'theme',
                'language',
                'english_name',
                'native_name',
                'status',
                'update',
                'updated',
            ),
            'optional' => array(),
        ),
        'longdesc' => '## OPTIONS

[<theme>...]
: One or more themes to list languages for.

[--all]
: If set, available languages for all themes will be listed.

[--field=<field>]
: Display the value of a single field.

[--<field>=<value>]
: Filter results by key=value pairs.

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
---

## AVAILABLE FIELDS

These fields will be displayed by default for each translation:

* theme
* language
* english_name
* native_name
* status
* update
* updated

## EXAMPLES

    # List available language packs for the theme.
    $ wp language theme list twentyten --fields=language,english_name,status
    +----------------+-------------------------+-------------+
    | language       | english_name            | status      |
    +----------------+-------------------------+-------------+
    | ar             | Arabic                  | uninstalled |
    | ary            | Moroccan Arabic         | uninstalled |
    | az             | Azerbaijani             | uninstalled |',
        'name' => 'list',
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
                'desc' => 'Display the value of a single field.',
                'name' => 'field',
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
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Lists all available languages for one or more themes.',
        'synopsis' => '[<theme>...] [--all] [--field=<field>] [--<field>=<value>] [--fields=<fields>] [--format=<format>]',
    ),
    'language theme uninstall' => array(
        'command' => 'language theme uninstall',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<theme>]
: Theme to uninstall language for.

[--all]
: If set, languages for all themes will be uninstalled.

<language>...
: Language code to uninstall.

[--format=<format>]
: Render output in a particular format. Used when installing languages for all themes.
---
default: table
options:
  - table
  - csv
  - json
  - summary
---

## EXAMPLES

    # Uninstall the Japanese theme language pack for Twenty Ten.
    $ wp language theme uninstall twentyten ja
    Language \'ja\' for \'twentyten\' uninstalled.
    +-----------+--------+-------------+
    | name      | locale | status      |
    +-----------+--------+-------------+
    | twentyten | ja     | uninstalled |
    +-----------+--------+-------------+
    Success: Uninstalled 1 of 1 languages.',
        'name' => 'uninstall',
        'params' => array(
            array(
                'name' => 'theme',
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
            array(
                'name' => 'language',
                'optional' => false,
                'repeating' => true,
                'type' => 'positional',
            ),
            array(
                'default' => 'table',
                'desc' => 'Render output in a particular format. Used when installing languages for all themes.',
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
        ),
        'shortdesc' => 'Uninstalls a given language for a theme.',
        'synopsis' => '[<theme>] [--all] <language>... [--format=<format>]',
    ),
    'language theme update' => array(
        'command' => 'language theme update',
        'fields' => null,
        'longdesc' => '## OPTIONS

[<theme>...]
: One or more themes to update languages for.

[--all]
: If set, languages for all themes will be updated.

[--dry-run]
: Preview which translations would be updated.

## EXAMPLES

    # Update all installed language packs for all themes.
    $ wp language theme update --all
    Updating \'Japanese\' translation for Twenty Fifteen 1.5...
    Downloading translation from https://downloads.wordpress.org/translation/theme/twentyfifteen/1.5/ja.zip...
    Translation updated successfully.
    Success: Updated 1/1 translation.',
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
                'name' => 'dry-run',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Updates installed languages for one or more themes.',
        'synopsis' => '[<theme>...] [--all] [--dry-run]',
    ),
);
