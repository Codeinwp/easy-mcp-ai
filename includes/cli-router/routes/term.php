<?php










































if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'term create'      => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Adapter',
        'method'  => 'create',
        'tools'   => array( 'wp_create_term' ),
        'write'   => true,
        'params'  => array( 'taxonomy', 'term', 'slug', 'description', 'parent', 'porcelain' ),
    ),
    'term delete'      => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Adapter',
        'method'  => 'delete',
        'tools'   => array( 'wp_delete_term' ),
        'write'   => true,
        'params'    => array( 'taxonomy', 'term', 'by' ),
        'bulk'      => 50,
        'plan'      => 'plan_delete',
        'preflight' => 'preflight_delete',
    ),
    'term generate'    => array( 'route' => 'refuse', 'reason' => 'Dummy-content generator.' ),
    'term get'         => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Adapter',
        'method'  => 'get',
        'tools'   => array( 'wp_get_term' ),
        'write'   => false,
        'params'  => array( 'taxonomy', 'term', 'by', 'field', 'fields', 'format' ),
    ),
    'term list'        => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Adapter',
        'method'  => 'list_',
        'tools'   => array( 'wp_list_terms', 'wp_get_term' ),
        'write'   => false,
        'params'  => array( 'taxonomy', 'field', 'fields', 'format' ),
        
        
        'generic' => true,
    ),
    'term meta add'    => array(
        'route'        => 'own',
        'handler'      => 'Term_Meta_Handler',
        'method'       => 'add',
        'cap'          => 'manage_options',
        'network'      => null,
        'write'        => true,
        'params'       => array( 'id', 'key', 'value', 'format' ),
        'preflight'    => 'preflight_id',
        'describe'     => 'describe',
        'not_captured' => array( 'Term meta is recorded by change history only while its meta capture is on (Settings, Change history; on by default).' ),
    ),
    'term meta delete' => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Meta_Adapter',
        'method'  => 'delete',
        'tools'     => array( 'wp_delete_term_meta', 'wp_get_term_meta' ),
        'write'     => true,
        'params'    => array( 'id', 'key', 'value', 'all' ),
        'bulk'      => 50,
        'preflight' => 'preflight_id',
    ),
    'term meta get'    => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Meta_Adapter',
        'method'  => 'get',
        'tools'   => array( 'wp_get_term_meta' ),
        'write'   => false,
        'params'  => array( 'id', 'key', 'single', 'format' ),
    ),
    'term meta list'   => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Meta_Adapter',
        'method'  => 'list_',
        'tools'   => array( 'wp_get_term_meta' ),
        'write'   => false,
        'params'  => array( 'id', 'keys', 'fields', 'format', 'orderby', 'order', 'unserialize' ),
    ),
    'term meta patch'  => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Meta_Adapter',
        'method'    => 'patch',
        'tools'     => array( 'wp_get_term_meta', 'wp_update_term_meta' ),
        'write'     => true,
        'params'    => array( 'action', 'id', 'key', 'key-path', 'value', 'format' ),
        'plan'      => 'plan_patch',
        'preflight' => 'preflight_id',
    ),
    'term meta pluck'  => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Meta_Adapter',
        'method'  => 'pluck',
        'tools'   => array( 'wp_get_term_meta' ),
        'write'   => false,
        'params'  => array( 'id', 'key', 'key-path', 'format' ),
    ),
    'term meta update' => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Meta_Adapter',
        'method'    => 'update',
        'tools'     => array( 'wp_get_term_meta', 'wp_update_term_meta' ),
        'write'     => true,
        'params'    => array( 'id', 'key', 'value', 'format' ),
        'preflight' => 'preflight_id',
    ),
    'term migrate'     => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Adapter',
        'method'  => 'migrate',
        'tools'     => array( 'wp_create_term', 'wp_add_post_terms', 'wp_delete_term' ),
        'write'     => true,
        'params'    => array( 'term', 'by', 'from', 'to' ),
        'bulk'      => 50,
        'preflight' => 'preflight_migrate',
    ),
    'term recount'     => array(
        'route'        => 'own',
        'handler'      => 'Term_Handler',
        'method'       => 'recount',
        'cap'          => 'manage_options',
        'network'      => null,
        'write'        => true,
        'params'       => array( 'taxonomy' ),
        'bulk'         => 50,
        'preflight'    => 'preflight_recount',
        'describe'     => 'describe_recount',
        'not_captured' => array( 'Term counts are rewritten by wp_update_term_count() with raw SQL, which change history records only while its database capture is on.' ),
    ),
    'term update'      => array(
        'route'   => 'delegate',
        'adapter' => 'Term_Adapter',
        'method'    => 'update',
        'tools'     => array( 'wp_update_term' ),
        'write'     => true,
        'params'    => array( 'taxonomy', 'term', 'by', 'name', 'slug', 'description', 'parent' ),
        'preflight' => 'preflight_update',
    ),
);
