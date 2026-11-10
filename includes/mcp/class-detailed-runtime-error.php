<?php
namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Detailed_Runtime_Error extends \RuntimeException {

    const CONTINUED = '… (the whole message follows)';

    private $details;

    public function __construct( string $message, string $details = '' ) {
        parent::__construct( $message );
        $this->details = $details;
    }

    public function get_details(): string {
        return $this->details;
    }

    public static function for_validation( string $message ): \RuntimeException {
        if ( Refusal_Text::fits( $message ) ) {
            return new \RuntimeException( $message );
        }
        $budget = Detailed_Tool_Error::MAX_MESSAGE - strlen( self::CONTINUED );
        do {
            $short = function_exists( 'mb_strcut' ) ? mb_strcut( $message, 0, $budget, 'UTF-8' ) : substr( $message, 0, $budget );
            $space = strrpos( $short, ' ' );
            if ( false !== $space && $space > $budget / 2 ) {
                $short = substr( $short, 0, $space );
            }
            $short   = rtrim( $short, ' ,;:—' ) . self::CONTINUED;
            $budget -= 10;
        } while ( ! Refusal_Text::fits( $short ) && $budget > 20 );
        return new self( $short, $message );
    }
}
