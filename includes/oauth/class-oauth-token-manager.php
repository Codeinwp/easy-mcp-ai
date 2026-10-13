<?php
namespace Easy_MCP_AI\OAuth;

use Easy_MCP_AI\Auth\Token_Keys;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/auth/class-token-keys.php';







class OAuth_Token_Manager {

    const TOKEN_PREFIX          = 'wpmcp_oat_';
    const DEFAULT_ACCESS_TTL   = 3600;
    const DEFAULT_REFRESH_TTL  = 2592000; 

    











    public function mint_access_token( string $client_id, int $wp_user_id, string $resource, string $scope, int $parent_id = 0 ): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';

        $access_ttl  = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_oauth_access_token_ttl', self::DEFAULT_ACCESS_TTL );
        $refresh_ttl = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_oauth_refresh_token_ttl', self::DEFAULT_REFRESH_TTL );

        
        
        
        
        
        $key_id        = Token_Keys::current_id();
        $raw_access    = Token_Keys::mint_prefix( self::TOKEN_PREFIX ) . bin2hex( random_bytes( 32 ) );
        $raw_refresh   = Token_Keys::mint_prefix( '' ) . bin2hex( random_bytes( 32 ) );
        $access_hash   = Token_Keys::hash( $raw_access, $key_id );
        $refresh_hash  = Token_Keys::hash( $raw_refresh, $key_id );

        $now               = current_time( 'mysql', true );
        $expires_at        = gmdate( 'Y-m-d H:i:s', time() + $access_ttl );
        $refresh_expires   = gmdate( 'Y-m-d H:i:s', time() + $refresh_ttl );

        $data = array(
            'token_hash'         => $access_hash,
            'refresh_hash'       => $refresh_hash,
            'client_id'          => sanitize_text_field( $client_id ),
            'wp_user_id'         => absint( $wp_user_id ),
            'resource'           => esc_url_raw( $resource ),
            'scope'              => sanitize_text_field( $scope ),
            'expires_at'         => $expires_at,
            'refresh_expires_at' => $refresh_expires,
            'is_active'          => 1,
            'created_at'         => $now,
        );
        $format = array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' );

        if ( $parent_id > 0 ) {
            $data['refresh_parent_id'] = $parent_id;
            $format[] = '%d';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table; writes don't need caching.
        $inserted = $wpdb->insert( $table, $data, $format );
        if ( false === $inserted ) {
            return null;
        }
        $token_id = (int) $wpdb->insert_id;

        return array(
            'access_token'  => $raw_access,
            'refresh_token' => $raw_refresh,
            'expires_in'    => $access_ttl,
            'scope'         => $scope,
            'token_id'      => $token_id,
        );
    }

    





    public function validate_access_token( string $raw_token ) {
        global $wpdb;

        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';

        
        if ( 0 !== strpos( $raw_token, self::TOKEN_PREFIX ) ) {
            return false;
        }

        
        $key_id     = Token_Keys::parse_id( $raw_token, self::TOKEN_PREFIX );
        $token_hash = Token_Keys::hash( $raw_token, $key_id );
        if ( null === $token_hash ) {
            return false;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; token lookup must be fresh.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, wp_user_id, client_id, scope, resource, is_active, expires_at FROM {$table} WHERE token_hash = %s LIMIT 1",
                $token_hash
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! $row ) {
            return false;
        }

        if ( ! (int) $row['is_active'] ) {
            return false;
        }

        
        
        if ( strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
            return false;
        }

        
        
        $throttle_key = 'easy_mcp_ai_oat_lu_' . (int) $row['id'];
        if ( ! \get_transient( $throttle_key ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write; throttled via transient.
            $wpdb->update(
                $table,
                array( 'last_used_at' => current_time( 'mysql', true ) ),
                array( 'id' => (int) $row['id'] ),
                array( '%s' ),
                array( '%d' )
            );
            \set_transient( $throttle_key, 1, 60 );
        }

        return $row;
    }

    









    public function refresh( string $raw_refresh_token, string $client_id, string $resource ) {
        global $wpdb;

        $table        = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        $key_id       = Token_Keys::parse_id( $raw_refresh_token, '' );
        $refresh_hash = Token_Keys::hash( $raw_refresh_token, $key_id );
        if ( null === $refresh_hash ) {
            return false;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; refresh lookup must be fresh.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, is_active, client_id, wp_user_id, resource, scope, refresh_expires_at, refresh_parent_id, token_hash FROM {$table} WHERE refresh_hash = %s LIMIT 1",
                $refresh_hash
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! $row ) {
            return false;
        }

        
        
        $row['scope'] = implode( ' ', \Easy_MCP_AI\OAuth\Scope_Map::apply_legacy_scope_upgrades(
            array_filter( explode( ' ', $row['scope'] ) )
        ) );

        
        
        
        
        
        
        
        
        if ( ! (int) $row['is_active'] ) {
            if ( $row['client_id'] !== $client_id ) {
                return false;
            }
            $chain_id = ! empty( $row['refresh_parent_id'] ) ? (int) $row['refresh_parent_id'] : (int) $row['id'];
            if ( $this->chain_has_live_successor( $chain_id ) ) {
                
                
                
                
                
                
                
                $this->log_refresh_event( 'race_refused', $chain_id, $client_id );
                return false;
            }
            $this->log_refresh_event( 'reuse_revoked', $chain_id, $client_id );
            $this->revoke_chain( $chain_id );
            return false;
        }

        
        if ( $row['client_id'] !== $client_id ) {
            return false;
        }

        
        
        if ( ! empty( $row['refresh_expires_at'] ) && strtotime( $row['refresh_expires_at'] . ' UTC' ) < time() ) {
            return false;
        }

        
        $parent_id = ! empty( $row['refresh_parent_id'] ) ? (int) $row['refresh_parent_id'] : (int) $row['id'];

        
        
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; atomic claim must hit DB.
        $claimed = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET is_active = 0 WHERE id = %d AND is_active = 1",
                (int) $row['id']
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( 1 !== (int) $claimed ) {
            
            
            
            
            
            if ( $this->chain_has_live_successor( $parent_id ) ) {
                
                
                $this->log_refresh_event( 'race_refused', $parent_id, $client_id );
                return false;
            }
            $this->log_refresh_event( 'reuse_revoked', $parent_id, $client_id );
            $this->revoke_chain( $parent_id );
            return false;
        }

        
        
        $new_pair = $this->mint_access_token(
            $client_id,
            (int) $row['wp_user_id'],
            $row['resource'],
            $row['scope'],
            $parent_id
        );

        if ( null === $new_pair ) {
            return false;
        }

        return $new_pair;
    }

    






    public function revoke_token( string $token_hash, string $client_id = '' ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';

        
        if ( '' !== $client_id ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix.
            $stored = $wpdb->get_var(
                $wpdb->prepare( "SELECT client_id FROM {$table} WHERE token_hash = %s LIMIT 1", $token_hash )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( null === $stored || $stored !== $client_id ) {
                return false;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write.
        $result = $wpdb->update(
            $table,
            array( 'is_active' => 0 ),
            array( 'token_hash' => $token_hash ),
            array( '%d' ),
            array( '%s' )
        );

        
        
        
        
        
        return false !== $result;
    }

    







    public function revoke_by_refresh_hash( string $refresh_hash, string $client_id = '' ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix.
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, client_id, refresh_parent_id FROM {$table} WHERE refresh_hash = %s LIMIT 1",
                $refresh_hash
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ( ! $row ) {
            return false;
        }
        
        if ( '' !== $client_id && $row['client_id'] !== $client_id ) {
            return false;
        }
        $chain_id = ! empty( $row['refresh_parent_id'] ) ? (int) $row['refresh_parent_id'] : (int) $row['id'];
        $this->revoke_chain( $chain_id );
        return true;
    }

    








































    private function chain_has_live_successor( int $chain_id ): bool {
        global $wpdb;

        if ( $chain_id <= 0 ) {
            return false;
        }

        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; freshness required.
        
        
        
        
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE refresh_parent_id = %d AND is_active = 1",
                $chain_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $count >= 1;
    }

    









    private function log_refresh_event( string $event, int $chain_id, string $client_id ): void {
        if ( ! \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_enabled', true ) ) {
            return;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned audit table.
        $wpdb->insert(
            $wpdb->prefix . 'easy_mcp_ai_audit_log',
            array(
                'token_id'      => 0,
                'tool_name'     => '_oauth_refresh',
                'arguments'     => \wp_json_encode( array(
                    'event'     => $event,
                    'chain_id'  => $chain_id,
                    'client_id' => $client_id,
                ) ),
                'result_status' => $event,
                
                
                
                
                
                'ip_address'    => class_exists( '\\Easy_MCP_AI\\Client_IP' ) ? \Easy_MCP_AI\Client_IP::get() : '',
                
                
                'auth_source'     => 'oauth',
                'oauth_client_id' => '' !== $client_id ? $client_id : null,
                'created_at'      => \current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    





    public static function hash_presented( string $token ): ?string {
        $prefix = 0 === strpos( $token, self::TOKEN_PREFIX ) ? self::TOKEN_PREFIX : '';
        return Token_Keys::hash( $token, Token_Keys::parse_id( $token, $prefix ) );
    }

    public function revoke_chain( int $refresh_parent_id ): bool {
        if ( $refresh_parent_id <= 0 ) {
            return false;
        }

        global $wpdb;

        $table  = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix.
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET is_active = 0 WHERE refresh_parent_id = %d OR id = %d",
                $refresh_parent_id,
                $refresh_parent_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return false !== $result;
    }
}
