<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Menu extends Base_Tool {

    use Menu_Slug;

    public function get_name() {
        return 'wp_update_menu';
    }

    public function get_description() {
        return 'Updates an existing WordPress navigation menu (the `nav_menu` term itself — name/slug/description) and the theme locations it is shown in. Required: `menu_id`. Optional: `name`, `slug`, `description`, `locations` — at least one; only the fields you provide are changed, so a rename keeps the slug and the description (a `slug` another menu uses is refused). `locations` is the full list of theme locations (e.g. "primary-menu") this menu is shown in: the menu leaves any location not listed, a listed location shows this menu instead of the one it had, and [] removes it from every location; a slug the active theme does not register is refused, naming the ones it does. Does NOT add or modify items in the menu — use `wp_create_menu_item` / `wp_update_menu_item` / `wp_delete_menu_item` for that. Returns the updated { id, name, slug, description, locations }. Requires WordPress 5.9+.';
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
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'menu_id'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu to update.',
                ),
                'name'        => array(
                    'type'        => 'string',
                    'description' => 'New name for the menu.',
                ),
                'slug'        => array(
                    'type'        => 'string',
                    'description' => 'New slug for the menu.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'New description for the menu. WordPress keeps the HTML it allows in a term description, such as links and bold text.',
                ),
                'locations'   => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Every theme location the menu is shown in, replacing the ones it has (e.g. ["primary-menu"]; [] for none).',
                ),
            ),
            'required'   => array( 'menu_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'menu_id' ) );

        $menu_id  = $this->parse_required_id( $arguments['menu_id'], 'menu_id' );
        $has_slug = isset( $arguments['slug'] ) && '' !== $arguments['slug'];
        if ( isset( $arguments['locations'] ) && ! self::is_slug_list( $arguments['locations'] ) ) {
            throw new \InvalidArgumentException( '`locations` must be a list of theme location slugs, such as ["primary-menu"].' );
        }
        if ( isset( $arguments['locations'] ) ) {
            $registered = array_map( 'strval', array_keys( (array) get_registered_nav_menus() ) );
            $unknown    = array_values( array_diff( $arguments['locations'], $registered ) );
            if ( array() !== $unknown ) {
                throw new \InvalidArgumentException( sprintf( 'The active theme has no menu location %s; its locations are %s.', '"' . implode( '", "', $unknown ) . '"', array() === $registered ? 'none' : '"' . implode( '", "', $registered ) . '"' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        if ( ! isset( $arguments['name'] ) && ! isset( $arguments['description'] ) && ! $has_slug && ! isset( $arguments['locations'] ) ) {
            throw new \InvalidArgumentException( 'Nothing to update: pass at least one of name, slug, description or locations.' );
        }
        $slug    = $has_slug ? $this->requested_menu_slug( $arguments['slug'], $menu_id ) : null;
        $current = get_term( $menu_id, 'nav_menu' );
        $current = is_object( $current ) && ! is_wp_error( $current ) ? $current : null;

        $response = rest_do_request( $this->menu_request( $menu_id, $arguments, $current ) );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException(
                    'Menu endpoints are not available. This requires WordPress 5.9 or later with navigation menu support.'
                );
            }
            throw new \RuntimeException( $wp_error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $data = $response->get_data();

        $data['slug'] = $this->reapply_slug( $menu_id, $slug, $current ) ?? $data['slug'];

        return array(
            'id'          => $data['id'],
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? '',
            'locations'   => array_values( array_map( 'strval', (array) ( $data['locations'] ?? array() ) ) ),
        );
    }

    private static function is_slug_list( $locations ): bool {
        if ( ! is_array( $locations ) || array_values( $locations ) !== $locations ) {
            return false;
        }
        foreach ( $locations as $location ) {
            if ( ! is_string( $location ) ) {
                return false;
            }
        }
        return true;
    }

    private function reapply_slug( int $menu_id, ?string $slug, $current ): ?string {
        if ( null === $slug && null !== $current ) {
            $slug = (string) $current->slug;
        }
        if ( null === $slug ) {
            return null;
        }
        try {
            return $this->store_menu_slug( $menu_id, $slug );
        } catch ( \RuntimeException $e ) {
            throw new \RuntimeException( 'Any name or description in this call was saved, but the slug was not: ' . $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function menu_request( int $menu_id, array $arguments, ?object $current ): \WP_REST_Request {
        $request = new \WP_REST_Request( 'POST', '/wp/v2/menus/' . $menu_id );
        if ( isset( $arguments['name'] ) ) {
            $request->set_param( 'name', sanitize_text_field( $arguments['name'] ) );
        }
        if ( isset( $arguments['description'] ) ) {
            $request->set_param( 'description', self::markup_arg( $arguments, 'description' ) );
        } elseif ( null !== $current ) {
            $request->set_param( 'description', (string) $current->description );
        }
        if ( isset( $arguments['locations'] ) ) {
            $request->set_param( 'locations', array_values( $arguments['locations'] ) );
        }
        return $request;
    }
}
