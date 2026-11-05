<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Menus extends Base_Tool {

    const PER_PAGE = 100;

    public function get_name() {
        return 'wp_list_menus';
    }

    public function get_description() {
        return 'Lists the WordPress navigation menus (`nav_menu` taxonomy terms), 100 per page, ordered by name. Optional: `page` (1-based, default 1; `total_pages` says how many there are). Returns { menus: [{ id, name, slug, description, locations, count, term_group, term_taxonomy_id, taxonomy, parent }], total, total_pages, page, per_page }. `locations` lists the theme locations the menu is assigned to ([] when none); `count` is WordPress\'s stored term count, which counts published items only (draft items added on the Menus screen but not saved are left out). Does not return the items themselves — use `wp_list_menu_items` for that. On a block theme, whose Navigation blocks show block menus rather than these classic menus, also returns `navigation_menus` [{ id, title, status }] (the `wp_navigation` posts, up to 100; left out when WordPress refuses that read, which the hint then says) and a `hint` naming the tools that read and edit them. Requires WordPress 5.9+.';
    }

    public function get_category() {
        return 'menus';
    }

    public function get_required_capability() {
        return 'edit_theme_options';
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
                'page' => array(
                    'type'        => 'integer',
                    'description' => 'Page number (1-based), 100 menus per page. Default: 1.',
                    'minimum'     => 1,
                    'default'     => 1,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $page = isset( $arguments['page'] ) ? max( 1, (int) $arguments['page'] ) : 1;

        $request = new \WP_REST_Request( 'GET', '/wp/v2/menus' );
        $request->set_param( 'per_page', self::PER_PAGE );
        $request->set_param( 'page', $page );
        $request->set_param( 'context', 'edit' );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $menus = $response->get_data();

        $result = array();
        foreach ( $menus as $menu ) {
            $result[] = array_merge(
                array(
                    'id'          => $menu['id'],
                    'name'        => $menu['name'],
                    'slug'        => $menu['slug'],
                    'description' => $menu['description'],
                    'locations'   => isset( $menu['locations'] ) && is_array( $menu['locations'] ) ? array_values( $menu['locations'] ) : array(),
                ),
                self::term_fields( (int) $menu['id'] )
            );
        }

        $result = array_merge(
            array( 'menus' => $result ),
            $this->pagination_meta( $response, $page, self::PER_PAGE, count( $menus ) )
        );
        $route = rest_get_route_for_post_type_items( 'wp_navigation' );
        if ( wp_is_block_theme() && '' !== $route ) {
            try {
                $navigation                 = self::navigation_menus( $route );
                $result['navigation_menus'] = $navigation;
                $result['hint']             = self::block_theme_hint( array() === $navigation );
            } catch ( \RuntimeException $e ) {
                $result['hint'] = self::block_theme_hint( false ) . ' The block menus could not be read: ' . $e->getMessage();
            }
        }
        return $result;
    }

    private static function navigation_menus( string $route ): array {
        $request = new \WP_REST_Request( 'GET', $route );
        $request->set_param( 'per_page', self::PER_PAGE );
        $request->set_param( 'status', array( 'publish', 'future', 'draft', 'pending', 'private' ) );
        $request->set_param( 'context', 'edit' );
        $request->set_param( '_fields', 'id,title,status' );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            throw new \RuntimeException( $response->as_error()->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $menus = array();
        foreach ( (array) $response->get_data() as $menu ) {
            $menus[] = array(
                'id'     => (int) $menu['id'],
                'title'  => (string) ( $menu['title']['raw'] ?? '' ),
                'status' => (string) $menu['status'],
            );
        }
        return $menus;
    }

    private static function block_theme_hint( bool $none ): string {
        $hint = 'The active theme is a block theme. Its Navigation blocks show block menus (`navigation_menus`), not the classic menus in `menus`: WordPress copies a classic menu into a block menu once, when it creates the first one, so editing a classic menu does not change what a Navigation block shows. A block menu\'s links are blocks in its `content`: read them with wp_get_cpt_item { "rest_base": "navigation", "item_id": <id> } and change them with wp_update_cpt_item. A Navigation block can also hold links of its own in a template part (wp_get_template).';
        if ( $none ) {
            $hint .= ' There is no block menu yet: WordPress creates one the first time a page shows a Navigation block with no links of its own, from the newest classic menu or as a list of the published pages.';
        }
        return $hint;
    }

    private static function term_fields( int $term_id ): array {
        $term = get_term( $term_id, 'nav_menu' );
        if ( ! is_object( $term ) || is_wp_error( $term ) ) {
            return array(
                'count'            => null,
                'term_group'       => null,
                'term_taxonomy_id' => null,
                'taxonomy'         => null,
                'parent'           => null,
            );
        }
        return array(
            'count'            => isset( $term->count ) ? (int) $term->count : null,
            'term_group'       => isset( $term->term_group ) ? (int) $term->term_group : null,
            'term_taxonomy_id' => isset( $term->term_taxonomy_id ) ? (int) $term->term_taxonomy_id : null,
            'taxonomy'         => isset( $term->taxonomy ) ? (string) $term->taxonomy : null,
            'parent'           => isset( $term->parent ) ? (int) $term->parent : null,
        );
    }
}
