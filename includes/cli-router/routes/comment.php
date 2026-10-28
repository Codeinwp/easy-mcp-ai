<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_moderate = static function ( string $tool ): array {
    return array(
        'route'     => 'delegate',
        'adapter'   => 'Comment_Adapter',
        'method'    => 'moderate',
        'tools'     => array( $tool ),
        'write'     => true,
        'params'    => array( 'id' ),
        'bulk'      => 50,
        'plan'      => 'plan_moderate',
        'preflight' => 'preflight_ids',
    );
};

$emai_meta = static function ( string $method, bool $write, array $params ): array {
    $route = array(
        'route'   => 'own',
        'handler' => 'Comment_Meta_Handler',
        'method'  => $method,
        'cap'     => 'moderate_comments',
        'network' => null,
        'write'   => $write,
        'params'  => $params,
    );
    $route['preflight'] = 'preflight';
    if ( $write ) {
        $route['describe']     = 'describe';
        $route['not_captured'] = array( 'comment meta: change history\'s meta capture (easy_mcp_ai_change_log_capture_meta) is off' );
    }
    return $route;
};

return array(
    'comment approve'     => $emai_moderate( 'wp_approve_comment' ),
    'comment count'       => array(
        'route'     => 'own',
        'handler'   => 'Comment_Handler',
        'method'    => 'count',
        'cap'       => 'moderate_comments',
        'network'   => null,
        'write'     => false,
        'params'    => array( 'post-id' ),
        'preflight' => 'preflight_count',
    ),
    'comment create'      => array(
        'route'     => 'delegate',
        'adapter'   => 'Comment_Adapter',
        'method'    => 'create',
        'tools'     => array( 'wp_create_comment' ),
        'write'     => true,
        'params'    => array( 'porcelain' ),
        'generic'   => true,
        'preflight' => 'preflight_create',
    ),
    'comment delete'      => array(
        'route'     => 'delegate',
        'adapter'   => 'Comment_Adapter',
        'method'    => 'delete',
        'tools'     => array( 'wp_delete_comment' ),
        'write'     => true,
        'params'    => array( 'id', 'force' ),
        'bulk'      => 50,
        'plan'      => 'plan_delete',
        'preflight' => 'preflight_ids',
    ),
    'comment exists'      => array(
        'route'     => 'delegate',
        'adapter'   => 'Comment_Adapter',
        'method'    => 'exists',
        'tools'     => array( 'wp_get_comment' ),
        'write'     => false,
        'params'    => array( 'id' ),
        'preflight' => 'preflight_ids',
    ),
    'comment generate'    => array( 'route' => 'refuse', 'reason' => 'Dummy-content generator.' ),
    'comment get'         => array(
        'route'     => 'delegate',
        'adapter'   => 'Comment_Adapter',
        'method'    => 'get',
        'tools'     => array( 'wp_get_comment' ),
        'write'     => false,
        'params'    => array( 'id', 'field', 'fields', 'format' ),
        'preflight' => 'preflight_ids',
    ),
    'comment list'        => array(
        'route'     => 'delegate',
        'adapter'   => 'Comment_Adapter',
        'method'    => 'list_',
        'tools'     => array( 'wp_list_comments' ),
        'write'     => false,
        'params'    => array( 'field', 'fields', 'format' ),
        'generic'   => true,
        'preflight' => 'preflight_list',
    ),
    'comment meta add'    => $emai_meta( 'add', true, array( 'id', 'key', 'value', 'format' ) ),
    'comment meta delete' => $emai_meta( 'delete', true, array( 'id', 'key', 'value', 'all' ) ),
    'comment meta get'    => $emai_meta( 'get', false, array( 'id', 'key', 'single', 'format' ) ),
    'comment meta list'   => $emai_meta( 'list_', false, array( 'id', 'keys', 'fields', 'format', 'orderby', 'order', 'unserialize' ) ),
    'comment meta patch'  => $emai_meta( 'patch', true, array( 'action', 'id', 'key', 'key-path', 'value', 'format' ) ),
    'comment meta pluck'  => $emai_meta( 'pluck', false, array( 'id', 'key', 'key-path', 'format' ) ),
    'comment meta update' => $emai_meta( 'update', true, array( 'id', 'key', 'value', 'format' ) ),
    'comment recount'     => array(
        'route'        => 'own',
        'handler'      => 'Comment_Handler',
        'method'       => 'recount',
        'cap'          => 'moderate_comments',
        'network'      => null,
        'write'        => true,
        'params'       => array( 'id' ),
        'bulk'         => 50,
        'preflight'    => 'preflight_recount',
        'describe'     => 'describe_recount',
        'not_captured' => array( 'posts.comment_count is written by a direct database update, which change history records only while its database capture is on' ),
    ),
    'comment spam'        => $emai_moderate( 'wp_spam_comment' ),
    'comment status'      => array(
        'route'     => 'delegate',
        'adapter'   => 'Comment_Adapter',
        'method'    => 'status',
        'tools'     => array( 'wp_get_comment' ),
        'write'     => false,
        'params'    => array( 'id' ),
        'preflight' => 'preflight_ids',
    ),
    'comment trash'       => $emai_moderate( 'wp_trash_comment' ),
    'comment unapprove'   => $emai_moderate( 'wp_unapprove_comment' ),
    'comment unspam'      => $emai_moderate( 'wp_unspam_comment' ),
    'comment untrash'     => $emai_moderate( 'wp_untrash_comment' ),
    'comment update'      => array(
        'route'     => 'delegate',
        'adapter'   => 'Comment_Adapter',
        'method'    => 'update',
        'tools'     => array( 'wp_update_comment' ),
        'write'     => true,
        'params'    => array( 'id' ),
        'generic'   => true,
        'bulk'      => 50,
        'plan'      => 'plan_update',
        'preflight' => 'preflight_update',
    ),
);
