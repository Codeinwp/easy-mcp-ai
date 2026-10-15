<?php





























if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_insecure = 'it retries a failed download without checking the TLS certificate, and wp_cli_run reaches wordpress.org only over verified HTTPS';
$emai_semver   = 'wp_update_plugin cannot limit an update to a minor or patch release yet; pass --version=<version> with the release to install';

$emai_plugin_own = static function ( string $method, bool $write, array $params, array $extra = array() ): array {
    return array_merge(
        array(
            'route'   => 'own',
            'handler' => 'Plugin_Handler',
            'method'  => $method,
            'cap'     => 'activate_plugins',
            'network' => null,
            'write'   => $write,
            'params'  => $params,
        ),
        $extra
    );
};

$emai_plugin_delegate = static function ( string $method, array $tools, bool $write, array $params, array $extra = array() ): array {
    return array_merge(
        array(
            'route'   => 'delegate',
            'adapter' => 'Plugin_Adapter',
            'method'  => $method,
            'tools'   => $tools,
            'write'   => $write,
            'params'  => $params,
        ),
        $extra
    );
};

return array(
    'plugin activate'             => $emai_plugin_delegate(
        'activate',
        array( 'wp_activate_plugin', 'wp_deactivate_plugin' ),
        true,
        array( 'plugin', 'all', 'exclude', 'network' ),
        array(
            'plan'      => 'plan_activate',
            'preflight' => 'preflight_activate',
            'bulk'      => 50,
        )
    ),
    'plugin auto-updates disable' => $emai_plugin_own(
        'auto_updates_disable',
        true,
        array( 'plugin', 'all', 'enabled-only' ),
        array(
            'network'   => 'manage_network_plugins',
            'preflight' => 'preflight_auto_updates',
            'describe'  => 'describe_auto_updates',
            'bulk'      => 50,
        )
    ),
    'plugin auto-updates enable'  => $emai_plugin_own(
        'auto_updates_enable',
        true,
        array( 'plugin', 'all', 'disabled-only' ),
        array(
            'network'   => 'manage_network_plugins',
            'preflight' => 'preflight_auto_updates',
            'describe'  => 'describe_auto_updates',
            'bulk'      => 50,
        )
    ),
    'plugin auto-updates status'  => $emai_plugin_own(
        'auto_updates_status',
        false,
        array( 'plugin', 'all', 'enabled-only', 'disabled-only', 'field', 'format' ),
        array( 'preflight' => 'preflight_auto_updates_read' )
    ),
    'plugin deactivate'           => $emai_plugin_delegate(
        'deactivate',
        array( 'wp_deactivate_plugin', 'wp_uninstall_plugin' ),
        true,
        array( 'plugin', 'uninstall', 'all', 'exclude', 'network' ),
        array(
            'plan'      => 'plan_deactivate',
            'preflight' => 'preflight_switch',
            'bulk'      => 50,
        )
    ),
    'plugin delete'               => $emai_plugin_delegate(
        'delete',
        array( 'wp_delete_plugin' ),
        true,
        array( 'plugin', 'all', 'exclude' ),
        array(
            'plan'      => 'plan_delete',
            'preflight' => 'preflight_named',
            'bulk'      => 5,
        )
    ),
    'plugin get'                  => $emai_plugin_own( 'get', false, array( 'plugin', 'field', 'fields', 'format' ) ),
    'plugin install'              => $emai_plugin_delegate(
        'install',
        array( 'wp_install_plugin', 'wp_activate_plugin', 'wp_deactivate_plugin' ),
        true,
        array( 'plugin|zip|url', 'version', 'force', 'ignore-requirements', 'activate', 'activate-network' ),
        array(
            'refused_params' => array( 'insecure' => $emai_insecure ),
            'plan'           => 'plan_install',
            'preflight'      => 'preflight_install',
            'bulk'           => 20,
        )
    ),
    'plugin is-active'            => $emai_plugin_own( 'is_active', false, array( 'plugin', 'network' ), array( 'answer' => true ) ),
    'plugin is-installed'         => $emai_plugin_own( 'is_installed', false, array( 'plugin' ), array( 'answer' => true ) ),
    'plugin list'                 => $emai_plugin_delegate(
        'list_',
        array( 'wp_list_plugins' ),
        false,
        array( 'field', 'fields', 'format', 'status', 'skip-update-check', 'recently-active' ),
        array( 'generic' => true )
    ),
    'plugin path'                 => $emai_plugin_own( 'path', false, array( 'plugin', 'dir' ) ),
    'plugin search'               => $emai_plugin_own(
        'search',
        false,
        array( 'search', 'page', 'per-page', 'field', 'fields', 'format' ),
        array( 'preflight' => 'preflight_search' )
    ),
    'plugin status'               => $emai_plugin_own( 'status', false, array( 'plugin' ) ),
    'plugin toggle'               => $emai_plugin_delegate(
        'toggle',
        array( 'wp_activate_plugin', 'wp_deactivate_plugin' ),
        true,
        array( 'plugin', 'network' ),
        array(
            'plan'      => 'plan_toggle',
            'preflight' => 'preflight_switch',
            'bulk'      => 50,
        )
    ),
    'plugin uninstall'            => $emai_plugin_delegate(
        'uninstall',
        array( 'wp_uninstall_plugin' ),
        true,
        array( 'plugin', 'deactivate', 'skip-delete', 'all', 'exclude' ),
        array(
            'plan'      => 'plan_uninstall',
            'preflight' => 'preflight_named',
            'bulk'      => 5,
        )
    ),
    'plugin update'               => $emai_plugin_delegate(
        'update',
        array( 'wp_list_plugins', 'wp_update_plugin' ),
        true,
        array( 'plugin', 'all', 'exclude', 'format', 'version', 'dry-run' ),
        array(
            'refused_params' => array(
                'minor'    => $emai_semver,
                'patch'    => $emai_semver,
                'insecure' => $emai_insecure,
            ),
            'plan'           => 'plan_update',
            'preflight'      => 'preflight_update',
            'bulk'           => 6,
        )
    ),
    'plugin verify-checksums'     => $emai_plugin_own(
        'verify_checksums',
        false,
        array( 'plugin', 'all', 'strict', 'version', 'format', 'exclude' ),
        array(
            'network'        => 'manage_network_plugins',
            'refused_params' => array( 'insecure' => $emai_insecure ),
            'bulk'           => 5,
        )
    ),
);
