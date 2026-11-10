<?php

namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class External_Data_Flag {

    public static function read( string $name, $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_int( $value ) || is_float( $value ) ) {
            $flag = in_array( (float) $value, array( 0.0, 1.0 ), true ) ? 1.0 === (float) $value : null;
        } else {
            $flag = is_string( $value ) ? filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : null;
        }
        if ( null === $flag ) {
            throw new \InvalidArgumentException( $name . ' must be true or false.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $flag;
    }
}
