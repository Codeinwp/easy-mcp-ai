<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







final class Cli_Exit extends \Exception {

    
    private $exit_code;

    
    public function __construct( int $exit_code ) {
        parent::__construct( 'wp-cli exit ' . $exit_code );
        $this->exit_code = $exit_code;
    }

    
    public function get_exit_code(): int {
        return $this->exit_code;
    }
}
