<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Output {

    private $stdout = '';

    private $stderr = '';

    private $quiet;

    private $warnings = array();

    private $errors = array();

    public function __construct( bool $quiet = false ) {
        $this->quiet = $quiet;
    }

    public function set_quiet( bool $quiet ): void {
        $this->quiet = $quiet;
    }

    public function is_quiet(): bool {
        return $this->quiet;
    }

    public function out( string $text ): void {
        $this->stdout .= $text;
    }

    public function err( string $text ): void {
        $this->stderr .= $text;
    }

    public function line( string $text = '' ): void {
        $this->stdout .= $text . "\n";
    }

    public function log( string $text ): void {
        if ( ! $this->quiet ) {
            $this->stdout .= $text . "\n";
        }
    }

    public function success( string $text ): void {
        if ( ! $this->quiet ) {
            $this->stdout .= 'Success: ' . $text . "\n";
        }
    }

    public function warning( string $text ): void {
        $this->warnings[] = $text;
        if ( ! $this->quiet ) {
            $this->stderr .= 'Warning: ' . $text . "\n";
        }
    }

    public function error( string $text, int $exit_code = 1 ): void {
        $this->error_continue( $text );
        throw new Cli_Exit( $exit_code );
    }

    public function error_continue( string $text ): void {
        $this->errors[] = $text;
        $this->stderr  .= 'Error: ' . $text . "\n";
    }

    public function halt( int $exit_code ): void {
        throw new Cli_Exit( $exit_code );
    }

    public function stdout(): string {
        return $this->stdout;
    }

    public function stderr(): string {
        return $this->stderr;
    }

    public function warnings(): array {
        return $this->warnings;
    }

    public function errors(): array {
        return $this->errors;
    }

    public function stdout_bytes(): int {
        return strlen( $this->stdout );
    }
}
