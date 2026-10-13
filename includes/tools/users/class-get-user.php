<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_User extends Base_Tool {

    public function get_name() {
        return 'wp_get_user';
    }

    public function get_description() {
        return 'Gets a WordPress user by ID. Pass `user_id` = 0 to get the currently authenticated user. Returns { id, username, name, first_name, last_name, email, roles (array), description, registered_date, url (website) }. Always requests full edit-context data: for the caller\'s own account, or when the caller is an administrator, the complete field set above is returned; requesting another user\'s data without sufficient capability fails with a 403 error rather than returning a partial/subset response.';
    }

    public function get_category() {
        return 'users';
    }

    public function get_required_capability() {
        return 'list_users';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'user_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the user to retrieve. Use 0 to get the current user.',
                ),
            ),
            'required'   => array( 'user_id' ),
        );
    }

    public function execute( array $arguments ) {
        
        
        if ( ! array_key_exists( 'user_id', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameters: user_id' );
        }

        $user_id = (int) $arguments['user_id'];
        if ( $user_id < 0 ) {
            throw new \InvalidArgumentException( 'Invalid user_id: must be 0 (current user) or a positive integer.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $route = 0 === $user_id ? '/wp/v2/users/me' : '/wp/v2/users/' . $user_id;

        $data = $this->rest_request( 'GET', $route, array( 'context' => 'edit' ) );

        return array(
            'id'              => $data['id'],
            'username'        => $data['username'],
            'name'            => $data['name'],
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $data['email'],
            'roles'           => $data['roles'],
            'description'     => $data['description'],
            'registered_date' => $data['registered_date'],
            'url'             => $data['url'],
        );
    }
}
