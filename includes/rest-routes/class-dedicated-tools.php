<?php






namespace Easy_MCP_AI\Rest_Routes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







final class Dedicated_Tools {

    



    const MAP = array(
        'GET /wp/v2/posts'                           => 'wp_list_posts',
        'GET /wp/v2/posts/{id}'                      => 'wp_get_post',
        'POST /wp/v2/posts'                          => 'wp_create_post',
        'WRITE /wp/v2/posts/{id}'                    => 'wp_update_post',
        'DELETE /wp/v2/posts/{id}'                   => 'wp_delete_post',
        'GET /wp/v2/posts/{parent}/revisions'        => 'wp_list_revisions',
        'GET /wp/v2/posts/{parent}/revisions/{id}'   => 'wp_get_revision',
        'DELETE /wp/v2/posts/{parent}/revisions/{id}' => 'wp_delete_revision',
        'GET /wp/v2/pages'                           => 'wp_list_pages',
        'GET /wp/v2/pages/{id}'                      => 'wp_get_page',
        'POST /wp/v2/pages'                          => 'wp_create_page',
        'WRITE /wp/v2/pages/{id}'                    => 'wp_update_page',
        'DELETE /wp/v2/pages/{id}'                   => 'wp_delete_page',
        'GET /wp/v2/media'                           => 'wp_list_media',
        'GET /wp/v2/media/{id}'                      => 'wp_get_media',
        'POST /wp/v2/media'                          => 'wp_upload_media',
        'WRITE /wp/v2/media/{id}'                    => 'wp_update_media',
        'DELETE /wp/v2/media/{id}'                   => 'wp_delete_media',
        'GET /wp/v2/categories'                      => 'wp_list_categories',
        'GET /wp/v2/categories/{id}'                 => 'wp_get_category',
        'POST /wp/v2/categories'                     => 'wp_create_category',
        'WRITE /wp/v2/categories/{id}'               => 'wp_update_category',
        'DELETE /wp/v2/categories/{id}'              => 'wp_delete_category',
        'GET /wp/v2/tags'                            => 'wp_list_tags',
        'GET /wp/v2/tags/{id}'                       => 'wp_get_tag',
        'POST /wp/v2/tags'                           => 'wp_create_tag',
        'WRITE /wp/v2/tags/{id}'                     => 'wp_update_tag',
        'DELETE /wp/v2/tags/{id}'                    => 'wp_delete_tag',
        'GET /wp/v2/comments'                        => 'wp_list_comments',
        'GET /wp/v2/comments/{id}'                   => 'wp_get_comment',
        'POST /wp/v2/comments'                       => 'wp_create_comment',
        'WRITE /wp/v2/comments/{id}'                 => 'wp_update_comment',
        'DELETE /wp/v2/comments/{id}'                => 'wp_delete_comment',
        'GET /wp/v2/users'                           => 'wp_list_users',
        'GET /wp/v2/users/{id}'                      => 'wp_get_user',
        'POST /wp/v2/users'                          => 'wp_create_user',
        'WRITE /wp/v2/users/{id}'                    => 'wp_update_user',
        'DELETE /wp/v2/users/{id}'                   => 'wp_delete_user',
        'GET /wp/v2/menus'                           => 'wp_list_menus',
        'GET /wp/v2/menus/{id}'                      => 'wp_get_menu',
        'POST /wp/v2/menus'                          => 'wp_create_menu',
        'WRITE /wp/v2/menus/{id}'                    => 'wp_update_menu',
        'DELETE /wp/v2/menus/{id}'                   => 'wp_delete_menu',
        'GET /wp/v2/menu-items'                      => 'wp_list_menu_items',
        'POST /wp/v2/menu-items'                     => 'wp_create_menu_item',
        'WRITE /wp/v2/menu-items/{id}'               => 'wp_update_menu_item',
        'DELETE /wp/v2/menu-items/{id}'              => 'wp_delete_menu_item',
        'GET /wp/v2/blocks'                          => 'wp_list_blocks',
        'GET /wp/v2/blocks/{id}'                     => 'wp_get_block',
        'POST /wp/v2/blocks'                         => 'wp_create_block',
        'WRITE /wp/v2/blocks/{id}'                   => 'wp_update_block',
        'DELETE /wp/v2/blocks/{id}'                  => 'wp_delete_block',
        'GET /wp/v2/templates'                       => 'wp_list_templates',
        'GET /wp/v2/templates/{id}'                  => 'wp_get_template',
        'WRITE /wp/v2/templates/{id}'                => 'wp_update_template',
        'GET /wp/v2/global-styles/{id}'              => 'wp_get_global_styles',
        'WRITE /wp/v2/global-styles/{id}'            => 'wp_update_global_styles',
        'GET /wp/v2/widgets'                         => 'wp_list_widgets',
        'GET /wp/v2/widgets/{id}'                    => 'wp_get_widget',
        'POST /wp/v2/widgets'                        => 'wp_create_widget',
        'WRITE /wp/v2/widgets/{id}'                  => 'wp_update_widget',
        'DELETE /wp/v2/widgets/{id}'                 => 'wp_delete_widget',
        'GET /wp/v2/sidebars'                        => 'wp_list_sidebars',
        'GET /wp/v2/widget-types'                    => 'wp_list_widget_types',
        'GET /wp/v2/settings'                        => 'wp_get_site_settings',
        'WRITE /wp/v2/settings'                      => 'wp_update_site_settings',
        'GET /wp/v2/plugins'                         => 'wp_list_plugins',
        'GET /wp/v2/themes'                          => 'wp_list_themes',
        'GET /wp/v2/search'                          => 'wp_search',
        'GET /wp/v2/types'                           => 'wp_get_post_types',
        'GET /wp/v2/statuses'                        => 'wp_get_post_statuses',
        'GET /wp/v2/taxonomies'                      => 'wp_get_taxonomies',
        'GET /wp/v2/block-types'                     => 'wp_list_block_types',
        'GET /wp/v2/block-patterns/patterns'         => 'wp_list_patterns',
        
        'GET /contact-form-7/v1/contact-forms'       => 'wp_cf7_list_forms',
        'POST /contact-form-7/v1/contact-forms'      => 'wp_cf7_create_form',
        'GET /contact-form-7/v1/contact-forms/{id}'  => 'wp_cf7_get_form',
        'WRITE /contact-form-7/v1/contact-forms/{id}' => 'wp_cf7_update_form',
        'DELETE /contact-form-7/v1/contact-forms/{id}' => 'wp_cf7_delete_form',
    );

    








    public static function for_call( string $method, string $pattern ): ?string {
        $tool = self::tool_for( $method, $pattern );
        if ( null === $tool ) {
            return null;
        }
        if ( class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) && method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' ) ) {
            return true === \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool ) ? $tool : null;
        }
        return null;
    }

    






    public static function tool_for( string $method, string $pattern ): ?string {
        $route = strtolower( Route_Catalogue::display( $pattern ) );
        $keys  = array( strtoupper( $method ) . ' ' . $route );
        if ( in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
            $keys[] = 'WRITE ' . $route;
        }
        foreach ( $keys as $key ) {
            if ( isset( self::MAP[ $key ] ) ) {
                return self::MAP[ $key ];
            }
        }
        return null;
    }
}
