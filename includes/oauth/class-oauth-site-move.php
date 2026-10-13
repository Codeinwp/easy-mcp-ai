<?php



































namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/oauth/class-token-endpoint.php';

class OAuth_Site_Move {

    
    const OPTION = 'easy_mcp_ai_oauth_site_host';

    
    const IDLE_DAYS = 30;

    
    const BATCH = 500;

    
    const ERROR_INCOMPLETE = 'site_move_revoke_incomplete';

    

    



    public static function current_resource(): string {
        return Token_Endpoint::canonicalize_resource_uri( (string) \rest_url( 'easy-mcp-ai/v1/mcp' ) );
    }

    
    public static function recorded(): string {
        $stored = \get_option( self::OPTION, '' );
        return is_string( $stored ) ? Token_Endpoint::canonicalize_resource_uri( $stored ) : '';
    }

    
    public static function record_current(): void {
        $current = self::current_resource();
        if ( '' !== $current ) {
            \update_option( self::OPTION, $current, false ); 
        }
    }

    








    public static function ensure_recorded(): bool {
        if ( '' !== self::recorded() ) {
            return true;
        }
        self::record_current();
        return false;
    }

    
    public static function moved(): bool {
        $recorded = self::recorded();
        $current  = self::current_resource();
        return '' !== $recorded && '' !== $current && ! Token_Endpoint::resource_matches( $recorded, $current );
    }

    

    












    public static function active_rows( bool $idle_only = false ) {
        global $wpdb;
        $current = self::current_resource();
        if ( '' === $current ) {
            return null;
        }
        $table = self::tables()['tokens'];
        $where = 'is_active = 1' . ( $idle_only ? ' AND ' . self::idle_predicate() : '' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; bounded by the active token count; the predicate is built from class constants.
        $rows = $wpdb->get_results( "SELECT id, client_id, wp_user_id, resource FROM `{$table}` WHERE {$where}", ARRAY_A );
        
        if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
            return null;
        }
        $verdict = array();
        $out     = array();
        foreach ( $rows as $row ) {
            $resource = (string) $row['resource'];
            if ( ! isset( $verdict[ $resource ] ) ) {
                $verdict[ $resource ] = self::is_foreign( $resource, $current );
            }
            $out[] = array(
                'id'         => (int) $row['id'],
                'client_id'  => (string) $row['client_id'],
                'wp_user_id' => (int) $row['wp_user_id'],
                'resource'   => $resource,
                'foreign'    => $verdict[ $resource ],
            );
        }
        return $out;
    }

    



    private static function is_foreign( string $resource, string $current ): bool {
        return '' !== $resource && ! Token_Endpoint::resource_matches( $resource, $current );
    }

    




    public static function foreign_rows( bool $idle_only = false, $rows = false ) {
        $rows = false === $rows ? self::active_rows( $idle_only ) : $rows;
        return null === $rows ? null : array_values( array_filter( $rows, static function ( $r ) { return $r['foreign']; } ) );
    }

    






    public static function summary( $rows = false ) {
        $foreign = self::foreign_rows( false, $rows );
        if ( null === $foreign ) {
            return null;
        }
        $clients = array();
        $sites   = array();
        foreach ( $foreign as $row ) {
            $clients[ $row['client_id'] ] = true;
            $label                        = self::site_label( $row['resource'] );
            if ( '' !== $label ) {
                $sites[ $label ] = true;
            }
        }
        return array( 'tokens' => count( $foreign ), 'clients' => count( $clients ), 'sites' => array_keys( $sites ) );
    }

    








    public static function flagged_grants( $rows = false ): array {
        return self::flagged( array( __CLASS__, 'pair_key' ), $rows );
    }

    






    public static function flagged_clients( $rows = false ): array {
        return self::flagged( static function ( $r ) { return $r['client_id']; }, $rows );
    }

    private static function flagged( callable $key, $rows ): array {
        $local = array();
        $any   = array();
        foreach ( (array) ( false === $rows ? self::active_rows() : $rows ) as $row ) { 
            $k         = $key( $row );
            $any[ $k ] = true;
            if ( ! $row['foreign'] ) {
                $local[ $k ] = true;
            }
        }
        return array_diff_key( $any, $local );
    }

    

    























    public static function revoke_mismatching() {
        global $wpdb;
        $result = array( 'tokens' => 0, 'consents' => 0, 'clients' => 0 );
        $rows   = self::active_rows();
        if ( null === $rows ) {
            return self::incomplete( 'read' );
        }
        $t = self::tables();

        $ids = array(); $pairs = array(); $local = array(); $client_ids = array();
        foreach ( $rows as $row ) {
            $k = self::pair_key( $row );
            if ( $row['foreign'] ) {
                $ids[]                           = $row['id'];
                $pairs[ $k ]                     = array( $row['client_id'], $row['wp_user_id'] );
                $client_ids[ $row['client_id'] ] = true;
            } else {
                $local[ $k ] = true;
            }
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- plugin-owned tables; every IN() list is %d/%s placeholders spread from values this class read.
        $now = \current_time( 'mysql', true );
        foreach ( array_diff_key( $pairs, $local ) as $pair ) {
            if ( false === $wpdb->query( $wpdb->prepare( "UPDATE `{$t['consents']}` SET scope = '', updated_at = %s WHERE client_id = %s AND wp_user_id = %d", $now, $pair[0], $pair[1] ) ) ) {
                return self::incomplete( 'consents' );
            }
        }

        if ( ! self::deactivate_tokens( $ids, $result['tokens'] ) ) {
            return self::incomplete( 'tokens' );
        }

        $ok = $wpdb->query(
            "DELETE FROM `{$t['consents']}` WHERE scope = ''
               AND NOT EXISTS (SELECT 1 FROM `{$t['tokens']}` t WHERE t.client_id = `{$t['consents']}`.client_id AND t.wp_user_id = `{$t['consents']}`.wp_user_id AND t.is_active = 1)"
        );
        if ( false === $ok ) {
            return self::incomplete( 'consent_delete' );
        }
        $result['consents'] = (int) $ok;

        foreach ( array_chunk( self::foreign_code_ids(), self::BATCH ) as $chunk ) {
            $in = self::placeholders( $chunk, '%d' );
            if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM `{$t['codes']}` WHERE id IN ({$in})", ...$chunk ) ) ) {
                return self::incomplete( 'codes' );
            }
        }

        
        foreach ( array_chunk( array_keys( $client_ids ), self::BATCH ) as $chunk ) {
            $cin = self::placeholders( $chunk, '%s' );
            $ok  = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `{$t['clients']}` WHERE client_id IN ({$cin})
                       AND NOT EXISTS (SELECT 1 FROM `{$t['tokens']}` t   WHERE t.client_id = `{$t['clients']}`.client_id AND t.is_active = 1)
                       AND NOT EXISTS (SELECT 1 FROM `{$t['consents']}` s WHERE s.client_id = `{$t['clients']}`.client_id)
                       AND NOT EXISTS (SELECT 1 FROM `{$t['codes']}` k    WHERE k.client_id = `{$t['clients']}`.client_id)
                       AND NOT EXISTS (SELECT 1 FROM `{$t['devices']}` d  WHERE d.client_id = `{$t['clients']}`.client_id)",
                    ...$chunk
                )
            );
            if ( false === $ok ) {
                return self::incomplete( 'clients' );
            }
            $result['clients'] += (int) $ok;
        }
        // phpcs:enable

        self::record_current();
        return $result;
    }

    
    private static function foreign_code_ids(): array {
        global $wpdb;
        $current = self::current_resource();
        $table   = self::tables()['codes'];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; codes live ten minutes and are swept daily, so this set is small.
        $rows = $wpdb->get_results( "SELECT id, resource FROM `{$table}`", ARRAY_A );
        $ids  = array();
        foreach ( (array) $rows as $row ) {
            if ( self::is_foreign( (string) $row['resource'], $current ) ) {
                $ids[] = (int) $row['id'];
            }
        }
        return $ids;
    }

    private static function incomplete( string $stage ): \WP_Error {
        return new \WP_Error(
            self::ERROR_INCOMPLETE,
            __( 'Revocation did not finish. Grants issued for the previous address may still be active; try again.', 'easy-mcp-ai' ),
            array( 'stage' => $stage )
        );
    }

    

    



















    public static function sweep(): array {
        global $wpdb;
        $result = array( 'tokens' => 0, 'consents' => 0 );
        $rows   = self::foreign_rows( true );
        if ( empty( $rows ) ) {
            return $result; 
        }
        if ( ! self::deactivate_tokens( array_column( $rows, 'id' ), $result['tokens'] ) ) {
            return $result; 
        }
        $t     = self::tables();
        $pairs = array();
        foreach ( $rows as $row ) {
            $pairs[ self::pair_key( $row ) ] = array( $row['client_id'], $row['wp_user_id'] );
        }
        foreach ( $pairs as $pair ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned tables.
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM `{$t['consents']}` WHERE client_id = %s AND wp_user_id = %d
                       AND NOT EXISTS (SELECT 1 FROM `{$t['tokens']}` t WHERE t.client_id = `{$t['consents']}`.client_id AND t.wp_user_id = `{$t['consents']}`.wp_user_id AND t.is_active = 1)",
                    $pair[0],
                    $pair[1]
                )
            );
            $result['consents'] += (int) $deleted; 
        }
        return $result;
    }

    
    public static function idle_predicate(): string {
        return 'COALESCE(last_used_at, created_at) < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (int) self::IDLE_DAYS . ' DAY)';
    }

    

    



    public static function site_label( string $resource ): string {
        $canonical = Token_Endpoint::canonicalize_resource_uri( $resource );
        $parts     = \wp_parse_url( '' !== $canonical ? $canonical : $resource );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return '';
        }
        $label = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' ) . $parts['host'];
        if ( isset( $parts['port'] ) ) {
            $label .= ':' . (int) $parts['port'];
        }
        return $label;
    }

    
    private static function tables(): array {
        global $wpdb;
        $prefix = $wpdb->prefix . 'easy_mcp_ai_oauth_';
        return array(
            'tokens'   => $prefix . 'access_tokens',
            'consents' => $prefix . 'consents',
            'codes'    => $prefix . 'codes',
            'clients'  => $prefix . 'clients',
            'devices'  => $prefix . 'device_codes',
        );
    }

    
    private static function pair_key( array $row ): string {
        return $row['client_id'] . ':' . $row['wp_user_id'];
    }

    



    private static function deactivate_tokens( array $ids, int &$count ): bool {
        global $wpdb;
        $tokens = self::tables()['tokens'];
        foreach ( array_chunk( $ids, self::BATCH ) as $chunk ) {
            $in = self::placeholders( $chunk, '%d' );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- plugin-owned table; %d placeholders spread.
            $ok = $wpdb->query( $wpdb->prepare( "UPDATE `{$tokens}` SET is_active = 0 WHERE id IN ({$in})", ...$chunk ) );
            if ( false === $ok ) {
                return false;
            }
            $count += (int) $ok;
        }
        return true;
    }

    
    private static function placeholders( array $values, string $type ): string {
        return implode( ', ', array_fill( 0, count( $values ), $type ) );
    }
}
