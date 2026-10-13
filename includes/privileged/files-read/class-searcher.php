<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}











final class Searcher {

    
    const MAX_FILE_BYTES = 2097152;
    const MAX_FILES = 5000;
    
    const BUDGET_SECONDS = 20.0;
    
    const MAX_ENTRIES = 50000;
    const MAX_DEPTH = 20;
    const EXCERPT_CHARS = 200;
    
    const EXCERPT_LEAD = 60;
    
    const DELIMITER = "\x01";

    
    private $resolver;
    
    private $budget;
    
    private $max_files;

    public function __construct( Resolver $resolver, float $budget = self::BUDGET_SECONDS, int $max_files = self::MAX_FILES ) {
        $this->resolver  = $resolver;
        $this->budget    = $budget;
        $this->max_files = $max_files;
    }

    








    public static function pattern( string $text, bool $regex, bool $case_sensitive ): string {
        if ( '' === $text ) {
            throw new \InvalidArgumentException( 'text is required.' );
        }
        $body    = $regex ? $text : preg_quote( $text, self::DELIMITER );
        $pattern = self::DELIMITER . $body . self::DELIMITER . 'u' . ( $case_sensitive ? '' : 'i' );
        if ( false === @preg_match( $pattern, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid pattern is the caller's input; answered below.
            throw new \InvalidArgumentException( 'text is not a valid regular expression (PCRE syntax, without delimiters or modifiers).' );
        }
        return $pattern;
    }

    







    public function search( array $target, bool $is_dir, string $pattern, array $extensions, int $max_results ): array {
        $state = array(
            'matches'          => array(),
            'files_scanned'    => 0,
            'truncated'        => false,
            'budget_exhausted' => false,
            'skipped'          => array(),
            'redacted_lines'   => 0,
        );
        $deadline = microtime( true ) + $this->budget;
        if ( ! $is_dir ) {
            $entry = array(
                'path'     => $target['rel'],
                'type'     => 'file',
                'size'     => (int) @filesize( $target['abs'] ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                'modified' => '',
                'readable' => true, 
            );
            if ( $this->wanted( $entry, $extensions, $state['skipped'] ) ) {
                $this->scan_file( $target, $pattern, $max_results, $deadline, $state );
            }
            return $state;
        }
        $visit    = function ( array $entry, string $abs ) use ( $pattern, $extensions, $max_results, $deadline, &$state ): bool {
            if ( 'file' !== $entry['type'] ) {
                return true;
            }
            if ( ! $this->wanted( $entry, $extensions, $state['skipped'] ) ) {
                return true;
            }
            if ( $state['files_scanned'] >= $this->max_files ) {
                $state['budget_exhausted'] = true;
                return false;
            }
            return $this->scan_file( array( 'abs' => $abs, 'rel' => $entry['path'] ), $pattern, $max_results, $deadline, $state );
        };
        $complete = $this->resolver->walk( $target, self::MAX_DEPTH, self::MAX_ENTRIES, $deadline, $visit, $state['skipped'] );
        if ( ! $complete && ! $state['truncated'] ) {
            $state['budget_exhausted'] = true;
        }
        return $state;
    }

    




    private function wanted( array $entry, array $extensions, array &$skipped ): bool {
        $extension = strtolower( (string) pathinfo( $entry['path'], PATHINFO_EXTENSION ) );
        if ( ! $entry['readable'] || ! in_array( $extension, $extensions, true ) ) {
            $skipped['extension'] = ( $skipped['extension'] ?? 0 ) + 1;
            return false;
        }
        if ( $entry['size'] > self::MAX_FILE_BYTES ) {
            $skipped['too_large'] = ( $skipped['too_large'] ?? 0 ) + 1;
            return false;
        }
        return true;
    }

    





    private function scan_file( array $file, string $pattern, int $max_results, float $deadline, array &$state ): bool {
        try {
            $text = Text_File::open( $file );
        } catch ( Refusal $e ) {
            
            $state['skipped']['unreadable'] = ( $state['skipped']['unreadable'] ?? 0 ) + 1;
            return true;
        }
        if ( $text->is_binary() ) {
            $state['skipped']['binary'] = ( $state['skipped']['binary'] ?? 0 ) + 1;
            return true;
        }
        ++$state['files_scanned'];
        $number = 0;
        try {
            while ( null !== ( $line = $text->next_line() ) ) {
                ++$number;
                if ( microtime( true ) >= $deadline ) {
                    $state['budget_exhausted'] = true;
                    return false;
                }
                if ( ! $this->match_line( $pattern, $line, $file['rel'], $number, $state ) ) {
                    continue;
                }
                if ( count( $state['matches'] ) >= $max_results ) {
                    $state['truncated'] = true;
                    return false;
                }
            }
        } finally {
            $state['redacted_lines'] += $text->redacted_lines;
            $text->close();
        }
        return true;
    }

    





    private function match_line( string $pattern, string $line, string $path, int $number, array &$state ): bool {
        $found = preg_match( $pattern, $line, $m, PREG_OFFSET_CAPTURE );
        if ( false === $found || PREG_NO_ERROR !== preg_last_error() ) {
            throw new \RuntimeException( sprintf( 'The pattern exceeded PCRE\'s backtracking or recursion limit on %s line %d; nothing is returned. Simplify it (avoid nested quantifiers such as (a+)+).', $path, $number ) );
        }
        if ( 1 !== $found ) {
            return false;
        }
        $state['matches'][] = array(
            'path'    => $path,
            'line'    => $number,
            'excerpt' => self::excerpt( $line, (int) $m[0][1] ),
        );
        return true;
    }

    
    public static function excerpt( string $line, int $byte_offset ): string {
        $byte_offset = max( 0, $byte_offset - ( strlen( $line ) - strlen( ltrim( $line ) ) ) );
        $line        = trim( $line );
        if ( ! function_exists( 'mb_substr' ) ) {
            return substr( $line, 0, self::EXCERPT_CHARS );
        }
        $offset = mb_strlen( substr( $line, 0, $byte_offset ), 'UTF-8' );
        $start  = max( 0, $offset - self::EXCERPT_LEAD );
        return mb_substr( $line, $start, self::EXCERPT_CHARS, 'UTF-8' );
    }
}
