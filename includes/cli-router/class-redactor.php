<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}













































final class Redactor {

    const MASK = '[REDACTED]';

    
    const SECRET_POSITIONALS = array(
        'user check-password'  => array( 1 ),
        'user session destroy' => array( 1 ),
    );

    






    const KEYED_VALUES = array(
        'option add'          => array( 0, 1 ),
        'option update'       => array( 0, 1 ),
        'option patch'        => array( 1, 2 ),
        'site option add'     => array( 0, 1 ),
        'site option update'  => array( 0, 1 ),
        'site option patch'   => array( 1, 2 ),
        'transient set'       => array( 0, 1 ),
        'transient patch'     => array( 1, 2 ),
        'theme mod set'       => array( 0, 1 ),
        'cache add'           => array( 0, 1 ),
        'cache set'           => array( 0, 1 ),
        'cache replace'       => array( 0, 1 ),
        'cache patch'         => array( 1, 2 ),
        'config set'          => array( 0, 1 ),
        'post meta add'       => array( 1, 2 ),
        'post meta update'    => array( 1, 2 ),
        'post meta delete'    => array( 1, 2 ),
        'post meta patch'     => array( 2, 3 ),
        'user meta add'       => array( 1, 2 ),
        'user meta update'    => array( 1, 2 ),
        'user meta delete'    => array( 1, 2 ),
        'user meta patch'     => array( 2, 3 ),
        'term meta add'       => array( 1, 2 ),
        'term meta update'    => array( 1, 2 ),
        'term meta delete'    => array( 1, 2 ),
        'term meta patch'     => array( 2, 3 ),
        'comment meta add'    => array( 1, 2 ),
        'comment meta update' => array( 1, 2 ),
        'comment meta delete' => array( 1, 2 ),
        'comment meta patch'  => array( 2, 3 ),
        'network meta add'    => array( 1, 2 ),
        'network meta update' => array( 1, 2 ),
        'network meta delete' => array( 1, 2 ),
        'network meta patch'  => array( 2, 3 ),
        'site meta add'       => array( 1, 2 ),
        'site meta update'    => array( 1, 2 ),
        'site meta delete'    => array( 1, 2 ),
        'site meta patch'     => array( 2, 3 ),
    );

    
    const URL_POSITIONALS = array(
        'media import'         => 0,
        'embed fetch'          => 0,
        'embed provider match' => 0,
    );

    


    public static function mask_command( string $command ): array {
        self::load_dependencies();
        try {
            $words = Shell_Lexer::split( $command );
        } catch ( Cli_Parse_Error $e ) {
            return self::fail_closed( $command );
        }

        $names = array();
        try {
            $replace = self::positional_replacements( $words, $names );
        } catch ( Cli_Parse_Error $e ) {
            return self::fail_closed_refused( $words );
        }

        $out = array();
        foreach ( $words as $word ) {
            $out[] = self::mask_word( (string) $word, $replace, $names );
        }

        $text = \Easy_MCP_AI\Text_Redactor::redact( implode( ' ', $out ) );
        return array( $text, array_values( array_unique( $names ) ) );
    }

    







    private static function mask_word( string $word, array $replace, array &$names ): string {
        if ( preg_match( '|^--([^=]+)=(.*)\z|s', $word, $m ) ) {
            return self::mask_flag_value( $word, $m[1], $m[2], $names );
        }
        if ( ! self::is_assoc( $word ) && isset( $replace[ $word ] ) ) {
            return $replace[ $word ];
        }
        if ( ! self::is_assoc( $word ) && self::json_has_secret_key( $word ) ) {
            $names[] = 'JSON with a secret-named key';
            return self::MASK;
        }
        return self::quote( $word );
    }

    






    private static function mask_flag_value( string $word, string $name, string $value, array &$names ): string {
        if ( '' !== $value && preg_match( \Easy_MCP_AI\Text_Redactor::SENSITIVE_KEY_PATTERN, $name ) ) {
            $names[] = '--' . $name;
            return self::quote( '--' . $name . '=' ) . self::MASK;
        }
        if ( self::json_has_secret_key( $value ) ) {
            $names[] = '--' . $name . ' (JSON with a secret-named key)';
            return self::quote( '--' . $name . '=' ) . self::MASK;
        }
        return self::quote( $word );
    }

    








    private static function positional_replacements( array $words, array &$names ): array {
        $found = self::find_leaf( $words );
        if ( null === $found ) {
            return array();
        }
        $command = (string) $found['leaf']['command'];
        $rest    = array_values( array_map( 'strval', $found['rest'] ) );

        
        
        return array_replace(
            self::secret_positionals( $command, $found['leaf'], $rest, $names ),
            self::keyed_value_secrets( $command, $rest, $names ),
            self::url_secrets( $command, $rest, $names )
        );
    }

    









    private static function find_leaf( array $words ): ?array {
        try {
            $globals = Catalog::global_parameters();
        } catch ( \RuntimeException $e ) {
            return null; 
        }
        $parsed     = Argv::parse( $words, $globals );
        $positional = array_map( 'strval', $parsed['positional'] );
        if ( count( $positional ) > 1 && 'help' === $positional[0] ) {
            array_shift( $positional );
        }
        try {
            $found = Catalog::find( $positional );
        } catch ( \RuntimeException $e ) {
            return null; 
        }
        return null === $found['leaf'] ? null : $found;
    }

    








    private static function secret_positionals( string $command, array $leaf, array $rest, array &$names ): array {
        if ( ! isset( self::SECRET_POSITIONALS[ $command ] ) ) {
            return array();
        }
        $params  = self::positional_names( $leaf );
        $replace = array();
        foreach ( self::SECRET_POSITIONALS[ $command ] as $index ) {
            if ( isset( $rest[ $index ] ) && '' !== $rest[ $index ] ) {
                $replace[ $rest[ $index ] ] = self::MASK;
                $names[]                    = '<' . ( isset( $params[ $index ] ) ? $params[ $index ] : 'secret' ) . '>';
            }
        }
        return $replace;
    }

    







    private static function keyed_value_secrets( string $command, array $rest, array &$names ): array {
        if ( ! isset( self::KEYED_VALUES[ $command ] ) ) {
            return array();
        }
        list( $key_index, $value_index ) = self::KEYED_VALUES[ $command ];
        if ( ! isset( $rest[ $key_index ] ) ) {
            return array();
        }
        if ( self::key_is_secret( $rest[ $key_index ] ) ) {
            return self::secret_key_values( $rest, $key_index, $value_index, $names );
        }
        return self::is_patch( $command ) ? self::secret_path_value( $rest, $key_index, $value_index, $names ) : array();
    }

    







    private static function secret_key_values( array $rest, int $key_index, int $value_index, array &$names ): array {
        $replace = array();
        foreach ( array_slice( $rest, $value_index ) as $value ) {
            if ( '' !== $value && $value !== $rest[ $key_index ] ) {
                $replace[ $value ] = self::MASK;
            }
        }
        if ( $replace ) {
            $names[] = "value of '" . $rest[ $key_index ] . "'";
        }
        return $replace;
    }

    









    private static function secret_path_value( array $rest, int $key_index, int $value_index, array &$names ): array {
        $tail  = array_slice( $rest, $value_index );
        $value = 'delete' !== $rest[0] && count( $tail ) >= 2 ? (string) array_pop( $tail ) : '';
        if ( '' === $value ) {
            return array();
        }
        foreach ( $tail as $element ) {
            if ( self::key_is_secret( $element ) ) {
                $names[] = "value of '" . implode( ' ', array_merge( array( $rest[ $key_index ] ), $tail ) ) . "'";
                return array( $value => self::MASK );
            }
        }
        return array();
    }

    






    private static function url_secrets( string $command, array $rest, array &$names ): array {
        if ( ! isset( self::URL_POSITIONALS[ $command ] ) ) {
            return array();
        }
        $replace = array();
        foreach ( array_slice( $rest, self::URL_POSITIONALS[ $command ] ) as $value ) {
            $stripped = self::strip_url_secrets( $value );
            if ( null !== $stripped ) {
                $replace[ $value ] = $stripped;
                $names[]           = 'url query';
            }
        }
        return $replace;
    }

    
    private static function key_is_secret( string $key ): bool {
        if ( preg_match( \Easy_MCP_AI\Text_Redactor::SENSITIVE_KEY_PATTERN, $key ) ) {
            return true;
        }
        return class_exists( '\\Easy_MCP_AI\\History\\Change_Redactor' ) && \Easy_MCP_AI\History\Change_Redactor::option_name_is_sensitive( $key );
    }

    
    private static function is_patch( string $command ): bool {
        return ' patch' === substr( $command, -6 );
    }

    



    private static function json_has_secret_key( string $word ): bool {
        if ( '' === $word || ( '{' !== $word[0] && '[' !== $word[0] ) ) {
            return false;
        }
        $decoded = json_decode( $word, true );
        return is_array( $decoded ) && self::has_secret_key( $decoded );
    }

    




    private static function has_secret_key( array $data ): bool {
        foreach ( $data as $key => $value ) {
            if ( is_string( $key ) && self::key_is_secret( $key ) ) {
                return true;
            }
            if ( is_array( $value ) && self::has_secret_key( $value ) ) {
                return true;
            }
        }
        return false;
    }

    



    private static function strip_url_secrets( string $value ): ?string {
        if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $value ) ) {
            return null;
        }
        $cut = strcspn( $value, '?#' );
        if ( $cut >= strlen( $value ) ) {
            return null;
        }
        return self::quote( substr( $value, 0, $cut + 1 ) ) . self::MASK;
    }

    





    private static function fail_closed( string $command ): array {
        $plain = array();
        foreach ( preg_split( '/[ \t]+/', trim( $command ) ) as $word ) {
            if ( ! preg_match( '/^[a-z][a-z0-9-]*\z/', (string) $word ) || count( $plain ) >= 6 ) {
                break;
            }
            $plain[] = (string) $word;
        }
        if ( isset( $plain[0] ) && 'wp' === $plain[0] ) {
            array_shift( $plain );
        }
        $path = array();
        try {
            $found = Catalog::find( $plain );
            $path  = $found['path'];
        } catch ( \RuntimeException $e ) {
            $path = array();
        }
        $kept = $path ? implode( ' ', $path ) . ' ' : '';
        return array( $kept . self::MASK, array( 'unparseable' ) );
    }

    








    private static function fail_closed_refused( array $words ): array {
        $kept  = array();
        $names = array();
        $at    = 0;
        foreach ( $words as $word ) {
            $word = (string) $word;
            if ( preg_match( Argv::ENV_ASSIGNMENT, $word, $m ) ) {
                $kept[]  = self::quote( $m[0] ) . self::MASK;
                $names[] = 'environment assignment';
            } elseif ( 'wp' === $word || preg_match( '#' . Argv::ALIAS_REGEX . '#', $word ) ) {
                $kept[] = self::quote( $word );
            } else {
                break;
            }
            ++$at;
        }
        list( $rest ) = self::fail_closed( implode( ' ', array_slice( $words, $at ) ) );
        $kept[]       = $rest;
        $names[]      = 'unparseable';
        return array( \Easy_MCP_AI\Text_Redactor::redact( implode( ' ', $kept ) ), array_values( array_unique( $names ) ) );
    }

    





    private static function positional_names( array $leaf ): array {
        $names = array();
        foreach ( isset( $leaf['params'] ) ? (array) $leaf['params'] : array() as $param ) {
            if ( isset( $param['type'], $param['name'] ) && 'positional' === $param['type'] ) {
                $names[] = (string) $param['name'];
            }
        }
        return $names;
    }

    
    private static function is_assoc( string $word ): bool {
        return 1 === preg_match( '|^--[^=]|', $word );
    }

    
    private static function quote( string $word ): string {
        if ( '' === $word ) {
            return "''";
        }
        if ( preg_match( '#^[A-Za-z0-9_\-.,:/@%+=]+\z#', $word ) ) {
            return $word;
        }
        return "'" . str_replace( "'", "'\\''", $word ) . "'";
    }

    
    private static function load_dependencies(): void {
        if ( ! class_exists( '\\Easy_MCP_AI\\Text_Redactor' ) ) {
            require_once dirname( __DIR__ ) . '/class-text-redactor.php';
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\History\\Change_Redactor' ) && is_readable( dirname( __DIR__ ) . '/history/class-change-redactor.php' ) ) {
            require_once dirname( __DIR__ ) . '/history/class-change-redactor.php';
        }
    }
}
