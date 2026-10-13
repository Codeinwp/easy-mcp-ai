<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-action.php';






final class Read_Action extends Action {

    const ARGUMENTS = array( 'path', 'offset', 'limit' );

    
    const MAX_BYTES = 2097152;
    const DEFAULT_LIMIT = 500;
    const MAX_LIMIT = 2000;
    const MAX_OFFSET = 10000000;

    public function run( array $arguments ): array {
        $path = self::string_argument( $arguments, 'path', '' );
        if ( '' === trim( $path ) ) {
            throw new \InvalidArgumentException( 'path is required: a file relative to wp-content.' );
        }
        $offset = self::int_argument( $arguments, 'offset', 1, 1, self::MAX_OFFSET );
        $limit  = self::int_argument( $arguments, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT );
        $file   = $this->resolver->resolve( $path, false );
        $text   = Text_File::open( $file );
        try {
            return $this->read( $text, $file['rel'], $offset, $limit );
        } finally {
            $text->close();
        }
    }

    
    private function read( Text_File $text, string $rel, int $offset, int $limit ): array {
        if ( $text->size() > self::MAX_BYTES ) {
            throw new \RuntimeException( sprintf( '%1$s is %2$d KB, over the %3$d KB read limit; nothing was read. Find the lines you need with the search action.', $rel, (int) ceil( $text->size() / 1024 ), self::MAX_BYTES / 1024 ) );
        }
        $meta = array(
            'path'       => $rel,
            'size_bytes' => $text->size(),
            'modified'   => $text->modified(),
        );
        if ( $text->is_binary() ) {
            return $meta + array(
                'binary' => true,
                'lines'  => '',
                'note'   => 'Binary file: its content is not returned.',
            );
        }
        list( $out, $to, $number ) = $this->collect( $text, $offset, $limit );
        $more = $to < $number;
        return $meta + array(
            'lines'              => $out,
            'from'               => $offset,
            'to'                 => $to,
            'total_lines'        => $number,
            'truncated'          => $more,
            'next_offset'        => $more ? $to + 1 : null,
            'redacted_lines'     => $text->redacted_lines,
            'invalid_utf8_lines' => $text->invalid_utf8_lines,
        );
    }

    






    private function collect( Text_File $text, int $offset, int $limit ): array {
        $budget = self::RESPONSE_BUDGET - 1024;
        $used   = 2;
        $out    = '';
        $to     = $offset - 1;
        $full   = false;
        $number = 0;
        while ( true ) {
            $in_range = ! $full && $number + 1 >= $offset && $number + 1 < $offset + $limit;
            $line     = $in_range ? $text->next_line() : ( $text->skip_line() ? '' : null );
            if ( null === $line ) {
                break;
            }
            ++$number;
            if ( ! $in_range ) {
                continue;
            }
            $entry = $number . "\t" . $line . "\n";
            $size  = strlen( self::json( $entry ) ) - 2; 
            $full  = $used + $size > $budget;
            if ( ! $full ) {
                $used += $size;
                $out  .= $entry;
                $to    = $number;
            }
        }
        return array( $out, $to, $number );
    }
}
