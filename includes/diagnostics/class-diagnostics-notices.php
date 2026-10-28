<?php

namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Diagnostics_Notices {

    const LIVE_CHECK_IDS = array( 'a7', 'a8' );

    public static function live_results( $runner = null ) {
        if ( ! is_callable( $runner ) ) {
            $runner = array( Check_Notices::class, 'run' );
        }

        try {
            $results = call_user_func( $runner );
        } catch ( \Throwable $e ) {
            return array();
        }

        return is_array( $results ) ? $results : array();
    }

    public static function with_live( array $results, $runner = null ) {
        $live = array();
        foreach ( self::live_results( $runner ) as $result ) {
            if ( $result instanceof Diagnostic_Result && in_array( $result->id(), self::LIVE_CHECK_IDS, true ) ) {
                $live[ $result->id() ] = $result;
            }
        }

        $out = array();
        foreach ( $results as $result ) {
            if ( $result instanceof Diagnostic_Result && isset( $live[ $result->id() ] ) ) {
                $out[] = $live[ $result->id() ];
                unset( $live[ $result->id() ] );
                continue;
            }
            $out[] = $result;
        }
        foreach ( $live as $result ) {
            if ( $result->renders_in_notice() ) {
                $out[] = $result;
            }
        }
        return $out;
    }
}
