<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Add_Post_Terms extends Base_Tool {

    public function get_name() {
        return 'wp_add_post_terms';
    }

    public function get_description() {
        return 'Adds (or replaces) terms on a post in a taxonomy registered for its post type; another taxonomy is refused and nothing changes. Required: `post_id`, `taxonomy`, `terms` (array of integer IDs or string slugs/names — wp_set_object_terms() handles both). Optional: `append` (boolean, default true — when true ADDS to existing terms; when false REPLACES the term set), `by` (auto|slug|id, default auto). With `by` auto, a purely numeric string in `terms` (e.g. a year-based slug like \'2026\') is coerced to a term ID; `by` slug treats every value as a slug or name, so \'2026\' stays a slug; `by` id treats every value as a term ID and refuses an ID that is not in the taxonomy. A slug or name that matches no term creates the term, as WordPress does — refused unless you may create terms there (edit_terms in a hierarchical taxonomy such as category, assign_terms in a flat one such as post_tag, the rule the REST API applies). Returns { post_id, taxonomy, terms_input: array<int|string>, term_taxonomy_ids: array<int>, append: bool, changed: bool (false when the post already had exactly this result), created_terms: array<string> (the values that created a new term) }. Verifies edit_post on the post and the taxonomy\'s assign_terms cap.';
    }

    public function get_category() {
        return 'posts';
    }

    public function get_required_capability() {
        return 'edit_posts';
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
                'post_id'  => array(
                    'type'        => 'integer',
                    'description' => 'The post ID to assign terms to.',
                ),
                'taxonomy' => array(
                    'type'        => 'string',
                    'description' => 'Taxonomy slug (e.g. category, post_tag, product_cat).',
                ),
                'terms'    => array(
                    'type'        => 'array',
                    'description' => 'Array of term IDs (integer) or slugs (string).',
                    'items'       => array(
                        'oneOf' => array(
                            array( 'type' => 'integer' ),
                            array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'append'   => array(
                    'type'        => 'boolean',
                    'description' => 'When true (default) adds to existing terms; when false replaces the term set.',
                    'default'     => true,
                ),
                'by'       => array(
                    'type'        => 'string',
                    'description' => 'How terms values are read. auto (default): integers and digit-only strings are term IDs, other strings slugs or names. slug: every value is a slug or name (a digit-only slug such as 2026 stays a slug). id: every value is a term ID, and an ID not in the taxonomy is refused.',
                    'enum'        => array( 'auto', 'slug', 'id' ),
                    'default'     => 'auto',
                ),
            ),
            'required'   => array( 'post_id', 'taxonomy', 'terms' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id', 'taxonomy', 'terms' ) );

        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $this->refuse_missing_post( $post_id );
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( 'You do not have permission to edit this post.' );
        }

        $taxonomy = sanitize_key( (string) $arguments['taxonomy'] );
        $tax_obj  = self::assignable_taxonomy( $taxonomy );
        self::refuse_taxonomy_of_another_type( $post_id, $taxonomy );

        $by = isset( $arguments['by'] ) && '' !== $arguments['by'] ? $arguments['by'] : 'auto';
        $this->refuse_unless_one_of( 'by', $by, array( 'auto', 'slug', 'id' ), $by, 'auto, slug or id' );

        $terms_raw = $arguments['terms'];
        if ( ! is_array( $terms_raw ) ) {
            $terms_raw = $this->parse_json_param( $terms_raw, 'terms' );
        }

        $terms     = self::terms_to_set( $terms_raw, $by );
        $to_create = self::terms_to_create( $terms, $taxonomy, $by );
        self::refuse_uncreatable_terms( $to_create, $taxonomy, $tax_obj );

        $before = wp_get_object_terms(
            $post_id,
            $taxonomy,
            array(
                'fields'                 => 'tt_ids',
                'orderby'                => 'none',
                'update_term_meta_cache' => false,
            )
        );

        $append = isset( $arguments['append'] ) ? (bool) $arguments['append'] : true;
        $result = wp_set_object_terms( $post_id, $terms, $taxonomy, $append );
        if ( is_wp_error( $result ) ) {
            throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $tt_ids  = array_map( 'intval', (array) $result );
        $changed = self::terms_changed( $before, $tt_ids, $append );
        if ( $changed ) {
            $this->clear_page_cache( array( $post_id ) );
        }

        return array(
            'post_id'           => $post_id,
            'taxonomy'          => $taxonomy,
            'terms_input'       => $terms,
            'term_taxonomy_ids' => $tt_ids,
            'append'            => $append,
            'changed'           => $changed,
            'created_terms'     => array_values( $to_create ),
        );
    }

    private static function assignable_taxonomy( string $taxonomy ): object {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown taxonomy: %s', $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! current_user_can( $tax_obj->cap->assign_terms ) ) {
            throw new \RuntimeException( sprintf( 'Insufficient capability to assign terms in taxonomy %s.', $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $tax_obj;
    }

    private static function refuse_taxonomy_of_another_type( int $post_id, string $taxonomy ): void {
        $post       = get_post( $post_id );
        $post_type  = (string) get_post_type( $post_id );
        $registered = array_values( array_unique( array_map( 'strval', (array) get_object_taxonomies( $post ? $post : $post_type, 'names' ) ) ) );
        if ( in_array( $taxonomy, $registered, true ) ) {
            return;
        }
        throw new \InvalidArgumentException(
            sprintf(
                'taxonomy: %1$s is not registered for post type %2$s. Taxonomies: %3$s. Nothing was changed.',
                $taxonomy, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                $post_type, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                empty( $registered ) ? '(none)' : implode( ', ', $registered ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            )
        );
    }

    private static function terms_to_set( array $terms_raw, string $by ): array {
        $terms = array();
        foreach ( $terms_raw as $t ) {
            if ( 'id' === $by ) {
                $terms[] = self::term_id_value( $t );
                continue;
            }
            $value = 'slug' === $by ? self::slug_value( $t ) : self::auto_value( $t );
            if ( null !== $value ) {
                $terms[] = $value;
            }
        }
        if ( empty( $terms ) ) {
            throw new \InvalidArgumentException( 'terms array must contain at least one valid ID or slug.' );
        }
        return $terms;
    }

    private static function term_id_value( $t ): int {
        if ( ( is_int( $t ) && $t > 0 ) || ( is_string( $t ) && ctype_digit( $t ) && (int) $t > 0 ) ) {
            return (int) $t;
        }
        throw new \InvalidArgumentException( sprintf( 'by id: %s is not a term ID (a positive integer).', is_scalar( $t ) ? (string) $t : gettype( $t ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function slug_value( $t ): ?string {
        if ( is_int( $t ) ) {
            $t = (string) $t;
        }
        if ( is_string( $t ) && '' !== $t ) {
            return sanitize_text_field( $t );
        }
        return null;
    }

    private static function auto_value( $t ) {
        if ( is_int( $t ) ) {
            return $t;
        }
        if ( is_string( $t ) && '' !== $t ) {
            return ctype_digit( $t ) ? (int) $t : sanitize_text_field( $t );
        }
        return null;
    }

    private static function terms_to_create( array $terms, string $taxonomy, string $by ): array {
        $missing_ids = array();
        $to_create   = array();
        foreach ( $terms as $term ) {
            if ( ( is_string( $term ) && '' === trim( $term ) ) || term_exists( $term, $taxonomy ) ) {
                continue;
            }
            if ( is_int( $term ) ) {
                $missing_ids[] = $term;
                continue;
            }
            $slug = sanitize_title( $term );
            $slot = '' !== $slug ? $slug : $term;
            if ( ! isset( $to_create[ $slot ] ) ) {
                $to_create[ $slot ] = $term;
            }
        }
        if ( 'id' === $by && ! empty( $missing_ids ) ) {
            throw new \InvalidArgumentException( sprintf( 'Term ID(s) not found in taxonomy %s: %s. Nothing was changed.', $taxonomy, implode( ', ', $missing_ids ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $to_create;
    }

    private static function refuse_uncreatable_terms( array $to_create, string $taxonomy, object $tax_obj ): void {
        if ( empty( $to_create ) ) {
            return;
        }
        $create_cap = is_taxonomy_hierarchical( $taxonomy ) ? $tax_obj->cap->edit_terms : $tax_obj->cap->assign_terms;
        if ( ! current_user_can( $create_cap ) ) {
            throw new \RuntimeException( sprintf( 'No term in taxonomy %1$s matches %2$s, and creating one needs the %3$s capability. Nothing was changed.', $taxonomy, implode( ', ', $to_create ), $create_cap ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private static function terms_changed( $before, array $tt_ids, bool $append ): bool {
        if ( is_wp_error( $before ) || ! is_array( $before ) ) {
            return true;
        }
        $before  = array_map( 'intval', $before );
        $added   = array_diff( $tt_ids, $before );
        $removed = $append ? array() : array_diff( $before, $tt_ids );
        return ! empty( $added ) || ! empty( $removed );
    }
}
