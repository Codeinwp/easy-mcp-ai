<?php

namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Diagnostics {

    const CACHE_OPTION = 'easy_mcp_ai_diagnostics_last_run';

    const STALE_AFTER = DAY_IN_SECONDS;

    const CONNECTION_GROUPS = array( 'a1', 'a9', 'a10' );

    private static $checks = array();

    private static $results = null;

    public static function register( $cb, $id = '', $deep_only = false ) {
        self::$checks[] = array( 'cb' => $cb, 'id' => (string) $id, 'deep_only' => (bool) $deep_only );
    }

    public static function has_registered_checks() {
        return ! empty( self::$checks );
    }

    public static function register_core_checks( $tool_registry = null ) {
        self::register( array( Check_Transport::class, 'run' ), 'a' );
        self::register( array( Check_Notices::class, 'run' ), 'a' );
        self::register( array( Check_Session::class, 'run' ), 'b' );
        self::register( array( Check_Schema::class, 'run' ), 'c' );
        self::register( function () use ( $tool_registry ) {
            return Check_Tool_Visibility::run( $tool_registry );
        }, 'd' );
        self::register( array( Check_Header_Probe::class, 'run' ), 'a1', true );
        self::register( array( Check_Conflicts::class, 'run' ), 'e' );
        self::register( array( Check_Config::class, 'run' ), 'f' );
        self::register( array( Check_Environment::class, 'run' ), 'g' );
        self::register( array( Check_Edge_Block::class, 'run' ), 'a9', true );
        self::register( array( Check_Discovery::class, 'run' ), 'a10', true );
        self::register( array( Check_Observability::class, 'run' ), 'h', true );
        self::register( array( Check_Multisite::class, 'run' ), 'i', true );
    }

    public static function run( $deep = false, $groups = null ) {
        $results  = array();
        $previous = self::stored_report();
        $now      = time();

        foreach ( self::$checks as $check ) {
            $deep_only = ! empty( $check['deep_only'] );
            $group     = '' !== $check['id'] ? $check['id'] : 'unknown';

            if ( self::is_kept( $deep_only, $group, $deep, $groups ) ) {
                foreach ( self::kept_or_placeholder( $group, $previous ) as $kept ) {
                    $results[] = $kept;
                }
                continue;
            }

            try {
                $produced = call_user_func( $check['cb'] );
            } catch ( \Throwable $e ) {
                $failed = Diagnostic_Result::unknown(
                    $group,
                    Diagnostic_Result::TIER_INFO,
                    __( 'Check could not run', 'easy-mcp-ai' ),
                    self::redact_message( $e->getMessage() )
                );
                $results[] = self::stamp( $failed, $deep_only, $group, $now );
                continue;
            }

            if ( ! is_array( $produced ) ) {
                continue;
            }

            foreach ( $produced as $result ) {
                if ( $result instanceof Diagnostic_Result ) {
                    $results[] = self::stamp( $result, $deep_only, $group, $now );
                }
            }
        }

        self::$results = $results;
        self::persist( $results, self::invalidated_at( $previous ) );

        return self::redact_cross_tenant( $results );
    }

    private static function is_kept( $deep_only, $group, $deep, $groups ) {
        if ( ! $deep_only ) {
            return false;
        }
        return ! $deep || ( null !== $groups && ! in_array( $group, (array) $groups, true ) );
    }

    private static function stamp( Diagnostic_Result $result, $deep_only, $group, $now ) {
        return $deep_only ? $result->with_measurement( $group, $now ) : $result;
    }

    private static function invalidated_at( array $report ) {
        return isset( $report['invalidated_at'] ) ? (int) $report['invalidated_at'] : 0;
    }

    private static function deferred_placeholder( $group, $detail, array $evidence, $measured_at ) {
        return Diagnostic_Result::unknown(
            $group,
            Diagnostic_Result::TIER_INFO,
            __( 'Detailed check', 'easy-mcp-ai' ),
            $detail,
            array( 'deferred' => true ) + $evidence
        )->with_measurement( $group, $measured_at );
    }

    private static function stored_report() {
        $cache = \get_option( self::CACHE_OPTION, array() );
        return is_array( $cache ) ? $cache : array();
    }

    private static function kept_or_placeholder( $group, array $previous ) {
        $kept = array();
        if ( ! empty( $previous['results'] ) && is_array( $previous['results'] ) ) {
            foreach ( $previous['results'] as $row ) {
                if ( ! is_array( $row ) || ! isset( $row['group'] ) || $group !== $row['group'] || empty( $row['measured_at'] ) ) {
                    continue;
                }
                $kept[] = Diagnostic_Result::from_array( $row );
            }
        }

        if ( empty( $kept ) ) {
            return array(
                self::deferred_placeholder(
                    $group,
                    __( 'Not run — press Re-run checks to include the slower checks.', 'easy-mcp-ai' ),
                    array(),
                    null
                ),
            );
        }

        $invalidated_at = self::invalidated_at( $previous );
        $measured_at    = (int) $kept[0]->measured_at();
        if ( $invalidated_at > 0 && $measured_at <= $invalidated_at ) {
            return array(
                self::deferred_placeholder(
                    $group,
                    __( 'Out of date — the site address or permalinks changed after this was last checked. Press Re-run checks to check it again.', 'easy-mcp-ai' ),
                    array( 'expired' => true ),
                    $measured_at
                ),
            );
        }

        return $kept;
    }

    public static function connection_checks_due() {
        $report         = self::stored_report();
        $invalidated_at = self::invalidated_at( $report );
        $oldest         = array();
        if ( ! empty( $report['results'] ) && is_array( $report['results'] ) ) {
            foreach ( $report['results'] as $row ) {
                if ( ! is_array( $row ) || ! isset( $row['group'] ) || ! in_array( $row['group'], self::CONNECTION_GROUPS, true ) ) {
                    continue;
                }
                $at = empty( $row['measured_at'] ) ? 0 : (int) $row['measured_at'];
                $oldest[ $row['group'] ] = isset( $oldest[ $row['group'] ] ) ? min( $oldest[ $row['group'] ], $at ) : $at;
            }
        }
        foreach ( self::CONNECTION_GROUPS as $group ) {
            $at = isset( $oldest[ $group ] ) ? $oldest[ $group ] : 0;
            if ( 0 === $at || ( time() - $at ) >= self::STALE_AFTER || $at <= $invalidated_at ) {
                return true;
            }
        }
        return false;
    }

    public static function maybe_run() {
        if ( function_exists( 'current_user_can' ) && ! \current_user_can( 'manage_options' ) ) {
            return self::cached();
        }

        if ( self::is_stale() ) {
            return self::run();
        }
        return self::cached();
    }

    public static function redact_message( $message ) {
        $message = (string) $message;

        $message = preg_replace( '#(?:/[^/\s:()"\']+){2,}/([^/\s:()"\']+)#', '…/$1', $message );
        $message = preg_replace( '#[A-Za-z]:\\\\(?:[^\\\\\s:()"\']+\\\\)+([^\\\\\s:()"\']+)#', '…\\\\$1', $message );

        return $message;
    }

    public static function cached() {
        if ( null !== self::$results ) {
            return self::redact_cross_tenant( self::$results );
        }

        $cache = \get_option( self::CACHE_OPTION, array() );
        if ( ! is_array( $cache ) || empty( $cache['results'] ) || ! is_array( $cache['results'] ) ) {
            return array();
        }

        $results = array();
        foreach ( $cache['results'] as $row ) {
            $results[] = Diagnostic_Result::from_array( $row );
        }

        self::$results = $results;
        return self::redact_cross_tenant( $results );
    }

    private static function redact_cross_tenant( array $results ) {
        $is_multisite = function_exists( 'is_multisite' ) && \is_multisite();
        if ( ! $is_multisite ) {
            return $results;
        }

        $have_gate = class_exists( __NAMESPACE__ . '\\Check_Multisite' );
        $may_read  = $have_gate ? Check_Multisite::may_read_network() : false;

        if ( ! self::must_withhold_network( $is_multisite, $have_gate, $may_read ) ) {
            return $results;
        }

        return self::withhold_network_rows( $results, $have_gate ? true : null );
    }

    public static function must_withhold_network( $is_multisite, $gate_available, $may_read ) {
        if ( ! $is_multisite ) {
            return false;
        }
        if ( ! $gate_available ) {
            return true;
        }
        return ! $may_read;
    }

    private static function withhold_network_rows( array $results, $have_gate ) {
        $out = array();
        foreach ( $results as $result ) {
            if ( 'i1' !== $result->id() ) {
                $out[] = $result;
                continue;
            }

            $out[] = ( true === $have_gate )
                ? Check_Multisite::evaluate_per_site_tables(
                    true,
                    null,
                    0,
                    0,
                    Check_Multisite::REASON_NOT_PERMITTED
                )
                : Diagnostic_Result::unknown(
                    'i1',
                    Diagnostic_Result::TIER_WARNING,
                    __( 'Every site on the network is set up', 'easy-mcp-ai' ),
                    __( 'Only a network administrator can check the other sites on this network.', 'easy-mcp-ai' ),
                    array( 'not_permitted' => true )
                );
        }

        return $out;
    }

    public static function last_run_at() {
        $cache = \get_option( self::CACHE_OPTION, array() );
        if ( ! is_array( $cache ) || ! isset( $cache['generated_at'] ) ) {
            return null;
        }
        return (int) $cache['generated_at'];
    }

    public static function is_stale() {
        $at = self::last_run_at();
        if ( null === $at ) {
            return true;
        }
        return ( time() - $at ) >= self::STALE_AFTER;
    }

    public static function invalidate() {
        $cache = \get_option( self::CACHE_OPTION, array() );
        if ( ! is_array( $cache ) || empty( $cache ) ) {
            return;
        }

        $cache['generated_at'] = 0;
        $cache['invalidated_at'] = time();
        \update_option( self::CACHE_OPTION, $cache, false );
    }

    const INVALIDATING_OPTIONS = array(
        'permalink_structure',
        'siteurl',
        'home',
    );

    public static function register_invalidation() {
        foreach ( self::INVALIDATING_OPTIONS as $option ) {
            \add_action( 'update_option_' . $option, array( __CLASS__, 'invalidate' ), 10, 0 );
            \add_action( 'add_option_' . $option, array( __CLASS__, 'invalidate' ), 10, 0 );
        }
    }

    public static function blockers() {
        return array_values( array_filter( self::cached(), function ( $r ) {
            return $r->renders_in_notice();
        } ) );
    }

    public static function summary() {
        $counts = array( 'total' => 0, 'pass' => 0, 'warn' => 0, 'fail' => 0, 'unknown' => 0, 'deferred' => 0 );

        foreach ( self::cached() as $r ) {
            $counts['total']++;
            $evidence = $r->evidence();
            if ( is_array( $evidence ) && ! empty( $evidence['deferred'] ) ) {
                $counts['deferred']++;
            }
            switch ( $r->status() ) {
                case Diagnostic_Result::STATUS_PASS:
                    $counts['pass']++;
                    break;
                case Diagnostic_Result::STATUS_WARN:
                    $counts['warn']++;
                    break;
                case Diagnostic_Result::STATUS_FAIL:
                    $counts['fail']++;
                    break;
                default:
                    $counts['unknown']++;
            }
        }

        return $counts;
    }

    private static function persist( array $results, $invalidated_at = 0 ) {
        $saved_since   = self::invalidated_at( self::stored_report() );
        $changed_since = $saved_since > (int) $invalidated_at;
        $payload       = array(
            'generated_at' => $changed_since ? 0 : time(),
            'results'      => array_map( function ( $r ) { return $r->to_array(); }, $results ),
        );
        $invalidated_at = max( (int) $invalidated_at, $saved_since );
        if ( $invalidated_at > 0 ) {
            $payload['invalidated_at'] = $invalidated_at;
        }

        \update_option( self::CACHE_OPTION, $payload, false );

        $stored = \get_option( self::CACHE_OPTION, array() );
        return is_array( $stored ) && isset( $stored['generated_at'] ) && $stored['generated_at'] === $payload['generated_at'];
    }

    public static function reset_for_tests() {
        self::$checks  = array();
        self::$results = null;
    }

    public static function reset_for_tests_preserving_registry() {
        self::$results = null;
    }
}
