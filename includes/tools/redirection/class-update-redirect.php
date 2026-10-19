<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Redirect extends Base_Tool {

    use Redirection_Rest;

    




    const CONTENT_FIELDS = array( 'source', 'action_type', 'target', 'code', 'group_id', 'title', 'regex', 'ignore_case', 'ignore_trailing_slash', 'query_handling', 'position' );

    




    const COMPARED = array( 'source', 'match_type', 'action_type', 'code', 'target', 'title', 'group_id', 'position', 'enabled', 'regex', 'ignore_case', 'ignore_trailing_slash', 'query_handling', 'action_data' );

    public function get_name() {
        return 'wp_redirection_update_redirect';
    }

    public function get_description() {
        return 'Updates a redirect in the Redirection plugin. Only the fields you send change; every other field keeps its value. Required: `id`. Optional: `source`, `action_type`, `target`, `code`, `group_id`, `title` ("" clears it), `regex`, `ignore_case`, `ignore_trailing_slash`, `query_handling`, `position`, `enabled` (false switches the redirect off without deleting it). `target` can only be changed on redirects whose match_type is "url". Returns { updated, changed_fields, redirect }, read back from Redirection; `updated: false` means the redirect already had those values. Requires the Redirection plugin active.';
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
        $properties = array_merge(
            array(
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The redirect id (from wp_redirection_list_redirects).',
                ),
            ),
            Create_Redirect::write_properties( false ),
            array(
                'enabled' => array(
                    'type'        => 'boolean',
                    'description' => 'true switches the redirect on, false switches it off.',
                ),
            )
        );
        return array(
            'type'       => 'object',
            'properties' => $properties,
            'required'   => array( 'id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id = isset( $arguments['id'] ) && is_numeric( $arguments['id'] ) ? (int) $arguments['id'] : 0;
        try {
            $item = $id > 0 ? $this->find_redirect( $id ) : null;
        } catch ( \Throwable $e ) {
            $item = null;
        }
        if ( null === $item ) {
            return '';
        }
        $current = self::normalize_redirect( $item );
        $sent    = array_intersect_key( $arguments, array_flip( array_merge( self::CONTENT_FIELDS, array( 'enabled' ) ) ) );
        return sprintf(
            /* translators: 1: redirect id, 2: source path, 3: target, 4: JSON of the fields being set */
            __( 'Update redirect #%1$d (%2$s → %3$s): set %4$s', 'easy-mcp-ai' ),
            $id,
            $current['source'],
            null !== $current['target'] ? $current['target'] : $current['action_type'],
            wp_json_encode( $sent )
        );
    }

    public function execute( array $arguments ) {
        $this->require_redirection();
        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );

        $content     = array_filter(
            self::CONTENT_FIELDS,
            function ( string $field ) use ( $arguments ): bool {
                return self::sent( $arguments, $field );
            }
        );
        $has_enabled = self::sent( $arguments, 'enabled' );
        if ( empty( $content ) && ! $has_enabled ) {
            throw new \InvalidArgumentException( 'Nothing to update: send at least one field besides id.' );
        }

        $item   = $this->require_redirect( $id );
        $before = self::normalize_redirect( $item );

        if ( ! empty( $content ) ) {
            $this->rest_request( 'POST', self::$redirection_ns . '/redirect/' . $id, $this->merged_body( $item, $arguments ) );
        }
        if ( $has_enabled && rest_sanitize_boolean( $arguments['enabled'] ) !== $before['enabled'] ) {
            $this->rest_request( 'POST', self::$redirection_ns . '/bulk/redirect/' . ( rest_sanitize_boolean( $arguments['enabled'] ) ? 'enable' : 'disable' ), array( 'items' => array( $id ) ) );
        }

        
        
        $after_item = $this->find_redirect( $id );
        if ( null === $after_item ) {
            throw new \RuntimeException( sprintf( 'Redirect %d could not be read back after the update. Check it under Tools › Redirection.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $after   = self::normalize_redirect( $after_item );
        $changed = array_values(
            array_filter(
                self::COMPARED,
                function ( string $field ) use ( $before, $after ): bool {
                    return ( $before[ $field ] ?? null ) !== ( $after[ $field ] ?? null );
                }
            )
        );

        $result = array(
            'updated'        => ! empty( $changed ),
            'changed_fields' => $changed,
            'redirect'       => $after,
        );
        if ( empty( $changed ) ) {
            $result['note'] = 'Nothing changed: the redirect already had these values.';
        }
        return $result;
    }

    





    private static function sent( array $arguments, string $field ): bool {
        return array_key_exists( $field, $arguments ) && null !== $arguments[ $field ];
    }

    









    private function merged_body( array $item, array $arguments ): array {
        $match_type     = '' !== self::str_of( $item, 'match_type' ) ? self::str_of( $item, 'match_type' ) : 'url';
        $current_action = '' !== self::str_of( $item, 'action_type' ) ? self::str_of( $item, 'action_type' ) : 'url';
        $action_type    = isset( $arguments['action_type'] ) && '' !== $arguments['action_type'] ? $arguments['action_type'] : $current_action;
        $this->refuse_unless_one_of( 'action_type', $action_type, self::action_types(), $action_type, implode( ', ', self::action_types() ) );
        if ( 'url' !== $match_type && $action_type !== $current_action ) {
            
            
            
            throw new \InvalidArgumentException( sprintf( 'This redirect matches on "%s", not on the URL alone; change its action under Tools › Redirection.', $match_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $regex       = self::sent( $arguments, 'regex' ) ? rest_sanitize_boolean( $arguments['regex'] ) : ! empty( $item['regex'] );
        $url         = self::sent( $arguments, 'source' ) ? self::parse_source( $arguments['source'] ) : self::str_of( $item, 'url' );
        $action_data = $this->merged_action_data( $item, $arguments, $match_type, $action_type );

        $keep_code  = $action_type === $current_action ? self::int_of( $item, 'action_code' ) : null;
        $match_data = self::array_of( $item, 'match_data' );
        $flags      = $this->source_flags( $arguments, self::array_of( $match_data, 'source' ) );
        $match_data['source'] = array_merge( null !== $flags ? $flags : self::array_of( $match_data, 'source' ), array( 'flag_regex' => $regex ) );

        $body  = array(
            'url'         => $url,
            'match_type'  => $match_type,
            'action_type' => $action_type,
            'action_code' => self::resolve_code( $action_type, $arguments['code'] ?? null, $keep_code ),
            'action_data' => $action_data,
            'group_id'    => self::sent( $arguments, 'group_id' ) ? $this->resolve_group( $arguments['group_id'] ) : self::int_of( $item, 'group_id' ),
            'regex'       => $regex ? 1 : 0,
            'match_data'  => $match_data,
            'position'    => self::sent( $arguments, 'position' ) ? max( 0, (int) $arguments['position'] ) : self::int_of( $item, 'position' ),
        );
        $title = self::sent( $arguments, 'title' ) ? (string) $arguments['title'] : self::str_of( $item, 'title' );
        if ( '' !== $title ) {
            $body['title'] = $title;
        }
        return $body;
    }

    









    private function merged_action_data( array $item, array $arguments, string $match_type, string $action_type ): array {
        $action_data = self::array_of( $item, 'action_data' );
        $redirects   = in_array( $action_type, self::target_actions(), true );
        if ( self::sent( $arguments, 'target' ) ) {
            if ( 'url' !== $match_type ) {
                throw new \InvalidArgumentException( sprintf( 'This redirect matches on "%s", not on the URL alone; its targets live in action_data. Change them under Tools › Redirection.', $match_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( ! $redirects ) {
                throw new \InvalidArgumentException( sprintf( 'target does not apply to action_type "%s"; leave it out.', $action_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $action_data['url'] = trim( (string) $arguments['target'] );
            if ( '' === $action_data['url'] ) {
                throw new \InvalidArgumentException( 'target cannot be empty. To stop the redirect, send enabled: false or delete it.' );
            }
        }
        if ( 'url' === $match_type && $redirects && '' === self::str_of( $action_data, 'url' ) ) {
            throw new \InvalidArgumentException( sprintf( 'target is required to change action_type to "%s".', $action_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $action_data;
    }
}
