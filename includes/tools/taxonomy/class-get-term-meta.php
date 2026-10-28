<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Term_Meta extends Base_Tool {

    public function get_name() {
        return 'wp_get_term_meta';
    }

    public function get_description() {
        return 'Gets meta for a term. Required: `term_id`. Optional: `key` (single key — omit to return all meta), `single` (boolean, only with `key`, default true: the first value; false: every value stored under the key, as a list). When `key` is given, returns { term_id, taxonomy, key, value }; when `key` is omitted, returns { term_id, taxonomy, meta (array of key=>values), withheld_count } — withheld_count is how many protected keys (leading underscore) were left out because reading them needs manage_options (0 when nothing was left out). Read access is governed by the taxonomy\'s `edit_terms` capability. Useful for reading per-tag/per-category SEO meta from Yoast (_yoast_wpseo_*), Rank Math (rank_math_*), AIOSEO (_aioseo_*), or WooCommerce category images / brand logos.';
    }

    public function get_category() {
        return 'taxonomy';
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
                'term_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the term to retrieve meta for.',
                ),
                'key'     => array(
                    'type'        => 'string',
                    'description' => 'Optional single meta key to retrieve. Omit to return all meta.',
                ),
                'single'  => array(
                    'type'        => 'boolean',
                    'description' => 'Only together with `key`. true (the default when omitted): `value` is the first value stored under the key ("" when there is none). false: `value` is the list of every value stored under the key ([] when there is none). Without `key` every value of every key is returned, so sending `single` without `key` is refused.',
                ),
            ),
            'required'   => array( 'term_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'term_id' ) );
        $term_id = $this->parse_required_id( $arguments['term_id'], 'term_id' );
        $term    = $this->term_with_readable_meta( $term_id );

        $key    = isset( $arguments['key'] ) ? sanitize_text_field( (string) $arguments['key'] ) : '';
        $single = $this->single_arg( $arguments, $key );

        if ( '' !== $key ) {
            if ( is_protected_meta( $key, 'term' ) && ! current_user_can( 'manage_options' ) ) {
                throw new \RuntimeException( 'Protected meta keys require administrator privileges.' );
            }
            $value = get_term_meta( $term_id, $key, $single );
            return array(
                'term_id'  => $term_id,
                'taxonomy' => $term->taxonomy,
                'key'      => $key,
                'value'    => $value,
            );
        }

        $readable = $this->readable_meta( $term_id );

        return array(
            'term_id'        => $term_id,
            'taxonomy'       => $term->taxonomy,
            'meta'           => $readable['meta'],
            'withheld_count' => $readable['withheld_count'],
        );
    }

    private function term_with_readable_meta( int $term_id ): object {
        $term = get_term( $term_id );
        if ( ! $term || is_wp_error( $term ) ) {
            throw new \InvalidArgumentException( 'Term not found.' );
        }
        $tax_obj = get_taxonomy( $term->taxonomy );
        if ( ! $tax_obj ) {
            throw new \InvalidArgumentException( 'Invalid taxonomy.' );
        }
        if ( ! current_user_can( $tax_obj->cap->edit_terms ) ) {
            throw new \RuntimeException( sprintf( 'Insufficient capability for taxonomy %s.', $term->taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $term;
    }

    private function single_arg( array $arguments, string $key ): bool {
        $single_sent = array_key_exists( 'single', $arguments ) && null !== $arguments['single'];
        if ( $single_sent && '' === $key ) {
            throw new \InvalidArgumentException( 'single applies only together with key: without key every value of every key is returned already.' );
        }
        return $single_sent ? rest_sanitize_boolean( $arguments['single'] ) : true;
    }

    private function readable_meta( int $term_id ): array {
        $all      = get_term_meta( $term_id );
        $withheld = 0;
        if ( ! current_user_can( 'manage_options' ) ) {
            foreach ( $all as $k => $_ ) {
                if ( is_protected_meta( $k, 'term' ) ) {
                    unset( $all[ $k ] );
                    ++$withheld;
                }
            }
        }
        return array(
            'meta'           => $all,
            'withheld_count' => $withheld,
        );
    }
}
