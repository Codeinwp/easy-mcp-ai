<?php

namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Excluded_Abilities {

    const NAMES = array(
        'mcp-adapter/discover-abilities',
        'mcp-adapter/get-ability-info',
        'mcp-adapter/execute-ability',
        'angie/discover-abilities',
        'angie/get-ability-info',
        'angie/execute-ability',
        'angie/read-resource',
    );

    public static function excludes( string $name ): bool {
        return in_array( $name, self::NAMES, true );
    }

    public static function without( array $abilities ): array {
        foreach ( $abilities as $key => $ability ) {
            $name = is_object( $ability ) && method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : (string) $key;
            if ( self::excludes( $name ) ) {
                unset( $abilities[ $key ] );
            }
        }
        return $abilities;
    }
}
