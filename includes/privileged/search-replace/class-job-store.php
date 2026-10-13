<?php
namespace Easy_MCP_AI\Privileged\Search_Replace;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class Job_Store {

    const JOB_PREFIX  = 'easy_mcp_ai_sr_job_';
    const INDEX       = 'easy_mcp_ai_sr_index';
    const LIVE        = 'easy_mcp_ai_sr_live';
    const LOCK_PREFIX = 'easy_mcp_ai_sr_lock_';
    
    const INDEX_MAX = 20;

    




    public function get( string $id, bool $fresh = false ) {
        if ( ! self::valid_id( $id ) ) {
            return null;
        }
        if ( $fresh ) {
            \wp_cache_delete( self::JOB_PREFIX . $id, 'options' );
        }
        $job = \get_option( self::JOB_PREFIX . $id, null );
        return is_array( $job ) ? $job : null;
    }

    
    public function save( array $job ): void {
        \update_option( self::JOB_PREFIX . $job['id'], $job, false );
        $this->index( $job );
    }

    public function delete( string $id ): void {
        \delete_option( self::JOB_PREFIX . $id );
        \delete_option( self::LOCK_PREFIX . $id );
        $index = $this->recent();
        unset( $index[ $id ] );
        \update_option( self::INDEX, $index, false );
    }

    





    public function recent(): array {
        $raw = \get_option( self::INDEX, array() );
        $out = array();
        foreach ( is_array( $raw ) ? $raw : array() as $id => $summary ) {
            if ( self::valid_id( (string) $id ) && is_array( $summary ) ) {
                $out[ (string) $id ] = $summary;
            }
        }
        return $out;
    }

    
    public function live_job(): string {
        $id = \get_option( self::LIVE, '' );
        return is_string( $id ) && self::valid_id( $id ) ? $id : '';
    }

    public function set_live_job( string $id ): void {
        if ( '' === $id ) {
            \delete_option( self::LIVE );
            return;
        }
        \update_option( self::LIVE, $id, false );
    }

    







    public function claim_live( string $id ): bool {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
            if ( ! \add_option( self::LIVE, $id, '', false ) ) {
                return false;
            }
            return true;
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic claim cannot go through the options cache.
        $won = 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::LIVE, $id ) );
        if ( ! $won ) {
            $holder = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LIVE ) );
            $job    = '' !== $holder && $holder !== $id ? $this->get( $holder, true ) : null;
            if ( null !== $job && 'running' === $job['status'] ) {
                return false;
            }
            
            $won = $holder === $id || 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $id, self::LIVE, $holder ) );
        }
        // phpcs:enable
        \wp_cache_delete( self::LIVE, 'options' );
        return $won;
    }

    




    public function lock( string $id, int $ttl ): bool {
        global $wpdb;
        $name = self::LOCK_PREFIX . $id;
        $now  = time();
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
            return \add_option( $name, $now, '', false );
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic lock cannot go through the options cache.
        $won = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, (string) $now ) );
        if ( 1 !== $won ) {
            $held = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
            if ( $held > 0 && $now - $held < $ttl ) {
                return false;
            }
            
            $won = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) $now, $name, (string) $held ) );
        }
        // phpcs:enable
        \wp_cache_delete( $name, 'options' );
        return 1 === $won;
    }

    public function unlock( string $id ): void {
        \delete_option( self::LOCK_PREFIX . $id );
    }

    public static function new_id(): string {
        return bin2hex( random_bytes( 8 ) );
    }

    public static function valid_id( string $id ): bool {
        return (bool) preg_match( '/^[a-f0-9]{16}\z/', $id );
    }

    
    private function index( array $job ): void {
        $index = $this->recent();
        unset( $index[ $job['id'] ] );
        $index[ $job['id'] ] = array(
            'args_hash' => (string) $job['args_hash'],
            'dry_run'   => (bool) $job['dry_run'],
            'status'    => (string) $job['status'],
            'finished'  => (string) ( $job['finished'] ?? '' ),
            'updated'   => (int) ( $job['updated'] ?? 0 ),
        );
        
        foreach ( $index as $id => $summary ) {
            if ( count( $index ) <= self::INDEX_MAX ) {
                break;
            }
            if ( 'running' !== ( $summary['status'] ?? '' ) ) {
                unset( $index[ $id ] );
                \delete_option( self::JOB_PREFIX . $id );
            }
        }
        \update_option( self::INDEX, $index, false );
    }

    




    public function delete_finished_before( int $cutoff ): int {
        $deleted = 0;
        foreach ( $this->recent() as $id => $summary ) {
            if ( 'running' !== ( $summary['status'] ?? '' ) && (int) ( $summary['updated'] ?? 0 ) < $cutoff ) {
                $this->delete( (string) $id );
                ++$deleted;
            }
        }
        return $deleted;
    }
}
