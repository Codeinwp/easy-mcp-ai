<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Term extends Base_Tool {

    public function get_name() {
        return 'wp_update_term';
    }

    public function get_description() {
        return 'Updates a term in any taxonomy exposed in the REST API (show_in_rest); WooCommerce attribute taxonomies (pa_*) are not. Required: `term_id`, `taxonomy`. Optional updateable fields: `name`, `slug`, `description`, `parent`, `clear_description` (boolean, default false: true empties the description). Returns { id, name, slug, description, parent, count, taxonomy, changed } — `changed` is false when the term already had every value sent, so nothing changed. Capability resolved dynamically via taxonomy cap->edit_terms. Passing an empty string for `description` preserves the existing value (it is not cleared) — use `clear_description: true` to blank it. Pass `parent` as `0` (or `null`) to clear the parent and move the term to the top level. Writes through the taxonomy\'s own REST route, so a taxonomy registered under a custom `rest_namespace` works too.';
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
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'term_id'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the term to update.',
                ),
                'taxonomy'    => array(
                    'type'        => 'string',
                    'description' => 'The taxonomy slug.',
                ),
                'name'        => array(
                    'type'        => 'string',
                    'description' => 'New name for the term.',
                ),
                'slug'        => array(
                    'type'        => 'string',
                    'description' => 'New slug for the term.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'New description.',
                ),
                'parent'      => array(
                    'type'        => 'integer',
                    'description' => 'New parent term ID. Pass 0 to clear the parent, moving the term to the top level.',
                ),
                'clear_description' => array(
                    'type'        => 'boolean',
                    'description' => 'true: set the description to empty (an empty `description` string keeps the current one). Cannot be combined with a non-empty `description`. Default false.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'term_id', 'taxonomy' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'term_id', 'taxonomy' ) );
        $term_id  = $this->parse_required_id( $arguments['term_id'], 'term_id' );
        $taxonomy = $this->validate_rest_route_segment( $arguments['taxonomy'], 'taxonomy' );

        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! $tax_obj ) {
            throw new \InvalidArgumentException(
                sprintf( 'Unknown taxonomy: %s', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        $this->refuse_non_rest_taxonomy( $taxonomy, $tax_obj );

        $params = $this->update_params( $arguments );

        $rest_base = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $taxonomy;
        $rest_base = $this->validate_rest_route_segment( $rest_base, 'rest_base' );

        $namespace = List_Terms::taxonomy_rest_namespace( $tax_obj, $taxonomy );

        $before = get_term( $term_id, $taxonomy );

        $data = $this->rest_request( 'POST', '/' . $namespace . '/' . $rest_base . '/' . $term_id, $params );

        $result = array(
            'id'          => (int) ( $data['id'] ?? $term_id ),
            'name'        => (string) ( $data['name'] ?? '' ),
            'slug'        => (string) ( $data['slug'] ?? '' ),
            'description' => (string) ( $data['description'] ?? '' ),
            'parent'      => (int) ( $data['parent'] ?? 0 ),
            'count'       => (int) ( $data['count'] ?? 0 ),
            'taxonomy'    => $taxonomy,
        );
        $result['changed'] = self::term_changed( $before, $result );
        return $result;
    }

    private function update_params( array $arguments ): array {
        $params = array_merge( $this->name_and_slug_params( $arguments ), $this->description_param( $arguments ) );
        if ( array_key_exists( 'parent', $arguments ) ) {
            $params['parent'] = absint( $arguments['parent'] );
        }
        if ( empty( $params ) ) {
            throw new \InvalidArgumentException( 'At least one updateable field (name, slug, description, parent, clear_description) must be provided.' );
        }
        return $params;
    }

    private function name_and_slug_params( array $arguments ): array {
        $params = array();
        if ( isset( $arguments['name'] ) ) {
            $params['name'] = sanitize_text_field( (string) $arguments['name'] );
            if ( '' === $params['name'] ) {
                throw new \InvalidArgumentException( 'name cannot be empty.' );
            }
        }
        if ( isset( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( (string) $arguments['slug'] );
            if ( '' === $params['slug'] ) {
                throw new \InvalidArgumentException( 'slug cannot be empty.' );
            }
        }
        return $params;
    }

    private function description_param( array $arguments ): array {
        $params = array();
        if ( isset( $arguments['description'] ) && '' !== $arguments['description'] ) {
            $params['description'] = wp_kses_post( (string) $arguments['description'] );
        }
        if ( isset( $arguments['clear_description'] ) && rest_sanitize_boolean( $arguments['clear_description'] ) ) {
            if ( isset( $params['description'] ) ) {
                throw new \InvalidArgumentException( 'clear_description cannot be combined with a non-empty description: send one or the other.' );
            }
            $params['description'] = '';
        }
        return $params;
    }

    private static function term_changed( $before, array $after ): bool {
        if ( ! is_object( $before ) || is_wp_error( $before ) || ! isset( $before->name, $before->slug ) ) {
            return true;
        }
        return (string) $before->name !== $after['name']
            || (string) $before->slug !== $after['slug']
            || (string) ( $before->description ?? '' ) !== $after['description']
            || (int) ( $before->parent ?? 0 ) !== $after['parent'];
    }
}
