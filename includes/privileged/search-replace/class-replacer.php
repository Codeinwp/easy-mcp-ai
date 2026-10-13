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

    public function __construct( string $old, string $new ) {
        $this->old      = $old;
        $this->new      = $new;
        $old_json       = self::json_inner( $old );
        $this->old_json = $old_json !== $old ? $old_json : null;
        $this->new_json = self::json_inner( $new );
    }

    




    public static function json_inner( string $value ): string {
        $encoded = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact default-flag form PHP and WP-CLI produce.
        return false !== $encoded ? substr( $encoded, 1, -1 ) : $value;
    }

    




    public function needles(): array {
        return null === $this->old_json ? array( $this->old ) : array( $this->old, $this->old_json );
    }

    









    public function replace( string $value ): array {
        $this->occurrences     = 0;
        $this->classes         = array();
        $this->too_deep        = false;
        $this->unsafe          = false;
        $this->objects_left    = 0;
        $this->seen_objects    = array();
        $this->seen_references = array();

        if ( self::looks_serialized( $value ) ) {
            $data = self::unserialize( $value );
            if ( false === $data && ! self::is_serialized_false( $value ) ) {
                return $this->result( $value, 'corrupt' );
            }
            $walked = $this->walk( $data, 1 );
            if ( $this->too_deep ) {
                return $this->result( $value, 'too_deep' );
            }
            if ( $this->unsafe || ( $this->occurrences > 0 && $this->classes && ! self::round_trips( $value ) ) ) {
                return $this->result( $value, 'unsafe_object' );
            }
            if ( 0 === $this->occurrences ) {
                return $this->result( $value, $this->classes ? 'partial_unchanged' : 'unchanged' );
            }
            $out = serialize( $walked ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- rewriting a serialized value in place.
            
            
            $back = self::unserialize( $out );
            if ( ( false === $back && ! self::is_serialized_false( $out ) ) || gettype( $back ) !== gettype( $data ) ) {
                return $this->result( $value, 'corrupt' );
            }
            return $this->result( $out, $this->classes ? 'partial' : 'changed' );
        }

        $out = $this->replace_string( $value );
        return $this->result( $out, $out === $value ? 'unchanged' : 'changed' );
    }

    



    private function walk( $data, int $depth ) {
        if ( $depth > self::MAX_DEPTH ) {
            $this->too_deep = true;
            return $data;
        }
        if ( is_string( $data ) ) {
            if ( self::looks_serialized( $data ) ) {
                $inner = self::unserialize( $data );
                if ( false !== $inner || self::is_serialized_false( $data ) ) {
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
                
            }
            return $this->replace_string( $data );
        }
        if ( is_array( $data ) ) {
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
        if ( $data instanceof \__PHP_Incomplete_Class ) {
            $props = (array) $data;
            $name  = isset( $props['__PHP_Incomplete_Class_Name'] ) ? (string) $props['__PHP_Incomplete_Class_Name'] : '';
            $this->note_class( $name );
            return $data;
        }
        if ( $data instanceof \stdClass ) {
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
        if ( is_object( $data ) ) {
            
            
            $this->note_class( get_class( $data ) );
        }
        return $data;
    }

    private function replace_string( string $value ): string {
        $count = 0;
        $out   = str_replace( $this->old, $this->new, $value, $count );
        $this->occurrences += $count;
        if ( null !== $this->old_json ) {
            $json_count = 0;
            $out        = str_replace( $this->old_json, $this->new_json, $out, $json_count );
            $this->occurrences += $json_count;
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
            return array( 'value' => $value, 'status' => $status, 'occurrences' => 0, 'classes' => $this->classes );
        }
        return array(
            'value'       => $value,
            'status'      => $status,
            'occurrences' => in_array( $status, array( 'changed', 'partial' ), true ) ? $this->occurrences : 0,
            'classes'     => $this->classes,
        );
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
