<?php






























namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Session {

    
    const PROBE_TTL = 60;

    


    public static function run() {
        return array(
            self::probe_transient_roundtrip(),
            self::probe_object_cache(),
        );
    }

    




    public static function probe_key() {
        
        
        
        
        
        return 'easy_mcp_ai_diagnostics_probe_' . str_replace( '.', '', uniqid( '', true ) );
    }

    








    public static function evaluate_transient_roundtrip( $written, $sent, $read_back ) {
        $label = __( 'AI sessions can be stored', 'easy-mcp-ai' );

        if ( null === $written ) {
            return Diagnostic_Result::unknown(
                'b1',
                Diagnostic_Result::TIER_BLOCKER,
                $label,
                __( 'Could not test session storage on this request.', 'easy-mcp-ai' )
            );
        }

        if ( ! $written ) {
            return self::session_failure(
                $label,
                __( 'WordPress refused to store a test value. MCP sessions are kept in the same storage, so an AI client cannot stay connected: every session is rejected the moment it is created.', 'easy-mcp-ai' ),
                array( 'write_succeeded' => false )
            );
        }

        if ( false === $read_back || null === $read_back ) {
            return self::session_failure(
                $label,
                __( 'A test value was stored and had already vanished when read back immediately afterwards. MCP sessions are kept the same way, so an AI client connects and is then told its session has expired straight away.', 'easy-mcp-ai' ),
                array( 'write_succeeded' => true, 'read_back' => false )
            );
        }

        
        
        
        
        
        
        
        
        
        
        
        
        
        $matches = is_array( $read_back );

        if ( $matches ) {
            $sent_keys = array_keys( $sent );
            $back_keys = array_keys( $read_back );
            sort( $sent_keys );
            sort( $back_keys );
            $matches = ( $sent_keys === $back_keys );
        }

        if ( $matches ) {
            foreach ( $sent as $key => $value ) {
                // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Deliberate: tolerate int/numeric-string widening, see above.
                if ( $read_back[ $key ] != $value ) {
                    $matches = false;
                    break;
                }
            }
        }

        if ( ! $matches ) {
            return self::session_failure(
                $label,
                __( 'A test value came back changed from how it was stored. Session data is stored the same way, so sessions will be corrupted and connections will drop unpredictably.', 'easy-mcp-ai' ),
                array( 'write_succeeded' => true, 'read_back' => 'altered' )
            );
        }

        return Diagnostic_Result::pass( 'b1', Diagnostic_Result::TIER_BLOCKER, $label, '', array( 'roundtrip' => true ) );
    }

    






    public static function evaluate_object_cache( $dropin_present, $external_in_use, $probe_ok ) {
        $label    = __( 'Object cache is healthy', 'easy-mcp-ai' );
        $evidence = array(
            'dropin_present'  => (bool) $dropin_present,
            'external_in_use' => (bool) $external_in_use,
            'probe_ok'        => $probe_ok,
        );

        
        
        
        if ( ! $dropin_present ) {
            return Diagnostic_Result::pass(
                'b2',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'No persistent object cache is installed; WordPress stores sessions in the database.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        if ( ! $external_in_use ) {
            return Diagnostic_Result::warn(
                'b2',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'An object-cache.php drop-in is installed, but WordPress is not using it. That usually means its backend — Redis or Memcached — is unreachable.', 'easy-mcp-ai' ),
                __( 'Check that the caching service is running, or remove wp-content/object-cache.php to fall back to database storage.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        
        
        
        
        
        
        
        
        if ( null === $probe_ok ) {
            return Diagnostic_Result::unknown(
                'b2',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'An object cache is in use, but testing it raised an error, so its health could not be established.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        if ( false === $probe_ok ) {
            return Diagnostic_Result::warn(
                'b2',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'The persistent object cache accepted a value but did not return it. Anything WordPress stores there — including AI sessions — is being silently discarded.', 'easy-mcp-ai' ),
                __( 'Check the Redis or Memcached service this site uses, or remove wp-content/object-cache.php to fall back to database storage.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::pass( 'b2', Diagnostic_Result::TIER_WARNING, $label, __( 'In use and holding values.', 'easy-mcp-ai' ), $evidence );
    }

    

    






    private static function probe_transient_roundtrip() {
        if ( ! function_exists( 'set_transient' ) || ! function_exists( 'get_transient' ) ) {
            return self::evaluate_transient_roundtrip( null, array(), null );
        }

        $key  = self::probe_key();
        $sent = array( 'probe' => \wp_generate_password( 8, false, false ), 'at' => time() );

        try {
            $written = (bool) \set_transient( $key, $sent, self::PROBE_TTL );

            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            $flushed = false;
            if ( function_exists( 'wp_cache_flush_runtime' ) ) {
                if ( ! function_exists( 'wp_cache_supports' ) || \wp_cache_supports( 'flush_runtime' ) ) {
                    \wp_cache_flush_runtime();
                    $flushed = true;
                }
            }

            $read_back = \get_transient( $key );

            
            
            
            if ( ! $flushed && \function_exists( 'wp_using_ext_object_cache' ) && \wp_using_ext_object_cache() ) {
                return Diagnostic_Result::unknown(
                    'b1',
                    Diagnostic_Result::TIER_BLOCKER,
                    __( 'AI sessions can be stored', 'easy-mcp-ai' ),
                    __( 'This site\'s object cache does not support clearing its per-request memory, so a stored value cannot be re-read independently and session storage could not be proven either way.', 'easy-mcp-ai' ),
                    array( 'flush_runtime_supported' => false )
                );
            }
        } catch ( \Throwable $e ) {
            return self::evaluate_transient_roundtrip( null, array(), null );
        } finally {
            
            
            if ( function_exists( 'delete_transient' ) ) {
                @\delete_transient( $key ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup must never surface as the check's result.
            }
        }

        return self::evaluate_transient_roundtrip( $written, $sent, $read_back );
    }

    private static function probe_object_cache() {
        $dropin  = defined( 'WP_CONTENT_DIR' ) && file_exists( WP_CONTENT_DIR . '/object-cache.php' );
        $in_use  = function_exists( 'wp_using_ext_object_cache' ) ? (bool) \wp_using_ext_object_cache() : false;
        $probe   = null;

        if ( $dropin && $in_use && function_exists( 'wp_cache_set' ) && function_exists( 'wp_cache_get' ) ) {
            $key      = self::probe_key();
            $expected = \wp_generate_password( 8, false, false );
            try {
                \wp_cache_set( $key, $expected, 'easy_mcp_ai', self::PROBE_TTL );
                
                
                
                
                $probe = ( \wp_cache_get( $key, 'easy_mcp_ai', true ) === $expected );
            } catch ( \Throwable $e ) {
                $probe = null;
            } finally {
                if ( function_exists( 'wp_cache_delete' ) ) {
                    @\wp_cache_delete( $key, 'easy_mcp_ai' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- See probe_transient_roundtrip().
                }
            }
        }

        return self::evaluate_object_cache( $dropin, $in_use, $probe );
    }

    private static function session_failure( $label, $detail, array $evidence ) {
        return Diagnostic_Result::fail(
            'b1',
            Diagnostic_Result::TIER_BLOCKER,
            $label,
            $detail,
            __( 'If this site uses Redis or Memcached, check that the service is running. Otherwise remove wp-content/object-cache.php so WordPress falls back to storing sessions in the database.', 'easy-mcp-ai' ),
            $evidence
        );
    }
}
