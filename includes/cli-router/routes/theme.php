<?php





























if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_theme_auto_updates = static function ( string $method, bool $write, array $params ): array {
    $route = array(
        'route'     => 'own',
        'handler'   => 'Theme_Auto_Updates_Handler',
        'method'    => $method,
        'cap'       => 'manage_options',
        'network'   => 'manage_network_themes',
        'write'     => $write,
        'params'    => $params,
        'preflight' => 'preflight',
        'bulk'      => 50,
    );
    if ( $write ) {
        $route['describe'] = 'describe';
    }
    return $route;
};

$emai_theme_read = static function ( string $method, array $params, array $extra = array() ): array {
    return array_merge(
        array(
            'route'   => 'own',
            'handler' => 'Theme_Handler',
            'method'  => $method,
            'cap'     => 'switch_themes',
            'network' => null,
            'write'   => false,
            'params'  => $params,
        ),
        $extra
    );
};

$emai_theme_allowed = static function ( string $method, array $params, array $refused = array() ): array {
    $route = array(
        'route'     => 'own',
        'handler'   => 'Theme_Handler',
        'method'    => $method,
        'cap'       => 'manage_options',
        'network'   => 'manage_sites',
        'write'     => true,
        'params'    => $params,
        'preflight' => 'preflight_allowed',
        'describe'  => 'describe_allowed',
    );
    if ( $refused ) {
        $route['refused_params'] = $refused;
    }
    return $route;
};

$emai_theme_mod = static function ( string $method, bool $write, array $params ): array {
    $route = array(
        'route'   => 'own',
        'handler' => 'Theme_Mod_Handler',
        'method'  => $method,
        'cap'     => 'edit_theme_options',
        'network' => null,
        'write'   => $write,
        'params'  => $params,
    );
    if ( $write ) {
        $route['describe'] = 'describe_remove';
        $route['bulk']     = 50;
    }
    return $route;
};

return array(
    'theme activate'             => array(
        'route'   => 'delegate',
        'adapter' => 'Theme_Adapter',
        'method'  => 'activate',
        'tools'   => array( 'wp_switch_theme' ),
        'write'   => true,
        'params'  => array( 'theme' ),
    ),
    'theme auto-updates disable' => $emai_theme_auto_updates( 'disable', true, array( 'theme', 'all', 'enabled-only' ) ),
    'theme auto-updates enable'  => $emai_theme_auto_updates( 'enable', true, array( 'theme', 'all', 'disabled-only' ) ),
    'theme auto-updates status'  => $emai_theme_auto_updates( 'status', false, array( 'theme', 'all', 'enabled-only', 'disabled-only', 'field', 'format' ) ),
    'theme delete'               => array(
        'route'          => 'delegate',
        'adapter'        => 'Theme_Adapter',
        'method'         => 'delete',
        'tools'          => array( 'wp_delete_theme' ),
        'write'          => true,
        'params'         => array( 'theme', 'all' ),
        'refused_params' => array(
            'force' => 'its only effect is deleting the active theme or the parent of the active theme, which WordPress never offers (Appearance › Themes has no Delete for them, plan Q15). Activate another theme with `theme activate` first, then delete without --force',
        ),
        'bulk'           => 5,
        'plan'           => 'plan_delete',
        'preflight'      => 'preflight_batch',
    ),
    'theme disable'              => $emai_theme_allowed( 'disable', array( 'theme', 'network' ) ),
    'theme enable'               => array_merge(
        $emai_theme_allowed( 'enable', array( 'theme', 'network', 'activate' ) ),
        array(
            'split'       => 'split_activate',
            'split_tools' => array( 'wp_cli_run', 'wp_switch_theme' ),
        )
    ),
    'theme get'                  => $emai_theme_read( 'get', array( 'theme', 'field', 'fields', 'format' ) ),
    'theme install'              => array(
        'route'          => 'delegate',
        'adapter'        => 'Theme_Adapter',
        'method'         => 'install',
        'tools'          => array( 'wp_install_theme', 'wp_switch_theme' ),
        'write'          => true,
        
        'params'         => array( 'theme|zip|url', 'version', 'force', 'ignore-requirements', 'activate' ),
        'refused_params' => array(
            'insecure' => 'wp_cli_run never downloads without certificate checks',
        ),
        
        'bulk'           => 10,
        'plan'           => 'plan_install',
        'preflight'      => 'preflight_install',
    ),
    'theme is-active'            => $emai_theme_read(
        'is_active',
        array( 'theme' ),
        array(
            'answer'    => true,
            'preflight' => 'preflight_name',
        )
    ),
    'theme is-installed'         => $emai_theme_read(
        'is_installed',
        array( 'theme' ),
        array(
            'answer'    => true,
            'preflight' => 'preflight_name',
        )
    ),
    'theme list'                 => array(
        'route'   => 'delegate',
        'adapter' => 'Theme_Adapter',
        'method'  => 'list_',
        'tools'   => array( 'wp_list_themes' ),
        'write'   => false,
        'params'  => array( 'field', 'fields', 'format', 'status', 'skip-update-check' ),
        'generic' => true,
    ),
    'theme mod get'              => $emai_theme_mod( 'get', false, array( 'mod', 'field', 'all', 'format' ) ),
    'theme mod list'             => $emai_theme_mod( 'list_', false, array( 'field', 'format' ) ),
    'theme mod remove'           => array_merge(
        $emai_theme_mod( 'remove', true, array( 'mod', 'all' ) ),
        array(
            'delegate_when'   => 'delegates_remove',
            'adapter'         => 'Theme_Adapter',
            'delegate_method' => 'mod_remove',
            'delegate_tools'  => array( 'wp_delete_theme_mod' ),
            'delegate_plan'   => 'plan_mod_remove',
        )
    ),
    'theme mod set'              => array(
        'route'   => 'delegate',
        'adapter' => 'Theme_Adapter',
        'method'  => 'mod_set',
        'tools'   => array( 'wp_update_theme_mod' ),
        'write'   => true,
        'params'  => array( 'mod', 'value' ),
    ),
    'theme path'                 => $emai_theme_read( 'path', array( 'theme', 'dir' ) ),
    'theme search'               => array(
        'route'     => 'own',
        'handler'   => 'Theme_Handler',
        'method'    => 'search',
        'cap'       => 'manage_options',
        'network'   => null,
        'write'     => false,
        'params'    => array( 'search', 'page', 'per-page', 'field', 'fields', 'format' ),
        'preflight' => 'preflight_search',
    ),
    'theme status'               => $emai_theme_read( 'status', array( 'theme' ) ),
    'theme update'               => array(
        'route'          => 'delegate',
        'adapter'        => 'Theme_Adapter',
        'method'         => 'update',
        'tools'          => array( 'wp_list_themes', 'wp_update_theme' ),
        'write'          => true,
        'params'         => array( 'theme', 'all', 'exclude', 'format', 'version', 'dry-run' ),
        'refused_params' => array(
            'minor'    => 'wp_update_theme cannot limit an update to minor releases yet. Pass the version you want with --version, or update without --minor',
            'patch'    => 'wp_update_theme cannot limit an update to patch releases yet. Pass the version you want with --version, or update without --patch',
            'insecure' => 'wp_cli_run never downloads without certificate checks',
        ),
        
        'bulk'           => 6,
        'plan'           => 'plan_update',
        'preflight'      => 'preflight_batch',
    ),
);
