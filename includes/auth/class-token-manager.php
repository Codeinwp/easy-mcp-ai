<?php
namespace Easy_MCP_AI\Auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/auth/class-token-keys.php';


require_once __DIR__ . '/../class-site-host.php';

class Token_Manager {

    const TOKEN_PREFIX = 'wpmcp_';

    




    const ERROR_SITE_MISMATCH = 'site_mismatch';

    










    private $last_validation_error = null;

    public function create_token( $name, $wp_user_id, $allowed_tools = array( '*' ), $expires_at = null ) {
        global $wpdb;
        
        $raw_token   = Token_Keys::mint_prefix( self::TOKEN_PREFIX ) . bin2hex( random_bytes( 32 ) );
        $token_hash  = Token_Keys::hash( $raw_token, Token_Keys::current_id() );
        $token_pfx   = substr( $raw_token, 0, 14 );
        $table = esc_sql( $wpdb->prefix . 'easy_mcp_ai_tokens' );
        
        
        
        
        $site_host_hash = \Easy_MCP_AI\Site_Host::current_hash();
        $result = $wpdb->insert( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct DB required; table name is plugin-controlled, not user input.
            'name'          => sanitize_text_field( $name ),
            'token_hash'    => $token_hash,
            'token_prefix'  => $token_pfx,
            'allowed_tools' => wp_json_encode( $allowed_tools ),
            'wp_user_id'    => absint( $wp_user_id ),
            'created_by'    => \get_current_user_id() ?: null,
            'site_host_hash' => '' !== $site_host_hash ? $site_host_hash : null,
            'expires_at'    => $expires_at ? $this->normalize_expires_at( sanitize_text_field( $expires_at ) ) : null,
            'is_active'     => 1,
            'created_at'    => current_time( 'mysql', true ),
            'updated_at'    => current_time( 'mysql', true ),
        ), array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' ) );
        if ( false === $result ) {
            return new \WP_Error( 'token_create_failed', __( 'Failed to create token.', 'easy-mcp-ai' ) );
        }
        return array( 'id' => $wpdb->insert_id, 'raw_token' => $raw_token, 'prefix' => $token_pfx );
    }

    



















    public function create_self_service_token( $name, $user_id, array $tools, $expires_at, $limit ) {
        $count = $this->count_active_tokens( $user_id );
        if ( null === $count ) {
            return new \WP_Error( 'count_failed', __( 'Could not check the key limit. Please try again.', 'easy-mcp-ai' ) );
        }
        if ( $count >= $limit ) {
            return new \WP_Error( 'limit', __( 'You have reached the active key limit. Revoke a key before creating another.', 'easy-mcp-ai' ) );
        }
        return $this->create_token( $name, $user_id, $tools, $expires_at );
    }

    






    private function count_active_tokens( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_tokens';
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE wp_user_id = %d AND is_active = 1 AND (expires_at IS NULL OR expires_at >= %s)", $user_id, gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table; the count must be fresh.
        return ( null === $count || false === $count ) ? null : (int) $count;
    }

    public function get_user_tokens( $user_id, $page = 1 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_tokens';
        return $wpdb->get_results( $wpdb->prepare( "SELECT id, name, token_prefix, wp_user_id, last_used_at, expires_at, is_active, site_host_hash FROM `{$table}` WHERE wp_user_id = %d ORDER BY id DESC LIMIT 21 OFFSET %d", $user_id, ( max( 1, (int) $page ) - 1 ) * 20 ), ARRAY_A );
    }

    
    public static function stash_new_token( array $result ) {
        \update_user_meta( \get_current_user_id(), '_easy_mcp_ai_new_token_' . \get_current_blog_id() . '_' . $result['id'], array(
            'token' => $result['raw_token'], 'expires' => time() + 60,
        ) );
    }

    public static function take_new_token( $token_id ) {
        $key = '_easy_mcp_ai_new_token_' . \get_current_blog_id() . '_' . (int) $token_id;
        $stored = \get_user_meta( \get_current_user_id(), $key, true );
        
        if ( ! $stored || ! \delete_user_meta( \get_current_user_id(), $key, $stored ) ) { return false; }
        return is_array( $stored ) && ! empty( $stored['token'] ) && isset( $stored['expires'] ) && $stored['expires'] >= time() ? $stored['token'] : false;
    }

    public function validate_token( $raw_token ) {
        global $wpdb;
        $this->last_validation_error = null;
        if ( empty( $raw_token ) || 0 !== strpos( $raw_token, self::TOKEN_PREFIX ) ) {
            return false;
        }
        
        $key_id     = Token_Keys::parse_id( $raw_token, self::TOKEN_PREFIX );
        $token_hash = Token_Keys::hash( $raw_token, $key_id );
        if ( null === $token_hash ) {
            return false;
        }
        
        
        
        
        
        
        $cache_key  = self::cache_key( $token_hash );
        $cached     = wp_cache_get( $cache_key, 'easy_mcp_ai' );
        if ( false !== $cached ) {
            if ( empty( $cached['expires_at'] ) || strtotime( $cached['expires_at'] . ' UTC' ) >= time() ) {
                return $this->refuse_unless_bound_here( $cached );
            }
            return false;
        }
        $table = esc_sql( $wpdb->prefix . 'easy_mcp_ai_tokens' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached above; table name is plugin-controlled, not user input.
        $token = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE token_hash = %s AND is_active = 1", $token_hash ), ARRAY_A );
        if ( ! $token ) {
            return false;
        }
        if ( ! empty( $token['expires_at'] ) && strtotime( $token['expires_at'] . ' UTC' ) < time() ) {
            return false;
        }
        \wp_cache_set( $cache_key, $token, 'easy_mcp_ai', 60 );
        
        
        \wp_cache_set( 'token_id_' . $token['id'], $token, 'easy_mcp_ai', 60 );
        return $this->refuse_unless_bound_here( $token );
    }

    







    public function get_last_validation_error() {
        return $this->last_validation_error;
    }

    





















    private function refuse_unless_bound_here( array $token ) {
        if ( ! array_key_exists( 'site_host_hash', $token ) ) {
            return $token;
        }
        if ( \Easy_MCP_AI\Site_Host::matches_hash( $token['site_host_hash'] ) ) {
            return $token;
        }
        $this->last_validation_error = new \WP_Error(
            self::ERROR_SITE_MISMATCH,
            __( 'This API key was issued on another site.', 'easy-mcp-ai' ),
            array(
                'token_id'   => isset( $token['id'] ) ? (int) $token['id'] : 0,
                'wp_user_id' => isset( $token['wp_user_id'] ) ? (int) $token['wp_user_id'] : 0,
            )
        );
        return false;
    }

    







    public static function is_bound_elsewhere( array $token ) {
        return array_key_exists( 'site_host_hash', $token ) && ! \Easy_MCP_AI\Site_Host::matches_hash( $token['site_host_hash'] );
    }

    









    public function rebind_token( $token_id ) {
        $host = \Easy_MCP_AI\Site_Host::current();
        if ( '' === $host ) {
            return new \WP_Error( 'no_site_host', __( 'This site\'s host could not be determined, so the key was not re-bound.', 'easy-mcp-ai' ) );
        }
        return $this->update_token( $token_id, array( 'site_host' => $host ) );
    }

    public function update_last_used( $token_id ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'easy_mcp_ai_tokens' );
        
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB required; table name is plugin-controlled, not user input.
        $wpdb->query( $wpdb->prepare(
            "UPDATE `{$table}` SET last_used_at = UTC_TIMESTAMP() WHERE id = %d AND (last_used_at IS NULL OR last_used_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))",
            absint( $token_id )
        ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public function get_token_by_id( $token_id ) {
        $cache_key = 'token_id_' . absint( $token_id );
        $cached    = wp_cache_get( $cache_key, 'easy_mcp_ai' );
        if ( false !== $cached ) {
            return $cached;
        }
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'easy_mcp_ai_tokens' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached above; table name is plugin-controlled, not user input.
        $token = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", absint( $token_id ) ), ARRAY_A );
        
        
        
        
        
        
        if ( null !== $token ) {
            \wp_cache_set( $cache_key, $token, 'easy_mcp_ai', 60 );
        }
        return $token;
    }

    




    public static function cache_key( $token_hash ) {
        $site = \Easy_MCP_AI\Site_Host::current_hash();
        return 'token_' . $token_hash . ( '' !== $site ? '_' . substr( $site, 0, 16 ) : '' );
    }

    private function invalidate_token_cache( $token_id ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'easy_mcp_ai_tokens' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lightweight lookup for cache invalidation only; table name is plugin-controlled.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT token_hash FROM `{$table}` WHERE id = %d", absint( $token_id ) ), ARRAY_A );
        if ( $row ) {
            
            
            
            \wp_cache_delete( self::cache_key( $row['token_hash'] ), 'easy_mcp_ai' );
        }
        \wp_cache_delete( 'token_id_' . \absint( $token_id ), 'easy_mcp_ai' );
    }

    public function count_tokens() {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'easy_mcp_ai_tokens' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB required; table name is plugin-controlled, not user input.
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())" );
    }

    public function get_all_tokens( $limit = 200, $offset = 0 ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'easy_mcp_ai_tokens' );
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB required; table name is plugin-controlled, not user input.
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d", absint( $limit ), absint( $offset ) ), ARRAY_A );
    }

    public function update_token( $token_id, $data ) {
        $this->invalidate_token_cache( $token_id );
        global $wpdb;
        $update  = array( 'updated_at' => current_time( 'mysql', true ) );
        $formats = array( '%s' );
        if ( isset( $data['name'] ) ) { $update['name'] = sanitize_text_field( $data['name'] ); $formats[] = '%s'; }
        if ( isset( $data['allowed_tools'] ) ) { $update['allowed_tools'] = wp_json_encode( $data['allowed_tools'] ); $formats[] = '%s'; }
        if ( isset( $data['wp_user_id'] ) ) { $update['wp_user_id'] = absint( $data['wp_user_id'] ); $formats[] = '%d'; }
        if ( isset( $data['is_active'] ) ) { $update['is_active'] = absint( $data['is_active'] ); $formats[] = '%d'; }
        
        if ( isset( $data['unattended'] ) ) { $update['unattended'] = $data['unattended'] ? 1 : 0; $formats[] = '%d'; }
        if ( array_key_exists( 'expires_at', $data ) ) { $update['expires_at'] = $data['expires_at'] ? $this->normalize_expires_at( sanitize_text_field( $data['expires_at'] ) ) : null; $formats[] = '%s'; }
        
        
        if ( array_key_exists( 'site_host', $data ) ) { $host_hash = \Easy_MCP_AI\Site_Host::hash( (string) $data['site_host'] ); $update['site_host_hash'] = '' !== $host_hash ? $host_hash : null; $formats[] = '%s'; }
        return $wpdb->update( $wpdb->prefix . 'easy_mcp_ai_tokens', $update, array( 'id' => absint( $token_id ) ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB required; table name is plugin-controlled, not user input.
    }

    public function delete_token( $token_id ) {
        $this->invalidate_token_cache( $token_id );
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'easy_mcp_ai_tokens', array( 'id' => absint( $token_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB required; table name is plugin-controlled, not user input.
    }

    public function revoke_token( $token_id, $owner_id = null ) {
        if ( null !== $owner_id ) {
            global $wpdb;
            $this->invalidate_token_cache( $token_id );
            
            return $wpdb->update( $wpdb->prefix . 'easy_mcp_ai_tokens',
                array( 'is_active' => 0, 'updated_at' => current_time( 'mysql', true ) ),
                array( 'id' => absint( $token_id ), 'wp_user_id' => absint( $owner_id ) ),
                array( '%d', '%s' ), array( '%d', '%d' ) );
        }
        return $this->update_token( $token_id, array( 'is_active' => 0 ) );
    }

    public function get_allowed_tools( $token_id ) {
        $token = $this->get_token_by_id( $token_id );
        if ( ! $token ) { return array(); }
        $tools = json_decode( $token['allowed_tools'], true );
        return is_array( $tools ) ? $tools : array();
    }

    






    private function normalize_expires_at( $value ) {
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return $value . ' 23:59:59';
        }
        return $value;
    }
}
