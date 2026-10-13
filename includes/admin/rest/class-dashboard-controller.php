<?php







namespace Easy_MCP_AI\Admin\Rest;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




class Dashboard_Controller extends Admin_Rest_Controller {

    const WINDOW_DAYS   = 7;
    
    const CALLS_WINDOW_DAYS = 1;
    const CHANGES_LIMIT = 25;

    
    const ATTENTION_SCAN_LIMIT = 200;

    
    const RECENT_LIMIT = 3;

    
    const LAST_CALL_OPTION = 'easy_mcp_ai_last_tool_call';

    
    private $db;

    
    private $repository;

    
    private $token_manager;

    
    private $tool_registry;

    





    public function __construct( $db, $repository, $token_manager, $tool_registry = null ) {
        $this->db            = $db;
        $this->repository    = $repository;
        $this->token_manager = $token_manager;
        $this->tool_registry = $tool_registry;
    }

    public function register_routes() {
        $this->register_route( '/dashboard', \WP_REST_Server::READABLE, array( $this, 'get_dashboard' ) );
    }

    



    public function get_dashboard( $request ) {
        $now    = \current_time( 'mysql', true );
        $bounds = self::window_bounds( $now );
        $calls  = self::window_bounds( $now, self::CALLS_WINDOW_DAYS );

        $audit_enabled  = (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_enabled', true );
        $change_enabled = (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_enabled', true );

        $audit_rows  = $audit_enabled ? $this->grouped_audit_rows( $calls ) : array();
        $change_rows = $change_enabled ? $this->grouped_change_rows( $bounds ) : array();
        $tools       = $this->count_enabled_tools();
        $stats       = self::bucket_stats( $audit_rows, $change_rows, $audit_enabled, $change_enabled );
        $stats['registeredTools'] = $tools;
        $recent      = $audit_enabled ? self::build_recent( $this->attach_labels( $this->recent_audit_rows() ) ) : array();
        $last_call   = self::read_last_call( \get_option( self::LAST_CALL_OPTION, null ) );
        if ( null !== $last_call ) {
            $last_call = self::build_last_call( $this->attach_labels( array( $last_call ) )[0] );
        }

        $clients = self::merge_clients( $this->legacy_client_rows( $bounds ), $this->oauth_client_rows( $bounds ) );

        $changes   = array();
        $attention = array( 'count' => 0, 'since' => null );
        if ( $change_enabled ) {
            $changes   = $this->recent_changes( $bounds );
            $attention = self::build_attention( $this->attention_rows( $bounds ), array( $this, 'post_status_of' ) );
        }

        $payload = array(
            'window'    => array(
                'days'  => self::WINDOW_DAYS,
                'from'  => self::iso8601( $bounds['from'] ),
                'to'    => self::iso8601( $bounds['to'] ),
                'calls' => array(
                    'days' => self::CALLS_WINDOW_DAYS,
                    'from' => self::iso8601( $calls['from'] ),
                    'to'   => self::iso8601( $calls['to'] ),
                ),
            ),
            'stats'         => $stats,
            'recent'        => $recent,
            'lastCall'      => $last_call,
            'clients'       => $clients,
            'changes'       => $changes,
            'changesByType' => self::changes_by_type( $change_rows ),
            'attention'     => $attention,
            'access'        => array(
                'draftMode'    => (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_force_draft_on_create', false ),
                'tokens'       => (int) $this->token_manager->count_tokens(),
                'grants'       => $this->count_grants(),
                'toolsEnabled' => $tools,
                
                'preset'       => null,
            ),
            'mcpUrl'        => \rest_url( 'easy-mcp-ai/v1/mcp' ),
            'connectorName' => self::connector_name( \get_bloginfo( 'name' ) ),
        );

        return $this->ok( $payload );
    }

    

    








    public static function window_bounds( $now, $days = self::WINDOW_DAYS ) {
        $ts = strtotime( (string) $now . ' UTC' );
        if ( false === $ts ) {
            $ts = time();
        }
        $day = max( 1, (int) $days ) * DAY_IN_SECONDS;
        return array(
            'from'      => gmdate( 'Y-m-d H:i:s', $ts - $day ),
            'to'        => gmdate( 'Y-m-d H:i:s', $ts ),
            'prev_from' => gmdate( 'Y-m-d H:i:s', $ts - 2 * $day ),
            'prev_to'   => gmdate( 'Y-m-d H:i:s', $ts - $day ),
        );
    }

    





    public static function iso8601( $mysql ) {
        if ( null === $mysql || '' === $mysql ) {
            return null;
        }
        $ts = strtotime( (string) $mysql . ' UTC' );
        return false === $ts ? null : gmdate( 'Y-m-d\TH:i:s\Z', $ts );
    }

    





    public static function is_tool_call( $tool_name ) {
        $tool_name = (string) $tool_name;
        return '' !== $tool_name && '_' !== $tool_name[0];
    }

    








    public static function bucket_stats( array $audit_rows, array $change_rows, $audit_enabled, $change_enabled ) {
        $empty = array( 'current' => 0, 'previous' => 0 );
        $calls    = $empty;
        $failures = $empty;
        $tools    = array( 'current' => array(), 'previous' => array() );

        foreach ( $audit_rows as $row ) {
            $bucket = isset( $row['bucket'] ) ? (string) $row['bucket'] : '';
            if ( ! isset( $calls[ $bucket ] ) ) {
                continue;
            }
            $tool = isset( $row['tool_name'] ) ? (string) $row['tool_name'] : '';
            if ( ! self::is_tool_call( $tool ) ) {
                continue;
            }
            $n = isset( $row['n'] ) ? (int) $row['n'] : 0;
            $calls[ $bucket ]          += $n;
            $tools[ $bucket ][ $tool ] = true;
            $status = isset( $row['result_status'] ) ? (string) $row['result_status'] : 'success';
            if ( 'success' !== $status ) {
                $failures[ $bucket ] += $n;
            }
        }

        $changes = $empty;
        foreach ( $change_rows as $row ) {
            $bucket = isset( $row['bucket'] ) ? (string) $row['bucket'] : '';
            if ( isset( $changes[ $bucket ] ) ) {
                $changes[ $bucket ] += isset( $row['n'] ) ? (int) $row['n'] : 0;
            }
        }

        return array(
            'calls'       => $audit_enabled ? $calls : null,
            'toolsUsed'   => $audit_enabled
                ? array( 'current' => count( $tools['current'] ), 'previous' => count( $tools['previous'] ) )
                : null,
            'failures'    => $audit_enabled ? $failures : null,
            
            'successRate' => $audit_enabled
                ? array(
                    'current'  => self::success_rate( $calls['current'], $failures['current'] ),
                    'previous' => self::success_rate( $calls['previous'], $failures['previous'] ),
                )
                : null,
            'changes'     => $change_enabled ? $changes : null,
        );
    }

    




    public static function success_rate( $calls, $failures ) {
        $calls = (int) $calls;
        if ( $calls <= 0 ) {
            return null;
        }
        return (int) round( 100 * max( 0, $calls - (int) $failures ) / $calls );
    }

    





    public static function changes_by_type( array $change_rows ) {
        $out = array();
        foreach ( $change_rows as $row ) {
            if ( 'current' !== ( $row['bucket'] ?? '' ) ) {
                continue;
            }
            $type = (string) ( $row['object_type'] ?? '' );
            if ( '' === $type ) {
                continue;
            }
            $out[ $type ] = ( $out[ $type ] ?? 0 ) + (int) ( $row['n'] ?? 0 );
        }
        arsort( $out, SORT_NUMERIC );
        return $out;
    }

    






    public static function build_recent( array $rows ): array {
        $out = array();
        foreach ( $rows as $row ) {
            if ( ! self::is_tool_call( $row['tool_name'] ?? '' ) ) {
                continue;
            }
            $out[] = array(
                'id'        => (int) ( $row['id'] ?? 0 ),
                'toolName'  => (string) $row['tool_name'],
                'status'    => (string) ( $row['result_status'] ?? 'success' ),
                'createdAt' => self::iso8601( $row['created_at'] ?? null ),
                'client'    => self::caller( $row ),
            );
            if ( count( $out ) >= self::RECENT_LIMIT ) {
                break;
            }
        }
        return $out;
    }

    






    public static function read_last_call( $stored ): ?array {
        if ( ! is_array( $stored ) || ! isset( $stored['id'], $stored['at'], $stored['auth_source'] ) ) {
            return null;
        }
        if ( ! is_string( $stored['id'] ) || '' === $stored['id'] || ! is_string( $stored['at'] ) || null === self::iso8601( $stored['at'] ) ) {
            return null;
        }
        if ( ! in_array( $stored['auth_source'], array( 'legacy', 'oauth' ), true ) ) {
            return null;
        }
        
        $token_id = (int) ( $stored['token_id'] ?? 0 );
        if ( $token_id < 1 ) {
            return null;
        }
        $client_id = $stored['oauth_client_id'] ?? null;
        return array(
            'id'              => $stored['id'],
            'created_at'      => $stored['at'],
            'auth_source'     => $stored['auth_source'],
            'token_id'        => $token_id,
            'oauth_client_id' => is_string( $client_id ) && '' !== $client_id ? $client_id : null,
        );
    }

    



    public static function build_last_call( array $row ): ?array {
        $client = self::caller( $row );
        if ( null === $client ) {
            return null;
        }
        return array(
            'id'     => (string) $row['id'],
            'at'     => (string) self::iso8601( $row['created_at'] ),
            'client' => $client,
        );
    }

    






    public static function caller( array $row ): ?array {
        
        $source = $row['auth_source'] ?? null;
        if ( ! in_array( $source, array( 'legacy', 'oauth' ), true ) || (int) ( $row['token_id'] ?? 0 ) < 1 ) {
            return null;
        }
        return array(
            'kind' => 'oauth' === $source ? 'oauth' : 'token',
            'name' => (string) \Easy_MCP_AI\Audit_Log_Repository::credential_label( $row )['label'],
        );
    }

    






    public static function connector_name( $site_title ) {
        $title = trim( (string) $site_title );
        return '' !== $title ? $title . ' WordPress' : 'WordPress';
    }

    







    public static function merge_clients( array $legacy_rows, array $oauth_rows ) {
        $clients = array();
        foreach ( $legacy_rows as $row ) {
            $clients[] = array(
                'id'         => 'token:' . (int) $row['id'],
                'kind'       => 'token',
                'name'       => '' !== (string) ( $row['name'] ?? '' ) ? (string) $row['name'] : '#' . (int) $row['id'],
                'user'       => self::user_summary( (int) ( $row['wp_user_id'] ?? 0 ) ),
                'lastCallAt' => self::iso8601( $row['last_used_at'] ?? null ),
            );
        }

        $by_client = array();
        foreach ( $oauth_rows as $row ) {
            $client_id = (string) ( $row['client_id'] ?? '' );
            $last      = (string) ( $row['last_used_at'] ?? '' );
            if ( isset( $by_client[ $client_id ] ) && strcmp( $last, $by_client[ $client_id ]['last_used_at'] ) <= 0 ) {
                continue;
            }
            $by_client[ $client_id ] = array(
                'client_id'    => $client_id,
                'client_name'  => (string) ( $row['client_name'] ?? '' ),
                'wp_user_id'   => (int) ( $row['wp_user_id'] ?? 0 ),
                'last_used_at' => $last,
            );
        }
        foreach ( $by_client as $row ) {
            $clients[] = array(
                'id'         => 'oauth:' . $row['client_id'],
                'kind'       => 'oauth',
                'name'       => '' !== $row['client_name'] ? $row['client_name'] : '#' . $row['client_id'],
                'user'       => self::user_summary( $row['wp_user_id'] ),
                'lastCallAt' => self::iso8601( $row['last_used_at'] ),
            );
        }

        usort( $clients, function ( $a, $b ) {
            return strcmp( (string) $b['lastCallAt'], (string) $a['lastCallAt'] );
        } );
        return $clients;
    }

    





    public static function derive_status( array $row, $post_status, $audit_status ) {
        if ( 'refused' === $audit_status ) {
            return 'blocked';
        }
        $action = (string) ( $row['action'] ?? '' );
        if ( 'post' === ( $row['object_type'] ?? '' ) && in_array( $action, array( 'create', 'update' ), true ) ) {
            if ( 'draft' === $post_status ) {
                return 'draft';
            }
            if ( 'publish' === $post_status ) {
                return 'published';
            }
        }
        return 'done';
    }

    







    public static function describe_change( array $row, $title = null ) {
        $type  = (string) ( $row['object_type'] ?? '' );
        $id    = (string) ( $row['object_id'] ?? '' );
        $label = ( null !== $title && '' !== $title )
            /* translators: %s: post title */
            ? sprintf( \__( '“%s”', 'easy-mcp-ai' ), $title )
            : ( is_numeric( $id ) ? '#' . $id : $id );
        $object = trim( self::object_type_label( $type ) . ' ' . $label );

        switch ( (string) ( $row['action'] ?? '' ) ) {
            case 'create':
                /* translators: %s: object, e.g. post “Spring launch” */
                return sprintf( \__( 'Created %s', 'easy-mcp-ai' ), $object );
            case 'delete':
                /* translators: %s: object, e.g. post “Spring launch” */
                return sprintf( \__( 'Deleted %s', 'easy-mcp-ai' ), $object );
            case 'update':
                /* translators: %s: object, e.g. post “Spring launch” */
                return sprintf( \__( 'Updated %s', 'easy-mcp-ai' ), $object );
            default:
                /* translators: %s: object, e.g. post “Spring launch” */
                return sprintf( \__( 'Changed %s', 'easy-mcp-ai' ), $object );
        }
    }

    



    public static function object_type_label( $type ) {
        switch ( $type ) {
            case 'post':
                return \__( 'post', 'easy-mcp-ai' );
            case 'term':
                return \__( 'term', 'easy-mcp-ai' );
            case 'user':
                return \__( 'user', 'easy-mcp-ai' );
            case 'comment':
                return \__( 'comment', 'easy-mcp-ai' );
            case 'option':
                return \__( 'option', 'easy-mcp-ai' );
            case 'site_option':
                return \__( 'network option', 'easy-mcp-ai' );
            case 'meta':
                return \__( 'post meta', 'easy-mcp-ai' );
            case 'term_meta':
                return \__( 'term meta', 'easy-mcp-ai' );
            case 'user_meta':
                return \__( 'user meta', 'easy-mcp-ai' );
            case 'comment_meta':
                return \__( 'comment meta', 'easy-mcp-ai' );
            case 'wc_product':
                return \__( 'product', 'easy-mcp-ai' );
            case 'wc_order':
                return \__( 'order', 'easy-mcp-ai' );
            case 'bp_activity':
                return \__( 'activity item', 'easy-mcp-ai' );
            case 'external':
                return \__( 'external service', 'easy-mcp-ai' );
            default:
                return str_replace( '_', ' ', (string) $type );
        }
    }

    






    public static function build_attention( array $rows, callable $status_of ) {
        $count = 0;
        $since = null;
        foreach ( $rows as $row ) {
            if ( 'draft' !== call_user_func( $status_of, (string) ( $row['object_id'] ?? '' ) ) ) {
                continue;
            }
            $count++;
            $at = (string) ( $row['created_at'] ?? '' );
            if ( '' !== $at && ( null === $since || strcmp( $at, $since ) < 0 ) ) {
                $since = $at;
            }
        }
        return array( 'count' => $count, 'since' => self::iso8601( $since ) );
    }

    








    public static function count_active_tools( array $tools_by_category, array $disabled, array $patterns ) {
        $total = 0;
        foreach ( $tools_by_category as $tools ) {
            foreach ( (array) $tools as $tool ) {
                $name = isset( $tool['name'] ) ? (string) $tool['name'] : '';
                if ( '' === $name || in_array( $name, $disabled, true ) ) {
                    continue;
                }
                if ( ! \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns ) ) {
                    continue;
                }
                $total++;
            }
        }
        return $total;
    }

    

    



    public function post_status_of( $object_id ) {
        if ( ! is_numeric( $object_id ) ) {
            return null;
        }
        $post = \get_post( (int) $object_id );
        return ( $post && isset( $post->post_status ) ) ? (string) $post->post_status : null;
    }

    private static function user_summary( $user_id ) {
        $user = $user_id > 0 ? \get_userdata( $user_id ) : false;
        return array(
            'id'          => (int) $user_id,
            'displayName' => ( $user && isset( $user->display_name ) ) ? (string) $user->display_name : '',
        );
    }

    private function count_enabled_tools() {
        if ( ! $this->tool_registry || ! method_exists( $this->tool_registry, 'get_tools_by_category' ) ) {
            return 0;
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\Tools\\Tool_State' ) ) {
            require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/tools/class-tool-state.php';
        }
        return self::count_active_tools(
            (array) $this->tool_registry->get_tools_by_category(),
            \Easy_MCP_AI\Tools\Tool_State::off_names( $this->tool_registry ),
            (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_allowed_tool_patterns', array() )
        );
    }

    

    



    private static function bucket_sql() {
        return "CASE WHEN created_at >= %s THEN 'current' ELSE 'previous' END";
    }

    private function grouped_audit_rows( array $b ) {
        $table  = $this->db->prefix . 'easy_mcp_ai_audit_log';
        $bucket = self::bucket_sql();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; the bucket expression is a constant.
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT {$bucket} AS bucket, tool_name, result_status, COUNT(*) AS n FROM `{$table}` WHERE created_at >= %s AND created_at <= %s GROUP BY bucket, tool_name, result_status",
                $b['from'],
                $b['prev_from'],
                $b['to']
            ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }

    private function grouped_change_rows( array $b ) {
        $table  = $this->repository->table();
        $bucket = self::bucket_sql();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; the bucket expression is a constant.
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT {$bucket} AS bucket, object_type, COUNT(*) AS n FROM `{$table}` WHERE created_at >= %s AND created_at <= %s GROUP BY bucket, object_type",
                $b['from'],
                $b['prev_from'],
                $b['to']
            ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }

    private function recent_audit_rows() {
        $table = $this->db->prefix . 'easy_mcp_ai_audit_log';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT id, tool_name, result_status, created_at, auth_source, token_id, oauth_client_id FROM `{$table}` WHERE tool_name NOT LIKE %s ORDER BY created_at DESC, id DESC LIMIT %d",
                '\\_%',
                self::RECENT_LIMIT * 4
            ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }

    



    private function attach_labels( array $rows ): array {
        return ( new \Easy_MCP_AI\Audit_Log_Repository() )->attach_labels( $rows );
    }

    private function legacy_client_rows( array $b ) {
        $table = $this->db->prefix . 'easy_mcp_ai_tokens';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT id, name, wp_user_id, last_used_at FROM `{$table}` WHERE is_active = 1 AND last_used_at >= %s AND last_used_at <= %s",
                $b['from'],
                $b['to']
            ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }

    private function oauth_client_rows( array $b ) {
        $tokens  = $this->db->prefix . 'easy_mcp_ai_oauth_access_tokens';
        $clients = $this->db->prefix . 'easy_mcp_ai_oauth_clients';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned tables.
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT t.client_id, t.wp_user_id, MAX(t.last_used_at) AS last_used_at, cl.client_name FROM `{$tokens}` t LEFT JOIN `{$clients}` cl ON cl.client_id = t.client_id WHERE t.is_active = 1 AND t.last_used_at >= %s AND t.last_used_at <= %s GROUP BY t.client_id, t.wp_user_id, cl.client_name",
                $b['from'],
                $b['to']
            ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }

    private function attention_rows( array $b ) {
        $table = $this->repository->table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT object_id, created_at FROM `{$table}` WHERE action = 'create' AND object_type = 'post' AND created_at >= %s AND created_at <= %s ORDER BY created_at ASC, id ASC LIMIT %d",
                $b['from'],
                $b['to'],
                self::ATTENTION_SCAN_LIMIT
            ),
            ARRAY_A
        );
        $rows = is_array( $rows ) ? $rows : array();
        if ( $rows && function_exists( '_prime_post_caches' ) ) {
            \_prime_post_caches( array_map( 'intval', array_column( $rows, 'object_id' ) ), false, false );
        }
        return $rows;
    }

    private function count_grants() {
        $consents = $this->db->prefix . 'easy_mcp_ai_oauth_consents';
        $clients  = $this->db->prefix . 'easy_mcp_ai_oauth_clients';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned tables, no user input.
        return (int) $this->db->get_var(
            "SELECT COUNT(*) FROM `{$consents}` con INNER JOIN `{$clients}` cl ON cl.client_id = con.client_id AND cl.is_active = 1"
        );
    }

    





    private function recent_changes( array $b ) {
        
        
        $rows = $this->repository->query(
            array( 'since' => $b['from'], 'until' => $b['to'] ),
            self::CHANGES_LIMIT,
            0,
            \Easy_MCP_AI\History\Change_Log_Repository::list_columns() . ', token_id'
        );
        $rows = is_array( $rows ) ? $rows : array();
        if ( ! $rows ) {
            return array();
        }

        $audit_status = $this->audit_statuses( array_column( $rows, 'audit_id' ) );
        $token_names  = $this->token_names( array_column( $rows, 'token_id' ) );
        $client_names = $this->client_names( array_column( $rows, 'oauth_client_id' ) );

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
            $type   = (string) ( $row['object_type'] ?? '' );
            $id     = (string) ( $row['object_id'] ?? '' );
            $status = null;
            $title  = null;
            if ( 'post' === $type ) {
                $status = $this->post_status_of( $id );
                if ( null !== $status ) {
                    $title = (string) \get_the_title( (int) $id );
                }
            }
            $audit_id = isset( $row['audit_id'] ) ? (int) $row['audit_id'] : 0;

            $out[] = array(
                'id'          => (int) ( $row['id'] ?? 0 ),
                'objectType'  => $type,
                'objectId'    => $id,
                'objectLabel' => ( null !== $title && '' !== $title ) ? $title : trim( self::object_type_label( $type ) . ' ' . ( is_numeric( $id ) ? '#' . $id : $id ) ),
                'objectUrl'   => null,
                'description' => self::describe_change( $row, $title ),
                'action'      => (string) ( $row['action'] ?? '' ),
                'status'      => self::derive_status( $row, $status, isset( $audit_status[ $audit_id ] ) ? $audit_status[ $audit_id ] : null ),
                'client'      => self::client_label( $row, $token_names, $client_names ),
                'createdAt'   => self::iso8601( $row['created_at'] ?? null ),
            );
        }
        return $out;
    }

    







    public static function client_label( array $row, array $token_names, array $client_names ) {
        if ( 'oauth' === ( $row['auth_source'] ?? '' ) || ! empty( $row['oauth_client_id'] ) ) {
            $client_id = (string) ( $row['oauth_client_id'] ?? '' );
            if ( '' !== $client_id && ! empty( $client_names[ $client_id ] ) ) {
                return (string) $client_names[ $client_id ];
            }
            return '#' . $client_id;
        }
        $token_id = (int) ( $row['token_id'] ?? 0 );
        if ( ! empty( $token_names[ $token_id ] ) ) {
            return (string) $token_names[ $token_id ];
        }
        return '#' . $token_id;
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

    private function token_names( array $ids ) {
        $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
        if ( ! $ids ) {
            return array();
        }
        $table        = $this->db->prefix . 'easy_mcp_ai_tokens';
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- placeholders generated, plugin-owned table.
        $rows = $this->db->get_results( $this->db->prepare( "SELECT id, name FROM `{$table}` WHERE id IN ({$placeholders})", ...$ids ), ARRAY_A );
        $out  = array();
        foreach ( (array) $rows as $r ) {
            $out[ (int) $r['id'] ] = (string) $r['name'];
        }
        return $out;
    }

    private function client_names( array $client_ids ) {
        $client_ids = array_values( array_unique( array_filter( array_map( 'strval', $client_ids ) ) ) );
        if ( ! $client_ids ) {
            return array();
        }
        $table        = $this->db->prefix . 'easy_mcp_ai_oauth_clients';
        $placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- placeholders generated, plugin-owned table.
        $rows = $this->db->get_results( $this->db->prepare( "SELECT client_id, client_name FROM `{$table}` WHERE client_id IN ({$placeholders})", ...$client_ids ), ARRAY_A );
        $out  = array();
        foreach ( (array) $rows as $r ) {
            $out[ (string) $r['client_id'] ] = (string) $r['client_name'];
        }
        return $out;
    }
}
