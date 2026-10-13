<?php







namespace Easy_MCP_AI\Compat\Shims;

use Easy_MCP_AI\Compat\Compat_Shim;
use Easy_MCP_AI\Compat\Learnpress_Abilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-learnpress-abilities.php';































final class Learnpress_Mcp_Auth extends Compat_Shim {

    
    const TOOL_PREFIX = 'wp_ability_learnpress_';

    
    const SCOPE = 'read_write';

    
    const CONTEXT_METHODS = array( 'is_api_key_auth', 'set_api_key_auth', 'reset', 'get_key_id', 'get_user_id', 'get_permissions' );

    
    private $context;

    


    public function __construct( string $context = '\\LearnPress\\MCP\\Auth\\AuthContext' ) {
        $this->context = $context;
    }

    


    public function id(): string {
        return 'learnpress-mcp-auth';
    }

    


    public function applies_to( $tool ): bool {
        return is_object( $tool )
            && method_exists( $tool, 'get_category' ) && 'abilities' === $tool->get_category()
            && method_exists( $tool, 'get_name' ) && 0 === strpos( (string) $tool->get_name(), self::TOOL_PREFIX );
    }

    


    public function is_needed(): bool {
        if ( ! Learnpress_Abilities::group_enabled() || ! $this->context_available() ) {
            return false;
        }
        if ( (int) get_current_user_id() <= 0 ) {
            return false;
        }
        $context = $this->context;
        return ! $context::is_api_key_auth();
    }

    




    public function enter( array $arguments = array(), $tool = null ): \stdClass {
        $context = $this->context;
        $prior   = (object) array(
            'set'         => (bool) $context::is_api_key_auth(),
            'key_id'      => (int) $context::get_key_id(),
            'user_id'     => (int) $context::get_user_id(),
            'permissions' => (string) $context::get_permissions(),
        );
        $context::set_api_key_auth( 0, (int) get_current_user_id(), self::SCOPE );
        return $prior;
    }

    


    public function leave( $state = null ): void {
        if ( ! $state instanceof \stdClass || ! $this->context_available() ) {
            return;
        }
        $context = $this->context;
        $context::reset();
        if ( ! empty( $state->set ) ) {
            $context::set_api_key_auth( (int) $state->key_id, (int) $state->user_id, (string) $state->permissions );
        }
    }

    
    private function context_available(): bool {
        if ( ! class_exists( $this->context ) ) {
            return false;
        }
        foreach ( self::CONTEXT_METHODS as $method ) {
            if ( ! method_exists( $this->context, $method ) ) {
                return false;
            }
        }
        return true;
    }
}
