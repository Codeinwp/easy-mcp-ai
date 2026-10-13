<?php
namespace Easy_MCP_AI\Tools\CPT;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_CPT_Item extends Base_Tool {

    public function get_name() {
        return 'wp_create_cpt_item';
    }

    public function get_description() {
        return 'Creates a new item in any custom post type (CPT) registered with `show_in_rest=true`. Required: `rest_base` (NOT the post_type slug — discover via `wp_get_post_types`), `title`. Optional: `content`, `status` (publish/draft/pending/private — default draft), `slug`, `excerpt`. Field acceptance depends on what the CPT registered as `supports`; sending unsupported fields is silently ignored. If **Easy MCP AI → Settings → Force draft on create** is enabled, `status` is silently overridden to `draft` regardless of the value supplied. For built-in post/page use `wp_create_post` / `wp_create_page` for richer schemas. This tool is listed as `read` for all callers regardless of the target CPT, but WordPress enforces the CPT\'s own write capability on the underlying REST request (some require edit_posts, others a custom capability like WooCommerce\'s edit_products) — a low-privilege caller may still see a permission error even though the tool is visible.';
    }

    public function get_category() {
        return 'cpt';
    }

    






    public function get_required_capability() {
        return 'read';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'rest_base' => array(
                    'type'        => 'string',
                    'description' => 'The REST API base slug for the post type.',
                ),
                'title'     => array(
                    'type'        => 'string',
                    'description' => 'The title for the new item.',
                ),
                'content'   => array(
                    'type'        => 'string',
                    'description' => 'The content for the item.',
                ),
                'status'    => array(
                    'type'        => 'string',
                    'description' => 'The status for the item.',
                    'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
                    'default'     => 'draft',
                ),
                'excerpt'   => array(
                    'type'        => 'string',
                    'description' => 'The excerpt for the item. HTML is accepted and sanitized by WordPress per the calling user\'s capability.',
                ),
                'slug'      => array(
                    'type'        => 'string',
                    'description' => 'The slug for the item.',
                ),
            ),
            'required'   => array( 'rest_base', 'title' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'rest_base', 'title' ) );
        $this->validate_title_length( $arguments['title'] );

        $rest_base = $this->validate_rest_route_segment( $arguments['rest_base'], 'rest_base' );

        
        $builtin_post_types = array(
            'post', 'page', 'attachment', 'revision', 'nav_menu_item',
            'custom_css', 'customize_changeset', 'oembed_cache', 'user_request',
            'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
            'wp_navigation', 'wp_font_family', 'wp_font_face',
        );
        $rest_enabled_types = get_post_types( array( 'show_in_rest' => true ), 'objects' );
        $valid_rest_bases   = array();
        foreach ( $rest_enabled_types as $pt_key => $pt_object ) {
            if ( in_array( $pt_key, $builtin_post_types, true ) ) {
                continue;
            }
            $valid_rest_bases[] = ! empty( $pt_object->rest_base ) ? $pt_object->rest_base : $pt_key;
        }
        if ( ! in_array( $rest_base, $valid_rest_bases, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid rest_base "%s". Must correspond to a registered custom post type.', $rest_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $params = array(
            'title'  => sanitize_text_field( $arguments['title'] ),
            'status' => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'draft',
        );

        if ( isset( $arguments['content'] ) ) {
            
            
            
            
            $params['content'] = $arguments['content'];
        }
        if ( isset( $arguments['excerpt'] ) ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }
        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }

        $this->maybe_force_draft( $params );

        $request = new \WP_REST_Request( 'POST', '/wp/v2/' . $rest_base );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException(
                    sprintf( 'No REST API route found for post type "%s". This post type may not exist, may not have show_in_rest enabled, or the rest_base may be incorrect. Use wp_get_post_types to discover available post types.', $rest_base ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            throw new \RuntimeException(
                sprintf( 'Failed to create item in "%s": %s', $rest_base, $wp_error->get_error_message() ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $data = $response->get_data();

        return array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
            'link'   => $data['link'] ?? '',
        );
    }
}
