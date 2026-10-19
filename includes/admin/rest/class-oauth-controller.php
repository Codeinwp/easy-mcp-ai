<?php
































namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Admin\Access_Presets;
use Easy_MCP_AI\Admin\OAuth_Admin;
use Easy_MCP_AI\Config;
use Easy_MCP_AI\OAuth\Client_Registry;
use Easy_MCP_AI\OAuth\OAuth_Site_Move;
use Easy_MCP_AI\OAuth\Scope_Map;
use Easy_MCP_AI\Privileged\Privileged_Groups;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OAuth_Controller extends Admin_Rest_Controller {

    
    const ROWS_LIMIT = 200;

    
    const TTL_MIN = 60;

    const FULL_SCOPE = 'mcp';

    
    const SETTINGS_FIELDS = array(
        'accessTokenTtl'  => 'oauth_access_token_ttl',
        'refreshTokenTtl' => 'oauth_refresh_token_ttl',
        'dcrEnabled'      => 'oauth_dcr_enabled',
    );

    
    private $wpdb;

    
    private $presets;

    
    private $site_move_rows = false;

    



    public function __construct( $wpdb, Access_Presets $presets ) {
        $this->wpdb    = $wpdb;
        $this->presets = $presets;
    }

    public function register_routes() {
        $this->register_route( '/oauth/settings', \WP_REST_Server::READABLE, array( $this, 'get_settings' ) );
        $this->register_route( '/oauth/settings', self::UPDATABLE, array( $this, 'patch_settings' ) );
        $this->register_route( '/oauth/clients', \WP_REST_Server::READABLE, array( $this, 'get_clients' ) );
        $this->register_delete_route(
            '/oauth/clients/(?P<id>[A-Za-z0-9_\-]+)',
            array( $this, 'delete_client' ),
            array( 'id' => array( 'type' => 'string', 'required' => true ) )
        );
        $this->register_route(
            '/oauth/clients/(?P<id>[A-Za-z0-9_\-]+)/rotate-secret',
            'POST',
            array( $this, 'rotate_client_secret' ),
            array( 'id' => array( 'type' => 'string', 'required' => true ) )
        );
        $this->register_route( '/oauth/grants', \WP_REST_Server::READABLE, array( $this, 'get_grants' ) );
        $this->register_delete_route( '/oauth/grants/(?P<id>\d+)', array( $this, 'delete_grant' ), self::id_args() );
        $this->register_route( '/oauth/grants/(?P<id>\d+)/scope', self::UPDATABLE, array( $this, 'patch_grant_scope' ), self::id_args() );
        $this->register_route( '/oauth/device', \WP_REST_Server::READABLE, array( $this, 'get_device' ) );
        $this->register_route( '/oauth/site-move/revoke', 'POST', array( $this, 'revoke_site_move' ) );
        $this->register_route( '/oauth/site-move/dismiss', 'POST', array( $this, 'dismiss_site_move' ) );
    }

    

    



    public function get_settings( $request ) {
        return $this->ok( self::settings_payload() );
    }

    






    public function patch_settings( $request ) {
        $current = self::settings_payload();
        $next    = array(
            'accessTokenTtl'  => $current['accessTokenTtl'],
            'refreshTokenTtl' => $current['refreshTokenTtl'],
            'dcrEnabled'      => $current['dcrEnabled'],
        );
        $touched = array();

        foreach ( array( 'accessTokenTtl', 'refreshTokenTtl' ) as $field ) {
            $raw = $request->get_param( $field );
            if ( null === $raw ) {
                continue;
            }
            $seconds = self::ttl_seconds( $raw );
            if ( null === $seconds ) {
                return $this->fail(
                    'easy_mcp_ai_invalid_param',
                    sprintf(
                        /* translators: %d: minimum seconds */
                        \__( 'Enter a whole number of seconds, at least %d.', 'easy-mcp-ai' ),
                        self::TTL_MIN
                    ),
                    400,
                    array( 'field' => $field )
                );
            }
            $next[ $field ] = $seconds;
            $touched[]      = $field;
        }

        $dcr = $request->get_param( 'dcrEnabled' );
        if ( null !== $dcr ) {
            if ( ! is_bool( $dcr ) && ! in_array( $dcr, array( 0, 1, '0', '1', 'true', 'false' ), true ) ) {
                return $this->fail( 'easy_mcp_ai_invalid_param', \__( 'Expected true or false.', 'easy-mcp-ai' ), 400, array( 'field' => 'dcrEnabled' ) );
            }
            $next['dcrEnabled'] = \rest_sanitize_boolean( $dcr );
            $touched[]          = 'dcrEnabled';
        }

        foreach ( $touched as $field ) {
            if ( $current['locked'][ $field ] ) {
                return $this->fail(
                    'easy_mcp_ai_setting_locked',
                    \__( 'This setting is set in wp-config.php or the environment and cannot be changed here.', 'easy-mcp-ai' ),
                    409,
                    array( 'field' => $field )
                );
            }
        }

        if ( ! empty( $touched ) ) {
            OAuth_Admin::save_settings( $next['accessTokenTtl'], $next['refreshTokenTtl'], $next['dcrEnabled'] );
        }
        return $this->ok( self::settings_payload() );
    }

    






    public static function settings_payload() {
        return array(
            'accessTokenTtl'  => (int) Config::validate( 'oauth_access_token_ttl', Config::get( 'easy_mcp_ai_oauth_access_token_ttl', 3600 ) ),
            'refreshTokenTtl' => (int) Config::validate( 'oauth_refresh_token_ttl', Config::get( 'easy_mcp_ai_oauth_refresh_token_ttl', 2592000 ) ),
            'dcrEnabled'      => (bool) Config::get( 'easy_mcp_ai_oauth_dcr_enabled', true ),
            'defaults'        => array(
                'accessTokenTtl'  => 3600,
                'refreshTokenTtl' => 2592000,
            ),
            'minTtl'          => self::TTL_MIN,
            'locked'          => array_map( array( Config::class, 'is_locked' ), self::SETTINGS_FIELDS ),
            'lockedBy'        => self::locked_by(),
            'lockSources'     => self::lock_sources(),
        );
    }

    
    private static function locked_by() {
        $names = array();
        foreach ( self::SETTINGS_FIELDS as $field => $suffix ) {
            if ( Config::is_locked( $suffix ) ) {
                $names[ $field ] = Config::constant_name( $suffix );
            }
        }
        return $names;
    }

    
    private static function lock_sources(): array {
        $sources = array();
        foreach ( self::SETTINGS_FIELDS as $suffix ) {
            if ( Config::is_locked( $suffix ) ) {
                $sources[ Config::constant_name( $suffix ) ] = Config::source( $suffix );
            }
        }
        return $sources;
    }

    





    public static function ttl_seconds( $raw ) {
        if ( is_bool( $raw ) || is_array( $raw ) ) {
            return null;
        }
        if ( is_string( $raw ) ) {
            $raw = trim( $raw );
            if ( '' === $raw || ! preg_match( '/^\d+$/D', $raw ) ) {
                return null;
            }
            $raw = (int) $raw;
        }
        if ( ! is_int( $raw ) && ! ( is_float( $raw ) && floor( $raw ) === $raw ) ) {
            return null;
        }
        $seconds = (int) $raw;
        return $seconds < self::TTL_MIN ? null : $seconds;
    }

    

    



    public function get_clients( $request ) {
        $clients = $this->wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $tokens  = $this->wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        $now     = \current_time( 'mysql', true );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned tables; names prefixed by $wpdb->prefix.
        
        
        $rows = (array) $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT c.id, c.client_id, c.client_name, c.redirect_uris, c.grant_types, c.token_endpoint_auth_method, c.client_secret_hash, c.created_at,
                        (SELECT COUNT(*) FROM {$tokens} t WHERE t.client_id = c.client_id AND t.is_active = 1 AND t.expires_at > %s) AS active_tokens
                 FROM {$clients} c
                 WHERE c.is_active = 1
                 ORDER BY c.created_at DESC, c.id DESC
                 LIMIT %d",
                $now,
                self::ROWS_LIMIT + 1
            )
        );
        $total = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$clients} WHERE is_active = 1" );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $truncated = count( $rows ) > self::ROWS_LIMIT;
        $rows      = array_slice( $rows, 0, self::ROWS_LIMIT );

        $flagged = OAuth_Site_Move::flagged_clients( $this->site_move_rows() );
        $out     = array();
        foreach ( $rows as $row ) {
            $row   = (array) $row;
            $out[] = self::shape_client( $row, isset( $flagged[ (string) $row['client_id'] ] ) );
        }
        return $this->ok( array(
            'clients'       => $out,
            'total'         => $total,
            'truncated'     => $truncated,
            'limit'         => self::ROWS_LIMIT,
            'retentionDays' => self::retention_days(),
            'siteMove'      => $this->site_move_payload(),
        ) );
    }

    



    public function delete_client( $request ) {
        $client_id = \sanitize_text_field( (string) $request->get_param( 'id' ) );
        if ( '' === $client_id || ! $this->client_exists( $client_id ) ) {
            return $this->fail( 'easy_mcp_ai_client_not_found', \__( 'Client not found.', 'easy-mcp-ai' ), 404 );
        }
        OAuth_Admin::revoke_client( $client_id );
        return $this->ok( array( 'deleted' => true, 'clientId' => $client_id ) );
    }

    





    public function rotate_client_secret( $request ) {
        $client_id = \sanitize_text_field( (string) $request->get_param( 'id' ) );
        if ( '' === $client_id || ! $this->client_exists( $client_id ) ) {
            return $this->fail( 'easy_mcp_ai_client_not_found', \__( 'Client not found.', 'easy-mcp-ai' ), 404 );
        }
        $secret = ( new Client_Registry() )->rotate_secret( $client_id );
        if ( null === $secret ) {
            return $this->fail(
                'easy_mcp_ai_client_not_confidential',
                \__( 'The client secret was not rotated: the client is not an active confidential client.', 'easy-mcp-ai' ),
                409
            );
        }
        return $this->ok( array( 'clientId' => $client_id, 'secret' => $secret ) );
    }

    








    public static function shape_client( array $row, bool $issued_elsewhere = false ): array {
        $method = Client_Registry::effective_auth_method( $row );
        $uris   = json_decode( (string) ( $row['redirect_uris'] ?? '' ), true );
        if ( ! is_array( $uris ) ) {
            $uris = '' === trim( (string) ( $row['redirect_uris'] ?? '' ) ) ? array() : array( (string) $row['redirect_uris'] );
        }
        
        
        
        $grants = json_decode( (string) ( $row['grant_types'] ?? '' ), true );
        if ( ! is_array( $grants ) ) {
            $grants = explode( ' ', (string) ( $row['grant_types'] ?? '' ) );
        }
        $grants = array_values( array_filter( array_map( 'trim', array_filter( $grants, 'is_string' ) ) ) );

        return array(
            'id'           => (int) ( $row['id'] ?? 0 ),
            'clientId'     => (string) ( $row['client_id'] ?? '' ),
            'name'         => (string) ( $row['client_name'] ?? '' ),
            'authMethod'   => '' === $method ? 'unusable' : $method,
            'confidential' => Client_Registry::is_confidential_method( $method ),
            'grantTypes'   => $grants,
            'redirectUris' => array_values( array_map( 'strval', $uris ) ),
            'createdAt'    => Dashboard_Controller::iso8601( $row['created_at'] ?? null ),
            'activeTokens' => (int) ( $row['active_tokens'] ?? 0 ),
            'status'       => 'active',
            'issuedElsewhere' => (bool) $issued_elsewhere,
        );
    }

    

    



    public function get_grants( $request ) {
        $rows  = $this->grant_rows();
        $total = count( $rows );
        $rows  = array_slice( $rows, 0, self::ROWS_LIMIT );

        $names   = $this->user_names( array_column( $rows, 'wp_user_id' ) );
        $flagged = OAuth_Site_Move::flagged_grants( $this->site_move_rows() );
        $out     = array();
        foreach ( $rows as $row ) {
            $key   = $row['client_id'] . ':' . $row['wp_user_id'];
            $out[] = $this->shape_grant( $row, $names, isset( $flagged[ $key ] ) );
        }
        return $this->ok( array(
            'grants'     => $out,
            'total'      => $total,
            'truncated'  => $total > self::ROWS_LIMIT,
            'limit'      => self::ROWS_LIMIT,
            'categories' => self::categories(),
            'siteMove'   => $this->site_move_payload(),
        ) );
    }

    



    public function delete_grant( $request ) {
        $row = $this->find_grant( $request );
        if ( \is_wp_error( $row ) ) {
            return $row;
        }
        OAuth_Admin::revoke_grant( (int) $row['consent_id'] );
        return $this->ok( array( 'deleted' => true, 'id' => (int) $row['consent_id'] ) );
    }

    







    public function patch_grant_scope( $request ) {
        $row = $this->find_grant( $request );
        if ( \is_wp_error( $row ) ) {
            return $row;
        }

        $raw = $request->get_param( 'scopes' );
        if ( ! is_array( $raw ) ) {
            return $this->fail( 'easy_mcp_ai_invalid_param', \__( 'scopes must be a list of scope names.', 'easy-mcp-ai' ), 400, array( 'field' => 'scopes' ) );
        }
        $valid  = Scope_Map::get_all_scopes();
        $scopes = array();
        foreach ( $raw as $scope ) {
            if ( ! is_string( $scope ) ) {
                continue;
            }
            $scope = \sanitize_text_field( $scope );
            if ( in_array( $scope, $valid, true ) && ! in_array( $scope, $scopes, true ) ) {
                $scopes[] = $scope;
            }
        }
        if ( empty( $scopes ) ) {
            
            
            return $this->fail( 'easy_mcp_ai_empty_scope', \__( 'Pick at least one category, or revoke the grant instead.', 'easy-mcp-ai' ), 400, array( 'field' => 'scopes' ) );
        }

        $current = self::scope_parts( (string) $row['scope'] );
        $scopes  = self::with_privileged_scopes( $scopes, $current, $request->get_param( 'privilegedScopes' ), $valid );
        if ( ! self::is_narrower_or_equal( $current, $scopes ) ) {
            return $this->fail(
                'easy_mcp_ai_scope_widening',
                \__( 'A grant can only be narrowed here. To give this client more access, the user has to approve it again.', 'easy-mcp-ai' ),
                400,
                array( 'field' => 'scopes' )
            );
        }

        OAuth_Admin::save_scope( (int) $row['consent_id'], $scopes );

        $fresh = $this->find_grant( $request );
        return $this->ok( array( 'grant' => $this->shape_grant( \is_wp_error( $fresh ) ? array_merge( $row, array( 'scope' => implode( ' ', $scopes ) ) ) : $fresh ) ) );
    }

    













    public static function with_privileged_scopes( array $scopes, array $current, $keep, array $valid ) {
        if ( null === $keep ) {
            $keep = Privileged_Groups::split_scopes( $current )[0];
        }
        foreach ( (array) $keep as $scope ) {
            $scope = is_string( $scope ) ? \sanitize_text_field( $scope ) : '';
            if ( Privileged_Groups::is_privileged_scope( $scope ) && in_array( $scope, $valid, true ) && ! in_array( $scope, $scopes, true ) ) {
                $scopes[] = $scope;
            }
        }
        return $scopes;
    }

    









    public static function is_narrower_or_equal( array $current, array $requested ) {
        $current   = Scope_Map::apply_legacy_scope_upgrades( array_values( $current ) );
        $requested = Scope_Map::apply_legacy_scope_upgrades( array_values( $requested ) );
        
        
        
        if ( in_array( self::FULL_SCOPE, $current, true ) ) {
            return true;
        }
        if ( in_array( self::FULL_SCOPE, $requested, true ) ) {
            return false;
        }
        foreach ( $requested as $scope ) {
            if ( ! in_array( $scope, $current, true ) ) {
                return false;
            }
        }
        return true;
    }

    



    public static function scope_parts( $scope ) {
        return array_values( array_filter( array_map( 'trim', explode( ' ', (string) $scope ) ), 'strlen' ) );
    }

    






    public function shape_grant( array $row, array $names = array(), $issued_elsewhere = false ) {
        $scope   = (string) ( $row['scope'] ?? '' );
        $parts   = self::scope_parts( $scope );
        
        
        
        list( $privileged, $ordinary ) = Privileged_Groups::split_scopes( $parts );
        $tools   = Scope_Map::resolve_allowed_tools( implode( ' ', $ordinary ) );
        $full    = in_array( '*', $tools, true );
        $user_id = (int) ( $row['wp_user_id'] ?? 0 );

        return array(
            'id'           => (int) ( $row['consent_id'] ?? 0 ),
            'user'         => array(
                'id'          => $user_id,
                'displayName' => isset( $names[ $user_id ] ) ? $names[ $user_id ] : self::display_name( $user_id ),
            ),
            'client'       => array(
                'clientId' => (string) ( $row['client_id'] ?? '' ),
                'name'     => (string) ( $row['client_name'] ?? '' ),
            ),
            'scope'        => $scope,
            'scopes'       => $parts,
            
            'privilegedScopes' => $privileged,
            'preset'       => empty( $ordinary ) ? Access_Presets::CUSTOM : $this->presets->from_allowed_tools( $tools ),
            'fullAccess'   => $full,
            'toolCount'    => $full ? null : count( $tools ),
            'grantedAt'    => Dashboard_Controller::iso8601( $row['granted_at'] ?? null ),
            'updatedAt'    => Dashboard_Controller::iso8601( $row['updated_at'] ?? null ),
            'lastUsedAt'   => Dashboard_Controller::iso8601( $row['last_used'] ?? null ),
            'activeTokens' => (int) ( $row['active_tokens'] ?? 0 ),
            'issuedElsewhere' => (bool) $issued_elsewhere,
        );
    }

    





    private function grant_rows() {
        $consents = $this->wpdb->prefix . 'easy_mcp_ai_oauth_consents';
        $clients  = $this->wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $tokens   = $this->wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        $now      = \current_time( 'mysql', true );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned tables; names prefixed by $wpdb->prefix.
        $rows = (array) $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT con.id AS consent_id, con.wp_user_id, con.client_id, con.scope, con.granted_at, con.updated_at,
                        cl.client_name,
                        (SELECT MAX(t.last_used_at) FROM {$tokens} t WHERE t.client_id = con.client_id AND t.wp_user_id = con.wp_user_id) AS last_used,
                        (SELECT COUNT(*) FROM {$tokens} t WHERE t.client_id = con.client_id AND t.wp_user_id = con.wp_user_id AND t.is_active = 1 AND t.expires_at > %s) AS active_tokens
                 FROM {$consents} con
                 INNER JOIN {$clients} cl ON cl.client_id = con.client_id AND cl.is_active = 1
                 ORDER BY con.granted_at DESC, con.id DESC
                 LIMIT %d",
                $now,
                self::ROWS_LIMIT + 1
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $out = array();
        foreach ( $rows as $row ) {
            $out[] = (array) $row;
        }
        return $out;
    }

    






    private function find_grant( $request ) {
        $id = \absint( $request->get_param( 'id' ) );
        if ( $id ) {
            foreach ( $this->grant_rows() as $row ) {
                if ( (int) $row['consent_id'] === $id ) {
                    return $row;
                }
            }
        }
        return $this->fail( 'easy_mcp_ai_grant_not_found', \__( 'Grant not found.', 'easy-mcp-ai' ), 404 );
    }

    





    public static function categories() {
        $out = array();
        foreach ( (array) Scope_Map::get_categories() as $cat ) {
            $out[] = array(
                'key'            => (string) ( $cat['slug'] ?? '' ),
                'label'          => (string) ( $cat['label'] ?? '' ),
                'readScope'      => (string) ( $cat['read_scope'] ?? '' ),
                'writeScope'     => (string) ( $cat['write_scope'] ?? '' ),
                'pluginRequired' => isset( $cat['plugin_required'] ) && '' !== (string) $cat['plugin_required'] ? (string) $cat['plugin_required'] : null,
                'defaultRead'    => ! empty( $cat['default_read'] ),
                'defaultWrite'   => ! empty( $cat['default_write'] ),
                'isAbility'      => ! empty( $cat['is_ability'] ),
            );
        }
        return $out;
    }

    

    








    public function get_device( $request ) {
        $device  = $this->wpdb->prefix . 'easy_mcp_ai_oauth_device_codes';
        $clients = $this->wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $now     = \current_time( 'mysql', true );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned tables; names prefixed by $wpdb->prefix.
        $rows = (array) $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT d.id, d.client_id, d.wp_user_id, d.status, d.created_at, d.expires_at, d.last_polled_at, d.decided_at,
                        cl.client_name
                 FROM {$device} d
                 INNER JOIN {$clients} cl ON cl.client_id = d.client_id AND cl.is_active = 1
                 WHERE d.status IN ('pending', 'approved') AND d.expires_at > %s
                 ORDER BY d.created_at DESC, d.id DESC
                 LIMIT %d",
                $now,
                self::ROWS_LIMIT
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $rows  = array_map( static function ( $row ) { return (array) $row; }, $rows );
        $names = $this->user_names( array_column( $rows, 'wp_user_id' ) );
        $out   = array();
        foreach ( $rows as $row ) {
            $out[] = self::shape_device( $row, $names );
        }
        return $this->ok( array( 'logins' => $out ) );
    }

    







    public static function shape_device( array $row, array $names = array() ) {
        $user_id = (int) ( $row['wp_user_id'] ?? 0 );
        return array(
            'id'           => (int) ( $row['id'] ?? 0 ),
            'client'       => array(
                'clientId' => (string) ( $row['client_id'] ?? '' ),
                'name'     => (string) ( $row['client_name'] ?? '' ),
            ),
            'user'         => $user_id ? array(
                'id'          => $user_id,
                'displayName' => isset( $names[ $user_id ] ) ? $names[ $user_id ] : self::display_name( $user_id ),
            ) : null,
            'status'       => (string) ( $row['status'] ?? '' ),
            'createdAt'    => Dashboard_Controller::iso8601( $row['created_at'] ?? null ),
            'expiresAt'    => Dashboard_Controller::iso8601( $row['expires_at'] ?? null ),
            'lastPolledAt' => Dashboard_Controller::iso8601( $row['last_polled_at'] ?? null ),
            'decidedAt'    => Dashboard_Controller::iso8601( $row['decided_at'] ?? null ),
        );
    }

    

    



    private function client_exists( $client_id ) {
        $clients = $this->wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; name prefixed by $wpdb->prefix.
        return (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$clients} WHERE client_id = %s AND is_active = 1", $client_id ) ) > 0;
    }

    
    public static function retention_days() {
        if ( class_exists( '\\Easy_MCP_AI\\Plugin' ) && method_exists( '\\Easy_MCP_AI\\Plugin', 'oauth_client_retention_days' ) ) {
            return (int) \Easy_MCP_AI\Plugin::oauth_client_retention_days();
        }
        return 7;
    }

    



    private function user_names( array $ids ) {
        $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
        if ( empty( $ids ) ) {
            return array();
        }
        $names = array();
        foreach ( (array) \get_users( array( 'include' => $ids, 'fields' => array( 'ID', 'display_name' ) ) ) as $user ) {
            if ( is_object( $user ) && ! empty( $user->ID ) ) {
                $names[ (int) $user->ID ] = (string) ( $user->display_name ?? '' );
            }
        }
        return $names;
    }

    private static function display_name( $user_id ) {
        $user = $user_id ? \get_userdata( $user_id ) : false;
        if ( $user && ! empty( $user->display_name ) ) {
            return (string) $user->display_name;
        }
        /* translators: %d: WordPress user id */
        return sprintf( \__( 'Unknown user #%d', 'easy-mcp-ai' ), (int) $user_id );
    }

    

    











    public function revoke_site_move( $request ) {
        $result = OAuth_Site_Move::revoke_mismatching();
        if ( \is_wp_error( $result ) ) {
            return $this->fail(
                'easy_mcp_ai_site_move_incomplete',
                \__( 'Nothing was acknowledged. Grants issued for the previous address are refused either way; try again to finish removing them.', 'easy-mcp-ai' ),
                500
            );
        }
        $this->site_move_rows = false; 
        return $this->ok( array(
            'tokens'   => (int) $result['tokens'],
            'consents' => (int) $result['consents'],
            'clients'  => (int) $result['clients'],
            'siteMove' => $this->site_move_payload(),
        ) );
    }

    







    public function dismiss_site_move( $request ) {
        OAuth_Site_Move::record_current();
        return $this->ok( array( 'dismissed' => true, 'siteMove' => null ) );
    }

    








    private function site_move_payload() {
        if ( ! OAuth_Site_Move::ensure_recorded() || ! OAuth_Site_Move::moved() ) {
            return null;
        }
        $summary = OAuth_Site_Move::summary( $this->site_move_rows() );
        if ( null === $summary ) {
            return null; 
        }
        if ( $summary['tokens'] < 1 ) {
            OAuth_Site_Move::record_current(); 
            return null;
        }
        return array(
            'from'    => array_values( $summary['sites'] ),
            'to'      => OAuth_Site_Move::site_label( OAuth_Site_Move::current_resource() ),
            'tokens'  => (int) $summary['tokens'],
            'clients' => (int) $summary['clients'],
        );
    }

    
    private function site_move_rows() {
        if ( false === $this->site_move_rows ) {
            $this->site_move_rows = OAuth_Site_Move::active_rows();
        }
        return $this->site_move_rows;
    }
}
