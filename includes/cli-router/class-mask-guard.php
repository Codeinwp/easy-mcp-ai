<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Mask_Guard {

    const REFUSED = "wp_cli_run refuses to store %1\$s: it is '%2\$s' (as sent or URL-decoded), the mask shown in place of a secret, not the secret. Nothing was written. Send the real value.";

    const STORED_FLAGS = array(
        'post create'         => array( 'meta_input' => true ),
        'post update'         => array( 'meta_input' => true ),
        'user create'         => array( 'user_pass' => false ),
        'user update'         => array( 'user_pass' => false ),
        'cron event schedule' => array( '*' => false ),
    );

    const JSON_TEXT = array( 'theme mod set' );

    public static function refusal( string $command, array $args, array $assoc ): ?string {
        $where = self::flag_with_mask( $command, $assoc );
        if ( null === $where ) {
            $where = self::value_with_mask( $command, $args, $assoc );
        }
        return null === $where ? null : sprintf( self::REFUSED, $where, Redactor::MASK );
    }

    private static function holds_mask( $value ): bool {
        if ( is_array( $value ) ) {
            foreach ( $value as $inner ) {
                if ( self::holds_mask( $inner ) ) {
                    return true;
                }
            }
            return false;
        }
        return is_string( $value ) && Redactor::MASK === trim( rawurldecode( $value ) );
    }

    private static function flag_with_mask( string $command, array $assoc ): ?string {
        $flags = isset( self::STORED_FLAGS[ $command ] ) ? self::STORED_FLAGS[ $command ] : array();
        if ( isset( $flags['*'] ) ) {
            $flags = array_fill_keys( array_keys( $assoc ), $flags['*'] );
        }
        foreach ( $flags as $flag => $json ) {
            if ( ! isset( $assoc[ $flag ] ) ) {
                continue;
            }
            if ( self::holds_mask( $json ? json_decode( (string) $assoc[ $flag ], true ) : $assoc[ $flag ] ) ) {
                return '--' . $flag;
            }
        }
        return null;
    }

    private static function value_with_mask( string $command, array $args, array $assoc ): ?string {
        $value = self::stored_value( $command, $args );
        if ( null === $value ) {
            return null;
        }
        if ( 'json' === Read_Value::flag( $assoc, 'format' ) ) {
            $value = json_decode( $value, true );
        } elseif ( in_array( $command, self::JSON_TEXT, true ) && is_array( json_decode( $value, true ) ) ) {
            $value = json_decode( $value, true );
        }
        if ( ! self::holds_mask( $value ) ) {
            return null;
        }
        return is_array( $value ) ? 'a string in the value' : 'the value';
    }

    private static function stored_value( string $command, array $args ): ?string {
        if ( ! isset( Redactor::KEYED_VALUES[ $command ] ) || ' delete' === substr( $command, -7 ) ) {
            return null;
        }
        $value_index = Redactor::KEYED_VALUES[ $command ][1];
        if ( ' patch' !== substr( $command, -6 ) ) {
            return isset( $args[ $value_index ] ) ? (string) $args[ $value_index ] : null;
        }
        $tail = array_slice( $args, $value_index );
        return isset( $args[0] ) && 'delete' !== $args[0] && count( $tail ) >= 2 ? (string) end( $tail ) : null;
    }
}
