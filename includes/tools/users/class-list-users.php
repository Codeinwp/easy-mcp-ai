<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Users extends Base_Tool {

    





    const QUERY_ORDERBY = array( 'login', 'post_count', 'meta_value', 'meta_value_num' );

    
    const SEARCH_COLUMNS = array( 'ID', 'user_login', 'user_email', 'user_url', 'user_nicename', 'display_name' );

    
    const META_COMPARE = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE' );

    
    const META_TYPE = array( 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED' );

    




    const QUERY_LIST_VARS = array( 'role__not_in', 'capability', 'capability__not_in', 'login__in', 'login__not_in', 'nicename__in', 'nicename__not_in' );

    public function get_name() {
        return 'wp_list_users';
    }

    public function get_description() {
        return 'Lists WordPress users. Optional: `search` (matches name/username/email; wrapped in `*` wildcards unless `search_exact` is true), `roles` (array of role slugs — e.g. ["editor","author"] — OR operation), `per_page` (default 10, max 100), `page`, `orderby` (id/name/registered_date/email/slug/url, or WP_User_Query\'s login/post_count/meta_value/meta_value_num), `order` (asc/desc). WP_User_Query filters: `role` (role slugs the user must ALL hold, or ["none"] for users with no role on this site), `role__not_in`, `capability` (capabilities the user must all have), `capability__not_in`, `login`, `login__in`, `login__not_in`, `nicename`, `nicename__in`, `nicename__not_in`, `include`, `exclude` (user IDs), `has_published_posts` (post types), `search_columns`, and `meta_key` with optional `meta_value`/`meta_compare`/`meta_type` — a meta filter is refused for a key wp_get_user_meta would withhold from this caller. `network` (multisite, super admin with manage_network_users) lists every user of the network instead of this site; it cannot be combined with role, capability or published-posts filters, which WordPress ignores without a site. `include_capabilities` adds each user\'s capabilities, extra_capabilities and cap_key. Returns { users: [{ id, username, name, email, roles, registered_date, slug, url (website), link (author posts URL), user_registered (UTC "Y-m-d H:i:s"), user_status, spam and deleted (multisite only) }], total, total_pages, page, per_page }. Only users the caller may edit are listed. Requires `list_users` capability (administrators only by default).';
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
        $string_list = static function ( string $description ): array {
            return array(
                'type'        => 'array',
                'items'       => array( 'type' => 'string' ),
                'description' => $description,
            );
        };
        return array(
            'type'       => 'object',
            'properties' => array(
                'per_page'             => array(
                    'type'        => 'integer',
                    'description' => 'Number of users per page (1-100).',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'                 => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'search'               => array(
                    'type'        => 'string',
                    'description' => 'Search query to filter users.',
                ),
                'search_exact'         => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'When true, `search` is passed to WP_User_Query as typed, without the leading and trailing `*` the REST API adds: an exact match unless the query itself starts or ends with `*` (WP-CLI semantics). Requires `search`.',
                ),
                'search_columns'       => array(
                    'type'        => 'array',
                    'items'       => array(
                        'type' => 'string',
                        'enum' => self::SEARCH_COLUMNS,
                    ),
                    'description' => 'Users-table columns `search` looks in (WP_User_Query names). Requires `search`.',
                ),
                'roles'                => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Array of role slugs to filter by (e.g. administrator, editor, author).',
                ),
                'role'                 => $string_list( 'Role slugs the user must hold ALL of (WP_User_Query `role`). ["none"] alone lists the users who have no role on this site.' ),
                'role__not_in'         => $string_list( 'Exclude users holding any of these roles.' ),
                'capability'           => $string_list( 'Capabilities the user must have ALL of, through a role or a direct grant (WP_User_Query `capability`, WordPress 5.9+).' ),
                'capability__not_in'   => $string_list( 'Exclude users having any of these capabilities.' ),
                'login'                => array(
                    'type'        => 'string',
                    'description' => 'Exact user login.',
                ),
                'login__in'            => $string_list( 'Only these user logins.' ),
                'login__not_in'        => $string_list( 'Exclude these user logins.' ),
                'nicename'             => array(
                    'type'        => 'string',
                    'description' => 'Exact user_nicename (the URL slug).',
                ),
                'nicename__in'         => $string_list( 'Only these user_nicename values.' ),
                'nicename__not_in'     => $string_list( 'Exclude these user_nicename values.' ),
                'include'              => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Only these user IDs.',
                ),
                'exclude'              => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Exclude these user IDs.',
                ),
                'has_published_posts'  => $string_list( 'Only users with at least one published post of these post types (each must be registered).' ),
                'meta_key'             => array(
                    'type'        => 'string',
                    'description' => 'Only users with this user meta key (and, with meta_value, this value). Refused for a key wp_get_user_meta would withhold from you. Required by meta_value, meta_compare, meta_type and the meta_value orderings.',
                ),
                'meta_value'           => array(
                    'type'        => 'string',
                    'description' => 'Value compared with meta_key\'s value. Needs meta_key.',
                ),
                'meta_compare'         => array(
                    'type'        => 'string',
                    'enum'        => self::META_COMPARE,
                    'description' => 'Comparison between the stored value and meta_value (default "="). Needs meta_key.',
                ),
                'meta_type'            => array(
                    'type'        => 'string',
                    'enum'        => self::META_TYPE,
                    'description' => 'Cast applied to the stored value before comparing. Needs meta_key.',
                ),
                'network'              => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Multisite only: list every user of the network (WP_User_Query blog_id 0) instead of this site. Needs a super admin with manage_network_users. `roles` in the result are each user\'s roles on this site.',
                ),
                'include_capabilities' => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Add capabilities (every capability the user has), extra_capabilities (the user\'s own capability map) and cap_key (the user meta key that stores it) to each user.',
                ),
                'orderby'              => array(
                    'type'        => 'string',
                    'description' => 'Field to order results by. login, post_count (published posts of type post, plus the private ones you may read), meta_value and meta_value_num (both need meta_key) are WP_User_Query orderings.',
                    'enum'        => array( 'id', 'name', 'registered_date', 'email', 'slug', 'url', 'login', 'post_count', 'meta_value', 'meta_value_num' ),
                    'default'     => 'id',
                ),
                'order'                => array(
                    'type'        => 'string',
                    'description' => 'Sort direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'asc',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $params                      = self::rest_params( $arguments );
        $query_vars                  = $this->query_vars( $arguments, $params );
        list( $params, $query_vars ) = self::route_orderby_and_ids( $arguments, $params, $query_vars );

        $response = self::dispatch( $params, $query_vars );
        if ( $response->is_error() ) {
            return $this->empty_page_or_throw( $response, $params );
        }

        $users     = $response->get_data();
        $with_caps = ! empty( $arguments['include_capabilities'] ) && rest_sanitize_boolean( $arguments['include_capabilities'] );

        return array_merge(
            array( 'users' => self::user_rows( $users, $with_caps ) ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $users ) )
        );
    }

    






    private static function rest_params( array $arguments ): array {
        $params = array(
            'context' => 'edit',
        );

        $params['per_page'] = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $params['page']     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;

        if ( ! empty( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }

        if ( ! empty( $arguments['roles'] ) ) {
            $params['roles'] = array_map( 'sanitize_text_field', $arguments['roles'] );
        }
        return $params;
    }

    










    private static function route_orderby_and_ids( array $arguments, array $params, array $query_vars ): array {
        if ( ! empty( $arguments['orderby'] ) ) {
            if ( in_array( $arguments['orderby'], self::QUERY_ORDERBY, true ) ) {
                
                
                $params['orderby']     = 'id';
                $query_vars['orderby'] = $arguments['orderby'];
            } else {
                $params['orderby'] = $arguments['orderby'];
            }
        }

        if ( ! empty( $arguments['order'] ) ) {
            $params['order'] = $arguments['order'];
        }

        foreach ( array( 'include', 'exclude' ) as $name ) {
            if ( isset( $query_vars[ $name ] ) ) {
                $params[ $name ] = $query_vars[ $name ];
                unset( $query_vars[ $name ] );
            }
        }
        return array( $params, $query_vars );
    }

    







    private static function dispatch( array $params, array $query_vars ): \WP_REST_Response {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/users' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        
        
        $filter = self::query_vars_filter( $request, $query_vars );
        if ( null !== $filter ) {
            add_filter( 'rest_user_query', $filter, 10, 2 );
        }
        try {
            return rest_do_request( $request );
        } finally {
            if ( null !== $filter ) {
                remove_filter( 'rest_user_query', $filter, 10 );
            }
        }
    }

    







    private static function query_vars_filter( \WP_REST_Request $request, array $query_vars ): ?\Closure {
        if ( empty( $query_vars ) ) {
            return null;
        }
        return static function ( $prepared_args, $rest_request = null ) use ( $request, $query_vars ) {
            if ( $rest_request !== $request || ! is_array( $prepared_args ) ) {
                return $prepared_args;
            }
            return array_merge( $prepared_args, $query_vars );
        };
    }

    








    private function empty_page_or_throw( \WP_REST_Response $response, array $params ): array {
        $error = $response->as_error();
        
        if ( $this->is_invalid_page_error( $error ) ) {
            return array_merge(
                array( 'users' => array() ),
                $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
            );
        }
        throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    






    private static function user_rows( array $users, bool $with_caps ): array {
        $cap_key_base = self::cap_key();
        $result       = array();
        foreach ( $users as $user ) {
            $result[] = self::user_row( $user, $with_caps, $cap_key_base );
        }
        return $result;
    }

    








    private static function user_row( array $user, bool $with_caps, string $cap_key ): array {
        $row = array(
            'id'              => $user['id'],
            'username'        => $user['username'],
            'name'            => $user['name'],
            'email'           => $user['email'],
            'roles'           => $user['roles'],
            'registered_date' => $user['registered_date'],
            'slug'            => isset( $user['slug'] ) ? $user['slug'] : null,
            'url'             => isset( $user['url'] ) ? $user['url'] : null,
            'link'            => isset( $user['link'] ) ? $user['link'] : null,
        );
        $row = array_merge( $row, Get_User::user_columns( (int) $user['id'] ) );
        if ( $with_caps ) {
            $row['capabilities']       = Get_User::capability_map( isset( $user['capabilities'] ) ? $user['capabilities'] : null );
            $row['extra_capabilities'] = Get_User::capability_map( isset( $user['extra_capabilities'] ) ? $user['extra_capabilities'] : null );
            $row['cap_key']            = $cap_key;
        }
        return $row;
    }

    









    private function query_vars( array $arguments, array $params ): array {
        $vars = array_merge( self::list_vars( $arguments ), self::string_vars( $arguments ), $this->id_vars( $arguments ) );
        $vars = self::role_vars( $arguments, $vars );
        return array_merge(
            $vars,
            self::published_posts_vars( $arguments ),
            self::search_vars( $arguments, $params ),
            $this->meta_vars( $arguments ),
            self::network_vars( $arguments )
        );
    }

    





    private static function list_vars( array $arguments ): array {
        $vars = array();
        foreach ( self::QUERY_LIST_VARS as $name ) {
            if ( isset( $arguments[ $name ] ) && array() !== $arguments[ $name ] ) {
                $vars[ $name ] = self::string_list( $arguments[ $name ], $name );
            }
        }
        return $vars;
    }

    






    private static function string_vars( array $arguments ): array {
        $vars = array();
        foreach ( array( 'login', 'nicename' ) as $name ) {
            if ( isset( $arguments[ $name ] ) && '' !== $arguments[ $name ] ) {
                if ( ! is_string( $arguments[ $name ] ) ) {
                    throw new \InvalidArgumentException( sprintf( '%s must be a string.', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $vars[ $name ] = $arguments[ $name ];
            }
        }
        return $vars;
    }

    






    private function id_vars( array $arguments ): array {
        $vars = array();
        foreach ( array( 'include', 'exclude' ) as $name ) {
            if ( isset( $arguments[ $name ] ) && array() !== $arguments[ $name ] ) {
                if ( ! is_array( $arguments[ $name ] ) ) {
                    throw new \InvalidArgumentException( sprintf( '%s must be an array of user IDs.', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $ids = array();
                foreach ( $arguments[ $name ] as $id ) {
                    $ids[] = $this->parse_required_id( $id, $name . ' entry' );
                }
                $vars[ $name ] = array_values( array_unique( $ids ) );
            }
        }
        return $vars;
    }

    








    private static function role_vars( array $arguments, array $vars ): array {
        if ( ! isset( $arguments['role'] ) || array() === $arguments['role'] ) {
            return $vars;
        }
        $role = self::string_list( $arguments['role'], 'role' );
        if ( ! in_array( 'none', $role, true ) ) {
            $vars['role'] = $role;
            return $vars;
        }
        if ( count( $role ) > 1 ) {
            throw new \InvalidArgumentException( 'role ["none"] lists users with no role and cannot be combined with other roles.' );
        }
        return self::no_role_vars( $vars );
    }

    









    private static function no_role_vars( array $vars ): array {
        $no_role = function_exists( 'wp_get_users_with_no_role' ) ? array_map( 'intval', (array) wp_get_users_with_no_role() ) : array();
        if ( empty( $no_role ) ) {
            $vars['role'] = array( 'none' );
            return $vars;
        }
        $vars['include'] = isset( $vars['include'] ) ? array_values( array_intersect( $vars['include'], $no_role ) ) : $no_role;
        if ( empty( $vars['include'] ) ) {
            $vars['role'] = array( 'none' );
            unset( $vars['include'] );
        }
        return $vars;
    }

    






    private static function published_posts_vars( array $arguments ): array {
        if ( ! isset( $arguments['has_published_posts'] ) || array() === $arguments['has_published_posts'] ) {
            return array();
        }
        $types = self::string_list( $arguments['has_published_posts'], 'has_published_posts' );
        foreach ( $types as $type ) {
            if ( ! post_type_exists( $type ) ) {
                throw new \InvalidArgumentException( sprintf( 'has_published_posts: post type "%s" is not registered.', $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        return array( 'has_published_posts' => $types );
    }

    








    private static function search_vars( array $arguments, array $params ): array {
        $search_exact = ! empty( $arguments['search_exact'] ) && rest_sanitize_boolean( $arguments['search_exact'] );
        $has_columns  = isset( $arguments['search_columns'] ) && array() !== $arguments['search_columns'];
        if ( ( $search_exact || $has_columns ) && ! isset( $params['search'] ) ) {
            throw new \InvalidArgumentException( 'search_exact and search_columns apply to `search`, which was not given.' );
        }
        $vars = array();
        if ( $search_exact ) {
            $vars['search'] = $params['search'];
        }
        if ( $has_columns ) {
            $vars['search_columns'] = self::search_columns( $arguments['search_columns'] );
        }
        return $vars;
    }

    






    private static function search_columns( $value ): array {
        $columns = self::string_list( $value, 'search_columns' );
        $unknown = array_diff( $columns, self::SEARCH_COLUMNS );
        if ( ! empty( $unknown ) ) {
            throw new \InvalidArgumentException( sprintf( 'search_columns: unknown column(s) %s. Use %s.', implode( ', ', $unknown ), implode( ', ', self::SEARCH_COLUMNS ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array_values( array_unique( $columns ) );
    }

    








    private static function network_vars( array $arguments ): array {
        if ( empty( $arguments['network'] ) || ! rest_sanitize_boolean( $arguments['network'] ) ) {
            return array();
        }
        if ( ! is_multisite() ) {
            throw new \InvalidArgumentException( 'network applies only to a multisite network; this site is not one.' );
        }
        if ( ! is_super_admin() || ! current_user_can( 'manage_network_users' ) ) {
            throw new \RuntimeException( 'network lists every user of the network and needs a super admin with the manage_network_users capability.' );
        }
        self::refuse_site_only_filters( $arguments );
        return array( 'blog_id' => 0 );
    }

    









    private static function refuse_site_only_filters( array $arguments ): void {
        $site_only = array();
        foreach ( array( 'roles', 'role', 'role__not_in', 'capability', 'capability__not_in', 'has_published_posts' ) as $name ) {
            if ( ! empty( $arguments[ $name ] ) ) {
                $site_only[] = $name;
            }
        }
        if ( ! empty( $site_only ) ) {
            throw new \InvalidArgumentException( sprintf( 'network cannot be combined with %s: WordPress applies those filters only to one site.', implode( ', ', $site_only ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    









    private function meta_vars( array $arguments ): array {
        $has_key = isset( $arguments['meta_key'] ) && '' !== $arguments['meta_key'];
        if ( ! $has_key ) {
            self::refuse_meta_arguments_without_key( $arguments );
            return array();
        }
        $key = self::meta_key_argument( $arguments['meta_key'] );
        return array_merge( array( 'meta_key' => $key ), $this->meta_clause_vars( $arguments ) );
    }

    







    private static function refuse_meta_arguments_without_key( array $arguments ): void {
        $dependent = array();
        foreach ( array( 'meta_value', 'meta_compare', 'meta_type' ) as $name ) {
            if ( isset( $arguments[ $name ] ) && '' !== $arguments[ $name ] ) {
                $dependent[] = $name;
            }
        }
        if ( ! empty( $dependent ) ) {
            throw new \InvalidArgumentException( sprintf( '%s needs meta_key: without a key WordPress compares the value with every meta key.', implode( ', ', $dependent ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( isset( $arguments['orderby'] ) && in_array( $arguments['orderby'], array( 'meta_value', 'meta_value_num' ), true ) ) {
            throw new \InvalidArgumentException( sprintf( 'orderby %s needs meta_key.', $arguments['orderby'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    private static function meta_key_argument( $key ): string {
        if ( ! is_string( $key ) ) {
            throw new \InvalidArgumentException( 'meta_key must be a string.' );
        }
        
        
        
        
        
        if ( '0' === $key ) {
            throw new \InvalidArgumentException( 'Invalid meta_key "0": WordPress drops a meta key of "0" and would compare the value with every user meta key.' );
        }
        if ( ! preg_match( '/^[\x21-\x7E]+\z/', $key ) ) {
            throw new \InvalidArgumentException( 'Invalid meta_key: use the key in plain printable ASCII with no spaces, as WordPress stores it.' );
        }
        if ( ! class_exists( Get_User_Meta::class ) ) {
            require_once __DIR__ . '/class-get-user-meta.php';
        }
        if ( ! Get_User_Meta::is_key_readable( $key ) ) {
            throw new \RuntimeException( sprintf( 'meta_key "%s" is a key wp_get_user_meta withholds from you, so it cannot be used as a filter either.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $key;
    }

    







    private function meta_clause_vars( array $arguments ): array {
        $vars = array();
        if ( isset( $arguments['meta_value'] ) && '' !== $arguments['meta_value'] ) {
            if ( ! is_scalar( $arguments['meta_value'] ) ) {
                throw new \InvalidArgumentException( 'meta_value must be a string.' );
            }
            $vars['meta_value'] = (string) $arguments['meta_value'];
        }
        if ( isset( $arguments['meta_compare'] ) && '' !== $arguments['meta_compare'] ) {
            $compare = strtoupper( (string) $arguments['meta_compare'] );
            $this->refuse_unless_one_of( 'meta_compare', $compare, self::META_COMPARE, $arguments['meta_compare'], implode( ', ', self::META_COMPARE ) );
            $vars['meta_compare'] = $compare;
        }
        if ( isset( $arguments['meta_type'] ) && '' !== $arguments['meta_type'] ) {
            $type = strtoupper( (string) $arguments['meta_type'] );
            $this->refuse_unless_one_of( 'meta_type', $type, self::META_TYPE, $arguments['meta_type'], implode( ', ', self::META_TYPE ) );
            $vars['meta_type'] = $type;
        }
        return $vars;
    }

    







    private static function string_list( $value, string $name ): array {
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an array of strings.', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $out = array();
        foreach ( $value as $item ) {
            if ( ! is_string( $item ) || '' === trim( $item ) ) {
                throw new \InvalidArgumentException( sprintf( '%s must be an array of non-empty strings.', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[] = trim( $item );
        }
        return array_values( array_unique( $out ) );
    }

    




    private static function cap_key(): string {
        global $wpdb;
        $prefix = ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_blog_prefix' ) )
            ? $wpdb->get_blog_prefix()
            : ( isset( $wpdb->prefix ) ? $wpdb->prefix : 'wp_' );
        return $prefix . 'capabilities';
    }
}
