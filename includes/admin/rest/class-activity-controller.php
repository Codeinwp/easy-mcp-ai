<?php








namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\History\Change_Log_Repository;
use Easy_MCP_AI\Rest_Routes\Route_Gate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../../rest-routes/class-route-gate.php';

class Activity_Controller extends Admin_Rest_Controller {

    const PER_PAGE_DEFAULT = 25;
    const PER_PAGE_MAX     = 100;

    
    const AUDIT_STATUSES = array( 'ok', 'error', 'refused', 'auth_failure', 'pending' );

    const AUDIT_SOURCES = array( 'legacy', 'oauth', 'unknown' );

    const CHANGE_ACTIONS = array( 'create', 'update', 'delete' );

    
    const OBJECT_TYPES_TRANSIENT = 'easy_mcp_ai_change_log_object_types';

    
    private $db;

    
    private $changes;

    
    private $audit;

    
    private $cleanup;

    





    public function __construct( $db, $changes, $audit, callable $cleanup ) {
        $this->db      = $db;
        $this->changes = $changes;
        $this->audit   = $audit;
        $this->cleanup = $cleanup;
    }

    public function register_routes() {
        $this->register_route( '/activity/audit', \WP_REST_Server::READABLE, array( $this, 'get_audit' ) );
        $this->register_route( '/activity/audit/cleanup', \WP_REST_Server::CREATABLE, array( $this, 'cleanup_audit' ) );
        $this->register_route( '/activity/changes', \WP_REST_Server::READABLE, array( $this, 'get_changes' ) );
        $this->register_route( '/activity/changes/cleanup', \WP_REST_Server::CREATABLE, array( $this, 'cleanup_changes' ) );
        $this->register_route( '/activity/changes/(?P<id>\d+)', \WP_REST_Server::READABLE, array( $this, 'get_change' ) );
    }

    

    



    public function get_audit( $request ) {
        $paging = self::paging( $request );
        if ( \is_wp_error( $paging ) ) {
            return $paging;
        }
        $filters = self::audit_filters( self::params( $request ) );
        if ( \is_wp_error( $filters ) ) {
            return $filters;
        }

        
        if ( isset( $filters['id'] ) ) {
            $row   = $this->audit->find( $filters['id'] );
            $rows  = ( $row && 1 === $paging['page'] ) ? array( $row ) : array();
            $total = $row ? 1 : 0;
        } else {
            $total = $this->audit->count( $filters );
            $rows  = $this->audit->query( $filters, $paging['per_page'], $paging['offset'] );
        }
        $rows   = $this->audit->attach_labels( $rows );
        $counts = $this->change_counts( array_column( $rows, 'id' ) );

        $out = array();
        foreach ( $rows as $row ) {
            $out[] = self::audit_row( $row, $counts[ (int) $row['id'] ] ?? 0 );
        }

        return $this->ok( array(
            'total'         => (int) $total,
            'page'          => $paging['page'],
            'perPage'       => $paging['per_page'],
            'retentionDays' => self::audit_retention_days(),
            'rows'          => $out,
            'filterOptions' => $this->audit_filter_options(),
        ) );
    }

    
    private function audit_filter_options() {
        $users = array();
        foreach ( array_filter( array_map( 'intval', $this->audit->distinct( 'wp_user_id' ) ) ) as $user_id ) {
            $users[] = self::user_summary( $user_id );
        }
        return array( 'users' => $users );
    }

    



    public function cleanup_audit( $request ) {
        $retention = self::audit_retention_days();
        $more      = call_user_func( $this->cleanup, $this->db->prefix . 'easy_mcp_ai_audit_log', $retention );
        return $this->ok( array( 'retentionDays' => $retention, 'more' => (bool) $more ) );
    }

    






    public static function audit_filters( array $params ) {
        $filters = array();

        $search = trim( (string) ( $params['search'] ?? '' ) );
        if ( '' !== $search ) {
            $filters['search'] = $search;
        }
        $tool = trim( (string) ( $params['tool'] ?? '' ) );
        if ( '' !== $tool ) {
            $filters['tool_name'] = $tool;
        }

        $status = (string) ( $params['status'] ?? '' );
        if ( '' !== $status ) {
            if ( ! in_array( $status, self::AUDIT_STATUSES, true ) ) {
                return self::invalid( 'status' );
            }
            $filters['result_status'] = 'ok' === $status ? 'success' : $status;
        }

        $source = (string) ( $params['source'] ?? '' );
        if ( '' !== $source ) {
            if ( ! in_array( $source, self::AUDIT_SOURCES, true ) ) {
                return self::invalid( 'source' );
            }
            $filters['auth_source'] = $source;
        }

        foreach ( array( 'user' => 'wp_user_id', 'id' => 'id' ) as $param => $key ) {
            $value = (string) ( $params[ $param ] ?? '' );
            if ( '' === $value ) {
                continue;
            }
            if ( ! ctype_digit( $value ) || (int) $value < 1 ) {
                return self::invalid( $param );
            }
            $filters[ $key ] = (int) $value;
        }

        $client = (string) ( $params['client'] ?? '' );
        if ( '' !== $client ) {
            $parsed = self::parse_client( $client );
            if ( ! $parsed ) {
                return self::invalid( 'client' );
            }
            $filters = array_merge( $filters, $parsed );
        }

        foreach ( array( 'from' => 'since', 'to' => 'until' ) as $param => $key ) {
            $raw = (string) ( $params[ $param ] ?? '' );
            if ( '' === $raw ) {
                continue;
            }
            $coerced = self::coerce_datetime( $raw );
            if ( '' === $coerced ) {
                return self::invalid( $param );
            }
            $filters[ $key ] = $coerced;
        }

        return $filters;
    }

    





    public static function parse_client( $client ) {
        if ( preg_match( '/^token:(\d+)$/', $client, $m ) && (int) $m[1] > 0 ) {
            return array( 'auth_source' => 'legacy', 'token_id' => (int) $m[1] );
        }
        if ( preg_match( '/^oauth:([A-Za-z0-9_\-.:]{1,191})$/', $client, $m ) ) {
            return array( 'auth_source' => 'oauth', 'oauth_client_id' => $m[1] );
        }
        return null;
    }

    







    public static function audit_row( array $row, $count ) {
        $credential = \Easy_MCP_AI\Audit_Log_Repository::credential_label( $row );
        $status     = strtolower( (string) ( $row['result_status'] ?? 'success' ) );
        $arguments  = null;
        if ( ! empty( $row['arguments'] ) ) {
            $decoded   = json_decode( (string) $row['arguments'], true );
            $arguments = is_array( $decoded ) ? $decoded : null;
        }
        $error = null;
        
        if ( 'auth_failure' === $status && is_array( $arguments ) && isset( $arguments['reason'] ) && is_string( $arguments['reason'] ) ) {
            $error = $arguments['reason'];
        }
        $ip = (string) ( $row['ip_address'] ?? '' );
        return array(
            'id'           => (int) ( $row['id'] ?? 0 ),
            'createdAt'    => Dashboard_Controller::iso8601( $row['created_at'] ?? null ),
            'client'       => array(
                'kind' => 'oauth' === $credential['source'] ? 'oauth' : 'token',
                'name' => (string) $credential['label'],
                
                'id'   => 'oauth' === $credential['source'] && ! empty( $row['client_name'] ) && ! empty( $row['oauth_client_id'] ) ? (string) $row['oauth_client_id'] : null,
            ),
            'source'       => $credential['source'],
            'user'         => self::user_summary( (int) ( $row['wp_user_id'] ?? 0 ), $row['user_login'] ?? null ),
            'toolName'     => (string) ( $row['tool_name'] ?? '' ),
            
            'request'      => is_array( $arguments ) ? Route_Gate::request_summary( (string) ( $row['tool_name'] ?? '' ), $arguments ) : null,
            'changesCount' => (int) $count,
            'arguments'    => $arguments,
            'status'       => 'success' === $status ? 'ok' : $status,
            'error'        => $error,
            'ip'           => '' !== $ip ? $ip : null,
            'durationMs'   => isset( $row['duration_ms'] ) && null !== $row['duration_ms'] ? (int) $row['duration_ms'] : null,
        );
    }

    

    



    public function get_changes( $request ) {
        $paging = self::paging( $request );
        if ( \is_wp_error( $paging ) ) {
            return $paging;
        }
        $filters = self::change_filters( self::params( $request ) );
        if ( \is_wp_error( $filters ) ) {
            return $filters;
        }

        $total = $this->changes->count( $filters );
        $rows  = $this->changes->query( $filters, $paging['per_page'], $paging['offset'], Change_Log_Repository::list_columns() . ', token_id' );
        $rows  = is_array( $rows ) ? $rows : array();

        return $this->ok( array(
            'total'                    => (int) $total,
            'page'                     => $paging['page'],
            'perPage'                  => $paging['per_page'],
            'retentionDays'            => \Easy_MCP_AI\Plugin::change_log_retention_days(),
            'captureSettingsAvailable' => is_readable( EASY_MCP_AI_PLUGIN_DIR . 'includes/admin/class-history-settings-page.php' ),
            'objectTypes'              => $this->object_types(),
            'rows'                     => $this->change_rows( $rows ),
        ) );
    }

    



    public function cleanup_changes( $request ) {
        $retention = \Easy_MCP_AI\Plugin::change_log_retention_days();
        $more      = call_user_func( $this->cleanup, $this->changes->table(), $retention );
        return $this->ok( array( 'retentionDays' => $retention, 'more' => (bool) $more ) );
    }

    



    public function get_change( $request ) {
        $row = $this->find_change( $request );
        if ( \is_wp_error( $row ) ) {
            return $row;
        }
        return $this->ok( $this->change_detail( $row ) );
    }

    





    public static function change_filters( array $params ) {
        $filters = array();
        foreach ( array( 'object_type' => 'object_type', 'object_id' => 'object_id', 'tool' => 'tool_name' ) as $param => $key ) {
            $value = trim( (string) ( $params[ $param ] ?? '' ) );
            if ( '' !== $value ) {
                $filters[ $key ] = $value;
            }
        }
        $action = (string) ( $params['action'] ?? '' );
        if ( '' !== $action ) {
            if ( ! in_array( $action, self::CHANGE_ACTIONS, true ) ) {
                return self::invalid( 'action' );
            }
            $filters['action'] = $action;
        }
        $drafts = (string) ( $params['drafts'] ?? '' );
        if ( '' !== $drafts ) {
            if ( '1' !== $drafts ) {
                return self::invalid( 'drafts' );
            }
            $filters['drafts'] = true;
        }
        foreach ( array( 'user' => 'wp_user_id', 'audit_id' => 'audit_id' ) as $param => $key ) {
            $value = (string) ( $params[ $param ] ?? '' );
            if ( '' === $value ) {
                continue;
            }
            if ( ! ctype_digit( $value ) || (int) $value < 1 ) {
                return self::invalid( $param );
            }
            $filters[ $key ] = (int) $value;
        }
        foreach ( array( 'from' => 'since', 'to' => 'until' ) as $param => $key ) {
            $raw = (string) ( $params[ $param ] ?? '' );
            if ( '' === $raw ) {
                continue;
            }
            $coerced = self::coerce_datetime( $raw );
            if ( '' === $coerced ) {
                return self::invalid( $param );
            }
            $filters[ $key ] = $coerced;
        }
        return $filters;
    }

    





    public static function capture_kind( $mode ) {
        $mode = is_string( $mode ) ? trim( $mode ) : '';
        if ( 'db_row' === $mode || 'db_sql' === $mode ) {
            return 'raw';
        }
        if ( 'external' === $mode ) {
            return 'remote';
        }
        return 'standard';
    }

    






    private function change_rows( array $rows ) {
        if ( ! $rows ) {
            return array();
        }
        $audit_status = $this->audit_statuses( array_column( $rows, 'audit_id' ) );

        $post_ids = array();
        foreach ( $rows as $row ) {
            if ( 'post' === ( $row['object_type'] ?? '' ) && is_numeric( $row['object_id'] ?? null ) ) {
                $post_ids[] = (int) $row['object_id'];
            }
        }
        if ( $post_ids && function_exists( '_prime_post_caches' ) ) {
            \_prime_post_caches( $post_ids, false, false );
        }

        $out = array();
        foreach ( $rows as $row ) {
            $out[] = $this->change_row( $row, $audit_status );
        }
        return $out;
    }

    private function change_row( array $row, array $audit_status ) {
        $id       = (int) ( $row['id'] ?? 0 );
        $type     = (string) ( $row['object_type'] ?? '' );
        $oid      = (string) ( $row['object_id'] ?? '' );
        $audit_id = isset( $row['audit_id'] ) ? (int) $row['audit_id'] : 0;
        $tool     = (string) ( $row['tool_name'] ?? '' );

        $post_status = null;
        $title       = null;
        if ( 'post' === $type && is_numeric( $oid ) ) {
            $post = \get_post( (int) $oid );
            if ( $post && isset( $post->post_status ) ) {
                $post_status = (string) $post->post_status;
                $title       = (string) \get_the_title( (int) $oid );
            }
        }
        $status = Dashboard_Controller::derive_status( $row, $post_status, $audit_status[ $audit_id ] ?? null );
        $auth   = 'oauth' === ( $row['auth_source'] ?? '' ) ? 'oauth' : 'token';

        return array(
            'id'            => $id,
            'createdAt'     => Dashboard_Controller::iso8601( $row['created_at'] ?? null ),
            'toolName'      => $tool,
            'action'        => (string) ( $row['action'] ?? '' ),
            'objectType'    => $type,
            'objectSubtype' => ( isset( $row['object_subtype'] ) && '' !== (string) $row['object_subtype'] ) ? (string) $row['object_subtype'] : null,
            'objectId'      => $oid,
            'objectLabel'   => ( null !== $title && '' !== $title )
                ? $title
                : trim( Dashboard_Controller::object_type_label( $type ) . ' ' . ( is_numeric( $oid ) ? '#' . $oid : $oid ) ),
            'user'          => self::user_summary( (int) ( $row['wp_user_id'] ?? 0 ) ),
            'auth'          => $auth,
            'capture'       => self::capture_kind( $row['capture_mode'] ?? null ),
            'status'        => $status,
            'auditId'       => $audit_id > 0 ? $audit_id : null,
            'revisionId'    => ! empty( $row['revision_id'] ) ? (int) $row['revision_id'] : null,
            'truncated'     => ! empty( $row['truncated'] ),
        );
    }

    






    private function change_detail( array $row ) {
        $row     = $this->changes->find( (int) $row['id'] ) ?: $row;
        $summary = $this->change_row( $row, $this->audit_statuses( array( $row['audit_id'] ?? 0 ) ) );

        $revision_id = ! empty( $row['revision_id'] ) ? (int) $row['revision_id'] : 0;

        return array_merge( $summary, array(
            'fields'        => self::fields_of( $row ),
            'changedFields' => self::changed_fields( $row ),
            'revisionUrl'   => $revision_id > 0 ? \admin_url( 'revision.php?revision=' . $revision_id ) : null,
        ) );
    }

    








    public static function fields_of( array $row ) {
        $before = self::decode_side( $row['before_value'] ?? null );
        $after  = self::decode_side( $row['after_value'] ?? null );
        if ( null === $before && null === $after ) {
            return array();
        }
        $before = is_array( $before ) ? $before : ( null === $before ? array() : array( 'value' => $before ) );
        $after  = is_array( $after ) ? $after : ( null === $after ? array() : array( 'value' => $after ) );
        
        if ( self::is_list( $before ) || self::is_list( $after ) ) {
            $before = $before ? array( 'value' => $before ) : array();
            $after  = $after ? array( 'value' => $after ) : array();
        }
        $keys = array_values( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) );
        $out  = array();
        foreach ( $keys as $key ) {
            $b = array_key_exists( $key, $before ) ? $before[ $key ] : null;
            $a = array_key_exists( $key, $after ) ? $after[ $key ] : null;
            $out[] = array(
                'field'   => (string) $key,
                'before'  => $b,
                'after'   => $a,
                'changed' => $b !== $a,
            );
        }
        return $out;
    }

    private static function is_list( array $value ) {
        return $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    private static function decode_side( $json ) {
        if ( null === $json || '' === $json ) {
            return null;
        }
        if ( ! is_string( $json ) ) {
            return $json;
        }
        $decoded = json_decode( $json, true );
        return ( null === $decoded && 'null' !== trim( $json ) ) ? $json : $decoded;
    }

    private static function changed_fields( array $row ) {
        if ( empty( $row['changed_fields'] ) ) {
            return array();
        }
        $decoded = is_array( $row['changed_fields'] ) ? $row['changed_fields'] : json_decode( (string) $row['changed_fields'], true );
        return is_array( $decoded ) ? array_values( array_map( 'strval', $decoded ) ) : array();
    }

    

    





    public static function paging( $request ) {
        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $page     = ( null === $page || '' === $page ) ? 1 : $page;
        $per_page = ( null === $per_page || '' === $per_page ) ? self::PER_PAGE_DEFAULT : $per_page;
        if ( ! is_numeric( $page ) || (int) $page < 1 || (int) $page != $page ) {
            return self::invalid( 'page' );
        }
        if ( ! is_numeric( $per_page ) || (int) $per_page < 1 || (int) $per_page > self::PER_PAGE_MAX || (int) $per_page != $per_page ) {
            return self::invalid( 'per_page' );
        }
        return array(
            'page'     => (int) $page,
            'per_page' => (int) $per_page,
            'offset'   => ( (int) $page - 1 ) * (int) $per_page,
        );
    }

    





    public static function coerce_datetime( $raw ) {
        $raw = str_replace( 'T', ' ', trim( (string) $raw ) );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            $raw .= ' 00:00';
        }
        if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw ) ) {
            $raw .= ':00';
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw ) ) {
            return '';
        }
        
        $parsed = \DateTime::createFromFormat( 'Y-m-d H:i:s', $raw, new \DateTimeZone( 'UTC' ) );
        return ( $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $raw ) ? $raw : '';
    }

    





    public static function audit_retention_days() {
        $stored = \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_retention', 30 );
        if ( ! is_numeric( $stored ) ) {
            return 30;
        }
        return max( 1, (int) $stored );
    }

    private static function invalid( $field ) {
        return new \WP_Error(
            'easy_mcp_ai_invalid_filter',
            /* translators: %s: query parameter name */
            sprintf( \__( 'The value of "%s" is not valid.', 'easy-mcp-ai' ), $field ),
            array( 'status' => 400, 'field' => $field )
        );
    }

    private function find_change( $request ) {
        $id = $request->get_param( 'id' );
        if ( ! is_numeric( $id ) || (int) $id < 1 ) {
            return self::invalid( 'id' );
        }
        $row = $this->changes->find( (int) $id );
        if ( ! is_array( $row ) || empty( $row['id'] ) ) {
            return $this->fail( 'easy_mcp_ai_not_found', \__( 'That change-history entry does not exist.', 'easy-mcp-ai' ), 404 );
        }
        return $row;
    }

    private static function params( $request ) {
        $params = $request->get_query_params();
        if ( ! $params ) {
            $params = $request->get_params();
        }
        return is_array( $params ) ? $params : array();
    }

    private static function user_summary( $user_id, $login = null ) {
        $user = $user_id > 0 ? \get_userdata( $user_id ) : false;
        $name = ( $user && isset( $user->display_name ) ) ? (string) $user->display_name : '';
        if ( '' === $name && is_string( $login ) && '' !== $login ) {
            $name = $login;
        }
        return array( 'id' => (int) $user_id, 'displayName' => $name );
    }

    

    
    private function change_counts( array $audit_ids ) {
        $ids = array_values( array_unique( array_filter( array_map( 'intval', $audit_ids ) ) ) );
        if ( ! $ids ) {
            return array();
        }
        $table        = $this->changes->table();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- placeholders generated, plugin-owned table.
        $rows = $this->db->get_results( $this->db->prepare( "SELECT audit_id, COUNT(*) AS c FROM `{$table}` WHERE audit_id IN ({$placeholders}) GROUP BY audit_id", ...$ids ), ARRAY_A );
        $out  = array();
        foreach ( (array) $rows as $r ) {
            $out[ (int) $r['audit_id'] ] = (int) $r['c'];
        }
        return $out;
    }

    private function audit_statuses( array $ids ) {
        $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
        if ( ! $ids ) {
            return array();
        }
        $table        = $this->db->prefix . 'easy_mcp_ai_audit_log';
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- placeholders generated, plugin-owned table.
        $rows = $this->db->get_results( $this->db->prepare( "SELECT id, result_status FROM `{$table}` WHERE id IN ({$placeholders})", ...$ids ), ARRAY_A );
        $out  = array();
        foreach ( (array) $rows as $r ) {
            $out[ (int) $r['id'] ] = (string) $r['result_status'];
        }
        return $out;
    }

    private function object_types() {
        $cached = \get_transient( self::OBJECT_TYPES_TRANSIENT );
        if ( is_array( $cached ) ) {
            return array_values( array_map( 'strval', $cached ) );
        }
        $table = $this->changes->table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table, no user input; cached above.
        $types = $this->db->get_col( "SELECT DISTINCT object_type FROM `{$table}` ORDER BY object_type ASC" );
        $types = is_array( $types ) ? array_values( array_filter( array_map( 'strval', $types ), 'strlen' ) ) : array();
        \set_transient( self::OBJECT_TYPES_TRANSIENT, $types, 300 ); 
        return $types;
    }
}
