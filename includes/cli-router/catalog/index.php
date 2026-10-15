<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'aliases' => array(
        'comment meta set' => 'comment meta update',
        'core install-network' => 'core multisite-convert',
        'core upgrade' => 'core update',
        'db connect' => 'db cli',
        'db dump' => 'db export',
        'network meta set' => 'network meta update',
        'option set' => 'option update',
        'plugin upgrade' => 'plugin update',
        'post meta set' => 'post meta update',
        'scaffold _s' => 'scaffold underscores',
        'scaffold cpt' => 'scaffold post-type',
        'scaffold tax' => 'scaffold taxonomy',
        'site meta set' => 'site meta update',
        'site option set' => 'site option update',
        'term meta set' => 'term meta update',
        'theme uninstall' => 'theme delete',
        'theme upgrade' => 'theme update',
        'user meta set' => 'user meta update',
    ),
    'composites' => array(
        'cache' => array(
            'command' => 'cache',
            'global_prefix' => '',
            'longdesc' => 'By default, the WP Object Cache exists in PHP memory for the length of the
request (and is emptied at the end). Use a persistent object cache drop-in
to persist the object cache between requests.

[Read the codex article](https://codex.wordpress.org/Class_Reference/WP_Object_Cache)
for more detail.

## EXAMPLES

    # Set cache.
    $ wp cache set my_key my_value my_group 300
    Success: Set object \'my_key\' in group \'my_group\'.

    # Get cache.
    $ wp cache get my_key my_group
    my_value',
            'shortdesc' => 'Adds, removes, fetches, and flushes the WP Object Cache object.',
            'subcommands' => array(
                'add',
                'decr',
                'delete',
                'flush',
                'flush-group',
                'get',
                'incr',
                'patch',
                'pluck',
                'replace',
                'set',
                'supports',
                'type',
            ),
        ),
        'cap' => array(
            'command' => 'cap',
            'global_prefix' => '',
            'longdesc' => 'See references for [Roles and Capabilities](https://codex.wordpress.org/Roles_and_Capabilities) and [WP User class](https://codex.wordpress.org/Class_Reference/WP_User).

## EXAMPLES

    # Add \'spectate\' capability to \'author\' role.
    $ wp cap add \'author\' \'spectate\'
    Success: Added 1 capability to \'author\' role.

    # Add all caps from \'editor\' role to \'author\' role.
    $ wp cap list \'editor\' | xargs wp cap add \'author\'
    Success: Added 24 capabilities to \'author\' role.

    # Remove all caps from \'editor\' role that also appear in \'author\' role.
    $ wp cap list \'author\' | xargs wp cap remove \'editor\'
    Success: Removed 34 capabilities from \'editor\' role.',
            'shortdesc' => 'Adds, removes, and lists capabilities of a user role.',
            'subcommands' => array(
                'add',
                'list',
                'remove',
            ),
        ),
        'cli' => array(
            'command' => 'cli',
            'global_prefix' => '',
            'hook' => 'before_wp_load',
            'longdesc' => '## EXAMPLES

    # Display the version currently installed.
    $ wp cli version
    WP-CLI 0.24.1

    # Check for updates to WP-CLI.
    $ wp cli check-update
    Success: WP-CLI is at the latest version.

    # Update WP-CLI to the latest stable release.
    $ wp cli update
    You have version 0.24.0. Would you like to update to 0.24.1? [y/n] y
    Downloading from https://github.com/wp-cli/wp-cli/releases/download/v0.24.1/wp-cli-0.24.1.phar...
    New version works. Proceeding to replace.
    Success: Updated WP-CLI to 0.24.1.

    # Clear the internal WP-CLI cache.
    $ wp cli cache clear
    Success: Cache cleared.',
            'shortdesc' => 'Reviews current WP-CLI info, checks for updates, or views defined aliases.',
            'subcommands' => array(
                'alias',
                'cache',
                'check-update',
                'cmd-dump',
                'completions',
                'has-command',
                'info',
                'param-dump',
                'update',
                'version',
            ),
        ),
        'cli alias' => array(
            'command' => 'cli alias',
            'global_prefix' => '

',
            'hook' => 'before_wp_load',
            'longdesc' => 'Aliases are shorthand references to WordPress installs. For instance,
`@dev` could refer to a development install and `@prod` could refer to a production install.
This command gives you and option to add, update and delete, the registered aliases you have available.

## EXAMPLES

    # List alias information.
    $ wp cli alias list
    list
    ---
    @all: Run command against every registered alias.
    @local:
      user: wpcli
      path: /Users/wpcli/sites/testsite

    # Get alias information.
    $ wp cli alias get @dev
    ssh: dev@somedeve.env:12345/home/dev/

    # Add alias.
    $ wp cli alias add @prod --set-ssh=login@host --set-path=/path/to/wordpress/install/ --set-user=wpcli
    Success: Added \'@prod\' alias.

    # Update alias.
    $ wp cli alias update @prod --set-user=newuser --set-path=/new/path/to/wordpress/install/
    Success: Updated \'prod\' alias.

    # Delete alias.
    $ wp cli alias delete @prod
    Success: Deleted \'@prod\' alias.',
            'shortdesc' => 'Retrieves, sets and updates aliases for WordPress Installations.',
            'subcommands' => array(
                'add',
                'delete',
                'get',
                'is-group',
                'list',
                'update',
            ),
        ),
        'cli cache' => array(
            'command' => 'cli cache',
            'global_prefix' => '

',
            'hook' => 'before_wp_load',
            'longdesc' => '## EXAMPLES

    # Remove all cached files.
    $ wp cli cache clear
    Success: Cache cleared.

    # Remove all cached files except for the newest version of each one.
    $ wp cli cache prune
    Success: Cache pruned.',
            'shortdesc' => 'Manages the internal WP-CLI cache,.',
            'subcommands' => array(
                'clear',
                'prune',
            ),
        ),
        'comment' => array(
            'command' => 'comment',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Create a new comment.
    $ wp comment create --comment_post_ID=15 --comment_content="hello blog" --comment_author="wp-cli"
    Success: Created comment 932.

    # Update an existing comment.
    $ wp comment update 123 --comment_author=\'That Guy\'
    Success: Updated comment 123.

    # Delete an existing comment.
    $ wp comment delete 1337 --force
    Success: Deleted comment 1337.

    # Trash all spam comments.
    $ wp comment delete $(wp comment list --status=spam --format=ids)
    Success: Trashed comment 264.
    Success: Trashed comment 262.',
            'shortdesc' => 'Creates, updates, deletes, and moderates comments.',
            'subcommands' => array(
                'approve',
                'count',
                'create',
                'delete',
                'exists',
                'generate',
                'get',
                'list',
                'meta',
                'recount',
                'spam',
                'status',
                'trash',
                'unapprove',
                'unspam',
                'untrash',
                'update',
            ),
        ),
        'comment meta' => array(
            'command' => 'comment meta',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Set comment meta
    $ wp comment meta set 123 description "Mary is a WordPress developer."
    Success: Updated custom field \'description\'.

    # Get comment meta
    $ wp comment meta get 123 description
    Mary is a WordPress developer.

    # Update comment meta
    $ wp comment meta update 123 description "Mary is an awesome WordPress developer."
    Success: Updated custom field \'description\'.

    # Delete comment meta
    $ wp comment meta delete 123 description
    Success: Deleted custom field.',
            'shortdesc' => 'Adds, updates, deletes, and lists comment custom fields.',
            'subcommands' => array(
                'add',
                'delete',
                'get',
                'list',
                'patch',
                'pluck',
                'update',
            ),
        ),
        'config' => array(
            'command' => 'config',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Create standard wp-config.php file.
    $ wp config create --dbname=testing --dbuser=wp --dbpass=securepswd --locale=ro_RO
    Success: Generated \'wp-config.php\' file.

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

    # Get wp-config.php file path.
    $ wp config path
    /home/person/htdocs/project/wp-config.php

    # Get the table_prefix as defined in wp-config.php file.
    $ wp config get table_prefix
    wp_

    # Set the WP_DEBUG constant to true.
    $ wp config set WP_DEBUG true --raw
    Success: Updated the constant \'WP_DEBUG\' in the \'wp-config.php\' file with the raw value \'true\'.

    # Delete the COOKIE_DOMAIN constant from the wp-config.php file.
    $ wp config delete COOKIE_DOMAIN
    Success: Deleted the constant \'COOKIE_DOMAIN\' from the \'wp-config.php\' file.

    # Launch system editor to edit wp-config.php file.
    $ wp config edit

    # Check whether the DB_PASSWORD constant exists in the wp-config.php file.
    $ wp config has DB_PASSWORD
    $ echo $?
    0

    # Assert if MULTISITE is true.
    $ wp config is-true MULTISITE
    $ echo $?
    0

    # Get new salts for your wp-config.php file.
    $ wp config shuffle-salts
    Success: Shuffled the salt keys.',
            'shortdesc' => 'Generates and reads the wp-config.php file.',
            'subcommands' => array(
                'create',
                'delete',
                'edit',
                'get',
                'has',
                'is-true',
                'list',
                'path',
                'set',
                'shuffle-salts',
            ),
        ),
        'core' => array(
            'command' => 'core',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Download WordPress core
    $ wp core download --locale=nl_NL
    Downloading WordPress 4.5.2 (nl_NL)...
    md5 hash verified: c5366d05b521831dd0b29dfc386e56a5
    Success: WordPress downloaded.

    # Install WordPress
    $ wp core install --url=example.com --title=Example --admin_user=supervisor --admin_password=strongpassword --admin_email=info@example.com
    Success: WordPress installed successfully.

    # Display the WordPress version
    $ wp core version
    4.5.2',
            'shortdesc' => 'Downloads, installs, updates, and manages a WordPress installation.',
            'subcommands' => array(
                'check-update',
                'download',
                'install',
                'is-installed',
                'multisite-convert',
                'multisite-install',
                'update',
                'update-db',
                'verify-checksums',
                'version',
            ),
        ),
        'cron' => array(
            'command' => 'cron',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Test WP Cron spawning system
    $ wp cron test
    Success: WP-Cron spawning is working as expected.',
            'shortdesc' => 'Tests, runs, and deletes WP-Cron events; manages WP-Cron schedules.',
            'subcommands' => array(
                'event',
                'schedule',
                'test',
            ),
        ),
        'cron event' => array(
            'command' => 'cron event',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Schedule a new cron event
    $ wp cron event schedule cron_test
    Success: Scheduled event with hook \'cron_test\' for 2016-05-31 10:19:16 GMT.

    # Run all cron events due right now
    $ wp cron event run --due-now
    Executed the cron event \'cron_test_1\' in 0.01s.
    Executed the cron event \'cron_test_2\' in 0.006s.
    Success: Executed a total of 2 cron events.

    # Delete all scheduled cron events for the given hook
    $ wp cron event delete cron_test
    Success: Deleted a total of 2 cron events.

    # List scheduled cron events in JSON
    $ wp cron event list --fields=hook,next_run --format=json
    [{"hook":"wp_version_check","next_run":"2016-05-31 10:15:13"},{"hook":"wp_update_plugins","next_run":"2016-05-31 10:15:13"},{"hook":"wp_update_themes","next_run":"2016-05-31 10:15:14"}]',
            'shortdesc' => 'Schedules, runs, and deletes WP-Cron events.',
            'subcommands' => array(
                'delete',
                'list',
                'run',
                'schedule',
                'unschedule',
            ),
        ),
        'cron schedule' => array(
            'command' => 'cron schedule',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # List available cron schedules
    $ wp cron schedule list
    +------------+-------------+----------+
    | name       | display     | interval |
    +------------+-------------+----------+
    | hourly     | Once Hourly | 3600     |
    | twicedaily | Twice Daily | 43200    |
    | daily      | Once Daily  | 86400    |
    +------------+-------------+----------+',
            'shortdesc' => 'Gets WP-Cron schedules.',
            'subcommands' => array(
                'list',
            ),
        ),
        'db' => array(
            'command' => 'db',
            'global_prefix' => '',
            'hook' => 'after_wp_config_load',
            'longdesc' => '## EXAMPLES

    # Create a new database.
    $ wp db create
    Success: Database created.

    # Drop an existing database.
    $ wp db drop --yes
    Success: Database dropped.

    # Reset the current database.
    $ wp db reset --yes
    Success: Database reset.

    # Execute a SQL query stored in a file.
    $ wp db query < debug.sql',
            'shortdesc' => 'Performs basic database operations using credentials stored in wp-config.php.',
            'subcommands' => array(
                'check',
                'clean',
                'cli',
                'columns',
                'create',
                'drop',
                'export',
                'import',
                'optimize',
                'prefix',
                'query',
                'repair',
                'reset',
                'search',
                'size',
                'tables',
            ),
        ),
        'embed' => array(
            'command' => 'embed',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Get embed HTML for a given URL.
    $ wp embed fetch https://www.youtube.com/watch?v=dQw4w9WgXcQ
    <iframe width="525" height="295" src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed" ...

    # Find cache post ID for a given URL.
    $ wp embed cache find https://www.youtube.com/watch?v=dQw4w9WgXcQ --width=500
    123

    # List format,endpoint fields of available providers.
    $ wp embed provider list
    +------------------------------+-----------------------------------------+
    | format                       | endpoint                                |
    +------------------------------+-----------------------------------------+
    | #https?://youtu\\.be/.*#i     | https://www.youtube.com/oembed          |
    | #https?://flic\\.kr/.*#i      | https://www.flickr.com/services/oembed/ |
    | #https?://wordpress\\.tv/.*#i | https://wordpress.tv/oembed/            |

    # List id,regex,priority fields of available handlers.
    $ wp embed handler list --fields=priority,id
    +----------+-------------------+
    | priority | id                |
    +----------+-------------------+
    | 10       | youtube_embed_url |
    | 9999     | audio             |
    | 9999     | video             |
    +----------+-------------------+',
            'shortdesc' => 'Inspects oEmbed providers, clears embed cache, and more.',
            'subcommands' => array(
                'cache',
                'fetch',
                'handler',
                'provider',
            ),
        ),
        'embed cache' => array(
            'command' => 'embed cache',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Find cache post ID for a given URL.
    $ wp embed cache find https://www.youtube.com/watch?v=dQw4w9WgXcQ --width=500
    123

    # Clear cache for a post.
    $ wp embed cache clear 123
    Success: Cleared oEmbed cache.

    # Triggers cache for a post.
    $ wp embed cache trigger 456
    Success: Caching triggered!',
            'shortdesc' => 'Finds, triggers, and deletes oEmbed caches.',
            'subcommands' => array(
                'clear',
                'find',
                'trigger',
            ),
        ),
        'embed handler' => array(
            'command' => 'embed handler',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # List id,regex,priority fields of available handlers.
    $ wp embed handler list --fields=priority,id
    +----------+-------------------+
    | priority | id                |
    +----------+-------------------+
    | 10       | youtube_embed_url |
    | 9999     | audio             |
    | 9999     | video             |',
            'shortdesc' => 'Retrieves embed handlers.',
            'subcommands' => array(
                'list',
            ),
        ),
        'embed provider' => array(
            'command' => 'embed provider',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # List format,endpoint fields of available providers.
    $ wp embed provider list
    +------------------------------+-----------------------------------------+
    | format                       | endpoint                                |
    +------------------------------+-----------------------------------------+
    | #https?://youtu\\.be/.*#i     | https://www.youtube.com/oembed          |
    | #https?://flic\\.kr/.*#i      | https://www.flickr.com/services/oembed/ |
    | #https?://wordpress\\.tv/.*#i | https://wordpress.tv/oembed/            |

    # Get the matching provider for the URL.
    $ wp embed provider match https://www.youtube.com/watch?v=dQw4w9WgXcQ
    https://www.youtube.com/oembed',
            'shortdesc' => 'Retrieves oEmbed providers.',
            'subcommands' => array(
                'list',
                'match',
            ),
        ),
        'i18n' => array(
            'command' => 'i18n',
            'global_prefix' => '',
            'hook' => 'before_wp_load',
            'longdesc' => '## EXAMPLES

    # Create a POT file for the WordPress plugin/theme in the current directory
    $ wp i18n make-pot . languages/my-plugin.pot',
            'shortdesc' => 'Provides internationalization tools for WordPress projects.',
            'subcommands' => array(
                'make-json',
                'make-mo',
                'make-php',
                'make-pot',
                'update-po',
            ),
        ),
        'language' => array(
            'command' => 'language',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Install the Dutch core language pack.
    $ wp language core install nl_NL
    Downloading translation from https://downloads.wordpress.org/translation/core/6.4.3/nl_NL.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the translation...
    Translation updated successfully.
    Language \'nl_NL\' installed.
    Success: Installed 1 of 1 languages.

    # Activate the Dutch core language pack.
    $ wp site switch-language nl_NL
    Success: Language activated.

    # Install the Dutch theme language pack for Twenty Ten.
    $ wp language theme install twentyten nl_NL
    Downloading translation from https://downloads.wordpress.org/translation/theme/twentyten/4.0/nl_NL.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the translation...
    Translation updated successfully.
    Language \'nl_NL\' installed.
    Success: Installed 1 of 1 languages.

    # Install the Dutch plugin language pack for Hello Dolly.
    $ wp language plugin install hello-dolly nl_NL
    Downloading translation from https://downloads.wordpress.org/translation/plugin/hello-dolly/1.7.2/nl_NL.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the translation...
    Translation updated successfully.
    Language \'nl_NL\' installed.
    Success: Installed 1 of 1 languages.',
            'shortdesc' => 'Installs, activates, and manages language packs.',
            'subcommands' => array(
                'core',
                'plugin',
                'theme',
            ),
        ),
        'language core' => array(
            'command' => 'language core',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Install the Dutch core language pack.
    $ wp language core install nl_NL
    Downloading translation from https://downloads.wordpress.org/translation/core/6.4.3/nl_NL.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the translation...
    Translation updated successfully.
    Language \'nl_NL\' installed.
    Success: Installed 1 of 1 languages.

    # Activate the Dutch core language pack.
    $ wp site switch-language nl_NL
    Success: Language activated.

    # Uninstall the Dutch core language pack.
    $ wp language core uninstall nl_NL
    Success: Language uninstalled.

    # List installed core language packs.
    $ wp language core list --status=installed
    +----------+--------------+-------------+-----------+-----------+---------------------+
    | language | english_name | native_name | status    | update    | updated             |
    +----------+--------------+-------------+-----------+-----------+---------------------+
    | nl_NL    | Dutch        | Nederlands  | installed | available | 2024-01-31 10:24:06 |
    +----------+--------------+-------------+-----------+-----------+---------------------+',
            'shortdesc' => 'Installs, activates, and manages core language packs.',
            'subcommands' => array(
                'activate',
                'install',
                'is-installed',
                'list',
                'uninstall',
                'update',
            ),
        ),
        'language plugin' => array(
            'command' => 'language plugin',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Install the Dutch plugin language pack for Hello Dolly.
    $ wp language plugin install hello-dolly nl_NL
    Downloading translation from https://downloads.wordpress.org/translation/plugin/hello-dolly/1.7.2/nl_NL.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the translation...
    Translation updated successfully.
    Language \'nl_NL\' installed.
    Success: Installed 1 of 1 languages.

    # Uninstall the Dutch plugin language pack for Hello Dolly.
    $ wp language plugin uninstall hello-dolly nl_NL
    Language \'nl_NL\' for \'hello-dolly\' uninstalled.
    +-------------+--------+-------------+
    | name        | locale | status      |
    +-------------+--------+-------------+
    | hello-dolly | nl_NL  | uninstalled |
    +-------------+--------+-------------+
    Success: Uninstalled 1 of 1 languages.

    # List installed plugin language packs for Hello Dolly.
    $ wp language plugin list hello-dolly --status=installed
    +-------------+----------+--------------+-------------+-----------+--------+---------------------+
    | plugin      | language | english_name | native_name | status    | update | updated             |
    +-------------+----------+--------------+-------------+-----------+--------+---------------------+
    | hello-dolly | nl_NL    | Dutch        | Nederlands  | installed | none   | 2023-11-13 12:34:15 |
    +-------------+----------+--------------+-------------+-----------+--------+---------------------+',
            'shortdesc' => 'Installs, activates, and manages plugin language packs.',
            'subcommands' => array(
                'install',
                'is-installed',
                'list',
                'uninstall',
                'update',
            ),
        ),
        'language theme' => array(
            'command' => 'language theme',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Install the Dutch theme language pack for Twenty Ten.
    $ wp language theme install twentyten nl_NL
    Downloading translation from https://downloads.wordpress.org/translation/theme/twentyten/4.0/nl_NL.zip...
    Unpacking the update...
    Installing the latest version...
    Removing the old version of the translation...
    Translation updated successfully.
    Language \'nl_NL\' installed.
    Success: Installed 1 of 1 languages.

    # Uninstall the Dutch theme language pack for Twenty Ten.
    $ wp language theme uninstall twentyten nl_NL
    Language \'nl_NL\' for \'twentyten\' uninstalled.
    +-----------+--------+-------------+
    | name      | locale | status      |
    +-----------+--------+-------------+
    | twentyten | nl_NL  | uninstalled |
    +-----------+--------+-------------+
    Success: Uninstalled 1 of 1 languages.

    # List installed theme language packs for Twenty Ten.
    $ wp language theme list twentyten --status=installed
    +-----------+----------+--------------+-------------+-----------+--------+---------------------+
    | theme     | language | english_name | native_name | status    | update | updated             |
    +-----------+----------+--------------+-------------+-----------+--------+---------------------+
    | twentyten | nl_NL    | Dutch        | Nederlands  | installed | none   | 2023-12-29 21:21:39 |
    +-----------+----------+--------------+-------------+-----------+--------+---------------------+',
            'shortdesc' => 'Installs, activates, and manages theme language packs.',
            'subcommands' => array(
                'install',
                'is-installed',
                'list',
                'uninstall',
                'update',
            ),
        ),
        'maintenance-mode' => array(
            'command' => 'maintenance-mode',
            'global_prefix' => '',
            'hook' => 'after_wp_load',
            'longdesc' => '## EXAMPLES

    # Activate Maintenance mode.
    $ wp maintenance-mode activate
    Enabling Maintenance mode...
    Success: Activated Maintenance mode.

    # Deactivate Maintenance mode.
    $ wp maintenance-mode deactivate
    Disabling Maintenance mode...
    Success: Deactivated Maintenance mode.

    # Display Maintenance mode status.
    $ wp maintenance-mode status
    Maintenance mode is active.

    # Get Maintenance mode status for scripting purpose.
    $ wp maintenance-mode is-active
    $ echo $?
    1',
            'shortdesc' => 'Activates, deactivates or checks the status of the maintenance mode of a site.',
            'subcommands' => array(
                'activate',
                'deactivate',
                'is-active',
                'status',
            ),
        ),
        'media' => array(
            'command' => 'media',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Re-generate all thumbnails, without confirmation.
    $ wp media regenerate --yes
    Found 3 images to regenerate.
    1/3 Regenerated thumbnails for "Sydney Harbor Bridge" (ID 760).
    2/3 Regenerated thumbnails for "Boardwalk" (ID 757).
    3/3 Regenerated thumbnails for "Sunburst Over River" (ID 756).
    Success: Regenerated 3 of 3 images.

    # Import a local image and set it to be the featured image for a post.
    $ wp media import ~/Downloads/image.png --post_id=123 --title="A downloaded picture" --featured_image
    Imported file \'/home/person/Downloads/image.png\' as attachment ID 1753 and attached to post 123 as featured image.
    Success: Imported 1 of 1 images.

    # List all registered image sizes
    $ wp media image-size
    +---------------------------+-------+--------+-------+
    | name                      | width | height | crop  |
    +---------------------------+-------+--------+-------+
    | full                      |       |        | N/A   |
    | twentyfourteen-full-width | 1038  | 576    | hard  |
    | large                     | 1024  | 1024   | soft  |
    | medium_large              | 768   | 0      | soft  |
    | medium                    | 300   | 300    | soft  |
    | thumbnail                 | 150   | 150    | hard  |
    +---------------------------+-------+--------+-------+

    # Fix orientation for specific images.
    $ wp media fix-orientation 63
    1/1 Fixing orientation for "Portrait_6" (ID 63).
    Success: Fixed 1 of 1 images.',
            'shortdesc' => 'Imports files as attachments, regenerates thumbnails, or lists registered image sizes.',
            'subcommands' => array(
                'fix-orientation',
                'image-size',
                'import',
                'regenerate',
            ),
        ),
        'menu' => array(
            'command' => 'menu',
            'global_prefix' => '',
            'longdesc' => 'See the [Navigation Menus](https://developer.wordpress.org/themes/functionality/navigation-menus/) reference in the Theme Handbook.

## EXAMPLES

    # Create a new menu
    $ wp menu create "My Menu"
    Success: Created menu 200.

    # List existing menus
    $ wp menu list
    +---------+----------+----------+-----------+-------+
    | term_id | name     | slug     | locations | count |
    +---------+----------+----------+-----------+-------+
    | 200     | My Menu  | my-menu  |           | 0     |
    | 177     | Top Menu | top-menu | primary   | 7     |
    +---------+----------+----------+-----------+-------+

    # Create a new menu link item
    $ wp menu item add-custom my-menu Apple http://apple.com --porcelain
    1922

    # Assign the \'my-menu\' menu to the \'primary\' location
    $ wp menu location assign my-menu primary
    Success: Assigned location primary to menu my-menu.',
            'shortdesc' => 'Lists, creates, assigns, and deletes the active theme\'s navigation menus.',
            'subcommands' => array(
                'create',
                'delete',
                'item',
                'list',
                'location',
            ),
        ),
        'menu item' => array(
            'command' => 'menu item',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Add an existing post to an existing menu
    $ wp menu item add-post sidebar-menu 33 --title="Custom Test Post"
    Success: Menu item added.

    # Create a new menu link item
    $ wp menu item add-custom sidebar-menu Apple http://apple.com
    Success: Menu item added.

    # Delete menu item
    $ wp menu item delete 45
    Success: Deleted 1 of 1 menu items.',
            'shortdesc' => 'List, add, and delete items associated with a menu.',
            'subcommands' => array(
                'add-custom',
                'add-post',
                'add-term',
                'delete',
                'list',
                'update',
            ),
        ),
        'menu location' => array(
            'command' => 'menu location',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # List available menu locations
    $ wp menu location list
    +----------+-------------------+
    | location | description       |
    +----------+-------------------+
    | primary  | Primary Menu      |
    | social   | Social Links Menu |
    +----------+-------------------+

    # Assign the \'primary-menu\' menu to the \'primary\' location
    $ wp menu location assign primary-menu primary
    Success: Assigned location primary to menu primary-menu.

    # Remove the \'primary-menu\' menu from the \'primary\' location
    $ wp menu location remove primary-menu primary
    Success: Removed location from menu.',
            'shortdesc' => 'Assigns, removes, and lists a menu\'s locations.',
            'subcommands' => array(
                'assign',
                'list',
                'remove',
            ),
        ),
        'network' => array(
            'command' => 'network',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Get a list of super-admins
    $ wp network meta get 1 site_admins
    array (
      0 => \'supervisor\',
    )',
            'shortdesc' => 'Perform network-wide operations.',
            'subcommands' => array(
                'meta',
            ),
        ),
        'network meta' => array(
            'command' => 'network meta',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Get a list of super-admins
    $ wp network meta get 1 site_admins
    array (
      0 => \'supervisor\',
    )',
            'shortdesc' => 'Gets, adds, updates, deletes, and lists network custom fields.',
            'subcommands' => array(
                'add',
                'delete',
                'get',
                'list',
                'patch',
                'pluck',
                'update',
            ),
        ),
        'option' => array(
            'command' => 'option',
            'global_prefix' => '',
            'longdesc' => 'See the [Plugin Settings API](https://developer.wordpress.org/plugins/settings/settings-api/) and the [Theme Options](https://developer.wordpress.org/themes/customize-api/) for more information on adding customized options.

## EXAMPLES

    # Get site URL.
    $ wp option get siteurl
    http://example.com

    # Add option.
    $ wp option add my_option foobar
    Success: Added \'my_option\' option.

    # Update option.
    $ wp option update my_option \'{"foo": "bar"}\' --format=json
    Success: Updated \'my_option\' option.

    # Delete option.
    $ wp option delete my_option
    Success: Deleted \'my_option\' option.',
            'shortdesc' => 'Retrieves and sets site options, including plugin and WordPress settings.',
            'subcommands' => array(
                'add',
                'delete',
                'get',
                'get-autoload',
                'list',
                'patch',
                'pluck',
                'set-autoload',
                'update',
            ),
        ),
        'package' => array(
            'command' => 'package',
            'global_prefix' => '',
            'hook' => 'before_wp_load',
            'longdesc' => 'WP-CLI packages are community-maintained projects built on WP-CLI. They can
contain WP-CLI commands, but they can also just extend WP-CLI in some way.

Learn how to create your own command from the
[Commands Cookbook](https://make.wordpress.org/cli/handbook/guides/commands-cookbook/)

## EXAMPLES

    # List installed packages.
    $ wp package list
    +-----------------------+------------------+----------+-----------+----------------+
    | name                  | authors          | version  | update    | update_version |
    +-----------------------+------------------+----------+-----------+----------------+
    | wp-cli/server-command | Daniel Bachhuber | dev-main | available | 2.x-dev        |
    +-----------------------+------------------+----------+-----------+----------------+

    # Install the latest development version of the package.
    $ wp package install wp-cli/server-command
    Installing package wp-cli/server-command (dev-main)
    Updating /home/person/.wp-cli/packages/composer.json to require the package...
    Using Composer to install the package...
    ---
    Loading composer repositories with package information
    Updating dependencies
    Resolving dependencies through SAT
    Dependency resolution completed in 0.005 seconds
    Analyzed 732 packages to resolve dependencies
    Analyzed 1034 rules to resolve dependencies
     - Installing package
    Writing lock file
    Generating autoload files
    ---
    Success: Package installed.

    # Uninstall package.
    $ wp package uninstall wp-cli/server-command
    Removing require statement for package \'wp-cli/server-command\' from /home/person/.wp-cli/packages/composer.json
    Removing repository details from /home/person/.wp-cli/packages/composer.json
    Removing package directories and regenerating autoloader...
    Success: Uninstalled package.',
            'shortdesc' => 'Lists, installs, and removes WP-CLI packages.',
            'subcommands' => array(
                'browse',
                'install',
                'list',
                'path',
                'uninstall',
                'update',
            ),
        ),
        'plugin' => array(
            'command' => 'plugin',
            'global_prefix' => '',
            'longdesc' => 'See the WordPress [Plugin Handbook](https://developer.wordpress.org/plugins/) developer resource for more information on plugins.

## EXAMPLES

    # Activate plugin
    $ wp plugin activate hello
    Plugin \'hello\' activated.
    Success: Activated 1 of 1 plugins.

    # Deactivate plugin
    $ wp plugin deactivate hello
    Plugin \'hello\' deactivated.
    Success: Deactivated 1 of 1 plugins.

    # Delete plugin
    $ wp plugin delete hello
    Deleted \'hello\' plugin.
    Success: Deleted 1 of 1 plugins.

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
    Success: Installed 1 of 1 plugins.',
            'shortdesc' => 'Manages plugins, including installs, activations, and updates.',
            'subcommands' => array(
                'activate',
                'auto-updates',
                'deactivate',
                'delete',
                'get',
                'install',
                'is-active',
                'is-installed',
                'list',
                'path',
                'search',
                'status',
                'toggle',
                'uninstall',
                'update',
                'verify-checksums',
            ),
        ),
        'plugin auto-updates' => array(
            'command' => 'plugin auto-updates',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Enable the auto-updates for a plugin
    $ wp plugin auto-updates enable hello
    Plugin auto-updates for \'hello\' enabled.
    Success: Enabled 1 of 1 plugin auto-updates.

    # Disable the auto-updates for a plugin
    $ wp plugin auto-updates disable hello
    Plugin auto-updates for \'hello\' disabled.
    Success: Disabled 1 of 1 plugin auto-updates.

    # Get the status of plugin auto-updates
    $ wp plugin auto-updates status hello
    Auto-updates for plugin \'hello\' are disabled.',
            'shortdesc' => 'Manages plugin auto-updates.',
            'subcommands' => array(
                'disable',
                'enable',
                'status',
            ),
        ),
        'post' => array(
            'command' => 'post',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Create a new post.
    $ wp post create --post_type=post --post_title=\'A sample post\'
    Success: Created post 123.

    # Update an existing post.
    $ wp post update 123 --post_status=draft
    Success: Updated post 123.

    # Delete an existing post.
    $ wp post delete 123
    Success: Trashed post 123.',
            'shortdesc' => 'Manages posts, content, and meta.',
            'subcommands' => array(
                'create',
                'delete',
                'edit',
                'exists',
                'generate',
                'get',
                'list',
                'meta',
                'term',
                'update',
                'url-to-id',
            ),
        ),
        'post meta' => array(
            'command' => 'post meta',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Set post meta
    $ wp post meta set 123 _wp_page_template about.php
    Success: Updated custom field \'_wp_page_template\'.

    # Get post meta
    $ wp post meta get 123 _wp_page_template
    about.php

    # Update post meta
    $ wp post meta update 123 _wp_page_template contact.php
    Success: Updated custom field \'_wp_page_template\'.

    # Delete post meta
    $ wp post meta delete 123 _wp_page_template
    Success: Deleted custom field.',
            'shortdesc' => 'Adds, updates, deletes, and lists post custom fields.',
            'subcommands' => array(
                'add',
                'clean-duplicates',
                'delete',
                'get',
                'list',
                'patch',
                'pluck',
                'update',
            ),
        ),
        'post term' => array(
            'command' => 'post term',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Set category post term `test` to the post ID 123
    $ wp post term set 123 test category
    Success: Set term.

    # Set category post terms `test` and `apple` to the post ID 123
    $ wp post term set 123 test apple category
    Success: Set terms.

    # List category post terms for the post ID 123
    $ wp post term list 123 category --fields=term_id,slug
    +---------+-------+
    | term_id | slug  |
    +---------+-------+
    | 2       | apple |
    | 3       | test  |
    +----------+------+

    # Remove category post terms `test` and `apple` for the post ID 123
    $ wp post term remove 123 category test apple
    Success: Removed terms.',
            'shortdesc' => 'Adds, updates, removes, and lists post terms.',
            'subcommands' => array(
                'add',
                'list',
                'remove',
                'set',
            ),
        ),
        'post-type' => array(
            'command' => 'post-type',
            'global_prefix' => '',
            'longdesc' => 'Get information on WordPress\' built-in and the site\'s [custom post types](https://developer.wordpress.org/plugins/post-types/).

## EXAMPLES

    # Get details about a post type
    $ wp post-type get page --fields=name,label,hierarchical --format=json
    {"name":"page","label":"Pages","hierarchical":true}

    # List post types with \'post\' capability type
    $ wp post-type list --capability_type=post --fields=name,public
    +---------------+--------+
    | name          | public |
    +---------------+--------+
    | post          | 1      |
    | attachment    | 1      |
    | revision      |        |
    | nav_menu_item |        |
    +---------------+--------+',
            'shortdesc' => 'Retrieves details on the site\'s registered post types.',
            'subcommands' => array(
                'get',
                'list',
            ),
        ),
        'rewrite' => array(
            'command' => 'rewrite',
            'global_prefix' => '',
            'longdesc' => 'See the WordPress [Rewrite API](https://codex.wordpress.org/Rewrite_API) and
[WP Rewrite](https://codex.wordpress.org/Class_Reference/WP_Rewrite) class reference.

## EXAMPLES

    # Flush rewrite rules
    $ wp rewrite flush
    Success: Rewrite rules flushed.

    # Update permalink structure
    $ wp rewrite structure \'/%year%/%monthnum%/%postname%\'
    Success: Rewrite structure set.

    # List rewrite rules
    $ wp rewrite list --format=csv
    match,query,source
    ^wp-json/?$,index.php?rest_route=/,other
    ^wp-json/(.*)?,index.php?rest_route=/$matches[1],other
    category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$,index.php?category_name=$matches[1]&feed=$matches[2],category
    category/(.+?)/(feed|rdf|rss|rss2|atom)/?$,index.php?category_name=$matches[1]&feed=$matches[2],category
    category/(.+?)/embed/?$,index.php?category_name=$matches[1]&embed=true,category',
            'shortdesc' => 'Lists or flushes the site\'s rewrite rules, updates the permalink structure.',
            'subcommands' => array(
                'flush',
                'list',
                'structure',
            ),
        ),
        'role' => array(
            'command' => 'role',
            'global_prefix' => '',
            'longdesc' => 'See references for [Roles and Capabilities](https://codex.wordpress.org/Roles_and_Capabilities) and [WP User class](https://codex.wordpress.org/Class_Reference/WP_User).

## EXAMPLES

    # List roles.
    $ wp role list --fields=role --format=csv
    role
    administrator
    editor
    author
    contributor
    subscriber

    # Check to see if a role exists.
    $ wp role exists editor
    Success: Role with ID \'editor\' exists.

    # Create a new role.
    $ wp role create approver Approver
    Success: Role with key \'approver\' created.

    # Delete an existing role.
    $ wp role delete approver
    Success: Role with key \'approver\' deleted.

    # Reset existing roles to their default capabilities.
    $ wp role reset administrator author contributor
    Success: Reset 3/3 roles.',
            'shortdesc' => 'Manages user roles, including creating new roles and resetting to defaults.',
            'subcommands' => array(
                'create',
                'delete',
                'exists',
                'list',
                'reset',
            ),
        ),
        'scaffold' => array(
            'command' => 'scaffold',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Generate a new plugin with unit tests.
    $ wp scaffold plugin sample-plugin
    Success: Created plugin files.
    Success: Created test files.

    # Generate theme based on _s.
    $ wp scaffold _s sample-theme --theme_name="Sample Theme" --author="John Doe"
    Success: Created theme \'Sample Theme\'.

    # Generate code for post type registration in given theme.
    $ wp scaffold post-type movie --label=Movie --theme=simple-life
    Success: Created \'/var/www/example.com/public_html/wp-content/themes/simple-life/post-types/movie.php\'.',
            'shortdesc' => 'Generates code for post types, taxonomies, plugins, child themes, etc.',
            'subcommands' => array(
                'block',
                'child-theme',
                'plugin',
                'plugin-tests',
                'post-type',
                'taxonomy',
                'theme-tests',
                'underscores',
            ),
        ),
        'sidebar' => array(
            'command' => 'sidebar',
            'global_prefix' => '',
            'longdesc' => 'A [sidebar](https://developer.wordpress.org/themes/functionality/sidebars/) is any widgetized area of your theme.

## EXAMPLES

    # List sidebars
    $ wp sidebar list --fields=name,id --format=csv
    name,id
    "Widget Area",sidebar-1
    "Inactive Widgets",wp_inactive_widgets',
            'shortdesc' => 'Lists registered sidebars.',
            'subcommands' => array(
                'list',
            ),
        ),
        'site' => array(
            'command' => 'site',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # Create site
    $ wp site create --slug=example
    Success: Site 3 created: www.example.com/example/

    # Output a simple list of site URLs
    $ wp site list --field=url
    http://www.example.com/
    http://www.example.com/subdir/

    # Delete site
    $ wp site delete 123
    Are you sure you want to delete the \'http://www.example.com/example\' site? [y/n] y
    Success: The site at \'http://www.example.com/example\' was deleted.',
            'shortdesc' => 'Creates, deletes, empties, moderates, and lists one or more sites on a multisite installation.',
            'subcommands' => array(
                'activate',
                'archive',
                'create',
                'deactivate',
                'delete',
                'empty',
                'generate',
                'list',
                'mature',
                'meta',
                'option',
                'private',
                'public',
                'spam',
                'switch-language',
                'unarchive',
                'unmature',
                'unspam',
            ),
        ),
        'site meta' => array(
            'command' => 'site meta',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Set site meta
    $ wp site meta set 123 bio "Mary is a WordPress developer."
    Success: Updated custom field \'bio\'.

    # Get site meta
    $ wp site meta get 123 bio
    Mary is a WordPress developer.

    # Update site meta
    $ wp site meta update 123 bio "Mary is an awesome WordPress developer."
    Success: Updated custom field \'bio\'.

    # Delete site meta
    $ wp site meta delete 123 bio
    Success: Deleted custom field.',
            'shortdesc' => 'Adds, updates, deletes, and lists site custom fields.',
            'subcommands' => array(
                'add',
                'delete',
                'get',
                'list',
                'patch',
                'pluck',
                'update',
            ),
        ),
        'site option' => array(
            'command' => 'site option',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Get site registration
    $ wp site option get registration
    none

    # Add site option
    $ wp site option add my_option foobar
    Success: Added \'my_option\' site option.

    # Update site option
    $ wp site option update my_option \'{"foo": "bar"}\' --format=json
    Success: Updated \'my_option\' site option.

    # Delete site option
    $ wp site option delete my_option
    Success: Deleted \'my_option\' site option.',
            'shortdesc' => 'Adds, updates, deletes, and lists site options in a multisite installation.',
            'subcommands' => array(
                'add',
                'delete',
                'get',
                'list',
                'patch',
                'pluck',
                'update',
            ),
        ),
        'super-admin' => array(
            'command' => 'super-admin',
            'global_prefix' => '',
            'longdesc' => '## EXAMPLES

    # List user with super-admin capabilities.
    $ wp super-admin list
    supervisor
    administrator

    # Grant super-admin privileges to the user.
    $ wp super-admin add superadmin2
    Success: Granted super-admin capabilities to 1 user.

    # Revoke super-admin privileges from the user.
    $ wp super-admin remove superadmin2
    Success: Revoked super-admin capabilities from 1 user.',
            'shortdesc' => 'Lists, adds, or removes super admin users on a multisite installation.',
            'subcommands' => array(
                'add',
                'list',
                'remove',
            ),
        ),
        'taxonomy' => array(
            'command' => 'taxonomy',
            'global_prefix' => '',
            'longdesc' => 'See references for [built-in taxonomies](https://developer.wordpress.org/themes/basics/categories-tags-custom-taxonomies/) and [custom taxonomies](https://developer.wordpress.org/plugins/taxonomies/working-with-custom-taxonomies/).

## EXAMPLES

    # List all taxonomies with \'post\' object type.
    $ wp taxonomy list --object_type=post --fields=name,public
    +-------------+--------+
    | name        | public |
    +-------------+--------+
    | category    | 1      |
    | post_tag    | 1      |
    | post_format | 1      |
    +-------------+--------+

    # Get capabilities of \'post_tag\' taxonomy.
    $ wp taxonomy get post_tag --field=cap
    {"manage_terms":"manage_categories","edit_terms":"manage_categories","delete_terms":"manage_categories","assign_terms":"edit_posts"}',
            'shortdesc' => 'Retrieves information about registered taxonomies.',
            'subcommands' => array(
                'get',
                'list',
            ),
        ),
        'term' => array(
            'command' => 'term',
            'global_prefix' => '',
            'longdesc' => 'See reference for [taxonomies and their terms](https://codex.wordpress.org/Taxonomies).

## EXAMPLES

    # Create a new term.
    $ wp term create category Apple --description="A type of fruit"
    Success: Created category 199.

    # Get details about a term.
    $ wp term get category 199 --format=json --fields=term_id,name,slug,count
    {"term_id":199,"name":"Apple","slug":"apple","count":1}

    # Update an existing term.
    $ wp term update category 15 --name=Apple
    Success: Term updated.

    # Get the term\'s URL.
    $ wp term list post_tag --include=123 --field=url
    http://example.com/tag/tips-and-tricks

    # Delete post category
    $ wp term delete category 15
    Success: Deleted category 15.

    # Recount posts assigned to each categories and tags
    $ wp term recount category post_tag
    Success: Updated category term count
    Success: Updated post_tag term count',
            'shortdesc' => 'Manages taxonomy terms and term meta, with create, delete, and list commands.',
            'subcommands' => array(
                'create',
                'delete',
                'generate',
                'get',
                'list',
                'meta',
                'migrate',
                'recount',
                'update',
            ),
        ),
        'term meta' => array(
            'command' => 'term meta',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Set term meta
    $ wp term meta set 123 bio "Mary is a WordPress developer."
    Success: Updated custom field \'bio\'.

    # Get term meta
    $ wp term meta get 123 bio
    Mary is a WordPress developer.

    # Update term meta
    $ wp term meta update 123 bio "Mary is an awesome WordPress developer."
    Success: Updated custom field \'bio\'.

    # Delete term meta
    $ wp term meta delete 123 bio
    Success: Deleted custom field.',
            'shortdesc' => 'Adds, updates, deletes, and lists term custom fields.',
            'subcommands' => array(
                'add',
                'delete',
                'get',
                'list',
                'patch',
                'pluck',
                'update',
            ),
        ),
        'theme' => array(
            'command' => 'theme',
            'global_prefix' => '',
            'longdesc' => 'See the WordPress [Theme Handbook](https://developer.wordpress.org/themes/) developer resource for more information on themes.

## EXAMPLES

    # Install the latest version of a theme from wordpress.org and activate
    $ wp theme install twentysixteen --activate
    Installing Twenty Sixteen (1.2)
    Downloading install package from http://downloads.wordpress.org/theme/twentysixteen.1.2.zip...
    Unpacking the package...
    Installing the theme...
    Theme installed successfully.
    Activating \'twentysixteen\'...
    Success: Switched to \'Twenty Sixteen\' theme.
    Success: Installed 1 of 1 themes.

    # Get details of an installed theme
    $ wp theme get twentysixteen --fields=name,title,version
    +---------+----------------+
    | Field   | Value          |
    +---------+----------------+
    | name    | Twenty Sixteen |
    | title   | Twenty Sixteen |
    | version | 1.2            |
    +---------+----------------+

    # Get status of theme
    $ wp theme status twentysixteen
    Theme twentysixteen details:
         Name: Twenty Sixteen
         Status: Active
         Version: 1.2
         Author: the WordPress team',
            'shortdesc' => 'Manages themes, including installs, activations, and updates.',
            'subcommands' => array(
                'activate',
                'auto-updates',
                'delete',
                'disable',
                'enable',
                'get',
                'install',
                'is-active',
                'is-installed',
                'list',
                'mod',
                'path',
                'search',
                'status',
                'update',
            ),
        ),
        'theme auto-updates' => array(
            'command' => 'theme auto-updates',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Enable the auto-updates for a theme
    $ wp theme auto-updates enable twentysixteen
    Theme auto-updates for \'twentysixteen\' enabled.
    Success: Enabled 1 of 1 theme auto-updates.

    # Disable the auto-updates for a theme
    $ wp theme auto-updates disable twentysixteen
    Theme auto-updates for \'twentysixteen\' disabled.
    Success: Disabled 1 of 1 theme auto-updates.

    # Get the status of theme auto-updates
    $ wp theme auto-updates status twentysixteen
    Auto-updates for theme \'twentysixteen\' are disabled.',
            'shortdesc' => 'Manages theme auto-updates.',
            'subcommands' => array(
                'disable',
                'enable',
                'status',
            ),
        ),
        'theme mod' => array(
            'command' => 'theme mod',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Set the \'background_color\' theme mod to \'000000\'.
    $ wp theme mod set background_color 000000
    Success: Theme mod background_color set to 000000.

    # Get single theme mod in JSON format.
    $ wp theme mod get background_color --format=json
    [{"key":"background_color","value":"dd3333"}]

    # Remove all theme mods.
    $ wp theme mod remove --all
    Success: Theme mods removed.',
            'shortdesc' => 'Sets, gets, and removes theme mods.',
            'subcommands' => array(
                'get',
                'list',
                'remove',
                'set',
            ),
        ),
        'transient' => array(
            'command' => 'transient',
            'global_prefix' => '',
            'longdesc' => 'By default, the transient cache uses the WordPress database to persist values
between requests. On a single site installation, values are stored in the
`wp_options` table. On a multisite installation, values are stored in the
`wp_options` or the `wp_sitemeta` table, depending on use of the `--network`
flag.

When a persistent object cache drop-in is installed (e.g. Redis or Memcached),
the transient cache skips the database and simply wraps the WP Object Cache.

## EXAMPLES

    # Set transient.
    $ wp transient set sample_key "test data" 3600
    Success: Transient added.

    # Get transient.
    $ wp transient get sample_key
    test data

    # Delete transient.
    $ wp transient delete sample_key
    Success: Transient deleted.

    # Delete expired transients.
    $ wp transient delete --expired
    Success: 12 expired transients deleted from the database.

    # Delete all transients.
    $ wp transient delete --all
    Success: 14 transients deleted from the database.',
            'shortdesc' => 'Adds, gets, and deletes entries in the WordPress Transient Cache.',
            'subcommands' => array(
                'delete',
                'get',
                'list',
                'patch',
                'pluck',
                'set',
                'type',
            ),
        ),
        'user' => array(
            'command' => 'user',
            'global_prefix' => '',
            'longdesc' => 'See references for [Roles and Capabilities](https://codex.wordpress.org/Roles_and_Capabilities) and [WP User class](https://codex.wordpress.org/Class_Reference/WP_User).

## EXAMPLES

    # List user IDs
    $ wp user list --field=ID
    1

    # Create a new user.
    $ wp user create bob bob@example.com --role=author
    Success: Created user 3.
    Password: k9**&I4vNH(&

    # Update an existing user.
    $ wp user update 123 --display_name=Mary --user_pass=marypass
    Success: Updated user 123.

    # Delete user 123 and reassign posts to user 567
    $ wp user delete 123 --reassign=567
    Success: Removed user 123 from http://example.com.',
            'shortdesc' => 'Manages users, along with their roles, capabilities, and meta.',
            'subcommands' => array(
                'add-cap',
                'add-role',
                'application-password',
                'check-password',
                'create',
                'delete',
                'exists',
                'generate',
                'get',
                'import-csv',
                'list',
                'list-caps',
                'meta',
                'remove-cap',
                'remove-role',
                'reset-password',
                'session',
                'set-role',
                'signup',
                'spam',
                'term',
                'unspam',
                'update',
            ),
        ),
        'user application-password' => array(
            'command' => 'user application-password',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # List user application passwords and only show app name and password hash
    $ wp user application-password list 123 --fields=name,password
    +--------+------------------------------------+
    | name   | password                           |
    +--------+------------------------------------+
    | myapp  | $P$BVGeou1CUot114YohIemgpwxQCzb8O/ |
    +--------+------------------------------------+

    # Get a specific application password and only show app name and created timestamp
    $ wp user application-password get 123 6633824d-c1d7-4f79-9dd5-4586f734d69e --fields=name,created
    +--------+------------+
    | name   | created    |
    +--------+------------+
    | myapp  | 1638395611 |
    +--------+------------+

    # Create user application password
    $ wp user application-password create 123 myapp
    Success: Created application password.
    Password: ZG1bxdxdzjTwhsY8vK8l1C65

    # Only print the password without any chrome
    $ wp user application-password create 123 myapp --porcelain
    ZG1bxdxdzjTwhsY8vK8l1C65

    # Update an existing application password
    $ wp user application-password update 123 6633824d-c1d7-4f79-9dd5-4586f734d69e --name=newappname
    Success: Updated application password.

    # Delete an existing application password
    $ wp user application-password delete 123 6633824d-c1d7-4f79-9dd5-4586f734d69e
    Success: Deleted 1 of 1 application password.

    # Check if an application password for a given application exists
    $ wp user application-password exists 123 myapp
    $ echo $?
    1

    # Bash script for checking whether an application password exists and creating one if not
    if ! wp user application-password exists 123 myapp; then
        PASSWORD=$(wp user application-password create 123 myapp --porcelain)
    fi',
            'shortdesc' => 'Creates, updates, deletes, lists and retrieves application passwords.',
            'subcommands' => array(
                'create',
                'delete',
                'exists',
                'get',
                'list',
                'record-usage',
                'update',
            ),
        ),
        'user meta' => array(
            'command' => 'user meta',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Add user meta
    $ wp user meta add 123 bio "Mary is an WordPress developer."
    Success: Added custom field.

    # List user meta
    $ wp user meta list 123 --keys=nickname,description,wp_capabilities
    +---------+-----------------+--------------------------------+
    | user_id | meta_key        | meta_value                     |
    +---------+-----------------+--------------------------------+
    | 123     | nickname        | supervisor                     |
    | 123     | description     | Mary is a WordPress developer. |
    | 123     | wp_capabilities | {"administrator":true}         |
    +---------+-----------------+--------------------------------+

    # Update user meta
    $ wp user meta update 123 bio "Mary is an awesome WordPress developer."
    Success: Updated custom field \'bio\'.

    # Delete user meta
    $ wp user meta delete 123 bio
    Success: Deleted custom field.',
            'shortdesc' => 'Adds, updates, deletes, and lists user custom fields.',
            'subcommands' => array(
                'add',
                'delete',
                'get',
                'list',
                'patch',
                'pluck',
                'update',
            ),
        ),
        'user session' => array(
            'command' => 'user session',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # List a user\'s sessions.
    $ wp user session list admin@example.com --format=csv
    login_time,expiration_time,ip,ua
    "2016-01-01 12:34:56","2016-02-01 12:34:56",127.0.0.1,"Mozilla/5.0..."

    # Destroy the most recent session of the given user.
    $ wp user session destroy admin
    Success: Destroyed session. 3 sessions remaining.',
            'shortdesc' => 'Destroys and lists a user\'s sessions.',
            'subcommands' => array(
                'destroy',
                'list',
            ),
        ),
        'user signup' => array(
            'command' => 'user signup',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # List signups.
    $ wp user signup list
    +-----------+------------+---------------------+---------------------+--------+------------------+
    | signup_id | user_login | user_email          | registered          | active | activation_key   |
    +-----------+------------+---------------------+---------------------+--------+------------------+
    | 1         | bobuser    | bobuser@example.com | 2024-03-13 05:46:53 | 1      | 7320b2f009266618 |
    | 2         | johndoe    | johndoe@example.com | 2024-03-13 06:24:44 | 0      | 9068d859186cd0b5 |
    +-----------+------------+---------------------+---------------------+--------+------------------+

    # Activate signup.
    $ wp user signup activate 2
    Signup 2 activated. Password: bZFSGsfzb9xs
    Success: Activated 1 of 1 signups.

    # Delete signup.
    $ wp user signup delete 3
    Signup 3 deleted.
    Success: Deleted 1 of 1 signups.',
            'shortdesc' => 'Manages signups on a multisite installation.',
            'subcommands' => array(
                'activate',
                'delete',
                'get',
                'list',
            ),
        ),
        'user term' => array(
            'command' => 'user term',
            'global_prefix' => '

',
            'longdesc' => '## EXAMPLES

    # Set user terms
    $ wp user term set 123 test category
    Success: Set terms.',
            'shortdesc' => 'Adds, updates, removes, and lists user terms.',
            'subcommands' => array(
                'add',
                'list',
                'remove',
                'set',
            ),
        ),
        'widget' => array(
            'command' => 'widget',
            'global_prefix' => '',
            'longdesc' => 'A [widget](https://developer.wordpress.org/themes/functionality/widgets/) adds content and features to a widget area (also called a [sidebar](https://developer.wordpress.org/themes/functionality/sidebars/)).

## EXAMPLES

    # List widgets on a given sidebar
    $ wp widget list sidebar-1
    +----------+------------+----------+----------------------+
    | name     | id         | position | options              |
    +----------+------------+----------+----------------------+
    | meta     | meta-6     | 1        | {"title":"Meta"}     |
    | calendar | calendar-2 | 2        | {"title":"Calendar"} |
    +----------+------------+----------+----------------------+

    # Add a calendar widget to the second position on the sidebar
    $ wp widget add calendar sidebar-1 2
    Success: Added widget to sidebar.

    # Update option(s) associated with a given widget
    $ wp widget update calendar-1 --title="Calendar"
    Success: Widget updated.

    # Delete one or more widgets entirely
    $ wp widget delete calendar-2 archive-1
    Success: 2 widgets removed from sidebar.',
            'shortdesc' => 'Manages widgets, including adding and moving them within sidebars.',
            'subcommands' => array(
                'add',
                'deactivate',
                'delete',
                'list',
                'move',
                'reset',
                'update',
            ),
        ),
    ),
    'families' => array(
        'cache' => 'cache',
        'cap' => 'cap',
        'cli' => 'cli',
        'comment' => 'comment',
        'config' => 'config',
        'core' => 'core',
        'cron' => 'cron',
        'db' => 'db',
        'embed' => 'embed',
        'eval' => 'eval',
        'eval-file' => 'eval-file',
        'export' => 'export',
        'help' => 'help',
        'i18n' => 'i18n',
        'import' => 'import',
        'language' => 'language',
        'maintenance-mode' => 'maintenance-mode',
        'media' => 'media',
        'menu' => 'menu',
        'network' => 'network',
        'option' => 'option',
        'package' => 'package',
        'plugin' => 'plugin',
        'post' => 'post',
        'post-type' => 'post-type',
        'rewrite' => 'rewrite',
        'role' => 'role',
        'scaffold' => 'scaffold',
        'search-replace' => 'search-replace',
        'server' => 'server',
        'shell' => 'shell',
        'sidebar' => 'sidebar',
        'site' => 'site',
        'super-admin' => 'super-admin',
        'taxonomy' => 'taxonomy',
        'term' => 'term',
        'theme' => 'theme',
        'transient' => 'transient',
        'user' => 'user',
        'widget' => 'widget',
    ),
    'global_help' => '

## GLOBAL PARAMETERS

  --path=<path>
      Path to the WordPress files.

  --url=<url>
      Pretend request came from given URL. In multisite, this argument is how the target site is specified.

  --ssh=[<scheme>:][<user>@]<host|container>[:<port>][<path>]
      Perform operation against a remote server over SSH (or a container using scheme of "docker", "docker-compose", "docker-compose-run", "vagrant").

  --http=<http>
      Perform operation against a remote WordPress installation over HTTP.

  --user=<id|login|email>
      Set the WordPress user.

  --skip-plugins[=<plugins>]
      Skip loading all plugins, or a comma-separated list of plugins. Note: mu-plugins are still loaded.

  --skip-themes[=<themes>]
      Skip loading all themes, or a comma-separated list of themes.

  --skip-packages
      Skip loading all installed packages.

  --require=<path>
      Load PHP file before running the command (may be used more than once).

  --exec=<php-code>
      Execute PHP code before running the command (may be used more than once).

  --context=<context>
      Load WordPress in a given context.

  --[no-]color
      Whether to colorize the output.

  --debug[=<group>]
      Show all PHP errors and add verbosity to WP-CLI output. Built-in groups include: bootstrap, commandfactory, and help.

  --prompt[=<assoc>]
      Prompt the user to enter values for all command arguments, or a subset specified as comma-separated values.

  --quiet
      Suppress informational messages.

',
    'global_synopsis_names' => array(
        'path',
        'url',
        'http',
        'user',
        'skip-plugins',
        'skip-themes',
        'skip-packages',
        'require',
        'exec',
        'context',
        'color',
        'debug',
        'prompt',
        'quiet',
    ),
    'globals' => array(
        'allow-root' => array(
            'default' => null,
            'file' => false,
            'hidden' => true,
            'multiple' => false,
            'runtime' => '',
            'synopsis' => '',
        ),
        'blog' => array(
            'default' => null,
            'deprecated' => 'Use --url instead.',
            'file' => false,
            'multiple' => false,
            'runtime' => '=<url>',
            'synopsis' => '',
        ),
        'color' => array(
            'default' => 'auto',
            'desc' => 'Whether to colorize the output.',
            'file' => '<bool>',
            'multiple' => false,
            'runtime' => true,
            'synopsis' => '',
        ),
        'context' => array(
            'default' => 'cli',
            'desc' => 'Load WordPress in a given context.',
            'file' => '<context>',
            'multiple' => false,
            'runtime' => '=<context>',
            'synopsis' => '',
        ),
        'debug' => array(
            'default' => false,
            'desc' => 'Show all PHP errors and add verbosity to WP-CLI output. Built-in groups include: bootstrap, commandfactory, and help.',
            'file' => '<group>',
            'multiple' => false,
            'runtime' => '[=<group>]',
            'synopsis' => '',
        ),
        'exec' => array(
            'default' => array(),
            'desc' => 'Execute PHP code before running the command (may be used more than once).',
            'file' => '<php-code>',
            'multiple' => true,
            'runtime' => '=<php-code>',
            'synopsis' => '',
        ),
        'http' => array(
            'default' => null,
            'desc' => 'Perform operation against a remote WordPress installation over HTTP.',
            'file' => '<http>',
            'multiple' => false,
            'runtime' => '=<http>',
            'synopsis' => '',
        ),
        'path' => array(
            'default' => null,
            'desc' => 'Path to the WordPress files.',
            'file' => '<path>',
            'multiple' => false,
            'runtime' => '=<path>',
            'synopsis' => '',
        ),
        'prompt' => array(
            'default' => false,
            'desc' => 'Prompt the user to enter values for all command arguments, or a subset specified as comma-separated values.',
            'file' => false,
            'multiple' => false,
            'runtime' => '[=<assoc>]',
            'synopsis' => '',
        ),
        'quiet' => array(
            'default' => false,
            'desc' => 'Suppress informational messages.',
            'file' => '<bool>',
            'multiple' => false,
            'runtime' => '',
            'synopsis' => '',
        ),
        'require' => array(
            'default' => array(),
            'desc' => 'Load PHP file before running the command (may be used more than once).',
            'file' => '<path>',
            'multiple' => true,
            'runtime' => '=<path>',
            'synopsis' => '',
        ),
        'skip-packages' => array(
            'default' => false,
            'desc' => 'Skip loading all installed packages.',
            'file' => '<bool>',
            'multiple' => false,
            'runtime' => '',
            'synopsis' => '',
        ),
        'skip-plugins' => array(
            'default' => '',
            'desc' => 'Skip loading all plugins, or a comma-separated list of plugins. Note: mu-plugins are still loaded.',
            'file' => '<list>',
            'multiple' => false,
            'runtime' => '[=<plugins>]',
            'synopsis' => '',
        ),
        'skip-themes' => array(
            'default' => '',
            'desc' => 'Skip loading all themes, or a comma-separated list of themes.',
            'file' => '<list>',
            'multiple' => false,
            'runtime' => '[=<themes>]',
            'synopsis' => '',
        ),
        'ssh' => array(
            'default' => null,
            'desc' => 'Perform operation against a remote server over SSH (or a container using scheme of "docker", "docker-compose", "docker-compose-run", "vagrant").',
            'file' => '[<scheme>:][<user>@]<host|container>[:<port>][<path>]',
            'multiple' => false,
            'runtime' => '=[<scheme>:][<user>@]<host|container>[:<port>][<path>]',
            'synopsis' => '',
        ),
        'url' => array(
            'default' => null,
            'desc' => 'Pretend request came from given URL. In multisite, this argument is how the target site is specified.',
            'file' => '<url>',
            'multiple' => false,
            'runtime' => '=<url>',
            'synopsis' => '',
        ),
        'user' => array(
            'default' => null,
            'desc' => 'Set the WordPress user.',
            'file' => '<id|login|email>',
            'multiple' => false,
            'runtime' => '=<id|login|email>',
            'synopsis' => '',
        ),
    ),
    'root' => array(
        'longdesc' => '

## GLOBAL PARAMETERS

  --path=<path>
      Path to the WordPress files.

  --url=<url>
      Pretend request came from given URL. In multisite, this argument is how the target site is specified.

  --ssh=[<scheme>:][<user>@]<host|container>[:<port>][<path>]
      Perform operation against a remote server over SSH (or a container using scheme of "docker", "docker-compose", "docker-compose-run", "vagrant").

  --http=<http>
      Perform operation against a remote WordPress installation over HTTP.

  --user=<id|login|email>
      Set the WordPress user.

  --skip-plugins[=<plugins>]
      Skip loading all plugins, or a comma-separated list of plugins. Note: mu-plugins are still loaded.

  --skip-themes[=<themes>]
      Skip loading all themes, or a comma-separated list of themes.

  --skip-packages
      Skip loading all installed packages.

  --require=<path>
      Load PHP file before running the command (may be used more than once).

  --exec=<php-code>
      Execute PHP code before running the command (may be used more than once).

  --context=<context>
      Load WordPress in a given context.

  --[no-]color
      Whether to colorize the output.

  --debug[=<group>]
      Show all PHP errors and add verbosity to WP-CLI output. Built-in groups include: bootstrap, commandfactory, and help.

  --prompt[=<assoc>]
      Prompt the user to enter values for all command arguments, or a subset specified as comma-separated values.

  --quiet
      Suppress informational messages.

  Run \'wp help <command>\' to get more information on a specific command.

',
        'shortdesc' => 'Manage WordPress through the command-line.',
        'subcommands' => array(
            'cache',
            'cap',
            'cli',
            'comment',
            'config',
            'core',
            'cron',
            'db',
            'embed',
            'eval',
            'eval-file',
            'export',
            'help',
            'i18n',
            'import',
            'language',
            'maintenance-mode',
            'media',
            'menu',
            'network',
            'option',
            'package',
            'plugin',
            'post',
            'post-type',
            'rewrite',
            'role',
            'scaffold',
            'search-replace',
            'server',
            'shell',
            'sidebar',
            'site',
            'super-admin',
            'taxonomy',
            'term',
            'theme',
            'transient',
            'user',
            'widget',
        ),
    ),
    'source_sha512' => 'be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832',
    'version' => '2.12.0',
);
