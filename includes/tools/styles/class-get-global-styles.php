<?php
namespace Easy_MCP_AI\Tools\Styles;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Global_Styles extends Base_Tool {

    public function get_name() {
        return 'wp_get_global_styles';
    }

    public function get_description() {
        return 'Gets the current global styles (theme.json) settings and styles. Requires an active block theme. Returns { id, settings, styles, title }. Optional `include_variations` (default false) adds `available_variations: [{ slug, title, scope }]` — the style variations the active theme ships (its /styles/*.json files, shown as "Browse styles" in the Site Editor). `scope` is `full`, `color` (a colour-only partial) or `typography` (a typography-only partial); when a theme ships a full variation and a partial under the same title, the full one keeps the bare slug and the partials are `<slug>-color` / `<slug>-typography`; every slug in the list is unique — a later variation whose slug is already taken gets `-2`, `-3`, … appended (so a full "Noon Color" next to a colour-only "Noon" is `noon-color-2`). Pass a slug exactly as listed to wp_update_global_styles `variation_slug` to apply one.';
    }

    public function get_category() {
        return 'styles';
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
                'include_variations' => array(
                    'type'        => 'boolean',
                    'description' => 'When true, also list the theme\'s available style variations as available_variations: [{ slug, title, scope }].',
                    'default'     => false,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $global_styles_id = $this->discover_global_styles_id();

        $request  = new \WP_REST_Request( 'GET', '/wp/v2/global-styles/' . $global_styles_id );
        $request->set_param( 'context', 'edit' );
        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException(
                    'Global styles endpoint is not available. This requires an active block theme (Full Site Editing). The current theme appears to be a classic theme.'
                );
            }
            throw new \RuntimeException( $wp_error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $data = $response->get_data();

        $result = array(
            'id'       => $data['id'],
            'settings' => $data['settings'] ?? new \stdClass(),
            'styles'   => $data['styles'] ?? new \stdClass(),
            'title'    => $data['title']['raw'] ?? wp_strip_all_tags( $data['title']['rendered'] ?? '' ),
        );

        if ( ! empty( $arguments['include_variations'] ) && rest_sanitize_boolean( $arguments['include_variations'] ) ) {
            $result['available_variations'] = array_map(
                function ( $variation ) {
                    return array(
                        'slug'  => $variation['slug'],
                        'title' => $variation['title'],
                        'scope' => $variation['scope'],
                    );
                },
                $this->fetch_style_variations()
            );
        }

        return $result;
    }
}
