<?php
namespace Easy_MCP_AI\Tools\Styles;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Global_Styles extends Base_Tool {

    public function get_name() {
        return 'wp_update_global_styles';
    }

    public function get_description() {
        return 'Updates the site\'s global styles (theme.json user overrides; block themes only). To change some values, pass `merge: true` with only those values. Without `merge`, `styles` and `settings` each REPLACE the whole stored object: every key you leave out is deleted, including `styles.css` (Additional CSS); an object you do not send is kept. To delete a key on purpose, send the full object without it and no `merge`. If a write deleted stored keys, the reply starts with `removed_keys` (path: previous value). They stay deleted until you call again with `merge: true` and those previous values put back in `styles`/`settings`. To apply a theme style variation instead, pass `variation_slug` exactly as wp_get_global_styles lists it with `include_variations: true`; `variation_mode` `merge` (default) keeps your other overrides, `replace` discards them. A variation cannot be combined with `styles`, `settings` or `merge`. Returns { id, settings, styles, title }, plus `applied_variation: { slug, title, scope, mode }` for a variation.';
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
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'styles'         => array(
                    'type'        => 'object',
                    'description' => 'theme.json styles object (color, typography, spacing, elements, blocks, css). Replaces the whole stored styles object unless `merge` is true: omitted keys are deleted, including `css` (Additional CSS).',
                ),
                'settings'       => array(
                    'type'        => 'object',
                    'description' => 'theme.json settings object. Replaces the whole stored settings object unless `merge` is true: omitted keys are deleted.',
                ),
                'merge'          => array(
                    'type'        => 'boolean',
                    'description' => 'true: deep-merge `styles`/`settings` into the stored objects, so omitted keys are kept. Lists such as a color palette are still replaced whole, and no key can be deleted. Default false: replace. Not for variations.',
                    'default'     => false,
                ),
                'variation_slug' => array(
                    'type'        => 'string',
                    'description' => 'Slug of a theme style variation to apply (from wp_get_global_styles with include_variations). Cannot be combined with styles, settings or merge.',
                ),
                'variation_mode' => array(
                    'type'        => 'string',
                    'description' => 'How to apply the variation (variation_slug only; for styles/settings see `merge`): `merge` (default — deep-merge over the current user styles/settings) or `replace` (the variation becomes the whole user configuration).',
                    'enum'        => array( 'merge', 'replace' ),
                    'default'     => 'merge',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $has_styles    = isset( $arguments['styles'] );
        $has_settings  = isset( $arguments['settings'] );
        $has_variation = isset( $arguments['variation_slug'] ) && '' !== $arguments['variation_slug'];
        $merge_raw     = isset( $arguments['merge'] ) && rest_sanitize_boolean( $arguments['merge'] );

        $this->validate_arguments( $has_styles, $has_settings, $has_variation, $merge_raw );
        $mode = $this->variation_mode( $arguments );

        $global_styles_id = $this->discover_global_styles_id();

        $applied = null;
        $before  = null;

        if ( $has_variation ) {
            $variation = $this->resolve_variation( $arguments['variation_slug'] );
            $applied   = array(
                'slug'  => $variation['slug'],
                'title' => $variation['title'],
                'scope' => $variation['scope'],
                'mode'  => $mode,
            );
            $body      = $this->variation_body( $variation, $mode, $global_styles_id );
        } else {
            $built  = $this->sent_body( $arguments, $merge_raw, $global_styles_id );
            $body   = $built['body'];
            $before = $built['before'];
        }

        $data = $this->post_global_styles( $global_styles_id, $body );

        $result = array(
            'id'       => $data['id'],
            'settings' => $data['settings'] ?? new \stdClass(),
            'styles'   => $data['styles'] ?? new \stdClass(),
            'title'    => $data['title']['raw'] ?? wp_strip_all_tags( $data['title']['rendered'] ?? '' ),
        );
        if ( null !== $applied ) {
            $result['applied_variation'] = $applied;
        }
        if ( null !== $before ) {
            $result = $this->prepend_removed_keys( $result, $before, $body, $data );
        }

        return $result;
    }

    








    private function validate_arguments( $has_styles, $has_settings, $has_variation, $merge_raw ) {
        if ( $has_variation && ( $has_styles || $has_settings ) ) {
            throw new \InvalidArgumentException( '"variation_slug" cannot be combined with "styles" or "settings". Apply the variation first, then send the overrides in a second call.' );
        }
        if ( $has_variation && $merge_raw ) {
            throw new \InvalidArgumentException( '"merge" applies to "styles" and "settings" only. For a variation use "variation_mode" ("merge" or "replace").' );
        }
        if ( ! $has_styles && ! $has_settings && ! $has_variation ) {
            throw new \InvalidArgumentException( 'At least one of "styles" or "settings" must be provided, or a "variation_slug" to apply.' );
        }
    }

    






    private function variation_mode( array $arguments ) {
        if ( ! isset( $arguments['variation_mode'] ) || '' === $arguments['variation_mode'] ) {
            return 'merge';
        }
        $mode = is_string( $arguments['variation_mode'] ) ? strtolower( trim( $arguments['variation_mode'] ) ) : '';
        if ( 'merge' !== $mode && 'replace' !== $mode ) {
            throw new \InvalidArgumentException( 'Invalid variation_mode. Use "merge" or "replace".' );
        }
        return $mode;
    }

    







    private function variation_body( array $variation, $mode, $global_styles_id ) {
        if ( 'replace' === $mode ) {
            
            
            
            
            return array(
                'settings' => empty( $variation['settings'] ) ? new \stdClass() : $variation['settings'],
                'styles'   => empty( $variation['styles'] ) ? new \stdClass() : $variation['styles'],
            );
        }
        $current = $this->read_current( $global_styles_id );
        return array(
            'settings' => $this->merge_theme_json( $current['settings'], $variation['settings'] ),
            'styles'   => $this->merge_theme_json( $current['styles'], $variation['styles'] ),
        );
    }

    















    private function sent_body( array $arguments, $merge_raw, $global_styles_id ) {
        $sent = array();
        foreach ( array( 'styles', 'settings' ) as $section ) {
            if ( isset( $arguments[ $section ] ) ) {
                $sent[ $section ] = $this->parse_json_param( $arguments[ $section ], $section );
            }
        }

        $before = null;
        try {
            $before = $this->read_current( $global_styles_id );
        } catch ( \RuntimeException $e ) {
            if ( $merge_raw ) {
                throw $e;
            }
        }

        $body = array();
        foreach ( $sent as $section => $value ) {
            $body[ $section ] = $merge_raw ? $this->merge_theme_json( $before[ $section ], $value ) : $value;
        }

        return array(
            'body'   => $body,
            'before' => $before,
        );
    }

    







    private function post_global_styles( $global_styles_id, array $body ) {
        $request = new \WP_REST_Request( 'POST', '/wp/v2/global-styles/' . $global_styles_id );

        if ( ! empty( $body ) ) {
            $request->set_body( wp_json_encode( $body ) );
            $request->set_header( 'content-type', 'application/json' );
        }

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

        return $response->get_data();
    }

    









    private function prepend_removed_keys( array $result, array $before, array $body, array $data ) {
        $removed = array();
        foreach ( array_keys( $body ) as $section ) {
            $after = json_decode( (string) wp_json_encode( $data[ $section ] ?? array() ), true );
            $this->collect_removed_keys( $before[ $section ], is_array( $after ) ? $after : array(), $section, $removed );
        }
        if ( empty( $removed ) ) {
            return $result;
        }
        return array_merge(
            array(
                'removed_keys' => $removed,
                'notice'       => 'These stored keys were deleted, because a sent styles/settings object replaces the stored one. They stay deleted until you call again with merge: true and these previous values put back in styles/settings; re-sending your change does not restore them.',
            ),
            $result
        );
    }

    










    private function collect_removed_keys( array $before, array $after, $prefix, array &$removed ) {
        if ( $this->is_list( $before ) ) {
            return;
        }
        foreach ( $before as $key => $value ) {
            $path = $prefix . '.' . $key;
            if ( ! array_key_exists( $key, $after ) ) {
                $removed[ $path ] = $value;
            } elseif ( is_array( $value ) && is_array( $after[ $key ] ) ) {
                $this->collect_removed_keys( $value, $after[ $key ], $path, $removed );
            }
        }
    }

    







    private function resolve_variation( $slug ) {
        $slug       = is_string( $slug ) ? trim( $slug ) : '';
        $variations = $this->fetch_style_variations();

        foreach ( $variations as $variation ) {
            if ( $variation['slug'] === $slug ) {
                return $variation;
            }
        }

        $available = array_values( array_unique( array_column( $variations, 'slug' ) ) );
        throw new \InvalidArgumentException(
            sprintf(
                'Unknown variation_slug "%s". %s',
                $slug, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                empty( $available )
                    ? 'The active theme ships no style variations.'
                    : 'Available: ' . implode( ', ', $available ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            )
        );
    }

    







    private function read_current( $global_styles_id ) {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/global-styles/' . $global_styles_id );
        $request->set_param( 'context', 'edit' );
        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            throw new \RuntimeException( $response->as_error()->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $data = $response->get_data();
        return array(
            'settings' => isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array(),
            'styles'   => isset( $data['styles'] ) && is_array( $data['styles'] ) ? $data['styles'] : array(),
        );
    }

    















    private function merge_theme_json( array $current, array $variation ) {
        if ( empty( $current ) && empty( $variation ) ) {
            return new \stdClass();
        }
        return $this->merge_recursive( $current, $variation );
    }

    private function merge_recursive( array $current, array $variation ) {
        if ( $this->is_list( $variation ) || $this->is_list( $current ) ) {
            return $variation;
        }
        foreach ( $variation as $key => $value ) {
            if ( is_array( $value ) && isset( $current[ $key ] ) && is_array( $current[ $key ] ) ) {
                $current[ $key ] = $this->merge_recursive( $current[ $key ], $value );
            } else {
                $current[ $key ] = $value;
            }
        }
        return $current;
    }

    
    private function is_list( array $value ) {
        if ( empty( $value ) ) {
            return false;
        }
        return array_keys( $value ) === range( 0, count( $value ) - 1 );
    }
}
