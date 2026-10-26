<?php
namespace Easy_MCP_AI\Tools\History;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\History\Change_Log_Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class History_List extends Base_Tool {

    







    private const MAX_ATTACHMENT_CHAIN = 5;

    public function get_name() { return 'wp_history_list'; }
    public function get_description() {
        return 'List change-history entries for write operations performed via MCP. Filter by object, tool, user, or date range. Returns summaries only — call wp_history_get to see before/after payloads. Each item names its object in `object_title` (post or product title, term name, user display name; null when the object is gone, when you may not read it, or when more than five inherit attachments that each have a parent run in a row starting with it, following post_parent, or they loop (a cycle); callers with `easy_mcp_ai_view_all_history` are not refused for any reason but a gone object) and its user in `user_login`. Non-admin callers (without the `easy_mcp_ai_view_all_history` capability) cannot view other users\' history: any `wp_user_id` filter they pass is silently overridden and pinned to their own user ID, with no error or notice.';
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

        $titles = self::object_titles( $rows, $can_view_all );
        $logins = self::user_logins( $rows );

        $items = array();
        foreach ( $rows as $i => $r ) {
            $item = array(
                'id'              => (int) $r['id'],
                'audit_id'        => isset( $r['audit_id'] ) ? (int) $r['audit_id'] : null,
                'tool_name'       => $r['tool_name'],
                'action'          => $r['action'],
                'object_type'     => $r['object_type'],
                'object_id'       => $r['object_id'],
                'object_title'    => $titles[ $i ] ?? null,
                'object_subtype'  => $r['object_subtype'] ?? null,
                'changed_fields'  => ! empty( $r['changed_fields'] ) ? json_decode( $r['changed_fields'], true ) : null,
                'revision_id'     => isset( $r['revision_id'] ) ? (int) $r['revision_id'] : null,
                'wp_user_id'      => (int) ( $r['wp_user_id'] ?? 0 ),
                'user_login'      => $logins[ (int) ( $r['wp_user_id'] ?? 0 ) ] ?? null,
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

    


























    private static function object_titles( array $rows, bool $can_view_all ): array {
        $wanted = array();
        $ids    = array( 'post' => array(), 'term' => array(), 'user' => array() );
        foreach ( $rows as $i => $r ) {
            $target = self::title_target( (string) ( $r['object_type'] ?? '' ), (string) ( $r['object_id'] ?? '' ) );
            if ( null === $target ) {
                continue;
            }
            $wanted[ $i ]                    = $target;
            $ids[ $target[0] ][ $target[1] ] = true;
        }

        if ( $ids['post'] && function_exists( '_prime_post_caches' ) ) {
            \_prime_post_caches( array_keys( $ids['post'] ), false, false );
        }
        if ( $ids['term'] && function_exists( '_prime_term_caches' ) ) {
            \_prime_term_caches( array_keys( $ids['term'] ), false );
        }
        if ( $ids['user'] && function_exists( 'cache_users' ) ) {
            \cache_users( array_keys( $ids['user'] ) );
        }

        $exists = array();
        foreach ( $ids as $kind => $set ) {
            $exists[ $kind ] = $set ? self::existing_ids( $kind, array_keys( $set ) ) : array();
        }
        if ( ! $can_view_all && $exists['post'] ) {
            self::prime_inherited_parents( array_keys( $exists['post'] ) );
        }

        $labels = array();
        $titles = array();
        foreach ( $wanted as $i => $target ) {
            $key = $target[0] . ':' . $target[1];
            if ( ! array_key_exists( $key, $labels ) ) {
                $labels[ $key ] = isset( $exists[ $target[0] ][ $target[1] ] )
                    ? self::object_title( $target[0], $target[1], $can_view_all )
                    : null;
            }
            $titles[ $i ] = $labels[ $key ];
        }
        return $titles;
    }

    








    private static function existing_ids( string $kind, array $ids ): array {
        global $wpdb;
        $tables = array(
            'post' => array( 'posts', 'posts', 'ID' ),
            'term' => array( 'terms', 'terms', 'term_id' ),
            'user' => array( 'users', 'users', 'ID' ),
        );
        list( $group, $table, $column ) = $tables[ $kind ];

        $uncached = function_exists( '_get_non_cached_ids' ) ? \_get_non_cached_ids( $ids, $group ) : $ids;
        $found    = array_diff( $ids, $uncached );
        if ( $uncached ) {
            $in = implode( ',', array_map( 'intval', $uncached ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- integer IDs only; the table and column come from the fixed map above.
            $rows  = (array) $wpdb->get_col( "SELECT {$column} FROM {$wpdb->$table} WHERE {$column} IN ({$in})" );
            $found = array_merge( $found, array_map( 'intval', $rows ) );
        }
        return array_fill_keys( $found, true );
    }

    











    private static function prime_inherited_parents( array $ids ): void {
        if ( ! function_exists( '_prime_post_caches' ) ) {
            return;
        }
        
        
        $walked = array_fill_keys( $ids, true );
        $primed = array();
        $level  = $ids;
        while ( $level ) {
            $parents = array();
            foreach ( $level as $id ) {
                $post   = \get_post( $id );
                $parent = $post ? (int) $post->post_parent : 0;
                if ( $parent > 0 && ! isset( $primed[ $parent ] ) && ( 'revision' === $post->post_type || ( 'attachment' === $post->post_type && 'inherit' === $post->post_status ) ) ) {
                    $parents[ $parent ] = true;
                }
            }
            if ( ! $parents ) {
                return;
            }
            $primed += $parents;
            \_prime_post_caches( array_keys( $parents ), false, true );
            $level   = array_keys( array_diff_key( self::existing_ids( 'post', array_keys( $parents ) ), $walked ) );
            $walked += array_fill_keys( $level, true );
        }
    }

    












    private static function attachment_chain_exceeds_limit( $post ): bool {
        for ( $depth = 0; $post && 'attachment' === $post->post_type && 'inherit' === $post->post_status && (int) $post->post_parent > 0; $depth++ ) {
            if ( $depth >= self::MAX_ATTACHMENT_CHAIN ) {
                return true;
            }
            $post = \get_post( (int) $post->post_parent );
        }
        return false;
    }

    







    private static function title_target( string $type, string $object_id ): ?array {
        if ( 'meta' === $type ) {
            $object_id = explode( ':', $object_id, 2 )[0];
            $type      = 'post';
        }
        if ( ! in_array( $type, array( 'post', 'wc_product', 'term', 'user' ), true ) || ! ctype_digit( $object_id ) || '0' === $object_id ) {
            return null;
        }
        return array( 'wc_product' === $type ? 'post' : $type, (int) $object_id );
    }

    









    private static function object_title( string $kind, int $id, bool $can_view_all ): ?string {
        switch ( $kind ) {
            case 'post':
                $post = \get_post( $id );
                if ( ! $post || ( ! $can_view_all && ( self::attachment_chain_exceeds_limit( $post ) || ! \current_user_can( 'read_post', $id ) ) ) ) {
                    return null;
                }
                
                
                return (string) $post->post_title;
            case 'term':
                $term = \get_term( $id );
                if ( ! is_object( $term ) || \is_wp_error( $term ) || ( ! $can_view_all && ! \current_user_can( 'manage_categories' ) ) ) {
                    return null;
                }
                return (string) $term->name;
            case 'user':
                $user = \get_userdata( $id );
                if ( ! $user || ( ! $can_view_all && (int) \get_current_user_id() !== $id && ! \current_user_can( 'list_users' ) ) ) {
                    return null;
                }
                return (string) $user->display_name;
        }
        return null;
    }

    










    private static function user_logins( array $rows ): array {
        $ids = array();
        foreach ( $rows as $r ) {
            if ( ! empty( $r['wp_user_id'] ) ) {
                $ids[ (int) $r['wp_user_id'] ] = true;
            }
        }
        $logins = array();
        if ( $ids && function_exists( 'get_users' ) ) {
            foreach ( (array) \get_users( array( 'include' => array_keys( $ids ), 'blog_id' => 0, 'fields' => array( 'ID', 'user_login' ) ) ) as $u ) {
                if ( is_object( $u ) && isset( $u->ID ) ) {
                    $logins[ (int) $u->ID ] = (string) $u->user_login;
                }
            }
        }
        return $logins;
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
