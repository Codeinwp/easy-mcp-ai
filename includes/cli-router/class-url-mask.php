<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Url_Mask {

    const OUTSIDE = 0;

    const AUTHORITY = 1;

    const PATH = 2;

    const QUERY = 3;

    const DOUBLE_QUOTED_ESCAPES = array( '$', '`', '"', '\\', "\n" );

    private $window_from;

    private $window_to;

    private $ranges = array();

    private $state = self::OUTSIDE;

    private $tail = '';

    private $start = 0;

    private $at = null;

    public static function hidden_ranges( string $line, int $from, int $to ): array {
        $mask = new self( $from, $to );
        $mask->scan( $line );
        return $mask->ranges;
    }

    private function __construct( int $from, int $to ) {
        $this->window_from = $from;
        $this->window_to   = $to;
    }

    private function scan( string $line ): void {
        $length = strlen( $line );
        $quote  = '';
        $i      = 0;
        while ( $i < $length ) {
            if ( $i >= $this->window_to && self::AUTHORITY !== $this->state ) {
                break;
            }
            $c = $line[ $i ];
            if ( "'" === $quote ) {
                if ( "'" === $c ) {
                    $quote = '';
                } else {
                    $this->decoded( $c, $i );
                }
                ++$i;
            } elseif ( '"' === $quote ) {
                $i = $this->double_quoted( $line, $i, $quote );
            } elseif ( ' ' === $c || "\t" === $c ) {
                $this->end_word( $i );
                ++$i;
            } elseif ( "'" === $c || '"' === $c ) {
                $quote = $c;
                ++$i;
            } elseif ( '\\' === $c ) {
                if ( $i + 1 < $length && "\n" !== $line[ $i + 1 ] ) {
                    $this->decoded( $line[ $i + 1 ], $i + 1 );
                }
                $i += 2;
            } else {
                $this->decoded( $c, $i );
                ++$i;
            }
        }
        $this->end_word( min( $i, $length ) );
    }

    private function double_quoted( string $line, int $i, string &$quote ): int {
        $c = $line[ $i ];
        if ( '"' === $c ) {
            $quote = '';
            return $i + 1;
        }
        $next = substr( $line, $i + 1, 1 );
        if ( '\\' === $c && in_array( $next, self::DOUBLE_QUOTED_ESCAPES, true ) ) {
            if ( "\n" !== $next ) {
                $this->decoded( $next, $i + 1 );
            }
            return $i + 2;
        }
        $this->decoded( $c, $i );
        return $i + 1;
    }

    private function decoded( string $c, int $offset ): void {
        if ( self::QUERY === $this->state ) {
            return;
        }
        if ( self::AUTHORITY === $this->state ) {
            if ( '@' === $c ) {
                $this->at = $offset;
            } elseif ( '/' === $c || '?' === $c || '#' === $c ) {
                $this->close_authority();
                $this->state = self::PATH;
            }
        }
        if ( self::PATH === $this->state && ( '?' === $c || '#' === $c ) ) {
            $this->state = self::QUERY;
            $this->start = $offset + 1;
            return;
        }
        $this->tail = substr( $this->tail . $c, -3 );
        if ( '://' === $this->tail && self::AUTHORITY !== $this->state ) {
            $this->state = self::AUTHORITY;
            $this->start = $offset + 1;
            $this->at    = null;
        }
    }

    private function end_word( int $offset ): void {
        if ( self::AUTHORITY === $this->state ) {
            $this->close_authority();
        }
        if ( self::QUERY === $this->state ) {
            $this->add( $this->start, $offset );
        }
        $this->state = self::OUTSIDE;
        $this->tail  = '';
        $this->at    = null;
    }

    private function close_authority(): void {
        if ( null !== $this->at ) {
            $this->add( $this->start, $this->at );
        }
        $this->at = null;
    }

    private function add( int $from, int $to ): void {
        $from = max( $from, $this->window_from );
        $to   = min( $to, $this->window_to );
        if ( $from < $to ) {
            $this->ranges[] = array( $from, $to );
        }
    }
}
