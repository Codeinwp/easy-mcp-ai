<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Self_Protection;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































class Cache_Handler extends Command_Handler {

    
    const NOT_RECORDED = 'Object cache: change history does not record the object cache, which is not the database.';

    
    const NOTE_RUNTIME = 'This site has no persistent object cache, so the write reached only the memory of this request and ended with it; WP-CLI prints the same line for the same reason. changed is false.';

    
    const FLUSH_RESET = 'Flushing the object cache also ended every MCP session and reset the rate-limit and lockout counters of the plugin that serves this MCP connection, which this site keeps in the object cache. MCP clients start a new session on their next request.';

    const NOTE_NO_PATH = "WP-CLI exits 1 without output here: the object '%s' in group '%s' has no value at that key path.";

    
    const RUNTIME_STATE = '(no persistent object cache: only the memory of the request that runs it)';

    
    
    

    
    public function preflight_group_third( Invocation $inv, Context $ctx ): void {
        $this->guard( (string) $this->arg( $inv, 2, 'default' ), $ctx );
    }

    
    public function preflight_group_second( Invocation $inv, Context $ctx ): void {
        $this->guard( (string) $this->arg( $inv, 1, 'default' ), $ctx );
    }

    
    public function preflight_group_first( Invocation $inv, Context $ctx ): void {
        $this->guard( (string) $this->arg( $inv, 0, '' ), $ctx );
    }

    
    public function preflight_group_flag( Invocation $inv, Context $ctx ): void {
        $this->guard( (string) Read_Value::flag( $inv->assoc, 'group', 'default' ), $ctx );
    }

    
    
    

    
    public function add( Invocation $inv, Context $ctx ): void {
        list( $key, $value, $group, $expiration ) = $this->positionals( $inv, 4 );
        $this->guard( (string) $group, $ctx );
        $ctx->not_captured( self::NOT_RECORDED );

        if ( ! $this->cache_add( $key, $value, $group, $expiration ) ) {
            $ctx->out->error( "Could not add object '$key' in group '$group'. Does it already exist?" );
        }

        $this->wrote( $ctx, true );
        $ctx->out->success( "Added object '$key' in group '$group'." );
    }

    
    public function decr( Invocation $inv, Context $ctx ): void {
        $this->step( $inv, $ctx, 'decr', 'The value was not decremented.' );
    }

    
    public function incr( Invocation $inv, Context $ctx ): void {
        $this->step( $inv, $ctx, 'incr', 'The value was not incremented.' );
    }

    
    public function delete( Invocation $inv, Context $ctx ): void {
        list( $key, $group ) = $this->positionals( $inv, 2 );
        $this->guard( (string) $group, $ctx );
        $ctx->not_captured( self::NOT_RECORDED );

        $result = $this->cache_delete( $key, $group );

        if ( false === $result ) {
            $ctx->out->error( 'The object was not deleted.' );
        }

        $this->wrote( $ctx, true );
        $ctx->out->success( 'Object deleted.' );
    }

    
    public function flush( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_RECORDED );

        if ( ! empty( $inv->runtime['url'] ) && $this->is_multisite() ) {
            $ctx->out->warning( 'Flushing the cache may affect all sites in a multisite installation, depending on the implementation of the object cache.' );
        }

        $value = $this->cache_flush();
        if ( false === $value ) {
            $ctx->out->error( 'The object cache could not be flushed.' );
        }

        if ( $this->ext_object_cache() ) {
            $ctx->mark_changed();
            $ctx->out->warning( self::FLUSH_RESET );
        } else {
            $ctx->note( self::NOTE_RUNTIME );
        }
        $ctx->out->success( 'The cache was flushed.' );
    }

    
    public function get( Invocation $inv, Context $ctx ): void {
        list( $key, $group ) = $this->positionals( $inv, 2 );
        $this->guard( (string) $group, $ctx );

        $value = $this->cache_get( $key, $group );

        if ( false === $value ) {
            $ctx->out->error( "Object with key '$key' and group '$group' not found." );
        }

        Values::print_value( $ctx->out, $this->mask( (string) $key, (string) $group, $value, $ctx ), $inv->assoc );
    }

    
    public function replace( Invocation $inv, Context $ctx ): void {
        list( $key, $value, $group, $expiration ) = $this->positionals( $inv, 4 );
        $this->guard( (string) $group, $ctx );
        $ctx->not_captured( self::NOT_RECORDED );

        $before = $this->ext_object_cache() ? $this->cache_get_found( $key, $group ) : array( false, false );
        $result = $this->cache_replace( $key, $value, $group, $expiration );

        if ( false === $result ) {
            $ctx->out->error( "Could not replace object '$key' in group '$group'. Does it not exist?" );
        }

        $this->wrote( $ctx, ! ( $before[1] && $before[0] === $value ) );
        $ctx->out->success( "Replaced object '$key' in group '$group'." );
    }

    
    public function set( Invocation $inv, Context $ctx ): void {
        list( $key, $value, $group, $expiration ) = $this->positionals( $inv, 4 );
        $this->guard( (string) $group, $ctx );
        $ctx->not_captured( self::NOT_RECORDED );

        $before = $this->ext_object_cache() ? $this->cache_get_found( $key, $group ) : array( false, false );
        $result = $this->cache_set( $key, $value, $group, $expiration );

        if ( false === $result ) {
            $ctx->out->error( "Could not add object '$key' in group '$group'." );
        }

        $this->wrote( $ctx, ! ( $before[1] && $before[0] === $value ) );
        $ctx->out->success( "Set object '$key' in group '$group'." );
    }

    
    public function type( Invocation $inv, Context $ctx ): void {
        $message = $this->cache_type();
        $ctx->out->line( $message );
    }

    



    public function supports( Invocation $inv, Context $ctx ): void {
        list( $feature ) = $this->positionals( $inv, 1 );

        if ( ! $this->supports_exists() ) {
            $ctx->out->error( 'Checking cache features is only available in WordPress 6.1 and higher' );
        }

        $supports = $this->cache_supports( (string) $feature );

        if ( $supports ) {
            $ctx->out->halt( 0 );
        }
        $ctx->out->halt( 1 );
    }

    
    public function flush_group( Invocation $inv, Context $ctx ): void {
        list( $group ) = $this->positionals( $inv, 1 );
        $this->guard( (string) $group, $ctx );
        $ctx->not_captured( self::NOT_RECORDED );

        if ( ! $this->supports_exists() || ! $this->cache_supports( 'flush_group' ) ) {
            $ctx->out->error( 'Group flushing is not supported.' );
        }

        if ( ! $this->cache_flush_group( $group ) ) {
            $ctx->out->error( "Cache group '$group' was not flushed." );
        }
        $this->wrote( $ctx, true );
        $ctx->out->success( "Cache group '$group' was flushed." );
    }

    
    public function pluck( Invocation $inv, Context $ctx ): void {
        $args  = array_values( $inv->args );
        $key   = $args[0];
        $group = Read_Value::flag( $inv->assoc, 'group' );
        $this->guard( (string) $group, $ctx );

        $value = $this->cache_get( $key, $group );

        if ( false === $value ) {
            $ctx->out->warning( "No object found for the key '$key' in group '$group'" );
            return;
        }

        $key_path = Meta_Command::key_path( array_slice( $args, 1 ) );

        try {
            $value = Meta_Command::pluck_value( $value, $key_path );
        } catch ( \Exception $e ) {
            $ctx->note( sprintf( self::NOTE_NO_PATH, $key, $group ) );
            $ctx->out->halt( 1 );
        }

        Values::print_value( $ctx->out, $this->mask( (string) $key, (string) $group, $value, $ctx ), $inv->assoc );
    }

    
    public function patch( Invocation $inv, Context $ctx ): void {
        $args       = array_values( $inv->args );
        $action     = (string) $args[0];
        $key        = $args[1];
        $group      = Read_Value::flag( $inv->assoc, 'group' );
        $expiration = Read_Value::flag( $inv->assoc, 'expiration' );
        $this->guard( (string) $group, $ctx );

        $key_path = Meta_Command::key_path( array_slice( $args, 2 ) );

        if ( 'delete' === $action ) {
            $patch_value = null;
        } else {
            
            
            if ( count( $key_path ) > 1 ) {
                $patch_value = Read_Value::read( $ctx->out, array_pop( $key_path ), $inv->assoc );
            } else {
                $patch_value = null;
            }

            if ( null === $patch_value ) {
                $ctx->out->error( 'Please provide value to update.' );
            }
        }

        
        $old_value     = $this->cache_get( $key, $group );
        $current_value = $old_value;
        if ( is_object( $old_value ) ) {
            $current_value = clone $old_value;
        }

        try {
            $patched_value = Meta_Command::patch_value( $action, $current_value, $key_path, $patch_value );
        } catch ( \Exception $e ) {
            $ctx->out->error( $e->getMessage() );
            return;
        }

        $ctx->not_captured( self::NOT_RECORDED );
        if ( $patched_value === $old_value ) {
            $ctx->out->success( "Value passed for cache key '$key' is unchanged." );
        } else {
            $success = $this->cache_set( $key, $patched_value, $group, $expiration );
            if ( $success ) {
                $this->wrote( $ctx, true );
                $ctx->out->success( "Updated cache key '$key'." );
            } else {
                $ctx->out->error( "Could not update cache key '$key'." );
            }
        }
    }

    
    
    

    
    public function describe_store( Invocation $inv ): array {
        list( $key, $value, $group, $expiration ) = $this->positionals( $inv, 4 );
        $verb = array(
            'cache add'     => 'add',
            'cache set'     => 'set',
            'cache replace' => 'replace with',
        );
        return array(
            sprintf(
                "Object cache key '%s' in group '%s': %s → %s %s, %s",
                $key,
                $group,
                $this->current( (string) $key, (string) $group ),
                isset( $verb[ $inv->command ] ) ? $verb[ $inv->command ] : 'set',
                Option_Handler::preview( (string) $key, $value ),
                self::expiry_text( $expiration )
            ),
        );
    }

    
    public function describe_step( Invocation $inv ): array {
        list( $key, $offset, $group ) = $this->positionals( $inv, 3 );
        return array(
            sprintf(
                "Object cache key '%s' in group '%s': %s → %s by %s",
                $key,
                $group,
                $this->current( (string) $key, (string) $group ),
                'cache incr' === $inv->command ? 'increment' : 'decrement',
                is_scalar( $offset ) ? (string) $offset : '1'
            ),
        );
    }

    
    public function describe_delete( Invocation $inv ): array {
        list( $key, $group ) = $this->positionals( $inv, 2 );
        return array( sprintf( "Object cache key '%s' in group '%s': %s → delete", $key, $group, $this->current( (string) $key, (string) $group ) ) );
    }

    
    public function describe_flush( Invocation $inv ): array {
        if ( ! $this->ext_object_cache() ) {
            return array( 'Object cache ' . self::RUNTIME_STATE . ': every group → flush' );
        }
        return array( sprintf( "Object cache (%s): every key of every group, shared by every site that uses the same backend → flush, ending every MCP session and resetting the plugin's rate-limit and lockout counters", $this->cache_type() ) );
    }

    
    public function describe_flush_group( Invocation $inv ): array {
        list( $group ) = $this->positionals( $inv, 1 );
        $where = $this->ext_object_cache() ? '' : ' ' . self::RUNTIME_STATE;
        return array( sprintf( "Object cache group '%s'%s: every key → flush", $group, $where ) );
    }

    
    public function describe_patch( Invocation $inv ): array {
        $args   = array_values( $inv->args );
        $action = (string) $args[0];
        $key    = (string) $args[1];
        $group  = (string) Read_Value::flag( $inv->assoc, 'group', 'default' );
        $path   = Meta_Command::key_path( array_slice( $args, 2 ) );
        $value  = 'delete' === $action ? null : ( count( $path ) > 1 ? array_pop( $path ) : null );
        if ( null !== $value && 'json' === Read_Value::flag( $inv->assoc, 'format' ) ) {
            $decoded = json_decode( (string) $value, true );
            $value   = null === $decoded ? $value : $decoded;
        }
        $effect = 'delete' === $action ? 'delete ' . implode( '.', $path ) : $action . ' ' . implode( '.', $path ) . ' = ' . Option_Handler::preview( $key, $value );
        return array( sprintf( "Object cache key '%s' in group '%s': %s → %s", $key, $group, $this->current( $key, $group ), $effect ) );
    }

    
    
    

    









    public static function cache_type_of( ?bool $using_ext, ?object $object_cache ): string {
        if ( empty( $using_ext ) ) {
            return 'Default';
        }
        $message = self::memcache_type( $object_cache )
            ?? self::extension_cache_type( $object_cache )
            ?? self::redis_type( $object_cache )
            ?? self::other_cache_type( $object_cache );
        return $message ?? 'Unknown';
    }

    





    private static function memcache_type( ?object $object_cache ): ?string {
        
        if ( isset( $object_cache->m ) && $object_cache->m instanceof \Memcached ) {
            return 'Memcached';
        }
        
        if ( ! isset( $object_cache->mc ) ) {
            return null;
        }
        $is_memcache = true;
        foreach ( $object_cache->mc as $bucket ) {
            if ( ! $bucket instanceof \Memcache && ! $bucket instanceof \Memcached ) {
                $is_memcache = false;
            }
        }
        return $is_memcache ? 'Memcache' : 'Unknown';
    }

    




    private static function extension_cache_type( ?object $object_cache ): ?string {
        
        if ( $object_cache instanceof \XCache_Object_Cache ) {
            return 'Xcache';
        }
        
        if ( class_exists( 'WinCache_Object_Cache' ) ) {
            return 'WinCache';
        }
        
        if ( class_exists( 'APC_Object_Cache' ) ) {
            return 'APC';
        }
        return null;
    }

    




    private static function redis_type( ?object $object_cache ): ?string {
        
        if ( isset( $object_cache->redis ) && $object_cache->redis instanceof \Redis ) {
            return 'Redis';
        }
        
        if ( method_exists( $object_cache, 'redis_instance' ) && method_exists( $object_cache, 'redis_status' ) ) {
            return 'Redis';
        }
        
        if ( method_exists( $object_cache, 'config' ) && method_exists( $object_cache, 'connection' ) ) {
            return 'Redis';
        }
        return null;
    }

    





    private static function other_cache_type( ?object $object_cache ): ?string {
        
        if ( isset( $object_cache->lcache ) && $object_cache->lcache instanceof \LCache\Integrated ) {
            return 'WP LCache';
        }
        if ( function_exists( 'w3_instance' ) ) {
            $config = \w3_instance( 'W3_Config' );
            return $config->get_boolean( 'objectcache.enabled' ) ? 'W3TC ' . $config->get_string( 'objectcache.engine' ) : 'Unknown';
        }
        return null;
    }

    
    
    

    








    protected function seam( string $name, array $args, callable $real ) {
        return call_user_func_array( $real, $args );
    }

    protected function ext_object_cache(): bool {
        return (bool) $this->seam(
            'wp_using_ext_object_cache',
            array(),
            static function () {
                return \wp_using_ext_object_cache();
            }
        );
    }

    protected function is_multisite(): bool {
        return (bool) $this->seam(
            'is_multisite',
            array(),
            static function () {
                return \is_multisite();
            }
        );
    }

    




    protected function cache_add( string $key, $value, string $group, $expiration ): bool {
        return (bool) $this->seam(
            'wp_cache_add',
            array( $key, $value, $group, $expiration ),
            static function ( $key, $value, $group, $expiration ) {
                return \wp_cache_add( $key, $value, $group, $expiration );
            }
        );
    }

    





    protected function cache_get( string $key, $group ) {
        return $this->seam(
            'wp_cache_get',
            array( $key, $group ),
            static function ( $key, $group ) {
                return \wp_cache_get( $key, $group );
            }
        );
    }

    





    protected function cache_get_found( string $key, $group ): array {
        $result = $this->seam(
            'wp_cache_get_found',
            array( $key, $group ),
            static function ( $key, $group ) {
                $found = false;
                $value = \wp_cache_get( $key, $group, false, $found );
                return array( $value, (bool) $found );
            }
        );
        return is_array( $result ) && array_key_exists( 0, $result ) ? array( $result[0], ! empty( $result[1] ) ) : array( false, false );
    }

    




    protected function cache_set( string $key, $value, $group, $expiration ): bool {
        return (bool) $this->seam(
            'wp_cache_set',
            array( $key, $value, $group, $expiration ),
            static function ( $key, $value, $group, $expiration ) {
                return \wp_cache_set( $key, $value, $group, $expiration );
            }
        );
    }

    




    protected function cache_replace( string $key, $value, string $group, $expiration ): bool {
        return (bool) $this->seam(
            'wp_cache_replace',
            array( $key, $value, $group, $expiration ),
            static function ( $key, $value, $group, $expiration ) {
                return \wp_cache_replace( $key, $value, $group, $expiration );
            }
        );
    }

    protected function cache_delete( string $key, string $group ): bool {
        return (bool) $this->seam(
            'wp_cache_delete',
            array( $key, $group ),
            static function ( $key, $group ) {
                return \wp_cache_delete( $key, $group );
            }
        );
    }

    






    protected function cache_step( string $which, string $key, $offset, string $group ) {
        return $this->seam(
            'decr' === $which ? 'wp_cache_decr' : 'wp_cache_incr',
            array( $key, $offset, $group ),
            'decr' === $which
                ? static function ( $key, $offset, $group ) {
                    return \wp_cache_decr( $key, $offset, $group );
                }
                : static function ( $key, $offset, $group ) {
                    return \wp_cache_incr( $key, $offset, $group );
                }
        );
    }

    protected function cache_flush(): bool {
        return (bool) $this->seam(
            'wp_cache_flush',
            array(),
            static function () {
                return \wp_cache_flush();
            }
        );
    }

    
    protected function supports_exists(): bool {
        return (bool) $this->seam(
            'function_exists:wp_cache_supports',
            array(),
            static function () {
                return function_exists( 'wp_cache_supports' );
            }
        );
    }

    protected function cache_supports( string $feature ): bool {
        return (bool) $this->seam(
            'wp_cache_supports',
            array( $feature ),
            static function ( $feature ) {
                return \wp_cache_supports( $feature );
            }
        );
    }

    protected function cache_flush_group( string $group ): bool {
        return (bool) $this->seam(
            'wp_cache_flush_group',
            array( $group ),
            static function ( $group ) {
                return \wp_cache_flush_group( $group );
            }
        );
    }

    protected function cache_type(): string {
        return (string) $this->seam(
            'wp_get_cache_type',
            array(),
            static function () {
                global $_wp_using_ext_object_cache, $wp_object_cache;
                return self::cache_type_of( $_wp_using_ext_object_cache, $wp_object_cache );
            }
        );
    }

    
    
    

    




    private function guard( string $group, Context $ctx ): void {
        $refusal = Self_Protection::cache_group( $group );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
    }

    



    private function arg( Invocation $inv, int $index, string $default ): string {
        return array_key_exists( $index, $inv->args ) ? $inv->args[ $index ] : $default;
    }

    






    private function positionals( Invocation $inv, int $count ): array {
        $out = array();
        for ( $i = 0; $i < $count; $i++ ) {
            $out[] = array_key_exists( $i, $inv->args ) ? $inv->args[ $i ] : null;
        }
        return $out;
    }

    




    private function step( Invocation $inv, Context $ctx, string $which, string $failure ): void {
        list( $key, $offset, $group ) = $this->positionals( $inv, 3 );
        $this->guard( (string) $group, $ctx );
        $ctx->not_captured( self::NOT_RECORDED );

        $before = $this->ext_object_cache() ? $this->cache_get_found( $key, $group ) : array( false, false );
        $value  = $this->cache_step( $which, $key, $offset, $group );

        if ( false === $value ) {
            $ctx->out->error( $failure );
        }

        $this->wrote( $ctx, ! ( $before[1] && $before[0] === $value ) );
        Values::print_value( $ctx->out, $this->mask( (string) $key, (string) $group, $value, $ctx ), $inv->assoc );
    }

    



    private function wrote( Context $ctx, bool $moved ): void {
        if ( ! $this->ext_object_cache() ) {
            $ctx->note( self::NOTE_RUNTIME );
            return;
        }
        if ( $moved ) {
            $ctx->mark_changed();
        }
    }

    






    private function mask( string $key, string $group, $value, Context $ctx ) {
        if ( Option_Handler::key_is_secret( $key ) ) {
            $ctx->redacted( "value of cache key '{$key}' in group '{$group}'" );
            return Option_Handler::MASK;
        }
        return $value;
    }

    
    private function current( string $key, string $group ): string {
        if ( ! $this->ext_object_cache() ) {
            return self::RUNTIME_STATE;
        }
        $found = $this->cache_get_found( $key, $group );
        return $found[1] ? Option_Handler::preview( $key, $found[0] ) : '(not set)';
    }

    
    private static function expiry_text( $expiration ): string {
        $seconds = is_numeric( $expiration ) ? (int) $expiration : 0;
        return 0 === $seconds ? 'no expiry' : sprintf( 'expires %d seconds after the write', $seconds );
    }
}
