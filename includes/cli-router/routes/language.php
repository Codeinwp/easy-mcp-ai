<?php




























if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_language_route = static function ( string $method, string $cap, bool $write, array $params, array $extra = array() ): array {
    $route = array(
        'route'   => 'own',
        'handler' => 'Language_Handler',
        'method'  => $method,
        'cap'     => $cap,
        'write'   => $write,
        'params'  => $params,
    );
    if ( $write ) {
        $route['network'] = 'manage_network_options';
    }
    return array_merge( $route, $extra );
};

$emai_language_objects = array();
foreach ( array(
    'plugin' => 'activate_plugins',
    'theme'  => 'switch_themes',
) as $emai_noun => $emai_read_cap ) {
    $emai_language_objects[ "language {$emai_noun} install" ]      = $emai_language_route(
        'object_install',
        'manage_options',
        true,
        array( $emai_noun, 'all', 'language', 'format' ),
        array(
            'preflight' => 'preflight_object_install',
            'describe'  => 'describe_object_install',
        )
    );
    $emai_language_objects[ "language {$emai_noun} is-installed" ] = $emai_language_route( 'object_is_installed', $emai_read_cap, false, array( $emai_noun, 'language' ), array( 'answer' => true ) );
    $emai_language_objects[ "language {$emai_noun} list" ]         = $emai_language_route(
        'object_list',
        $emai_read_cap,
        false,
        array( $emai_noun, 'all', 'field', 'fields', 'format' ),
        array(
            'generic'   => true,
            'preflight' => 'preflight_object_list',
        )
    );
    $emai_language_objects[ "language {$emai_noun} uninstall" ]    = $emai_language_route(
        'object_uninstall',
        'manage_options',
        true,
        array( $emai_noun, 'all', 'language', 'format' ),
        array(
            'preflight' => 'preflight_object_uninstall',
            'describe'  => 'describe_object_uninstall',
        )
    );
    $emai_language_objects[ "language {$emai_noun} update" ]       = $emai_language_route(
        'object_update',
        'manage_options',
        true,
        array( $emai_noun, 'all', 'dry-run' ),
        array(
            'preflight'      => 'preflight_object_update',
            'describe'       => 'describe_object_update',
            'read_only_when' => 'is_dry_run',
        )
    );
}

return array_merge(
    array(
        'language core activate'     => array(
            'route'   => 'delegate',
            'adapter' => 'Language_Adapter',
            'method'  => 'core_activate',
            'tools'   => array( 'wp_update_site_settings' ),
            'write'   => true,
            'params'  => array( 'language' ),
        ),
        'language core install'      => $emai_language_route(
            'core_install',
            'manage_options',
            true,
            array( 'language', 'activate' ),
            array(
                'preflight'   => 'preflight_core_install',
                'describe'    => 'describe_core_install',
                'bulk'        => 20,
                
                
                
                'split'       => 'split_activate',
                'split_tools' => array( 'wp_cli_run', 'wp_update_site_settings' ),
            )
        ),
        'language core is-installed' => $emai_language_route( 'core_is_installed', 'manage_options', false, array( 'language' ), array( 'answer' => true ) ),
        'language core list'         => $emai_language_route( 'core_list', 'manage_options', false, array( 'field', 'fields', 'format' ), array( 'generic' => true ) ),
        'language core uninstall'    => $emai_language_route(
            'core_uninstall',
            'manage_options',
            true,
            array( 'language' ),
            array(
                'preflight' => 'preflight_core_uninstall',
                'describe'  => 'describe_core_uninstall',
                'bulk'      => 50,
            )
        ),
        'language core update'       => $emai_language_route(
            'core_update',
            'manage_options',
            true,
            array( 'dry-run' ),
            array(
                'preflight'      => 'preflight_core_update',
                'describe'       => 'describe_core_update',
                'read_only_when' => 'is_dry_run',
            )
        ),
    ),
    $emai_language_objects
);
