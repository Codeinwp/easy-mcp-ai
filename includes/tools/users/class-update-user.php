<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_User extends Base_Tool {

    const TAKEOVER_CAPABILITIES = array(
        'manage_options',
        'promote_users',
        'edit_users',
        'manage_network',
        'manage_sites',
        'manage_network_users',
        'manage_network_plugins',
        'manage_network_themes',
        'manage_network_options',
        'upgrade_network',
        'setup_network',
        'create_sites',
        'delete_sites',
    );

    const FLAG_FIELDS = array( 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'show_admin_bar_front' );

    const SNAPSHOT_META = array( 'first_name', 'last_name', 'nickname', 'description', 'locale', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl', 'show_admin_bar_front' );

    const SNAPSHOT_COLUMNS = array(
        'email'           => 'user_email',
        'name'            => 'display_name',
        'slug'            => 'user_nicename',
        'url'             => 'user_url',
        'user_registered' => 'user_registered',
        'password'        => 'user_pass',
    );

    public function get_name() {
        return 'wp_update_user';
    }

    public function get_description() {
        return 'Updates an existing WordPress user (PATCH semantics). Required: `user_id`. Optional: `email`, `first_name`, `last_name`, `roles` (array of role slugs — replaces all current roles), `url` (website URL), `description` (biographical info), `password`, `name` (display name), `nickname`, `slug` (user_nicename), `locale`, `rich_editing`, `syntax_highlighting`, `comment_shortcuts`, `show_admin_bar_front`, `use_ssl` (booleans), `admin_color`, `user_registered` (UTC "Y-m-d H:i:s"), `skip_email` (do not send the password-change or email-change notice). Role changes without replacing the set: `add_roles` / `remove_roles` (WP_User::add_role() / remove_role(); a role must exist and be one you may assign; removing the last administrator, or a role that leaves your own account unable to edit users, is refused); `remove_from_site` (single site: remove_all_caps(), the account stays with no role; multisite: remove_user_from_blog(), the network account stays); `add_to_site` (multisite: add a network user who is not a member to this site with the one role in `roles`). Returns { id, name, email, roles, changed, changed_fields }: `changed` is false when the call left the user exactly as it was (a new password always counts as a change: WordPress stores a new hash). Granting an administrator-level role, setting a password or changing the email address is the kind of call the site\'s approval switch pauses (when it is on and nothing in the approval settings exempts it). Only administrators can change roles or edit other users.';
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

    public function get_redacted_arguments() {
        return array( 'password' );
    }

    public function get_input_schema() {
        $flag = static function ( string $description ): array {
            return array(
                'type'        => 'boolean',
                'description' => $description,
            );
        };
        $role_list = static function ( string $description ): array {
            return array(
                'type'        => 'array',
                'items'       => array( 'type' => 'string' ),
                'description' => $description,
            );
        };
        return array(
            'type'       => 'object',
            'properties' => array(
                'user_id'              => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the user to update.',
                ),
                'email'                => array(
                    'type'        => 'string',
                    'description' => 'The new email address for the user.',
                ),
                'first_name'           => array(
                    'type'        => 'string',
                    'description' => 'The new first name for the user.',
                ),
                'last_name'            => array(
                    'type'        => 'string',
                    'description' => 'The new last name for the user.',
                ),
                'roles'                => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Array of roles to assign to the user.',
                ),
                'description'          => array(
                    'type'        => 'string',
                    'description' => 'The new biographical description for the user.',
                ),
                'url'                  => array(
                    'type'        => 'string',
                    'description' => 'The new URL/website for the user.',
                ),
                'password'             => array(
                    'type'        => 'string',
                    'description' => 'A new password (REST `password`; WordPress refuses an empty one and one containing a backslash). Sends the password-change notice unless skip_email is true.',
                ),
                'name'                 => array(
                    'type'        => 'string',
                    'description' => 'The display name (REST `name`, the users-table display_name).',
                ),
                'nickname'             => array(
                    'type'        => 'string',
                    'description' => 'The nickname.',
                ),
                'slug'                 => array(
                    'type'        => 'string',
                    'description' => 'The URL-friendly name (REST `slug`, the users-table user_nicename). Refused by WordPress when another user has it.',
                ),
                'locale'               => array(
                    'type'        => 'string',
                    'description' => 'The user\'s admin language: an installed locale such as "de_DE", "en_US", or "" for the site language. WordPress refuses a locale that is not installed.',
                ),
                'rich_editing'         => $flag( 'Whether the visual editor is enabled for the user.' ),
                'syntax_highlighting'  => $flag( 'Whether the code editor\'s syntax highlighting is enabled for the user.' ),
                'comment_shortcuts'    => $flag( 'Whether keyboard shortcuts for comment moderation are enabled.' ),
                'show_admin_bar_front' => $flag( 'Whether the toolbar shows when the user views the site.' ),
                'use_ssl'              => $flag( 'Whether the user\'s admin is always forced over HTTPS.' ),
                'admin_color'          => array(
                    'type'        => 'string',
                    'description' => 'Admin color scheme slug, e.g. "fresh", "light", "modern". Letters, digits, space, _ . - @ only (WordPress strips anything else); WordPress does not check that the scheme exists.',
                ),
                'user_registered'      => array(
                    'type'        => 'string',
                    'description' => 'Registration date and time in UTC, "Y-m-d H:i:s" (e.g. "2024-05-01 09:30:00").',
                ),
                'skip_email'           => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'When true, WordPress does not send the password-change or email-change notice for this call.',
                ),
                'add_roles'            => $role_list( 'Roles to add, keeping every role the user already has (WP_User::add_role()). Cannot be combined with `roles`.' ),
                'remove_roles'         => $role_list( 'Roles to remove, keeping the others (WP_User::remove_role()). Removing administrator from the last administrator is refused. Cannot be combined with `roles`.' ),
                'remove_from_site'     => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Remove every role and capability the user has on this site. Single site: remove_all_caps() (the account stays, with no role). Multisite: remove_user_from_blog() (the network account stays). Refused for your own account and the last administrator. Cannot be combined with roles, add_roles, remove_roles or add_to_site.',
                ),
                'add_to_site'          => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Multisite only: when the user is a network user who is not a member of this site, add them with the one role given in `roles` (add_user_to_blog()). Needs `roles` with exactly one role.',
                ),
            ),
            'required'   => array( 'user_id' ),
        );
    }

    public function get_approval_policy( array $arguments ) {
        try {
            if ( self::grants_takeover_role( $arguments ) || self::sets_password( $arguments ) || self::changes_email( $arguments ) ) {
                return 'always';
            }
        } catch ( \Throwable $e ) {
            return 'always';
        }
        return 'inherit';
    }

    private static function grants_takeover_role( array $arguments ): bool {
        foreach ( array( 'roles', 'add_roles' ) as $field ) {
            if ( ! isset( $arguments[ $field ] ) || ! is_array( $arguments[ $field ] ) ) {
                continue;
            }
            foreach ( $arguments[ $field ] as $role ) {
                if ( is_string( $role ) && self::role_is_takeover_grant( sanitize_text_field( $role ) ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function sets_password( array $arguments ): bool {
        return isset( $arguments['password'] ) && is_string( $arguments['password'] ) && '' !== $arguments['password'];
    }

    private static function changes_email( array $arguments ): bool {
        if ( ! isset( $arguments['email'] ) || ! is_string( $arguments['email'] ) || '' === trim( $arguments['email'] ) ) {
            return false;
        }
        $id   = isset( $arguments['user_id'] ) && is_numeric( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;
        $user = $id > 0 ? get_userdata( $id ) : false;
        if ( ! $user ) {
            return false;
        }
        $current = isset( $user->user_email ) ? (string) $user->user_email : '';
        return 0 !== strcasecmp( sanitize_email( $arguments['email'] ), $current );
    }

    public static function role_is_takeover_grant( string $role ): bool {
        if ( '' === $role ) {
            return false;
        }
        if ( 'administrator' === $role ) {
            return true;
        }
        $object = function_exists( 'get_role' ) ? get_role( $role ) : null;
        if ( ! is_object( $object ) || ! method_exists( $object, 'has_cap' ) ) {
            return false;
        }
        foreach ( self::TAKEOVER_CAPABILITIES as $cap ) {
            if ( $object->has_cap( $cap ) ) {
                return true;
            }
        }
        return false;
    }

    public static function parse_user_registered( $value ): string {
        $shown = is_scalar( $value ) ? (string) $value : gettype( $value );
        if ( is_string( $value ) && preg_match( '/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})$/', trim( $value ), $m ) ) {
            $normal = $m[1] . ' ' . $m[2];
            $parsed = \DateTime::createFromFormat( '!Y-m-d H:i:s', $normal, new \DateTimeZone( 'UTC' ) );
            if ( $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $normal ) {
                return $normal;
            }
        }
        throw new \InvalidArgumentException( sprintf( 'user_registered must be a real UTC date and time as "Y-m-d H:i:s" (e.g. 2024-05-01 09:30:00), got: %s', $shown ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    public static function refuse_unusable_slug( $slug, int $user_id = 0 ): void {
        $clean = is_string( $slug ) ? sanitize_title( $slug ) : '';
        if ( '' === $clean ) {
            throw new \InvalidArgumentException( sprintf( 'slug must contain at least one letter or digit, got: %s', is_scalar( $slug ) ? (string) $slug : gettype( $slug ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $owner = function_exists( 'get_user_by' ) ? get_user_by( 'slug', $clean ) : false;
        if ( $owner && isset( $owner->ID ) && (int) $owner->ID !== $user_id ) {
            throw new \InvalidArgumentException( sprintf( 'slug "%1$s" is already used by user %2$d; each user needs a different slug.', $clean, (int) $owner->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    public function describe( array $arguments ) {
        try {
            $id   = isset( $arguments['user_id'] ) && is_numeric( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;
            $user = $id > 0 ? $this->peek( '/wp/v2/users/' . $id, 'id,name,username,email,roles', array( 'context' => 'edit' ) ) : null;
            if ( ! $user ) {
                return '';
            }
            $parts = array_merge( self::describe_account_changes( $arguments, $user ), self::describe_profile_fields( $arguments ) );
            if ( self::flag( $arguments, 'skip_email' ) ) {
                $parts[] = 'no notification email';
            }
            return self::preview_sentence( $user, $id, $parts );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    private static function preview_sentence( array $user, int $id, array $parts ): string {
        $login = isset( $user['username'] ) ? (string) $user['username'] : '?';
        return sprintf(
            "Update user '%s' (%s, ID %d): %s.",
            self::rest_title( $user, 'name' ),
            '' !== $login ? $login : '?',
            $id,
            empty( $parts ) ? 'no field changes requested' : implode( '; ', $parts )
        );
    }

    private static function describe_account_changes( array $arguments, array $user ): array {
        $parts = array();
        if ( isset( $arguments['email'] ) && is_string( $arguments['email'] ) ) {
            $parts[] = sprintf( "email '%s' → '%s'", isset( $user['email'] ) ? $user['email'] : '', sanitize_email( $arguments['email'] ) );
        }
        if ( isset( $arguments['password'] ) && '' !== $arguments['password'] ) {
            $parts[] = 'set a new password';
        }
        $parts = array_merge( $parts, self::describe_role_changes( $arguments, $user ) );
        if ( self::flag( $arguments, 'remove_from_site' ) ) {
            $parts[] = is_multisite() ? 'remove the user from this site' : 'remove every role and capability';
        }
        return $parts;
    }

    private static function describe_role_changes( array $arguments, array $user ): array {
        $parts = array();
        if ( ! empty( $arguments['roles'] ) && is_array( $arguments['roles'] ) ) {
            $parts[] = sprintf( 'roles %s → %s', self::role_list( isset( $user['roles'] ) ? $user['roles'] : array() ), self::role_list( $arguments['roles'] ) );
        }
        foreach ( array( 'add_roles' => 'add roles: ', 'remove_roles' => 'remove roles: ' ) as $field => $label ) {
            if ( ! empty( $arguments[ $field ] ) && is_array( $arguments[ $field ] ) ) {
                $parts[] = $label . self::role_list( $arguments[ $field ] );
            }
        }
        return $parts;
    }

    private static function describe_profile_fields( array $arguments ): array {
        $parts = array();
        foreach ( array( 'name', 'first_name', 'last_name', 'nickname', 'slug', 'url', 'description', 'locale', 'admin_color', 'user_registered' ) as $field ) {
            if ( isset( $arguments[ $field ] ) && is_scalar( $arguments[ $field ] ) ) {
                $value   = (string) $arguments[ $field ];
                $value   = strlen( $value ) > 80 ? substr( $value, 0, 77 ) . '…' : $value;
                $parts[] = sprintf( "%s '%s'", $field, $value );
            }
        }
        foreach ( array_merge( self::FLAG_FIELDS, array( 'use_ssl' ) ) as $field ) {
            if ( isset( $arguments[ $field ] ) ) {
                $parts[] = sprintf( '%s %s', $field, rest_sanitize_boolean( $arguments[ $field ] ) ? 'on' : 'off' );
            }
        }
        return $parts;
    }

    private static function role_list( $roles ): string {
        $roles = array_values( array_filter( array_map( 'strval', (array) $roles ), 'strlen' ) );
        sort( $roles, SORT_STRING );
        return empty( $roles ) ? '(none)' : implode( ', ', $roles );
    }

    private static function flag( array $arguments, string $name ): bool {
        return ! empty( $arguments[ $name ] ) && rest_sanitize_boolean( $arguments[ $name ] );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'user_id' ) );

        $user_id = $this->parse_required_id( $arguments['user_id'], 'user_id' );
        $params  = $this->rest_params( $arguments, $user_id );
        $extra   = $this->extra_fields( $arguments );
        $plan    = $this->role_plan( $arguments, $params, $user_id );

        $before = self::snapshot( $user_id );

        if ( $plan['join_site'] ) {
            self::join_site( $user_id, $plan['site_role'] );
            unset( $params['roles'] );
        }

        $data = $this->maybe_rest_update( $user_id, $params, $extra, $plan );

        if ( $plan['role_ops'] ) {
            $this->apply_role_operations( $user_id, $plan['add_roles'], $plan['remove_roles'], $plan['remove_from_site'] );
        }

        $after   = self::snapshot( $user_id );
        $changed = self::changed_fields( $before, $after );

        $result                   = self::result_row( $user_id, $data, $plan['role_ops'] || $plan['join_site'] );
        $result['changed']        = ! empty( $changed );
        $result['changed_fields'] = $changed;
        return $result;
    }

    private function rest_params( array $arguments, int $user_id ): array {
        $params = array();

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

        $params = array_merge( $params, self::string_params( $arguments ) );
        if ( isset( $params['slug'] ) ) {
            self::refuse_unusable_slug( $params['slug'], $user_id );
        }
        return $params;
    }

    private static function string_params( array $arguments ): array {
        $params = array();
        foreach ( array( 'password', 'name', 'nickname', 'slug', 'locale' ) as $field ) {
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

    private function role_plan( array $arguments, array $params, int $user_id ): array {
        $plan = array(
            'add_roles'        => $this->role_argument( $arguments, 'add_roles' ),
            'remove_roles'     => $this->role_argument( $arguments, 'remove_roles' ),
            'remove_from_site' => self::flag( $arguments, 'remove_from_site' ),
            'add_to_site'      => self::flag( $arguments, 'add_to_site' ),
            'skip_email'       => self::flag( $arguments, 'skip_email' ),
        );

        $this->refuse_conflicting_role_arguments( $params, $plan['add_roles'], $plan['remove_roles'], $plan['remove_from_site'], $plan['add_to_site'] );

        $target            = get_userdata( $user_id );
        $plan['role_ops']  = ! empty( $plan['add_roles'] ) || ! empty( $plan['remove_roles'] ) || $plan['remove_from_site'];
        $plan['site_role'] = $plan['add_to_site'] ? (string) reset( $params['roles'] ) : '';
        $plan['join_site'] = false;
        if ( ( $plan['role_ops'] || $plan['add_to_site'] ) && ! $target ) {
            throw new \InvalidArgumentException( sprintf( 'User %d does not exist.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $plan['add_to_site'] ) {
            $plan['join_site'] = $this->check_add_to_site( $user_id, $plan['site_role'] );
        }
        if ( $plan['role_ops'] ) {
            $this->check_role_operations( $user_id, $target, $plan['add_roles'], $plan['remove_roles'], $plan['remove_from_site'] );
        }
        return $plan;
    }

    private static function join_site( int $user_id, string $role ): void {
        $joined = add_user_to_blog( get_current_blog_id(), $user_id, $role );
        if ( is_wp_error( $joined ) ) {
            throw new \RuntimeException( $joined->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function maybe_rest_update( int $user_id, array $params, array $extra, array $plan ): ?array {
        if ( empty( $params ) && empty( $extra ) && ( $plan['role_ops'] || $plan['join_site'] ) ) {
            return null;
        }
        try {
            return $this->rest_update( $user_id, $params, $extra, $plan['skip_email'] );
        } catch ( \RuntimeException $e ) {
            if ( $plan['join_site'] ) {
                throw new \RuntimeException( sprintf( 'User %1$d was added to this site with the role %2$s, but the profile update that followed failed: %3$s', $user_id, $plan['site_role'], $e->getMessage() ), 0, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            throw $e;
        }
    }

    private static function result_row( int $user_id, ?array $data, bool $role_change ): array {
        if ( null !== $data && ! $role_change ) {
            return array(
                'id'    => $data['id'],
                'name'  => $data['name'],
                'email' => $data['email'],
                'roles' => $data['roles'],
            );
        }
        $fresh = get_userdata( $user_id );
        return array(
            'id'    => $user_id,
            'name'  => self::fresh_or_rest( $fresh, 'display_name', $data, 'name' ),
            'email' => self::fresh_or_rest( $fresh, 'user_email', $data, 'email' ),
            'roles' => ( $fresh && isset( $fresh->roles ) ) ? array_values( (array) $fresh->roles ) : array(),
        );
    }

    private static function fresh_or_rest( $fresh, string $property, ?array $data, string $key ) {
        if ( $fresh && isset( $fresh->$property ) ) {
            return $fresh->$property;
        }
        return null !== $data ? $data[ $key ] : null;
    }

    private function extra_fields( array $arguments ): array {
        $out = array();
        foreach ( self::FLAG_FIELDS as $field ) {
            if ( isset( $arguments[ $field ] ) ) {
                $out[ $field ] = rest_sanitize_boolean( $arguments[ $field ] ) ? 'true' : 'false';
            }
        }
        if ( isset( $arguments['use_ssl'] ) ) {
            $out['use_ssl'] = rest_sanitize_boolean( $arguments['use_ssl'] ) ? 1 : 0;
        }
        if ( isset( $arguments['admin_color'] ) ) {
            $out['admin_color'] = self::admin_color_argument( $arguments['admin_color'] );
        }
        if ( isset( $arguments['user_registered'] ) ) {
            $out['user_registered'] = self::parse_user_registered( $arguments['user_registered'] );
        }
        return $out;
    }

    private static function admin_color_argument( $color ): string {
        if ( ! is_string( $color ) || ! preg_match( '/^[a-z0-9 _.\-@]+$/i', $color ) ) {
            throw new \InvalidArgumentException( sprintf( 'admin_color must be a color scheme slug of letters, digits, space, _ . - @ (e.g. "fresh"), got: %s', is_scalar( $color ) ? (string) $color : gettype( $color ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $color;
    }

    private function role_argument( array $arguments, string $field ): array {
        if ( ! isset( $arguments[ $field ] ) || array() === $arguments[ $field ] ) {
            return array();
        }
        if ( ! is_array( $arguments[ $field ] ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an array of role slugs.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $roles = array();
        foreach ( $arguments[ $field ] as $role ) {
            $role = is_string( $role ) ? sanitize_text_field( $role ) : '';
            if ( '' === $role ) {
                throw new \InvalidArgumentException( sprintf( '%s must be an array of non-empty role slugs.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $roles[] = $role;
        }
        return array_values( array_unique( $roles ) );
    }

    private function refuse_conflicting_role_arguments( array $params, array $add_roles, array $remove_roles, bool $remove_from_site, bool $add_to_site ): void {
        $replaces = isset( $params['roles'] );
        $adjusts  = ! empty( $add_roles ) || ! empty( $remove_roles );
        if ( $replaces && $adjusts ) {
            throw new \InvalidArgumentException( '`roles` replaces every role; it cannot be combined with add_roles or remove_roles. Send one or the other.' );
        }
        self::refuse_roles_added_and_removed( $add_roles, $remove_roles );
        if ( $remove_from_site && ( $replaces || $adjusts || $add_to_site ) ) {
            throw new \InvalidArgumentException( 'remove_from_site removes every role; it cannot be combined with roles, add_roles, remove_roles or add_to_site.' );
        }
        if ( $add_to_site ) {
            self::refuse_unusable_add_to_site( $params );
        }
    }

    private static function refuse_roles_added_and_removed( array $add_roles, array $remove_roles ): void {
        $both = array_intersect( $add_roles, $remove_roles );
        if ( ! empty( $both ) ) {
            throw new \InvalidArgumentException( sprintf( 'Role(s) %s are in both add_roles and remove_roles.', implode( ', ', $both ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private static function refuse_unusable_add_to_site( array $params ): void {
        if ( ! is_multisite() ) {
            throw new \InvalidArgumentException( 'add_to_site applies only to a multisite network; on a single site every user already belongs to the site.' );
        }
        if ( ! isset( $params['roles'] ) || 1 !== count( $params['roles'] ) ) {
            throw new \InvalidArgumentException( 'add_to_site needs `roles` with exactly one role: the role the user gets on this site.' );
        }
    }

    private function check_add_to_site( int $user_id, string $role ): bool {
        if ( is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
            return false;
        }
        if ( ! current_user_can( 'promote_user', $user_id ) ) {
            throw new \RuntimeException( sprintf( 'You are not allowed to add user %d to this site: it needs the promote_users capability.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $this->assert_roles_grantable( array( $role ), 'roles' );
        return true;
    }

    private function check_role_operations( int $user_id, $target, array $add_roles, array $remove_roles, bool $remove_from_site ): void {
        if ( is_multisite() && ! is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
            if ( $remove_from_site ) {
                return;
            }
            throw new \InvalidArgumentException( sprintf( 'User %d is not a member of this site. Pass add_to_site: true with roles [<role>] to add them first.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $current_roles = isset( $target->roles ) ? array_values( (array) $target->roles ) : array();

        if ( $remove_from_site ) {
            self::check_remove_from_site( $user_id, $current_roles );
            return;
        }

        if ( ! current_user_can( 'promote_user', $user_id ) ) {
            throw new \RuntimeException( sprintf( 'You are not allowed to change the roles of user %d: it needs the promote_users capability.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! empty( $add_roles ) ) {
            $this->assert_roles_grantable( $add_roles, 'add_roles' );
        }
        if ( ! empty( $remove_roles ) ) {
            $this->check_remove_roles( $user_id, $remove_roles, $current_roles );
        }
    }

    private static function check_remove_from_site( int $user_id, array $current_roles ): void {
        if ( get_current_user_id() === $user_id ) {
            throw new \RuntimeException( 'remove_from_site cannot be used on your own account.' );
        }
        $cap = is_multisite() ? 'remove_user' : 'promote_user';
        if ( ! current_user_can( $cap, $user_id ) ) {
            throw new \RuntimeException( sprintf( 'You are not allowed to remove user %1$d from this site: it needs the %2$s capability.', $user_id, is_multisite() ? 'remove_users' : 'promote_users' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( in_array( 'administrator', $current_roles, true ) ) {
            self::refuse_last_administrator( 'remove_from_site' );
        }
    }

    private function check_remove_roles( int $user_id, array $remove_roles, array $current_roles ): void {
        $this->assert_roles_grantable( $remove_roles, 'remove_roles' );
        if ( in_array( 'administrator', $remove_roles, true ) && in_array( 'administrator', $current_roles, true ) ) {
            self::refuse_last_administrator( 'remove_roles' );
        }
        if ( get_current_user_id() === $user_id && ! ( is_multisite() && current_user_can( 'manage_sites' ) )
            && ! self::any_role_edits_users( array_diff( $current_roles, $remove_roles ) ) ) {
            throw new \RuntimeException( 'remove_roles would leave your own account without a role that can edit users; WordPress does not let you demote yourself that way.' );
        }
    }

    private static function any_role_edits_users( array $roles ): bool {
        foreach ( $roles as $role ) {
            $object = get_role( $role );
            if ( is_object( $object ) && $object->has_cap( 'edit_users' ) ) {
                return true;
            }
        }
        return false;
    }

    private function assert_roles_grantable( array $roles, string $field ): void {
        $registry = function_exists( 'wp_roles' ) ? wp_roles() : null;
        foreach ( $roles as $role ) {
            if ( ! is_object( $registry ) || ! $registry->is_role( $role ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: the role "%2$s" does not exist.', $field, $role ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        $this->validate_assignable_roles( $roles );
        self::refuse_roles_not_editable( $roles, $field );
    }

    private static function refuse_roles_not_editable( array $roles, string $field ): void {
        if ( ! function_exists( 'get_editable_roles' ) && is_readable( ABSPATH . 'wp-admin/includes/user.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        if ( ! function_exists( 'get_editable_roles' ) ) {
            throw new \RuntimeException( 'Cannot check which roles you may assign: get_editable_roles() is not available.' );
        }
        $editable = get_editable_roles();
        foreach ( $roles as $role ) {
            if ( empty( $editable[ $role ] ) ) {
                throw new \RuntimeException( sprintf( '%1$s: you are not allowed to give or take the role "%2$s"; it is not one of the roles you may assign (get_editable_roles()).', $field, $role ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    private static function refuse_last_administrator( string $field ): void {
        $admin_count = count(
            get_users( array(
                'role'   => 'administrator',
                'fields' => 'ID',
                'number' => 2,
            ) )
        );
        if ( $admin_count <= 1 ) {
            throw new \RuntimeException( sprintf( '%s would remove the last administrator. Promote another user to administrator first.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function rest_update( int $user_id, array $params, array $extra, bool $skip_email ): array {
        $route = '/wp/v2/users/' . $user_id;
        $hooks = array_merge( self::extra_fields_hooks( $extra, $route ), self::skip_email_hooks( $skip_email ) );
        foreach ( $hooks as $hook ) {
            add_filter( $hook[0], $hook[1], $hook[2], $hook[3] );
        }
        try {
            return $this->rest_request( 'POST', $route, $params );
        } finally {
            foreach ( $hooks as $hook ) {
                remove_filter( $hook[0], $hook[1], $hook[2] );
            }
        }
    }

    private static function extra_fields_hooks( array $extra, string $route ): array {
        if ( empty( $extra ) ) {
            return array();
        }
        $applied = false;
        return array(
            array(
                'rest_pre_insert_user',
                static function ( $prepared, $request = null ) use ( $extra, $route, &$applied ) {
                    if ( $applied || ! is_object( $prepared ) || ! $request instanceof \WP_REST_Request || $route !== $request->get_route() ) {
                        return $prepared;
                    }
                    $applied = true;
                    foreach ( $extra as $key => $value ) {
                        $prepared->$key = $value;
                    }
                    return $prepared;
                },
                5,
                2,
            ),
        );
    }

    private static function skip_email_hooks( bool $skip_email ): array {
        if ( ! $skip_email ) {
            return array();
        }
        $hooks = array();
        foreach ( array( 'send_password_change_email', 'send_email_change_email' ) as $hook ) {
            $hooks[] = array(
                $hook,
                static function (): bool {
                    return false;
                },
                PHP_INT_MAX,
                1,
            );
        }
        return $hooks;
    }

    private function apply_role_operations( int $user_id, array $add_roles, array $remove_roles, bool $remove_from_site ): void {
        if ( $remove_from_site ) {
            self::remove_from_site( $user_id );
            return;
        }
        $user = get_userdata( $user_id );
        if ( ! $user || ! method_exists( $user, 'add_role' ) ) {
            throw new \RuntimeException( sprintf( 'User %d could not be loaded to change its roles.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        foreach ( $add_roles as $role ) {
            $user->add_role( $role );
        }
        foreach ( $remove_roles as $role ) {
            $user->remove_role( $role );
        }
    }

    private static function remove_from_site( int $user_id ): void {
        if ( is_multisite() ) {
            if ( is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
                $removed = remove_user_from_blog( $user_id, get_current_blog_id() );
                if ( is_wp_error( $removed ) ) {
                    throw new \RuntimeException( $removed->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }
            return;
        }
        $user = get_userdata( $user_id );
        if ( $user && method_exists( $user, 'remove_all_caps' ) ) {
            $user->remove_all_caps();
        }
    }

    private static function snapshot( int $user_id ): ?array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return null;
        }
        $snap = array();
        foreach ( self::SNAPSHOT_COLUMNS as $field => $column ) {
            $snap[ $field ] = isset( $user->$column ) ? (string) $user->$column : null;
        }
        foreach ( self::SNAPSHOT_META as $key ) {
            $snap[ $key ] = get_user_meta( $user_id, $key, true );
        }
        $roles = isset( $user->roles ) ? array_values( array_map( 'strval', (array) $user->roles ) ) : array();
        sort( $roles, SORT_STRING );
        $snap['roles'] = $roles;
        $caps = isset( $user->caps ) ? array_keys( array_filter( (array) $user->caps ) ) : array();
        sort( $caps, SORT_STRING );
        $snap['capabilities'] = $caps;
        if ( is_multisite() ) {
            $snap['site_membership'] = (bool) is_user_member_of_blog( $user_id, get_current_blog_id() );
        }
        return $snap;
    }

    private static function changed_fields( ?array $before, ?array $after ): array {
        if ( ! is_array( $before ) || ! is_array( $after ) ) {
            return array();
        }
        $changed = array();
        foreach ( $after as $field => $value ) {
            if ( ! array_key_exists( $field, $before ) || $before[ $field ] !== $value ) {
                $changed[] = $field;
            }
        }
        return $changed;
    }
}
