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
        return 'Adds (or replaces) terms on a post in any taxonomy. Required: `post_id`, `taxonomy`, `terms` (array of integer IDs or string slugs — wp_set_object_terms() handles both). Optional: `append` (boolean, default true — when true ADDS to existing terms; when false REPLACES the term set). Returns { post_id, taxonomy, terms_input: array<int|string>, term_taxonomy_ids: array<int>, append: bool }. Note: a purely numeric string in `terms` (e.g. a year-based slug like \'2026\') is always coerced to a term ID, not treated as a slug — a numeric-string slug cannot currently be assigned via this tool. Verifies edit_post on the post and the taxonomy\'s assign_terms cap.';
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
        if ( ! taxonomy_exists( $taxonomy ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown taxonomy: %s', $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! current_user_can( $tax_obj->cap->assign_terms ) ) {
            throw new \RuntimeException( sprintf( 'Insufficient capability to assign terms in taxonomy %s.', $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $terms_raw = $arguments['terms'];
        if ( ! is_array( $terms_raw ) ) {
            $terms_raw = $this->parse_json_param( $terms_raw, 'terms' );
        }

        $terms = array();
        foreach ( $terms_raw as $t ) {
            if ( is_int( $t ) ) {
                $terms[] = $t;
            } elseif ( is_string( $t ) && '' !== $t ) {
                if ( ctype_digit( $t ) ) {
                    $terms[] = (int) $t;
                } else {
                    $terms[] = sanitize_text_field( $t );
                }
            }
        }
        if ( empty( $terms ) ) {
            throw new \InvalidArgumentException( 'terms array must contain at least one valid ID or slug.' );
        }

        $append = isset( $arguments['append'] ) ? (bool) $arguments['append'] : true;
        $result = wp_set_object_terms( $post_id, $terms, $taxonomy, $append );
        if ( is_wp_error( $result ) ) {
            throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return array(
            'post_id'           => $post_id,
            'taxonomy'          => $taxonomy,
            'terms_input'       => $terms,
            'term_taxonomy_ids' => array_map( 'intval', (array) $result ),
            'append'            => $append,
        );
    }
}
