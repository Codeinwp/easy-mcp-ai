<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






final class Outline_Action extends Action {

    const ARGUMENTS = array( 'path' );

    public function run( array $arguments ): array {
        $path = self::string_argument( $arguments, 'path', '' );
        if ( '' === trim( $path ) ) {
            throw new \InvalidArgumentException( 'path is required: a file relative to wp-content.' );
        }
        $file = $this->resolver->resolve( $path, false );
        $kind = Outliner::kind( $file['rel'] );
        if ( null === $kind ) {
            throw new \InvalidArgumentException( 'No outline for this file type. Outlines cover php, inc, css, scss, less, js, mjs, cjs, jsx, ts, tsx, json, html and htm; read other files with the read action.' );
        }
        $text = Text_File::open( $file );
        try {
            if ( $text->size() > Outliner::MAX_BYTES ) {
                throw new \RuntimeException( sprintf( '%1$s is %2$d KB, over the %3$d KB outline limit. Read it in ranges with the read action.', $file['rel'], (int) ceil( $text->size() / 1024 ), Outliner::MAX_BYTES / 1024 ) );
            }
            if ( $text->is_binary() ) {
                throw new \RuntimeException( sprintf( '%s is a binary file; there is nothing to outline.', $file['rel'] ) );
            }
            $outline = ( new Outliner() )->outline( $kind, $text->contents( Outliner::MAX_BYTES ) );
            $meta    = array(
                'path'       => $file['rel'],
                'size_bytes' => $text->size(),
                'modified'   => $text->modified(),
            );
        } finally {
            $text->close();
        }
        return $meta + self::fit_outline( $outline );
    }

    





    private static function fit_outline( array $outline ): array {
        $budget = self::RESPONSE_BUDGET - 1024; 
        while ( strlen( self::json( $outline ) ) > $budget ) {
            $longest = '';
            $size    = 0;
            foreach ( $outline as $key => $value ) {
                $length = is_array( $value ) ? strlen( self::json( $value ) ) : 0;
                if ( $length > $size ) {
                    $longest = $key;
                    $size    = $length;
                }
            }
            if ( '' === $longest ) {
                break;
            }
            if ( count( $outline[ $longest ] ) <= 1 ) {
                $outline[ $longest ]  = array();
                $outline['truncated'] = true;
                continue;
            }
            $outline[ $longest ]  = array_slice( $outline[ $longest ], 0, (int) floor( count( $outline[ $longest ] ) * 3 / 4 ) );
            $outline['truncated'] = true;
        }
        return $outline;
    }
}
