<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_User extends Base_Tool {

    public function get_name() {
        return 'wp_create_user';
    }

    public function get_description() {
        return 'Creates a new WordPress user. Required: `username`, `email`, `password`. Optional: `first_name`, `last_name`, `roles` (array of role slugs, e.g. ["editor"] — default is the site\'s `default_role`, typically "subscriber"). Returns { id, username, email, roles }. Usernames and emails must be unique across the site; duplicate username or email triggers an error. The password is stored hashed and never returned.';
    }

    public function get_category() {
        return 'users';
    }

    public function get_required_capability() {
        return 'create_users';
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
                'username'   => array(
                    'type'        => 'string',
                    'description' => 'The username for the new user.',
                ),
                'email'      => array(
                    'type'        => 'string',
                    'description' => 'The email address for the new user.',
                ),
                'password'   => array(
                    'type'        => 'string',
                    'description' => 'The password for the new user.',
                ),
                'first_name' => array(
                    'type'        => 'string',
                    'description' => 'The first name for the new user.',
                ),
                'last_name'  => array(
                    'type'        => 'string',
                    'description' => 'The last name for the new user.',
                ),
                'roles'      => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Array of roles to assign to the new user. Assigning ANY role requires the caller to hold `promote_users`; assigning the `administrator` role specifically also requires `manage_options`. Requests missing the required capability are rejected.',
                ),
            ),
            'required'   => array( 'username', 'email', 'password' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'username', 'email', 'password' ) );

        $username = sanitize_user( $arguments['username'] );
        $email    = sanitize_email( $arguments['email'] );

        if ( username_exists( $username ) ) {
            throw new \InvalidArgumentException( 'Sorry, that username already exists!' );
        }

        if ( email_exists( $email ) ) {
            throw new \InvalidArgumentException( 'Sorry, that email address is already in use!' );
        }

        $params = array(
            'username' => $username,
            'email'    => $email,
            'password' => $arguments['password'],
        );

        if ( isset( $arguments['first_name'] ) ) {
            $params['first_name'] = sanitize_text_field( $arguments['first_name'] );
        }

        if ( isset( $arguments['last_name'] ) ) {
            $params['last_name'] = sanitize_text_field( $arguments['last_name'] );
        }

        if ( ! empty( $arguments['roles'] ) ) {
            $params['roles'] = $this->validate_assignable_roles( array_map( 'sanitize_text_field', $arguments['roles'] ) );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/users', $params );

        return array(
            'id'       => $data['id'],
            'username' => $data['username'],
            'email'    => $data['email'],
            'roles'    => $data['roles'],
        );
    }

}
