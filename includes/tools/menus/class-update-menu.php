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
        return 'Updates an existing WordPress navigation menu (the `nav_menu` term itself — name/slug/description). Required: `menu_id`. Optional: `name`, `slug`, `description` — at least one; only the fields you provide are changed, so a rename keeps the slug and the description (a `slug` another menu uses is refused). Does NOT add or modify items in the menu — use `wp_create_menu_item` / `wp_update_menu_item` / `wp_delete_menu_item` for that. Returns the updated { id, name, slug, description }. Requires WordPress 5.9+.';
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
                    'description' => 'New description for the menu.',
                ),
            ),
            'required'   => array( 'menu_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'menu_id' ) );

        $menu_id  = $this->parse_required_id( $arguments['menu_id'], 'menu_id' );
        $has_slug = isset( $arguments['slug'] ) && '' !== $arguments['slug'];
        if ( ! isset( $arguments['name'] ) && ! isset( $arguments['description'] ) && ! $has_slug ) {
            
            throw new \InvalidArgumentException( 'Nothing to update: pass at least one of name, slug or description.' );
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
        );
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
            $request->set_param( 'description', sanitize_text_field( $arguments['description'] ) );
        } elseif ( null !== $current ) {
            $request->set_param( 'description', (string) $current->description );
        }
        return $request;
    }
}
