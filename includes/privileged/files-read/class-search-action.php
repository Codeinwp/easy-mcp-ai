<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Search_Action extends Action {

    const ARGUMENTS = array( 'text', 'path', 'regex', 'case_sensitive', 'extensions', 'max_results' );

    const DEFAULT_RESULTS = 100;
    const MAX_RESULTS = 500;

    private $searcher;

    public function __construct( Resolver $resolver, ?Searcher $searcher = null ) {
        parent::__construct( $resolver );
        $this->searcher = $searcher;
    }

    public function run( array $arguments ): array {
        $text        = self::string_argument( $arguments, 'text', '' );
        $pattern     = Searcher::pattern( $text, self::bool_argument( $arguments, 'regex', false ), self::bool_argument( $arguments, 'case_sensitive', false ) );
        $max_results = self::int_argument( $arguments, 'max_results', self::DEFAULT_RESULTS, 1, self::MAX_RESULTS );
        $extensions  = $this->extensions( $arguments );
        $path        = self::string_argument( $arguments, 'path', self::default_dir() );
        $is_dir      = true;
        try {
            $target = $this->resolver->resolve( $path, true );
        } catch ( Refusal $e ) {
            $target = $this->resolver->resolve( $path, false );
            $is_dir = false;
        }
        $searcher = $this->searcher ?? new Searcher( $this->resolver );
        $found    = $searcher->search( $target, $is_dir, $pattern, $extensions, $max_results );
        return self::fit_list(
            array(
                'path'             => $target['rel'],
                'matches'          => $found['matches'],
                'files_scanned'    => $found['files_scanned'],
                'truncated'        => $found['truncated'],
                'budget_exhausted' => $found['budget_exhausted'],
                'skipped'          => (object) $found['skipped'],
            ),
            'matches'
        );
    }

    private function extensions( array $arguments ): array {
        $allowed = $this->resolver->extensions();
        if ( ! isset( $arguments['extensions'] ) || array() === $arguments['extensions'] ) {
            return $allowed;
        }
        if ( ! is_array( $arguments['extensions'] ) ) {
            throw new \InvalidArgumentException( 'extensions must be a list of file types, e.g. ["php", "js"].' );
        }
        $out = array();
        foreach ( $arguments['extensions'] as $extension ) {
            $extension = is_string( $extension ) ? strtolower( ltrim( trim( $extension ), '.' ) ) : '';
            if ( ! in_array( $extension, $allowed, true ) ) {
                throw new \InvalidArgumentException( sprintf( 'extensions may only name readable file types: %s.', implode( ', ', $allowed ) ) );
            }
            $out[] = $extension;
        }
        return array_values( array_unique( $out ) );
    }
}
