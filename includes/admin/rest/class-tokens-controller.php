<?php























namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Admin\Access_Presets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tokens_Controller extends Admin_Rest_Controller {

    
    const TOKENS_LIMIT = 200;

    
    const USERS_LIMIT = 200;

    
    const DESCRIPTION_MAX = 160;

    
    private $token_manager;

    
    private $presets;

    
    private $tool_registry;

    




    public function __construct( $token_manager, Access_Presets $presets, $tool_registry = null ) {
        $this->token_manager = $token_manager;
        $this->presets       = $presets;
        $this->tool_registry = $tool_registry;
    }

    public function register_routes() {
        $this->register_route( '/tokens', \WP_REST_Server::READABLE, array( $this, 'get_tokens' ) );
        $this->register_route( '/tokens/tool-catalogue', \WP_REST_Server::READABLE, array( $this, 'get_tool_catalogue' ) );
        $this->register_route( '/tokens', \WP_REST_Server::CREATABLE, array( $this, 'create_token' ) );
        $this->register_route( '/tokens/(?P<id>\d+)', self::UPDATABLE, array( $this, 'update_token' ), self::id_args() );
        $this->register_route( '/tokens/(?P<id>\d+)/revoke', \WP_REST_Server::CREATABLE, array( $this, 'revoke_token' ), self::id_args() );
        $this->register_route( '/tokens/(?P<id>\d+)/rebind', \WP_REST_Server::CREATABLE, array( $this, 'rebind_token' ), self::id_args() );
        $this->register_delete_route( '/tokens/(?P<id>\d+)', array( $this, 'delete_token' ), self::id_args() );
    }

    

    



    public function get_tokens( $request ) {
        $rows  = (array) $this->token_manager->get_all_tokens( self::TOKENS_LIMIT );
        $total = (int) $this->token_manager->count_tokens();
        $names = $this->display_names( array_column( $rows, 'wp_user_id' ) );

        $tokens = array();
        foreach ( $rows as $row ) {
            $tokens[] = $this->shape( (array) $row, $names );
        }

        return $this->ok( array(
            'tokens'    => $tokens,
            'users'     => self::assignable_users(),
            'total'     => $total,
            'truncated' => count( $rows ) >= self::TOKENS_LIMIT && $total > self::TOKENS_LIMIT,
            'limit'     => self::TOKENS_LIMIT,
            
            
            'siteHost'  => \Easy_MCP_AI\Site_Host::current(),
            
            
            'approvalRequired' => self::approval_required(),
        ) );
    }

    







    public function get_tool_catalogue( $request ) {
        $by_category = $this->tool_registry && method_exists( $this->tool_registry, 'get_tools_by_category' )
            ? (array) $this->tool_registry->get_tools_by_category()
            : array();
        return $this->ok(
            array(
                'categories'   => self::catalogue( $by_category ),
                
                
                
                'privilegedOn' => $this->privileged_on(),
            )
        );
    }

    
    private function privileged_on() {
        $on = array();
        if ( ! $this->tool_registry || ! method_exists( $this->tool_registry, 'get_all_tool_names' ) || ! method_exists( $this->tool_registry, 'get_tool' ) ) {
            return $on;
        }
        foreach ( (array) $this->tool_registry->get_all_tool_names() as $name ) {
            $tool = $this->tool_registry->get_tool( $name );
            if ( $tool instanceof \Easy_MCP_AI\Tools\Privileged_Tool && \Easy_MCP_AI\Tools\Tool_State::is_enabled( $tool ) ) {
                $on[] = (string) $name;
            }
        }
        sort( $on );
        return $on;
    }

    



    public static function catalogue( array $by_category ) {
        
        
        require_once dirname( __DIR__, 2 ) . '/auth/class-permission-guard.php';
        $categories = array();
        foreach ( $by_category as $key => $defs ) {
            $tools = array();
            foreach ( (array) $defs as $def ) {
                $name = is_array( $def ) ? (string) ( $def['name'] ?? '' ) : (string) $def;
                if ( '' === $name || in_array( $name, \Easy_MCP_AI\Auth\Permission_Guard::EVERY_CREDENTIAL, true ) ) {
                    continue;
                }
                $tools[] = array(
                    'name'        => $name,
                    'description' => self::short_description( is_array( $def ) ? (string) ( $def['description'] ?? '' ) : '' ),
                );
            }
            if ( empty( $tools ) ) {
                continue;
            }
            usort( $tools, static function ( $a, $b ) {
                return strcmp( $a['name'], $b['name'] );
            } );
            $privileged   = 'privileged' === (string) $key;
            $categories[] = array(
                'key'        => (string) $key,
                'label'      => $privileged
                    ? \__( 'Privileged: runs code, reads server files or rewrites the database', 'easy-mcp-ai' )
                    : ucfirst( str_replace( array( '-', '_' ), ' ', (string) $key ) ),
                'tools'      => $tools,
                
                
                
                'privileged' => $privileged,
            );
        }
        return $categories;
    }

    
    public static function short_description( $text ) {
        $text = trim( (string) $text );
        if ( '' === $text ) {
            return '';
        }
        if ( preg_match( '/^(.+?[.!?])(\s|$)/u', $text, $m ) ) {
            $text = $m[1];
        }
        if ( mb_strlen( $text ) > self::DESCRIPTION_MAX ) {
            $text = rtrim( mb_substr( $text, 0, self::DESCRIPTION_MAX - 3 ) ) . '…';
        }
        return $text;
    }

    

    



    public function create_token( $request ) {
        $name = \sanitize_text_field( (string) $request->get_param( 'name' ) );
        if ( '' === trim( $name ) ) {
            return $this->fail( 'easy_mcp_ai_name_required', \__( 'Give the token a name.', 'easy-mcp-ai' ), 400, array( 'field' => 'name' ) );
        }

        $user_id = \absint( $request->get_param( 'userId' ) );
        if ( ! $user_id ) {
            $user_id = \get_current_user_id();
        }
        if ( ! self::is_assignable_user( $user_id ) ) {
            return $this->invalid_user();
        }

        $allowed = $this->resolve_allowed_tools( $request );
        if ( \is_wp_error( $allowed ) ) {
            return $allowed;
        }

        $expires_at = $this->resolve_expiry( $request );
        if ( \is_wp_error( $expires_at ) ) {
            return $expires_at;
        }

        $unattended = $this->resolve_unattended( $request );
        if ( \is_wp_error( $unattended ) ) {
            return $unattended;
        }

        $result = $this->token_manager->create_token( $name, $user_id, $allowed, $expires_at );
        if ( \is_wp_error( $result ) || empty( $result['id'] ) ) {
            return $this->fail( 'easy_mcp_ai_token_create_failed', \__( "Couldn't create the token. Try again.", 'easy-mcp-ai' ), 500 );
        }
        if ( $unattended ) {
            
            $this->token_manager->update_token( (int) $result['id'], array( 'unattended' => 1 ) );
        }

        $row = $this->token_manager->get_token_by_id( (int) $result['id'] );
        
        return $this->ok( array(
            'token'    => $this->shape( is_array( $row ) ? $row : array( 'id' => $result['id'], 'name' => $name, 'token_prefix' => $result['prefix'], 'wp_user_id' => $user_id, 'allowed_tools' => $allowed, 'expires_at' => $expires_at, 'is_active' => 1 ) ),
            'rawToken' => (string) $result['raw_token'],
        ), 201 );
    }

    



    public function update_token( $request ) {
        $row = $this->find( $request );
        if ( \is_wp_error( $row ) ) {
            return $row;
        }
        $data = array();

        if ( null !== $request->get_param( 'name' ) ) {
            $name = \sanitize_text_field( (string) $request->get_param( 'name' ) );
            if ( '' === trim( $name ) ) {
                return $this->fail( 'easy_mcp_ai_name_required', \__( 'Give the token a name.', 'easy-mcp-ai' ), 400, array( 'field' => 'name' ) );
            }
            $data['name'] = $name;
        }

        if ( null !== $request->get_param( 'userId' ) ) {
            $user_id = \absint( $request->get_param( 'userId' ) );
            if ( ! self::is_assignable_user( $user_id ) ) {
                return $this->invalid_user();
            }
            $data['wp_user_id'] = $user_id;
        }

        if ( null !== $request->get_param( 'preset' ) ) {
            $allowed = $this->resolve_allowed_tools( $request );
            if ( \is_wp_error( $allowed ) ) {
                return $allowed;
            }
            $data['allowed_tools'] = $allowed;
        }

        if ( null !== $request->get_param( 'expiresPreset' ) || null !== $request->get_param( 'expiresAt' ) ) {
            $stored     = empty( $row['expires_at'] ) ? null : gmdate( 'Y-m-d', strtotime( $row['expires_at'] . ' UTC' ) );
            $expires_at = $this->resolve_expiry( $request, $stored );
            if ( \is_wp_error( $expires_at ) ) {
                return $expires_at;
            }
            $data['expires_at'] = $expires_at;
        }

        if ( null !== $request->get_param( 'isActive' ) ) {
            $data['is_active'] = \rest_sanitize_boolean( $request->get_param( 'isActive' ) ) ? 1 : 0;
        }

        if ( null !== $request->get_param( 'unattended' ) ) {
            $unattended = $this->resolve_unattended( $request );
            if ( \is_wp_error( $unattended ) ) {
                return $unattended;
            }
            $data['unattended'] = $unattended;
        }

        if ( ! empty( $data ) ) {
            $this->token_manager->update_token( (int) $row['id'], $data );
        }
        $fresh = $this->token_manager->get_token_by_id( (int) $row['id'] );
        return $this->ok( array( 'token' => $this->shape( is_array( $fresh ) ? $fresh : array_merge( $row, $data ) ) ) );
    }

    



    public function revoke_token( $request ) {
        $row = $this->find( $request );
        if ( \is_wp_error( $row ) ) {
            return $row;
        }
        $this->token_manager->revoke_token( (int) $row['id'] );
        $fresh = $this->token_manager->get_token_by_id( (int) $row['id'] );
        return $this->ok( array( 'token' => $this->shape( is_array( $fresh ) ? $fresh : array_merge( $row, array( 'is_active' => 0 ) ) ) ) );
    }

    








    public function rebind_token( $request ) {
        $row = $this->find( $request );
        if ( \is_wp_error( $row ) ) {
            return $row;
        }
        $result = $this->token_manager->rebind_token( (int) $row['id'] );
        if ( \is_wp_error( $result ) ) {
            return $this->fail( $result->get_error_code(), $result->get_error_message(), 409 );
        }
        if ( false === $result ) {
            return $this->fail(
                'easy_mcp_ai_rebind_failed',
                \__( 'The key could not be re-bound. Please try again.', 'easy-mcp-ai' ),
                500
            );
        }
        $fresh = $this->token_manager->get_token_by_id( (int) $row['id'] );
        return $this->ok( array( 'token' => $this->shape( is_array( $fresh ) ? $fresh : $row ) ) );
    }

    



    public function delete_token( $request ) {
        $row = $this->find( $request );
        if ( \is_wp_error( $row ) ) {
            return $row;
        }
        $this->token_manager->delete_token( (int) $row['id'] );
        return $this->ok( array( 'deleted' => true, 'id' => (int) $row['id'] ) );
    }

    

    





    private function find( $request ) {
        $id  = \absint( $request->get_param( 'id' ) );
        $row = $id ? $this->token_manager->get_token_by_id( $id ) : null;
        if ( ! is_array( $row ) || empty( $row['id'] ) ) {
            return $this->fail( 'easy_mcp_ai_token_not_found', \__( 'Token not found.', 'easy-mcp-ai' ), 404 );
        }
        return $row;
    }

    








    private function resolve_allowed_tools( $request ) {
        $preset = (string) $request->get_param( 'preset' );
        $role   = $request->get_param( 'role' );
        list( $kind, $role ) = Access_Presets::split( $preset, is_string( $role ) ? $role : null );

        if ( Access_Presets::ROLE === $preset && Access_Presets::ROLE !== $kind ) {
            return $this->fail( 'easy_mcp_ai_invalid_role', \__( 'Choose Editor or Author.', 'easy-mcp-ai' ), 400, array( 'field' => 'role' ) );
        }
        if ( Access_Presets::CUSTOM !== $kind ) {
            $list = $this->presets->to_allowed_tools( $kind, $role );
            if ( ! is_array( $list ) || empty( $list ) ) {
                return $this->fail( 'easy_mcp_ai_empty_allowlist', \__( 'This preset matches no tool on this site. Pick another preset or choose tools by hand.', 'easy-mcp-ai' ), 400, array( 'field' => 'preset' ) );
            }
            return $list;
        }
        if ( Access_Presets::CUSTOM !== $preset ) {
            return $this->fail( 'easy_mcp_ai_invalid_preset', \__( 'Choose an access level.', 'easy-mcp-ai' ), 400, array( 'field' => 'preset' ) );
        }

        $custom = $request->get_param( 'customTools' );
        $custom = is_array( $custom ) ? $custom : array();
        $tools  = array();
        foreach ( $custom as $tool ) {
            if ( ! is_string( $tool ) ) {
                continue;
            }
            $tool = \sanitize_text_field( $tool );
            if ( '' !== $tool && ! in_array( $tool, $tools, true ) ) {
                $tools[] = $tool;
            }
        }
        if ( empty( $tools ) ) {
            return $this->fail(
                'easy_mcp_ai_empty_allowlist',
                \__( 'Nothing was saved: no tools were selected. Choose at least one tool, or pick a preset.', 'easy-mcp-ai' ),
                400,
                array( 'field' => 'customTools' )
            );
        }
        return $tools;
    }

    








    private function resolve_expiry( $request, $stored = null ) {
        self::load_admin_page();
        $preset = $request->get_param( 'expiresPreset' );
        $custom = $request->get_param( 'expiresAt' );
        $result = \Easy_MCP_AI\Admin\Admin_Page::resolve_submitted_expiry(
            is_string( $preset ) ? \sanitize_key( $preset ) : '',
            is_string( $custom ) ? \sanitize_text_field( $custom ) : '',
            $stored
        );
        if ( \is_wp_error( $result ) ) {
            return $this->fail( 'easy_mcp_ai_invalid_expiry', $result->get_error_message(), 400, array( 'field' => 'expiresAt' ) );
        }
        return $result;
    }

    
    public static function approval_required() {
        return true === \Easy_MCP_AI\Config::get( 'approval_required' );
    }

    








    private function resolve_unattended( $request ) {
        $raw = $request->get_param( 'unattended' );
        if ( null === $raw ) {
            return 0;
        }
        if ( ! is_bool( $raw ) && ! in_array( $raw, array( 0, 1, '0', '1', 'true', 'false' ), true ) ) {
            return $this->fail( 'easy_mcp_ai_invalid_param', \__( 'Unattended must be on or off.', 'easy-mcp-ai' ), 400, array( 'field' => 'unattended' ) );
        }
        $on = \rest_sanitize_boolean( $raw );
        if ( $on && ! self::approval_required() ) {
            return $this->fail(
                'easy_mcp_ai_approvals_off',
                \sprintf(
                    /* translators: %s: the "Ask before destructive actions" setting label */
                    \__( 'Unattended only applies while "%s" is on in Settings.', 'easy-mcp-ai' ),
                    \__( 'Ask before destructive actions', 'easy-mcp-ai' )
                ),
                400,
                array( 'field' => 'unattended' )
            );
        }
        return $on ? 1 : 0;
    }

    private function invalid_user() {
        return $this->fail(
            'easy_mcp_ai_invalid_user',
            \__( 'Tokens can only be assigned to users who can publish posts (Authors and above).', 'easy-mcp-ai' ),
            400,
            array( 'field' => 'userId' )
        );
    }

    
    public static function min_capability() {
        return (string) \apply_filters( 'easy_mcp_ai_oauth_min_capability', 'publish_posts' );
    }

    



    public static function is_assignable_user( $user_id ) {
        if ( ! $user_id ) {
            return false;
        }
        $user = \get_userdata( $user_id );
        return $user ? (bool) \user_can( $user, self::min_capability() ) : false;
    }

    

    






    public function shape( array $row, array $names = array() ) {
        $allowed = isset( $row['allowed_tools'] ) ? $row['allowed_tools'] : array();
        if ( is_string( $allowed ) ) {
            $allowed = json_decode( $allowed, true );
        }
        $allowed = is_array( $allowed ) ? array_values( array_filter( $allowed, 'is_string' ) ) : array();
        $all     = in_array( '*', $allowed, true );
        $user_id = (int) ( $row['wp_user_id'] ?? 0 );

        return array(
            'id'           => (int) ( $row['id'] ?? 0 ),
            'name'         => (string) ( $row['name'] ?? '' ),
            'prefix'       => (string) ( $row['token_prefix'] ?? '' ),
            'user'         => array(
                'id'          => $user_id,
                'displayName' => isset( $names[ $user_id ] ) ? $names[ $user_id ] : self::display_name( $user_id ),
            ),
            'preset'       => $this->presets->from_allowed_tools( $allowed ),
            'allowedTools' => array(
                'all'   => $all,
                'count' => $all ? null : count( $allowed ),
                'list'  => $allowed,
            ),
            'lastUsedAt'   => Dashboard_Controller::iso8601( $row['last_used_at'] ?? null ),
            'expiresAt'    => Dashboard_Controller::iso8601( $row['expires_at'] ?? null ),
            'isActive'     => ! empty( $row['is_active'] ),
            
            'unattended'   => ! empty( $row['unattended'] ),
            'status'       => self::status( $row ),
            
            
            'boundElsewhere' => \Easy_MCP_AI\Auth\Token_Manager::is_bound_elsewhere( $row ),
            
            
            'selfService'  => ! empty( $row['created_by'] ) && (int) $row['created_by'] === $user_id
                && ! \user_can( $user_id, self::CAPABILITY ),
            'createdAt'    => Dashboard_Controller::iso8601( $row['created_at'] ?? null ),
        );
    }

    







    public static function status( array $row, $now = null ) {
        if ( empty( $row['is_active'] ) ) {
            return 'revoked';
        }
        if ( ! empty( $row['expires_at'] ) ) {
            $ts = strtotime( (string) $row['expires_at'] . ' UTC' );
            if ( false !== $ts && $ts < ( null === $now ? time() : (int) $now ) ) {
                return 'expired';
            }
        }
        return 'active';
    }

    





    public static function assignable_users() {
        $users = (array) \get_users( array(
            'capability' => self::min_capability(),
            'number'     => self::USERS_LIMIT,
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ) );
        $out = array();
        foreach ( $users as $user ) {
            if ( ! is_object( $user ) || empty( $user->ID ) ) {
                continue;
            }
            $out[] = array(
                'id'          => (int) $user->ID,
                'displayName' => (string) ( $user->display_name ?? '' ),
                'roles'       => array_values( array_map( 'strval', (array) ( $user->roles ?? array() ) ) ),
            );
        }
        usort( $out, static function ( $a, $b ) {
            return strcasecmp( $a['displayName'], $b['displayName'] );
        } );
        return array_slice( $out, 0, self::USERS_LIMIT );
    }

    



    private function display_names( array $ids ) {
        $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
        if ( empty( $ids ) ) {
            return array();
        }
        $names = array();
        foreach ( (array) \get_users( array( 'include' => $ids, 'fields' => array( 'ID', 'display_name' ) ) ) as $user ) {
            if ( is_object( $user ) && ! empty( $user->ID ) ) {
                $names[ (int) $user->ID ] = (string) ( $user->display_name ?? '' );
            }
        }
        return $names;
    }

    private static function display_name( $user_id ) {
        $user = $user_id ? \get_userdata( $user_id ) : false;
        return $user && ! empty( $user->display_name ) ? (string) $user->display_name : \__( 'Unknown', 'easy-mcp-ai' );
    }

    private static function load_admin_page() {
        if ( ! class_exists( '\\Easy_MCP_AI\\Admin\\Admin_Page' ) ) {
            require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/admin/class-admin-page.php';
        }
    }
}
