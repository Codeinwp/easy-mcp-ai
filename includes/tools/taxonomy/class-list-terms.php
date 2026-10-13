<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












class List_Terms extends Base_Tool {

    public function get_name() {
        return 'wp_list_terms';
    }

    public function get_description() {
        return 'Lists terms of ANY taxonomy exposed in the REST API — media folders such as `mlo-category`, product categories and brands (product_cat, product_brand), ACF or other custom taxonomies, as well as category and post_tag. Use `wp_get_taxonomies` to discover the taxonomy slugs. Required: `taxonomy` (the taxonomy slug). Optional: `search`, `parent` (filter by parent ID; use 0 for top-level only), `per_page` (default 100), `page`, `orderby` (id/name/slug/count/include — default name), `order` (asc/desc), `hide_empty` (boolean, default false). Returns { taxonomy, terms: [{ id, name, slug, description, parent, count }], total, total_pages, page, per_page }. Reads through the taxonomy\'s own REST route — its `rest_namespace` (default wp/v2) and `rest_base` — so a taxonomy registered under a custom namespace is reached too. Capability resolved dynamically per taxonomy via cap->assign_terms.';
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
                'taxonomy'   => array(
                    'type'        => 'string',
                    'description' => 'The taxonomy slug (e.g. category, post_tag, product_cat, mlo-category). Use wp_get_taxonomies to list the available slugs.',
                ),
                'per_page'   => array(
                    'type'        => 'integer',
                    'description' => 'Number of terms per page (1-100).',
                    'default'     => 100,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'       => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'search'     => array(
                    'type'        => 'string',
                    'description' => 'Search query to filter terms.',
                ),
                'parent'     => array(
                    'type'        => 'integer',
                    'description' => 'Parent term ID to filter by (hierarchical taxonomies only; 0 for top-level terms).',
                ),
                'hide_empty' => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to hide terms with no objects assigned.',
                    'default'     => false,
                ),
                'orderby'    => array(
                    'type'        => 'string',
                    'description' => 'Field to order results by.',
                    'enum'        => array( 'id', 'name', 'slug', 'count', 'include' ),
                    'default'     => 'name',
                ),
                'order'      => array(
                    'type'        => 'string',
                    'description' => 'Sort direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'asc',
                ),
            ),
            'required'   => array( 'taxonomy' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'taxonomy' ) );
        $taxonomy = $this->validate_rest_route_segment( $arguments['taxonomy'], 'taxonomy' );

        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! $tax_obj ) {
            throw new \InvalidArgumentException(
                sprintf( 'Unknown taxonomy: %s', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( empty( $tax_obj->show_in_rest ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Taxonomy %s is not exposed in the REST API (show_in_rest is false), so its terms cannot be listed.', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( ! current_user_can( $tax_obj->cap->assign_terms ) ) {
            throw new \RuntimeException(
                sprintf( 'Insufficient capability for taxonomy %s.', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $rest_base = ! empty( $tax_obj->rest_base ) ? (string) $tax_obj->rest_base : $taxonomy;
        $namespace = $this->taxonomy_rest_namespace( $tax_obj, $taxonomy );

        $params = array();

        $params['per_page'] = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 100;
        $params['page']     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;

        if ( ! empty( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }

        if ( isset( $arguments['parent'] ) ) {
            $params['parent'] = absint( $arguments['parent'] );
        }

        if ( isset( $arguments['hide_empty'] ) ) {
            $params['hide_empty'] = (bool) $arguments['hide_empty'];
        }

        if ( ! empty( $arguments['orderby'] ) ) {
            $params['orderby'] = $arguments['orderby'];
        }

        if ( ! empty( $arguments['order'] ) ) {
            $params['order'] = $arguments['order'];
        }

        $request = new \WP_REST_Request( 'GET', '/' . $namespace . '/' . $rest_base );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array(
                        'taxonomy' => $taxonomy,
                        'terms'    => array(),
                    ),
                    $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $terms = $response->get_data();

        $result = array();
        foreach ( (array) $terms as $term ) {
            $result[] = array(
                'id'          => $term['id'],
                'name'        => $term['name'],
                'slug'        => $term['slug'],
                'description' => $term['description'] ?? '',
                'parent'      => $term['parent'] ?? 0,
                'count'       => $term['count'] ?? 0,
            );
        }

        return array_merge(
            array(
                'taxonomy' => $taxonomy,
                'terms'    => $result,
            ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( (array) $terms ) )
        );
    }

    

















    private function taxonomy_rest_namespace( $tax_obj, $taxonomy ) {
        $namespace = isset( $tax_obj->rest_namespace ) && is_string( $tax_obj->rest_namespace )
            ? trim( $tax_obj->rest_namespace, '/' )
            : '';
        if ( '' === $namespace ) {
            return 'wp/v2';
        }
        
        
        
        
        
        foreach ( explode( '/', $namespace ) as $segment ) {
            if ( '' === $segment || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $segment ) ) {
                throw new \RuntimeException(
                    sprintf(
                        'Taxonomy %s declares a rest_namespace ("%s") the tool cannot route to: the segment "%s" is not a valid REST namespace segment.',
                        $taxonomy, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        $namespace, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        $segment // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    )
                );
            }
        }
        return $namespace;
    }
}
