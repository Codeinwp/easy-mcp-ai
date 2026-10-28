<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Read_Value {

    const NO_STDIN = 'wp_cli_run has no STDIN, so <%s> cannot be omitted: WP-CLI would read it from STDIN. Pass the value in the command.';

    const STDIN_MARKER = "wp_cli_run has no STDIN: '-' makes WP-CLI read <%s> from STDIN. Pass the content in the command instead.";

    public static function read( Output $out, $raw_value, array $assoc_args = array() ) {
        if ( self::flag( $assoc_args, 'format' ) === 'json' ) {
            $value = json_decode( (string) $raw_value, true );
            if ( null === $value ) {
                $out->error( sprintf( 'Invalid JSON: %s', $raw_value ) );
            }
        } else {
            $value = $raw_value;
        }

        return $value;
    }

    public static function from_arg_or_stdin( Output $out, array $args, int $index, string $param = 'value' ): string {
        if ( isset( $args[ $index ] ) ) {
            return (string) $args[ $index ];
        }
        $out->error( sprintf( self::NO_STDIN, $param ) );
        return '';
    }

    public static function refuse_stdin_marker( Output $out, string $value, string $param ): void {
        if ( '-' === $value ) {
            $out->error( sprintf( self::STDIN_MARKER, $param ) );
        }
    }

    public static function flag( array $assoc_args, string $flag, $default = null ) {
        return isset( $assoc_args[ $flag ] ) ? $assoc_args[ $flag ] : $default;
    }
}
