<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Post_Type_Rest_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( Post_Type_Rest_Base::class, false ) ) {
    require_once dirname( __DIR__ ) . '/class-post-type-rest-base.php';
}









class Get_Post_Full extends Base_Tool {

    const DEFAULT_MAX_TERMS_PER_TAXONOMY = 100;
    const DEFAULT_MAX_META_KEYS          = 200;
    const DEFAULT_MAX_TAXONOMIES         = 30;
    const ALLOWED_SECTIONS               = array( 'meta', 'terms', 'thumbnail', 'author' );

    public function get_name() {
        return 'wp_get_post_full';
    }

    public function get_description() {
        return 'Returns the complete view of a post in ONE call: post fields + all meta + all taxonomy terms grouped by taxonomy + featured image data + author data. Replaces 4–20 separate calls. Required: `id` (the post ID — called `post_id` on some sibling tools). Optional: `post_type` (REST base, default `posts`; same concept as `rest_base` on the CPT tools), `include` (subset of [meta, terms, thumbnail, author] — omit for all), `exclude` (e.g. ["content"] to drop large post_content), `context` (view|edit, default view; edit exposes protected fields/meta and requires edit_post cap), `raw_post` (boolean, edit context only: adds `raw_post`, every database column of the post as stored — raw post_title, to_ping, pinged, post_content_filtered, post_mime_type, comment_count and the rest), `meta_keys` (array: return only these meta keys, so the key cap cannot hide one), `meta_all_values` (boolean: each meta key maps to the list of all its stored values instead of the first), `terms_taxonomies` (array: only these taxonomies in `terms`), `full_terms` (boolean: each term also carries term_taxonomy_id, description, parent, count and term_group). Meta values are the stored strings (serialized values are not unserialized). Caps: 100 terms per taxonomy (filter: easy_mcp_ai_post_full_max_terms_per_taxonomy), 200 meta keys (filter: easy_mcp_ai_post_full_max_meta_keys). Response includes terms_truncated / meta_truncated / taxonomies_truncated flags when the respective caps are hit. SEO head markup (yoast_head*, aioseo_head*, aioseo_breadcrumb*) is omitted from `post`: saved Yoast fields are in `meta` (context=edit), and AIOSEO\'s stay in post.aioseo_meta_data. For the rendered head, use the Yoast SEO or AIOSEO tool for a post when one is listed.';
    }

    public function get_category() {
        return 'posts';
    }

    public function get_required_capability() {
        return 'read';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id'        => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post to retrieve.',
                ),
                'post_type' => array(
                    'type'        => 'string',
                    'description' => 'The REST base for the post type (e.g. posts, pages, product). Default: posts. A post_type slug is also accepted.',
                    'default'     => 'posts',
                ),
                'include'   => array(
                    'type'        => 'array',
                    'description' => 'Subset of [meta, terms, thumbnail, author] to include. Omit for all.',
                    'items'       => array( 'type' => 'string' ),
                ),
                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Schema-level field name only; not passed to get_posts().
                'exclude'   => array(
                    'type'        => 'array',
                    'description' => 'Top-level fields to drop from the post object (e.g. ["content"]).',
                    'items'       => array( 'type' => 'string' ),
                ),
                'context'   => array(
                    'type'        => 'string',
                    'description' => 'Response context. `edit` exposes protected fields/meta and requires edit_post.',
                    'enum'        => array( 'view', 'edit' ),
                    'default'     => 'view',
                ),
                'raw_post'  => array(
                    'type'        => 'boolean',
                    'description' => 'Edit context only. Adds raw_post: every database column of the post as WordPress stores it (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count, filter). Default false.',
                    'default'     => false,
                ),
                'meta_keys' => array(
                    'type'        => 'array',
                    'description' => 'Return only these meta keys (keys that are not stored are simply absent). Protected keys (starting with _) need context edit. Needs meta in include.',
                    'items'       => array( 'type' => 'string' ),
                ),
                'meta_all_values' => array(
                    'type'        => 'boolean',
                    'description' => 'When true, each meta key maps to the list of ALL its stored values (one per database row) instead of the first. Default false. Needs meta in include.',
                    'default'     => false,
                ),
                'terms_taxonomies' => array(
                    'type'        => 'array',
                    'description' => 'Return terms for these taxonomies only; each must be registered for the post\'s type. Needs terms in include.',
                    'items'       => array( 'type' => 'string' ),
                ),
                'full_terms' => array(
                    'type'        => 'boolean',
                    'description' => 'When true, each term also carries term_taxonomy_id, description, parent, count and term_group. Default false. Needs terms in include.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'id' ) );
        $post_id   = $this->parse_required_id( $arguments['id'], 'id' );
        $post_type = ! empty( $arguments['post_type'] )
            ? $this->resolve_post_type_rest_base( $arguments['post_type'], 'post_type' )
            : 'posts';

        $context                   = $this->read_context( $arguments, $post_id );
        list( $include, $exclude ) = self::sections( $arguments );
        $options                   = $this->section_options( $arguments, $context, $include );

        
        $post = $this->strip_response_noise(
            $this->rest_request(
                'GET',
                self::post_type_route( $post_type, $post_id, 'GET' ),
                array( 'context' => $context )
            )
        );

        
        foreach ( $exclude as $key ) {
            unset( $post[ $key ] );
        }

        $result = array( 'post' => $post );

        
        $post_object = get_post( $post_id );
        if ( ! $post_object ) {
            
            return $result;
        }
        $this->refuse_another_object( $post_object, $post_type );

        
        
        
        
        if ( $options['raw_post'] ) {
            $result['raw_post'] = get_object_vars( $post_object );
        }

        if ( null !== $options['terms_taxonomies'] ) {
            self::refuse_unregistered_taxonomies( $options['terms_taxonomies'], $post_object->post_type );
        }

        
        if ( in_array( 'meta', $include, true ) ) {
            $result = array_merge( $result, $this->meta_section( $post_id, $context, $options['meta_keys'], $options['meta_all_values'] ) );
        }

        
        if ( in_array( 'terms', $include, true ) ) {
            $result = array_merge( $result, self::terms_section( $post_id, $post_object->post_type, $options['terms_taxonomies'], $options['full_terms'] ) );
        }

        
        if ( in_array( 'thumbnail', $include, true ) ) {
            $result['thumbnail'] = self::thumbnail_section( $post_id );
        }

        
        if ( in_array( 'author', $include, true ) ) {
            $result['author'] = self::author_section( $post_object );
        }

        return $result;
    }

    














    private function refuse_another_object( object $post_object, string $rest_base ): void {
        $served = Post_Type_Rest_Base::require_served_type( $rest_base );
        if ( $served[0] !== $post_object->post_type ) {
            throw new \RuntimeException( sprintf( 'ID %1$d is not an item of the post type served at /wp/v2/%2$s, so its meta, terms, featured image and author were not read.', (int) $post_object->ID, $rest_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    









    private function read_context( array $arguments, int $post_id ): string {
        
        $context = isset( $arguments['context'] ) && '' !== $arguments['context'] ? $arguments['context'] : 'view';
        $this->refuse_unless_one_of( 'context', $context, array( 'view', 'edit' ), $context, 'view or edit' );

        if ( 'edit' === $context && ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( 'You do not have permission to view this post in edit context.' );
        }
        return $context;
    }

    







    private static function sections( array $arguments ): array {
        $include = isset( $arguments['include'] ) && is_array( $arguments['include'] )
            ? array_map( 'strval', $arguments['include'] )
            : self::ALLOWED_SECTIONS;
        $exclude = isset( $arguments['exclude'] ) && is_array( $arguments['exclude'] )
            ? array_map( 'strval', $arguments['exclude'] )
            : array();

        
        
        
        
        
        $unknown_include = array_values( array_diff( $include, self::ALLOWED_SECTIONS ) );
        if ( ! empty( $unknown_include ) ) {
            throw new \InvalidArgumentException( 'Unknown include value(s): ' . implode( ', ', $unknown_include ) . '. Allowed: ' . implode( ', ', self::ALLOWED_SECTIONS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array( $include, $exclude );
    }

    










    private function section_options( array $arguments, string $context, array $include ): array {
        $options = array(
            'raw_post'         => isset( $arguments['raw_post'] ) && rest_sanitize_boolean( $arguments['raw_post'] ),
            'meta_all_values'  => isset( $arguments['meta_all_values'] ) && rest_sanitize_boolean( $arguments['meta_all_values'] ),
            'full_terms'       => isset( $arguments['full_terms'] ) && rest_sanitize_boolean( $arguments['full_terms'] ),
            'meta_keys'        => $this->string_list( $arguments, 'meta_keys' ),
            'terms_taxonomies' => $this->string_list( $arguments, 'terms_taxonomies' ),
        );
        self::refuse_options_out_of_place( $options, $context, $include );
        self::refuse_protected_meta_keys( $options['meta_keys'], $context );
        return $options;
    }

    









    private static function refuse_options_out_of_place( array $options, string $context, array $include ): void {
        if ( $options['raw_post'] && 'edit' !== $context ) {
            throw new \InvalidArgumentException( 'raw_post needs context edit: the raw columns include the post password and unfiltered content, which WordPress shows only to users who can edit the post.' );
        }
        if ( ( null !== $options['meta_keys'] || $options['meta_all_values'] ) && ! in_array( 'meta', $include, true ) ) {
            throw new \InvalidArgumentException( 'meta_keys and meta_all_values apply to the meta section; add meta to include.' );
        }
        if ( ( null !== $options['terms_taxonomies'] || $options['full_terms'] ) && ! in_array( 'terms', $include, true ) ) {
            throw new \InvalidArgumentException( 'terms_taxonomies and full_terms apply to the terms section; add terms to include.' );
        }
    }

    








    private static function refuse_protected_meta_keys( ?array $meta_keys, string $context ): void {
        if ( null === $meta_keys || 'edit' === $context ) {
            return;
        }
        $protected = array();
        foreach ( $meta_keys as $key ) {
            if ( is_protected_meta( $key, 'post' ) ) {
                $protected[] = $key;
            }
        }
        if ( ! empty( $protected ) ) {
            throw new \InvalidArgumentException( 'meta_keys names protected meta (' . implode( ', ', $protected ) . '), which is returned only with context edit.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    private static function refuse_unregistered_taxonomies( array $terms_taxonomies, string $post_type ): void {
        $registered = get_object_taxonomies( $post_type, 'names' );
        $registered = is_array( $registered ) ? $registered : array();
        $unknown    = array_values( array_diff( $terms_taxonomies, $registered ) );
        if ( ! empty( $unknown ) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'terms_taxonomies: %s not registered for post type %s. Taxonomies: %s.',
                    implode( ', ', $unknown ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    $post_type, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    empty( $registered ) ? '(none)' : implode( ', ', $registered ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                )
            );
        }
    }

    









    private function meta_section( int $post_id, string $context, ?array $meta_keys, bool $meta_all_values ): array {
        $meta_cap = (int) apply_filters( 'easy_mcp_ai_post_full_max_meta_keys', self::DEFAULT_MAX_META_KEYS );
        $all_meta = get_post_meta( $post_id );
        if ( ! is_array( $all_meta ) ) {
            $all_meta = array();
        }

        
        if ( 'edit' !== $context ) {
            $all_meta = self::without_protected_meta( $all_meta );
        }

        
        
        if ( null !== $meta_keys ) {
            $all_meta = array_intersect_key( $all_meta, array_flip( $meta_keys ) );
        }

        $meta_truncated = false;
        if ( $meta_cap > 0 && count( $all_meta ) > $meta_cap ) {
            $all_meta       = array_slice( $all_meta, 0, $meta_cap, true );
            $meta_truncated = true;
        }

        $normalized = $this->normalized_meta( $all_meta, $meta_all_values );

        $section = array( 'meta' => empty( $normalized ) ? new \stdClass() : $normalized );
        if ( $meta_truncated ) {
            $section['meta_truncated'] = true;
        }
        return $section;
    }

    





    private static function without_protected_meta( array $all_meta ): array {
        foreach ( array_keys( $all_meta ) as $key ) {
            if ( is_protected_meta( $key, 'post' ) ) {
                unset( $all_meta[ $key ] );
            }
        }
        return $all_meta;
    }

    







    private function normalized_meta( array $all_meta, bool $meta_all_values ): array {
        $normalized = array();
        foreach ( $all_meta as $key => $values ) {
            if ( $meta_all_values ) {
                $list = array();
                foreach ( is_array( $values ) ? array_values( $values ) : array( $values ) as $value ) {
                    $list[] = $this->normalize_meta_value( $value );
                }
                $normalized[ $key ] = $list;
                continue;
            }
            $first = is_array( $values ) ? ( $values[0] ?? null ) : $values;
            $normalized[ $key ] = $this->normalize_meta_value( $first );
        }
        return $normalized;
    }

    










    private static function terms_section( int $post_id, string $post_type, ?array $terms_taxonomies, bool $full_terms ): array {
        $terms_cap                          = (int) apply_filters( 'easy_mcp_ai_post_full_max_terms_per_taxonomy', self::DEFAULT_MAX_TERMS_PER_TAXONOMY );
        list( $taxonomies, $tax_truncated ) = self::taxonomies_to_read( $post_type, $terms_taxonomies );
        $terms_by_tax                       = array();
        $truncated                          = array();

        foreach ( $taxonomies as $taxonomy ) {
            $found = self::taxonomy_terms( $post_id, $taxonomy, $terms_cap, $full_terms );
            if ( null === $found ) {
                continue;
            }
            if ( $found['truncated'] ) {
                $truncated[ $taxonomy ] = true;
            }
            $terms_by_tax[ $taxonomy ] = $found['terms'];
        }

        $section = array( 'terms' => empty( $terms_by_tax ) ? new \stdClass() : $terms_by_tax );
        if ( ! empty( $truncated ) ) {
            $section['terms_truncated'] = $truncated;
        }
        if ( $tax_truncated ) {
            $section['taxonomies_truncated'] = true;
        }
        return $section;
    }

    







    private static function taxonomies_to_read( string $post_type, ?array $terms_taxonomies ): array {
        $tax_cap    = (int) apply_filters( 'easy_mcp_ai_post_full_max_taxonomies', self::DEFAULT_MAX_TAXONOMIES );
        $taxonomies = null !== $terms_taxonomies ? $terms_taxonomies : get_object_taxonomies( $post_type, 'names' );
        $taxonomies = is_array( $taxonomies ) ? $taxonomies : array();
        if ( $tax_cap > 0 && count( $taxonomies ) > $tax_cap ) {
            return array( array_slice( $taxonomies, 0, $tax_cap ), true );
        }
        return array( $taxonomies, false );
    }

    









    private static function taxonomy_terms( int $post_id, string $taxonomy, int $terms_cap, bool $full_terms ): ?array {
        $args = array( 'fields' => 'all' );
        if ( $terms_cap > 0 ) {
            
            $args['number'] = $terms_cap + 1;
        }
        $terms = wp_get_object_terms( $post_id, $taxonomy, $args );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return null;
        }

        $truncated = false;
        if ( $terms_cap > 0 && count( $terms ) > $terms_cap ) {
            $terms     = array_slice( $terms, 0, $terms_cap );
            $truncated = true;
        }

        $shaped = array();
        foreach ( $terms as $t ) {
            $shaped[] = self::term_row( $t, $full_terms );
        }
        return array(
            'terms'     => $shaped,
            'truncated' => $truncated,
        );
    }

    







    private static function term_row( object $t, bool $full_terms ): array {
        $one = array(
            'id'   => (int) $t->term_id,
            'name' => (string) $t->name,
            'slug' => (string) $t->slug,
        );
        if ( $full_terms ) {
            $one['term_taxonomy_id'] = isset( $t->term_taxonomy_id ) ? (int) $t->term_taxonomy_id : 0;
            $one['description']      = isset( $t->description ) ? (string) $t->description : '';
            $one['parent']           = isset( $t->parent ) ? (int) $t->parent : 0;
            $one['count']            = isset( $t->count ) ? (int) $t->count : 0;
            $one['term_group']       = isset( $t->term_group ) ? (int) $t->term_group : 0;
        }
        return $one;
    }

    





    private static function thumbnail_section( int $post_id ): ?array {
        $thumb_id = (int) get_post_thumbnail_id( $post_id );
        if ( $thumb_id <= 0 ) {
            return null;
        }
        $src = wp_get_attachment_image_src( $thumb_id, 'full' );
        $alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
        return array(
            'id'         => $thumb_id,
            'source_url' => is_array( $src ) && isset( $src[0] ) ? (string) $src[0] : '',
            'alt_text'   => is_string( $alt ) ? $alt : '',
        );
    }

    





    private static function author_section( object $post_object ): ?array {
        $author_id = (int) $post_object->post_author;
        $user      = $author_id > 0 ? get_userdata( $author_id ) : false;
        if ( ! $user ) {
            return null;
        }
        return array(
            'id'   => (int) $user->ID,
            'name' => (string) $user->display_name,
            'slug' => (string) $user->user_nicename,
        );
    }

    






    private function normalize_meta_value( $value ) {
        if ( is_scalar( $value ) || null === $value ) {
            return $value;
        }
        $encoded = wp_json_encode( $value );
        if ( false === $encoded ) {
            return is_object( $value ) ? get_class( $value ) : '[unserializable]';
        }
        $decoded = json_decode( $encoded, true );
        return null === $decoded && 'null' !== $encoded ? (string) $value : $decoded;
    }

    







    private function string_list( array $arguments, string $key ): ?array {
        if ( ! isset( $arguments[ $key ] ) ) {
            return null;
        }
        $value = $arguments[ $key ];
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : array( $value );
        }
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an array of strings.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $out = array();
        foreach ( $value as $item ) {
            if ( ! is_string( $item ) || '' === $item ) {
                throw new \InvalidArgumentException( sprintf( '%s entries must be non-empty strings.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[] = $item;
        }
        return array_values( array_unique( $out ) );
    }
}
