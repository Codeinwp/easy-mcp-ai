<?php






namespace Easy_MCP_AI\Builders;

use Easy_MCP_AI\Option_Lock;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}














final class Layout_Lock {

    
    const PREFIX = 'easy_mcp_ai_layout_lock_';

    
    const TTL = 30;

    





    public static function acquire( int $post_id ): string {
        return Option_Lock::acquire( self::PREFIX . $post_id, self::TTL );
    }

    






    public static function holds( int $post_id, string $token ): bool {
        return Option_Lock::holds( self::PREFIX . $post_id, $token );
    }

    





    public static function release( int $post_id, string $token ): void {
        Option_Lock::release( self::PREFIX . $post_id, $token );
    }
}
