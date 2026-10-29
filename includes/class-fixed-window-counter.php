<?php

namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Fixed_Window_Counter {

    public static function hit( string $key, int $window ): array {
        $now    = time();
        $count  = 1;
        $ends   = $now + $window;
        $packed = get_transient( $key );
        if ( is_string( $packed ) && false !== strpos( $packed, ':' ) ) {
            list( $stored_count, $stored_ends ) = explode( ':', $packed, 2 );
            if ( (int) $stored_ends > $now ) {
                $count = (int) $stored_count + 1;
                $ends  = (int) $stored_ends;
            }
        }
        set_transient( $key, $count . ':' . $ends, max( 1, $ends - $now ) );
        return array(
            'count' => $count,
            'ends'  => $ends,
        );
    }
}
