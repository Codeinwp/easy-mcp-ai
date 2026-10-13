<?php







namespace Easy_MCP_AI\Compat\Shims;

use Easy_MCP_AI\Compat\Compat_Shim;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






































final class Buddypress_Loggedin_User extends Compat_Shim {

    
    private $filter = null;

    


    public function id() {
        return 'buddypress-loggedin-user';
    }

    


    public function applies_to( $tool ) {
        return is_object( $tool ) && method_exists( $tool, 'get_category' ) && 'buddypress' === $tool->get_category();
    }

    


    public function is_needed() {
        if ( ! function_exists( 'bp_loggedin_user_id' ) || ! function_exists( 'bp_is_user_active' ) ) {
            return false;
        }
        $wp_user_id = (int) get_current_user_id();
        if ( $wp_user_id <= 0 ) {
            return false;
        }
        if ( 0 !== (int) bp_loggedin_user_id() ) {
            return false;
        }
        return (bool) bp_is_user_active( $wp_user_id );
    }

    






    public function enter( array $arguments = array(), $tool = null ) {
        if ( null !== $this->filter ) {
            return null;
        }
        $this->filter = static function ( $id ) {
            $id = (int) $id;
            return $id > 0 ? $id : (int) get_current_user_id();
        };
        add_filter( 'bp_loggedin_user_id', $this->filter, 0 );
        return null;
    }

    


    public function leave( $state = null ): void {
        if ( null === $this->filter ) {
            return;
        }
        remove_filter( 'bp_loggedin_user_id', $this->filter, 0 );
        $this->filter = null;
    }
}
