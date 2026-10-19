<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






final class List_Action extends Action {

    const ARGUMENTS = array( 'path', 'recursive', 'max_depth', 'glob' );

    const MAX_ENTRIES = 5000;
    const DEFAULT_DEPTH = 5;
    const MAX_DEPTH = 10;
    const BUDGET_SECONDS = 20.0;

    public function run( array $arguments ): array {
        $path      = self::string_argument( $arguments, 'path', self::default_dir() );
        $recursive = self::bool_argument( $arguments, 'recursive', false );
        $depth     = self::int_argument( $arguments, 'max_depth', self::DEFAULT_DEPTH, 1, self::MAX_DEPTH );
        $glob      = strtolower( trim( self::string_argument( $arguments, 'glob', '' ) ) );
        $dir       = $this->resolver->resolve( $path, true );
        $prefix    = '' === $dir['rel'] ? '' : $dir['rel'] . '/';
        $entries   = array();
        $skipped   = array();
        $visit     = static function ( array $entry ) use ( $glob, $prefix, &$entries ): bool {
            if ( '' === $glob || self::glob_matches( $glob, $entry['path'], $prefix ) ) {
                $entries[] = $entry;
            }
            return true;
        };
        $complete = $this->resolver->walk( $dir, $recursive ? $depth : 1, self::MAX_ENTRIES, microtime( true ) + self::BUDGET_SECONDS, $visit, $skipped );
        return self::fit_list(
            array(
                'path'      => $dir['rel'],
                'entries'   => $entries,
                'truncated' => ! $complete,
                'skipped'   => (object) $skipped,
            ),
            'entries'
        );
    }

    
    private static function glob_matches( string $glob, string $path, string $prefix ): bool {
        $subject = false !== strpos( $glob, '/' ) ? substr( $path, strlen( $prefix ) ) : basename( $path );
        $subject = strtolower( $subject );
        if ( function_exists( 'fnmatch' ) ) {
            return fnmatch( $glob, $subject );
        }
        $regex = '/^' . strtr( preg_quote( $glob, '/' ), array( '\*' => '.*', '\?' => '.' ) ) . '\z/s';
        return 1 === preg_match( $regex, $subject );
    }
}
