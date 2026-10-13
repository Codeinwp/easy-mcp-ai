<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        return 'Returns the complete view of a post in ONE call: post fields + all meta + all taxonomy terms grouped by taxonomy + featured image data + author data. Replaces 4–20 separate calls. Required: `id` (the post ID — called `post_id` on some sibling tools). Optional: `post_type` (REST base, default `posts`; same concept as `rest_base` on the CPT tools), `include` (subset of [meta, terms, thumbnail, author] — omit for all), `exclude` (e.g. ["content"] to drop large post_content), `context` (view|edit, default view; edit exposes protected fields/meta and requires edit_post cap). Caps: 100 terms per taxonomy (filter: easy_mcp_ai_post_full_max_terms_per_taxonomy), 200 meta keys (filter: easy_mcp_ai_post_full_max_meta_keys). Response includes terms_truncated / meta_truncated / taxonomies_truncated flags when the respective caps are hit. SEO head markup (yoast_head*, aioseo_head*, aioseo_breadcrumb*) is omitted from `post`: saved Yoast fields are in `meta` (context=edit), and AIOSEO\'s stay in post.aioseo_meta_data. For the rendered head, use the Yoast SEO or AIOSEO tool for a post when one is listed.';
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
                    'description' => 'The REST base for the post type (e.g. posts, pages, products). Default: posts.',
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
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'id' ) );
        $post_id   = $this->parse_required_id( $arguments['id'], 'id' );
        $post_type = ! empty( $arguments['post_type'] )
            ? $this->validate_rest_route_segment( $arguments['post_type'], 'post_type' )
            : 'posts';

        
        $context = isset( $arguments['context'] ) && '' !== $arguments['context'] ? $arguments['context'] : 'view';
        $this->refuse_unless_one_of( 'context', $context, array( 'view', 'edit' ), $context, 'view or edit' );

        if ( 'edit' === $context && ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( 'You do not have permission to view this post in edit context.' );
        }

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

        
        $post = $this->strip_response_noise(
            $this->rest_request(
                'GET',
                '/wp/v2/' . $post_type . '/' . $post_id,
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

        
        if ( in_array( 'meta', $include, true ) ) {
            $meta_cap = (int) apply_filters( 'easy_mcp_ai_post_full_max_meta_keys', self::DEFAULT_MAX_META_KEYS );
            $all_meta = get_post_meta( $post_id );
            if ( ! is_array( $all_meta ) ) {
                $all_meta = array();
            }

            
            if ( 'edit' !== $context ) {
                foreach ( array_keys( $all_meta ) as $key ) {
                    if ( is_protected_meta( $key, 'post' ) ) {
                        unset( $all_meta[ $key ] );
                    }
                }
            }

            $meta_truncated = false;
            if ( $meta_cap > 0 && count( $all_meta ) > $meta_cap ) {
                $all_meta       = array_slice( $all_meta, 0, $meta_cap, true );
                $meta_truncated = true;
            }

            
            
            $normalized = array();
            foreach ( $all_meta as $key => $values ) {
                $first = is_array( $values ) ? ( $values[0] ?? null ) : $values;
                $normalized[ $key ] = $this->normalize_meta_value( $first );
            }

            $result['meta'] = empty( $normalized ) ? new \stdClass() : $normalized;
            if ( $meta_truncated ) {
                $result['meta_truncated'] = true;
            }
        }

        
        if ( in_array( 'terms', $include, true ) ) {
            $terms_cap   = (int) apply_filters( 'easy_mcp_ai_post_full_max_terms_per_taxonomy', self::DEFAULT_MAX_TERMS_PER_TAXONOMY );
            $tax_cap     = (int) apply_filters( 'easy_mcp_ai_post_full_max_taxonomies', self::DEFAULT_MAX_TAXONOMIES );
            $taxonomies  = get_object_taxonomies( $post_object->post_type, 'names' );
            $terms_by_tax = array();
            $truncated    = array();
            $tax_truncated = false;

            if ( is_array( $taxonomies ) && $tax_cap > 0 && count( $taxonomies ) > $tax_cap ) {
                $taxonomies    = array_slice( $taxonomies, 0, $tax_cap );
                $tax_truncated = true;
            }

            if ( is_array( $taxonomies ) ) {
                foreach ( $taxonomies as $taxonomy ) {
                    $args = array( 'fields' => 'all' );
                    if ( $terms_cap > 0 ) {
                        
                        $args['number'] = $terms_cap + 1;
                    }
                    $terms = wp_get_object_terms( $post_id, $taxonomy, $args );
                    if ( is_wp_error( $terms ) || empty( $terms ) ) {
                        continue;
                    }

                    if ( $terms_cap > 0 && count( $terms ) > $terms_cap ) {
                        $terms              = array_slice( $terms, 0, $terms_cap );
                        $truncated[ $taxonomy ] = true;
                    }

                    $shaped = array();
                    foreach ( $terms as $t ) {
                        $shaped[] = array(
                            'id'   => (int) $t->term_id,
                            'name' => (string) $t->name,
                            'slug' => (string) $t->slug,
                        );
                    }
                    $terms_by_tax[ $taxonomy ] = $shaped;
                }
            }

            $result['terms'] = empty( $terms_by_tax ) ? new \stdClass() : $terms_by_tax;
            if ( ! empty( $truncated ) ) {
                $result['terms_truncated'] = $truncated;
            }
            if ( $tax_truncated ) {
                $result['taxonomies_truncated'] = true;
            }
        }

        
        if ( in_array( 'thumbnail', $include, true ) ) {
            $thumb_id = (int) get_post_thumbnail_id( $post_id );
            if ( $thumb_id > 0 ) {
                $src = wp_get_attachment_image_src( $thumb_id, 'full' );
                $alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
                $result['thumbnail'] = array(
                    'id'         => $thumb_id,
                    'source_url' => is_array( $src ) && isset( $src[0] ) ? (string) $src[0] : '',
                    'alt_text'   => is_string( $alt ) ? $alt : '',
                );
            } else {
                $result['thumbnail'] = null;
            }
        }

        
        if ( in_array( 'author', $include, true ) ) {
            $author_id = (int) $post_object->post_author;
            $user      = $author_id > 0 ? get_userdata( $author_id ) : false;
            if ( $user ) {
                $result['author'] = array(
                    'id'   => (int) $user->ID,
                    'name' => (string) $user->display_name,
                    'slug' => (string) $user->user_nicename,
                );
            } else {
                $result['author'] = null;
            }
        }

        return $result;
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
}
