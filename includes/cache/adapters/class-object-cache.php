<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;
use Easy_MCP_AI\Cache\Vendor_Calls;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
























final class Object_Cache extends Cache_Adapter {

    
    const HEADER_NAMES = array(
        'Redis Object Cache Drop-In'               => 'redis-cache',
        'Hummingbird Redis Object Cache'           => 'hummingbird',
        'Docket Cache Drop-in'                     => 'docket-cache',
        'SQLite Object Cache (Drop-in)'            => 'sqlite-object-cache',
        'LiteSpeed Cache - Object Cache (Drop-in)' => 'litespeed',
        'APCu Objects Cache Handler'               => 'apcu-manager',
        'Object Cache 4 everyone - Memcached'      => 'oc4e',
        'Object Cache 4 everyone - Disk'           => 'oc4e',
    );

    
    const LABELS = array(
        'redis-cache'         => 'Redis Object Cache',
        'object-cache-pro'    => 'Object Cache Pro',
        'wp-redis'            => 'WP Redis',
        'hummingbird'         => 'Hummingbird Redis object cache',
        'docket-cache'        => 'Docket Cache',
        'sqlite-object-cache' => 'SQLite Object Cache',
        'memcached'           => 'Memcached object cache',
        'sg-memcached'        => 'SiteGround Speed Optimizer Memcached',
        'litespeed'           => 'LiteSpeed Cache object cache',
        'w3tc'                => 'W3 Total Cache object cache',
        'apcu-manager'        => 'APCu Manager object cache',
        'oc4e'                => 'Object Cache 4 Everyone',
        'atec'                => 'atec Cache APCu',
        'unknown'             => 'Persistent object cache',
    );

    
    private $backend = null;

    
    private $headers = null;

    





    public function with_calls( Vendor_Calls $calls ): Cache_Adapter {
        $this->backend = null;
        $this->headers = null;
        return parent::with_calls( $calls );
    }

    public function id(): string {
        return 'object-cache';
    }

    




    public function label(): string {
        return self::LABELS[ $this->backend() ];
    }

    public function kind(): string {
        return self::KIND_OBJECT;
    }

    public function tier(): int {
        return self::TIER_OBJECT;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE );
    }

    public function is_present(): bool {
        $calls = $this->calls();
        if ( $calls->using_ext_object_cache() ) {
            return true;
        }
        $path = $this->drop_in_path();
        return '' !== $path && $calls->file_exists( $path );
    }

    public function also_clears( Purge_Request $request ): array {
        return array( self::ALSO_OBJECT_CACHE );
    }

    







    public function reaches_network( Purge_Request $request ): bool {
        return $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( ! $calls->function_exists( 'wp_cache_flush' ) ) {
            return $this->helper_missing( 'wp_cache_flush()' );
        }
        if ( $calls->flush_object_cache() || 'memcached' === $this->backend() ) {
            return $this->sent( sprintf( '%s flushed the whole object cache.', $this->label() ) );
        }
        return $this->layer( Layer_Result::FAILED, array( 'detail' => $this->failure_detail() ) );
    }

    




    private function failure_detail(): string {
        $detail    = sprintf( '%s reported that the flush failed (wp_cache_flush() returned false).', $this->label() );
        $backend   = $this->backend();
        $selective = ( 'redis-cache' === $backend && $this->redis_selective() ) || ( 'hummingbird' === $backend && $this->truthy_constant( 'WP_REDIS_SALT' ) );
        return $selective ? $detail . ' Its selective flush also returns false when it found no key to delete.' : $detail;
    }

    







    private function redis_selective(): bool {
        $prefix = $this->calls()->constant( 'WP_REDIS_PREFIX' );
        return is_scalar( $prefix ) && (bool) trim( (string) $prefix ) && $this->truthy_constant( 'WP_REDIS_SELECTIVE_FLUSH' );
    }

    



    private function truthy_constant( string $name ): bool {
        return ! empty( $this->calls()->constant( $name ) );
    }

    




    private function cache_object(): ?object {
        $cache = $this->calls()->global_value( 'wp_object_cache' );
        return is_object( $cache ) ? $cache : null;
    }

    




    private function backend(): string {
        if ( null === $this->backend ) {
            $by_header     = $this->backend_by_header();
            $this->backend = '' !== $by_header ? $by_header : $this->backend_by_symbol();
        }
        return $this->backend;
    }

    




    private function backend_by_header(): string {
        $headers = $this->headers();
        if ( '' !== $headers['w3tc'] ) {
            return 'w3tc';
        }
        if ( false !== stripos( $headers['oc_name'], 'atec' ) ) {
            return 'atec';
        }
        if ( 'Memcached' === $headers['name'] ) {
            return false !== strpos( $headers['description'], 'SGO' ) ? 'sg-memcached' : 'memcached';
        }
        return isset( self::HEADER_NAMES[ $headers['name'] ] ) ? self::HEADER_NAMES[ $headers['name'] ] : '';
    }

    







    private function backend_by_symbol(): string {
        $calls   = $this->calls();
        $symbols = array(
            'litespeed'        => 'LSCWP_OBJECT_CACHE',
            'wp-redis'         => 'WP_REDIS_OBJECT_CACHE',
            'object-cache-pro' => 'RedisCachePro\\Version',
            'atec'             => 'ATEC_OC_VERSION',
        );
        if ( 'SG_OPTIMIZER_CACHE_KEY_SALT' === $calls->constant( 'WP_CACHE_KEY_SALT' ) ) {
            return 'sg-memcached';
        }
        foreach ( $symbols as $backend => $constant ) {
            if ( $this->truthy_constant( $constant ) ) {
                return $backend;
            }
        }
        if ( $this->truthy_constant( 'ObjectCachePro\\Version' ) ) {
            return 'object-cache-pro';
        }
        $cache = $this->cache_object();
        return null !== $cache && $calls->method_exists( $cache, 'register_cache' ) ? 'w3tc' : 'unknown';
    }

    




    private function headers(): array {
        if ( null === $this->headers ) {
            $this->headers = $this->read_headers();
        }
        return $this->headers;
    }

    


    private function read_headers(): array {
        $headers = array(
            'name'        => '',
            'description' => '',
            'w3tc'        => '',
            'oc_name'     => '',
        );
        $calls   = $this->calls();
        $path    = $this->drop_in_path();
        if ( '' === $path || ! $calls->file_exists( $path ) || ! $calls->function_exists( 'get_file_data' ) ) {
            return $headers;
        }
        $data = $calls->call( 'get_file_data', array( $path, array( 'name' => 'Plugin Name', 'description' => 'Description', 'w3tc' => 'ObjectCache Version', 'oc_name' => 'OC Name' ) ) );
        foreach ( array_keys( $headers ) as $key ) {
            $headers[ $key ] = is_array( $data ) && isset( $data[ $key ] ) && is_string( $data[ $key ] ) ? trim( $data[ $key ] ) : '';
        }
        return $headers;
    }

    




    private function drop_in_path(): string {
        $dir = $this->calls()->constant( 'WP_CONTENT_DIR' );
        return is_string( $dir ) && '' !== $dir ? rtrim( $dir, '/' ) . '/object-cache.php' : '';
    }
}
