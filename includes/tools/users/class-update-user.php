<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_User extends Base_Tool {

    public function get_name() {
        return 'wp_update_user';
    }

    public function get_description() {
        return 'Updates an existing WordPress user (PATCH semantics). Required: `user_id`. Optional: `email`, `first_name`, `last_name`, `roles` (array of role slugs — replaces all current roles), `url` (website URL), `description` (biographical info). Returns { id, name, email, roles }. Only administrators can change roles or edit other users.';
    }

    public function get_category() {
        return 'users';
    }

    public function get_required_capability() {
        return 'edit_users';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false, 
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'user_id'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the user to update.',
                ),
                'email'       => array(
                    'type'        => 'string',
                    'description' => 'The new email address for the user.',
                ),
                'first_name'  => array(
                    'type'        => 'string',
                    'description' => 'The new first name for the user.',
                ),
                'last_name'   => array(
                    'type'        => 'string',
                    'description' => 'The new last name for the user.',
                ),
                'roles'       => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Array of roles to assign to the user.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'The new biographical description for the user.',
                ),
                'url'         => array(
                    'type'        => 'string',
                    'description' => 'The new URL/website for the user.',
                ),
            ),
            'required'   => array( 'user_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'user_id' ) );

        $user_id = $this->parse_required_id( $arguments['user_id'], 'user_id' );
        $params  = array();

        if ( isset( $arguments['email'] ) ) {
            $params['email'] = sanitize_email( $arguments['email'] );
        }

        if ( isset( $arguments['first_name'] ) ) {
            $params['first_name'] = sanitize_text_field( $arguments['first_name'] );
        }

        if ( isset( $arguments['last_name'] ) ) {
            $params['last_name'] = sanitize_text_field( $arguments['last_name'] );
        }

        if ( ! empty( $arguments['roles'] ) ) {
            $params['roles'] = $this->validate_assignable_roles( array_map( 'sanitize_text_field', $arguments['roles'] ) );
        }

        if ( isset( $arguments['description'] ) ) {
            $params['description'] = sanitize_text_field( $arguments['description'] );
        }

        if ( isset( $arguments['url'] ) ) {
            $params['url'] = esc_url_raw( $arguments['url'] );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/users/' . $user_id, $params );

        return array(
            'id'    => $data['id'],
            'name'  => $data['name'],
            'email' => $data['email'],
            'roles' => $data['roles'],
        );
    }

}
