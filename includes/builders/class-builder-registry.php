<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Builder_Registry {

    private static ?array $adapters = null;

    public static function all(): array {
        if ( null === self::$adapters ) {
            self::$adapters = array( new Elementor_Adapter(), new WPBakery_Adapter(), new Flatsome_Adapter() );
        }
        return self::$adapters;
    }

    public static function present(): array {
        return array_values(
            array_filter(
                self::all(),
                static function ( Layout_Adapter $adapter ): bool {
                    return $adapter->is_present();
                }
            )
        );
    }

    public static function any_present(): bool {
        return array() !== self::present();
    }

    public static function for_post( object $post ): ?Layout_Adapter {
        foreach ( self::present() as $adapter ) {
            if ( $adapter->owns( $post ) ) {
                return $adapter;
            }
        }
        return null;
    }

    public static function get( string $id ): ?Layout_Adapter {
        foreach ( self::present() as $adapter ) {
            if ( $adapter->id() === $id ) {
                return $adapter;
            }
        }
        return null;
    }

    public static function set_adapters( ?array $adapters ): void {
        self::$adapters = $adapters;
    }
}
