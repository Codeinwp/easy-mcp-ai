<?php
namespace Easy_MCP_AI\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Change_Context {
    private static $data   = array();
    private static $active = false;

    public static function set( array $fields ) {
        self::$data   = array_merge( self::$data, $fields );
        self::$active = true;
    }

    public static function get( $key, $default = null ) {
        return isset( self::$data[ $key ] ) ? self::$data[ $key ] : $default;
    }

    public static function all() {
        return self::$data;
    }

    public static function is_active() {
        return self::$active;
    }

    public static function clear() {
        self::$data   = array();
        self::$active = false;
    }

    








    public static function note_recorded_fields( $type, $id, array $fields = array() ) {
        if ( ! self::$active ) {
            return;
        }
        $key = (string) $type . ':' . (string) $id;
        $cur = self::$data['_recorded_fields'][ $key ] ?? array();
        
        
        
        
        self::$data['_recorded_fields'][ $key ] = array_values( array_unique( array_merge( $cur, array_map( 'strval', $fields ) ) ) );
    }

    



    public static function object_was_recorded( $type, $id ) {
        return isset( self::$data['_recorded_fields'][ (string) $type . ':' . (string) $id ] );
    }

    
    public static function recorded_fields( $type, $id ) {
        return self::$data['_recorded_fields'][ (string) $type . ':' . (string) $id ] ?? array();
    }

    




    public static function increment_rows_written() {
        if ( ! self::$active ) {
            return;
        }
        self::$data['_rows_written'] = self::rows_written() + 1;
    }

    public static function rows_written() {
        return (int) ( self::$data['_rows_written'] ?? 0 );
    }

    













    public static function expect_refresh_only( $type, $id ) {
        if ( ! self::$active ) {
            return;
        }
        self::$data['_refresh_only'][ (string) $type . ':' . (string) $id ] = true;
    }

    






    public static function consume_refresh_only( $type, $id ) {
        $key = (string) $type . ':' . (string) $id;
        if ( empty( self::$data['_refresh_only'][ $key ] ) ) {
            return false;
        }
        unset( self::$data['_refresh_only'][ $key ] );
        return true;
    }

    











    public static function expect_scoped_option( $name, array $path ) {
        if ( ! self::$active ) {
            return;
        }
        self::$data['_scoped_option'][ (string) $name ] = array_map( 'strval', array_values( $path ) );
    }

    





    public static function consume_scoped_option( $name ) {
        $name = (string) $name;
        if ( empty( self::$data['_scoped_option'][ $name ] ) ) {
            return null;
        }
        $path = self::$data['_scoped_option'][ $name ];
        unset( self::$data['_scoped_option'][ $name ] );
        return $path;
    }

    



















    public static function note_prior_roles( $user_id, array $roles ) {
        if ( ! self::$active ) {
            return;
        }
        self::$data['_prior_roles'][ (int) $user_id ] = array_values( $roles );
    }

    
    public static function prior_roles( $user_id ) {
        return self::$data['_prior_roles'][ (int) $user_id ] ?? null;
    }
}
