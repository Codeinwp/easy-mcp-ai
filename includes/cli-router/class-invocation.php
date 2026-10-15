<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










final class Invocation {

    
    public $command;

    
    public $args;

    
    public $assoc;

    
    public $runtime;

    
    public $leaf;

    
    public $route;

    
    public $raw;

    






    public function __construct( string $command, array $args, array $assoc, array $runtime, array $leaf, array $route, string $raw ) {
        $this->command = $command;
        $this->args    = $args;
        $this->assoc   = $assoc;
        $this->runtime = $runtime;
        $this->leaf    = $leaf;
        $this->route   = $route;
        $this->raw     = $raw;
    }

    






    public function get( string $key, $default = null ) {
        return array_key_exists( $key, $this->assoc ) ? $this->assoc[ $key ] : $default;
    }

    
    public function has( string $key ): bool {
        return array_key_exists( $key, $this->assoc ) && false !== $this->assoc[ $key ];
    }
}
