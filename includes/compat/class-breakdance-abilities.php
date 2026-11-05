<?php

namespace Easy_MCP_AI\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Breakdance_Abilities extends Vendor_Abilities {

    const PREFIX = 'breakdance/';

    const VENDOR = 'Breakdance';

    const SETTING_FUNCTION = 'Breakdance\\Admin\\SettingsPage\\AgentsAndMcpTab\\isMcpEnabled';

    const SETTING_OPTION = 'breakdance_agents_and_mcp_enable_mcp_access';

    const SETTING_ON = 'true';

    const MODE_CONSTANT = 'BREAKDANCE_MODE';

    const SERVER_FILTER = 'breakdance_mcp_default_server_config';

    const CAPABILITY = 'manage_options';

    public static function register_abilities( string $setting = self::SETTING_FUNCTION, string $mode = self::MODE_CONSTANT ): void {
        if ( ! self::active() || ! function_exists( $setting ) || ! defined( $mode ) || 'breakdance' !== constant( $mode ) ) {
            return;
        }
        self::run(
            static function () use ( $setting ) {
                if ( true === call_user_func( $setting ) ) {
                    return;
                }
                $gate = self::gate_file( $setting );
                if ( '' === $gate ) {
                    throw new \RuntimeException( 'its plugin/mcp/base.php is not where Breakdance 3.0.0 has it, cannot be read, or declares something a second include would declare again' );
                }
                self::load( $gate );
            },
            'loading its MCP files'
        );
    }

    protected static function ran_before_switches_dropped(): bool {
        $stored = \get_option( self::SETTING_OPTION, null );
        if ( null === $stored ) {
            return true;
        }
        $decoded = is_string( $stored ) ? json_decode( $stored, true ) : false;
        return true === $decoded || 'yes' === $decoded;
    }

    public static function keep_private( array $args, string $name ): array {
        if ( 0 !== strpos( $name, self::PREFIX ) ) {
            return $args;
        }
        $meta          = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
        $mcp           = isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) ? $meta['mcp'] : array();
        $mcp['public'] = false;
        $meta['mcp']   = $mcp;
        $args['meta']  = $meta;
        return $args;
    }

    private static function load( string $gate ): void {
        $asked = false;
        $on    = static function () use ( &$asked ): string {
            $asked = true;
            return self::SETTING_ON;
        };
        add_filter( 'wp_register_ability_args', array( self::class, 'keep_private' ), PHP_INT_MAX, 2 );
        add_filter( 'pre_option_' . self::SETTING_OPTION, $on, PHP_INT_MAX );
        try {
            require $gate;
        } finally {
            remove_filter( 'pre_option_' . self::SETTING_OPTION, $on, PHP_INT_MAX );
            remove_filter( 'mcp_adapter_default_server_config', self::SERVER_FILTER );
        }
        if ( ! $asked ) {
            throw new \RuntimeException( sprintf( 'it no longer reads its setting from the %s option', self::SETTING_OPTION ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- written to the error log only.
        }
    }

    private static function gate_file( string $setting ): string {
        $file = ( new \ReflectionFunction( $setting ) )->getFileName();
        if ( ! is_string( $file ) ) {
            return '';
        }
        $gate = dirname( $file, 4 ) . '/mcp/base.php';
        $code = is_readable( $gate ) ? file_get_contents( $gate ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local plugin file, read to be tokenised.
        return is_string( $code ) && self::defines_nothing( $code ) ? $gate : '';
    }

    private static function defines_nothing( string $code ): bool {
        $declarations = array( T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_CONST );
        if ( defined( 'T_ENUM' ) ) {
            $declarations[] = T_ENUM;
        }
        $previous = null;
        foreach ( token_get_all( $code ) as $token ) {
            $id = is_array( $token ) ? $token[0] : $token;
            if ( in_array( $id, array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
                continue;
            }
            $imported = T_USE === $previous && in_array( $id, array( T_FUNCTION, T_CONST ), true );
            if ( in_array( $id, $declarations, true ) && ! $imported && T_DOUBLE_COLON !== $previous ) {
                return false;
            }
            $previous = $id;
        }
        return true;
    }
}
