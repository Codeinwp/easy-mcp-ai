<?php












if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'post-type get'  => array(
        'route'   => 'own',
        'handler' => 'Post_Type_Handler',
        'method'  => 'get',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        'params'  => array( 'post-type', 'field', 'fields', 'format' ),
    ),
    'post-type list' => array(
        'route'   => 'own',
        'handler' => 'Post_Type_Handler',
        'method'  => 'list_',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        
        'generic' => true,
        'params'  => array( 'field', 'fields', 'format' ),
    ),
);
