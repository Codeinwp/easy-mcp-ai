<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'widget add'        => array(
        'route'   => 'delegate',
        'adapter' => 'Widget_Adapter',
        'method'  => 'add',
        'tools'   => array( 'wp_create_widget' ),
        'write'   => true,
        'generic' => true,
        'params'  => array( 'name', 'sidebar-id', 'position' ),
    ),
    'widget deactivate' => array(
        'route'   => 'delegate',
        'adapter' => 'Widget_Adapter',
        'method'  => 'deactivate',
        'tools'   => array( 'wp_delete_widget' ),
        'write'   => true,
        'bulk'    => 50,
        'plan'    => 'plan_deactivate',
        'params'  => array( 'widget-id' ),
    ),
    'widget delete'     => array(
        'route'   => 'delegate',
        'adapter' => 'Widget_Adapter',
        'method'  => 'delete',
        'tools'   => array( 'wp_delete_widget' ),
        'write'   => true,
        'bulk'    => 50,
        'plan'    => 'plan_delete',
        'params'  => array( 'widget-id' ),
    ),
    'widget list'       => array(
        'route'   => 'delegate',
        'adapter' => 'Widget_Adapter',
        'method'  => 'list_widgets',
        'tools'   => array( 'wp_list_widgets' ),
        'write'   => false,
        'params'  => array( 'sidebar-id', 'fields', 'format' ),
    ),
    'widget move'       => array(
        'route'   => 'delegate',
        'adapter' => 'Widget_Adapter',
        'method'  => 'move',
        'tools'   => array( 'wp_update_widget' ),
        'write'   => true,
        'params'  => array( 'widget-id', 'position', 'sidebar-id' ),
    ),
    'widget reset'      => array(
        'route'   => 'delegate',
        'adapter' => 'Widget_Adapter',
        'method'  => 'reset',
        'tools'   => array( 'wp_delete_widget' ),
        'write'   => true,
        'bulk'    => 50,
        'plan'    => 'plan_reset',
        'params'  => array( 'sidebar-id', 'all' ),
    ),
    'widget update'     => array(
        'route'   => 'delegate',
        'adapter' => 'Widget_Adapter',
        'method'  => 'update',
        'tools'   => array( 'wp_update_widget' ),
        'write'   => true,
        'generic' => true,
        'params'  => array( 'widget-id' ),
    ),
);
