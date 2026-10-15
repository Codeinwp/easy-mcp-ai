<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
















class Language_Adapter extends Command_Handler {

    
    const DEPRECATED = 'This command is deprecated. use wp site switch-language instead';

    
    public function core_activate( Invocation $inv, Context $ctx ): void {
        $ctx->out->warning( self::DEPRECATED );
        $this->site_adapter()->switch_language( $inv, $ctx );
    }

    
    protected function site_adapter(): Site_Adapter {
        return new Site_Adapter();
    }
}
