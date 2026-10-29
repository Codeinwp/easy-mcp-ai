<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Base_Tool {

    const EXISTING_TITLE_LABEL_LENGTH = 60;

    const EXTERNAL_DATA_CATEGORIES = array( 'ga', 'gsc', 'dfs', 'semrush', 'seranking' );

    const REST_ERROR_WITHOUT_MESSAGE = 1;

    const RESPONSE_NOISE_KEYS = array( 'yoast_head', 'yoast_head_json', 'aioseo_head', 'aioseo_head_json', 'aioseo_breadcrumb', 'aioseo_breadcrumb_json', '_links' );

    const RESPONSE_NOISE_PROTECTED_KEYS = array( 'id', 'meta', 'meta_data', 'acf', 'aioseo_meta_data' );

    protected static $already_invalidated = array();

    abstract public function get_name();
    abstract public function get_description();
    abstract public function get_input_schema();
    abstract public function execute( array $arguments );

    public function get_required_capability() {
        return 'manage_options';
    }

    public function get_title() {
        $name = $this->get_name();

        $prefixes = array(
            'wp_wc_'       => 'WooCommerce',
            'wp_ga_'       => 'Google Analytics',
            'wp_gsc_'      => 'Search Console',
            'wp_dfs_'      => 'DataForSEO',
            'wp_semrush_'  => 'SEMrush',
            'wp_ahrefs_'   => 'Ahrefs',
            'wp_acf_'      => 'ACF',
            'wp_aioseo_'   => 'AIOSEO',
            'wp_bp_'       => 'BuddyPress',
            'wp_tec_'      => 'Events Calendar',
            'wp_redirection_' => 'Redirection',
            'wp_sitekit_'  => 'Site Kit',
            'wp_cf7_'      => 'Contact Form 7',
            'wp_yoast_'    => 'Yoast',
            'wp_rm_'       => 'Rank Math',
            'wp_seranking_' => 'SE Ranking',
            'wp_seopress_' => 'SEOPress',
            'wp_slimseo_'  => 'Slim SEO',
            'wp_tsf_'      => 'The SEO Framework',
            'wp_ability_'  => 'Ability',
        );

        foreach ( $prefixes as $prefix => $label ) {
            if ( strpos( $name, $prefix ) === 0 ) {
                $rest = substr( $name, strlen( $prefix ) );
                return $label . ': ' . ucwords( str_replace( '_', ' ', $rest ) );
            }
        }

        $rest = substr( $name, 3 );
        return ucwords( str_replace( '_', ' ', $rest ) );
    }

    public function get_redacted_arguments() {
        return array();
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_category() {
        return 'general';
    }

    public function get_covers(): string {
        return '';
    }

    public function get_match_phrases(): string {
        return '';
    }

    public function is_disabled_by_default() {
        return false;
    }

    public function get_approval_policy( array $arguments ) {
        return 'inherit';
    }

    public function is_composite( array $arguments = array() ) {
        return false;
    }

    public function is_read_only_call( array $arguments ) {
        $annotations = $this->get_annotations();
        return ! empty( $annotations['readOnlyHint'] );
    }

    public function redact_for_record( array $arguments ) {
        return $arguments;
    }

    public function describe( array $arguments ) {
        return self::generic_description( $this, $arguments );
    }

    public static function generic_description( $tool, array $arguments ) {
        $title = method_exists( $tool, 'get_title' ) ? (string) $tool->get_title() : (string) $tool->get_name();
        $mask  = method_exists( $tool, 'get_redacted_arguments' ) ? (array) $tool->get_redacted_arguments() : array();
        foreach ( $mask as $name ) {
            if ( array_key_exists( $name, $arguments ) ) {
                $arguments[ $name ] = '[redacted]';
            }
        }
        if ( empty( $arguments ) ) {
            return $title;
        }
        $pairs = array();
        foreach ( $arguments as $key => $value ) {
            if ( is_scalar( $value ) || null === $value ) {
                $shown = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : ( null === $value ? 'null' : (string) $value );
            } else {
                $shown = \wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                $shown = is_string( $shown ) ? $shown : '…';
            }
            if ( strlen( $shown ) > 120 ) {
                $shown = substr( $shown, 0, 117 ) . '…';
            }
            $pairs[] = $key . ': ' . $shown;
        }
        $text = sprintf( '%s (%s)', $title, implode( ', ', $pairs ) );
        return strlen( $text ) > 600 ? substr( $text, 0, 597 ) . '…' : $text;
    }

    protected function peek( $route, $fields, array $params = array() ) {
        try {
            $data = $this->rest_request( 'GET', $route, $params, $fields );
        } catch ( \Throwable $e ) {
            return null;
        }
        return is_array( $data ) ? $data : null;
    }

    protected static function rest_title( $data, $key = 'title' ) {
        $value = isset( $data[ $key ] ) ? $data[ $key ] : '';
        if ( is_array( $value ) ) {
            $value = isset( $value['rendered'] ) ? $value['rendered'] : ( isset( $value['raw'] ) ? $value['raw'] : '' );
        }
        $value = trim( \wp_strip_all_tags( (string) $value ) );
        return '' === $value ? __( '(no title)', 'easy-mcp-ai' ) : $value;
    }

    public function get_output_schema() {
        return null;
    }

    public function get_definition() {
        $definition = array(
            'name'        => $this->get_name(),
            'description' => $this->get_description(),
            'inputSchema' => $this->get_input_schema(),
        );
        $output_schema = $this->get_output_schema();
        if ( null !== $output_schema ) {
            $definition['outputSchema'] = $output_schema;
        }
        $annotations = $this->get_annotations();
        if ( ! empty( $annotations ) ) {
            $definition['annotations'] = $annotations;
        }
        $ui = $this->get_ui_meta();
        if ( ! empty( $ui ) ) {
            $definition['_meta'] = array( 'ui' => $ui );
            if ( isset( $ui['resourceUri'] ) ) {
                $definition['_meta']['ui/resourceUri'] = $ui['resourceUri'];
            }
        }
        return $definition;
    }

    public function get_ui_meta() {
        if ( ! \Easy_MCP_AI\Config::get( 'approval_required' ) || ! \Easy_MCP_AI\Approvals\Approval_Gate::is_destructive_by_annotation( $this ) ) {
            return array();
        }
        return array( 'resourceUri' => 'ui://easy-mcp-ai/approval' );
    }

    protected function parse_required_id( $value, string $label = 'ID' ): int {
        if ( is_int( $value ) ) {
            $id = $value;
        } elseif ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
            $id = (int) $value;
        } else {
            throw new \InvalidArgumentException( sprintf( "Invalid %s: must be a positive integer, got: %s", $label, json_encode( $value ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $id < 1 ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be a positive integer.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $id;
    }

    protected function normalize_counts( $counts ) {
        $out = array();
        foreach ( (array) $counts as $key => $value ) {
            $out[ (string) $key ] = is_scalar( $value ) ? (int) $value : 0;
        }
        return $out;
    }

    protected static function text_arg( array $arguments, string $key ): string {
        return isset( $arguments[ $key ] ) && is_string( $arguments[ $key ] ) ? trim( $arguments[ $key ] ) : '';
    }

    protected function validate_required( array $arguments, array $required_keys ) {
        $missing = array();
        foreach ( $required_keys as $key ) {
            if ( ! isset( $arguments[$key] ) || '' === $arguments[$key] ) {
                $missing[] = $key;
            }
        }
        if ( ! empty( $missing ) ) {
            throw new \InvalidArgumentException( sprintf( 'Missing required parameters: %s', implode( ', ', $missing ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    protected function refuse_unless_one_of( string $label, $checked, array $allowed, $shown, string $hint ): void {
        if ( ! in_array( $checked, $allowed, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: %2$s. Use %3$s.', $label, is_scalar( $shown ) ? (string) $shown : gettype( $shown ), $hint ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    protected function parse_json_param( $value, $label = 'parameter' ) {
        if ( is_array( $value ) ) {
            return $value;
        }
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }
        throw new \InvalidArgumentException(
            sprintf( '%s must be a valid JSON array or object.', $label ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        );
    }

    protected function validate_title_length( $title ) {
        if ( null === $title ) {
            return;
        }
        $max = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_max_title_length', 0 );
        if ( $max > 0 && mb_strlen( $title ) > $max ) {
            throw new \InvalidArgumentException(
                sprintf( 'Title exceeds maximum allowed length of %d characters.', $max ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    protected function maybe_force_draft( array &$params ) {
        if ( \Easy_MCP_AI\Config::get( 'easy_mcp_ai_force_draft_on_create', false ) ) {
            $params['status'] = 'draft';
        }
    }

    protected function validate_assignable_roles( array $roles ) {
        foreach ( $roles as $role ) {
            if ( 'administrator' === $role && ! current_user_can( 'manage_options' ) ) {
                throw new \InvalidArgumentException( 'You do not have permission to assign the administrator role.' );
            }
            if ( ! current_user_can( 'promote_users' ) ) {
                throw new \InvalidArgumentException( 'You do not have permission to assign roles.' );
            }
        }
        return $roles;
    }

    protected function validate_rest_route_segment( $value, $label = 'value' ) {
        $value = is_string( $value ) ? trim( $value ) : '';

        if ( '' === $value || ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid %s "%s". Use only letters, numbers, underscores, and hyphens.',
                    $label, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    $value // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                )
            );
        }

        return $value;
    }

    protected function resolve_post_type_rest_base( $value, string $label ): string {
        $value = $this->validate_rest_route_segment( $value, $label );
        $base = Post_Type_Rest_Base::resolve( $value );
        Post_Type_Rest_Base::require_served_type( $base );
        return $base;
    }

    protected static function post_type_route( string $base, ?int $id, string ...$methods ): string {
        $route = '/wp/v2/' . $base . ( null === $id ? '' : '/' . $id );
        foreach ( $methods as $method ) {
            Post_Type_Rest_Base::require_own_route( $method, $base, $route );
        }
        return $route;
    }

    protected function precheck_post_type_route( array $arguments, string $key, string $default, ?string $id_key, string ...$methods ): void {
        $given = '' === $default
            ? isset( $arguments[ $key ] ) && '' !== $arguments[ $key ]
            : ! empty( $arguments[ $key ] );
        if ( $given ) {
            $base = $this->resolve_post_type_rest_base( $arguments[ $key ], $key );
        } elseif ( '' !== $default ) {
            $base = $default;
        } else {
            return;
        }
        $id = null;
        if ( null !== $id_key ) {
            try {
                $id = $this->parse_required_id( $arguments[ $id_key ] ?? null, $id_key );
            } catch ( \InvalidArgumentException $e ) {
                return;
            }
        }
        self::post_type_route( $base, $id, ...$methods );
    }

    protected function refuse_other_type( int $post_id, string $expected_type, string $verb ): void {
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || $expected_type === $post->post_type || ! get_post_type_object( $post->post_type ) || ! current_user_can( 'read_post', $post_id ) ) {
            return;
        }
        if ( 'revision' === $post->post_type && ! current_user_can( 'edit_post', (int) $post->post_parent ) ) {
            return;
        }
        throw new \InvalidArgumentException( Post_Type_Tool_Hint::other_type( $post, $expected_type, $verb, $this->get_name() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    protected function precheck_other_type( array $arguments, string $id_key, string $expected_type, string $verb ): void {
        try {
            $post_id = $this->parse_required_id( $arguments[ $id_key ] ?? null, $id_key );
        } catch ( \InvalidArgumentException $e ) {
            return;
        }
        $this->refuse_other_type( $post_id, $expected_type, $verb );
    }

    protected function refuse_delete_value_read_as_empty( array $arguments ): void {
        $value = $arguments['value'] ?? null;
        if ( is_string( $value ) && '' !== $value && '' === wp_unslash( $value ) ) {
            throw new \InvalidArgumentException( 'A value of one backslash is refused: WordPress removes the backslash before it matches the value, so it would delete every row of the key. To delete every row, omit value.' );
        }
    }

    protected function refuse_builder_owned_write( int $post_id, bool $content, array $meta = array() ): void {
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $adapter = \Easy_MCP_AI\Builders\Builder_Registry::for_post( $post );
        if ( null === $adapter ) {
            return;
        }
        if ( $content && ! $adapter->renders_post_content( $post ) && ! $adapter->released_by( $meta ) ) {
            throw new \InvalidArgumentException( sprintf( 'Post %d is built with %s, which shows its own layout, not the post content, so this content edit would not appear. Edit the layout with wp_update_page_layout.', $post_id, $adapter->label() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $layout = array_values( array_intersect( array_map( 'strval', array_keys( $meta ) ), $adapter->layout_meta_keys() ) );
        if ( array() !== $layout ) {
            throw new \InvalidArgumentException( sprintf( 'Post %d is built with %s: writing %s directly leaves the page showing its old layout. Edit the layout with wp_update_page_layout.', $post_id, $adapter->label(), implode( ', ', $layout ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    protected function refuse_builder_owned_product_writes( array $items ): void {
        foreach ( $items as $item ) {
            $product_id = is_array( $item ) && isset( $item['id'] ) && is_scalar( $item['id'] ) ? (int) $item['id'] : 0;
            if ( $product_id <= 0 ) {
                continue;
            }
            $meta = array();
            foreach ( isset( $item['meta_data'] ) && is_array( $item['meta_data'] ) ? $item['meta_data'] : array() as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }
                if ( isset( $entry['key'] ) && is_scalar( $entry['key'] ) ) {
                    $meta[ (string) $entry['key'] ] = $entry['value'] ?? null;
                }
                $row = isset( $entry['id'] ) && is_scalar( $entry['id'] ) && (int) $entry['id'] > 0 ? get_metadata_by_mid( 'post', (int) $entry['id'] ) : false;
                if ( is_object( $row ) && isset( $row->meta_key, $row->post_id ) && (int) $row->post_id === $product_id ) {
                    $meta[ (string) $row->meta_key ] = $entry['value'] ?? null;
                }
            }
            $this->refuse_builder_owned_write( $product_id, array_key_exists( 'description', $item ), $meta );
        }
    }

    protected function rest_taxonomies_for( $object_type ) {
        $map = array();
        foreach ( (array) get_object_taxonomies( $object_type, 'objects' ) as $key => $tax ) {
            if ( ! is_object( $tax ) || empty( $tax->show_in_rest ) ) {
                continue;
            }
            $slug         = ! empty( $tax->name ) ? (string) $tax->name : (string) $key;
            $map[ $slug ] = ! empty( $tax->rest_base ) ? (string) $tax->rest_base : $slug;
        }
        return $map;
    }

    protected function item_taxonomy_terms( array $item, array $map ) {
        $terms = array();
        foreach ( $map as $slug => $rest_base ) {
            $ids = isset( $item[ $rest_base ] ) && is_array( $item[ $rest_base ] ) ? $item[ $rest_base ] : array();
            $terms[ $slug ] = array_values( array_map( 'intval', $ids ) );
        }
        return empty( $terms ) ? new \stdClass() : $terms;
    }

    protected function parse_taxonomy_filter( $value, $label, array $map ) {
        $filters = $this->parse_json_param( $value, $label );
        $params  = array();
        foreach ( $filters as $slug => $ids ) {
            $slug = (string) $slug;
            if ( ! isset( $map[ $slug ] ) ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        '%s: unknown taxonomy "%s". Valid taxonomies: %s.',
                        $label, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        $slug, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        empty( $map ) ? '(none exposed in the REST API)' : implode( ', ', array_keys( $map ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    )
                );
            }
            if ( is_scalar( $ids ) ) {
                $ids = array( $ids );
            }
            $clean = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
            if ( ! empty( $clean ) ) {
                $params[ $map[ $slug ] ] = $clean;
            }
        }
        return $params;
    }

    protected function refuse_non_rest_taxonomy( $taxonomy, $tax_obj ) {
        if ( ! empty( $tax_obj->show_in_rest ) ) {
            return;
        }
        $message = sprintf( 'Taxonomy %s is not exposed in the REST API (show_in_rest is false), so its terms cannot be changed here.', $taxonomy );
        if ( 0 === strpos( (string) $taxonomy, 'pa_' ) ) {
            $message .= ' Attribute values: Products > Attributes > Configure terms.';
        }
        throw new \InvalidArgumentException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    protected static function image_filename_from_bytes( string $filename, string $path ): ?string {
        if ( ! function_exists( 'wp_get_image_mime' ) ) {
            return null;
        }
        $mime = wp_get_image_mime( $path );
        return is_string( $mime ) ? self::image_filename_for_type( $filename, $mime ) : null;
    }

    protected static function image_filename_for_type( string $filename, string $mime ): ?string {
        $mime = strtolower( trim( $mime ) );
        if ( 0 !== strpos( $mime, 'image/' ) || 'image/svg+xml' === $mime || ! function_exists( 'get_allowed_mime_types' ) ) {
            return null;
        }
        foreach ( (array) get_allowed_mime_types() as $extensions => $type ) {
            if ( $type === $mime ) {
                $base = (string) pathinfo( $filename, PATHINFO_FILENAME );
                return ( '' !== $base ? $base : 'image' ) . '.' . explode( '|', (string) $extensions )[0];
            }
        }
        return null;
    }

    protected function refuse_missing_post( $post_id ) {
        if ( ! get_post( $post_id ) ) {
            throw new \InvalidArgumentException( sprintf( 'Post %d not found.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    protected function resolve_post_rest_base( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            throw new \RuntimeException( 'Post not found.' );
        }

        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || empty( $post_type_object->show_in_rest ) ) {
            throw new \RuntimeException(
                sprintf( 'Post type "%s" is not available via the REST API.', $post->post_type ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        return ! empty( $post_type_object->rest_base )
            ? $post_type_object->rest_base
            : $post->post_type;
    }

    protected function discover_global_styles_id() {
        $stylesheet = get_stylesheet();
        $request    = new \WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/' . $stylesheet );
        $response   = rest_do_request( $request );

        if ( ! $response->is_error() ) {
            $data = $response->get_data();
            if ( ! empty( $data['id'] ) ) {
                return (int) $data['id'];
            }
        }

        if ( class_exists( 'WP_Theme_JSON_Resolver' )
             && method_exists( 'WP_Theme_JSON_Resolver', 'get_user_global_styles_post_id' ) ) {
            $id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
            if ( $id ) {
                return (int) $id;
            }
        }

        throw new \RuntimeException(
            'Could not discover global styles. This requires WordPress 6.1 or later with an active block theme.'
        );
    }

    protected function fetch_style_variations() {
        $stylesheet = get_stylesheet();
        $request    = new \WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/' . $stylesheet . '/variations' );
        $response   = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException(
                    'Style variations endpoint is not available. This requires WordPress 6.0 or later with an active block theme (Full Site Editing).'
                );
            }
            throw new \RuntimeException( $wp_error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $variations = array();
        foreach ( (array) $response->get_data() as $variation ) {
            if ( ! is_array( $variation ) ) {
                continue;
            }
            $title = isset( $variation['title'] ) && is_scalar( $variation['title'] ) ? (string) $variation['title'] : '';
            $slug  = isset( $variation['slug'] ) && is_string( $variation['slug'] ) && '' !== $variation['slug']
                ? $variation['slug']
                : ( function_exists( '_wp_to_kebab_case' ) ? _wp_to_kebab_case( $title ) : sanitize_title( $title ) );
            if ( '' === $slug ) {
                continue;
            }
            $settings = isset( $variation['settings'] ) && is_array( $variation['settings'] ) ? $variation['settings'] : array();
            $styles   = isset( $variation['styles'] ) && is_array( $variation['styles'] ) ? $variation['styles'] : array();

            $variations[] = array(
                'slug'     => $slug,
                'title'    => $title,
                'scope'    => $this->style_variation_scope( $settings, $styles ),
                'settings' => $settings,
                'styles'   => $styles,
            );
        }

        return $this->disambiguate_style_variation_slugs( $variations );
    }

    protected function style_variation_scope( array $settings, array $styles ) {
        $touched = array();
        $this->collect_style_variation_categories( $settings, $touched );
        $this->collect_style_variation_categories( $styles, $touched );

        $keys = array_keys( $touched );
        if ( array( 'color' ) === $keys ) {
            return 'color';
        }
        if ( array( 'typography' ) === $keys ) {
            return 'typography';
        }
        return 'full';
    }

    private function collect_style_variation_categories( array $node, array &$touched ) {
        foreach ( $node as $key => $value ) {
            if ( 'color' === $key || 'typography' === $key ) {
                $touched[ $key ] = true;
                continue;
            }
            if ( is_array( $value ) ) {
                $this->collect_style_variation_categories( $value, $touched );
            } else {
                $touched['other'] = true;
            }
        }
    }

    private function disambiguate_style_variation_slugs( array $variations ) {
        $scopes_per_slug = array();
        foreach ( $variations as $variation ) {
            $scopes_per_slug[ $variation['slug'] ][ $variation['scope'] ] = true;
        }

        $taken = array();
        foreach ( $variations as &$variation ) {
            if ( count( $scopes_per_slug[ $variation['slug'] ] ) > 1 && 'full' !== $variation['scope'] ) {
                $variation['slug'] .= '-' . $variation['scope'];
            }
            $base = $variation['slug'];
            $slug = $base;
            for ( $n = 2; isset( $taken[ $slug ] ); $n++ ) {
                $slug = $base . '-' . $n;
            }
            $taken[ $slug ]    = true;
            $variation['slug'] = $slug;
        }
        unset( $variation );

        return $variations;
    }

    protected function refuse_webhook_api_version( array $arguments ): void {
        if ( array_key_exists( 'api_version', $arguments ) ) {
            throw new \InvalidArgumentException( '"api_version" cannot be set: the WooCommerce REST API ignores it. A new webhook gets the /wc/v3 route default and an update keeps the stored version. Send the call again without it.' );
        }
    }

    protected function validate_webhook_url( $url ) {
        $url    = is_string( $url ) ? trim( $url ) : '';
        $parsed = wp_parse_url( $url );

        if ( ! $parsed || empty( $parsed['host'] ) ) {
            throw new \InvalidArgumentException( 'Invalid webhook delivery URL.' );
        }

        $scheme = strtolower( isset( $parsed['scheme'] ) ? $parsed['scheme'] : '' );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            throw new \InvalidArgumentException( 'Webhook delivery URL must use http or https.' );
        }

        $host = $parsed['host'];
        if ( '[' === $host[0] ) {
            $host = trim( $host, '[]' );
        }

        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            $resolved_ip = $host;
        } else {
            $resolved_ip = gethostbyname( $host );
            if ( $resolved_ip === $host ) {
                throw new \InvalidArgumentException(
                    'Webhook delivery URL hostname could not be resolved.' // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
        }

        $is_public = filter_var(
            $resolved_ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ( false === $is_public ) {
            throw new \InvalidArgumentException(
                'Webhook delivery URL cannot target private or reserved IP ranges.' // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        return esc_url_raw( $url );
    }

    protected function invalidate_post_cache( $post_id, array $context = array() ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return;
        }

        if ( isset( self::$already_invalidated[ $post_id ] ) ) {
            return;
        }
        self::$already_invalidated[ $post_id ] = true;

        if ( function_exists( 'clean_post_cache' ) ) {
            clean_post_cache( $post_id );
        }

        $context = wp_parse_args(
            $context,
            array(
                'source' => 'mcp',
                'tool'   => $this->get_name(),
            )
        );

        do_action( 'easy_mcp_ai_post_changed', $post_id, $context );
    }

    protected function clear_page_cache( array $post_ids, array $urls = array() ): void {
        do_action( 'easy_mcp_ai_clear_page_cache', $post_ids, $urls );
    }

    public static function reset_cache_invalidation_state() {
        self::$already_invalidated = array();
    }

    protected function pagination_meta( $response, $page, $per_page, $count = 0 ) {
        $headers  = ( is_object( $response ) && method_exists( $response, 'get_headers' ) ) ? $response->get_headers() : array();
        $per_page = max( 1, (int) $per_page );
        $total    = isset( $headers['X-WP-Total'] ) ? (int) $headers['X-WP-Total'] : (int) $count;
        $total_pages = isset( $headers['X-WP-TotalPages'] )
            ? (int) $headers['X-WP-TotalPages']
            : (int) ceil( $total / $per_page );

        return array(
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => (int) $page,
            'per_page'    => $per_page,
        );
    }

    protected function is_invalid_page_error( $error ) {
        return \is_wp_error( $error ) && false !== strpos( (string) $error->get_error_code(), 'invalid_page_number' );
    }

    protected function build_search_snippet( $content, $query, $length = 200 ) {
        $length  = max( 20, min( 1000, (int) $length ) );
        $content = (string) $content;
        if ( '' === $content ) {
            return '';
        }
        $cap = 20000;
        if ( strlen( $content ) > $cap ) {
            $window = min( $cap, $length + 4000 );
            $raw_needle = trim( (string) $query );
            $use_mb  = function_exists( 'mb_stripos' ) && function_exists( 'mb_substr' );
            $raw_pos = false;
            if ( '' !== $raw_needle ) {
                $raw_pos = $use_mb
                    ? mb_stripos( $content, $raw_needle, 0, 'UTF-8' )
                    : stripos( $content, $raw_needle );
            }
            $raw_start = ( false !== $raw_pos ) ? (int) max( 0, $raw_pos - 2000 ) : 0;
            $content   = $use_mb
                ? mb_substr( $content, $raw_start, $window, 'UTF-8' )
                : substr( $content, $raw_start, $window );
        }

        $text = function_exists( 'strip_shortcodes' ) ? strip_shortcodes( $content ) : $content;
        $text = preg_replace( '/<!--.*?-->/s', ' ', $text );
        $text = wp_strip_all_tags( (string) $text );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $collapsed = preg_replace( '/\s+/u', ' ', $text );
        if ( null === $collapsed ) {
            $collapsed = preg_replace( '/\s+/', ' ', $text );
        }
        $text = trim( (string) $collapsed );
        if ( '' === $text ) {
            return '';
        }

        $have_mb = function_exists( 'mb_stripos' ) && function_exists( 'mb_substr' ) && function_exists( 'mb_strlen' );

        $pos    = false;
        $needle = trim( (string) $query );
        if ( '' !== $needle ) {
            $pos = $have_mb ? mb_stripos( $text, $needle, 0, 'UTF-8' ) : stripos( $text, $needle );
            if ( false === $pos && false !== strpos( $needle, ' ' ) ) {
                $first_word = strtok( $needle, ' ' );
                if ( '' !== (string) $first_word ) {
                    $pos = $have_mb ? mb_stripos( $text, $first_word, 0, 'UTF-8' ) : stripos( $text, $first_word );
                }
            }
        }
        if ( false === $pos ) {
            $pos = 0;
        }

        $total = $have_mb ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
        if ( $total <= $length ) {
            return $text;
        }

        $start = (int) max( 0, $pos - (int) floor( $length / 2 ) );
        if ( $start + $length > $total ) {
            $start = (int) max( 0, $total - $length );
        }
        $snippet = $have_mb ? mb_substr( $text, $start, $length, 'UTF-8' ) : substr( $text, $start, $length );
        $snippet = trim( (string) $snippet );

        $prefix = $start > 0 ? "\xE2\x80\xA6" : '';
        $suffix = ( $start + $length ) < $total ? "\xE2\x80\xA6" : '';
        return $prefix . $snippet . $suffix;
    }

    protected function apply_meta_argument( array $arguments, array &$params ) {
        if ( ! isset( $arguments['meta'] ) ) {
            return false;
        }
        $meta = $this->parse_json_param( $arguments['meta'], 'meta' );
        if ( array() === $meta ) {
            return false;
        }
        if ( array_keys( $meta ) === range( 0, count( $meta ) - 1 ) ) {
            throw new \InvalidArgumentException( 'meta must be an object of meta key-value pairs, not a list.' );
        }
        $params['meta'] = $meta;
        return true;
    }

    protected function ignored_meta_report( array $sent, $persisted, string $object_type = 'post', string $post_type = '' ): array {
        $registered = self::takes_meta( $object_type, $post_type ) ? self::rest_meta_names( $object_type, $post_type ) : array();
        $persisted  = is_array( $persisted ) ? $persisted : array();
        $ignored    = array();
        foreach ( array_keys( $sent ) as $key ) {
            if ( ! array_key_exists( $key, $persisted ) && ! isset( $registered[ $key ] ) ) {
                $ignored[] = (string) $key;
            }
        }
        if ( empty( $ignored ) ) {
            return array();
        }
        $scopes = array(
            'post'    => 'this post type',
            'comment' => 'comments',
        );
        $notice = sprintf(
            'The following meta keys were sent but not persisted (they are not registered with show_in_rest=true for %1$s): %2$s.',
            $scopes[ $object_type ],
            implode( ', ', $ignored )
        );
        $explained = array();
        if ( class_exists( '\\Easy_MCP_AI\\Meta\\Meta_Exposure' ) ) {
            $explained = \Easy_MCP_AI\Meta\Meta_Exposure::explain_ignored_keys( $ignored );
            if ( ! empty( $explained ) ) {
                $notice .= ' ' . implode( ' ', array_values( $explained ) );
            }
        }
        if ( 'post' === $object_type ) {
            $acf = self::acf_field_hint( array_values( array_diff( $ignored, array_keys( $explained ) ) ) );
            if ( '' !== $acf ) {
                $notice .= ' ' . $acf;
            }
        }
        return array(
            'meta_ignored' => $ignored,
            'notice'       => $notice,
        );
    }

    private static function takes_meta( string $object_type, string $post_type ): bool {
        if ( 'comment' === $object_type ) {
            return true;
        }
        $fields = self::rest_item_fields( get_post_type_object( $post_type ) );
        return null !== $fields && in_array( 'meta', $fields, true );
    }

    public static function rest_item_fields( ?object $post_type_object ): ?array {
        if ( ! is_object( $post_type_object ) || ! method_exists( $post_type_object, 'get_rest_controller' ) ) {
            return null;
        }
        $controller = $post_type_object->get_rest_controller();
        if ( ! is_object( $controller ) || ! method_exists( $controller, 'get_item_schema' ) ) {
            return null;
        }
        $schema = $controller->get_item_schema();
        if ( ! is_array( $schema ) || ! isset( $schema['properties'] ) || ! is_array( $schema['properties'] ) ) {
            return null;
        }
        return array_map( 'strval', array_keys( $schema['properties'] ) );
    }

    private static function rest_meta_names( string $object_type, string $post_type ): array {
        $fields = 'comment' === $object_type ? new \WP_REST_Comment_Meta_Fields() : new \WP_REST_Post_Meta_Fields( $post_type );
        return $fields->get_field_schema()['properties'];
    }

    private static function acf_field_hint( array $keys ): string {
        if ( ! function_exists( 'acf_get_field' ) ) {
            return '';
        }
        if ( ! Post_Type_Tool_Hint::may_use( 'wp_acf_update_fields' ) ) {
            return '';
        }
        $fields = array();
        foreach ( $keys as $key ) {
            if ( is_array( acf_get_field( $key ) ) ) {
                $fields[] = $key;
            }
        }
        if ( empty( $fields ) ) {
            return '';
        }
        return sprintf( 'ACF fields are not saved through meta: %s. Write them with wp_acf_update_fields (post_id, post_type, and fields as { field name: value }).', implode( ', ', $fields ) );
    }

    protected function require_compat_shim( string $id, array $arguments ): void {
        $registry = '\\Easy_MCP_AI\\Compat\\Compat_Registry';
        if ( class_exists( $registry ) && $registry::is_armed( $id, $this, $arguments ) ) {
            return;
        }
        throw new \RuntimeException( 'This tool cannot run safely right now: its data-protection step did not start, so nothing was changed. Please report this to the site administrator.' );
    }

    protected function find_posts_by_title( string $post_type, string $title, int $limit = 5 ): array {
        if ( '' === trim( $title ) ) {
            return array();
        }
        $query = new \WP_Query(
            array(
                'post_type'              => $post_type,
                'title'                  => $title,
                'post_status'            => array_values( array_diff( get_post_stati(), array( 'trash', 'auto-draft', 'inherit' ) ) ),
                'posts_per_page'         => max( 1, $limit ),
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );
        return array_values( array_filter( array_map( 'absint', (array) $query->posts ) ) );
    }

    protected function refuse_existing_title( string $post_type, string $name, array $meta_keys, string $noun, string $usage, string $list_tool ): void {
        $existing = $this->find_posts_by_title( $post_type, $name, 1 );
        if ( ! $existing ) {
            return;
        }
        $id      = $existing[0];
        $details = array();
        if ( current_user_can( 'read_post', $id ) ) {
            foreach ( $meta_keys as $meta_key ) {
                $value = get_post_meta( $id, $meta_key, true );
                if ( is_string( $value ) && '' !== $value ) {
                    $details[] = $value;
                }
            }
        }
        $label = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, self::EXISTING_TITLE_LABEL_LENGTH ) : substr( $name, 0, self::EXISTING_TITLE_LABEL_LENGTH );
        throw new \Easy_MCP_AI\MCP\Detailed_Tool_Error( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            sprintf( '%1$s "%2$s" already exists (ID %3$d). Nothing was changed.', ucfirst( $noun ), $label, $id ),
            sprintf(
                'Existing %1$s %2$d%3$s. %4$s To create a second %1$s with the same name anyway, call this tool again with `allow_duplicate` set to true. Use `%5$s` with `search` to see every match.',
                $noun,
                $id,
                $details ? ' (' . implode( ', ', $details ) . ')' : '',
                $usage,
                $list_tool
            )
        );
    }

    protected function allows_duplicate( array $arguments ): bool {
        return ! empty( $arguments['allow_duplicate'] ) && (bool) rest_sanitize_boolean( $arguments['allow_duplicate'] );
    }

    protected function strip_response_noise( $reply ) {
        if ( ! is_array( $reply ) ) {
            return $reply;
        }
        $drop      = array_flip( self::response_noise_keys() );
        $strip_row = static function ( $row ) use ( $drop ) {
            return is_array( $row ) ? array_diff_key( $row, $drop ) : $row;
        };
        if ( self::is_batch_reply( $reply ) ) {
            foreach ( $reply as $operation => $items ) {
                $reply[ $operation ] = is_array( $items ) ? array_map( $strip_row, $items ) : $items;
            }
            return $reply;
        }
        return array_values( $reply ) === $reply ? array_map( $strip_row, $reply ) : $strip_row( $reply );
    }

    protected static function response_noise_keys(): array {
        $keys = apply_filters( 'easy_mcp_ai_response_noise_keys', self::RESPONSE_NOISE_KEYS );
        if ( ! is_array( $keys ) ) {
            return self::RESPONSE_NOISE_KEYS;
        }
        $keys = array_filter(
            $keys,
            static function ( $key ) {
                return is_string( $key ) && '' !== $key;
            }
        );
        return array_values( array_diff( $keys, self::RESPONSE_NOISE_PROTECTED_KEYS ) );
    }

    private static function is_batch_reply( array $reply ): bool {
        return ! empty( $reply ) && array() === array_diff( array_keys( $reply ), array( 'create', 'update', 'delete' ) );
    }

    protected function rest_request( $method, $route, $params = array(), $fields = null ) {
        return $this->dispatch_rest_request( self::build_rest_request( $method, $route, $params, $fields ) );
    }

    protected static function build_rest_request( $method, $route, $params = array(), $fields = null ) {
        $request = new \WP_REST_Request( $method, $route );
        if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && ! empty( $params ) ) {
            $request->set_header( 'content-type', 'application/json' );
            $request->set_body( wp_json_encode( $params ) );
        } else {
            foreach ( $params as $key => $value ) {
                $request->set_param( $key, $value );
            }
        }
        if ( null !== $fields ) {
            $request->set_param( '_fields', $fields );
        }
        return $request;
    }

    protected function dispatch_rest_request( \WP_REST_Request $request ) {
        return $this->dispatch_rest_response( $request )->get_data();
    }

    protected function rest_response( $method, $route, $params = array() ) {
        return $this->dispatch_rest_response( self::build_rest_request( $method, $route, $params ) );
    }

    private function dispatch_rest_response( \WP_REST_Request $request ) {
        $method   = $request->get_method();
        $route    = $request->get_route();
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            $message = self::rest_error_message_or_empty( $response );
            if ( '' === $message ) {
                throw new \RuntimeException( self::rest_status_message( $method, $route, $response ), self::REST_ERROR_WITHOUT_MESSAGE ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            throw new \RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $response;
    }

    protected static function rest_status_message( $method, $route, $response ) {
        $status = (int) $response->get_status();
        return sprintf( '%s %s answered HTTP %s with no error message.', $method, $route, trim( $status . ' ' . get_status_header_desc( $status ) ) );
    }

    protected static function rest_error_message_or_empty( $response ) {
        $data = $response->get_data();
        if ( is_array( $data ) && ! isset( $data['code'] ) && ! isset( $data['message'] ) ) {
            return '';
        }
        $error = $response->as_error();
        return $error ? trim( (string) self::rest_error_message( $error ) ) : '';
    }

    protected static function rest_error_message( $error ) {
        $message = $error->get_error_message();
        if ( 'rest_invalid_param' !== $error->get_error_code() ) {
            return $message;
        }
        $data    = $error->get_error_data();
        $reasons = array();
        if ( is_array( $data ) && isset( $data['params'] ) && is_array( $data['params'] ) ) {
            foreach ( $data['params'] as $reason ) {
                if ( is_string( $reason ) && '' !== trim( $reason ) && false === strpos( $message, $reason ) ) {
                    $reasons[] = trim( $reason );
                }
            }
        }
        return empty( $reasons ) ? $message : $message . ' — ' . implode( ' ', $reasons );
    }
}
