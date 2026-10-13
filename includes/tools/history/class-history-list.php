<?php
namespace Easy_MCP_AI\Tools\History;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\History\Change_Log_Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class History_List extends Base_Tool {
    public function get_name() { return 'wp_history_list'; }
    public function get_description() {
        return 'List change-history entries for write operations performed via MCP. Filter by object, tool, user, or date range. Returns summaries only — call wp_history_get to see before/after payloads. Non-admin callers (without the `easy_mcp_ai_view_all_history` capability) cannot view other users\' history: any `wp_user_id` filter they pass is silently overridden and pinned to their own user ID, with no error or notice.';
    }
    public function get_category() { return 'history'; }
    public function get_required_capability() { return 'read'; }

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
                'object_type'     => array( 'type' => 'string', 'description' => 'Filter by object type. Core content: post, term, user, comment, option. Term assignments: object_terms (object_id "{post_id}:{taxonomy}", before/after term_ids). Custom fields: meta (post meta), term_meta, user_meta, comment_meta. Network settings: site_option. WooCommerce: wc_product, wc_order. BuddyPress: bp_activity. Raw database capture: db_row (full before/after) and db_sql (statement only). Remote services: external. Free-form string rather than an enum, so a type added in a later release does not break existing callers.' ),
                'object_id'       => array( 'type' => 'string' ),
                'tool_name'       => array( 'type' => 'string' ),
                'wp_user_id'      => array( 'type' => 'integer', 'description' => 'Filter by originating user ID. Non-admin callers cannot filter by another user\'s ID — this is silently overridden with their own user ID.' ),
                'oauth_client_id' => array( 'type' => 'string' ),
                'auth_source'     => array( 'type' => 'string', 'enum' => array( 'legacy', 'oauth' ) ),
                'action'          => array( 'type' => 'string', 'enum' => array( 'create', 'update', 'delete' ) ),
                'audit_id'        => array( 'type' => 'integer', 'description' => 'Filter to one MCP request audit id.' ),
                'since'           => array( 'type' => 'string', 'description' => 'GMT datetime, e.g. 2026-05-01 00:00:00' ),
                'until'           => array( 'type' => 'string' ),
                'limit'           => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ),
                'offset'          => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $args ) {
        $limit  = isset( $args['limit'] )  ? max( 1, min( 200, (int) $args['limit'] ) )  : 50;
        $offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

        
        
        
        
        $can_view_all = \current_user_can( 'easy_mcp_ai_view_all_history' );
        $self_scope   = $can_view_all
            ? array()
            : array( 'wp_user_id' => (int) \get_current_user_id() );

        $extra_filters = \apply_filters(
            'easy_mcp_ai_history_query_scope',
            array(),
            $args,
            (int) \get_current_user_id(),
            $can_view_all
        );

        
        
        
        $allowed_keys = array( 'object_type', 'object_id', 'tool_name', 'wp_user_id', 'oauth_client_id', 'auth_source', 'action', 'audit_id', 'since', 'until' );
        $filters      = array();
        foreach ( $allowed_keys as $k ) {
            if ( isset( $args[ $k ] ) ) {
                $filters[ $k ] = $args[ $k ];
            }
        }
        $filters = $this->validate_choice_filters( $filters );
        
        
        
        foreach ( array( 'since', 'until' ) as $dt_key ) {
            if ( ! isset( $filters[ $dt_key ] ) || '' === $filters[ $dt_key ] ) {
                continue;
            }
            $ts = strtotime( (string) $filters[ $dt_key ] );
            if ( false === $ts ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %s datetime: %s', $dt_key, (string) $filters[ $dt_key ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $filters[ $dt_key ] = gmdate( 'Y-m-d H:i:s', $ts );
        }
        
        
        
        $list_columns = \Easy_MCP_AI\History\Change_Log_Repository::list_columns();
        $rows = ( new Change_Log_Repository() )->query(
            array_merge( $filters, (array) $extra_filters, $self_scope ),
            $limit,
            $offset,
            $list_columns
        );

        $items = array();
        foreach ( $rows as $r ) {
            $item = array(
                'id'              => (int) $r['id'],
                'audit_id'        => isset( $r['audit_id'] ) ? (int) $r['audit_id'] : null,
                'tool_name'       => $r['tool_name'],
                'action'          => $r['action'],
                'object_type'     => $r['object_type'],
                'object_id'       => $r['object_id'],
                'object_subtype'  => $r['object_subtype'] ?? null,
                'changed_fields'  => ! empty( $r['changed_fields'] ) ? json_decode( $r['changed_fields'], true ) : null,
                'revision_id'     => isset( $r['revision_id'] ) ? (int) $r['revision_id'] : null,
                'wp_user_id'      => (int) ( $r['wp_user_id'] ?? 0 ),
                'oauth_client_id' => $r['oauth_client_id'] ?? null,
                'auth_source'     => $r['auth_source'] ?? null,
                'created_at'      => $r['created_at'] ?? null,
                'truncated'       => ! empty( $r['truncated'] ),
                
                
                
                
                'capture_mode'    => ( isset( $r['capture_mode'] ) && '' !== $r['capture_mode'] ) ? $r['capture_mode'] : 'hooked',
            );
            if ( $can_view_all && isset( $r['ip_address'] ) ) {
                $item['ip_address'] = $r['ip_address'];
            }
            $items[] = $item;
        }
        return array( 'items' => $items, 'limit' => $limit, 'offset' => $offset, 'count' => count( $items ) );
    }

    













    private function validate_choice_filters( array $filters ): array {
        $choices = array(
            'action'      => array( 'create', 'update', 'delete' ),
            'auth_source' => array( 'legacy', 'oauth' ),
        );
        foreach ( $choices as $key => $allowed ) {
            if ( ! isset( $filters[ $key ] ) ) {
                continue;
            }
            if ( '' === $filters[ $key ] ) {
                unset( $filters[ $key ] );
                continue;
            }
            $this->refuse_unless_one_of( $key, $filters[ $key ], $allowed, $filters[ $key ], implode( ', ', $allowed ) );
        }
        return $filters;
    }
}
