<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Shell_Lexer {

    const EXCERPT_CHARS = 10;

    const HIDDEN = '[redacted]';

    const UNQUOTED_REFUSALS = array(
        "\0" => array( 'nul_byte', 'NUL byte' ),
        "\n" => array( 'shell_operator', 'newline' ),
        "\r" => array( 'shell_operator', 'carriage return' ),
        ';'  => array( 'shell_operator', "command separator ';'" ),
        '('  => array( 'subshell', "subshell '('" ),
        ')'  => array( 'subshell', "subshell ')'" ),
        '$'  => array( 'expansion', "expansion '\$'" ),
        '`'  => array( 'expansion', "command substitution '`'" ),
        '*'  => array( 'glob', "glob '*'" ),
        '?'  => array( 'glob', "glob '?'" ),
        '['  => array( 'glob', "glob '['" ),
        '{'  => array( 'brace_expansion', "brace expansion '{'" ),
        '}'  => array( 'brace_expansion', "brace expansion '}'" ),
    );

    const TWO_BYTE_OPERATORS = array(
        '&&' => "operator '&&'",
        '||' => "operator '||'",
        '|&' => "pipe '|&'",
    );

    const ONE_BYTE_OPERATORS = array(
        '&' => "background operator '&'",
        '|' => "pipe '|'",
    );

    public static function split( string $line ): array {
        $words = array();
        $word  = '';
        $started = false;
        $after_separator = false;
        $length          = strlen( $line );
        $i               = 0;

        while ( $i < $length ) {
            $c = $line[ $i ];

            if ( ' ' === $c || "\t" === $c ) {
                if ( $started ) {
                    $words[] = $word;
                    $word    = '';
                    $started = false;
                }
                $after_separator = false;
                ++$i;
            } elseif ( self::is_line_continuation( $line, $i ) ) {
                $i += 2;
            } else {
                $i       = self::read_part( $line, $i, $word, $started, $after_separator );
                $started = true;
                $after_separator = ( '=' === $c || ':' === $c );
            }
        }

        if ( $started ) {
            $words[] = $word;
        }
        return $words;
    }

    private static function is_line_continuation( string $line, int $i ): bool {
        return '\\' === $line[ $i ] && "\n" === substr( $line, $i + 1, 1 );
    }

    private static function read_part( string $line, int $i, string &$word, bool $started, bool $after_separator ): int {
        $c = $line[ $i ];
        if ( "'" === $c ) {
            return self::single_quoted( $line, $i, $word );
        }
        if ( '"' === $c ) {
            return self::double_quoted( $line, $i, $word );
        }
        if ( '\\' === $c ) {
            return self::escaped( $line, $i, $word );
        }
        $feature = self::unquoted_feature( $line, $i, $started, $after_separator );
        if ( null !== $feature ) {
            throw 'nul_byte' === $feature[0] ? self::nul_byte( $line, $i ) : self::refusal( $feature[0], $feature[1], $line, $i );
        }
        $word .= $c;
        return $i + 1;
    }

    private static function single_quoted( string $line, int $open, string &$word ): int {
        $close = strpos( $line, "'", $open + 1 );
        $part  = false === $close ? substr( $line, $open + 1 ) : substr( $line, $open + 1, $close - $open - 1 );
        $nul   = strpos( (string) $part, "\0" );
        if ( false !== $nul ) {
            throw self::nul_byte( $line, $open + 1 + $nul );
        }
        if ( false === $close ) {
            throw self::unterminated( $line, $open, 'single' );
        }
        $word .= $part;
        return $close + 1;
    }

    private static function escaped( string $line, int $i, string &$word ): int {
        if ( $i + 1 >= strlen( $line ) ) {
            throw self::dangling_escape( $line, $i );
        }
        $next = $line[ $i + 1 ];
        if ( "\0" === $next ) {
            throw self::nul_byte( $line, $i + 1 );
        }
        $word .= $next;
        return $i + 2;
    }

    private static function double_quoted( string $line, int $open, string &$word ): int {
        $length = strlen( $line );
        $i      = $open + 1;
        while ( $i < $length ) {
            $c = $line[ $i ];
            if ( '"' === $c ) {
                return $i + 1;
            }
            if ( "\0" === $c ) {
                throw self::nul_byte( $line, $i );
            }
            if ( '$' === $c ) {
                throw self::refusal( 'expansion', "expansion '\$' inside double quotes", $line, $i );
            }
            if ( '`' === $c ) {
                throw self::refusal( 'expansion', "command substitution '`' inside double quotes", $line, $i );
            }
            if ( '\\' === $c ) {
                $i = self::double_quoted_backslash( $line, $i, $word );
                continue;
            }
            $word .= $c;
            ++$i;
        }
        throw self::unterminated( $line, $open, 'double' );
    }

    private static function double_quoted_backslash( string $line, int $i, string &$word ): int {
        $next = substr( $line, $i + 1, 1 );
        if ( "\n" === $next ) {
            return $i + 2;
        }
        if ( in_array( $next, array( '$', '`', '"', '\\' ), true ) ) {
            $word .= $next;
            return $i + 2;
        }
        $word .= '\\';
        return $i + 1;
    }

    private static function unquoted_feature( string $line, int $i, bool $started, bool $after_separator ): ?array {
        $c = $line[ $i ];
        if ( isset( self::UNQUOTED_REFUSALS[ $c ] ) ) {
            return self::UNQUOTED_REFUSALS[ $c ];
        }
        if ( false !== strpos( '&|<>', $c ) ) {
            return self::operator_feature( $line, $i );
        }
        if ( '~' === $c && ( ! $started || $after_separator ) ) {
            return array( 'tilde', "tilde '~'" );
        }
        if ( '#' === $c && ! $started ) {
            return array( 'comment', "comment '#'" );
        }
        return null;
    }

    private static function operator_feature( string $line, int $i ): array {
        $c    = $line[ $i ];
        $pair = substr( $line, $i, 2 );
        if ( '<' === $c || '>' === $c || '&>' === $pair ) {
            return array( 'redirection', "redirection '" . self::redirection_operator( $line, $i ) . "'" );
        }
        if ( isset( self::TWO_BYTE_OPERATORS[ $pair ] ) ) {
            return array( 'shell_operator', self::TWO_BYTE_OPERATORS[ $pair ] );
        }
        return array( 'shell_operator', self::ONE_BYTE_OPERATORS[ $c ] );
    }

    private static function redirection_operator( string $line, int $i ): string {
        $length   = strlen( $line );
        $operator = '';
        while ( $i < $length && strlen( $operator ) < 3 && false !== strpos( '<>&|', $line[ $i ] ) ) {
            $operator .= $line[ $i ];
            ++$i;
        }
        if ( $i < $length && '(' === $line[ $i ] && ( '<' === $operator || '>' === $operator ) ) {
            $operator .= '(';
        }
        return $operator;
    }

    private static function refusal( string $code, string $feature, string $line, int $byte ): Cli_Parse_Error {
        return new Cli_Parse_Error(
            $code,
            'wp_cli_run does not run a shell: ' . $feature . ' at character ' . self::char_index( $line, $byte )
            . ' (' . self::excerpt( $line, $byte ) . '). Quote it if it is part of a value.'
        );
    }

    private static function nul_byte( string $line, int $byte ): Cli_Parse_Error {
        return new Cli_Parse_Error(
            'nul_byte',
            'wp_cli_run does not run a shell: NUL byte at character ' . self::char_index( $line, $byte )
            . ' (' . self::excerpt( $line, $byte ) . '). A NUL byte cannot be passed, quoted or not.'
        );
    }

    private static function unterminated( string $line, int $byte, string $kind ): Cli_Parse_Error {
        $quote = 'single' === $kind ? "'" : '"';
        return new Cli_Parse_Error(
            'unterminated_quote',
            'wp_cli_run could not parse the command: the ' . $kind . ' quote (' . $quote . ') at character '
            . self::char_index( $line, $byte ) . ' (' . self::excerpt( $line, $byte ) . ') is never closed.'
        );
    }

    private static function dangling_escape( string $line, int $byte ): Cli_Parse_Error {
        return new Cli_Parse_Error(
            'dangling_escape',
            'wp_cli_run could not parse the command: the backslash at character ' . self::char_index( $line, $byte )
            . ' (' . self::excerpt( $line, $byte ) . ') ends the command and escapes nothing. Remove it, or quote it as \'\\\'.'
        );
    }

    private static function char_index( string $line, int $byte ): int {
        $prefix = substr( $line, 0, $byte );
        return strlen( $prefix ) - (int) preg_match_all( '/[\x80-\xBF]/', $prefix ) + 1;
    }

    private static function excerpt( string $line, int $byte ): string {
        $start  = self::excerpt_start( $line, $byte );
        $end    = self::excerpt_end( $line, $byte );
        $ranges = Url_Mask::hidden_ranges( $line, $start, $end );

        $text   = '';
        $hiding = false;
        for ( $k = $start; $k < $end; $k++ ) {
            if ( $k !== $byte && self::in_ranges( $ranges, $k ) ) {
                $text  .= $hiding ? '' : self::HIDDEN;
                $hiding = true;
                continue;
            }
            $hiding = false;
            $text  .= self::printable( $line[ $k ] );
        }
        return ( $start > 0 ? '...' : '' ) . $text . ( $end < strlen( $line ) ? '...' : '' );
    }

    private static function in_ranges( array $ranges, int $offset ): bool {
        foreach ( $ranges as $range ) {
            if ( $offset >= $range[0] && $offset < $range[1] ) {
                return true;
            }
        }
        return false;
    }

    private static function excerpt_start( string $line, int $byte ): int {
        $start = $byte;
        $count = 0;
        while ( $start > 0 && $count < self::EXCERPT_CHARS ) {
            --$start;
            if ( ! self::is_continuation( $line[ $start ] ) ) {
                ++$count;
            }
        }
        return $start;
    }

    private static function excerpt_end( string $line, int $byte ): int {
        $length = strlen( $line );
        $end    = $byte;
        $count  = 0;
        while ( $end < $length && $count <= self::EXCERPT_CHARS ) {
            ++$end;
            while ( $end < $length && self::is_continuation( $line[ $end ] ) ) {
                ++$end;
            }
            ++$count;
        }
        return $end;
    }

    private static function is_continuation( string $b ): bool {
        $o = ord( $b );
        return $o >= 0x80 && $o <= 0xBF;
    }

    private static function printable( string $b ): string {
        switch ( $b ) {
            case "\n":
                return '\\n';
            case "\r":
                return '\\r';
            case "\t":
                return '\\t';
            case "\0":
                return '\\0';
        }
        $o = ord( $b );
        if ( $o < 0x20 || 0x7F === $o ) {
            return sprintf( '\\x%02X', $o );
        }
        return $b;
    }
}
