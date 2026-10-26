<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Row_Extension;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_User extends Base_Tool {

    public function get_name() {
        return 'wp_get_user';
    }

    public function get_description() {
        return 'Gets a WordPress user by ID. Pass `user_id` = 0 to get the currently authenticated user. Returns { id, username, name (display name), first_name, last_name, email, roles (array), description, registered_date (ISO 8601), url (website), slug (user_nicename), nickname, locale, link (author posts URL), capabilities (every capability the user has, from roles and grants), extra_capabilities (the user\'s own capability map: role names and direct grants), user_registered (UTC, "Y-m-d H:i:s"), user_status, and on multisite spam and deleted (booleans) }. The password hash and activation key are never returned. Always requests full edit-context data: for the caller\'s own account, or when the caller is an administrator, the complete field set above is returned; requesting another user\'s data without sufficient capability fails with a 403 error rather than returning a partial/subset response.';
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

        $result = array(
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

        
        
        foreach ( array( 'slug', 'nickname', 'locale', 'link' ) as $field ) {
            $result[ $field ] = isset( $data[ $field ] ) ? $data[ $field ] : null;
        }
        $result['capabilities']       = self::capability_map( isset( $data['capabilities'] ) ? $data['capabilities'] : null );
        $result['extra_capabilities'] = self::capability_map( isset( $data['extra_capabilities'] ) ? $data['extra_capabilities'] : null );

        
        
        
        $result = array_merge( $result, self::user_columns( (int) $data['id'] ) );

        return Row_Extension::apply( Row_Extension::USER, $result, $data, $this->get_name(), false );
    }

    







    public static function user_columns( int $user_id ): array {
        $user    = $user_id > 0 ? get_userdata( $user_id ) : false;
        $columns = array(
            'user_registered' => 'string',
            'user_status'     => 'int',
        );
        if ( is_multisite() ) {
            $columns['spam']    = 'bool';
            $columns['deleted'] = 'bool';
        }
        $out = array();
        foreach ( $columns as $column => $type ) {
            $out[ $column ] = self::user_column( $user, $column, $type );
        }
        return $out;
    }

    








    private static function user_column( $user, string $column, string $type ) {
        if ( ! $user || ! isset( $user->$column ) ) {
            return null;
        }
        if ( 'string' === $type ) {
            return (string) $user->$column;
        }
        if ( 'int' === $type ) {
            return (int) $user->$column;
        }
        return (bool) (int) $user->$column;
    }

    






    public static function capability_map( $map ): ?\stdClass {
        if ( null === $map ) {
            return null;
        }
        $out = array();
        foreach ( (array) $map as $cap => $granted ) {
            $out[ (string) $cap ] = (bool) $granted;
        }
        return (object) $out;
    }
}
