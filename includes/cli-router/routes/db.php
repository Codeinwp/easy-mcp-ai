<?php

















if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_db_refused = 'Raw SQL, whole-database dumps and restores, or destroying tables.';

$emai_db = static function ( string $method, bool $write, array $params, array $extra = array() ): array {
    return array_merge(
        array(
            'route'   => 'own',
            'handler' => 'Db_Handler',
            'method'  => $method,
            'cap'     => 'manage_options',
            'network' => null,
            'write'   => $write,
            'params'  => $params,
        ),
        $extra
    );
};

$emai_db_maintenance = static function ( string $method, bool $write ) use ( $emai_db ): array {
    $extra = array(
        'network'        => 'manage_network_options',
        
        
        'generic'        => true,
        'refused_params' => array(
            'dbuser'   => 'wp_cli_run does not run the mysqlcheck binary; it checks the tables through the database connection WordPress already has, so there is no other database user to log in as',
            'dbpass'   => 'wp_cli_run does not run the mysqlcheck binary; it checks the tables through the database connection WordPress already has, so there is no password to pass',
            'defaults' => 'wp_cli_run does not run the mysqlcheck binary, so there are no MySQL option files to load',
        ),
        'preflight'      => 'preflight_maintenance',
    );
    if ( $write ) {
        $extra['describe'] = 'describe_maintenance';
    }
    return $emai_db( $method, $write, array(), $extra );
};

return array(
    'db check'    => $emai_db_maintenance( 'check', false ),
    'db clean'    => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db cli'      => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db columns'  => $emai_db( 'columns', false, array( 'table', 'format' ), array( 'preflight' => 'preflight_columns' ) ),
    'db create'   => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db drop'     => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db export'   => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db import'   => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db optimize' => $emai_db_maintenance( 'optimize', true ),
    'db prefix'   => $emai_db( 'prefix', false, array() ),
    'db query'    => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db repair'   => $emai_db_maintenance( 'repair', true ),
    'db reset'    => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db search'   => array( 'route' => 'refuse', 'reason' => $emai_db_refused ),
    'db size'     => $emai_db( 'size', false, array( 'size_format', 'tables', 'human-readable', 'format', 'scope', 'network', 'decimals', 'all-tables-with-prefix', 'all-tables', 'order', 'orderby' ), array( 'preflight' => 'preflight_size' ) ),
    'db tables'   => $emai_db( 'tables', false, array( 'table', 'scope', 'network', 'all-tables-with-prefix', 'all-tables', 'format' ), array( 'preflight' => 'preflight_tables' ) ),
);
