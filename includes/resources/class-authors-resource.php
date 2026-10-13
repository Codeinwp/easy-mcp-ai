<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Authors_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://users/authors';
    }

    public function get_name() {
        return 'Authors';
    }

    public function get_description() {
        return 'All users who can create posts, with display names. Logins, emails and roles are included only for callers who can list users.';
    }

    public function read() {
        $users   = get_users( array(
            'capability' => array( 'edit_posts' ),
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ) );
        $authors = array();

        
        
        
        
        
        
        
        
        
        $can_list_users = current_user_can( 'list_users' );

        foreach ( $users as $user ) {
            $authors[] = array(
                'id'           => (int) $user->ID,
                'display_name' => $user->display_name,
                'login'        => $can_list_users ? $user->user_login : null,
                'email'        => $can_list_users ? $user->user_email : null,
                'roles'        => $can_list_users ? array_values( (array) $user->roles ) : null,
            );
        }

        return array( 'authors' => $authors );
    }
}
