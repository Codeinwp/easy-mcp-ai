<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Redirect extends Base_Tool {

    use Redirection_Rest;

    public function get_name() {
        return 'wp_redirection_create_redirect';
    }

    public function get_description() {
        return 'Creates a redirect in the Redirection plugin, matched on the URL path. Required: `source` (the path to redirect from, e.g. "/old-page"; Redirection stores it as its own editor does: a URL on another site becomes a match on that domain). Optional: `action_type` ("url" default: redirect to `target`; "pass": serve `target` without redirecting; "error": answer an HTTP error; "random": a random post; "nothing"), `target` (required for url and pass, e.g. "/new-page" or "https://example.com/"), `code` (url/random: 301 default, 302, 303, 304, 307, 308; error: 404 default, 410, 400, 401, 403, 418, 451, 500-504), `group_id` (default: Redirection\'s default group; see wp_redirection_list_groups), `title`, `regex` (source is a regular expression), `ignore_case`, `ignore_trailing_slash`, `query_handling` ("exact", "ignore", "pass"; unset uses Redirection\'s defaults), `position` (unset or 0: last in the group). Redirection\'s own rules apply: like its editor, it stores a redirect whose source another redirect already uses (the first one runs) and one whose target is its own source. A source that would redirect the home page, the sign-in page or the REST API is refused unless `allow_protected_source` is true. Returns { created: true, redirect: { id, source, action_type, code, target, group_id, enabled, … } }, read back from Redirection. Requires the Redirection plugin active with its setup finished.';
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
            'properties' => self::write_properties( true ),
            'required'   => array( 'source' ),
        );
    }

    public static function write_properties( bool $create ): array {
        $properties = array(
            'source'                => array(
                'type'        => 'string',
                'description' => 'Path to redirect from, e.g. "/old-page". With regex true, a regular expression. Redirection stores it as its own editor does.',
            ),
            'action_type'           => array(
                'type'        => 'string',
                'enum'        => array( 'url', 'pass', 'error', 'random', 'nothing' ),
                'description' => 'url: redirect to target. pass: serve target without redirecting. error: answer the HTTP error in code. random: a random post. nothing: do nothing (stops later redirects matching).',
            ),
            'target'                => array(
                'type'        => 'string',
                'description' => 'Where to send the visitor, for action_type url or pass: a path such as "/new-page" or a full URL.',
            ),
            'code'                  => array(
                'type'        => 'integer',
                'description' => 'HTTP code. url/random: 301, 302, 303, 304, 307 or 308. error: 400, 401, 403, 404, 410, 418, 451, 500, 501, 502, 503 or 504. Not used by pass or nothing.',
            ),
            'group_id'              => array(
                'type'        => 'integer',
                'description' => 'Group id from wp_redirection_list_groups.',
            ),
            'title'                 => array(
                'type'        => 'string',
                'description' => 'A note describing the redirect.' . ( $create ? '' : ' An empty string clears it.' ),
            ),
            'regex'                 => array(
                'type'        => 'boolean',
                'description' => 'Whether source is a regular expression.',
            ),
            'ignore_case'           => array(
                'type'        => 'boolean',
                'description' => 'Match the source regardless of letter case.',
            ),
            'ignore_trailing_slash' => array(
                'type'        => 'boolean',
                'description' => 'Match the source with or without a trailing slash.',
            ),
            'query_handling'        => array(
                'type'        => 'string',
                'enum'        => array( 'exact', 'ignore', 'pass' ),
                'description' => 'Query parameters: exact (must match), ignore (any query matches), pass (any query matches and is passed on to the target).',
            ),
            'position'              => array(
                'type'        => 'integer',
                'description' => 'Order within the group; lower runs first.' . ( $create ? ' Unset or 0: Redirection puts the new redirect last.' : '' ),
                'minimum'     => 0,
            ),
            Protected_Sources::OVERRIDE => array(
                'type'        => 'boolean',
                'description' => 'true only when the user asked for this exact redirect of the home page, the sign-in page (wp-login.php) or the REST API (/wp-json/, ?rest_route=). Without it, a source that would redirect one of them is refused' . ( $create ? '.' : ', and so is switching on, or changing anything but the title or position of, a redirect that has one.' ),
            ),
        );
        return $properties;
    }

    public function execute( array $arguments ) {
        $this->require_redirection();

        $regex       = isset( $arguments['regex'] ) && rest_sanitize_boolean( $arguments['regex'] );
        $source      = self::parse_source( $arguments['source'] ?? null );
        $action_type = isset( $arguments['action_type'] ) && '' !== $arguments['action_type'] ? $arguments['action_type'] : 'url';
        $this->refuse_unless_one_of( 'action_type', $action_type, self::action_types(), $action_type, implode( ', ', self::action_types() ) );
        $target = self::checked_target( $arguments, $action_type );

        $body = array(
            'url'         => $source,
            'match_type'  => 'url',
            'action_type' => $action_type,
            'action_code' => self::resolve_code( $action_type, $arguments['code'] ?? null ),
            'group_id'    => $this->resolve_group( $arguments['group_id'] ?? null ),
            'regex'       => $regex ? 1 : 0,
        );
        $body = array_merge( $body, $this->optional_fields( $arguments, $target, $regex ) );
        $row  = Protected_Sources::overridden( $arguments ) ? null : Protected_Sources::as_stored( $body );
        Protected_Sources::refuse( 'This source', $row );
        $stored = null !== $row && isset( $row['url'] ) && is_string( $row['url'] ) ? $row['url'] : self::stored_source( $source, $regex );

        $before = $this->highest_redirect_id();
        $data = $this->rest_request( 'POST', self::$redirection_ns . '/redirect', $body );

        return array(
            'created'  => true,
            'redirect' => self::normalize_redirect( self::created_item( self::rows( $data ), $before, $stored, $body ) ),
        );
    }

    private static function checked_target( array $arguments, string $action_type ): string {
        $target = isset( $arguments['target'] ) && is_string( $arguments['target'] ) ? trim( $arguments['target'] ) : '';
        if ( ! in_array( $action_type, self::target_actions(), true ) ) {
            if ( '' !== $target ) {
                throw new \InvalidArgumentException( sprintf( 'target does not apply to action_type "%s"; leave it out.', $action_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return '';
        }
        if ( '' === $target ) {
            throw new \InvalidArgumentException( sprintf( 'target is required for action_type "%s": where to send the visitor, e.g. /new-page.', $action_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $target;
    }

    private function optional_fields( array $arguments, string $target, bool $regex ): array {
        $fields = array();
        if ( '' !== $target ) {
            $fields['action_data'] = array( 'url' => $target );
        }
        if ( isset( $arguments['title'] ) && '' !== trim( (string) $arguments['title'] ) ) {
            $fields['title'] = (string) $arguments['title'];
        }
        if ( isset( $arguments['position'] ) && '' !== $arguments['position'] ) {
            $fields['position'] = max( 0, (int) $arguments['position'] );
        }
        $flags = $this->source_flags( $arguments, self::default_source_flags() );
        if ( null !== $flags ) {
            $flags['flag_regex']  = $regex;
            $fields['match_data'] = array( 'source' => $flags );
        }
        return $fields;
    }

    private static function created_item( array $items, int $before, string $stored, array $body ): array {
        $new = array_values(
            array_filter(
                $items,
                function ( array $item ) use ( $before ): bool {
                    return self::int_of( $item, 'id' ) > $before;
                }
            )
        );
        $mine = array_values(
            array_filter(
                $new,
                function ( array $item ) use ( $stored, $body ): bool {
                    return self::str_of( $item, 'url' ) === $stored
                        && self::str_of( $item, 'action_type' ) === $body['action_type']
                        && self::int_of( $item, 'action_code' ) === $body['action_code']
                        && self::stored_target( $item ) === ( isset( $body['action_data']['url'] ) ? (string) $body['action_data']['url'] : '' );
                }
            )
        );
        if ( 1 === count( $mine ) ) {
            return $mine[0];
        }
        if ( empty( $mine ) && 1 === count( $new ) ) {
            return $new[0];
        }
        throw new \RuntimeException( empty( $new )
            ? 'Redirection answered success, but no new redirect appeared. Check Tools › Redirection before retrying.'
            : sprintf( 'The redirect was created, but other redirects were created at the same moment and it could not be told apart. Find it with wp_redirection_list_redirects (search: %s) before retrying.', $stored ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function stored_target( array $item ): string {
        $data = self::array_of( $item, 'action_data' );
        return '' !== self::str_of( $data, 'url' ) ? self::str_of( $data, 'url' ) : self::str_of( $data, 'url_from' );
    }

    private function highest_redirect_id(): int {
        $data = $this->rest_request( 'GET', self::$redirection_ns . '/redirect', array( 'per_page' => self::$redirection_min_per_page, 'page' => 0, 'direction' => 'desc' ) );
        $max  = 0;
        foreach ( self::rows( $data ) as $item ) {
            $max = max( $max, self::int_of( $item, 'id' ) );
        }
        return $max;
    }
}
