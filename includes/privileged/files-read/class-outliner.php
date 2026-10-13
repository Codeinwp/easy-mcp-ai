<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









final class Outliner {

    const MAX_BYTES = 1048576;
    
    const MAX_ITEMS = 1000;
    const MAX_LABEL_CHARS = 200;

    const HOOK_CALLS = array(
        'add_action', 'add_filter', 'do_action', 'do_action_ref_array', 'do_action_deprecated',
        'apply_filters', 'apply_filters_ref_array', 'apply_filters_deprecated', 'remove_action',
        'remove_filter', 'remove_all_actions', 'remove_all_filters',
    );

    const ENQUEUE_CALLS = array(
        'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_script', 'wp_register_style',
        'wp_enqueue_script_module', 'wp_register_script_module',
    );

    
    const KINDS = array(
        'php'  => 'php',
        'inc'  => 'php',
        'css'  => 'css',
        'scss' => 'css',
        'less' => 'css',
        'js'   => 'js',
        'mjs'  => 'js',
        'cjs'  => 'js',
        'jsx'  => 'js',
        'ts'   => 'js',
        'tsx'  => 'js',
        'json' => 'json',
        'html' => 'html',
        'htm'  => 'html',
    );

    
    private $truncated = false;

    
    public static function kind( string $path ): ?string {
        $extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
        return self::KINDS[ $extension ] ?? null;
    }

    



    public function outline( string $kind, string $source ): array {
        $this->truncated = false;
        switch ( $kind ) {
            case 'php':
                $out = $this->php( $source );
                break;
            case 'css':
                $out = array( 'rules' => $this->css( $source ) );
                break;
            case 'js':
                $out = array( 'functions' => $this->js( $source ) );
                break;
            case 'json':
                $out = $this->json( $source );
                break;
            default:
                $out = array( 'blocks' => $this->html( $source ) );
        }
        return array( 'type' => $kind ) + $out + array( 'truncated' => $this->truncated );
    }

    
    
    

    
    private function php( string $source ): array {
        $tokens = self::significant( token_get_all( $source ) );
        $out    = array(
            'namespaces' => array(),
            'classes'    => array(),
            'functions'  => array(),
            'hooks'      => array(),
            'enqueues'   => array(),
        );
        
        
        $scope = array(
            'depth'   => 0,
            'stack'   => array(),
            'pending' => false,
        );
        foreach ( $tokens as $i => $t ) {
            if ( self::php_brace( $t, $scope ) || ! is_int( $t[0] ) ) {
                continue;
            }
            if ( T_NAMESPACE === $t[0] ) {
                $this->php_namespace( $tokens, $i, $out['namespaces'] );
                continue;
            }
            $kind = self::class_kind( $tokens, $i );
            if ( null !== $kind ) {
                $scope['pending'] = $this->php_class( $tokens, $i, $kind, $out['classes'] );
                continue;
            }
            if ( T_FUNCTION === $t[0] ) {
                $this->php_function( $tokens, $i, $scope, $out );
                continue;
            }
            $this->php_call( $tokens, $i, $out );
        }
        return $out;
    }

    





    private static function significant( array $raw ): array {
        $out  = array();
        $line = 1;
        foreach ( $raw as $t ) {
            if ( is_array( $t ) ) {
                $line = $t[2];
                if ( ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
                    $out[] = array( $t[0], $t[1], $t[2] );
                }
                $line += substr_count( $t[1], "\n" );
                continue;
            }
            $out[] = array( $t, $t, $line );
        }
        return $out;
    }

    







    private static function php_brace( array $t, array &$scope ): bool {
        if ( '{' === $t[0] || T_CURLY_OPEN === $t[0] || T_DOLLAR_OPEN_CURLY_BRACES === $t[0] ) {
            ++$scope['depth'];
            if ( false !== $scope['pending'] ) {
                $scope['stack'][] = array(
                    'index' => $scope['pending'],
                    'depth' => $scope['depth'],
                );
                $scope['pending'] = false;
            }
            return true;
        }
        if ( '}' !== $t[0] ) {
            return false;
        }
        $top = end( $scope['stack'] );
        if ( false !== $top && $top['depth'] === $scope['depth'] ) {
            array_pop( $scope['stack'] );
        }
        --$scope['depth'];
        return true;
    }

    





    private function php_namespace( array $tokens, int $i, array &$namespaces ): void {
        $name = self::name_at( $tokens, $i + 1 );
        if ( '' !== $name ) {
            $this->add( $namespaces, array( 'name' => self::label( $name ), 'line' => $tokens[ $i ][2] ) );
        }
    }

    







    private static function class_kind( array $tokens, int $i ): ?string {
        if ( $i > 0 && T_DOUBLE_COLON === $tokens[ $i - 1 ][0] ) {
            return null;
        }
        $kinds = array(
            T_CLASS     => 'class',
            T_INTERFACE => 'interface',
            T_TRAIT     => 'trait',
        );
        $id    = $tokens[ $i ][0];
        if ( is_int( $id ) && isset( $kinds[ $id ] ) ) {
            return $kinds[ $id ];
        }
        return self::is_enum( $tokens, $i ) ? 'enum' : null;
    }

    





    private static function is_enum( array $tokens, int $i ): bool {
        $id = $tokens[ $i ][0];
        if ( defined( 'T_ENUM' ) && constant( 'T_ENUM' ) === $id ) {
            return true;
        }
        if ( T_STRING !== $id || 'enum' !== strtolower( $tokens[ $i ][1] ) || ! isset( $tokens[ $i + 1 ], $tokens[ $i + 2 ] ) ) {
            return false;
        }
        return T_STRING === $tokens[ $i + 1 ][0] && in_array( $tokens[ $i + 2 ][0], array( '{', ':', T_IMPLEMENTS ), true );
    }

    







    private function php_class( array $tokens, int $i, string $kind, array &$classes ): ?int {
        if ( ! isset( $tokens[ $i + 1 ] ) || T_STRING !== $tokens[ $i + 1 ][0] ) {
            return null;
        }
        if ( ! $this->add( $classes, array( 'kind' => $kind, 'name' => self::label( $tokens[ $i + 1 ][1] ), 'line' => $tokens[ $i ][2], 'methods' => array() ) ) ) {
            return null;
        }
        return count( $classes ) - 1;
    }

    







    private function php_function( array $tokens, int $i, array $scope, array &$out ): void {
        $name = self::function_name( $tokens, $i );
        if ( null === $name ) {
            return;
        }
        $entry = array( 'name' => self::label( $name ), 'line' => $tokens[ $i ][2] );
        $top   = end( $scope['stack'] );
        if ( false === $top ) {
            $this->add( $out['functions'], $entry );
            return;
        }
        if ( $top['depth'] === $scope['depth'] && null !== $top['index'] ) {
            $this->add( $out['classes'][ $top['index'] ]['methods'], $entry );
        }
    }

    





    private static function function_name( array $tokens, int $i ): ?string {
        if ( $i > 0 && T_USE === $tokens[ $i - 1 ][0] ) {
            return null;
        }
        $j = $i + 1;
        if ( isset( $tokens[ $j ] ) && '&' === $tokens[ $j ][1] ) {
            ++$j; 
        }
        if ( ! isset( $tokens[ $j ] ) || ! is_int( $tokens[ $j ][0] ) ) {
            return null;
        }
        return preg_match( '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*\z/', $tokens[ $j ][1] ) ? $tokens[ $j ][1] : null;
    }

    





    private function php_call( array $tokens, int $i, array &$out ): void {
        $list = self::call_list( $tokens, $i );
        if ( null === $list || ! isset( $tokens[ $i + 2 ] ) || '(' !== $tokens[ $i + 1 ][0] || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $i + 2 ][0] ) {
            return;
        }
        $key = 'hooks' === $list ? 'hook' : 'handle';
        $this->add(
            $out[ $list ],
            array(
                'call' => strtolower( ltrim( $tokens[ $i ][1], '\\' ) ),
                $key   => self::label( substr( $tokens[ $i + 2 ][1], 1, -1 ) ),
                'line' => $tokens[ $i ][2],
            )
        );
    }

    






    private static function call_list( array $tokens, int $i ): ?string {
        $names = array( T_STRING );
        if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
            $names[] = constant( 'T_NAME_FULLY_QUALIFIED' );
        }
        if ( ! in_array( $tokens[ $i ][0], $names, true ) ) {
            return null;
        }
        if ( $i > 0 && in_array( $tokens[ $i - 1 ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ), true ) ) {
            return null;
        }
        $call = strtolower( ltrim( $tokens[ $i ][1], '\\' ) );
        if ( in_array( $call, self::HOOK_CALLS, true ) ) {
            return 'hooks';
        }
        return in_array( $call, self::ENQUEUE_CALLS, true ) ? 'enqueues' : null;
    }

    





    private static function name_at( array $tokens, int $i ): string {
        $parts = array( T_STRING, T_NS_SEPARATOR );
        if ( defined( 'T_NAME_QUALIFIED' ) ) {
            $parts[] = constant( 'T_NAME_QUALIFIED' );
        }
        if ( ! isset( $tokens[ $i ] ) || T_NS_SEPARATOR === $tokens[ $i ][0] ) {
            return '';
        }
        $name = '';
        for ( ; isset( $tokens[ $i ] ) && in_array( $tokens[ $i ][0], $parts, true ); $i++ ) {
            $name .= $tokens[ $i ][1];
        }
        return $name;
    }

    
    
    

    





    private function css( string $source ): array {
        
        $source = (string) preg_replace_callback(
            '#/\*.*?(?:\*/|\z)#s',
            static function ( array $m ): string {
                return str_repeat( "\n", substr_count( $m[0], "\n" ) );
            },
            $source
        );
        $state  = array(
            'rules'  => array(),
            'stack'  => array(), 
            'buffer' => '',
            'line'   => 1,
            'start'  => 1,
        );
        $length = strlen( $source );
        for ( $k = 0; $k < $length; $k++ ) {
            $this->css_char( $source[ $k ], $state );
        }
        return $state['rules'];
    }

    




    private function css_char( string $c, array &$state ): void {
        if ( "\n" === $c ) {
            ++$state['line'];
        }
        if ( '{' === $c ) {
            $this->css_open( $state );
            return;
        }
        if ( '}' === $c || ';' === $c ) {
            if ( '}' === $c ) {
                array_pop( $state['stack'] );
            }
            $state['buffer'] = '';
            return;
        }
        if ( '' === trim( $state['buffer'] ) && ! ctype_space( $c ) ) {
            $state['start'] = $state['line'];
        }
        $state['buffer'] .= $c;
    }

    




    private function css_open( array &$state ): void {
        $text             = trim( (string) preg_replace( '/\s+/', ' ', $state['buffer'] ) );
        $listed           = ! in_array( false, $state['stack'], true );
        $state['stack'][] = '' !== $text && '@' === $text[0];
        if ( $listed && '' !== $text ) {
            $this->add( $state['rules'], array( 'selector' => self::label( $text ), 'line' => $state['start'] ) );
        }
        $state['buffer'] = '';
    }

    





    private function js( string $source ): array {
        $functions = array();
        foreach ( preg_split( '/\r\n|\n|\r/', $source ) as $index => $text ) {
            if ( preg_match( '/^(?:export\s+(?:default\s+)?)?(?:async\s+)?function\s*\*?\s*([A-Za-z_$][\w$]*)/', $text, $m )
                || preg_match( '/^(?:export\s+)?(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(?:async\s+)?(?:function\b|\([^)]*\)\s*=>|[A-Za-z_$][\w$]*\s*=>)/', $text, $m ) ) {
                $this->add( $functions, array( 'name' => self::label( $m[1] ), 'line' => $index + 1 ) );
            }
        }
        return $functions;
    }

    








    private function json( string $source ): array {
        if ( ! is_object( json_decode( $source ) ) ) {
            return array(
                'keys'  => array(),
                'error' => 'Not a JSON object; nothing to outline.',
            );
        }
        $keys  = array();
        $depth = 0;
        $line  = 1;
        $len   = strlen( $source );
        for ( $k = 0; $k < $len; $k++ ) {
            $c = $source[ $k ];
            if ( '"' === $c ) {
                $end = self::json_string_end( $source, $k );
                if ( 1 === $depth && self::json_colon_follows( $source, $end + 1 ) ) {
                    $this->add( $keys, array( 'key' => self::label( (string) json_decode( substr( $source, $k, $end - $k + 1 ) ) ), 'line' => $line ) );
                }
                $k = $end;
                continue;
            }
            $line  += "\n" === $c ? 1 : 0;
            $depth += self::json_depth_change( $c );
        }
        return array( 'keys' => $keys );
    }

    
    private static function json_string_end( string $source, int $start ): int {
        $len = strlen( $source );
        for ( $k = $start + 1; $k < $len; $k++ ) {
            if ( '\\' === $source[ $k ] ) {
                ++$k;
            } elseif ( '"' === $source[ $k ] ) {
                return $k;
            }
        }
        return $len - 1;
    }

    
    private static function json_colon_follows( string $source, int $from ): bool {
        $rest = ltrim( substr( $source, $from ), " \t\r\n" );
        return '' !== $rest && ':' === $rest[0];
    }

    
    private static function json_depth_change( string $c ): int {
        if ( '{' === $c || '[' === $c ) {
            return 1;
        }
        return ( '}' === $c || ']' === $c ) ? -1 : 0;
    }

    




    private function html( string $source ): array {
        $blocks = array();
        foreach ( preg_split( '/\r\n|\n|\r/', $source ) as $index => $text ) {
            if ( preg_match_all( '#<!--\s+wp:([a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*)?)#', $text, $m ) ) {
                foreach ( $m[1] as $name ) {
                    $this->add( $blocks, array( 'name' => self::label( $name ), 'line' => $index + 1 ) );
                }
            }
        }
        return $blocks;
    }

    
    
    

    





    private function add( array &$list, array $entry ): bool {
        if ( count( $list ) >= self::MAX_ITEMS ) {
            $this->truncated = true;
            return false;
        }
        $list[] = $entry;
        return true;
    }

    




    private static function label( string $text ): string {
        $text = Text_File::safe( $text );
        return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_LABEL_CHARS, 'UTF-8' ) : substr( $text, 0, self::MAX_LABEL_CHARS );
    }
}
