<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Theme_Mod_Handler extends Command_Handler {

    const MASK = '[REDACTED]';

    const MISSING_NONE    = "None of the theme mods named is stored for the active theme, so nothing was removed: %s. WP-CLI prints a success line anyway; `theme mod list` shows what is stored.";
    const MISSING_ALL     = 'The active theme has no stored theme mods, so nothing was removed. WP-CLI prints a success line anyway.';
    const NOTE_NOT_STORED = 'Not stored, so not removed (WP-CLI counts them anyway): %s.';

    const NOT_CAPTURED = 'Theme mods live in the theme_mods_<theme> option, which change history records only while it records every option (not in allowlist mode); wp_history_apply cannot restore it.';

    const TOOL = 'wp_delete_theme_mod';

    const REFUSE_TOOL = "'wp theme mod remove' would remove the Customizer settings %s, which the wp_delete_theme_mod tool changes, and this connection cannot use it: the tool is switched off, not granted to this API key or OAuth grant, or needs a capability your WordPress user lacks.";

    public function get( Invocation $inv, Context $ctx ): void {
        $this->get_mods( array_values( array_map( 'strval', $inv->args ) ), $inv->assoc, $ctx );
    }

    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc        = $inv->assoc;
        $assoc['all'] = 1;
        $this->get_mods( array(), $assoc, $ctx );
    }

    public function remove( Invocation $inv, Context $ctx ): void {
        $args = array_values( array_map( 'strval', $inv->args ) );
        if ( ! Read_Value::flag( $inv->assoc, 'all' ) && empty( $args ) ) {
            $ctx->out->error( 'You must specify at least one mod or use --all.' );
        }
        $ctx->not_captured( self::NOT_CAPTURED );
        self::refuse_owned_without_tool( $inv, $ctx, $args );

        if ( Read_Value::flag( $inv->assoc, 'all' ) ) {
            if ( Theme_Handler::wp( 'remove_theme_mods' ) ) {
                $ctx->mark_changed();
            } else {
                $ctx->fail_missing_target( self::MISSING_ALL );
            }
            $ctx->out->success( 'Theme mods removed.' );
            return;
        }

        self::remove_named( $args, $ctx );
    }

    private static function refuse_owned_without_tool( Invocation $inv, Context $ctx, array $args ): void {
        $removed = Read_Value::flag( $inv->assoc, 'all' ) ? array_map( 'strval', array_keys( (array) Theme_Handler::wp( 'theme_mods' ) ) ) : $args;
        $owned   = $removed ? array_values( (array) Theme_Handler::wp( 'customizer_owned', array( array_values( array_unique( $removed ) ) ) ) ) : array();
        if ( $owned && ! $ctx->may_use( self::TOOL ) ) {
            $ctx->out->error( sprintf( self::REFUSE_TOOL, "'" . implode( "', '", array_map( 'strval', $owned ) ) . "'" ) );
        }
    }

    private static function remove_named( array $args, Context $ctx ): void {
        $not_stored = array();
        foreach ( $args as $mod ) {
            if ( Theme_Handler::wp( 'remove_theme_mod', array( $mod ) ) ) {
                $ctx->mark_changed();
            } else {
                $not_stored[] = "'" . $mod . "'";
            }
        }

        if ( ! $ctx->changed() ) {
            $ctx->fail_missing_target( sprintf( self::MISSING_NONE, implode( ', ', $not_stored ) ) );
        } elseif ( $not_stored ) {
            $ctx->note( sprintf( self::NOTE_NOT_STORED, implode( ', ', $not_stored ) ) );
        }

        $count           = count( $args );
        $success_message = ( 1 === $count ) ? '%d mod removed.' : '%d mods removed.';
        $ctx->out->success( sprintf( $success_message, $count ) );
    }

    public function delegates_remove( Invocation $inv ): bool {
        $args = array_values( array_unique( array_map( 'strval', $inv->args ) ) );
        if ( Read_Value::flag( $inv->assoc, 'all' ) || ! $args ) {
            return false;
        }
        foreach ( $args as $mod ) {
            if ( '' === trim( $mod ) || false !== strpbrk( $mod, '[]' ) || null !== \Easy_MCP_AI\Tools\Appearance\Customizer_Settings::denied_reason( $mod ) ) {
                return false;
            }
        }
        return ! class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) || ! method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' )
            || false !== \Easy_MCP_AI\MCP\Server::current_call_may_use( self::TOOL );
    }

    public function describe_remove( Invocation $inv ): array {
        $mods  = (array) Theme_Handler::wp( 'theme_mods' );
        $lines = array();
        if ( Read_Value::flag( $inv->assoc, 'all' ) ) {
            $options = (array) Theme_Handler::wp( 'theme_mods_options' );
            foreach ( $mods as $key => $value ) {
                $lines[] = sprintf( "Theme mod '%s': %s → removed", (string) $key, self::preview( (string) $key, $value ) );
            }
            $lines[] = sprintf( "Options '%s' and '%s' → deleted (WordPress's remove_theme_mods())", (string) ( $options[0] ?? '' ), (string) ( $options[1] ?? '' ) );
            return $lines;
        }
        foreach ( $inv->args as $mod ) {
            $mod = (string) $mod;
            if ( isset( $mods[ $mod ] ) ) {
                $lines[] = sprintf( "Theme mod '%s': %s → removed", $mod, self::preview( $mod, $mods[ $mod ] ) );
            } else {
                $lines[] = sprintf( "Theme mod '%s': not stored → nothing changes", $mod );
            }
        }
        return $lines ? $lines : array( 'No theme mod named → nothing changes' );
    }

    private function get_mods( array $args, array $assoc, Context $ctx ): void {
        if ( ! Read_Value::flag( $assoc, 'all' ) && empty( $args ) ) {
            $ctx->out->error( 'You must specify at least one mod or use --all.' );
        }
        if ( Read_Value::flag( $assoc, 'all' ) ) {
            $args = array();
        }

        $list = array();
        $mods = Theme_Handler::wp( 'theme_mods' );
        if ( ! is_array( $mods ) ) {
            $mods = array();
        }
        foreach ( $mods as $k => $v ) {
            if ( ! empty( $args ) && ! in_array( $k, $args, true ) ) {
                continue;
            }
            $list = array_merge( $list, self::mod_rows( $k, $v, $ctx ) );
        }

        foreach ( $args as $mod ) {
            if ( ! isset( $mods[ $mod ] ) ) {
                $list[] = array(
                    'key'   => $mod,
                    'value' => '',
                );
            }
        }

        $this->formatter( $ctx, $assoc, array( 'key', 'value' ), 'thememods' )->display_items( $list );
    }

    private static function mod_rows( $key, $value, Context $ctx ): array {
        $secret = Option_Handler::key_is_secret( (string) $key );
        if ( ! is_array( $value ) ) {
            if ( $secret ) {
                $value = self::MASK;
                $ctx->redacted( "value of theme mod '{$key}'" );
            }
            return array(
                array(
                    'key'   => $key,
                    'value' => $value,
                ),
            );
        }
        $rows = array(
            array(
                'key'   => $key,
                'value' => '=>',
            ),
        );
        foreach ( $value as $sub_key => $sub_value ) {
            if ( $secret || Option_Handler::key_is_secret( (string) $sub_key ) ) {
                $sub_value = self::MASK;
                $ctx->redacted( "value of theme mod '{$key}[{$sub_key}]'" );
            } else {
                $sub_value = self::mask_nested( $sub_value, "{$key}[{$sub_key}]", $ctx );
            }
            $rows[] = array(
                'key'   => "    $sub_key",
                'value' => $sub_value,
            );
        }
        return $rows;
    }

    private static function preview( string $key, $value ): string {
        if ( Option_Handler::key_is_secret( $key ) ) {
            return self::MASK;
        }
        $json = (string) json_encode( self::mask_nested( $value, $key, null ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
        return strlen( $json ) > 200 ? substr( $json, 0, 197 ) . '...' : $json;
    }

    private static function mask_nested( $value, string $path, ?Context $ctx ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }
        foreach ( $value as $k => $v ) {
            if ( ! is_int( $k ) && Option_Handler::key_is_secret( (string) $k ) ) {
                $value[ $k ] = self::MASK;
                if ( null !== $ctx ) {
                    $ctx->redacted( "value of theme mod '{$path}[{$k}]'" );
                }
            } else {
                $value[ $k ] = self::mask_nested( $v, "{$path}[{$k}]", $ctx );
            }
        }
        return $value;
    }
}
