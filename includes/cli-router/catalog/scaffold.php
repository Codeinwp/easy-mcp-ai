<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'scaffold block' => array(
        'command' => 'scaffold block',
        'fields' => null,
        'longdesc' => '**Warning: `wp scaffold block` is deprecated.**

The official script to generate a block is the [@wordpress/create-block](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-create-block/) package.

See the [Create a Block tutorial](https://developer.wordpress.org/block-editor/getting-started/tutorial/) for a complete walk-through.

## OPTIONS

<slug>
: The internal name of the block.

[--title=<title>]
: The display title for your block.

[--dashicon=<dashicon>]
: The dashicon to make it easier to identify your block.

[--category=<category>]
: The category name to help users browse and discover your block.
---
default: widgets
options:
  - common
  - embed
  - formatting
  - layout
  - widgets
---

[--theme]
: Create files in the active theme directory. Specify a theme with `--theme=<theme>` to have the file placed in that theme.

[--plugin=<plugin>]
: Create files in the given plugin\'s directory.

[--force]
: Overwrite files that already exist.',
        'name' => 'block',
        'params' => array(
            array(
                'name' => 'slug',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'The display title for your block.',
                'name' => 'title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The dashicon to make it easier to identify your block.',
                'name' => 'dashicon',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'widgets',
                'desc' => 'The category name to help users browse and discover your block.',
                'name' => 'category',
                'optional' => true,
                'options' => array(
                    'common',
                    'embed',
                    'formatting',
                    'layout',
                    'widgets',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'theme',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Create files in the given plugin\'s directory.',
                'name' => 'plugin',
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
        ),
        'shortdesc' => 'Generates PHP, JS and CSS code for registering a Gutenberg block for a plugin or theme.',
        'synopsis' => '<slug> [--title=<title>] [--dashicon=<dashicon>] [--category=<category>] [--theme] [--plugin=<plugin>] [--force]',
    ),
    'scaffold child-theme' => array(
        'command' => 'scaffold child-theme',
        'fields' => null,
        'longdesc' => 'Creates a child theme folder with `functions.php` and `style.css` files.

## OPTIONS

<slug>
: The slug for the new child theme.

--parent_theme=<slug>
: What to put in the \'Template:\' header in \'style.css\'.

[--theme_name=<title>]
: What to put in the \'Theme Name:\' header in \'style.css\'.

[--author=<full-name>]
: What to put in the \'Author:\' header in \'style.css\'.

[--author_uri=<uri>]
: What to put in the \'Author URI:\' header in \'style.css\'.

[--theme_uri=<uri>]
: What to put in the \'Theme URI:\' header in \'style.css\'.

[--activate]
: Activate the newly created child theme.

[--enable-network]
: Enable the newly created child theme for the entire network.

[--force]
: Overwrite files that already exist.

## EXAMPLES

    # Generate a \'sample-theme\' child theme based on TwentySixteen
    $ wp scaffold child-theme sample-theme --parent_theme=twentysixteen
    Success: Created \'/var/www/example.com/public_html/wp-content/themes/sample-theme\'.',
        'name' => 'child-theme',
        'params' => array(
            array(
                'name' => 'slug',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'What to put in the \'Template:\' header in \'style.css\'.',
                'name' => 'parent_theme',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Theme Name:\' header in \'style.css\'.',
                'name' => 'theme_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Author:\' header in \'style.css\'.',
                'name' => 'author',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Author URI:\' header in \'style.css\'.',
                'name' => 'author_uri',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Theme URI:\' header in \'style.css\'.',
                'name' => 'theme_uri',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'activate',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'enable-network',
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
        'shortdesc' => 'Generates child theme based on an existing theme.',
        'synopsis' => '<slug> --parent_theme=<slug> [--theme_name=<title>] [--author=<full-name>] [--author_uri=<uri>] [--theme_uri=<uri>] [--activate] [--enable-network] [--force]',
    ),
    'scaffold plugin' => array(
        'command' => 'scaffold plugin',
        'fields' => null,
        'longdesc' => 'The following files are always generated:

* `plugin-slug.php` is the main PHP plugin file.
* `readme.txt` is the readme file for the plugin.
* `package.json` needed by NPM holds various metadata relevant to the project. Packages: `grunt`, `grunt-wp-i18n` and `grunt-wp-readme-to-markdown`. Scripts: `start`, `readme`, `i18n`.
* `Gruntfile.js` is the JS file containing Grunt tasks. Tasks: `i18n` containing `addtextdomain` and `makepot`, `readme` containing `wp_readme_to_markdown`.
* `.editorconfig` is the configuration file for Editor.
* `.gitignore` tells which files (or patterns) git should ignore.
* `.distignore` tells which files and folders should be ignored in distribution.

The following files are also included unless the `--skip-tests` is used:

* `phpunit.xml.dist` is the configuration file for PHPUnit.
* `.circleci/config.yml` is the configuration file for CircleCI. Use `--ci=<provider>` to select a different service.
* `bin/install-wp-tests.sh` configures the WordPress test suite and a test database.
* `tests/bootstrap.php` is the file that makes the current plugin active when running the test suite.
* `tests/test-sample.php` is a sample file containing test cases.
* `.phpcs.xml.dist` is a collection of PHP_CodeSniffer rules.

## OPTIONS

<slug>
: The internal name of the plugin.

[--dir=<dirname>]
: Put the new plugin in some arbitrary directory path. Plugin directory will be path plus supplied slug.

[--plugin_name=<title>]
: What to put in the \'Plugin Name:\' header.

[--plugin_description=<description>]
: What to put in the \'Description:\' header.

[--plugin_author=<author>]
: What to put in the \'Author:\' header.

[--plugin_author_uri=<url>]
: What to put in the \'Author URI:\' header.

[--plugin_uri=<url>]
: What to put in the \'Plugin URI:\' header.

[--skip-tests]
: Don\'t generate files for unit testing.

[--ci=<provider>]
: Choose a configuration file for a continuous integration provider.
---
default: circle
options:
  - circle
  - gitlab
  - bitbucket
  - github
---

[--activate]
: Activate the newly generated plugin.

[--activate-network]
: Network activate the newly generated plugin.

[--force]
: Overwrite files that already exist.

## EXAMPLES

    $ wp scaffold plugin sample-plugin
    Success: Created plugin files.
    Success: Created test files.',
        'name' => 'plugin',
        'params' => array(
            array(
                'name' => 'slug',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Put the new plugin in some arbitrary directory path. Plugin directory will be path plus supplied slug.',
                'name' => 'dir',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Plugin Name:\' header.',
                'name' => 'plugin_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Description:\' header.',
                'name' => 'plugin_description',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Author:\' header.',
                'name' => 'plugin_author',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Author URI:\' header.',
                'name' => 'plugin_author_uri',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Plugin URI:\' header.',
                'name' => 'plugin_uri',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'skip-tests',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'default' => 'circle',
                'desc' => 'Choose a configuration file for a continuous integration provider.',
                'name' => 'ci',
                'optional' => true,
                'options' => array(
                    'circle',
                    'gitlab',
                    'bitbucket',
                    'github',
                ),
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
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
                'name' => 'force',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Generates starter code for a plugin.',
        'synopsis' => '<slug> [--dir=<dirname>] [--plugin_name=<title>] [--plugin_description=<description>] [--plugin_author=<author>] [--plugin_author_uri=<url>] [--plugin_uri=<url>] [--skip-tests] [--ci=<provider>] [--activate] [--activate-network] [--force]',
    ),
    'scaffold plugin-tests' => array(
        'command' => 'scaffold plugin-tests',
        'fields' => null,
        'longdesc' => 'The following files are generated by default:

* `phpunit.xml.dist` is the configuration file for PHPUnit.
* `.circleci/config.yml` is the configuration file for CircleCI. Use `--ci=<provider>` to select a different service.
* `bin/install-wp-tests.sh` configures the WordPress test suite and a test database.
* `tests/bootstrap.php` is the file that makes the current plugin active when running the test suite.
* `tests/test-sample.php` is a sample file containing the actual tests.
* `.phpcs.xml.dist` is a collection of PHP_CodeSniffer rules.

Learn more from the [plugin unit tests documentation](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/).

## ENVIRONMENT

The `tests/bootstrap.php` file looks for the WP_TESTS_DIR environment
variable.

## OPTIONS

[<plugin>]
: The name of the plugin to generate test files for.

[--dir=<dirname>]
: Generate test files for a non-standard plugin path. If no plugin slug is specified, the directory name is used.

[--ci=<provider>]
: Choose a configuration file for a continuous integration provider.
---
default: circle
options:
  - circle
  - gitlab
  - bitbucket
  - github
---

[--force]
: Overwrite files that already exist.

## EXAMPLES

    # Generate unit test files for plugin \'sample-plugin\'.
    $ wp scaffold plugin-tests sample-plugin
    Success: Created test files.',
        'name' => 'plugin-tests',
        'params' => array(
            array(
                'name' => 'plugin',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Generate test files for a non-standard plugin path. If no plugin slug is specified, the directory name is used.',
                'name' => 'dir',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'circle',
                'desc' => 'Choose a configuration file for a continuous integration provider.',
                'name' => 'ci',
                'optional' => true,
                'options' => array(
                    'circle',
                    'gitlab',
                    'bitbucket',
                    'github',
                ),
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
        ),
        'shortdesc' => 'Generates files needed for running PHPUnit tests in a plugin.',
        'synopsis' => '[<plugin>] [--dir=<dirname>] [--ci=<provider>] [--force]',
    ),
    'scaffold post-type' => array(
        'alias' => 'cpt',
        'command' => 'scaffold post-type',
        'fields' => null,
        'longdesc' => '## OPTIONS

<slug>
: The internal name of the post type.

[--label=<label>]
: The text used to translate the update messages.

[--textdomain=<textdomain>]
: The textdomain to use for the labels.

[--dashicon=<dashicon>]
: The dashicon to use in the menu.

[--theme]
: Create a file in the active theme directory, instead of sending to
STDOUT. Specify a theme with `--theme=<theme>` to have the file placed in that theme.

[--plugin=<plugin>]
: Create a file in the given plugin\'s directory, instead of sending to STDOUT.

[--raw]
: Just generate the `register_post_type()` call and nothing else.

[--force]
: Overwrite files that already exist.

## EXAMPLES

    # Generate a \'movie\' post type for the \'simple-life\' theme
    $ wp scaffold post-type movie --label=Movie --theme=simple-life
    Success: Created \'/var/www/example.com/public_html/wp-content/themes/simple-life/post-types/movie.php\'.',
        'name' => 'post-type',
        'params' => array(
            array(
                'name' => 'slug',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'The text used to translate the update messages.',
                'name' => 'label',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The textdomain to use for the labels.',
                'name' => 'textdomain',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The dashicon to use in the menu.',
                'name' => 'dashicon',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'theme',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Create a file in the given plugin\'s directory, instead of sending to STDOUT.',
                'name' => 'plugin',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'raw',
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
        'shortdesc' => 'Generates PHP code for registering a custom post type.',
        'synopsis' => '<slug> [--label=<label>] [--textdomain=<textdomain>] [--dashicon=<dashicon>] [--theme] [--plugin=<plugin>] [--raw] [--force]',
    ),
    'scaffold taxonomy' => array(
        'alias' => 'tax',
        'command' => 'scaffold taxonomy',
        'fields' => null,
        'longdesc' => '## OPTIONS

<slug>
: The internal name of the taxonomy.

[--post_types=<post-types>]
: Post types to register for use with the taxonomy.

[--label=<label>]
: The text used to translate the update messages.

[--textdomain=<textdomain>]
: The textdomain to use for the labels.

[--theme]
: Create a file in the active theme directory, instead of sending to
STDOUT. Specify a theme with `--theme=<theme>` to have the file placed in that theme.

[--plugin=<plugin>]
: Create a file in the given plugin\'s directory, instead of sending to STDOUT.

[--raw]
: Just generate the `register_taxonomy()` call and nothing else.

[--force]
: Overwrite files that already exist.

## EXAMPLES

    # Generate PHP code for registering a custom taxonomy and save in a file
    $ wp scaffold taxonomy venue --post_types=event,presentation > taxonomy.php',
        'name' => 'taxonomy',
        'params' => array(
            array(
                'name' => 'slug',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Post types to register for use with the taxonomy.',
                'name' => 'post_types',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The text used to translate the update messages.',
                'name' => 'label',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The textdomain to use for the labels.',
                'name' => 'textdomain',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'theme',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Create a file in the given plugin\'s directory, instead of sending to STDOUT.',
                'name' => 'plugin',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'raw',
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
        'shortdesc' => 'Generates PHP code for registering a custom taxonomy.',
        'synopsis' => '<slug> [--post_types=<post-types>] [--label=<label>] [--textdomain=<textdomain>] [--theme] [--plugin=<plugin>] [--raw] [--force]',
    ),
    'scaffold theme-tests' => array(
        'command' => 'scaffold theme-tests',
        'fields' => null,
        'longdesc' => 'The following files are generated by default:

* `phpunit.xml.dist` is the configuration file for PHPUnit.
* `.circleci/config.yml` is the configuration file for CircleCI. Use `--ci=<provider>` to select a different service.
* `bin/install-wp-tests.sh` configures the WordPress test suite and a test database.
* `tests/bootstrap.php` is the file that makes the current theme active when running the test suite.
* `tests/test-sample.php` is a sample file containing the actual tests.
* `.phpcs.xml.dist` is a collection of PHP_CodeSniffer rules.

Learn more from the [plugin unit tests documentation](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/).

## ENVIRONMENT

The `tests/bootstrap.php` file looks for the WP_TESTS_DIR environment
variable.

## OPTIONS

[<theme>]
: The name of the theme to generate test files for.

[--dir=<dirname>]
: Generate test files for a non-standard theme path. If no theme slug is specified, the directory name is used.

[--ci=<provider>]
: Choose a configuration file for a continuous integration provider.
---
default: circle
options:
  - circle
  - gitlab
  - bitbucket
  - github
---

[--force]
: Overwrite files that already exist.

## EXAMPLES

    # Generate unit test files for theme \'twentysixteenchild\'.
    $ wp scaffold theme-tests twentysixteenchild
    Success: Created test files.',
        'name' => 'theme-tests',
        'params' => array(
            array(
                'name' => 'theme',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Generate test files for a non-standard theme path. If no theme slug is specified, the directory name is used.',
                'name' => 'dir',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'circle',
                'desc' => 'Choose a configuration file for a continuous integration provider.',
                'name' => 'ci',
                'optional' => true,
                'options' => array(
                    'circle',
                    'gitlab',
                    'bitbucket',
                    'github',
                ),
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
        ),
        'shortdesc' => 'Generates files needed for running PHPUnit tests in a theme.',
        'synopsis' => '[<theme>] [--dir=<dirname>] [--ci=<provider>] [--force]',
    ),
    'scaffold underscores' => array(
        'alias' => '_s',
        'command' => 'scaffold underscores',
        'fields' => null,
        'longdesc' => 'See the [Underscores website](https://underscores.me/) for more details.

## OPTIONS

<slug>
: The slug for the new theme, used for prefixing functions.

[--activate]
: Activate the newly downloaded theme.

[--enable-network]
: Enable the newly downloaded theme for the entire network.

[--theme_name=<title>]
: What to put in the \'Theme Name:\' header in \'style.css\'.

[--author=<full-name>]
: What to put in the \'Author:\' header in \'style.css\'.

[--author_uri=<uri>]
: What to put in the \'Author URI:\' header in \'style.css\'.

[--sassify]
: Include stylesheets as SASS.

[--woocommerce]
: Include WooCommerce boilerplate files.

[--force]
: Overwrite files that already exist.

## EXAMPLES

    # Generate a theme with name "Sample Theme" and author "John Doe"
    $ wp scaffold _s sample-theme --theme_name="Sample Theme" --author="John Doe"
    Success: Created theme \'Sample Theme\'.',
        'name' => 'underscores',
        'params' => array(
            array(
                'name' => 'slug',
                'optional' => false,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'activate',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'enable-network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'What to put in the \'Theme Name:\' header in \'style.css\'.',
                'name' => 'theme_name',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Author:\' header in \'style.css\'.',
                'name' => 'author',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'What to put in the \'Author URI:\' header in \'style.css\'.',
                'name' => 'author_uri',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'sassify',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'woocommerce',
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
        'shortdesc' => 'Generates starter code for a theme based on _s.',
        'synopsis' => '<slug> [--activate] [--enable-network] [--theme_name=<title>] [--author=<full-name>] [--author_uri=<uri>] [--sassify] [--woocommerce] [--force]',
    ),
);
