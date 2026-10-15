<?php
namespace Easy_MCP_AI\Tools\CPT;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Post_Type_Tool_Hint;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( Post_Type_Tool_Hint::class, false ) ) {
    require_once dirname( __DIR__ ) . '/class-post-type-tool-hint.php';
}


if ( ! class_exists( __NAMESPACE__ . '\\Create_CPT_Item', false ) ) {
    require_once __DIR__ . '/class-create-cpt-item.php';
}

class Update_CPT_Item extends Base_Tool {

    public function get_name() {
        return 'wp_update_cpt_item';
    }

    public function get_description() {
        return 'Updates an existing custom post type (CPT) item. Required: `rest_base` (the CPT\'s rest_base, or its post_type slug) + `item_id`. Only fields you provide are changed (PATCH semantics). Common: `title`, `content`, `status` (publish/draft/pending/private/future or a custom status registered on this site; "trash" is not accepted — use `wp_delete_cpt_item`), `slug`, `excerpt`, `meta` (only keys registered with `show_in_rest=true` persist; dropped keys are reported back as `meta_ignored` with a `notice`; refused when the CPT does not support "custom-fields"). Also: `date` (site time, ISO 8601) or `date_gmt` (UTC) — never both, `author` (user ID), `parent` (hierarchical types only, 0 = top level), `menu_order`, `comment_status` / `ping_status` (open/closed), `password` (an empty string removes it), `featured_media` (attachment ID, 0 removes it), `template`, `tax_input` ({ taxonomy slug: [term IDs] }, replacing that taxonomy\'s terms) and `clear` (list of title/content/excerpt to set to an empty string, the same as sending them empty here). Each of those added fields is refused, naming the missing support, when the CPT does not accept it, and needs `rest_base` to be a post type shown in the REST API. Status transitions follow normal WordPress rules. Note: unlike `wp_update_post`/`wp_update_page`, passing an empty string for `title`, `content`, or `excerpt` here DOES blank the field rather than preserving the existing value. Discover the CPT\'s `rest_base` via `wp_get_post_types`. Returns { id, title, status, link }, plus `meta_ignored` and `notice` when sent meta keys were dropped. This tool is listed as `read` for all callers regardless of the target CPT, but WordPress enforces the CPT\'s own write capability on the underlying REST request (some require edit_posts, others a custom capability like WooCommerce\'s edit_products) — a low-privilege caller may still see a permission error even though the tool is visible.';
    }

    public function get_category() {
        return 'cpt';
    }

    
    public function get_required_capability() {
        return 'read';
    }

    
    public function get_redacted_arguments() {
        return array( 'password' );
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
                    'description' => 'The post type\'s REST base (e.g. "product", "books"); its post type slug (e.g. "book") is also accepted.',
                ),
                'item_id'   => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the item to update.',
                ),
                'title'     => array(
                    'type'        => 'string',
                    'description' => 'New title for the item.',
                ),
                'content'   => array(
                    'type'        => 'string',
                    'description' => 'New content for the item.',
                ),
                'status'    => array(
                    'type'        => 'string',
                    'description' => 'New status for the item: any status the REST API accepts on this site (core: publish, draft, pending, private, future; plus custom statuses plugins registered). To move an item to trash, use wp_delete_cpt_item instead.',
                    'enum'        => Create_CPT_Item::status_enum(),
                ),
                'excerpt'   => array(
                    'type'        => 'string',
                    'description' => 'New excerpt for the item. HTML is accepted and sanitized by WordPress per the calling user\'s capability.',
                ),
                'slug'      => array(
                    'type'        => 'string',
                    'description' => 'URL-friendly slug for the item.',
                ),
                'meta'      => array(
                    'type'        => 'object',
                    'description' => 'Meta key-value pairs to set. Only keys registered with show_in_rest=true for this post type persist; dropped keys are reported back as meta_ignored. Refused when the post type does not support "custom-fields".',
                ),
                'date'      => array(
                    'type'        => 'string',
                    'description' => 'New publication date in the site\'s timezone, ISO 8601. Do not send together with date_gmt.',
                ),
                'date_gmt'  => array(
                    'type'        => 'string',
                    'description' => 'New publication date in UTC, ISO 8601. Do not send together with date: WordPress uses date and ignores date_gmt when both are present.',
                ),
                'author'    => array(
                    'type'        => 'integer',
                    'description' => 'Reassign the item to this user ID. Refused when the post type does not support "author".',
                ),
                'parent'    => array(
                    'type'        => 'integer',
                    'description' => 'New parent item ID (0 = top level). Refused when the post type is not hierarchical.',
                ),
                'menu_order' => array(
                    'type'        => 'integer',
                    'description' => 'New order in menus and lists. Refused when the post type does not support "page-attributes".',
                ),
                'comment_status' => array(
                    'type'        => 'string',
                    'description' => 'Whether comments are open or closed. Refused when the post type does not support "comments".',
                    'enum'        => array( 'open', 'closed' ),
                ),
                'ping_status' => array(
                    'type'        => 'string',
                    'description' => 'Whether pingbacks/trackbacks are open or closed. Refused when the post type does not support "comments".',
                    'enum'        => array( 'open', 'closed' ),
                ),
                'password'  => array(
                    'type'        => 'string',
                    'description' => 'New password protecting the item\'s content and excerpt; an empty string removes the password.',
                ),
                'featured_media' => array(
                    'type'        => 'integer',
                    'description' => 'Attachment ID of the featured image; 0 removes it. Refused when the post type does not support "thumbnail".',
                ),
                'template'  => array(
                    'type'        => 'string',
                    'description' => 'Theme template file for the item; WordPress refuses one the theme does not offer for this post type. Empty string = default template.',
                ),
                'tax_input' => array(
                    'type'        => 'object',
                    'description' => 'Terms to set, as { taxonomy slug: [term IDs] }, e.g. {"genre": [12]}. Each listed taxonomy\'s terms are replaced by the list given; an empty list removes them all. Only taxonomies registered for this post type and shown in the REST API are accepted; every term ID must exist in that taxonomy.',
                ),
                'clear'     => array(
                    'type'        => 'array',
                    'items'       => array(
                        'type' => 'string',
                        'enum' => array( 'title', 'content', 'excerpt' ),
                    ),
                    'description' => 'Fields to set to an empty string: any of title, content, excerpt (here the same as sending the field as an empty string; kept for the same contract as wp_update_post and wp_update_page). A field listed here must not also be sent with a value.',
                ),
            ),
            'required'   => array( 'rest_base', 'item_id' ),
        );
    }

    






    public function precheck( array $arguments ): void {
        $this->precheck_post_type_route( $arguments, 'rest_base', '', 'item_id', 'POST' );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'rest_base', 'item_id' ) );
        $this->validate_title_length( isset( $arguments['title'] ) ? $arguments['title'] : null );

        $rest_base = $this->resolve_post_type_rest_base( $arguments['rest_base'], 'rest_base' );
        $item_id   = $this->parse_required_id( $arguments['item_id'], 'item_id' );

        $params         = self::content_params( $arguments );
        $meta_sent      = $this->apply_meta_argument( $arguments, $params );
        $meta_post_type = $meta_sent ? self::refuse_meta_without_support( $rest_base ) : '';
        $this->apply_post_type_fields( $arguments, $rest_base, $params );

        $data = self::update_item( $rest_base, $item_id, $params );

        $result = array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
            'link'   => $data['link'] ?? '',
        );
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], $data['meta'] ?? null, 'post', $meta_post_type ) );
        }

        return $result;
    }

    









    private static function refuse_meta_without_support( string $rest_base ): string {
        list( $post_type, $post_type_object ) = Create_CPT_Item::post_type_for_rest_base( $rest_base );
        Create_CPT_Item::refuse_unsupported_fields( array( 'meta' ), $post_type_object, $post_type );
        return $post_type;
    }

    







    private static function content_params( array $arguments ): array {
        $params = array();
        if ( isset( $arguments['title'] ) ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }
        if ( isset( $arguments['content'] ) ) {
            
            
            
            
            $params['content'] = $arguments['content'];
        }
        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
        }
        if ( isset( $arguments['excerpt'] ) ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }
        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }
        return $params;
    }

    










    private function apply_post_type_fields( array $arguments, string $rest_base, array &$params ): void {
        $sent = Create_CPT_Item::apply_post_fields( $arguments, $params );
        if ( isset( $arguments['author'] ) ) {
            $params['author'] = $this->parse_required_id( $arguments['author'], 'author' );
            $sent[]           = 'author';
        }
        $clear = Create_CPT_Item::clear_fields( $arguments );
        foreach ( $clear as $field ) {
            $params[ $field ] = '';
        }
        $has_tax_input = isset( $arguments['tax_input'] );
        if ( ! $sent && ! $clear && ! $has_tax_input ) {
            return;
        }
        list( $post_type, $post_type_object ) = Create_CPT_Item::post_type_for_rest_base( $rest_base );
        Create_CPT_Item::refuse_unsupported_fields( $sent, $post_type_object, $post_type );
        Create_CPT_Item::refuse_unsupported_fields( $clear, $post_type_object, $post_type, 'clear' );
        if ( $has_tax_input ) {
            $params = array_merge( $params, Create_CPT_Item::tax_input_params( $arguments['tax_input'], $post_type, $params ) );
        }
    }

    









    private static function update_item( string $rest_base, int $item_id, array $params ): array {
        $request = new \WP_REST_Request( 'POST', self::post_type_route( $rest_base, $item_id, 'POST' ) );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException( Post_Type_Tool_Hint::no_route( $rest_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            throw new \RuntimeException(
                sprintf( 'Failed to update item in "%s": %s', $rest_base, self::rest_error_message( $wp_error ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        return $response->get_data();
    }
}
