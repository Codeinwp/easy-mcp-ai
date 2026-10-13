<?php
namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}














class Detailed_Tool_Error extends \InvalidArgumentException {

    



    const MAX_MESSAGE = 200;

    
    const MAX_DETAILS = 2000;

    
    private $details;

    



    public function __construct( string $message, string $details = '' ) {
        parent::__construct( $message );
        $this->details = $details;
    }

    
    public function get_details(): string {
        return $this->details;
    }
}
