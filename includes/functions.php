<?php







if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












function easy_mcp_ai_is_mcp_request(): bool {
    $is_mcp_request = defined( 'EASY_MCP_AI_MCP_REQUEST' ) && EASY_MCP_AI_MCP_REQUEST;

    




    return (bool) apply_filters( 'easy_mcp_ai_is_mcp_request', $is_mcp_request );
}










function easy_mcp_ai_is_tool_call(): bool {
    
    
    $tool_name = class_exists( '\\Easy_MCP_AI\\MCP\\Tool_Call_Stack', false ) ? \Easy_MCP_AI\MCP\Tool_Call_Stack::current() : null;

    





    return (bool) apply_filters( 'easy_mcp_ai_is_tool_call', null !== $tool_name, $tool_name );
}
