<?php
namespace Easy_MCP_AI\Tools\CPT;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Post_Type_Rest_Base;
use Easy_MCP_AI\Tools\Post_Type_Tool_Hint;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( Post_Type_Tool_Hint::class, false ) ) {
    require_once dirname( __DIR__ ) . '/class-post-type-tool-hint.php';
}

class Create_CPT_Item extends Base_Tool {

    




    const INTERNAL_STATUSES = array( 'trash', 'auto-draft', 'inherit' );

    
    const BUILTIN_CREATE_TOOLS = array(
        'post'       => 'wp_create_post',
        'page'       => 'wp_create_page',
        'attachment' => 'wp_upload_media',
    );

    
    const CORE_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

    




    const MISSING_FIELD_REASONS = array(
        'title'          => 'it does not support "title"',
        'content'        => 'it does not support "editor"',
        'excerpt'        => 'it does not support "excerpt"',
        'author'         => 'it does not support "author"',
        'parent'         => 'it is not hierarchical',
        'menu_order'     => 'it does not support "page-attributes"',
        'comment_status' => 'it does not support "comments"',
        'ping_status'    => 'it does not support "comments"',
        'featured_media' => 'it does not support "thumbnail"',
        'meta'           => 'it does not support "custom-fields"',
    );

    public function get_name() {
        return 'wp_create_cpt_item';
    }

    public function get_description() {
        return 'Creates a new item in any custom post type (CPT) registered with `show_in_rest=true`. Required: `rest_base` (the CPT\'s rest_base, or its post_type slug — `wp_get_post_types` lists both) and `title` (unless `allow_empty_title` is true and `content` or `excerpt` is given). Optional: `status` (default draft; publish/draft/pending/private/future or a custom status registered on this site; "future" needs `date` or `date_gmt`), `slug`, `date` (site time, ISO 8601) or `date_gmt` (UTC) — never both, `author` (user ID), `parent` (parent item ID, hierarchical types only), `menu_order`, `comment_status` / `ping_status` (open/closed), `password` (protects the content and excerpt), `featured_media` (attachment ID), `template`, `meta` (only REST-registered keys persist; dropped keys are reported back as `meta_ignored` with a `notice`) `tax_input` ({ taxonomy slug: [term IDs] } for taxonomies registered on the type and shown in the REST API; every term must exist in that taxonomy) and `allow_empty_title` (boolean, default false). `title`, `content` and `excerpt` are forwarded as before and silently ignored when the CPT lacks the matching `supports` entry; every field added since is refused, naming the missing support, when the CPT does not accept it. If **Easy MCP AI → Settings → Force draft on create** is enabled, `status` is silently overridden to `draft` regardless of the value supplied. For built-in post/page use `wp_create_post` / `wp_create_page` for richer schemas. Returns { id, title, status, link }, plus `meta_ignored` and `notice` when sent meta keys were dropped. This tool is listed as `read` for all callers regardless of the target CPT, but WordPress enforces the CPT\'s own write capability on the underlying REST request (some require edit_posts, others a custom capability like WooCommerce\'s edit_products) — a low-privilege caller may still see a permission error even though the tool is visible.';
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
                'title'     => array(
                    'type'        => 'string',
                    'description' => 'The title for the new item. Required unless allow_empty_title is true.',
                ),
                'content'   => array(
                    'type'        => 'string',
                    'description' => 'The content for the item.',
                ),
                'status'    => array(
                    'type'        => 'string',
                    'description' => 'The status for the item: any status the REST API accepts on this site (core: publish, draft, pending, private, future; plus custom statuses plugins registered). "future" needs date or date_gmt.',
                    'enum'        => self::status_enum(),
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
                'date'      => array(
                    'type'        => 'string',
                    'description' => 'Publication date in the site\'s timezone, ISO 8601 (e.g. 2026-06-01T09:00:00). Do not send together with date_gmt.',
                ),
                'date_gmt'  => array(
                    'type'        => 'string',
                    'description' => 'Publication date in UTC, ISO 8601. Do not send together with date: WordPress uses date and ignores date_gmt when both are present.',
                ),
                'author'    => array(
                    'type'        => 'integer',
                    'description' => 'User ID to assign as the author. Refused when the post type does not support "author".',
                ),
                'parent'    => array(
                    'type'        => 'integer',
                    'description' => 'Parent item ID (0 = top level). Refused when the post type is not hierarchical.',
                ),
                'menu_order' => array(
                    'type'        => 'integer',
                    'description' => 'Order of the item in menus and lists. Refused when the post type does not support "page-attributes".',
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
                    'description' => 'Password that protects the item\'s content and excerpt.',
                ),
                'featured_media' => array(
                    'type'        => 'integer',
                    'description' => 'Attachment ID of the featured image (0 = none). Refused when the post type does not support "thumbnail".',
                ),
                'template'  => array(
                    'type'        => 'string',
                    'description' => 'Theme template file for the item; WordPress refuses one the theme does not offer for this post type. Empty string = default template.',
                ),
                'meta'      => array(
                    'type'        => 'object',
                    'description' => 'Meta key-value pairs to set. Only keys registered with show_in_rest=true for this post type persist; dropped keys are reported back as meta_ignored. Refused when the post type does not support "custom-fields".',
                ),
                'tax_input' => array(
                    'type'        => 'object',
                    'description' => 'Terms to assign, as { taxonomy slug: [term IDs] }, e.g. {"genre": [12, 15]}. Only taxonomies registered for this post type and shown in the REST API are accepted; every term ID must exist in that taxonomy. Each listed taxonomy\'s terms are replaced by the list given.',
                ),
                'allow_empty_title' => array(
                    'type'        => 'boolean',
                    'description' => 'When true, title may be omitted or empty as long as content or excerpt is given. Default false, which keeps title required.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'rest_base' ),
        );
    }

    









    public function precheck( array $arguments ): void {
        self::post_type_route( $this->checked_custom_type( $arguments )[0], null, 'POST' );
    }

    public function execute( array $arguments ) {
        list( $rest_base, $post_type, $post_type_object ) = $this->checked_custom_type( $arguments );

        $params    = self::content_params( $arguments );
        $meta_sent = $this->apply_checked_fields( $arguments, $params, $post_type, $post_type_object );
        self::refuse_future_without_date( $params );

        $this->maybe_force_draft( $params );

        $data = self::create_item( $rest_base, $params );

        $result = array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
            'link'   => $data['link'] ?? '',
        );
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], $data['meta'] ?? null, 'post', $post_type ) );
        }

        return $result;
    }

    







    private function checked_custom_type( array $arguments ): array {
        if ( self::allows_empty_title( $arguments ) ) {
            $this->validate_required( $arguments, array( 'rest_base' ) );
            self::require_title_content_or_excerpt( $arguments );
        } else {
            $this->validate_required( $arguments, array( 'rest_base', 'title' ) );
        }
        if ( self::has_text( $arguments, 'title' ) ) {
            $this->validate_title_length( $arguments['title'] );
        }
        $sent                                 = $this->validate_rest_route_segment( $arguments['rest_base'], 'rest_base' );
        $rest_base                            = $this->resolve_post_type_rest_base( $sent, 'rest_base' );
        list( $post_type, $post_type_object ) = self::custom_post_type_for( $rest_base, $sent );
        return array( $rest_base, $post_type, $post_type_object );
    }

    











    private static function custom_post_type_for( string $rest_base, string $sent ): array {
        $served = Post_Type_Rest_Base::require_served_type( $rest_base );
        if ( Post_Type_Rest_Base::is_builtin( $served[0] ) ) {
            throw new \InvalidArgumentException( self::builtin_refusal( $served[0], $sent ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $served;
    }

    







    private static function builtin_refusal( string $post_type, string $sent ): string {
        $tool = isset( self::BUILTIN_CREATE_TOOLS[ $post_type ] ) ? self::BUILTIN_CREATE_TOOLS[ $post_type ] : '';
        if ( '' !== $tool && Post_Type_Tool_Hint::may_use( $tool ) ) {
            return sprintf( 'Use %1$s: "%2$s" is the built-in %3$s type, which this tool does not create.', $tool, $sent, $post_type );
        }
        return sprintf( '"%1$s" is the built-in %2$s type, which this tool does not create: it creates custom post types only.', $sent, $post_type );
    }

    






    private static function content_params( array $arguments ): array {
        $params = array(
            'status' => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'draft',
        );
        if ( self::has_text( $arguments, 'title' ) ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }

        if ( isset( $arguments['content'] ) ) {
            
            
            
            
            $params['content'] = $arguments['content'];
        }
        if ( isset( $arguments['excerpt'] ) ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }
        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }
        return $params;
    }

    












    private function apply_checked_fields( array $arguments, array &$params, string $post_type, object $post_type_object ): bool {
        $sent = self::apply_post_fields( $arguments, $params );
        if ( isset( $arguments['author'] ) ) {
            $params['author'] = $this->parse_required_id( $arguments['author'], 'author' );
            $sent[]           = 'author';
        }
        $meta_sent = $this->apply_meta_argument( $arguments, $params );
        if ( $meta_sent ) {
            $sent[] = 'meta';
        }
        self::refuse_unsupported_fields( $sent, $post_type_object, $post_type );
        if ( isset( $arguments['tax_input'] ) ) {
            $params = array_merge( $params, self::tax_input_params( $arguments['tax_input'], $post_type, $params ) );
        }
        return $meta_sent;
    }

    









    private static function refuse_future_without_date( array $params ): void {
        if ( 'future' === $params['status'] && empty( $params['date'] ) && empty( $params['date_gmt'] ) ) {
            throw new \InvalidArgumentException( 'The "date" or "date_gmt" field is required when status is "future".' );
        }
    }

    








    private static function create_item( string $rest_base, array $params ): array {
        $request = new \WP_REST_Request( 'POST', self::post_type_route( $rest_base, null, 'POST' ) );
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
                sprintf( 'Failed to create item in "%s": %s', $rest_base, self::rest_error_message( $wp_error ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        return $response->get_data();
    }

    
    
    
    
    
    

    








    public static function has_text( array $arguments, string $key ): bool {
        return isset( $arguments[ $key ] ) && '' !== $arguments[ $key ];
    }

    






    public static function allows_empty_title( array $arguments ): bool {
        return isset( $arguments['allow_empty_title'] ) && (bool) rest_sanitize_boolean( $arguments['allow_empty_title'] );
    }

    












    public static function require_title_content_or_excerpt( array $arguments ): void {
        if ( self::has_text( $arguments, 'title' ) ) {
            return;
        }
        foreach ( array( 'content', 'excerpt' ) as $key ) {
            if ( isset( $arguments[ $key ] ) && is_string( $arguments[ $key ] ) && '' !== $arguments[ $key ] ) {
                return;
            }
        }
        throw new \InvalidArgumentException( 'Missing required parameters: title, content or excerpt (with allow_empty_title, at least one must be non-empty).' );
    }

    










    public static function status_enum(): array {
        $statuses   = self::CORE_STATUSES;
        $registered = function_exists( 'get_post_stati' ) ? (array) get_post_stati( array( 'internal' => false ) ) : array();
        $extra      = array();
        foreach ( $registered as $status ) {
            $status = is_scalar( $status ) ? (string) $status : '';
            if ( '' === $status || in_array( $status, $statuses, true ) || in_array( $status, self::INTERNAL_STATUSES, true ) ) {
                continue;
            }
            $extra[] = $status;
        }
        return array_values( array_unique( array_merge( $statuses, $extra ) ) );
    }

    













    public static function apply_post_fields( array $arguments, array &$params ): array {
        $fields = array_merge(
            self::date_fields( $arguments ),
            self::order_fields( $arguments ),
            self::discussion_fields( $arguments ),
            self::display_fields( $arguments )
        );
        foreach ( $fields as $field => $value ) {
            $params[ $field ] = $value;
        }
        return array_keys( $fields );
    }

    






    private static function date_fields( array $arguments ): array {
        self::refuse_both_dates( $arguments );
        $fields = array();
        foreach ( array( 'date', 'date_gmt' ) as $field ) {
            if ( ! empty( $arguments[ $field ] ) ) {
                $fields[ $field ] = sanitize_text_field( (string) $arguments[ $field ] );
            }
        }
        return $fields;
    }

    






    private static function order_fields( array $arguments ): array {
        $fields = array();
        if ( isset( $arguments['parent'] ) ) {
            $fields['parent'] = self::parse_non_negative_int( $arguments['parent'], 'parent' );
        }
        if ( isset( $arguments['menu_order'] ) ) {
            $fields['menu_order'] = self::parse_int( $arguments['menu_order'], 'menu_order' );
        }
        return $fields;
    }

    






    private static function discussion_fields( array $arguments ): array {
        $fields = array();
        foreach ( array( 'comment_status', 'ping_status' ) as $field ) {
            if ( ! isset( $arguments[ $field ] ) ) {
                continue;
            }
            $value = is_scalar( $arguments[ $field ] ) ? (string) $arguments[ $field ] : '';
            if ( ! in_array( $value, array( 'open', 'closed' ), true ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: %2$s. Use open or closed.', $field, is_scalar( $arguments[ $field ] ) ? (string) $arguments[ $field ] : gettype( $arguments[ $field ] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $fields[ $field ] = $value;
        }
        return $fields;
    }

    






    private static function display_fields( array $arguments ): array {
        $fields = array();
        if ( isset( $arguments['password'] ) ) {
            if ( ! is_string( $arguments['password'] ) ) {
                throw new \InvalidArgumentException( 'Invalid password: must be a string.' );
            }
            
            
            
            
            $fields['password'] = $arguments['password'];
        }
        if ( isset( $arguments['featured_media'] ) ) {
            $fields['featured_media'] = self::parse_non_negative_int( $arguments['featured_media'], 'featured_media' );
        }
        if ( isset( $arguments['template'] ) ) {
            if ( ! is_scalar( $arguments['template'] ) ) {
                throw new \InvalidArgumentException( 'Invalid template: must be a string.' );
            }
            $fields['template'] = sanitize_text_field( (string) $arguments['template'] );
        }
        return $fields;
    }

    









    public static function refuse_both_dates( array $arguments ): void {
        if ( ! empty( $arguments['date'] ) && ! empty( $arguments['date_gmt'] ) ) {
            throw new \InvalidArgumentException( 'Send either date or date_gmt, not both: WordPress uses date and ignores date_gmt when both are given. Nothing was written.' );
        }
    }

    








    public static function clear_fields( array $arguments ): array {
        if ( ! isset( $arguments['clear'] ) ) {
            return array();
        }
        $value = $arguments['clear'];
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : array( $value );
        }
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( 'clear must be a list of field names: title, content, excerpt.' );
        }
        $fields = array();
        foreach ( $value as $field ) {
            $field = is_scalar( $field ) ? (string) $field : '';
            if ( ! in_array( $field, array( 'title', 'content', 'excerpt' ), true ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid clear entry "%s". Use title, content or excerpt.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( self::has_text( $arguments, $field ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s is both set and listed in clear. Send one or the other. Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $fields[ $field ] = true;
        }
        return array_keys( $fields );
    }

    







    public static function parse_non_negative_int( $value, string $label ): int {
        $int = self::parse_int( $value, $label );
        if ( $int < 0 ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be 0 or more.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $int;
    }

    







    public static function parse_int( $value, string $label ): int {
        if ( is_int( $value ) ) {
            return $value;
        }
        if ( is_float( $value ) && floor( $value ) === $value && abs( $value ) < PHP_INT_MAX ) {
            return (int) $value;
        }
        if ( is_string( $value ) && preg_match( '/^-?\d+\z/', $value ) ) {
            return (int) $value;
        }
        throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: must be a whole number, got: %2$s', $label, (string) wp_json_encode( $value ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    






    public static function rest_taxonomies( string $post_type ): array {
        $map = array();
        foreach ( (array) get_object_taxonomies( $post_type, 'objects' ) as $key => $tax ) {
            if ( ! is_object( $tax ) || empty( $tax->show_in_rest ) ) {
                continue;
            }
            $slug         = ! empty( $tax->name ) ? (string) $tax->name : (string) $key;
            $map[ $slug ] = ! empty( $tax->rest_base ) ? (string) $tax->rest_base : $slug;
        }
        return $map;
    }

    
















    public static function tax_input_params( $value, string $post_type, array $params ): array {
        $value = self::tax_input_object( $value );
        $map   = self::rest_taxonomies( $post_type );
        $out   = array();
        foreach ( $value as $taxonomy => $ids ) {
            $taxonomy = (string) $taxonomy;
            if ( ! isset( $map[ $taxonomy ] ) ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'tax_input: taxonomy "%1$s" is not registered for post type "%2$s" in the REST API. Accepted here: %3$s. Nothing was written.',
                        $taxonomy, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        $post_type, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        empty( $map ) ? '(none)' : implode( ', ', array_keys( $map ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    )
                );
            }
            $clean = self::existing_term_ids( $ids, $taxonomy );
            $base  = $map[ $taxonomy ];
            if ( array_key_exists( $base, $params ) || array_key_exists( $base, $out ) ) {
                throw new \InvalidArgumentException( sprintf( 'tax_input: taxonomy "%1$s" is sent to WordPress as "%2$s", which this call already sets. Nothing was written.', $taxonomy, $base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[ $base ] = $clean;
        }
        return $out;
    }

    







    private static function tax_input_object( $value ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : null;
        }
        if ( ! is_array( $value ) || ( array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 ) ) ) {
            throw new \InvalidArgumentException( 'tax_input must be an object of { taxonomy slug: [term IDs] }.' );
        }
        return $value;
    }

    








    private static function existing_term_ids( $ids, string $taxonomy ): array {
        if ( is_scalar( $ids ) ) {
            $ids = array( $ids );
        }
        if ( ! is_array( $ids ) ) {
            throw new \InvalidArgumentException( sprintf( 'tax_input: the terms for "%s" must be a list of term IDs.', $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $clean = array();
        foreach ( $ids as $id ) {
            $term_id = self::parse_int( $id, 'tax_input term ID for "' . $taxonomy . '"' );
            if ( $term_id < 1 ) {
                throw new \InvalidArgumentException( sprintf( 'tax_input: term IDs for "%s" must be positive.', $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $term = get_term( $term_id, $taxonomy );
            if ( ! $term || is_wp_error( $term ) || ( isset( $term->taxonomy ) && $term->taxonomy !== $taxonomy ) ) {
                throw new \InvalidArgumentException( sprintf( 'tax_input: term %1$d does not exist in taxonomy "%2$s". Nothing was written.', $term_id, $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $clean[ $term_id ] = $term_id;
        }
        return array_values( $clean );
    }

    













    public static function refuse_unsupported_fields( array $fields, ?object $post_type_object, string $post_type, string $prefix = '' ): void {
        if ( empty( $fields ) ) {
            return;
        }
        $accepted = self::rest_item_fields( $post_type_object );
        if ( null === $accepted ) {
            return;
        }
        foreach ( $fields as $field ) {
            if ( in_array( $field, $accepted, true ) ) {
                continue;
            }
            $why   = isset( self::MISSING_FIELD_REASONS[ $field ] ) ? self::MISSING_FIELD_REASONS[ $field ] : 'its REST API schema has no such field';
            $label = '' === $prefix ? $field : $prefix . ' (' . $field . ')';
            throw new \InvalidArgumentException( sprintf( '%1$s: post type "%2$s" does not accept it (%3$s), so WordPress would ignore it. Nothing was written.', $label, $post_type, $why ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    public static function post_type_for_rest_base( string $rest_base ): array {
        return Post_Type_Rest_Base::require_served_type( $rest_base );
    }
}
