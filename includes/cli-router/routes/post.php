<?php































if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$easy_mcp_ai_post_fields_refused = array(
    'post_content_filtered' => 'the REST API the tools use does not write post_content_filtered',
    'to_ping'               => 'the REST API the tools use does not write to_ping, and WordPress would ping each URL in it',
    'pinged'                => 'the REST API the tools use does not write pinged',
    'post_modified'         => 'WordPress sets post_modified itself on every save and ignores the value (wp_insert_post()), so WP-CLI drops it',
    'post_modified_gmt'     => 'WordPress sets post_modified_gmt itself on every save and ignores the value (wp_insert_post()), so WP-CLI drops it',
    'post_mime_type'        => 'the REST API the tools use does not write post_mime_type',
    'guid'                  => 'the REST API the tools use does not write guid',
    'tax_input'             => 'WP-CLI passes it to wp_insert_post() as a string, which drops it; use --post_category or --tags_input, or `post term set`',
);

return array(
    'post create'                => array(
        'route'          => 'delegate',
        'adapter'        => 'Post_Adapter',
        'method'         => 'create',
        'tools'          => array( 'wp_create_post', 'wp_create_page', 'wp_create_cpt_item' ),
        'write'          => true,
        'generic'        => true,
        'params'         => array( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'post_parent', 'menu_order', 'post_category', 'tags_input', 'meta_input', 'porcelain' ),
        'refused_params' => array_merge(
            $easy_mcp_ai_post_fields_refused,
            array(
                'file'      => 'it reads the post content from a file on the server (plan §4.1); pass it with --post_content',
                'from-post' => 'it copies every meta key of another post, protected ones included, and no tool duplicates a post',
                'edit'      => 'it opens an interactive editor (plan Q17); pass the content with --post_content',
            )
        ),
    ),
    'post delete'                => array(
        'route'   => 'delegate',
        'adapter' => 'Post_Adapter',
        'method'  => 'delete',
        'tools'   => array( 'wp_delete_post', 'wp_delete_page', 'wp_delete_media', 'wp_delete_revision', 'wp_delete_cpt_item' ),
        'write'   => true,
        'params'    => array( 'id', 'force', 'defer-term-counting' ),
        'plan'      => 'plan_delete',
        
        
        'bulk'      => 999,
        'preflight' => 'preflight_delete',
    ),
    'post edit'                  => array( 'route' => 'refuse', 'reason' => 'Needs an interactive editor.' ),
    'post exists'                => array(
        'route'   => 'own',
        'handler' => 'Post_Handler',
        'method'  => 'exists',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        'answer'  => true,
        'params'  => array( 'id' ),
    ),
    'post generate'              => array( 'route' => 'refuse', 'reason' => 'Dummy-content generator.' ),
    'post get'                   => array(
        'route'   => 'delegate',
        'adapter' => 'Post_Adapter',
        'method'  => 'get',
        'tools'   => array( 'wp_get_post_full' ),
        'write'   => false,
        'params'  => array( 'id', 'field', 'fields', 'format' ),
    ),
    'post list'                  => array(
        'route'   => 'delegate',
        'adapter' => 'Post_Adapter',
        'method'  => 'list_',
        'tools'   => array( 'wp_list_posts', 'wp_list_pages', 'wp_list_media', 'wp_list_cpt_items' ),
        'write'   => false,
        'generic' => true,
        'params'  => array( 'field', 'fields', 'format' ),
    ),
    'post meta add'              => array(
        'route'     => 'own',
        'handler'   => 'Post_Meta_Handler',
        'method'    => 'add',
        'cap'       => 'edit_posts',
        'network'   => null,
        'write'     => true,
        'describe'  => 'describe',
        'preflight' => 'preflight_id',
        'params'    => array( 'id', 'key', 'value', 'format' ),
    ),
    'post meta clean-duplicates' => array(
        'route'     => 'own',
        'handler'   => 'Post_Meta_Handler',
        'method'    => 'clean_duplicates',
        'cap'       => 'edit_posts',
        'network'   => null,
        'write'     => true,
        'describe'  => 'describe',
        'preflight' => 'preflight_id',
        'params'    => array( 'id', 'key' ),
    ),
    'post meta delete'           => array(
        'route'     => 'delegate',
        'adapter'   => 'Post_Meta_Adapter',
        'method'    => 'delete',
        'tools'     => array( 'wp_delete_post_meta' ),
        'write'     => true,
        'preflight' => 'preflight_id',
        'params'    => array( 'id', 'key', 'value', 'all' ),
    ),
    'post meta get'              => array(
        'route'   => 'delegate',
        'adapter' => 'Post_Meta_Adapter',
        'method'  => 'get',
        'tools'   => array( 'wp_get_post_full' ),
        'write'   => false,
        'params'  => array( 'id', 'key', 'single', 'format' ),
    ),
    'post meta list'             => array(
        'route'   => 'delegate',
        'adapter' => 'Post_Meta_Adapter',
        'method'  => 'list_',
        'tools'   => array( 'wp_get_post_full' ),
        'write'   => false,
        'params'  => array( 'id', 'keys', 'fields', 'format', 'orderby', 'order', 'unserialize' ),
    ),
    'post meta patch'            => array(
        'route'              => 'own',
        'handler'            => 'Post_Meta_Handler',
        'method'             => 'patch',
        'cap'                => 'edit_posts',
        'network'            => null,
        'write'              => true,
        'describe'           => 'describe',
        'preflight'          => 'preflight_id',
        'params'             => array( 'action', 'id', 'key', 'key-path', 'value', 'format' ),
        'delegate_when'      => 'is_rest_key',
        'adapter'            => 'Post_Meta_Adapter',
        'delegate_method'    => 'patch',
        'delegate_tools'     => array( 'wp_get_post_full', 'wp_update_post_meta' ),
        'delegate_plan'      => 'plan_patch',
        'delegate_preflight' => 'preflight_id',
    ),
    'post meta pluck'            => array(
        'route'   => 'delegate',
        'adapter' => 'Post_Meta_Adapter',
        'method'  => 'pluck',
        'tools'   => array( 'wp_get_post_full' ),
        'write'   => false,
        'params'  => array( 'id', 'key', 'key-path', 'format' ),
    ),
    'post meta update'           => array(
        'route'              => 'own',
        'handler'            => 'Post_Meta_Handler',
        'method'             => 'update',
        'cap'                => 'edit_posts',
        'network'            => null,
        'write'              => true,
        'describe'           => 'describe',
        'preflight'          => 'preflight_id',
        'params'             => array( 'id', 'key', 'value', 'format' ),
        'delegate_when'      => 'is_rest_key',
        'adapter'            => 'Post_Meta_Adapter',
        'delegate_method'    => 'update',
        'delegate_tools'     => array( 'wp_get_post_full', 'wp_update_post_meta' ),
        'delegate_preflight' => 'preflight_id',
    ),
    'post term add'              => array(
        'route'     => 'delegate',
        'adapter'   => 'Post_Term_Adapter',
        'method'    => 'add',
        'tools'     => array( 'wp_add_post_terms' ),
        'write'     => true,
        'plan'      => 'plan_add',
        'preflight' => 'preflight_ids',
        'params'    => array( 'id', 'taxonomy', 'term', 'by' ),
    ),
    'post term list'             => array(
        'route'   => 'delegate',
        'adapter' => 'Post_Term_Adapter',
        'method'  => 'list_',
        'tools'   => array( 'wp_get_post_full' ),
        'write'   => false,
        'params'  => array( 'id', 'taxonomy', 'field', 'fields', 'format' ),
    ),
    'post term remove'           => array(
        'route'     => 'own',
        'handler'   => 'Post_Term_Handler',
        'method'    => 'remove',
        'cap'       => 'edit_posts',
        'network'   => null,
        'write'     => true,
        'describe'  => 'describe',
        'preflight' => 'preflight_ids',
        'params'    => array( 'id', 'taxonomy', 'term', 'by', 'all' ),
    ),
    'post term set'              => array(
        'route'     => 'delegate',
        'adapter'   => 'Post_Term_Adapter',
        'method'    => 'set',
        'tools'     => array( 'wp_add_post_terms' ),
        'write'     => true,
        'plan'      => 'plan_set',
        'preflight' => 'preflight_ids',
        'params'    => array( 'id', 'taxonomy', 'term', 'by' ),
    ),
    'post update'                => array(
        'route'          => 'delegate',
        'adapter'        => 'Post_Adapter',
        'method'         => 'update',
        'tools'          => array( 'wp_update_post', 'wp_update_page', 'wp_update_cpt_item' ),
        'write'          => true,
        'generic'        => true,
        
        
        
        'params'         => array( 'id', 'file', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'post_parent', 'menu_order', 'post_category', 'tags_input', 'meta_input', 'defer-term-counting' ),
        'refused_params' => $easy_mcp_ai_post_fields_refused,
        'plan'           => 'plan_update',
        'bulk'           => 50,
        'preflight'      => 'preflight_update',
    ),
    'post url-to-id'             => array(
        'route'   => 'own',
        'handler' => 'Post_Handler',
        'method'  => 'url_to_id',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        'params'  => array( 'url' ),
    ),
);
