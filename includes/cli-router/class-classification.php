<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Classification {

    public $kind;

    public $write = false;

    public $split = false;

    public $tools = array();

    public $invocation = null;

    public $error = null;

    public $plan = null;

    public $output;

    public $exit_code = 0;

    public $reason = null;

    public $notes = array();

    public $command = '';

    public $masked = '';

    public function __construct( string $kind ) {
        $this->kind   = $kind;
        $this->output = new Output();
    }

    public function is_composite(): bool {
        return 'delegate' === $this->kind;
    }

    public function dispatches(): bool {
        return null !== $this->invocation && in_array( $this->kind, array( 'catalog', 'delegate', 'own' ), true );
    }
}
