<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../../mcp/class-detailed-tool-error.php';

class Update_Site_Settings extends Base_Tool {

    
    const PAGE_FIELDS = array( 'page_on_front', 'page_for_posts' );

    public function get_name() {
        return 'wp_update_site_settings';
    }

    public function get_description() {
        return 'Updates WordPress site settings (PATCH semantics — only supplied fields change). Editable: `title`, `description` (tagline), `timezone` (e.g. "America/New_York" or "Europe/London"; `timezone_string` is accepted as an alias; a timezone WordPress does not know is refused and nothing is changed), `date_format` (e.g. "F j, Y"), `time_format` (e.g. "g:i a"), `posts_per_page` (integer), and the homepage: `show_on_front` ("posts" for the latest posts, "page" for a static page), `page_on_front` (the static homepage) and `page_for_posts` (the page that lists posts) — page IDs of published pages, 0 clears one. To make a page the homepage, send `show_on_front: "page"` with `page_on_front`; a page ID while the homepage shows posts, the same page for both, or a page that is not published is refused. Any other key is refused and nothing is changed: admin email, permalinks, site URL and language are not editable here (wp-admin > Settings). Requires `manage_options` (administrators only). Changes take effect immediately. Returns { updated: true, title, description, show_on_front, page_on_front, page_for_posts }.';
    }

    public function get_category() {
        return 'site';
    }

    public function get_required_capability() {
        return 'manage_options';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'title'          => array(
                    'type'        => 'string',
                    'description' => 'The site title.',
                ),
                'description'    => array(
                    'type'        => 'string',
                    'description' => 'The site tagline/description.',
                ),
                'timezone'        => array(
                    'type'        => 'string',
                    'description' => 'The site timezone string (e.g. America/New_York, Europe/London). This is the WP REST API settings field name.',
                ),
                'timezone_string' => array(
                    'type'        => 'string',
                    'description' => 'Deprecated alias for `timezone`; accepted for backward compatibility.',
                ),
                'date_format'    => array(
                    'type'        => 'string',
                    'description' => 'The date format string (e.g. F j, Y).',
                ),
                'time_format'    => array(
                    'type'        => 'string',
                    'description' => 'The time format string (e.g. g:i a).',
                ),
                'posts_per_page' => array(
                    'type'        => 'integer',
                    'description' => 'The number of posts to show per page.',
                ),
                'show_on_front'  => array(
                    'type'        => 'string',
                    'enum'        => array( 'posts', 'page' ),
                    'description' => 'What the homepage shows: "posts" (the latest posts) or "page" (the static page in page_on_front).',
                ),
                'page_on_front'  => array(
                    'type'        => 'integer',
                    'description' => 'ID of a published page to use as the homepage; 0 clears it. Needs show_on_front "page".',
                ),
                'page_for_posts' => array(
                    'type'        => 'integer',
                    'description' => 'ID of a published page that lists the latest posts; 0 clears it. Needs show_on_front "page".',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        
        
        
        $settable = array_keys( $this->get_input_schema()['properties'] );
        $unknown  = array_diff( array_keys( $arguments ), $settable );
        if ( $unknown ) {
            throw new Detailed_Tool_Error(
                self::unsupported_message( $unknown ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                'Settable here: ' . implode( ', ', $settable ) . '. Admin email, permalinks, site URL and language are not; change them in wp-admin > Settings.' // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $params = array();

        if ( isset( $arguments['title'] ) ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }

        if ( isset( $arguments['description'] ) ) {
            $params['description'] = sanitize_text_field( $arguments['description'] );
        }

        
        
        
        if ( isset( $arguments['timezone'] ) ) {
            $params['timezone'] = sanitize_text_field( $arguments['timezone'] );
        } elseif ( isset( $arguments['timezone_string'] ) ) {
            $params['timezone'] = sanitize_text_field( $arguments['timezone_string'] );
        }

        if ( isset( $arguments['date_format'] ) ) {
            $params['date_format'] = sanitize_text_field( $arguments['date_format'] );
        }

        if ( isset( $arguments['time_format'] ) ) {
            $params['time_format'] = sanitize_text_field( $arguments['time_format'] );
        }

        if ( isset( $arguments['posts_per_page'] ) ) {
            $params['posts_per_page'] = absint( $arguments['posts_per_page'] );
        }

        $params = array_merge( $params, $this->front_page_params( $arguments ) );

        if ( empty( $params ) ) {
            throw new \InvalidArgumentException( 'No setting given. Pass at least one of: ' . implode( ', ', $settable ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        
        
        
        
        
        $zone_group = version_compare( (string) get_bloginfo( 'version' ), '6.1', '>=' ) ? \DateTimeZone::ALL_WITH_BC : \DateTimeZone::ALL;
        if ( isset( $params['timezone'] ) && '' !== $params['timezone']
            && ! in_array( $params['timezone'], timezone_identifiers_list( $zone_group ), true ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'timezone "%s" is not a timezone WordPress accepts; nothing was changed. Use an identifier such as "Europe/London" or "UTC" (offsets like "UTC+2" are not accepted).', $params['timezone'] ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/settings', $params );

        return array(
            'updated'        => true,
            'title'          => $data['title'],
            'description'    => $data['description'],
            'show_on_front'  => $data['show_on_front'] ?? null,
            'page_on_front'  => $data['page_on_front'] ?? null,
            'page_for_posts' => $data['page_for_posts'] ?? null,
        );
    }

    













    private function front_page_params( array $arguments ): array {
        $params = array();
        if ( isset( $arguments['show_on_front'] ) ) {
            if ( ! in_array( $arguments['show_on_front'], array( 'posts', 'page' ), true ) ) {
                throw new \InvalidArgumentException( 'show_on_front must be "posts" or "page"; nothing was changed.' );
            }
            $params['show_on_front'] = $arguments['show_on_front'];
        }
        foreach ( self::PAGE_FIELDS as $field ) {
            if ( isset( $arguments[ $field ] ) ) {
                $params[ $field ] = self::page_id( $field, $arguments[ $field ] );
                if ( 0 !== $params[ $field ] ) {
                    self::require_published_page( $field, $params[ $field ] );
                }
            }
        }
        if ( ! $params ) {
            return $params;
        }

        
        
        $registered = get_registered_settings();
        foreach ( array_keys( $params ) as $field ) {
            if ( empty( $registered[ $field ]['show_in_rest'] ) ) {
                throw new \InvalidArgumentException( sprintf( '%s cannot be set on this site: its REST settings endpoint does not accept it (WordPress 6.0.1 added it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        $stored_show = (string) get_option( 'show_on_front', 'posts' );
        $show        = $params['show_on_front'] ?? $stored_show;
        $front       = $params['page_on_front'] ?? (int) get_option( 'page_on_front', 0 );
        $blog        = $params['page_for_posts'] ?? (int) get_option( 'page_for_posts', 0 );

        
        
        if ( 'page' === $show && 'page' !== $stored_show ) {
            foreach ( array( 'page_on_front' => $front, 'page_for_posts' => $blog ) as $field => $id ) {
                if ( 0 !== $id ) {
                    self::require_published_page( $field, $id );
                }
            }
        }

        if ( 'page' === $show && 0 === $front ) {
            throw new \InvalidArgumentException( 'Nothing was changed: show_on_front "page" needs page_on_front set to a published page ID.' );
        }
        if ( 'page' !== $show && ( ! empty( $params['page_on_front'] ) || ! empty( $params['page_for_posts'] ) ) ) {
            throw new \InvalidArgumentException( 'Nothing was changed: a page ID has no effect while show_on_front is "posts". Pass show_on_front "page" too.' );
        }
        if ( 0 !== $front && $front === $blog ) {
            throw new \InvalidArgumentException( sprintf( 'Nothing was changed: page %d cannot be both the front page and the posts page.', $front ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $params;
    }

    








    private static function page_id( string $field, $value ): int {
        if ( is_int( $value ) && $value >= 0 ) {
            return $value;
        }
        if ( is_string( $value ) && ctype_digit( $value ) ) {
            return (int) $value;
        }
        throw new \InvalidArgumentException( sprintf( '%s must be a page ID, or 0 to clear it; nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    








    private static function require_published_page( string $field, int $id ): void {
        $page = get_post( $id );
        if ( ! $page || 'page' !== $page->post_type ) {
            throw new \InvalidArgumentException( sprintf( '%s %d is not a page. Use wp_list_pages to find the ID.', $field, $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $status = (string) $page->post_status;
        if ( 'publish' === $status ) {
            return;
        }
        if ( 'trash' === $status ) {
            throw new \InvalidArgumentException( sprintf( 'Page %d is in the trash, not published. Restore it in wp-admin > Pages or pick a published page.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $states = array(
            'draft'   => 'a draft',
            'pending' => 'pending review',
            'future'  => 'scheduled',
        );
        throw new \InvalidArgumentException(
            sprintf( 'Page %d is %s, not published. Publish it first (wp_update_page, status "publish") or pick a published page.', $id, $states[ $status ] ?? $status ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        );
    }

    






    private static function unsupported_message( array $keys ): string {
        $head  = 'Nothing was changed: unsupported setting(s) ';
        $tail  = '. This tool sets only the fields in its schema; change others in wp-admin > Settings.';
        $shown = array_slice( array_values( array_map( array( __CLASS__, 'printable_key' ), $keys ) ), 0, 3 );
        $each  = intdiv( Detailed_Tool_Error::MAX_MESSAGE - strlen( $head ) - strlen( $tail ) - 2 * ( count( $shown ) - 1 ), count( $shown ) );
        foreach ( $shown as $i => $key ) {
            if ( '' === $key ) {
                $shown[ $i ] = '?';
            } elseif ( strlen( $key ) > $each ) {
                $shown[ $i ] = substr( $key, 0, $each - 3 ) . '...';
            }
        }
        return $head . implode( ', ', $shown ) . $tail;
    }

    






    private static function printable_key( $key ): string {
        return (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
    }
}
