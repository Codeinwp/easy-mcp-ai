<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Confirm;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Db_Object_Command;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class User_Adapter extends Db_Object_Command {

    protected $obj_type = 'user';

    protected $obj_fields = array( 'ID', 'user_login', 'display_name', 'user_email', 'user_registered', 'roles' );

    const LIST_MAX = 1000;

    const PAGE_SIZE = 100;

    const LIST_FIELDS = array( 'ID', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name', 'spam', 'deleted', 'roles', 'url', 'caps', 'cap_key', 'allcaps', 'filter' );

    const SECRET_FIELDS = array( 'user_pass', 'user_activation_key' );

    const PLANNED_PASSWORD = Redactor::MASK;

    const MSG_CANNOT_EDIT   = 'Sorry, you are not allowed to edit this user.';
    const MSG_CANNOT_DELETE = 'Sorry, you are not allowed to delete this user.';

    const MSG_OWN_PASSWORD   = "wp_cli_run does not change the password of your own WordPress user (user %d): it would reset this connection's login cookies and put a new session in the response. Change it on your profile in wp-admin.";
    const MSG_NO_ROLE_CREATE = 'wp_cli_run cannot create a user with no role: wp_create_user gives every new user a role (default_role when none is named). Create the user, then remove the role with `user remove-role <user>`.';
    const MSG_EMPTY_ROLE     = "wp_cli_run refuses --role with no role: WordPress would strip every role from the user. Remove roles with `user remove-role <user>`.";
    const MSG_RICH_EDITING   = "wp_cli_run takes --%s=true or --%s=false (or 1 / 0), got '%s'.";
    const MSG_REASSIGN_GONE  = "--reassign=%s names no user, and WP-CLI would hand the deleted users' posts and links to that missing user. Pass the ID of an existing user, or leave --reassign out (with --yes) to delete them.";
    const MSG_NO_DEFAULT     = "wp_cli_run cannot set an empty role: no <role> was given and the default role (default_role) is empty. Name the role.";
    const MSG_LIST_CAP       = "wp_cli_run returns at most %d users in one 'wp user list' command, and this one matches %d. Narrow it with a filter, page it with --number=<n> --paged=<n>, or count it with --format=count.";
    const MSG_LIST_PAGED     = 'wp_cli_run pages a --number above %d only from the first page: wp_list_users returns at most %d users per call. Use --number=%d or less with --paged.';
    const MSG_FIELD_NOTE     = "WP-CLI's user list also reads a user meta key named as a field ('%s'); wp_cli_run lists the users-table columns and the documented fields. Read meta with `user meta get`.";
    const MSG_MISSING_NOTE   = "wp_get_user answered '%s' for user %d, so it is not a user of this site; WP-CLI, which looks users up across the whole network, would report it as existing.";
    const MSG_ROLES_NOT_HELD = 'None of the roles named is on %s (%d), so nothing was removed.';
    const NOTE_ROLES_NOT_HELD = '%s (%d) did not have %s, so it was not removed.';

    const LIST_QUERY = array(
        'role__in'           => array( 'roles', true ),
        'role__not_in'       => array( 'role__not_in', true ),
        'capability'         => array( 'capability', true ),
        'capability__not_in' => array( 'capability__not_in', true ),
        'login'              => array( 'login', false ),
        'login__in'          => array( 'login__in', true ),
        'login__not_in'      => array( 'login__not_in', true ),
        'nicename'           => array( 'nicename', false ),
        'nicename__in'       => array( 'nicename__in', true ),
        'nicename__not_in'   => array( 'nicename__not_in', true ),
        'search_columns'     => array( 'search_columns', true ),
        'meta_key'           => array( 'meta_key', false ),
        'meta_value'         => array( 'meta_value', false ),
        'meta_compare'       => array( 'meta_compare', false ),
        'meta_type'          => array( 'meta_type', false ),
    );

    const LIST_ORDERBY = array(
        'id'              => 'id',
        'ID'              => 'id',
        'display_name'    => 'name',
        'name'            => 'name',
        'login'           => 'login',
        'user_login'      => 'login',
        'nicename'        => 'slug',
        'user_nicename'   => 'slug',
        'email'           => 'email',
        'user_email'      => 'email',
        'url'             => 'url',
        'user_url'        => 'url',
        'registered'      => 'registered_date',
        'user_registered' => 'registered_date',
        'post_count'      => 'post_count',
        'meta_value'      => 'meta_value',
        'meta_value_num'  => 'meta_value_num',
    );

    const LIST_REFUSED = array(
        'offset'         => 'wp_list_users pages by page number; use --number=<n> with --paged=<n>',
        'blog_id'        => 'wp_cli_run lists the users of this site, or of the whole network with --network',
        'count_total'    => 'it only changes how WordPress counts, and --format=count counts already',
        'cache_results'  => 'it only changes WordPress caching',
        'who'            => 'WordPress deprecated it; use --capability=edit_posts',
        'meta_query'     => 'use --meta_key with --meta_value, --meta_compare and --meta_type',
        'capability__in' => 'wp_list_users does not take it yet; use --role__in or --capability',
    );

    const UPDATE_FIELDS = array(
        'user_pass'            => 'password',
        'user_nicename'        => 'slug',
        'user_url'             => 'url',
        'user_email'           => 'email',
        'display_name'         => 'name',
        'nickname'             => 'nickname',
        'first_name'           => 'first_name',
        'last_name'            => 'last_name',
        'description'          => 'description',
        'user_registered'      => 'user_registered',
        'locale'               => 'locale',
        'admin_color'          => 'admin_color',
    );

    const UPDATE_FLAGS = array( 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'show_admin_bar_front', 'use_ssl' );

    const UPDATE_REFUSED = array(
        'ID'                  => 'the user is named by <user>',
        'user_activation_key' => 'it is a password-reset secret; WordPress writes it itself',
        'spam'                => 'use `user spam` or `user unspam`',
        'deleted'             => 'WordPress sets it only when a network deletes a user',
        'user_status'         => 'WordPress no longer uses it',
        'meta_input'          => 'use `user meta update`',
    );

    public function exists( Invocation $inv, Context $ctx ): void {
        $arg = isset( $inv->args[0] ) ? $inv->args[0] : '';
        Fetchers::require_tool( $ctx, $inv, 'wp_get_user' );
        $user = Fetchers::of( 'user' )->get( $arg, $ctx->out );
        if ( ! $user ) {
            $ctx->out->halt( 1 );
        }
        $this->object_check( $ctx, $user, 'edit_user', self::MSG_CANNOT_EDIT, true );
        $result = $ctx->delegate( 'wp_get_user', array( 'user_id' => (int) $user->ID ) );
        if ( ! $result['ok'] ) {
            if ( 'Invalid user ID.' === $result['error'] ) {
                $ctx->note( sprintf( self::MSG_MISSING_NOTE, $result['error'], (int) $user->ID ) );
                $ctx->out->halt( 1 );
            }
            $ctx->out->error( (string) $result['error'] );
        }
        $ctx->out->success( "User with ID {$arg} exists." );
    }

    public function get( Invocation $inv, Context $ctx ): void {
        $user   = $this->resolve( $inv, $ctx, 'wp_get_user', isset( $inv->args[0] ) ? $inv->args[0] : '', 'edit_user', self::MSG_CANNOT_EDIT, true );
        $result = $ctx->delegate( 'wp_get_user', array( 'user_id' => (int) $user->ID ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $user_data = self::user_data( $result['payload'] );
        $assoc     = $inv->assoc;
        $this->report_redacted( $ctx, $assoc, array_keys( $user_data ) );
        $formatter = $this->get_formatter( $ctx, $assoc );
        $formatter->display_item( $user_data );
    }

    public function list_caps( Invocation $inv, Context $ctx ): void {
        $user   = $this->resolve( $inv, $ctx, 'wp_get_user', isset( $inv->args[0] ) ? $inv->args[0] : '', 'edit_user', self::MSG_CANNOT_EDIT, true );
        $result = $ctx->delegate( 'wp_get_user', array( 'user_id' => (int) $user->ID ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $assoc_args           = $inv->assoc;
        $active_user_cap_list = self::active_caps( $result['payload'], isset( $assoc_args['origin'] ) ? $assoc_args['origin'] : 'all', (bool) Read_Value::flag( $assoc_args, 'exclude-role-names' ) );

        if ( 'list' === $assoc_args['format'] ) {
            foreach ( $active_user_cap_list as $cap ) {
                $ctx->out->line( (string) $cap );
            }
            return;
        }
        $output_caps = array();
        foreach ( $active_user_cap_list as $cap ) {
            $output_cap       = new \stdClass();
            $output_cap->name = $cap;
            $output_caps[]    = $output_cap;
        }
        $formatter = new Formatter( $ctx->out, $assoc_args, array( 'name' ) );
        $formatter->display_items( $output_caps );
    }

    private static function active_caps( array $payload, $origin, bool $exclude_role_names ): array {
        $allcaps           = isset( $payload['capabilities'] ) && is_array( $payload['capabilities'] ) ? $payload['capabilities'] : array();
        $user_capabilities = isset( $payload['extra_capabilities'] ) && is_array( $payload['extra_capabilities'] ) ? $payload['extra_capabilities'] : array();
        $user_roles        = isset( $payload['roles'] ) ? array_values( (array) $payload['roles'] ) : array();
        switch ( $origin ) {
            case 'all':
                return self::all_caps( $allcaps, $user_roles, $exclude_role_names );
            case 'user':
                return self::user_caps( $user_capabilities, $user_roles );
            case 'role':
                return self::role_caps( $allcaps, $user_capabilities, $user_roles, $exclude_role_names );
        }
        return array();
    }

    private static function all_caps( array $allcaps, array $user_roles, bool $exclude_role_names ): array {
        $caps = array();
        foreach ( $allcaps as $capability => $active ) {
            if ( $exclude_role_names && in_array( $capability, $user_roles, true ) ) {
                continue;
            }
            if ( $active ) {
                $caps[] = $capability;
            }
        }
        return $caps;
    }

    private static function user_caps( array $user_capabilities, array $user_roles ): array {
        $caps = array();
        foreach ( $user_capabilities as $capability => $active ) {
            if ( true === $active && ! in_array( $capability, $user_roles, true ) ) {
                $caps[] = $capability;
            }
        }
        return $caps;
    }

    private static function role_caps( array $allcaps, array $user_capabilities, array $user_roles, bool $exclude_role_names ): array {
        $caps = array();
        foreach ( $allcaps as $capability => $active ) {
            if ( true === $active && ! isset( $user_capabilities[ $capability ] ) ) {
                $caps[] = $capability;
            }
            if ( true === $active && ! $exclude_role_names && in_array( $capability, $user_roles, true ) ) {
                $caps[] = $capability;
            }
        }
        return $caps;
    }

    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $network    = (bool) Read_Value::flag( $assoc_args, 'network' );
        if ( $network ) {
            $assoc_args = $this->network_fields( $ctx, $assoc_args );
        }

        $requested = self::requested_fields( $assoc_args, $this->obj_fields );
        $formatter = $this->get_formatter( $ctx, $assoc_args );
        $query     = $this->list_query( $ctx, $assoc_args, $network );
        if ( self::wants_caps( $ctx, $requested ) && ! in_array( $formatter->format, array( 'ids', 'count' ), true ) ) {
            $query['include_capabilities'] = true;
        }

        $number = isset( $query['__number'] ) ? $query['__number'] : 0;
        $paged  = isset( $query['__paged'] ) ? $query['__paged'] : 1;
        unset( $query['__number'], $query['__paged'] );

        if ( 'count' === $formatter->format ) {
            $ctx->out->out( (string) $this->list_count( $ctx, $query, $number, $paged ) );
            return;
        }

        $users = $this->list_all( $ctx, $query, $number, $paged );
        if ( 'ids' === $formatter->format ) {
            $ctx->out->out( self::ids_line( $users ) );
            return;
        }

        $this->report_redacted( $ctx, $assoc_args, self::LIST_FIELDS, $requested );
        $items = array();
        foreach ( $users as $user ) {
            $items[] = self::list_item( $user );
        }
        $formatter->display_items( $items );
    }

    private function network_fields( Context $ctx, array $assoc_args ): array {
        if ( ! \is_multisite() ) {
            $ctx->out->error( 'This is not a multisite installation.' );
        }
        $fields               = isset( $assoc_args['fields'] ) ? explode( ',', (string) $assoc_args['fields'] ) : $this->obj_fields;
        $assoc_args['fields'] = array_diff( $fields, array( 'roles' ) );
        return $assoc_args;
    }

    private static function wants_caps( Context $ctx, array $requested ): bool {
        $with_caps = false;
        foreach ( $requested as $field ) {
            $key = in_array( $field, self::LIST_FIELDS, true ) ? $field : ( in_array( 'user_' . $field, self::LIST_FIELDS, true ) ? 'user_' . $field : null );
            if ( null === $key ) {
                $ctx->note( sprintf( self::MSG_FIELD_NOTE, $field ) );
                continue;
            }
            if ( in_array( $key, array( 'caps', 'cap_key', 'allcaps' ), true ) ) {
                $with_caps = true;
            }
        }
        return $with_caps;
    }

    private function list_count( Context $ctx, array $query, int $number, int $paged ): int {
        $total = (int) $this->list_page( $ctx, $query, 1, 1 )['total'];
        return $number > 0 ? min( $number, max( 0, $total - ( $paged - 1 ) * $number ) ) : $total;
    }

    private static function ids_line( array $users ): string {
        $ids = array();
        foreach ( $users as $user ) {
            $ids[] = (int) $user['id'];
        }
        return implode( ' ', $ids );
    }

    public function preflight_create( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        if ( array_key_exists( 'role', $assoc ) ) {
            if ( false === $assoc['role'] || '' === $assoc['role'] || true === $assoc['role'] ) {
                $ctx->out->error( self::MSG_NO_ROLE_CREATE );
            }
            self::validate_role( $ctx, (string) $assoc['role'] );
        }
        self::flag_value( $ctx, $assoc, 'rich_editing' );
        $email = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';
        if ( ! \is_email( $email ) ) {
            $ctx->out->error( "The '{$email}' email address is invalid." );
        }
    }

    public function plan_create( Invocation $inv ): array {
        return array(
            array(
                'tool'      => 'wp_create_user',
                'arguments' => self::create_arguments( $inv, self::PLANNED_PASSWORD ),
            ),
        );
    }

    public function create( Invocation $inv, Context $ctx ): void {
        $login = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';
        $email = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';
        $assoc = $inv->assoc;

        Fetchers::require_tool( $ctx, $inv, 'wp_create_user' );
        if ( \username_exists( $login ) ) {
            $ctx->out->error( "The '{$login}' username is already registered." );
        }
        if ( ! \is_email( $email ) ) {
            $ctx->out->error( "The '{$email}' email address is invalid." );
        }

        $generated = ! isset( $assoc['user_pass'] );
        $password  = $generated ? \wp_generate_password( 24 ) : (string) $assoc['user_pass'];

        $result = $ctx->delegate( 'wp_create_user', self::create_arguments( $inv, $password ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $ctx->mark_changed();
        $user_id = isset( $result['payload']['id'] ) ? (int) $result['payload']['id'] : 0;

        if ( Read_Value::flag( $assoc, 'porcelain' ) ) {
            $ctx->out->line( (string) $user_id );
        } else {
            $ctx->out->success( "Created user {$user_id}." );
            if ( $generated ) {
                $ctx->out->line( "Password: {$password}" );
            }
        }
    }

    public function preflight_own_password( Invocation $inv, Context $ctx ): void {
        $is_update = 'user update' === $inv->command;
        if ( $is_update ) {
            self::update_arguments( $ctx, $inv->assoc );
        }
        if ( $is_update && ! array_key_exists( 'user_pass', $inv->assoc ) ) {
            return;
        }
        foreach ( $inv->args as $arg ) {
            if ( self::names_caller( (string) $arg ) ) {
                $ctx->out->error( sprintf( self::MSG_OWN_PASSWORD, $ctx->user_id() ) );
            }
        }
    }

    public function plan_update( Invocation $inv ): array {
        $fields = self::update_arguments( null, $inv->assoc );
        if ( isset( $fields['password'] ) ) {
            $fields['password'] = self::PLANNED_PASSWORD;
        }
        $plan = array();
        foreach ( $inv->args as $arg ) {
            $plan[] = array(
                'tool'      => 'wp_update_user',
                'arguments' => array_merge( array( 'user_id' => self::planned_id( (string) $arg ) ), $fields ),
            );
        }
        return $plan;
    }

    public function update( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        if ( isset( $assoc_args['user_login'] ) ) {
            $ctx->out->warning( "User logins can't be changed." );
            unset( $assoc_args['user_login'] );
        }
        if ( isset( $assoc_args['role'] ) ) {
            self::validate_role( $ctx, (string) $assoc_args['role'], true );
        }
        $fields = self::update_arguments( $ctx, $assoc_args );

        $users = $this->resolve_many( $inv, $ctx, 'wp_update_user', $inv->args, 'edit_user', self::MSG_CANNOT_EDIT, true );
        if ( empty( $users ) ) {
            $ctx->out->error( 'No valid users found.' );
        }
        if ( array_key_exists( 'password', $fields ) ) {
            $this->refuse_own_account( $ctx, $users );
        }

        $user_ids = array();
        foreach ( $users as $user ) {
            $user_ids[] = (string) $user->ID;
        }
        $this->_update(
            $inv,
            $ctx,
            $user_ids,
            $assoc_args,
            static function ( array $params, Batch $batch ) use ( $fields ): array {
                $user_id = (int) $params['ID'];
                $result  = $batch->delegate( 'wp_update_user', array_merge( array( 'user_id' => $user_id ), $fields ) );
                if ( ! $result['ok'] ) {
                    return array( 'error', (string) $result['error'] );
                }
                return array( 'success', "Updated user {$user_id}.", ! empty( $result['payload']['changed'] ) );
            }
        );
    }

    public function preflight_delete( Invocation $inv, Context $ctx ): void {
        $assoc    = $inv->assoc;
        $network  = Read_Value::flag( $assoc, 'network' ) && \is_multisite();
        $reassign = Read_Value::flag( $assoc, 'reassign' );
        if ( $network && $reassign ) {
            $ctx->out->error( 'Reassigning content to a different user is not supported on multisite.' );
        }
        if ( ! $reassign ) {
            Confirm::ask( $inv, $ctx, '--reassign parameter not passed. All associated posts will be deleted. Proceed?' );
        }
    }

    public function plan_delete( Invocation $inv ): array {
        $plan = array();
        foreach ( $inv->args as $arg ) {
            $plan[] = array(
                'tool'      => 'wp_delete_user',
                'arguments' => array_merge( array( 'user_id' => self::planned_id( (string) $arg ) ), self::delete_arguments( $inv->assoc ) ),
            );
        }
        return $plan;
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $network    = Read_Value::flag( $assoc_args, 'network' ) && \is_multisite();
        $reassign   = Read_Value::flag( $assoc_args, 'reassign' );

        Fetchers::require_tool( $ctx, $inv, 'wp_delete_user' );
        if ( $reassign && false === \get_userdata( $reassign ) ) {
            $ctx->out->error( sprintf( self::MSG_REASSIGN_GONE, (string) $reassign ) );
        }

        $users = $this->resolve_many( $inv, $ctx, 'wp_delete_user', $inv->args, 'delete_user', self::MSG_CANNOT_DELETE, false );
        $by_id = array();
        foreach ( $users as $user ) {
            $by_id[ (string) $user->ID ] = $user;
        }
        $arguments = self::delete_arguments( $assoc_args );

        $this->_delete(
            $inv,
            $ctx,
            array_keys( $by_id ),
            $assoc_args,
            static function ( string $user_id, array $assoc, Batch $batch ) use ( $network, $by_id, $arguments ): array {
                $user = $by_id[ $user_id ];
                if ( ! $network && \is_multisite() && empty( $user->roles ) ) {
                    return array( 'error', "No roles found for user {$user_id} on " . \home_url() . ', no users deleted.' );
                }
                $result = $batch->delegate( 'wp_delete_user', array_merge( array( 'user_id' => (int) $user_id ), $arguments ) );
                if ( ! $result['ok'] ) {
                    return array( 'error', "Failed deleting user {$user_id}. " . (string) $result['error'] );
                }
                return array( 'success', $network ? "Deleted user {$user_id}." : "Removed user {$user_id} from " . \home_url() . '.' );
            }
        );
    }

    public function set_role( Invocation $inv, Context $ctx ): void {
        $user = $this->resolve( $inv, $ctx, 'wp_update_user', isset( $inv->args[0] ) ? $inv->args[0] : '', 'edit_user', self::MSG_CANNOT_EDIT, true );
        $role = isset( $inv->args[1] ) ? (string) $inv->args[1] : (string) \get_option( 'default_role' );
        if ( '' === $role ) {
            $ctx->out->error( self::MSG_NO_DEFAULT );
        }
        self::validate_role( $ctx, $role );

        $arguments = array(
            'user_id' => (int) $user->ID,
            'roles'   => array( $role ),
        );
        if ( \is_multisite() && function_exists( 'is_user_member_of_blog' ) && ! \is_user_member_of_blog( (int) $user->ID, \get_current_blog_id() ) ) {
            $arguments['add_to_site'] = true;
        }
        $this->role_call( $ctx, $arguments );
        $ctx->out->success( "Added {$user->user_login} ({$user->ID}) to " . \site_url() . " as {$role}." );
    }

    public function add_role( Invocation $inv, Context $ctx ): void {
        $user  = $this->resolve( $inv, $ctx, 'wp_update_user', isset( $inv->args[0] ) ? $inv->args[0] : '', 'edit_user', self::MSG_CANNOT_EDIT, true );
        $roles = array_values( array_slice( $inv->args, 1 ) );
        if ( empty( $roles ) ) {
            $ctx->out->error( 'Please specify at least one role to add.' );
        }
        foreach ( $roles as $role ) {
            self::validate_role( $ctx, (string) $role );
        }
        $this->role_call(
            $ctx,
            array(
                'user_id'   => (int) $user->ID,
                'add_roles' => array_map( 'strval', $roles ),
            )
        );
        $message = implode( "', '", $roles );
        $label   = count( $roles ) > 1 ? 'roles' : 'role';
        $ctx->out->success( "Added '{$message}' {$label} for {$user->user_login} ({$user->ID})." );
    }

    public function remove_role( Invocation $inv, Context $ctx ): void {
        $user = $this->resolve( $inv, $ctx, 'wp_update_user', isset( $inv->args[0] ) ? $inv->args[0] : '', 'edit_user', self::MSG_CANNOT_EDIT, true );
        if ( isset( $inv->args[1] ) ) {
            $roles = array_values( array_slice( $inv->args, 1 ) );
            foreach ( $roles as $role ) {
                self::validate_role( $ctx, (string) $role );
            }
            $held     = isset( $user->roles ) ? array_values( array_map( 'strval', (array) $user->roles ) ) : array();
            $not_held = array_values( array_unique( array_diff( array_map( 'strval', $roles ), $held ) ) );
            $this->role_call(
                $ctx,
                array(
                    'user_id'      => (int) $user->ID,
                    'remove_roles' => array_map( 'strval', $roles ),
                )
            );
            if ( count( $not_held ) === count( array_unique( array_map( 'strval', $roles ) ) ) ) {
                $ctx->fail_missing_target( sprintf( self::MSG_ROLES_NOT_HELD, $user->user_login, (int) $user->ID ) );
            } elseif ( $not_held ) {
                $ctx->note( sprintf( self::NOTE_ROLES_NOT_HELD, $user->user_login, (int) $user->ID, "'" . implode( "', '", $not_held ) . "'" ) );
            }
            $message = implode( "', '", $roles );
            $label   = count( $roles ) > 1 ? 'roles' : 'role';
            $ctx->out->success( "Removed '{$message}' {$label} from {$user->user_login} ({$user->ID})." );
            return;
        }
        $this->role_call(
            $ctx,
            array(
                'user_id'          => (int) $user->ID,
                'remove_from_site' => true,
            )
        );
        $ctx->out->success( "Removed {$user->user_login} ({$user->ID}) from " . \site_url() . '.' );
    }

    public function plan_reset_password( Invocation $inv ): array {
        $plan = array();
        foreach ( $inv->args as $arg ) {
            $plan[] = array(
                'tool'      => 'wp_update_user',
                'arguments' => self::reset_arguments( self::planned_id( (string) $arg ), self::PLANNED_PASSWORD, $inv->assoc ),
            );
        }
        return $plan;
    }

    public function reset_password( Invocation $inv, Context $ctx ): void {
        $users = $this->resolve_many( $inv, $ctx, 'wp_update_user', $inv->args, 'edit_user', self::MSG_CANNOT_EDIT, true );
        $this->refuse_own_account( $ctx, $users );

        $assoc  = $inv->assoc;
        $batch  = new Batch( $inv, $ctx, 'password', 'reset' );
        $failed = array();
        $result = $batch->run(
            $users,
            static function ( object $user, Batch $batch ) use ( $ctx, $assoc, &$failed ): string {
                $new_pass = \wp_generate_password( 24 );
                $result   = $batch->delegate( 'wp_update_user', self::reset_arguments( (int) $user->ID, $new_pass, $assoc ) );
                if ( ! $result['ok'] ) {
                    $ctx->out->warning( "Failed to reset the password for {$user->user_login}: " . (string) $result['error'] );
                    $failed[] = $user->user_login;
                    return Batch::FAILURE;
                }
                $ctx->mark_changed();
                $ctx->out->log( "Reset password for {$user->user_login}." );
                return Batch::SUCCESS;
            },
            static function ( object $user ): string {
                return 'user ' . $user->user_login;
            }
        );

        $reset_user_count = (int) $result['successes'];
        if ( 1 === $reset_user_count ) {
            $ctx->out->success( "Password reset for {$reset_user_count} user." );
        } elseif ( $reset_user_count > 1 ) {
            $ctx->out->success( "Passwords reset for {$reset_user_count} users." );
        } elseif ( ! $failed ) {
            $ctx->out->error( 'No user found to reset password.' );
        }
        if ( $failed ) {
            $ctx->out->error( sprintf( 'The password was not reset for %d of %d users: %s.', count( $failed ), count( $users ), implode( ', ', $failed ) ) );
        }
    }

    public static function user_data( array $payload ): array {
        $row          = array(
            'ID'                  => self::column( $payload, 'id' ),
            'user_login'          => self::column( $payload, 'username' ),
            'user_pass'           => Redactor::MASK,
            'user_nicename'       => self::column( $payload, 'slug' ),
            'user_email'          => self::column( $payload, 'email' ),
            'user_url'            => self::column( $payload, 'url' ),
            'user_registered'     => self::column( $payload, 'user_registered' ),
            'user_activation_key' => Redactor::MASK,
            'user_status'         => self::column( $payload, 'user_status', '0' ),
            'display_name'        => self::column( $payload, 'name' ),
        );
        $row         += self::spam_columns( $payload );
        $row['roles'] = implode( ', ', self::role_names( $payload ) );
        return $row;
    }

    public static function list_item( array $user ): \stdClass {
        $item           = array(
            'ID'                  => (int) $user['id'],
            'user_login'          => (string) $user['username'],
            'user_pass'           => Redactor::MASK,
            'user_nicename'       => self::column( $user, 'slug' ),
            'user_email'          => self::column( $user, 'email' ),
            'user_url'            => self::column( $user, 'url' ),
            'user_registered'     => self::column( $user, 'user_registered' ),
            'user_activation_key' => Redactor::MASK,
            'user_status'         => self::column( $user, 'user_status', '0' ),
            'display_name'        => self::column( $user, 'name' ),
        );
        $item          += self::spam_columns( $user );
        $item['roles']  = implode( ',', self::role_names( $user ) );
        $item['url']    = self::column( $user, 'link' );
        $item['filter'] = null;
        if ( array_key_exists( 'capabilities', $user ) ) {
            $item['caps']    = self::map_column( $user, 'extra_capabilities' );
            $item['cap_key'] = self::column( $user, 'cap_key' );
            $item['allcaps'] = self::map_column( $user, 'capabilities' );
        }
        return (object) $item;
    }

    private static function column( array $row, string $key, string $fallback = '' ): string {
        return (string) ( isset( $row[ $key ] ) ? $row[ $key ] : $fallback );
    }

    private static function map_column( array $row, string $key ): array {
        return isset( $row[ $key ] ) && is_array( $row[ $key ] ) ? $row[ $key ] : array();
    }

    private static function spam_columns( array $row ): array {
        if ( ! array_key_exists( 'spam', $row ) ) {
            return array();
        }
        return array(
            'spam'    => ! empty( $row['spam'] ) ? '1' : '0',
            'deleted' => ! empty( $row['deleted'] ) ? '1' : '0',
        );
    }

    private static function role_names( array $row ): array {
        return array_map( 'strval', isset( $row['roles'] ) ? array_values( (array) $row['roles'] ) : array() );
    }

    private function resolve( Invocation $inv, Context $ctx, string $tool, string $arg, string $cap, string $refusal, bool $self_ok ): object {
        return Fetchers::of( 'user' )->get_check_for(
            $ctx,
            $inv,
            $tool,
            $arg,
            static function ( object $user ) use ( $ctx, $cap, $refusal, $self_ok ): ?string {
                return self::may_act( $ctx, $user, $cap, $self_ok ) ? null : $refusal;
            }
        );
    }

    private function resolve_many( Invocation $inv, Context $ctx, string $tool, array $args, string $cap, string $refusal, bool $self_ok ): array {
        Fetchers::require_tool( $ctx, $inv, $tool );
        $fetcher = Fetchers::of( 'user' );
        $users   = array();
        foreach ( $args as $arg ) {
            $user = $fetcher->get( $arg, $ctx->out );
            if ( $user ) {
                $users[] = $user;
                continue;
            }
            $message = sprintf( $fetcher->message(), $arg );
            $ctx->out->warning( $message );
            $ctx->fail_missing_target( $message );
        }
        foreach ( $users as $user ) {
            if ( ! self::may_act( $ctx, $user, $cap, $self_ok ) ) {
                $ctx->out->error( $refusal . sprintf( ' (user %d)', (int) $user->ID ) );
            }
        }
        return $users;
    }

    private function object_check( Context $ctx, object $user, string $cap, string $refusal, bool $self_ok ): void {
        if ( ! self::may_act( $ctx, $user, $cap, $self_ok ) ) {
            $ctx->out->error( $refusal );
        }
    }

    private static function may_act( Context $ctx, object $user, string $cap, bool $self_ok ): bool {
        $id = isset( $user->ID ) ? (int) $user->ID : 0;
        return ( $self_ok && $id === $ctx->user_id() ) || \current_user_can( $cap, $id );
    }

    private function role_call( Context $ctx, array $arguments ): void {
        $result = $ctx->delegate( 'wp_update_user', $arguments );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        if ( ! empty( $result['payload']['changed'] ) ) {
            $ctx->mark_changed();
        }
    }

    private function refuse_own_account( Context $ctx, array $users ): void {
        foreach ( $users as $user ) {
            if ( (int) $user->ID === $ctx->user_id() ) {
                $ctx->out->error( sprintf( self::MSG_OWN_PASSWORD, $ctx->user_id() ) );
            }
        }
    }

    private static function names_caller( string $arg ): bool {
        $me = \wp_get_current_user();
        if ( ! is_object( $me ) || empty( $me->ID ) ) {
            return false;
        }
        if ( is_numeric( $arg ) ) {
            return (int) $arg === (int) $me->ID;
        }
        $login = isset( $me->user_login ) ? (string) $me->user_login : '';
        $email = isset( $me->user_email ) ? (string) $me->user_email : '';
        return ( '' !== $login && $arg === $login ) || ( '' !== $email && $arg === $email );
    }

    private static function planned_id( string $arg ) {
        return ctype_digit( $arg ) && (int) $arg > 0 ? (int) $arg : $arg;
    }

    private static function validate_role( Context $ctx, string $role, bool $warn_user_only = false ): void {
        if ( '' === $role ) {
            return;
        }
        $roles = function_exists( 'wp_roles' ) ? \wp_roles() : null;
        if ( is_object( $roles ) && method_exists( $roles, 'is_role' ) && $roles->is_role( $role ) ) {
            return;
        }
        if ( $warn_user_only ) {
            $ctx->out->warning( "Role doesn't exist: {$role}" );
        } else {
            $ctx->out->error( "Role doesn't exist: {$role}" );
        }
    }

    private static function flag_value( ?Context $ctx, array $assoc, string $key ): ?bool {
        if ( ! array_key_exists( $key, $assoc ) ) {
            return null;
        }
        $value = $assoc[ $key ];
        $flag  = self::read_flag( $value, $key );
        if ( null === $flag && null !== $ctx ) {
            $ctx->out->error( sprintf( self::MSG_RICH_EDITING, $key, $key, is_scalar( $value ) ? (string) $value : '' ) );
        }
        return $flag;
    }

    private static function read_flag( $value, string $key ): ?bool {
        if ( true === $value || 'true' === $value || '1' === $value ) {
            return true;
        }
        if ( false === $value || 'false' === $value || '0' === $value ) {
            return false;
        }
        if ( '' === $value && 'rich_editing' === $key ) {
            return true;
        }
        return null;
    }

    private static function text_value( $value ): string {
        return is_string( $value ) ? $value : ( true === $value ? '1' : '' );
    }

    private static function create_arguments( Invocation $inv, string $password ): array {
        $assoc     = $inv->assoc;
        $arguments = array(
            'username' => isset( $inv->args[0] ) ? (string) $inv->args[0] : '',
            'email'    => isset( $inv->args[1] ) ? (string) $inv->args[1] : '',
            'password' => $password,
        );
        if ( isset( $assoc['role'] ) && is_string( $assoc['role'] ) && '' !== $assoc['role'] ) {
            $arguments['roles'] = array( $assoc['role'] );
        }
        $arguments = array_merge( $arguments, self::profile_arguments( $assoc ) );
        $rich      = self::flag_value( null, $assoc, 'rich_editing' );
        if ( null !== $rich ) {
            $arguments['rich_editing'] = $rich;
        }
        if ( Read_Value::flag( $assoc, 'send-email' ) ) {
            $arguments['send_email'] = true;
        }
        return $arguments;
    }

    private static function profile_arguments( array $assoc ): array {
        $map       = array(
            'first_name'      => 'first_name',
            'last_name'       => 'last_name',
            'display_name'    => 'name',
            'nickname'        => 'nickname',
            'user_nicename'   => 'slug',
            'user_url'        => 'url',
            'description'     => 'description',
            'user_registered' => 'user_registered',
        );
        $arguments = array();
        foreach ( $map as $cli => $tool ) {
            if ( isset( $assoc[ $cli ] ) ) {
                $arguments[ $tool ] = self::text_value( $assoc[ $cli ] );
            }
        }
        return $arguments;
    }

    private static function update_arguments( ?Context $ctx, array $assoc ): array {
        $fields = array();
        $errors = array();
        foreach ( $assoc as $key => $value ) {
            $key = (string) $key;
            if ( 'user_login' === $key ) {
                continue;
            }
            $field = self::update_field( $ctx, $assoc, $key, $value );
            if ( is_string( $field ) ) {
                $errors[] = $field;
            } else {
                $fields = array_merge( $fields, $field );
            }
        }
        if ( $errors && null !== $ctx ) {
            $ctx->out->error( "Parameter errors:\n" . implode( "\n", $errors ) );
        }
        return $fields;
    }

    private static function update_field( ?Context $ctx, array $assoc, string $key, $value ) {
        if ( 'skip-email' === $key ) {
            return $value ? array( 'skip_email' => true ) : array();
        }
        if ( 'role' === $key ) {
            return self::update_role( $ctx, $value );
        }
        if ( isset( self::UPDATE_FIELDS[ $key ] ) ) {
            return array( self::UPDATE_FIELDS[ $key ] => self::text_value( $value ) );
        }
        if ( in_array( $key, self::UPDATE_FLAGS, true ) ) {
            $flag = self::flag_value( $ctx, $assoc, $key );
            return null !== $flag ? array( $key => $flag ) : array();
        }
        $reason = isset( self::UPDATE_REFUSED[ $key ] ) ? self::UPDATE_REFUSED[ $key ] : 'wp_update_user does not take it, and WordPress would ignore it';
        return sprintf( ' --%s parameter is not available through wp_cli_run: %s', $key, $reason );
    }

    private static function update_role( ?Context $ctx, $value ): array {
        if ( false === $value || '' === $value || true === $value ) {
            if ( null !== $ctx ) {
                $ctx->out->error( self::MSG_EMPTY_ROLE );
            }
            return array();
        }
        return array( 'roles' => array( (string) $value ) );
    }

    private static function delete_arguments( array $assoc ): array {
        $reassign  = Read_Value::flag( $assoc, 'reassign' );
        $network   = Read_Value::flag( $assoc, 'network' ) && \is_multisite();
        $arguments = array();
        if ( $reassign && true !== $reassign ) {
            $arguments['reassign'] = (int) $reassign;
        } else {
            $arguments['delete_content'] = true;
        }
        if ( $network ) {
            $arguments['network'] = true;
        }
        return $arguments;
    }

    private static function reset_arguments( $user_id, string $password, array $assoc ): array {
        $arguments = array(
            'user_id'  => $user_id,
            'password' => $password,
        );
        if ( Read_Value::flag( $assoc, 'skip-email' ) ) {
            $arguments['skip_email'] = true;
        }
        return $arguments;
    }

    private function list_query( Context $ctx, array $assoc, bool $network ): array {
        $query  = array(
            'orderby' => 'login',
            'order'   => 'asc',
        );
        $errors = array();
        if ( $network ) {
            $query['network'] = true;
        }
        foreach ( $assoc as $key => $value ) {
            $key = (string) $key;
            if ( in_array( $key, array( 'network', 'fields', 'format', 'field' ), true ) ) {
                continue;
            }
            $mapped = self::list_argument( $key, $value );
            if ( is_string( $mapped ) ) {
                $errors[] = $mapped;
                continue;
            }
            $query = array_merge( $query, $mapped );
        }
        if ( $errors ) {
            $ctx->out->error( "Parameter errors:\n" . implode( "\n", $errors ) );
        }
        return self::list_paging( $ctx, $query );
    }

    private static function list_argument( string $key, $value ) {
        if ( isset( self::LIST_QUERY[ $key ] ) ) {
            list( $tool_key, $split ) = self::LIST_QUERY[ $key ];
            $text                     = self::text_value( $value );
            return array( $tool_key => $split ? self::split_list( $text ) : $text );
        }
        switch ( $key ) {
            case 'role':
                return self::list_role( $value );
            case 'search':
                return array(
                    'search'       => is_string( $value ) ? $value : '',
                    'search_exact' => true,
                );
            case 'include':
            case 'exclude':
                return array( $key => self::id_list( $value ) );
            case 'has_published_posts':
                return array( 'has_published_posts' => self::published_post_types( $value ) );
        }
        return self::list_order_argument( $key, $value );
    }

    private static function list_order_argument( string $key, $value ) {
        switch ( $key ) {
            case 'orderby':
                return self::list_orderby( $value );
            case 'order':
                return self::list_order( $value );
            case 'number':
                return array( '__number' => max( 0, (int) $value ) );
            case 'paged':
                return array( '__paged' => max( 1, (int) $value ) );
        }
        $reason = isset( self::LIST_REFUSED[ $key ] ) ? self::LIST_REFUSED[ $key ] : 'wp_list_users does not take this WP_User_Query argument';
        return sprintf( ' --%s parameter is not available through wp_cli_run: %s', $key, $reason );
    }

    private static function list_orderby( $value ) {
        if ( is_string( $value ) && isset( self::LIST_ORDERBY[ $value ] ) ) {
            return array( 'orderby' => self::LIST_ORDERBY[ $value ] );
        }
        return sprintf( ' --orderby=%s is not available through wp_cli_run: wp_list_users orders by %s', is_scalar( $value ) ? (string) $value : '', implode( ', ', array_keys( self::LIST_ORDERBY ) ) );
    }

    private static function list_order( $value ) {
        $order = is_string( $value ) ? strtolower( $value ) : '';
        if ( in_array( $order, array( 'asc', 'desc' ), true ) ) {
            return array( 'order' => $order );
        }
        return sprintf( ' --order=%s is not available through wp_cli_run: use asc or desc', is_scalar( $value ) ? (string) $value : '' );
    }

    private static function list_role( $value ): array {
        if ( ! is_string( $value ) || '' === $value ) {
            return array();
        }
        return array( 'role' => 'none' === $value ? array( 'none' ) : self::split_list( $value ) );
    }

    private static function split_list( string $text ): array {
        return array_values( array_filter( array_map( 'trim', explode( ',', $text ) ), 'strlen' ) );
    }

    private static function id_list( $value ): array {
        $ids = preg_split( '/[\s,]+/', is_string( $value ) ? $value : '' );
        return array_values( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) ) );
    }

    private static function published_post_types( $value ): array {
        return true === $value && function_exists( 'get_post_types' )
            ? array_values( \get_post_types( array( 'public' => true ) ) )
            : array( (string) $value );
    }

    private static function list_paging( Context $ctx, array $query ): array {
        $number = isset( $query['__number'] ) ? (int) $query['__number'] : 0;
        $paged  = isset( $query['__paged'] ) ? (int) $query['__paged'] : 1;
        if ( $number > self::PAGE_SIZE && $paged > 1 ) {
            $ctx->out->error( sprintf( self::MSG_LIST_PAGED, self::PAGE_SIZE, self::PAGE_SIZE, self::PAGE_SIZE ) );
        }
        return $query;
    }

    private function list_all( Context $ctx, array $query, int $number, int $paged ): array {
        if ( $number > 0 && $number <= self::PAGE_SIZE ) {
            $payload = $this->list_page( $ctx, $query, $number, $paged );
            self::refuse_past_cap( $ctx, min( $number, max( 0, (int) $payload['total'] - ( $paged - 1 ) * $number ) ) );
            return self::take_users( array(), self::page_users( $payload ), $number );
        }
        return $this->list_pages( $ctx, $query, $number > 0 ? $number : PHP_INT_MAX );
    }

    private function list_pages( Context $ctx, array $query, int $want ): array {
        $page    = 1;
        $payload = $this->list_page( $ctx, $query, self::PAGE_SIZE, $page );
        self::refuse_past_cap( $ctx, min( $want, (int) $payload['total'] ) );
        $users = array();
        while ( true ) {
            $rows  = self::page_users( $payload );
            $users = self::take_users( $users, $rows, $want );
            if ( ! $rows || count( $users ) >= $want || $page >= (int) $payload['total_pages'] ) {
                return $users;
            }
            $payload = $this->list_page( $ctx, $query, self::PAGE_SIZE, ++$page );
        }
    }

    private static function refuse_past_cap( Context $ctx, int $expected ): void {
        if ( $expected > self::LIST_MAX ) {
            $ctx->out->error( sprintf( self::MSG_LIST_CAP, self::LIST_MAX, $expected ) );
        }
    }

    private static function page_users( array $payload ): array {
        return isset( $payload['users'] ) && is_array( $payload['users'] ) ? $payload['users'] : array();
    }

    private static function take_users( array $users, array $rows, int $want ): array {
        foreach ( $rows as $row ) {
            if ( count( $users ) >= $want ) {
                break;
            }
            $users[] = $row;
        }
        return $users;
    }

    private function list_page( Context $ctx, array $query, int $per_page, int $page ): array {
        $result = $ctx->delegate(
            'wp_list_users',
            array_merge(
                $query,
                array(
                    'per_page' => $per_page,
                    'page'     => $page,
                )
            )
        );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $payload = $result['payload'];
        $payload += array(
            'users'       => array(),
            'total'       => 0,
            'total_pages' => 0,
        );
        return $payload;
    }

    private static function requested_fields( array $assoc, array $default ): array {
        if ( isset( $assoc['field'] ) && is_string( $assoc['field'] ) ) {
            return array( trim( $assoc['field'] ) );
        }
        if ( ! empty( $assoc['fields'] ) ) {
            $fields = is_array( $assoc['fields'] ) ? $assoc['fields'] : explode( ',', (string) $assoc['fields'] );
            return array_values( array_map( 'trim', array_map( 'strval', $fields ) ) );
        }
        return array_values( $default );
    }

    private function report_redacted( Context $ctx, array $assoc, array $keys, ?array $requested = null ): void {
        $requested = null !== $requested ? $requested : self::requested_fields( $assoc, $this->obj_fields );
        foreach ( $requested as $field ) {
            $key = in_array( $field, $keys, true ) ? $field : 'user_' . $field;
            if ( in_array( $key, self::SECRET_FIELDS, true ) ) {
                $ctx->redacted( $key );
            }
        }
    }
}
