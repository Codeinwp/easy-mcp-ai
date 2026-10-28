<?php
namespace Easy_MCP_AI\Privileged\Search_Replace;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Replacer {

    const MAX_DEPTH = 64;

    private $old;
    private $new;
    private $old_json;
    private $new_json;

    private $occurrences = 0;
    private $classes = array();
    private $too_deep = false;
    private $unsafe = false;
    private $objects_left = 0;
    private $seen_objects = array();
    private $seen_references = array();

    private $pattern = null;
    private $regex_limit = -1;
    private $recurse_objects = true;
    private $logging = false;
    private $regex_error = false;
    private $log_data = array();

    public function __construct( string $old, string $new, array $options = array() ) {
        $this->old             = $old;
        $this->new             = $new;
        $this->recurse_objects = ! array_key_exists( 'recurse_objects', $options ) || (bool) $options['recurse_objects'];
        $this->logging         = ! empty( $options['log'] );
        $this->new_json        = self::json_inner( $new );
        if ( ! empty( $options['regex'] ) ) {
            $delimiter         = isset( $options['regex_delimiter'] ) && '' !== (string) $options['regex_delimiter'] ? (string) $options['regex_delimiter'] : chr( 1 );
            $this->pattern     = self::pattern( $old, $delimiter, (string) ( $options['regex_flags'] ?? '' ) );
            $this->regex_limit = isset( $options['regex_limit'] ) ? (int) $options['regex_limit'] : -1;
            $this->old_json    = null;
            return;
        }
        $old_json       = self::json_inner( $old );
        $this->old_json = $old_json !== $old ? $old_json : null;
    }

    public static function pattern( string $old, string $delimiter, string $flags ): string {
        return $delimiter . $old . $delimiter . $flags;
    }

    public function is_regex(): bool {
        return null !== $this->pattern;
    }

    public static function json_inner( string $value ): string {
        $encoded = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact default-flag form PHP and WP-CLI produce.
        return false !== $encoded ? substr( $encoded, 1, -1 ) : $value;
    }

    public function needles(): array {
        if ( null !== $this->pattern ) {
            return array( '' );
        }
        return null === $this->old_json ? array( $this->old ) : array( $this->old, $this->old_json );
    }

    public function first_match( string $value ): ?int {
        if ( null !== $this->pattern ) {
            $found = @preg_match( $this->pattern, $value, $m, PREG_OFFSET_CAPTURE ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a PCRE error is answered as no match.
            return 1 === $found ? (int) $m[0][1] : null;
        }
        $pos = null;
        foreach ( $this->needles() as $needle ) {
            $at = strpos( $value, $needle );
            if ( false !== $at && ( null === $pos || $at < $pos ) ) {
                $pos = $at;
            }
        }
        return $pos;
    }

    public function replace( string $value ): array {
        $this->reset_cell();

        if ( self::looks_serialized( $value ) ) {
            return $this->replace_serialized( $value );
        }

        $out = $this->replace_string( $value );
        if ( $this->regex_error ) {
            return $this->result( $value, 'regex_error' );
        }
        return $this->result( $out, $out === $value ? 'unchanged' : 'changed' );
    }

    private function reset_cell(): void {
        $this->occurrences     = 0;
        $this->classes         = array();
        $this->too_deep        = false;
        $this->unsafe          = false;
        $this->objects_left    = 0;
        $this->seen_objects    = array();
        $this->seen_references = array();
        $this->regex_error     = false;
        $this->log_data        = array();
    }

    private function replace_serialized( string $value ): array {
        $data = self::unserialize( $value );
        if ( false === $data && ! self::is_serialized_false( $value ) ) {
            return $this->result( $value, 'corrupt' );
        }
        $walked  = $this->walk( $data, 1 );
        $refused = $this->walk_refusal( $value );
        if ( null !== $refused ) {
            return $this->result( $value, $refused );
        }
        if ( 0 === $this->occurrences ) {
            return $this->result( $value, $this->classes ? 'partial_unchanged' : 'unchanged' );
        }
        $out = serialize( $walked ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- rewriting a serialized value in place.
        if ( ! self::reads_back_as( $out, $data ) ) {
            return $this->result( $value, 'corrupt' );
        }
        return $this->result( $out, $this->classes ? 'partial' : 'changed' );
    }

    private function walk_refusal( string $value ): ?string {
        if ( $this->too_deep ) {
            return 'too_deep';
        }
        if ( $this->regex_error ) {
            return 'regex_error';
        }
        if ( $this->unsafe || ( $this->occurrences > 0 && $this->classes && ! self::round_trips( $value ) ) ) {
            return 'unsafe_object';
        }
        return null;
    }

    private static function reads_back_as( string $out, $data ): bool {
        $back = self::unserialize( $out );
        return ( false !== $back || self::is_serialized_false( $out ) ) && gettype( $back ) === gettype( $data );
    }

    private function walk( $data, int $depth ) {
        if ( $depth > self::MAX_DEPTH ) {
            $this->too_deep = true;
            return $data;
        }
        if ( is_string( $data ) ) {
            return $this->walk_string( $data, $depth );
        }
        if ( is_array( $data ) ) {
            return $this->walk_array( $data, $depth );
        }
        if ( is_object( $data ) ) {
            return $this->walk_object( $data, $depth );
        }
        return $data;
    }

    private function walk_string( string $data, int $depth ): string {
        if ( self::looks_serialized( $data ) ) {
            $inner = self::unserialize( $data );
            if ( false !== $inner || self::is_serialized_false( $data ) ) {
                return $this->walk_nested_serialized( $data, $inner, $depth );
            }
        }
        return $this->replace_string( $data );
    }

    private function walk_nested_serialized( string $data, $inner, int $depth ): string {
        $before  = $this->occurrences;
        $objects = $this->objects_left;
        $walked  = $this->walk( $inner, $depth + 1 );
        if ( $this->occurrences === $before ) {
            return $data;
        }
        if ( $this->objects_left > $objects && ! self::round_trips( $data ) ) {
            $this->unsafe = true;
            return $data;
        }
        return serialize( $walked ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- a value serialized twice keeps its outer layer.
    }

    private function walk_array( array $data, int $depth ): array {
        foreach ( array_keys( $data ) as $key ) {
            $ref = class_exists( 'ReflectionReference' ) ? \ReflectionReference::fromArrayElement( $data, $key ) : null;
            if ( null !== $ref ) {
                $id = $ref->getId();
                if ( isset( $this->seen_references[ $id ] ) ) {
                    continue;
                }
                $this->seen_references[ $id ] = true;
            }
            $data[ $key ] = $this->walk( $data[ $key ], $depth + 1 );
        }
        return $data;
    }

    private function walk_object( object $data, int $depth ): object {
        if ( $data instanceof \__PHP_Incomplete_Class ) {
            $props = (array) $data;
            $name  = isset( $props['__PHP_Incomplete_Class_Name'] ) ? (string) $props['__PHP_Incomplete_Class_Name'] : '';
            $this->note_class( $name );
            return $data;
        }
        if ( $data instanceof \stdClass ) {
            return $this->walk_stdclass( $data, $depth );
        }
        $this->note_class( get_class( $data ) );
        return $data;
    }

    private function walk_stdclass( \stdClass $data, int $depth ): \stdClass {
        if ( ! $this->recurse_objects ) {
            $this->note_class( 'stdClass' );
            return $data;
        }
        $id = spl_object_id( $data );
        if ( isset( $this->seen_objects[ $id ] ) ) {
            return $data;
        }
        $this->seen_objects[ $id ] = true;
        foreach ( get_object_vars( $data ) as $key => $value ) {
            $data->$key = $this->walk( $value, $depth + 1 );
        }
        return $data;
    }

    private function replace_string( string $value ): string {
        if ( null !== $this->pattern ) {
            return $this->replace_pattern( $value );
        }
        $count = 0;
        $out   = str_replace( $this->old, $this->new, $value, $count );
        $this->occurrences += $count;
        if ( null !== $this->old_json ) {
            $json_count = 0;
            $out        = str_replace( $this->old_json, $this->new_json, $out, $json_count );
            $this->occurrences += $json_count;
        }
        if ( $this->logging && $out !== $value ) {
            $this->log_data[] = $value;
        }
        return $out;
    }

    private function replace_pattern( string $value ): string {
        $count = 0;
        $out   = @preg_replace( (string) $this->pattern, $this->new, $value, $this->regex_limit, $count ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the error is read from preg_last_error() and reported as regex_error.
        if ( null === $out || PREG_NO_ERROR !== preg_last_error() ) {
            $this->regex_error = true;
            return $value;
        }
        $this->occurrences += $count;
        if ( $this->logging && $out !== $value ) {
            $this->log_data[] = $value;
        }
        return $out;
    }

    private function note_class( string $name ): void {
        ++$this->objects_left;
        if ( '' !== $name && ! in_array( $name, $this->classes, true ) ) {
            $this->classes[] = $name;
        }
    }

    private function result( string $value, string $status ): array {
        if ( 'partial_unchanged' === $status ) {
            $status = 'partial';
            $out    = array( 'value' => $value, 'status' => $status, 'occurrences' => 0, 'classes' => $this->classes );
        } else {
            $out = array(
                'value'       => $value,
                'status'      => $status,
                'occurrences' => in_array( $status, array( 'changed', 'partial' ), true ) ? $this->occurrences : 0,
                'classes'     => $this->classes,
            );
        }
        if ( $this->logging ) {
            $out['log_data'] = in_array( $status, array( 'changed', 'partial' ), true ) ? $this->log_data : array();
        }
        return $out;
    }

    public function log_bits( string $old_data, int $before, int $after ): array {
        $search = null !== $this->pattern ? $this->pattern : $this->literal_log_pattern();
        if ( ! @preg_match_all( $search, $old_data, $old_matches, PREG_OFFSET_CAPTURE ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a PCRE error is answered as no excerpt.
            return array( array(), array() );
        }
        $replaced = $this->log_replace( $search, $old_data, $old_matches[0] );
        if ( null === $replaced ) {
            return array( array(), array() );
        }
        return self::log_excerpts(
            array( $old_data, $replaced['data'] ),
            array( $old_matches[0], $replaced['matches'] ),
            $replaced['count'],
            $before,
            $after
        );
    }

    private function log_replace( string $search, string $old_data, array $old_matches ): ?array {
        $i           = 0;
        $diff        = 0;
        $new_matches = array();
        $match_cnt   = 0;
        $new_data    = preg_replace_callback(
            $search,
            function ( array $matches ) use ( $old_matches, &$new_matches, &$i, &$diff ): string {
                $replacement       = $this->log_replacement( $matches );
                $new_matches[ $i ] = array( $replacement, $old_matches[ $i ][1] + $diff );
                $diff             += strlen( $replacement ) - strlen( $old_matches[ $i ][0] );
                ++$i;
                return $replacement;
            },
            $old_data,
            null !== $this->pattern ? $this->regex_limit : -1,
            $match_cnt
        );
        if ( null === $new_data ) {
            return null;
        }
        return array(
            'data'    => $new_data,
            'matches' => $new_matches,
            'count'   => $match_cnt,
        );
    }

    private function log_replacement( array $matches ): string {
        if ( null === $this->pattern ) {
            return $matches[0] === $this->old ? $this->new : $this->new_json;
        }
        $replacement = preg_replace_callback(
            '/(?<!\\\\)(?:\\\\\\\\)*((?:\\\\|\\$)[0-9]{1,2}|\\${[0-9]{1,2}\\})/',
            static function ( array $m ) use ( $matches ): string {
                $idx = (int) str_replace( array( '\\', '$', '{', '}' ), '', $m[0] );
                return isset( $matches[ $idx ] ) ? $matches[ $idx ] : '';
            },
            $this->new
        );
        return str_replace( '\\\\', '\\', (string) $replacement );
    }

    private static function log_excerpts( array $data, array $matches, int $count, int $before, int $after ): array {
        $bits        = array( array(), array() );
        $append_next = false;
        $last        = array( 0, 0 );
        for ( $i = 0; $i < $count; $i++ ) {
            $context = array( array( '', '' ), array( '', '' ) );
            if ( $before && $matches[0][ $i ][1] && ! $append_next ) {
                foreach ( array( 0, 1 ) as $side ) {
                    $context[ $side ][0] = self::safe_substr( (string) substr( $data[ $side ], $last[ $side ], $matches[ $side ][ $i ][1] - $last[ $side ] ), -$before, null );
                }
            }
            $shortened = false;
            if ( $after ) {
                list( $context[0][1], $context[1][1], $shortened ) = self::log_after_context( $data, $matches, $i, $count, $after );
            }
            foreach ( array( 0, 1 ) as $side ) {
                if ( $append_next ) {
                    $bits[ $side ][ count( $bits[ $side ] ) - 1 ] .= $matches[ $side ][ $i ][0] . $context[ $side ][1];
                } else {
                    $bits[ $side ][] = $context[ $side ][0] . $matches[ $side ][ $i ][0] . $context[ $side ][1];
                }
                $last[ $side ] = $matches[ $side ][ $i ][1];
            }
            $append_next = $shortened;
        }
        return $bits;
    }

    private static function log_after_context( array $data, array $matches, int $i, int $count, int $after ): array {
        $old_end_offset = $matches[0][ $i ][1] + strlen( $matches[0][ $i ][0] );
        $new_end_offset = $matches[1][ $i ][1] + strlen( $matches[1][ $i ][0] );
        $old_after      = self::safe_substr( (string) substr( $data[0], $old_end_offset ), 0, $after );
        $new_after      = self::safe_substr( (string) substr( $data[1], $new_end_offset ), 0, $after );
        if ( $i + 1 < $count && $old_end_offset + strlen( $old_after ) > $matches[0][ $i + 1 ][1] ) {
            return array(
                (string) substr( $old_after, 0, $matches[0][ $i + 1 ][1] - $old_end_offset ),
                (string) substr( $new_after, 0, $matches[1][ $i + 1 ][1] - $new_end_offset ),
                true,
            );
        }
        return array( $old_after, $new_after, false );
    }

    private function literal_log_pattern(): string {
        $parts = array( preg_quote( $this->old, '/' ) );
        if ( null !== $this->old_json ) {
            $parts[] = preg_quote( $this->old_json, '/' );
        }
        return '/' . implode( '|', $parts ) . '/';
    }

    private static function safe_substr( string $str, int $start, ?int $length ): string {
        $len = self::safe_strlen( $str );
        if ( null === $length ) {
            $length = $len;
        }
        if ( $start > $len ) {
            return '';
        }
        if ( $start < 0 && -$start > $len ) {
            $start = 0;
        }
        return self::substr_graphemes( $str, $start, $length );
    }

    private static function substr_graphemes( string $str, int $start, int $length ): string {
        if ( function_exists( 'grapheme_substr' ) ) {
            $try = grapheme_substr( $str, $start, $length );
            if ( false !== $try && null !== $try ) {
                return (string) $try;
            }
        }
        $parts = @preg_split( '/(\X)/u', $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid UTF-8 falls through to mb_substr().
        if ( false !== $parts && PREG_NO_ERROR === preg_last_error() ) {
            return implode( '', array_slice( $parts, $start, $length ) );
        }
        if ( function_exists( 'mb_substr' ) ) {
            return (string) mb_substr( $str, $start, $length, 'UTF-8' );
        }
        return (string) substr( $str, $start, $length );
    }

    private static function safe_strlen( string $str ): int {
        if ( function_exists( 'grapheme_strlen' ) ) {
            $len = grapheme_strlen( $str );
            if ( is_int( $len ) ) {
                return $len;
            }
        }
        $count = @preg_match_all( '/\X/u', $str ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid UTF-8 falls through.
        if ( false !== $count && PREG_NO_ERROR === preg_last_error() ) {
            return (int) $count;
        }
        return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $str, 'UTF-8' ) : strlen( $str );
    }

    public static function looks_serialized( string $value ): bool {
        if ( function_exists( 'is_serialized' ) && \is_serialized( $value ) ) {
            return true;
        }
        return (bool) preg_match( '/^C:\d+:"[^"]+":\d+:\{/', ltrim( $value ) );
    }

    private static function unserialize( string $value ) {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged -- allowed_classes restricts objects to stdClass; a corrupt value must not emit a notice into the response.
        return @unserialize( trim( $value ), array( 'allowed_classes' => array( 'stdClass' ) ) );
    }

    private static function round_trips( string $value ): bool {
        $data = self::unserialize( $value );
        if ( false === $data && ! self::is_serialized_false( $value ) ) {
            return false;
        }
        return serialize( $data ) === trim( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- comparing a round trip.
    }

    private static function is_serialized_false( string $value ): bool {
        return 'b:0;' === trim( $value );
    }
}
