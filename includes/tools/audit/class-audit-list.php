<?php
namespace Easy_MCP_AI\Tools\Audit;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Audit_Log_Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















class Audit_List extends Base_Tool {
    public function get_name() { return 'wp_audit_list'; }
    public function get_description() {
        return 'List entries from the MCP audit log: every tool call (reads included), refusals, authentication failures and OAuth authorize/device/refresh events, newest first. Each entry carries the acting user, the credential source (legacy API key or oauth), the OAuth client, the redacted arguments, the result status, the tool duration in milliseconds and the client IP. Filter by free text over tool name and arguments, tool, user, client, source, status, token id or date range. Admin only. For before/after snapshots of what a call changed, use wp_history_list with the entry id as audit_id.';
    }
    public function get_category() { return 'audit'; }
    public function get_required_capability() { return 'manage_options'; }

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
                'search'          => array( 'type' => 'string', 'description' => 'Free-text match over the tool name and the redacted arguments (case-insensitive substring).' ),
                'tool_name'       => array( 'type' => 'string', 'description' => 'Exact tool name, e.g. wp_update_post. Internal events use a leading underscore: _auth_failure, _oauth_authorize, _oauth_device, _oauth_refresh.' ),
                'wp_user_id'      => array( 'type' => 'integer', 'description' => 'WordPress user the credential belongs to.' ),
                'oauth_client_id' => array( 'type' => 'string' ),
                'auth_source'     => array( 'type' => 'string', 'enum' => array( 'legacy', 'oauth', 'unknown' ), 'description' => 'legacy = API key, oauth = OAuth 2.1 grant, unknown = rows written before the source was recorded.' ),
                'result_status'   => array( 'type' => 'string', 'description' => 'success, error, pending, refused, auth_failure, or an OAuth refresh event name.' ),
                'token_id'        => array( 'type' => 'integer', 'description' => 'Credential row id. Combine with auth_source: the id is from the API-key table for legacy rows and from the OAuth access-token table for oauth rows.' ),
                'since'           => array( 'type' => 'string', 'description' => 'GMT datetime, e.g. 2026-05-01 00:00:00' ),
                'until'           => array( 'type' => 'string' ),
                'before_id'       => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Snapshot bound for paging: only rows with id <= this value. Omit on the first page; the response returns the before_id it used, pass that back with the next offset so later pages cannot shift when new rows (including this call\'s own audit row) arrive.' ),
                'limit'           => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ),
                'offset'          => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $args ) {
        $limit  = isset( $args['limit'] )  ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
        $offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

        
        
        $filters = array();
        foreach ( Audit_Log_Repository::filter_keys() as $k ) {
            if ( isset( $args[ $k ] ) && '' !== $args[ $k ] ) {
                $filters[ $k ] = is_scalar( $args[ $k ] ) ? (string) $args[ $k ] : '';
            }
        }
        if ( isset( $filters['auth_source'] ) && ! in_array( $filters['auth_source'], array( 'legacy', 'oauth', Audit_Log_Repository::SOURCE_UNKNOWN ), true ) ) {
            throw new \InvalidArgumentException( 'auth_source must be one of: legacy, oauth, unknown' );
        }
        
        
        
        foreach ( array( 'since', 'until' ) as $dt_key ) {
            if ( ! isset( $filters[ $dt_key ] ) ) {
                continue;
            }
            $ts = strtotime( $filters[ $dt_key ] );
            if ( false === $ts ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %s datetime: %s', $dt_key, $filters[ $dt_key ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $filters[ $dt_key ] = gmdate( 'Y-m-d H:i:s', $ts );
        }

        $repo = new Audit_Log_Repository();

        
        
        
        
        
        $before_id = isset( $args['before_id'] ) ? max( 0, (int) $args['before_id'] ) : 0;
        if ( 0 === $before_id ) {
            $before_id = $repo->max_id();
        }
        if ( $before_id > 0 ) {
            $filters['before_id'] = (string) $before_id;
        } else {
            unset( $filters['before_id'] );
        }

        $total = $repo->count( $filters );
        $rows  = $repo->attach_labels( $repo->query( $filters, $limit, $offset ) );

        $items = array();
        foreach ( $rows as $r ) {
            $arguments = null;
            if ( ! empty( $r['arguments'] ) ) {
                $decoded   = json_decode( (string) $r['arguments'], true );
                $arguments = is_array( $decoded ) ? $decoded : (string) $r['arguments'];
            }
            $items[] = array(
                'id'              => (int) $r['id'],
                'created_at'      => isset( $r['created_at'] ) ? (string) $r['created_at'] : null,
                'tool_name'       => (string) $r['tool_name'],
                'result_status'   => isset( $r['result_status'] ) ? (string) $r['result_status'] : null,
                'auth_source'     => isset( $r['auth_source'] ) && '' !== $r['auth_source'] ? (string) $r['auth_source'] : null,
                'wp_user_id'      => ! empty( $r['wp_user_id'] ) ? (int) $r['wp_user_id'] : null,
                'user_login'      => isset( $r['user_login'] ) ? $r['user_login'] : null,
                'token_id'        => (int) ( $r['token_id'] ?? 0 ),
                'token_name'      => isset( $r['token_name'] ) ? $r['token_name'] : null,
                'oauth_client_id' => isset( $r['oauth_client_id'] ) && '' !== $r['oauth_client_id'] ? (string) $r['oauth_client_id'] : null,
                'client_name'     => isset( $r['client_name'] ) ? $r['client_name'] : null,
                'duration_ms'     => isset( $r['duration_ms'] ) && null !== $r['duration_ms'] && '' !== $r['duration_ms'] ? (int) $r['duration_ms'] : null,
                'ip_address'      => isset( $r['ip_address'] ) ? (string) $r['ip_address'] : null,
                'arguments'       => $arguments,
            );
        }
        return array( 'items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset, 'count' => count( $items ), 'before_id' => $before_id );
    }
}
