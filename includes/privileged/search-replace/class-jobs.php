<?php
namespace Easy_MCP_AI\Privileged\Search_Replace;

use Easy_MCP_AI\Privileged\Privileged_Groups;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}













final class Jobs {

    const TOOL_NAME = 'wp_privileged_search_replace_run';
    const TICK_HOOK = 'easy_mcp_ai_search_replace_tick';
    





    const RETENTION_DAYS = 1;

    public static function runner(): Runner {
        return new Runner(
            new Wpdb_Database(),
            new Job_Store(),
            null,
            array( __CLASS__, 'on_finish' ),
            static function () {
                if ( function_exists( 'wp_cache_flush' ) ) {
                    \wp_cache_flush();
                }
            }
        );
    }

    




    public static function site_context( int $user_id ): array {
        global $wpdb;
        $multisite = function_exists( 'is_multisite' ) && \is_multisite();
        $globals   = array();
        if ( $multisite && is_object( $wpdb ) && method_exists( $wpdb, 'tables' ) ) {
            $globals = array_values( (array) $wpdb->tables( 'global', true ) );
        }
        return array(
            'prefix'        => (string) $wpdb->prefix,
            'base_prefix'   => (string) ( $wpdb->base_prefix ?? $wpdb->prefix ),
            'multisite'     => $multisite,
            'blog_id'       => function_exists( 'get_current_blog_id' ) ? (int) \get_current_blog_id() : 1,
            'global_tables' => $globals,
            'network_user'  => self::network_user( $user_id ),
        );
    }

    
    public static function network_user( int $user_id ): bool {
        if ( ! ( function_exists( 'is_multisite' ) && \is_multisite() ) ) {
            return true;
        }
        return $user_id > 0 && \is_super_admin( $user_id ) && \user_can( $user_id, 'manage_network_options' );
    }

    
    public static function schedule( string $id ): void {
        if ( ! \wp_next_scheduled( self::TICK_HOOK, array( $id ) ) ) {
            \wp_schedule_single_event( time(), self::TICK_HOOK, array( $id ) );
        }
    }

    





    public static function guard( array $job ): ?string {
        $on = Privileged_Groups::switched_on_names();
        if ( ! in_array( self::TOOL_NAME, isset( $on['search_replace'] ) ? $on['search_replace'] : array(), true ) ) {
            return 'The search-replace tool was switched off.';
        }
        $user_id = (int) $job['user_id'];
        if ( ! Privileged_Groups::user_holds( 'search_replace', $user_id ) ) {
            return 'The user who started the job may no longer run search-replace.';
        }
        if ( ! empty( $job['needs_network'] ) && ! self::network_user( $user_id ) ) {
            return 'The job reaches network tables, and the user who started it is no longer a super admin with manage_network_options.';
        }
        if ( ! \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_enabled', true ) ) {
            return 'The audit log was switched off; privileged work runs only while it is on.';
        }
        if ( ! \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_enabled', true ) ) {
            return 'Change history was switched off; privileged work runs only while it is on.';
        }
        return null;
    }

    



    public static function tick( $id ): void {
        if ( ! is_string( $id ) || ! Job_Store::valid_id( $id ) ) {
            return;
        }
        $store = new Job_Store();
        $job   = $store->get( $id );
        if ( null === $job || 'running' !== $job['status'] ) {
            return;
        }
        if ( function_exists( 'get_current_blog_id' ) && (int) \get_current_blog_id() !== (int) $job['blog_id'] ) {
            return;
        }
        if ( ! $store->lock( $id, Runner::LOCK_TTL ) ) {
            return;
        }
        $previous = function_exists( 'get_current_user_id' ) ? (int) \get_current_user_id() : 0;
        try {
            
            
            $job = $store->get( $id, true );
            if ( null === $job || 'running' !== $job['status'] ) {
                return;
            }
            $runner  = self::runner();
            $refusal = self::guard( $job );
            if ( null !== $refusal ) {
                $runner->stop( $job, 'stopped', $refusal );
                return;
            }
            \wp_set_current_user( (int) $job['user_id'] );
            $job = $runner->run_slice( $job );
            if ( 'running' === $job['status'] ) {
                self::schedule( $id );
            }
        } finally {
            $store->unlock( $id );
            \wp_set_current_user( $previous );
        }
    }

    






    public static function on_finish( array $job ): void {
        $store = new Job_Store();
        if ( $store->live_job() === $job['id'] ) {
            $store->set_live_job( '' );
        }
        if ( ! empty( $job['dry_run'] ) ) {
            return;
        }
        
        
        if ( ! empty( $job['site_address'] ) ) {
            try {
                \Easy_MCP_AI\Diagnostics\Diagnostics::invalidate();
            } catch ( \Throwable $e ) {
                unset( $e ); 
            }
        }
        try {
            $identity = (array) $job['identity'];
            $summary  = self::runner()->summary( $job );
            unset( $summary['next'], $summary['samples'] );
            ( new \Easy_MCP_AI\History\Change_Log_Repository() )->insert( array(
                'audit_id'        => isset( $identity['audit_id'] ) ? (int) $identity['audit_id'] : null,
                'auth_source'     => (string) ( $identity['auth_source'] ?? 'legacy' ),
                'token_id'        => (int) ( $identity['token_id'] ?? 0 ),
                'oauth_client_id' => isset( $identity['oauth_client_id'] ) ? (string) $identity['oauth_client_id'] : null,
                'wp_user_id'      => (int) $job['user_id'],
                'tool_name'       => self::TOOL_NAME,
                'ip_address'      => isset( $identity['ip_address'] ) ? (string) $identity['ip_address'] : null,
                'action'          => 'update',
                'object_type'     => 'system',
                'object_id'       => 'search_replace:' . $job['id'],
                'object_subtype'  => 'search_replace',
                'after_value'     => array(
                    'arguments' => $job['args'],
                    'result'    => $summary,
                    'note'      => 'A database-wide search-replace. Its cells are not recorded one by one; undo is the opposite replacement, exact only when result.reversible.value is true.',
                ),
            ) );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( 'Easy MCP AI search-replace history entry failed: %s', $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging
            }
        }
    }

    
    public static function cleanup(): void {
        ( new Job_Store() )->delete_finished_before( time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
    }
}
