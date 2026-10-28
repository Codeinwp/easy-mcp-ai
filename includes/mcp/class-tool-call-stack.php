<?php

namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tool_Call_Stack {

    private static $names = array();

    public static function enter( string $tool_name ): int {
        self::$names[] = $tool_name;
        return count( self::$names );
    }

    public static function leave(): void {
        array_pop( self::$names );
    }

    public static function depth(): int {
        return count( self::$names );
    }

    public static function current(): ?string {
        $count = count( self::$names );
        return 0 === $count ? null : self::$names[ $count - 1 ];
    }
}
