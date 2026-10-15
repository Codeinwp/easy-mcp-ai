<?php








if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'core check-update' => array(
        'command' => 'core check-update',
        'fields' => array(
            'default' => array(
                'version',
                'update_type',
                'package_url',
            ),
            'optional' => array(),
        ),
        'longdesc' => 'Lists the most recent versions when there are updates available,
or success message when up to date.

## OPTIONS

[--minor]
: Compare only the first two parts of the version number.

[--major]
: Compare only the first part of the version number.

[--force-check]
: Bypass the transient cache and force a fresh update check.

[--field=<field>]
: Prints the value of a single field for each update.

[--fields=<fields>]
: Limit the output to specific object fields. Defaults to version,update_type,package_url.

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

    $ wp core check-update
    +---------+-------------+-------------------------------------------------------------+
    | version | update_type | package_url                                                 |
    +---------+-------------+-------------------------------------------------------------+
    | 4.5.2   | major       | https://downloads.wordpress.org/release/wordpress-4.5.2.zip |
    +---------+-------------+-------------------------------------------------------------+',
        'name' => 'check-update',
        'params' => array(
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
                'name' => 'force-check',
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
                'desc' => 'Limit the output to specific object fields. Defaults to version,update_type,package_url.',
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
        'shortdesc' => 'Checks for WordPress updates via Version Check API.',
        'synopsis' => '[--minor] [--major] [--force-check] [--field=<field>] [--fields=<fields>] [--format=<format>]',
    ),
    'core download' => array(
        'command' => 'core download',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Downloads and extracts WordPress core files to the specified path. Uses
current directory when no path is specified. Downloaded build is verified
to have the correct md5 and then cached to the local filesystem.
Subsequent uses of command will use the local cache if it still exists.

## OPTIONS

[<download-url>]
: Download directly from a provided URL instead of fetching the URL from the wordpress.org servers.

[--path=<path>]
: Specify the path in which to install WordPress. Defaults to current
directory.

[--locale=<locale>]
: Select which language you want to download.

[--version=<version>]
: Select which version you want to download. Accepts a version number, \'latest\' or \'nightly\'.

[--skip-content]
: Download WP without the default themes and plugins.

[--force]
: Overwrites existing files, if present.

[--insecure]
: Retry download without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

[--extract]
: Whether to extract the downloaded file. Defaults to true.

## EXAMPLES

    $ wp core download --locale=nl_NL
    Downloading WordPress 4.5.2 (nl_NL)...
    md5 hash verified: c5366d05b521831dd0b29dfc386e56a5
    Success: WordPress downloaded.',
        'name' => 'download',
        'params' => array(
            array(
                'name' => 'download-url',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'desc' => 'Specify the path in which to install WordPress. Defaults to current',
                'name' => 'path',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Select which language you want to download.',
                'name' => 'locale',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Select which version you want to download. Accepts a version number, \'latest\' or \'nightly\'.',
                'name' => 'version',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'skip-content',
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
                'name' => 'insecure',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'extract',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Downloads core WordPress files.',
        'synopsis' => '[<download-url>] [--path=<path>] [--locale=<locale>] [--version=<version>] [--skip-content] [--force] [--insecure] [--extract]',
    ),
    'core install' => array(
        'command' => 'core install',
        'fields' => null,
        'longdesc' => 'Creates the WordPress tables in the database using the URL, title, and
default admin user details provided. Performs the famous 5 minute install
in seconds or less.

Note: if you\'ve installed WordPress in a subdirectory, then you\'ll need
to `wp option update siteurl` after `wp core install`. For instance, if
WordPress is installed in the `/wp` directory and your domain is example.com,
then you\'ll need to run `wp option update siteurl http://example.com/wp` for
your WordPress installation to function properly.

Note: When using custom user tables (e.g. `CUSTOM_USER_TABLE`), the admin
email and password are ignored if the user_login already exists. If the
user_login doesn\'t exist, a new user will be created.

## OPTIONS

--url=<url>
: The address of the new site.

--title=<site-title>
: The title of the new site.

--admin_user=<username>
: The name of the admin user.

[--admin_password=<password>]
: The password for the admin user. Defaults to randomly generated string.

--admin_email=<email>
: The email address for the admin user.

[--locale=<locale>]
: The locale/language for the installation (e.g. `de_DE`). Default is `en_US`.

[--skip-email]
: Don\'t send an email notification to the new admin user.

## EXAMPLES

    # Install WordPress in 5 seconds
    $ wp core install --url=example.com --title=Example --admin_user=supervisor --admin_password=strongpassword --admin_email=info@example.com
    Success: WordPress installed successfully.

    # Install WordPress without disclosing admin_password to bash history
    $ wp core install --url=example.com --title=Example --admin_user=supervisor --admin_email=info@example.com --prompt=admin_password < admin_password.txt',
        'name' => 'install',
        'params' => array(
            array(
                'desc' => 'The address of the new site.',
                'name' => 'url',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The title of the new site.',
                'name' => 'title',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The name of the admin user.',
                'name' => 'admin_user',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The password for the admin user. Defaults to randomly generated string.',
                'name' => 'admin_password',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The email address for the admin user.',
                'name' => 'admin_email',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The locale/language for the installation (e.g. `de_DE`). Default is `en_US`.',
                'name' => 'locale',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'skip-email',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Runs the standard WordPress installation process.',
        'synopsis' => '--url=<url> --title=<site-title> --admin_user=<username> [--admin_password=<password>] --admin_email=<email> [--locale=<locale>] [--skip-email]',
    ),
    'core is-installed' => array(
        'command' => 'core is-installed',
        'fields' => null,
        'longdesc' => 'Determines whether WordPress is installed by checking if the standard
database tables are installed. Doesn\'t produce output; uses exit codes
to communicate whether WordPress is installed.

## OPTIONS

[--network]
: Check if this is a multisite installation.

## EXAMPLES

    # Bash script for checking if WordPress is not installed.

    if ! wp core is-installed 2>/dev/null; then
        # WP is not installed. Let\'s try installing it.
        wp core install
    fi

    # Bash script for checking if WordPress is installed, with fallback.

    if wp core is-installed 2>/dev/null; then
        # WP is installed. Let\'s do some things we should only do in a confirmed WP environment.
        wp core verify-checksums
    else
        # Fallback if WP is not installed.
        echo \'Hey Friend, you are in the wrong spot. Move in to your WordPress directory and try again.\'
    fi',
        'name' => 'is-installed',
        'params' => array(
            array(
                'name' => 'network',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Checks if WordPress is installed.',
        'synopsis' => '[--network]',
    ),
    'core multisite-convert' => array(
        'alias' => 'install-network',
        'command' => 'core multisite-convert',
        'fields' => null,
        'longdesc' => 'Creates the multisite database tables, and adds the multisite constants
to wp-config.php.

For those using WordPress with Apache, remember to update the `.htaccess`
file with the appropriate multisite rewrite rules.

[Review the multisite documentation](https://wordpress.org/support/article/create-a-network/)
for more details about how multisite works.

## OPTIONS

[--title=<network-title>]
: The title of the new network.

[--base=<url-path>]
: Base path after the domain name that each site url will start with.
---
default: /
---

[--subdomains]
: If passed, the network will use subdomains, instead of subdirectories. Doesn\'t work with \'localhost\'.

[--skip-config]
: Don\'t add multisite constants to wp-config.php.

## EXAMPLES

    $ wp core multisite-convert
    Set up multisite database tables.
    Added multisite constants to wp-config.php.
    Success: Network installed. Don\'t forget to set up rewrite rules.',
        'name' => 'multisite-convert',
        'params' => array(
            array(
                'desc' => 'The title of the new network.',
                'name' => 'title',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => '/',
                'desc' => 'Base path after the domain name that each site url will start with.',
                'name' => 'base',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'subdomains',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-config',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Transforms an existing single-site installation into a multisite installation.',
        'synopsis' => '[--title=<network-title>] [--base=<url-path>] [--subdomains] [--skip-config]',
    ),
    'core multisite-install' => array(
        'command' => 'core multisite-install',
        'fields' => null,
        'longdesc' => 'Creates the WordPress tables in the database using the URL, title, and
default admin user details provided. Then, creates the multisite tables
in the database and adds multisite constants to the wp-config.php.

For those using WordPress with Apache, remember to update the `.htaccess`
file with the appropriate multisite rewrite rules.

## OPTIONS

[--url=<url>]
: The address of the new site.

[--base=<url-path>]
: Base path after the domain name that each site url in the network will start with.
---
default: /
---

[--subdomains]
: If passed, the network will use subdomains, instead of subdirectories. Doesn\'t work with \'localhost\'.

--title=<site-title>
: The title of the new site.

--admin_user=<username>
: The name of the admin user.
---
default: admin
---

[--admin_password=<password>]
: The password for the admin user. Defaults to randomly generated string.

--admin_email=<email>
: The email address for the admin user.

[--skip-email]
: Don\'t send an email notification to the new admin user.

[--skip-config]
: Don\'t add multisite constants to wp-config.php.

## EXAMPLES

    $ wp core multisite-install --title="Welcome to the WordPress" \\
    > --admin_user="admin" --admin_password="password" \\
    > --admin_email="user@example.com"
    Single site database tables already present.
    Set up multisite database tables.
    Added multisite constants to wp-config.php.
    Success: Network installed. Don\'t forget to set up rewrite rules.',
        'name' => 'multisite-install',
        'params' => array(
            array(
                'desc' => 'The address of the new site.',
                'name' => 'url',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => '/',
                'desc' => 'Base path after the domain name that each site url in the network will start with.',
                'name' => 'base',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'subdomains',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'The title of the new site.',
                'name' => 'title',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'default' => 'admin',
                'desc' => 'The name of the admin user.',
                'name' => 'admin_user',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The password for the admin user. Defaults to randomly generated string.',
                'name' => 'admin_password',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'The email address for the admin user.',
                'name' => 'admin_email',
                'optional' => false,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'name' => 'skip-email',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'name' => 'skip-config',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Installs WordPress multisite from scratch.',
        'synopsis' => '[--url=<url>] [--base=<url-path>] [--subdomains] --title=<site-title> --admin_user=<username> [--admin_password=<password>] --admin_email=<email> [--skip-email] [--skip-config]',
    ),
    'core update' => array(
        'alias' => 'upgrade',
        'command' => 'core update',
        'fields' => null,
        'longdesc' => 'Defaults to updating WordPress to the latest version.

If you see "Error: Another update is currently in progress.", you may
need to run `wp option delete core_updater.lock` after verifying another
update isn\'t actually running.

## OPTIONS

[<zip>]
: Path to zip file to use, instead of downloading from wordpress.org.

[--minor]
: Only perform updates for minor releases (e.g. update from WP 4.3 to 4.3.3 instead of 4.4.2).

[--version=<version>]
: Update to a specific version, instead of to the latest version. Alternatively accepts \'nightly\'.

[--force]
: Update even when installed WP version is greater than the requested version.

[--locale=<locale>]
: Select which language you want to download.

[--insecure]
: Retry download without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

## EXAMPLES

    # Update WordPress
    $ wp core update
    Updating to version 4.5.2 (en_US)...
    Downloading update from https://downloads.wordpress.org/release/wordpress-4.5.2-no-content.zip...
    Unpacking the update...
    Cleaning up files...
    No files found that need cleaning up
    Success: WordPress updated successfully.

    # Update WordPress using zip file.
    $ wp core update ../latest.zip
    Starting update...
    Unpacking the update...
    Success: WordPress updated successfully.

    # Update WordPress to 3.1 forcefully
    $ wp core update --version=3.1 --force
    Updating to version 3.1 (en_US)...
    Downloading update from https://wordpress.org/wordpress-3.1.zip...
    Unpacking the update...
    Warning: Checksums not available for WordPress 3.1/en_US. Please cleanup files manually.
    Success: WordPress updated successfully.',
        'name' => 'update',
        'params' => array(
            array(
                'name' => 'zip',
                'optional' => true,
                'repeating' => false,
                'type' => 'positional',
            ),
            array(
                'name' => 'minor',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Update to a specific version, instead of to the latest version. Alternatively accepts \'nightly\'.',
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
                'desc' => 'Select which language you want to download.',
                'name' => 'locale',
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
        'shortdesc' => 'Updates WordPress to a newer version.',
        'synopsis' => '[<zip>] [--minor] [--version=<version>] [--force] [--locale=<locale>] [--insecure]',
    ),
    'core update-db' => array(
        'command' => 'core update-db',
        'fields' => null,
        'longdesc' => '## OPTIONS

[--network]
: Update databases for all sites on a network

[--dry-run]
: Compare database versions without performing the update.

## EXAMPLES

    # Update the WordPress database.
    $ wp core update-db
    Success: WordPress database upgraded successfully from db version 36686 to 35700.

    # Update databases for all sites on a network.
    $ wp core update-db --network
    WordPress database upgraded successfully from db version 35700 to 29630 on example.com/
    Success: WordPress database upgraded on 123/123 sites.',
        'name' => 'update-db',
        'params' => array(
            array(
                'name' => 'network',
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
        'shortdesc' => 'Runs the WordPress database update procedure.',
        'synopsis' => '[--network] [--dry-run]',
    ),
    'core verify-checksums' => array(
        'command' => 'core verify-checksums',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => 'Downloads md5 checksums for the current version from WordPress.org, and
compares those checksums against the currently installed files.

For security, avoids loading WordPress when verifying checksums.

If you experience issues verifying from this command, ensure you are
passing the relevant `--locale` and `--version` arguments according to
the values from the `Dashboard->Updates` menu in the admin area of the
site.

## OPTIONS

[--include-root]
: Verify all files and folders in the root directory, and warn if any non-WordPress items are found.

[--version=<version>]
: Verify checksums against a specific version of WordPress.

[--locale=<locale>]
: Verify checksums against a specific locale of WordPress.

[--insecure]
: Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.

[--exclude=<files>]
: Exclude specific files from the checksum verification. Provide a comma-separated list of file paths.

## EXAMPLES

    # Verify checksums
    $ wp core verify-checksums
    Success: WordPress installation verifies against checksums.

    # Verify checksums for given WordPress version
    $ wp core verify-checksums --version=4.0
    Success: WordPress installation verifies against checksums.

    # Verify checksums for given locale
    $ wp core verify-checksums --locale=en_US
    Success: WordPress installation verifies against checksums.

    # Verify checksums for given locale
    $ wp core verify-checksums --locale=ja
    Warning: File doesn\'t verify against checksum: wp-includes/version.php
    Warning: File doesn\'t verify against checksum: readme.html
    Warning: File doesn\'t verify against checksum: wp-config-sample.php
    Error: WordPress installation doesn\'t verify against checksums.

    # Verify checksums and exclude files
    $ wp core verify-checksums --exclude="readme.html"
    Success: WordPress installation verifies against checksums.',
        'name' => 'verify-checksums',
        'params' => array(
            array(
                'name' => 'include-root',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
            array(
                'desc' => 'Verify checksums against a specific version of WordPress.',
                'name' => 'version',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
            array(
                'desc' => 'Verify checksums against a specific locale of WordPress.',
                'name' => 'locale',
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
            array(
                'desc' => 'Exclude specific files from the checksum verification. Provide a comma-separated list of file paths.',
                'name' => 'exclude',
                'optional' => true,
                'repeating' => false,
                'type' => 'assoc',
                'value_optional' => false,
            ),
        ),
        'shortdesc' => 'Verifies WordPress files against WordPress.org\'s checksums.',
        'synopsis' => '[--include-root] [--version=<version>] [--locale=<locale>] [--insecure] [--exclude=<files>]',
    ),
    'core version' => array(
        'command' => 'core version',
        'fields' => null,
        'hook' => 'before_wp_load',
        'longdesc' => '## OPTIONS

[--extra]
: Show extended version information.

## EXAMPLES

    # Display the WordPress version
    $ wp core version
    4.5.2

    # Display WordPress version along with other information
    $ wp core version --extra
    WordPress version: 4.5.2
    Database revision: 36686
    TinyMCE version:   4.310 (4310-20160418)
    Package language:  en_US',
        'name' => 'version',
        'params' => array(
            array(
                'name' => 'extra',
                'optional' => true,
                'repeating' => false,
                'type' => 'flag',
            ),
        ),
        'shortdesc' => 'Displays the WordPress version.',
        'synopsis' => '[--extra]',
    ),
);
