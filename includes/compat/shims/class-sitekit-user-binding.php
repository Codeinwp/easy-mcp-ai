<?php







namespace Easy_MCP_AI\Compat\Shims;

use Easy_MCP_AI\Compat\Compat_Shim;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





































final class Sitekit_User_Binding extends Compat_Shim {

    
    const STORAGE_CLASS = 'Google\\Site_Kit\\Core\\Storage\\User_Options';

    


    public function id() {
        return 'sitekit-user-binding';
    }

    





    public function applies_to( $tool ) {
        return is_object( $tool ) && method_exists( $tool, 'get_category' ) && in_array( $tool->get_category(), array( 'sitekit', 'rest' ), true );
    }

    


    public function is_needed() {
        $user_id = (int) get_current_user_id();
        if ( $user_id <= 0 ) {
            return false;
        }
        $bound = self::bound_user_id();
        return null !== $bound && $bound !== $user_id;
    }

    


    public function enter( array $arguments = array(), $tool = null ) {
        $user_options = self::user_options();
        if ( null === $user_options ) {
            return null;
        }
        $state               = new \stdClass();
        $state->user_options = $user_options;
        $state->previous     = (int) $user_options->get_user_id();
        $user_options->switch_user( (int) get_current_user_id() );
        return $state;
    }

    


    public function leave( $state = null ): void {
        if ( ! $state instanceof \stdClass || ! isset( $state->user_options ) ) {
            return;
        }
        $state->user_options->switch_user( (int) $state->previous );
    }

    



    public static function bound_user_id(): ?int {
        $user_options = self::user_options();
        return null === $user_options ? null : (int) $user_options->get_user_id();
    }

    






    public static function user_options(): ?object {
        global $wp_filter;
        if ( ! isset( $wp_filter['wp_login'] ) || ! is_object( $wp_filter['wp_login'] ) || ! isset( $wp_filter['wp_login']->callbacks ) || ! is_array( $wp_filter['wp_login']->callbacks ) ) {
            return null;
        }
        foreach ( $wp_filter['wp_login']->callbacks as $callbacks ) {
            foreach ( (array) $callbacks as $callback ) {
                $function = $callback['function'] ?? null;
                if ( ! $function instanceof \Closure ) {
                    continue;
                }
                foreach ( ( new \ReflectionFunction( $function ) )->getStaticVariables() as $value ) {
                    if ( is_a( $value, self::STORAGE_CLASS ) && is_callable( array( $value, 'switch_user' ) ) && is_callable( array( $value, 'get_user_id' ) ) ) {
                        return $value;
                    }
                }
            }
        }
        return null;
    }
}
