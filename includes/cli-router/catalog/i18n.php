<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'i18n make-json' => array(
        'command' => 'i18n make-json',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'For JavaScript internationalization purposes, WordPress requires translations to be split up into
one Jed-formatted JSON file per JavaScript source file.

See https://make.wordpress.org/core/2018/11/09/new-javascript-i18n-support-in-wordpress/ to learn more
about WordPress JavaScript internationalization.

## OPTIONS

<source>
: Path to an existing PO file or a directory containing multiple PO files.

[<destination>]
: Path to the destination directory for the resulting JSON files. Defaults to the source directory.

[--domain=<domain>]
: Text domain to use for the JSON file name. Overrides the default one extracted from the PO file.

[--extensions=<extensions>]
: Additional custom JS extensions, comma separated list. By default searches for .min.js and .js extensions.

[--purge]
: Whether to purge the strings that were extracted from the original source file. Defaults to true, use `--no-purge` to skip the removal.

[--update-mo-files]
: Whether MO files should be updated as well after updating PO files.
Only has an effect when used in combination with `--purge`.

[--pretty-print]
: Pretty-print resulting JSON files.

[--use-map=<paths_or_maps>]
: Whether to use a mapping file for the strings, as a JSON value, array to specify multiple.
Each element can either be a string (file path) or object (map).

## EXAMPLES

    # Create JSON files for all PO files in the languages directory
    $ wp i18n make-json languages

    # Create JSON files for my-plugin-de_DE.po and leave the PO file untouched.
    $ wp i18n make-json my-plugin-de_DE.po /tmp --no-purge

    # Create JSON files with mapping
    $ wp i18n make-json languages --use-map=build/map.json

    # Create JSON files with multiple mappings
    $ wp i18n make-json languages \'--use-map=["build/map.json","build/map2.json"]\'

    # Create JSON files with object mapping
    $ wp i18n make-json languages \'--use-map={"source/index.js":"build/index.js"}\'',
        'name' => 'make-json',
        'params' => array(
            array(
                'name' => 'source',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'destination',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Text domain to use for the JSON file name. Overrides the default one extracted from the PO file.',
                'name' => 'domain',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Additional custom JS extensions, comma separated list. By default searches for .min.js and .js extensions.',
                'name' => 'extensions',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'purge',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'update-mo-files',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'pretty-print',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Whether to use a mapping file for the strings, as a JSON value, array to specify multiple.',
                'name' => 'use-map',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Extract JavaScript strings from PO files and add them to individual JSON files.',
        'synopsis' => '<source> [<destination>] [--domain=<domain>] [--extensions=<extensions>] [--purge] [--update-mo-files] [--pretty-print] [--use-map=<paths_or_maps>]',
    ),
    'i18n make-mo' => array(
        'command' => 'i18n make-mo',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<source>
: Path to an existing PO file or a directory containing multiple PO files.

[<destination>]
: Path to the destination file or directory for the resulting MO files. Defaults to the source directory.

## EXAMPLES

    # Create MO files for all PO files in the current directory.
    $ wp i18n make-mo .

    # Create a MO file from a single PO file in a specific directory.
    $ wp i18n make-mo example-plugin-de_DE.po languages

    # Create a MO file from a single PO file to a specific file destination
    $ wp i18n make-mo example-plugin-de_DE.po languages/bar.mo',
        'name' => 'make-mo',
        'params' => array(
            array(
                'name' => 'source',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'destination',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Create MO files from PO files.',
        'synopsis' => '<source> [<destination>]',
    ),
    'i18n make-php' => array(
        'command' => 'i18n make-php',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

<source>
: Path to an existing PO file or a directory containing multiple PO files.

[<destination>]
: Path to the destination directory for the resulting PHP files. Defaults to the source directory.

## EXAMPLES

    # Create PHP files for all PO files in the current directory.
    $ wp i18n make-php .
    Success: Created 3 files.

    # Create a PHP file from a single PO file in a specific directory.
    $ wp i18n make-php example-plugin-de_DE.po languages
    Success: Created 1 file.',
        'name' => 'make-php',
        'params' => array(
            array(
                'name' => 'source',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'destination',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Create PHP files from PO files.',
        'synopsis' => '<source> [<destination>]',
    ),
    'i18n make-pot' => array(
        'command' => 'i18n make-pot',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Scans PHP, Blade-PHP and JavaScript files for translatable strings, as well as theme stylesheets and plugin files
if the source directory is detected as either a plugin or theme.

## OPTIONS

<source>
: Directory to scan for string extraction.

[<destination>]
: Name of the resulting POT file.

[--slug=<slug>]
: Plugin or theme slug. Defaults to the source directory\'s basename.

[--domain=<domain>]
: Text domain to look for in the source code, unless the `--ignore-domain` option is used.
By default, the "Text Domain" header of the plugin or theme is used.
If none is provided, it falls back to the project slug.

[--ignore-domain]
: Ignore the text domain completely and extract strings with any text domain.

[--merge[=<paths>]]
: Comma-separated list of POT files whose contents should be merged with the extracted strings.
If left empty, defaults to the destination POT file. POT file headers will be ignored.

[--subtract=<paths>]
: Comma-separated list of POT files whose contents should act as some sort of denylist for string extraction.
Any string which is found on that denylist will not be extracted.
This can be useful when you want to create multiple POT files from the same source directory with slightly
different content and no duplicate strings between them.

[--subtract-and-merge]
: Whether source code references and comments from the generated POT file should be instead added to the POT file
used for subtraction. Warning: this modifies the files passed to `--subtract`!

[--include=<paths>]
: Comma-separated list of files and paths that should be used for string extraction.
If provided, only these files and folders will be taken into account for string extraction.
For example, `--include="src,my-file.php` will ignore anything besides `my-file.php` and files in the `src`
directory. Simple glob patterns can be used, i.e. `--include=foo-*.php` includes any PHP file with the `foo-`
prefix. Leading and trailing slashes are ignored, i.e. `/my/directory/` is the same as `my/directory`.

[--exclude=<paths>]
: Comma-separated list of files and paths that should be skipped for string extraction.
For example, `--exclude=.github,myfile.php` would ignore any strings found within `myfile.php` or the `.github`
folder. Simple glob patterns can be used, i.e. `--exclude=foo-*.php` excludes any PHP file with the `foo-`
prefix. Leading and trailing slashes are ignored, i.e. `/my/directory/` is the same as `my/directory`. The
following files and folders are always excluded: node_modules, .git, .svn, .CVS, .hg, vendor, *.min.js.

[--headers=<headers>]
: Array in JSON format of custom headers which will be added to the POT file. Defaults to empty array.

[--location]
: Whether to write `#: filename:line` lines.
Defaults to true, use `--no-location` to skip the removal.
Note that disabling this option makes it harder for technically skilled translators to understand each message’s context.

[--skip-js]
: Skips JavaScript string extraction. Useful when this is done in another build step, e.g. through Babel.

[--skip-php]
: Skips PHP string extraction.

[--skip-blade]
: Skips Blade-PHP string extraction.

[--skip-block-json]
: Skips string extraction from block.json files.

[--skip-theme-json]
: Skips string extraction from theme.json files.

[--skip-audit]
: Skips string audit where it tries to find possible mistakes in translatable strings. Useful when running in an
automated environment.

[--file-comment=<file-comment>]
: String that should be added as a comment to the top of the resulting POT file.
By default, a copyright comment is added for WordPress plugins and themes in the following manner:

     ```
     Copyright (C) 2018 Example Plugin Author
     This file is distributed under the same license as the Example Plugin package.
     ```

     If a plugin or theme specifies a license in their main plugin file or stylesheet, the comment looks like
     this:

     ```
     Copyright (C) 2018 Example Plugin Author
     This file is distributed under the GPLv2.
     ```

[--package-name=<name>]
: Name to use for package name in the resulting POT file\'s `Project-Id-Version` header.
Overrides plugin or theme name, if applicable.

## EXAMPLES

    # Create a POT file for the WordPress plugin/theme in the current directory.
    $ wp i18n make-pot . languages/my-plugin.pot

    # Create a POT file for the continents and cities list in WordPress core.
    $ wp i18n make-pot . continents-and-cities.pot --include="wp-admin/includes/continents-cities.php" --ignore-domain

    # Create a POT file for the WordPress theme in the current directory with custom headers.
    $ wp i18n make-pot . languages/my-theme.pot --headers=\'{"Report-Msgid-Bugs-To":"https://github.com/theme-author/my-theme/","POT-Creation-Date":""}\'',
        'name' => 'make-pot',
        'params' => array(
            array(
                'name' => 'source',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'destination',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Plugin or theme slug. Defaults to the source directory\'s basename.',
                'name' => 'slug',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Text domain to look for in the source code, unless the `--ignore-domain` option is used.',
                'name' => 'domain',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'ignore-domain',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => '',
                'name' => 'merge',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => true,
            ),
            array(
                'desc' => 'Comma-separated list of POT files whose contents should act as some sort of denylist for string extraction.',
                'name' => 'subtract',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'subtract-and-merge',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Comma-separated list of files and paths that should be used for string extraction.',
                'name' => 'include',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Comma-separated list of files and paths that should be skipped for string extraction.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Array in JSON format of custom headers which will be added to the POT file. Defaults to empty array.',
                'name' => 'headers',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'location',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-js',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-php',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-blade',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-block-json',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-theme-json',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-audit',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'String that should be added as a comment to the top of the resulting POT file.',
                'name' => 'file-comment',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Name to use for package name in the resulting POT file\'s `Project-Id-Version` header.',
                'name' => 'package-name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Create a POT file for a WordPress project.',
        'synopsis' => '<source> [<destination>] [--slug=<slug>] [--domain=<domain>] [--ignore-domain] [--merge[=<paths>]] [--subtract=<paths>] [--subtract-and-merge] [--include=<paths>] [--exclude=<paths>] [--headers=<headers>] [--location] [--skip-js] [--skip-php] [--skip-blade] [--skip-block-json] [--skip-theme-json] [--skip-audit] [--file-comment=<file-comment>] [--package-name=<name>]',
    ),
    'i18n update-po' => array(
        'command' => 'i18n update-po',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'This behaves similarly to the [msgmerge](https://www.gnu.org/software/gettext/manual/html_node/msgmerge-Invocation.html) command.

## OPTIONS

<source>
: Path to an existing POT file to use for updating.

[<destination>]
: PO file to update or a directory containing multiple PO files.
  Defaults to all PO files in the source directory.

## EXAMPLES

    # Update all PO files from a POT file in the current directory.
    $ wp i18n update-po example-plugin.pot
    Success: Updated 3 files.

    # Update a PO file from a POT file.
    $ wp i18n update-po example-plugin.pot example-plugin-de_DE.po
    Success: Updated 1 file.

    # Update all PO files in a given directory from a POT file.
    $ wp i18n update-po example-plugin.pot languages
    Success: Updated 2 files.',
        'name' => 'update-po',
        'params' => array(
            array(
                'name' => 'source',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'destination',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
        ),
        'shortdesc' => 'Update PO files from a POT file.',
        'synopsis' => '<source> [<destination>]',
    ),
);
