<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Site_Settings extends Base_Tool {

    
    const PAGE_FIELDS = array( 'page_on_front', 'page_for_posts' );

    public function get_name() {
        return 'wp_update_site_settings';
    }

    public function get_description() {
        return 'Updates WordPress site settings (PATCH semantics — only supplied fields change). Editable: `title`, `description` (tagline), `timezone` (e.g. "America/New_York" or "Europe/London"; `timezone_string` is accepted as an alias; a timezone WordPress does not know is refused and nothing is changed), `date_format` (e.g. "F j, Y"), `time_format` (e.g. "g:i a"), `posts_per_page` (integer; -1 shows every post, as WordPress allows), `language` (the site locale, e.g. "de_DE"; "en_US" or "" for English; only a language already installed on this site is accepted — any other is refused and nothing is changed, because WordPress would silently keep the current one), and the homepage: `show_on_front` ("posts" for the latest posts, "page" for a static page), `page_on_front` (the static homepage) and `page_for_posts` (the page that lists posts) — page IDs of published pages, 0 clears one. To make a page the homepage, send `show_on_front: "page"` with `page_on_front`; a page ID while the homepage shows posts, the same page for both, or a page that is not published is refused. Any other key is refused and nothing is changed: admin email, permalinks and site URL are not editable here (wp-admin > Settings). Requires `manage_options` (administrators only). Changes take effect immediately; a new language applies from the next request. Returns { updated, changed_fields, unchanged_fields, title, description, show_on_front, page_on_front, page_for_posts, language (only when sent) }: `updated` is true when at least one setting changed its stored value, and false, with `changed_fields` empty, when every setting sent already had that value.';
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
                    'description' => 'The number of posts to show per page. -1 shows every post (WordPress keeps -1; any other negative number is stored as its absolute value, as before).',
                ),
                'language'       => array(
                    'type'        => 'string',
                    'description' => 'Site language: a locale installed on this site (e.g. "de_DE", "fr_FR"), or "en_US" / "" for English. A locale that is not installed is refused and nothing is changed; installing a language is not done here.',
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
                'Settable here: ' . implode( ', ', $settable ) . '. Admin email, permalinks and site URL are not; change them in wp-admin > Settings.' // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $params = array_merge( self::settings_params( $arguments ), $this->front_page_params( $arguments ) );
        if ( empty( $params ) ) {
            throw new \InvalidArgumentException( 'No setting given. Pass at least one of: ' . implode( ', ', $settable ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        self::refuse_unknown_timezone( $params );

        $before = $this->stored_settings( $params );

        $data = $this->rest_request( 'POST', '/wp/v2/settings', $params );
        $data = is_array( $data ) ? $data : array();

        list( $changed, $unchanged ) = self::changed_and_unchanged( $params, $before, $data );

        $result = array(
            'updated'          => ! empty( $changed ),
            'title'            => $data['title'] ?? null,
            'description'      => $data['description'] ?? null,
            'show_on_front'    => $data['show_on_front'] ?? null,
            'page_on_front'    => $data['page_on_front'] ?? null,
            'page_for_posts'   => $data['page_for_posts'] ?? null,
            'changed_fields'   => $changed,
            'unchanged_fields' => $unchanged,
        );
        if ( isset( $params['language'] ) ) {
            $result['language'] = isset( $data['language'] ) ? (string) $data['language'] : '';
        }
        return $result;
    }

    








    private static function settings_params( array $arguments ): array {
        $params = self::text_params( $arguments );

        if ( isset( $arguments['posts_per_page'] ) ) {
            $params['posts_per_page'] = self::posts_per_page_value( $arguments['posts_per_page'] );
        }

        if ( isset( $arguments['language'] ) ) {
            $params['language'] = self::resolve_language( $arguments['language'] );
        }
        return $params;
    }

    






    private static function text_params( array $arguments ): array {
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
        return $params;
    }

    










    private static function posts_per_page_value( $ppp ): int {
        return ( is_numeric( $ppp ) && -1 === (int) $ppp ) ? -1 : absint( $ppp );
    }

    












    private static function refuse_unknown_timezone( array $params ): void {
        $zone_group = version_compare( (string) get_bloginfo( 'version' ), '6.1', '>=' ) ? \DateTimeZone::ALL_WITH_BC : \DateTimeZone::ALL;
        if ( isset( $params['timezone'] ) && '' !== $params['timezone']
            && ! in_array( $params['timezone'], timezone_identifiers_list( $zone_group ), true ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'timezone "%s" is not a timezone WordPress accepts; nothing was changed. Use an identifier such as "Europe/London" or "UTC" (offsets like "UTC+2" are not accepted).', $params['timezone'] ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    









    private function stored_settings( array $params ): array {
        $before = $this->rest_request( 'GET', '/wp/v2/settings' );
        $before = is_array( $before ) ? $before : array();
        foreach ( array_keys( $params ) as $field ) {
            if ( ! array_key_exists( $field, $before ) ) {
                throw new \RuntimeException(
                    sprintf( 'WordPress does not offer the setting "%s" through its REST settings endpoint on this site (a plugin may have unregistered it), so it cannot be saved; nothing was changed.', $field ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
        }
        return $before;
    }

    










    private static function changed_and_unchanged( array $params, array $before, array $data ): array {
        $changed   = array();
        $unchanged = array();
        foreach ( array_keys( $params ) as $field ) {
            $old = self::comparable( $field, $before[ $field ] );
            $new = self::comparable( $field, array_key_exists( $field, $data ) ? $data[ $field ] : $before[ $field ] );
            if ( $old === $new ) {
                $unchanged[] = $field;
            } else {
                $changed[] = $field;
            }
        }
        return array( $changed, $unchanged );
    }

    














    private static function resolve_language( $value ): string {
        if ( ! is_string( $value ) ) {
            throw new \InvalidArgumentException( 'language must be a locale string such as "de_DE", or "en_US" for English; nothing was changed.' );
        }
        $locale = trim( $value );
        if ( '' === $locale || 'en_US' === $locale ) {
            return '';
        }
        $allowed = self::installed_languages();
        if ( in_array( $locale, $allowed, true ) ) {
            return $locale;
        }
        throw self::language_not_installed( $locale, $allowed );
    }

    





    private static function installed_languages(): array {
        $allowed = function_exists( 'get_available_languages' ) ? (array) get_available_languages() : array();
        if ( ! is_multisite() && defined( 'WPLANG' ) && '' !== WPLANG && 'en_US' !== WPLANG ) {
            $allowed[] = WPLANG;
        }
        return $allowed;
    }

    







    private static function language_not_installed( string $locale, array $allowed ): \InvalidArgumentException {
        $shown     = function_exists( 'mb_substr' ) ? mb_substr( $locale, 0, 30 ) : substr( $locale, 0, 30 );
        $installed = array_slice( array_values( array_unique( array_map( 'strval', $allowed ) ) ), 0, 5 );
        $list      = $installed ? implode( ', ', $installed ) . ( count( $allowed ) > 5 ? ', …' : '' ) . ', en_US' : 'en_US only';
        return new \InvalidArgumentException(
            sprintf( 'language "%1$s" is not installed on this site; WordPress would keep the current one, so nothing was changed. Installed: %2$s.', $shown, $list ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        );
    }

    









    private static function comparable( string $field, $value ) {
        if ( 'language' === $field && ( '' === $value || 'en_US' === $value || null === $value ) ) {
            return 'en_US';
        }
        return $value;
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
