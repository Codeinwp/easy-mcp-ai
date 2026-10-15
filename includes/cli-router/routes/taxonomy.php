<?php











if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'taxonomy get'  => array(
        'route'   => 'own',
        'handler' => 'Taxonomy_Handler',
        'method'  => 'get',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        'params'  => array( 'taxonomy', 'field', 'fields', 'format' ),
    ),
    'taxonomy list' => array(
        'route'   => 'own',
        'handler' => 'Taxonomy_Handler',
        'method'  => 'list_',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        
        
        'generic' => true,
        'params'  => array( 'field', 'fields', 'format' ),
    ),
);
