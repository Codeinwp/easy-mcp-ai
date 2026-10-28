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
        return 'Creates a new WordPress user. Required: `username`, `email`, `password`. Optional: `first_name`, `last_name`, `roles` (array of role slugs, e.g. ["editor"] — default is the site\'s `default_role`, typically "subscriber"), `name` (display name), `nickname`, `slug` (user_nicename; refused when another user has it), `url` (website), `description` (biography; WordPress keeps the HTML it allows in a biography), `user_registered` (UTC "Y-m-d H:i:s"; default now), `rich_editing` (boolean), `send_email` (boolean, default false: WordPress emails the new user a link to set a password and notifies the site admin, as wp-admin\'s "Send the new user an email" does). Returns { id, username, email, roles, new_user_notification }. Usernames and emails must be unique across the site; duplicate username or email triggers an error. The password is stored hashed and never returned. Creating a user with an administrator-level role is the kind of call the site\'s approval switch pauses (when it is on and nothing in the approval settings exempts it).';
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

    public function get_redacted_arguments() {
        return array( 'password' );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'username'        => array(
                    'type'        => 'string',
                    'description' => 'The username for the new user.',
                ),
                'email'           => array(
                    'type'        => 'string',
                    'description' => 'The email address for the new user.',
                ),
                'password'        => array(
                    'type'        => 'string',
                    'description' => 'The password for the new user.',
                ),
                'first_name'      => array(
                    'type'        => 'string',
                    'description' => 'The first name for the new user.',
                ),
                'last_name'       => array(
                    'type'        => 'string',
                    'description' => 'The last name for the new user.',
                ),
                'roles'           => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Array of roles to assign to the new user. Assigning ANY role requires the caller to hold `promote_users`; assigning the `administrator` role specifically also requires `manage_options`. Requests missing the required capability are rejected.',
                ),
                'name'            => array(
                    'type'        => 'string',
                    'description' => 'The display name (REST `name`). Default: the username.',
                ),
                'nickname'        => array(
                    'type'        => 'string',
                    'description' => 'The nickname. Default: the username.',
                ),
                'slug'            => array(
                    'type'        => 'string',
                    'description' => 'The URL-friendly name (REST `slug`, the users-table user_nicename). Default: derived from the username. Refused when another user already has it, rather than letting WordPress store a suffixed variant.',
                ),
                'url'             => array(
                    'type'        => 'string',
                    'description' => 'The user\'s website URL.',
                ),
                'description'     => array(
                    'type'        => 'string',
                    'description' => 'Biographical info. WordPress filters it with kses, so the HTML it allows in a biography is kept.',
                ),
                'user_registered' => array(
                    'type'        => 'string',
                    'description' => 'Registration date and time in UTC, "Y-m-d H:i:s". Default: now.',
                ),
                'rich_editing'    => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the visual editor is enabled for the user. Default: enabled.',
                ),
                'send_email'      => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'When true, WordPress sends its new-user notification after creating the user: the user gets a link to set a password, the site admin gets a new-user notice. Neither is sent by default.',
                ),
            ),
            'required'   => array( 'username', 'email', 'password' ),
        );
    }

    public function get_approval_policy( array $arguments ) {
        try {
            $roles = ( isset( $arguments['roles'] ) && is_array( $arguments['roles'] ) && ! empty( $arguments['roles'] ) )
                ? $arguments['roles']
                : array( (string) get_option( 'default_role' ) );
            foreach ( $roles as $role ) {
                if ( is_string( $role ) && Update_User::role_is_takeover_grant( sanitize_text_field( $role ) ) ) {
                    return 'always';
                }
            }
        } catch ( \Throwable $e ) {
            return 'always';
        }
        return 'inherit';
    }

    public function describe( array $arguments ) {
        try {
            $text = sprintf(
                "Create user '%s' (%s) with %s",
                isset( $arguments['username'] ) && is_scalar( $arguments['username'] ) ? sanitize_user( (string) $arguments['username'] ) : '?',
                isset( $arguments['email'] ) && is_scalar( $arguments['email'] ) ? sanitize_email( (string) $arguments['email'] ) : '?',
                self::describe_roles( $arguments )
            );
            if ( ! empty( $arguments['send_email'] ) && rest_sanitize_boolean( $arguments['send_email'] ) ) {
                $text .= '; email the new account details';
            }
            return $text . '.';
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    private static function describe_roles( array $arguments ): string {
        $roles = ( isset( $arguments['roles'] ) && is_array( $arguments['roles'] ) && ! empty( $arguments['roles'] ) )
            ? array_map( 'strval', $arguments['roles'] )
            : array();
        sort( $roles, SORT_STRING );
        return empty( $roles )
            ? sprintf( "the default role '%s'", (string) get_option( 'default_role' ) )
            : sprintf( 'role(s) %s', implode( ', ', $roles ) );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'username', 'email', 'password' ) );

        $params     = $this->create_params( $arguments );
        $extra      = self::extra_fields( $arguments );
        $send_email = self::send_email_requested( $arguments );
        $data       = $this->rest_create( $params, $extra );

        $notified = false;
        if ( $send_email ) {
            wp_new_user_notification( (int) $data['id'], null, 'both' );
            $notified = true;
        }

        return array(
            'id'                    => $data['id'],
            'username'              => $data['username'],
            'email'                 => $data['email'],
            'roles'                 => $data['roles'],
            'new_user_notification' => $notified,
        );
    }

    private function create_params( array $arguments ): array {
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

        $params = array_merge( $params, self::string_params( $arguments ) );
        if ( isset( $params['slug'] ) ) {
            Update_User::refuse_unusable_slug( $params['slug'], 0 );
        }
        return $params;
    }

    private static function string_params( array $arguments ): array {
        $params = array();
        foreach ( array( 'name', 'nickname', 'slug', 'url', 'description' ) as $field ) {
            if ( ! isset( $arguments[ $field ] ) ) {
                continue;
            }
            if ( ! is_string( $arguments[ $field ] ) ) {
                throw new \InvalidArgumentException( sprintf( '%s must be a string.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $params[ $field ] = $arguments[ $field ];
        }
        return $params;
    }

    private static function extra_fields( array $arguments ): array {
        $extra = array();
        if ( isset( $arguments['user_registered'] ) ) {
            $extra['user_registered'] = Update_User::parse_user_registered( $arguments['user_registered'] );
        }
        if ( isset( $arguments['rich_editing'] ) ) {
            $extra['rich_editing'] = rest_sanitize_boolean( $arguments['rich_editing'] ) ? 'true' : 'false';
        }
        return $extra;
    }

    private static function send_email_requested( array $arguments ): bool {
        $send_email = ! empty( $arguments['send_email'] ) && rest_sanitize_boolean( $arguments['send_email'] );
        if ( $send_email && ! function_exists( 'wp_new_user_notification' ) ) {
            throw new \RuntimeException( 'send_email cannot be honoured: wp_new_user_notification() is not available on this request.' );
        }
        return $send_email;
    }

    private function rest_create( array $params, array $extra ): array {
        $filter = self::extra_fields_filter( $extra );
        if ( null !== $filter ) {
            add_filter( 'rest_pre_insert_user', $filter, 5, 2 );
        }
        try {
            return $this->rest_request( 'POST', '/wp/v2/users', $params );
        } finally {
            if ( null !== $filter ) {
                remove_filter( 'rest_pre_insert_user', $filter, 5 );
            }
        }
    }

    private static function extra_fields_filter( array $extra ): ?\Closure {
        if ( empty( $extra ) ) {
            return null;
        }
        $applied = false;
        return static function ( $prepared, $request = null ) use ( $extra, &$applied ) {
            if ( $applied || ! is_object( $prepared ) || ! $request instanceof \WP_REST_Request
                || '/wp/v2/users' !== $request->get_route() || 'POST' !== $request->get_method() ) {
                return $prepared;
            }
            $applied = true;
            foreach ( $extra as $key => $value ) {
                $prepared->$key = $value;
            }
            return $prepared;
        };
    }

}
