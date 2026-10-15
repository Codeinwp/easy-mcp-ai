<?php























if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'core check-update'      => array(
        'route'     => 'own',
        'handler'   => 'Core_Handler',
        'method'    => 'check_update',
        'cap'       => 'manage_options',
        'network'   => 'manage_network_options',
        'write'     => false,
        'params'    => array( 'minor', 'major', 'force-check', 'field', 'fields', 'format' ),
        'preflight' => 'preflight_update_core',
    ),
    'core download'          => array( 'route' => 'refuse', 'reason' => 'Installs WordPress or converts the site.' ),
    'core install'           => array( 'route' => 'refuse', 'reason' => 'Installs WordPress or converts the site.' ),
    'core is-installed'      => array(
        'route'   => 'own',
        'handler' => 'Core_Handler',
        'method'  => 'is_installed',
        'cap'     => 'read',
        'write'   => false,
        'params'  => array( 'network' ),
        'answer'  => true,
    ),
    'core multisite-convert' => array( 'route' => 'refuse', 'reason' => 'Installs WordPress or converts the site.' ),
    'core multisite-install' => array( 'route' => 'refuse', 'reason' => 'Installs WordPress or converts the site.' ),
    'core update'            => array(
        'route'          => 'delegate',
        'adapter'        => 'Core_Adapter',
        'method'         => 'update',
        'tools'          => array( 'wp_update_core' ),
        'write'          => true,
        'params'         => array( 'minor', 'version', 'locale' ),
        'refused_params' => array(
            'zip'      => 'it installs from a file on the server or from a URL that is not wordpress.org; wp_cli_run updates WordPress only from wordpress.org',
            'force'    => 'wp_update_core offers no downgrade or reinstall',
            'insecure' => 'it turns off TLS certificate checks for a package that becomes WordPress itself',
        ),
    ),
    'core update-db'         => array(
        'route'     => 'own',
        'handler'   => 'Core_Handler',
        'method'    => 'update_db',
        'cap'       => 'manage_options',
        'network'   => 'manage_network_options',
        'write'     => true,
        'params'         => array( 'network', 'dry-run' ),
        'preflight'      => 'preflight_update_db',
        'describe'       => 'describe_update_db',
        'read_only_when' => 'is_dry_run',
    ),
    'core verify-checksums'  => array(
        'route'          => 'own',
        'handler'        => 'Core_Handler',
        'method'         => 'verify_checksums',
        'cap'            => 'manage_options',
        'network'        => 'manage_network_options',
        'write'          => false,
        'params'         => array( 'include-root', 'version', 'locale', 'exclude' ),
        'refused_params' => array(
            'insecure' => 'it turns off TLS certificate checks for the checksums the files are compared with',
        ),
    ),
    'core version'           => array(
        'route'   => 'own',
        'handler' => 'Core_Handler',
        'method'  => 'version',
        'cap'     => 'read',
        'write'   => false,
        'params'  => array( 'extra' ),
    ),
);
