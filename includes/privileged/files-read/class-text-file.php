<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

use Easy_MCP_AI\Text_Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Text_File {

    const LINE_BYTES = 8192;
    const BINARY_PROBE_BYTES = 8192;
    const CUT_MARKER = ' [line cut at 8192 bytes]';

    private $handle;
    private $size;
    private $mtime;
    public $redacted_lines = 0;
    public $invalid_utf8_lines = 0;

    private function __construct( $handle, int $size, int $mtime ) {
        $this->handle = $handle;
        $this->size   = $size;
        $this->mtime  = $mtime;
    }

    public static function open( array $file ): self {
        $abs    = $file['abs'];
        $handle = @fopen( $abs, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- bounded line reads; warnings name server paths.
        if ( false === $handle ) {
            throw Refusal::for_path( $file['rel'] );
        }
        clearstatcache( true );
        $opened = fstat( $handle );
        $now    = @lstat( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( ! is_array( $opened ) || ! is_array( $now ) || Resolver::real( $abs ) !== $abs || ! self::same_file( $opened, $now ) ) {
            fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            throw Refusal::for_path( $file['rel'] );
        }
        return new self( $handle, (int) $opened['size'], (int) $opened['mtime'] );
    }

    public static function same_file( array $opened, array $now ): bool {
        return (int) $opened['dev'] === (int) $now['dev']
            && (int) $opened['ino'] === (int) $now['ino']
            && 0100000 === ( (int) $opened['mode'] & 0170000 )
            && 1 === (int) $opened['nlink'];
    }

    public function size(): int {
        return $this->size;
    }

    public function modified(): string {
        return gmdate( 'Y-m-d\TH:i:s\Z', $this->mtime );
    }

    public function is_binary(): bool {
        $head = (string) fread( $this->handle, self::BINARY_PROBE_BYTES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
        rewind( $this->handle );
        return false !== strpos( $head, "\0" );
    }

    public function next_line(): ?string {
        $raw = $this->next_raw_line();
        return null === $raw ? null : $this->clean( $raw );
    }

    public function skip_line(): bool {
        return null !== $this->next_raw_line();
    }

    private function next_raw_line(): ?string {
        $line = fgets( $this->handle, self::LINE_BYTES + 1 );
        if ( false === $line ) {
            return null;
        }
        if ( "\n" === substr( $line, -1 ) ) {
            return rtrim( $line, "\r\n" );
        }
        if ( feof( $this->handle ) ) {
            return rtrim( $line, "\r" );
        }
        do {
            $rest = fgets( $this->handle, self::LINE_BYTES + 1 );
        } while ( false !== $rest && "\n" !== substr( $rest, -1 ) );
        $cut = function_exists( 'mb_strcut' ) ? mb_strcut( $line, 0, strlen( $line ), 'UTF-8' ) : $line;
        return $cut . self::CUT_MARKER;
    }

    public function clean( string $line ): string {
        if ( ! self::is_utf8( $line ) ) {
            ++$this->invalid_utf8_lines;
            $line = self::to_utf8( $line );
        }
        $redacted = self::redact( $line );
        if ( $redacted !== $line ) {
            ++$this->redacted_lines;
        }
        return $redacted;
    }

    public static function redact( string $line ): string {
        if ( '' === $line ) {
            return $line;
        }
        return self::checked( Text_Redactor::redact( $line, false ) );
    }

    public static function checked( ?string $redacted ): string {
        if ( null === $redacted || '' === $redacted ) {
            throw new \RuntimeException( 'A line could not be checked for secrets, so the file was not returned. Nothing was read out.' );
        }
        return $redacted;
    }

    public function contents( int $max_bytes ): string {
        rewind( $this->handle );
        $data = '';
        while ( ! feof( $this->handle ) && strlen( $data ) <= $max_bytes ) {
            $chunk = fread( $this->handle, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
            if ( false === $chunk || '' === $chunk ) {
                break;
            }
            $data .= $chunk;
        }
        if ( strlen( $data ) > $max_bytes ) {
            throw new \RuntimeException( sprintf( 'The file is larger than %d KB; it is not outlined. Read it in ranges with the read action.', (int) ( $max_bytes / 1024 ) ) );
        }
        return $data;
    }

    public static function safe( string $text ): string {
        return self::redact( self::is_utf8( $text ) ? $text : self::to_utf8( $text ) );
    }

    public static function is_utf8( string $text ): bool {
        return 1 === preg_match( '//u', $text );
    }

    public static function to_utf8( string $text ): string {
        if ( function_exists( 'mb_convert_encoding' ) && function_exists( 'mb_substitute_character' ) ) {
            $previous = mb_substitute_character();
            mb_substitute_character( 0xFFFD );
            $clean = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
            mb_substitute_character( $previous );
            if ( is_string( $clean ) && self::is_utf8( $clean ) ) {
                return $clean;
            }
        }
        return (string) preg_replace( '/[\x80-\xff]/', "\xEF\xBF\xBD", $text );
    }

    public function close(): void {
        if ( is_resource( $this->handle ) ) {
            fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
    }

    public function __destruct() {
        $this->close();
    }
}
