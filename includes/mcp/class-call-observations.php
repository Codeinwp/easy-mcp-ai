<?php

namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Call_Observations {

    const OPTION = 'easy_mcp_ai_call_observations';

    const VERSION = 1;

    const OFF_SCHEMA = 'off_schema';

    const WROTE_THEN_REFUSED = 'wrote_then_refused';

    const DEFAULT_CAP = 200;

    const MAX_CAP = 100000;

    const CAP_FILTER = 'easy_mcp_ai_call_observations_cap';

    const MAX_MESSAGE = 500;

    const MAX_RPC_ID = 128;

    private static $pending = array();

    private static $shutdown_flush = false;

    public static function record( array $observation ): void {
        try {
            $entry = self::entry( $observation, time() );
            if ( null === $entry ) {
                return;
            }
            self::$pending[] = $entry;
            self::flush();
        } catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }
    }

    public static function flush(): void {
        if ( array() === self::$pending ) {
            return;
        }
        try {
            if ( self::context_active() ) {
                if ( ! self::$shutdown_flush ) {
                    self::$shutdown_flush = true;
                    register_shutdown_function( array( self::class, 'flush' ) );
                }
                return;
            }
            $pending       = self::$pending;
            self::$pending = array();
            set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- swallowed only while the report-only log is written.
                static function () {
                    return true;
                }
            );
            try {
                self::write( $pending );
            } finally {
                restore_error_handler();
            }
        } catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }
    }

    private static function write( array $pending ): void {
        $log = self::read();
        foreach ( $pending as $entry ) {
            if ( 0 === $log['since'] ) {
                $log['since'] = $entry['time'];
            }
            ++$log['counts'][ $entry['kind'] ];
            $log['recent'][] = $entry;
        }
        $cap = self::cap();
        if ( count( $log['recent'] ) > $cap ) {
            $log['recent'] = array_slice( $log['recent'], -$cap );
        }
        \update_option( self::OPTION, $log, false );
    }

    public static function read(): array {
        return self::inspect()['log'];
    }

    public static function inspect(): array {
        $stored = \get_option( self::OPTION, null );
        if ( null === $stored || false === $stored ) {
            return array( 'state' => 'absent', 'log' => self::empty_log() );
        }
        $log = self::validated( $stored );
        return null === $log
            ? array( 'state' => 'malformed', 'log' => self::empty_log() )
            : array( 'state' => 'valid', 'log' => $log );
    }

    public static function clear(): bool {
        self::$pending = array();
        return (bool) \delete_option( self::OPTION );
    }

    public static function cap(): int {
        $cap = \apply_filters( self::CAP_FILTER, self::DEFAULT_CAP );
        if ( is_string( $cap ) && 1 === preg_match( '/^\s*[0-9]+\s*$/', $cap ) ) {
            $cap = (int) $cap;
        }
        if ( is_float( $cap ) && is_finite( $cap ) && floor( $cap ) === $cap ) {
            $cap = (int) $cap;
        }
        if ( ! is_int( $cap ) ) {
            return self::DEFAULT_CAP;
        }
        return max( 1, min( self::MAX_CAP, $cap ) );
    }

    public static function reset_for_tests(): void {
        self::$pending        = array();
        self::$shutdown_flush = false;
    }

    private static function empty_log(): array {
        return array(
            'version' => self::VERSION,
            'since'   => 0,
            'counts'  => array(
                self::OFF_SCHEMA         => 0,
                self::WROTE_THEN_REFUSED => 0,
            ),
            'recent'  => array(),
        );
    }

    private static function validated( $stored ): ?array {
        if ( ! is_array( $stored ) || ! isset( $stored['version'], $stored['since'], $stored['counts'], $stored['recent'] ) ) {
            return null;
        }
        if ( self::VERSION !== $stored['version'] || ! is_int( $stored['since'] ) || $stored['since'] < 0 || ! is_array( $stored['counts'] ) || ! is_array( $stored['recent'] ) ) {
            return null;
        }
        $log = self::empty_log();
        foreach ( array_keys( $log['counts'] ) as $kind ) {
            if ( ! isset( $stored['counts'][ $kind ] ) || ! is_int( $stored['counts'][ $kind ] ) || $stored['counts'][ $kind ] < 0 ) {
                return null;
            }
            $log['counts'][ $kind ] = $stored['counts'][ $kind ];
        }
        $expected = 0;
        foreach ( $stored['recent'] as $key => $raw ) {
            if ( $key !== $expected || ! is_array( $raw ) ) {
                return null;
            }
            ++$expected;
            $entry = self::entry( $raw, null );
            if ( null === $entry ) {
                return null;
            }
            $log['recent'][] = $entry;
        }
        if ( ( array() !== $log['recent'] || array_sum( $log['counts'] ) > 0 ) && 0 === $stored['since'] ) {
            return null;
        }
        $log['since'] = $stored['since'];
        return $log;
    }

    private static function entry( array $raw, ?int $now ): ?array {
        $kind = isset( $raw['kind'] ) ? $raw['kind'] : null;
        if ( self::OFF_SCHEMA !== $kind && self::WROTE_THEN_REFUSED !== $kind ) {
            return null;
        }
        $time = null === $now ? ( isset( $raw['time'] ) ? $raw['time'] : null ) : $now;
        if ( ! is_int( $time ) || $time <= 0 ) {
            return null;
        }
        if ( ! isset( $raw['tool'] ) || ! is_string( $raw['tool'] ) || '' === $raw['tool'] ) {
            return null;
        }
        $rpc_id = array_key_exists( 'rpc_id', $raw ) ? $raw['rpc_id'] : null;
        if ( null !== $rpc_id && ! is_int( $rpc_id ) && ! is_string( $rpc_id ) ) {
            if ( null === $now ) {
                return null;
            }
            $rpc_id = null;
        }
        if ( is_string( $rpc_id ) && strlen( $rpc_id ) > self::MAX_RPC_ID ) {
            if ( null === $now ) {
                return null;
            }
            $rpc_id = 'sha256:' . hash( 'sha256', $rpc_id );
        }
        $audit_id = array_key_exists( 'audit_id', $raw ) ? $raw['audit_id'] : null;
        if ( null !== $now ) {
            $audit_id = is_int( $audit_id ) && $audit_id > 0 ? $audit_id : null;
        } elseif ( null !== $audit_id && ( ! is_int( $audit_id ) || $audit_id <= 0 ) ) {
            return null;
        }
        $entry = array(
            'kind'     => $kind,
            'time'     => $time,
            'tool'     => $raw['tool'],
            'rpc_id'   => $rpc_id,
            'audit_id' => $audit_id,
        );
        if ( self::OFF_SCHEMA === $kind ) {
            $arguments = self::arguments( isset( $raw['arguments'] ) ? $raw['arguments'] : null, null !== $now );
            if ( null === $arguments ) {
                return null;
            }
            $entry['arguments'] = $arguments;
            return $entry;
        }
        $rows = isset( $raw['rows'] ) ? $raw['rows'] : null;
        if ( ! is_int( $rows ) || $rows < 1 ) {
            return null;
        }
        $entry['rows'] = $rows;
        return $entry;
    }

    private static function arguments( $raw, bool $fresh ): ?array {
        if ( ! is_array( $raw ) || array() === $raw ) {
            return null;
        }
        $out      = array();
        $expected = 0;
        foreach ( $raw as $key => $argument ) {
            if ( $key !== $expected || ! is_array( $argument ) ) {
                return null;
            }
            ++$expected;
            foreach ( array( 'name', 'code', 'message' ) as $field ) {
                if ( ! isset( $argument[ $field ] ) || ! is_string( $argument[ $field ] ) ) {
                    return null;
                }
            }
            if ( '' === $argument['name'] ) {
                return null;
            }
            $message = $argument['message'];
            if ( mb_strlen( $message, 'UTF-8' ) > self::MAX_MESSAGE ) {
                if ( ! $fresh ) {
                    return null;
                }
                $message = self::cut( $message );
            }
            $kept = array(
                'name'    => $argument['name'],
                'code'    => $argument['code'],
                'message' => $message,
            );
            if ( isset( $argument['refuse'] ) ) {
                if ( ! in_array( $argument['refuse'], Off_Schema_Refusal::KINDS, true ) ) {
                    return null;
                }
                $kept['refuse'] = $argument['refuse'];
            }
            $out[] = $kept;
        }
        return $out;
    }

    private static function cut( string $text ): string {
        return mb_substr( $text, 0, self::MAX_MESSAGE - 1, 'UTF-8' ) . '…';
    }

    private static function context_active(): bool {
        return class_exists( '\\Easy_MCP_AI\\History\\Change_Context' ) && \Easy_MCP_AI\History\Change_Context::is_active();
    }
}
