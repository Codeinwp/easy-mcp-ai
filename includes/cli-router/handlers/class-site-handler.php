<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Capability_Grants;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Confirm;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Self_Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









































class Site_Handler extends Command_Handler {

    
    const NOT_MULTISITE = 'This is not a multisite installation.';

    
    const MAIN_SITE = 'You are not allowed to change the main site.';

    
    const IDS_OR_SLUG = 'Please specify one or more IDs of sites, or pass the slug for a single site using --slug.';

    
    const SLUG_MISSING = "Could not find site with slug '%s'.";

    
    const ROW_CAP = 1000;

    
    const CHUNK = 500;

    
    const STATUS = array(
        'activate'   => array( 'deleted', 0 ),
        'archive'    => array( 'archived', 1 ),
        'deactivate' => array( 'deleted', 1 ),
        'mature'     => array( 'mature', 1 ),
        'private'    => array( 'public', 0 ),
        'public'     => array( 'public', 1 ),
        'spam'       => array( 'spam', 1 ),
        'unarchive'  => array( 'archived', 0 ),
        'unmature'   => array( 'mature', 0 ),
        'unspam'     => array( 'spam', 0 ),
    );

    
    const LOCKS_OUT = array( 'archive', 'deactivate', 'spam' );

    
    const SITE_COLS = array( 'blog_id', 'last_updated', 'registered', 'site_id', 'domain', 'path', 'public', 'archived', 'mature', 'spam', 'deleted', 'lang_id' );

    
    const RESERVED_FALLBACK = array( 'page', 'comments', 'blog', 'files', 'feed', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'embed' );

    




    const EMPTY_CAPS_FLOOR = array( 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts', 'delete_pages', 'delete_others_pages', 'delete_published_pages', 'delete_private_pages', 'moderate_comments', 'manage_categories', 'manage_links' );

    
    const POST_DELETE_CAPS = array( 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts' );

    const OTHER_NETWORK      = 'site %1$s belongs to network %2$d, and wp_cli_run acts only on the sites of the network this MCP connection belongs to (network %3$d): a super admin of one network is not one of another.';
    const OTHER_NETWORK_LIST = 'wp_cli_run lists only the sites of the network this MCP connection belongs to (network %1$d); --%2$s=%3$s names another network, whose super admins may differ.';
    const OTHER_NETWORK_NEW  = 'wp_cli_run creates sites only on the network this MCP connection belongs to (network %1$d); --network_id=%2$s names another network, whose super admins may differ.';
    const SITE_IN_NUMBERS    = 'wp_cli_run accepts only site IDs (whole numbers) in --site__in: WP-CLI puts the list into the SQL query unquoted.';
    const UNKNOWN_FILTER     = "'site list' cannot filter by --%s: WP-CLI would ignore it and list every site. Filter by one of: %s.";
    const BLOG_ID_SITE_USER  = "wp_cli_run cannot combine --blog_id with --site_user: WP-CLI stops with a PHP error on it. Use --site__in for a list of IDs.";
    const OVER_ROW_CAP       = "wp_cli_run lists at most %1\$d sites in one 'site list', and %2\$d match. Narrow it with --site__in, --site_user or a --<field>=<value> filter (blog_id, domain, path, public, archived, …), or ask for --format=count.";
    const STATUS_REFUSED     = 'Site %s was not updated: WordPress refused the change.';
    const TRUNCATE_FAILED    = 'site empty stopped at %1$s: %2$d tables were emptied before it and nothing after it was changed, because the database refused TRUNCATE TABLE: %3$s';
    const TABLES_KEPT        = "WordPress kept site %d's tables: it marked the site as deleted and removed its users instead (wpmu_delete_blog() never drops a site's tables while ms_files_rewriting is on and upload_path is empty). WP-CLI prints the same Success line.";

    const NOT_CAPTURED_STATUS = "Site status flags in the network's sites table (wp_blogs): change history does not record them.";
    const NOT_CAPTURED_CREATE = "The new site's tables, its wp_blogs row and its default content: change history does not record them (a user created for it is recorded).";
    
    const CREATED_SETUP_STOPPED = 'Site %1$d was created (%2$s), but its setup was stopped: %3$s The site exists; do not create it again.';
    const NOT_CAPTURED_DELETE = "Dropping a site's tables and its wp_blogs row: change history does not record it, and the site's own audit log and change history are dropped with it.";
    const NOT_CAPTURED_EMPTY  = 'TRUNCATE of the posts, postmeta, comments, commentmeta, terms, term_taxonomy, term_relationships, termmeta and links tables: change history never records it; only the option writes (sticky_posts, wp_page_for_privacy_policy, <taxonomy>_children) can be.';

    
    
    

    




    public function preflight_multisite( Invocation $inv, Context $ctx ): void {
        if ( ! $this->is_multisite() ) {
            $ctx->out->error( self::NOT_MULTISITE );
        }
    }

    




    public function preflight_list( Invocation $inv, Context $ctx ): void {
        $this->preflight_multisite( $inv, $ctx );
        self::refuse_unknown_filters( $inv, $ctx );
        if ( isset( $inv->assoc['site__in'] ) ) {
            foreach ( explode( ',', (string) $inv->assoc['site__in'] ) as $id ) {
                if ( ! preg_match( '/^[ \t]*[0-9]+[ \t]*\z/', $id ) ) {
                    $ctx->out->error( self::SITE_IN_NUMBERS );
                }
            }
        }
        if ( isset( $inv->assoc['blog_id'], $inv->assoc['site_user'] ) && ! isset( $inv->assoc['site__in'] ) ) {
            $ctx->out->error( self::BLOG_ID_SITE_USER );
        }
    }

    





    private static function refuse_unknown_filters( Invocation $inv, Context $ctx ): void {
        $named = array();
        foreach ( (array) $inv->leaf['params'] as $param ) {
            if ( isset( $param['name'] ) && in_array( $param['type'], array( 'assoc', 'flag' ), true ) ) {
                $named[ (string) $param['name'] ] = true;
            }
        }
        foreach ( array_keys( $inv->assoc ) as $key ) {
            $key = (string) $key;
            if ( ! isset( $named[ $key ] ) && ! in_array( $key, self::SITE_COLS, true ) ) {
                $ctx->out->error( sprintf( self::UNKNOWN_FILTER, $key, implode( ', ', self::SITE_COLS ) ) );
            }
        }
    }

    









    public function preflight_delete( Invocation $inv, Context $ctx ): void {
        $this->preflight_multisite( $inv, $ctx );
        $blog = $this->resolve_delete( $inv, $ctx );
        Confirm::ask( $inv, $ctx, "Are you sure you want to delete the '" . $this->trailingslashit( (string) $blog->siteurl ) . "' site?" );
    }

    









    public function preflight_empty( Invocation $inv, Context $ctx ): void {
        foreach ( $this->empty_caps() as $cap ) {
            if ( 'manage_links' === $cap ) {
                
                
                
                
                
                
                if ( ! $this->holds_capability( $cap ) ) {
                    $ctx->out->error( sprintf( 'Sorry, you are not allowed to do this: wp_cli_run needs the %s capability for this command.', $cap ) );
                }
                continue;
            }
            $ctx->require_cap( $cap );
        }
        Confirm::ask( $inv, $ctx, self::empty_question( $this->site_url() ) );
    }

    














    public function empty_caps(): array {
        $caps = self::EMPTY_CAPS_FLOOR;
        foreach ( $this->post_type_objects() as $type ) {
            $cap = isset( $type->cap ) ? (array) $type->cap : array();
            $map = ! empty( $type->map_meta_cap );
            foreach ( $map ? self::POST_DELETE_CAPS : array( 'delete_post' ) as $key ) {
                if ( isset( $cap[ $key ] ) ) {
                    $caps[] = (string) $cap[ $key ];
                }
            }
        }
        foreach ( $this->taxonomy_objects() as $taxonomy ) {
            $cap = isset( $taxonomy->cap ) ? (array) $taxonomy->cap : array();
            if ( isset( $cap['delete_terms'] ) ) {
                $caps[] = (string) $cap['delete_terms'];
            }
        }
        return array_values( array_unique( array_filter( $caps, 'strlen' ) ) );
    }

    
    private static function empty_question( string $site_url ): string {
        return "Are you sure you want to empty the site at '" . $site_url . "' of all posts, links, comments, and terms?";
    }

    
    
    

    





    public function status( Invocation $inv, Context $ctx ): void {
        $sub = (string) $inv->leaf['name'];
        list( $pref, $value ) = self::STATUS[ $sub ];
        $args = array_values( $inv->args );

        $this->check_site_ids_and_slug( $args, $inv->assoc, $ctx );
        $ids = $this->get_sites_ids( $args, $inv->assoc, $ctx );

        
        $network = $this->current_network_id();
        foreach ( $ids as $site_id ) {
            $site = $this->site_row( $site_id );
            if ( ! $site ) {
                continue; 
            }
            $this->guard_network( $site, $network, $ctx );
            if ( in_array( $sub, self::LOCKS_OUT, true ) && ! $this->is_main_site( (int) $site->blog_id ) ) {
                $refusal = Self_Protection::current_site( (int) $site->blog_id );
                if ( null !== $refusal ) {
                    $ctx->out->error( $refusal );
                }
            }
        }

        $ctx->not_captured( self::NOT_CAPTURED_STATUS );
        $value  = (int) $value;
        $action = self::action( $pref, $value );
        foreach ( $ids as $site_id ) {
            $site = $this->site_check( $site_id, $ctx );

            if ( $this->is_main_site( (int) $site->blog_id ) ) {
                $ctx->out->warning( self::MAIN_SITE );
                continue;
            }

            $old_value = (int) $this->get_blog_status( (int) $site->blog_id, $pref );

            if ( $value === $old_value ) {
                $ctx->out->warning( "Site {$site->blog_id} already {$action}." );
                continue;
            }

            if ( false === $this->update_blog_status( (int) $site->blog_id, $pref, $value ) ) {
                $ctx->out->warning( sprintf( self::STATUS_REFUSED, $site->blog_id ) );
                continue;
            }
            $ctx->mark_changed();
            $ctx->out->success( "Site {$site->blog_id} {$action}." );
        }
    }

    




    public function describe_status( Invocation $inv ): array {
        $sub = (string) $inv->leaf['name'];
        list( $pref, $value ) = self::STATUS[ $sub ];
        $value  = (int) $value;
        $action = self::action( $pref, $value );
        $args   = array_values( $inv->args );
        $slug   = Read_Value::flag( $inv->assoc, 'slug', false );
        $ids    = $args;
        if ( $slug && ! $args ) {
            $id  = $this->get_id_from_blogname( trim( (string) $slug, '/' ) );
            $ids = null === $id ? array() : array( $id );
            if ( null === $id ) {
                return array( sprintf( "Slug '%s': no such site → nothing changes", $slug ) );
            }
        }
        if ( ! $ids ) {
            return array( 'No site named → nothing changes' );
        }
        $lines = array();
        foreach ( $ids as $site_id ) {
            $site = $this->site_row( $site_id );
            if ( ! $site ) {
                $lines[] = sprintf( 'Site %s: not found → the command stops here', $site_id );
                continue;
            }
            $label = sprintf( 'Site %s (%s%s)', $site->blog_id, $site->domain, $site->path );
            if ( $this->is_main_site( (int) $site->blog_id ) ) {
                $lines[] = $label . ': the main site → not changed';
                continue;
            }
            $old = (int) $this->get_blog_status( (int) $site->blog_id, $pref );
            $lines[] = $old === $value
                ? sprintf( '%s: already %s → no change', $label, $action )
                : sprintf( '%s: %s %d → %d (%s)', $label, $pref, $old, $value, $action );
        }
        return $lines;
    }

    
    
    

    






    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;

        if ( isset( $assoc_args['fields'] ) ) {
            $assoc_args['fields'] = preg_split( '/,[ \t]*/', (string) $assoc_args['fields'] );
        }

        $defaults   = array(
            'format' => 'table',
            'fields' => array( 'blog_id', 'url', 'last_updated', 'registered' ),
        );
        $assoc_args = array_merge( $defaults, $assoc_args );

        $filter = self::list_filter( $assoc_args );
        $where  = $filter['where'];
        $append = $filter['append'];

        
        $this->scope_to_network( $where, $assoc_args, $ctx );

        if ( isset( $assoc_args['site_user'] ) ) {
            $append = $this->scope_to_user( $where, $assoc_args, $ctx );
            if ( null === $append ) {
                $formatter = new Formatter( $ctx->out, $assoc_args, array(), 'site' );
                $formatter->display_items( array() );
                return;
            }
        }

        
        if ( isset( $where['blog_id'] ) && is_array( $where['blog_id'] ) ) {
            $where['blog_id'] = array_map( 'intval', $where['blog_id'] );
        }

        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
        if ( 'count' !== $format ) {
            $total = $this->count_blogs( $where );
            if ( $total > self::ROW_CAP ) {
                $ctx->out->error( sprintf( self::OVER_ROW_CAP, self::ROW_CAP, $total ) );
            }
        }

        $this->display_sites( $this->rows_with_url( $where, $append, (string) $format ), $assoc_args, $ctx );
    }

    







    private static function list_filter( array $assoc_args ): array {
        $where  = array();
        $append = '';

        foreach ( self::SITE_COLS as $col ) {
            if ( isset( $assoc_args[ $col ] ) ) {
                $where[ $col ] = $assoc_args[ $col ];
            }
        }

        if ( isset( $assoc_args['site__in'] ) ) {
            $where['blog_id'] = explode( ',', (string) $assoc_args['site__in'] );
            $append           = 'ORDER BY FIELD( blog_id, ' . implode( ',', array_map( 'intval', $where['blog_id'] ) ) . ' )';
        }

        if ( isset( $assoc_args['network'] ) ) {
            $where['site_id'] = $assoc_args['network'];
        }

        return array(
            'where'  => $where,
            'append' => $append,
        );
    }

    








    private function scope_to_network( array &$where, array $assoc_args, Context $ctx ): void {
        $network = $this->current_network_id();
        if ( isset( $where['site_id'] ) ) {
            if ( (int) $where['site_id'] !== $network ) {
                $flag = isset( $assoc_args['network'] ) ? 'network' : 'site_id';
                $ctx->out->error( sprintf( self::OTHER_NETWORK_LIST, $network, $flag, is_scalar( $where['site_id'] ) ? (string) $where['site_id'] : '' ) );
            }
        } else {
            $where['site_id'] = (string) $network;
        }
    }

    








    private function scope_to_user( array &$where, array $assoc_args, Context $ctx ): ?string {
        $user = Fetchers::of( 'user' )->get_check( $assoc_args['site_user'], $ctx->out );

        if ( $user ) {
            $blogs = $this->get_blogs_of_user( (int) $user->ID );

            foreach ( $blogs as $blog ) {
                $where['blog_id'][] = $blog->userblog_id;
            }
        }

        if ( ! isset( $where['blog_id'] ) || empty( $where['blog_id'] ) ) {
            return null;
        }

        return 'ORDER BY FIELD( blog_id, ' . implode( ',', array_map( 'intval', $where['blog_id'] ) ) . ' )';
    }

    






    private function rows_with_url( array $where, string $append, string $format ): \Generator {
        foreach ( $this->blog_rows( $where, $append ) as $blog ) {
            if ( ! in_array( $format, array( 'count', 'ids' ), true ) ) {
                $blog->url = $this->trailingslashit( $this->get_home_url( (int) $blog->blog_id ) );
            }
            yield $blog;
        }
    }

    





    private function display_sites( \Generator $iterator, array $assoc_args, Context $ctx ): void {
        if ( ! empty( $assoc_args['format'] ) && 'ids' === $assoc_args['format'] ) {
            $sites     = iterator_to_array( $iterator, false );
            $ids       = array();
            foreach ( $sites as $site ) {
                $ids[] = $site->blog_id;
            }
            $formatter = new Formatter( $ctx->out, $assoc_args, null, 'site' );
            $formatter->display_items( $ids );
        } else {
            $formatter = new Formatter( $ctx->out, $assoc_args, null, 'site' );
            $formatter->display_items( $iterator );
        }
    }

    
    
    

    





    public function create( Invocation $inv, Context $ctx ): void {
        $plan = $this->plan_create( $inv, $ctx, true );

        $user_id = $plan['user_id'];
        if ( ! $user_id ) { 
            $password = $this->wp_generate_password( 24, false );
            $user_id  = $this->wpmu_create_user( $plan['base'], $password, $plan['email'] );
            if ( false === $user_id ) {
                $ctx->out->error( "Can't create user." );
            } else {
                $ctx->mark_changed();
                $this->wp_new_user_notification( (int) $user_id );
            }
        }

        $ctx->not_captured( self::NOT_CAPTURED_CREATE );
        try {
            $id = $this->wpmu_create_blog( $plan['domain'], $plan['path'], $this->wp_slash( $plan['title'] ), (int) $user_id, array( 'public' => $plan['public'] ), (int) $plan['network_id'] );
        } catch ( \Throwable $e ) {
            $this->stop_after_failed_setup( $plan, $e, $ctx );
            return; 
        }
        if ( ! $this->is_wp_error( $id ) ) {
            $ctx->mark_changed();
            if ( ! $this->is_super_admin( (int) $user_id ) && ! $this->get_user_option( 'primary_blog', (int) $user_id ) ) {
                $this->update_user_option( (int) $user_id, 'primary_blog', $id, true );
            }
        } else {
            $ctx->out->error( $id->get_error_message() );
        }

        if ( Read_Value::flag( $inv->assoc, 'porcelain' ) ) {
            $ctx->out->line( (string) $id );
        } else {
            $site_url = $this->trailingslashit( $this->get_site_url( (int) $id ) );
            $ctx->out->success( "Site {$id} created: {$site_url}" );
        }
    }

    









    private function stop_after_failed_setup( array $plan, \Throwable $e, Context $ctx ): void {
        $id = $this->created_site_id( $plan['domain'], $plan['path'], (int) $plan['network_id'] );
        if ( null === $id ) {
            throw $e;
        }
        $ctx->mark_changed();
        
        
        try {
            $url = $this->trailingslashit( $this->get_site_url( $id ) );
        } catch ( \Throwable $lookup ) {
            $url = $plan['domain'] . $plan['path'];
        }
        $ctx->out->error( sprintf( self::CREATED_SETUP_STOPPED, $id, $url, $e->getMessage() ) );
    }

    




    public function describe_create( Invocation $inv ): array {
        $ctx  = self::silent_context();
        $plan = $this->plan_create( $inv, $ctx, false );
        if ( null === $plan ) {
            $errors = $ctx->out->errors();
            return array( 'New site: refused → ' . ( $errors ? (string) $errors[0] : 'nothing changes' ) );
        }
        $taken = $this->domain_exists( $plan['domain'], $plan['path'], (int) $plan['network_id'] );
        $admin = $plan['user_id']
            ? sprintf( 'existing user %d (%s)', $plan['user_id'], '' !== $plan['email'] ? $plan['email'] : 'no email' )
            : sprintf( "new user '%s' (%s), created with a random password and emailed", $plan['base'], '' !== $plan['email'] ? $plan['email'] : 'no email' );
        return array(
            sprintf(
                "New site %s%s on network %d: %s → %s, title '%s', admin %s",
                $plan['domain'],
                $plan['path'],
                (int) $plan['network_id'],
                $taken ? 'that address is taken' : 'that address is free',
                $taken ? 'the command fails' : ( $plan['public'] ? 'create it, public' : 'create it, private (not indexed)' ),
                $plan['title'],
                $admin
            ),
        );
    }

    





    private function plan_create( Invocation $inv, Context $ctx, bool $strict ): ?array {
        try {
            $assoc_args   = $inv->assoc;
            $current_site = $this->current_network();
            $current_id   = $this->current_network_id();

            $base  = (string) $assoc_args['slug'];
            $title = (string) Read_Value::flag( $assoc_args, 'title', ucfirst( $base ) );

            $email = empty( $assoc_args['email'] ) ? '' : (string) $assoc_args['email'];

            
            $network = $this->plan_network( $assoc_args, $current_site, $current_id, $ctx );

            $public = ! Read_Value::flag( $assoc_args, 'private' );

            
            if ( preg_match( '|^([a-zA-Z0-9-])+$|', $base ) ) {
                $base = strtolower( $base );
            }

            
            $this->refuse_reserved_name( $base, $ctx );

            
            $email = $this->plan_email( $email );

            list( $newdomain, $path ) = $this->plan_address( $base, $current_site );

            return array(
                'base'       => $base,
                'title'      => $title,
                'email'      => $email,
                'network_id' => (int) $network->id,
                'public'     => (bool) $public,
                'domain'     => $newdomain,
                'path'       => $path,
                'user_id'    => (int) $this->email_exists( $email ),
            );
        } catch ( \Easy_MCP_AI\Cli_Router\Cli_Exit $e ) {
            if ( $strict ) {
                throw $e;
            }
            return null;
        }
    }

    








    private function plan_network( array $assoc_args, ?object $current_site, int $current_id, Context $ctx ): ?object {
        if ( empty( $assoc_args['network_id'] ) ) {
            return $current_site;
        }
        $network = $this->get_network_row( $assoc_args['network_id'] );
        if ( false === $network ) {
            $ctx->out->error( "Network with id {$assoc_args['network_id']} does not exist." );
        }
        if ( (int) $network->id !== $current_id ) {
            $ctx->out->error( sprintf( self::OTHER_NETWORK_NEW, $current_id, (string) $assoc_args['network_id'] ) );
        }
        return $network;
    }

    





    private function refuse_reserved_name( string $base, Context $ctx ): void {
        if ( $this->is_subdomain_install() ) {
            return;
        }
        $subdirectory_reserved_names = $this->get_subdirectory_reserved_names();
        if ( in_array( $base, $subdirectory_reserved_names, true ) ) {
            $ctx->out->error( 'The following words are reserved and cannot be used as blog names: ' . implode( ', ', $subdirectory_reserved_names ) );
        }
    }

    



    private function plan_email( string $email ): string {
        $email = $this->sanitize_email( $email );
        if ( ! empty( $email ) && $this->is_email( $email ) ) {
            return $email;
        }
        $super_admins = $this->get_super_admins();
        $email        = '';
        if ( ! empty( $super_admins ) && is_array( $super_admins ) ) {
            
            $super_login = reset( $super_admins );
            $super_user  = $this->get_user_by( 'login', (string) $super_login );
            if ( $super_user ) {
                $email = (string) $super_user->user_email;
            }
        }
        return $email;
    }

    






    private function plan_address( string $base, ?object $current_site ): array {
        if ( $this->is_subdomain_install() ) {
            return array( $base . '.' . preg_replace( '|^www\.|', '', (string) $current_site->domain ), (string) $current_site->path );
        }
        return array( (string) $current_site->domain, $current_site->path . $base . '/' );
    }

    
    
    

    




    public function delete( Invocation $inv, Context $ctx ): void {
        $blog = $this->resolve_delete( $inv, $ctx );

        $site_url = $this->trailingslashit( (string) $blog->siteurl );

        Confirm::ask( $inv, $ctx, "Are you sure you want to delete the '{$site_url}' site?" );

        $ctx->not_captured( self::NOT_CAPTURED_DELETE );
        $drop = ! Read_Value::flag( $inv->assoc, 'keep-tables' );
        $this->wpmu_delete_blog( (int) $blog->blog_id, $drop );
        $ctx->mark_changed();
        if ( $drop && $this->get_site( (int) $blog->blog_id ) ) {
            $ctx->note( sprintf( self::TABLES_KEPT, (int) $blog->blog_id ) );
        }

        $ctx->out->success( "The site at '{$site_url}' was deleted." );
    }

    




    public function describe_delete( Invocation $inv ): array {
        $ctx = self::silent_context();
        try {
            $blog = $this->resolve_delete( $inv, $ctx );
        } catch ( \Easy_MCP_AI\Cli_Router\Cli_Exit $e ) {
            $errors = $ctx->out->errors();
            return array( 'Site delete: refused → ' . ( $errors ? (string) $errors[0] : 'nothing changes' ) );
        }
        $effect = Read_Value::flag( $inv->assoc, 'keep-tables' )
            ? 'mark it as deleted and remove every user from it, keeping its tables'
            : 'delete it, drop its tables and delete its uploads directory';
        return array( sprintf( "Site %d '%s' (network %d): exists → %s", (int) $blog->blog_id, $this->trailingslashit( (string) $blog->siteurl ), (int) $blog->site_id, $effect ) );
    }

    






    private function resolve_delete( Invocation $inv, Context $ctx ): object {
        $assoc_args = $inv->assoc;
        $args       = array_values( $inv->args );

        if ( isset( $assoc_args['slug'] ) ) {
            $blog_id = $this->get_id_from_blogname( (string) $assoc_args['slug'] );
            if ( null === $blog_id ) {
                $ctx->out->error( sprintf( self::SLUG_MISSING, $assoc_args['slug'] ) );
            }
            
            if ( $this->is_main_site( (int) $blog_id ) ) {
                $ctx->out->error( 'You cannot delete the root site.' );
            }
            $blog = $this->get_blog_details( $blog_id );
        } else {
            if ( empty( $args ) ) {
                $ctx->out->error( 'Need to specify a blog id.' );
            }

            $blog_id = $args[0];

            if ( $this->is_main_site( (int) $blog_id ) ) {
                $ctx->out->error( 'You cannot delete the root site.' );
            }

            $blog = $this->get_blog_details( $blog_id );
        }

        if ( ! $blog ) {
            $ctx->out->error( 'Site not found.' );
        }

        $this->guard_network( $blog, $this->current_network_id(), $ctx );
        $refusal = Self_Protection::current_site( (int) $blog->blog_id );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
        
        $ctx->require_cap( 'delete_site', (int) $blog->blog_id );
        return $blog;
    }

    
    
    

    





    public function empty_( Invocation $inv, Context $ctx ): void {
        Confirm::ask( $inv, $ctx, self::empty_question( $this->site_url() ) );

        $ctx->not_captured( self::NOT_CAPTURED_EMPTY );
        
        
        
        
        $emptied = 0;
        foreach ( array( 'empty_posts', 'empty_links', 'empty_comments', 'empty_taxonomies' ) as $step ) {
            foreach ( $this->{$step}() as $table ) {
                $refused = $this->truncate_table( (string) $table );
                if ( null !== $refused ) {
                    $ctx->out->error( sprintf( self::TRUNCATE_FAILED, (string) $table, $emptied, $refused ) );
                }
                ++$emptied;
                $ctx->mark_changed();
            }
        }
        $this->insert_default_terms();
        $this->reset_options();

        $ctx->out->success( "The site at '" . $this->site_url() . "' was emptied." );
    }

    




    public function describe_empty( Invocation $inv ): array {
        $counts = $this->content_counts();
        return array(
            sprintf(
                "Site '%s': %d posts (all types), %d comments, %d terms, %d links → empty every content table (TRUNCATE) and re-create 'Uncategorized'",
                $this->site_url(),
                $counts['posts'],
                $counts['comments'],
                $counts['terms'],
                $counts['links']
            ),
        );
    }

    
    
    

    






    private function check_site_ids_and_slug( array $args, array $assoc_args, Context $ctx ): void {
        if ( ( empty( $args ) && empty( $assoc_args['slug'] ) )
            || ( ! empty( $args ) && ! empty( $assoc_args['slug'] ) ) ) {
            $ctx->out->error( self::IDS_OR_SLUG );
        }
    }

    







    private function get_sites_ids( array $args, array $assoc_args, Context $ctx ): array {
        $slug = Read_Value::flag( $assoc_args, 'slug', false );

        if ( $slug ) {
            $blog_id = $this->get_id_from_blogname( trim( (string) $slug, '/' ) );
            if ( null === $blog_id ) {
                $ctx->out->error( sprintf( self::SLUG_MISSING, $slug ) );
            }
            return array( $blog_id );
        }

        return $args;
    }

    





    private function guard_network( object $site, int $network, Context $ctx ): void {
        if ( isset( $site->site_id ) && (int) $site->site_id !== $network ) {
            $ctx->out->error( sprintf( self::OTHER_NETWORK, (string) $site->blog_id, (int) $site->site_id, $network ) );
        }
    }

    
    private static function action( string $pref, int $value ): string {
        switch ( $pref ) {
            case 'archived':
                return $value ? 'archived' : 'unarchived';
            case 'deleted':
                return $value ? 'deactivated' : 'activated';
            case 'mature':
                return $value ? 'marked as mature' : 'marked as unmature';
            case 'public':
                return $value ? 'marked as public' : 'marked as private';
            default:
                return $value ? 'marked as spam' : 'removed from spam';
        }
    }

    
    private static function silent_context(): Context {
        return new Context(
            new \Easy_MCP_AI\Cli_Router\Output(),
            static function () {
                return null;
            },
            static function () {
                return false;
            }
        );
    }

    






    protected function site_check( $site_id, Context $ctx ): object {
        $site = $this->site_row( $site_id );
        if ( ! $site ) {
            $ctx->out->error( sprintf( Fetchers::MESSAGES['site'], $site_id ) );
        }
        return $site;
    }

    
    
    

    




    protected function empty_posts(): array {
        global $wpdb;
        $taxonomies = function_exists( 'get_taxonomies' ) ? \get_taxonomies() : array();
        $offset     = 0;
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WP-CLI's QueryIterator walk over every post ID (chunks of 10000).
            $ids = (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} LIMIT 10000 OFFSET {$offset}" );
            foreach ( $ids as $post_id ) {
                \wp_cache_delete( $post_id, 'posts' );
                \wp_cache_delete( $post_id, 'post_meta' );
                foreach ( $taxonomies as $taxonomy ) {
                    \wp_cache_delete( $post_id, "{$taxonomy}_relationships" );
                }
                \wp_cache_delete( $wpdb->blogid . '-' . $post_id, 'global-posts' );
            }
            $offset += 10000;
        } while ( count( $ids ) === 10000 );
        return array( (string) $wpdb->posts, (string) $wpdb->postmeta );
    }

    




    protected function empty_links(): array {
        global $wpdb;
        \wp_cache_delete( 'get_bookmarks', 'bookmark' );
        $offset = 0;
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WP-CLI's QueryIterator walk over every link ID.
            $ids = (array) $wpdb->get_col( "SELECT link_id FROM {$wpdb->links} LIMIT 10000 OFFSET {$offset}" );
            foreach ( $ids as $link_id ) {
                \wp_delete_object_term_relationships( $link_id, 'link_category' );
                \wp_cache_delete( $link_id, 'bookmark' );
                \clean_object_term_cache( $link_id, 'link' );
            }
            $offset += 10000;
        } while ( count( $ids ) === 10000 );
        return array( (string) $wpdb->links );
    }

    




    protected function empty_comments(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WP-CLI's site empty.
        $comment_ids = (array) $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments}" );
        foreach ( $comment_ids as $comment_id ) {
            \wp_cache_delete( $comment_id, 'comment' );
            \wp_cache_delete( $comment_id, 'comment_meta' );
        }
        return array( (string) $wpdb->comments, (string) $wpdb->commentmeta );
    }

    





    protected function empty_taxonomies(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WP-CLI's site empty.
        $terms      = $wpdb->get_results( "SELECT term_id, taxonomy FROM {$wpdb->term_taxonomy}" );
        $taxonomies = array();
        foreach ( (array) $terms as $term ) {
            $taxonomies[] = $term->taxonomy;
            \wp_cache_delete( $term->term_id, $term->taxonomy );
        }

        $taxonomies = array_unique( $taxonomies );
        $cleaned    = array();
        foreach ( $taxonomies as $taxonomy ) {
            if ( isset( $cleaned[ $taxonomy ] ) ) {
                continue;
            }
            $cleaned[ $taxonomy ] = true;

            \wp_cache_delete( 'all_ids', $taxonomy );
            \wp_cache_delete( 'get', $taxonomy );
            \delete_option( "{$taxonomy}_children" );
        }
        $tables = array( (string) $wpdb->terms, (string) $wpdb->term_taxonomy, (string) $wpdb->term_relationships );
        if ( ! empty( $wpdb->termmeta ) ) {
            $tables[] = (string) $wpdb->termmeta;
        }
        return $tables;
    }

    




    protected function truncate_table( string $table ): ?string {
        global $wpdb;
        $result = $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WP-CLI's site empty; the table name is one of WordPress's own ($wpdb's table properties).
        if ( false !== $result ) {
            return null;
        }
        $reason = isset( $wpdb->last_error ) ? trim( (string) $wpdb->last_error ) : '';
        return '' !== $reason ? $reason : 'the database gave no reason';
    }

    




    protected function insert_default_terms(): void {
        global $wpdb;

        
        $cat_name = \__( 'Uncategorized' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's own string, as WP-CLI translates it.

        /* translators: Default category slug */
        $cat_slug = \sanitize_title( \_x( 'Uncategorized', 'Default category slug' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's own string.

        $global_terms = function_exists( 'global_terms_enabled' ) && version_compare( (string) $GLOBALS['wp_version'], '6.1', '<' ) && \global_terms_enabled(); // phpcs:ignore WordPress.WP.DeprecatedFunctions.global_terms_enabledFound -- WordPress 6.0 only.
        if ( $global_terms ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's site empty (global terms, WordPress 6.0).
            $cat_id = $wpdb->get_var( $wpdb->prepare( "SELECT cat_ID FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
            if ( null === $cat_id ) {
                $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- WP-CLI's site empty.
                    $wpdb->sitecategories,
                    array(
                        'cat_ID'            => 0,
                        'cat_name'          => $cat_name,
                        'category_nicename' => $cat_slug,
                        'last_updated'      => \current_time( 'mysql', true ),
                    )
                );
                $cat_id = $wpdb->insert_id;
            }
            \update_option( 'default_category', $cat_id );
        } else {
            $cat_id = 1;
        }

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- WP-CLI's site empty.
            $wpdb->terms,
            array(
                'term_id'    => $cat_id,
                'name'       => $cat_name,
                'slug'       => $cat_slug,
                'term_group' => 0,
            )
        );
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- WP-CLI's site empty.
            $wpdb->term_taxonomy,
            array(
                'term_id'     => $cat_id,
                'taxonomy'    => 'category',
                'description' => '',
                'parent'      => 0,
                'count'       => 0,
            )
        );
    }

    
    protected function reset_options(): void {
        
        \update_option( 'wp_page_for_privacy_policy', 0 );

        
        \update_option( 'sticky_posts', array() );
    }

    




    protected function content_counts(): array {
        global $wpdb;
        $counts = array();
        foreach ( array( 'posts' => 'posts', 'comments' => 'comments', 'terms' => 'term_taxonomy', 'links' => 'links' ) as $key => $table ) {
            $name = $wpdb->$table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a WordPress table name.
            $counts[ $key ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$name}" );
        }
        return $counts;
    }

    
    protected function holds_capability( string $cap ): bool {
        return Capability_Grants::holds( $cap );
    }

    
    protected function post_type_objects(): array {
        return function_exists( 'get_post_types' ) ? (array) \get_post_types( array(), 'objects' ) : array();
    }

    
    protected function taxonomy_objects(): array {
        return function_exists( 'get_taxonomies' ) ? (array) \get_taxonomies( array(), 'objects' ) : array();
    }

    
    
    

    protected function is_multisite(): bool {
        return function_exists( 'is_multisite' ) && \is_multisite();
    }

    protected function current_network_id(): int {
        return function_exists( 'get_current_network_id' ) ? (int) \get_current_network_id() : 1;
    }

    




    protected function current_network(): ?object {
        global $current_site;
        if ( is_object( $current_site ) ) {
            return $current_site;
        }
        return \get_network();
    }

    protected function is_main_site( int $site_id ): bool {
        return \is_main_site( $site_id );
    }

    





    protected function site_row( $site_id ) {
        return Fetchers::of( 'site' )->get( $site_id );
    }

    
    protected function get_id_from_blogname( string $slug ): ?int {
        return \get_id_from_blogname( $slug );
    }

    



    protected function get_blog_details( $blog_id ) {
        return \get_blog_details( $blog_id );
    }

    
    protected function get_blog_status( int $blog_id, string $pref ) {
        return \get_blog_status( $blog_id, $pref );
    }

    
    protected function update_blog_status( int $blog_id, string $pref, int $value ) {
        return \update_blog_status( $blog_id, $pref, $value );
    }

    
    protected function wpmu_delete_blog( int $blog_id, bool $drop ): void {
        if ( ! function_exists( 'wpmu_delete_blog' ) ) {
            require_once ABSPATH . 'wp-admin/includes/ms.php';
        }
        \wpmu_delete_blog( $blog_id, $drop );
    }

    
    protected function get_site( int $blog_id ): ?object {
        return \get_site( $blog_id );
    }

    protected function get_home_url( int $blog_id ): string {
        return (string) \get_home_url( $blog_id );
    }

    protected function trailingslashit( string $value ): string {
        return \trailingslashit( $value );
    }

    protected function site_url(): string {
        return (string) \site_url();
    }

    protected function get_site_url( int $blog_id ): string {
        return (string) \get_site_url( $blog_id );
    }

    
    protected function get_blogs_of_user( int $user_id ): array {
        return array_values( (array) \get_blogs_of_user( $user_id ) );
    }

    







    protected function blog_rows( array $where, string $append ): \Generator {
        global $wpdb;
        $conditions = self::build_where_conditions( $where );
        $where_sql  = $conditions ? " WHERE $conditions" : '';
        $query      = "SELECT * FROM `{$wpdb->blogs}` $where_sql {$append}";
        $offset     = 0;
        while ( true ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- every value is prepared in build_where_conditions(); blog IDs are integers.
            $rows = $wpdb->get_results( $query . sprintf( ' LIMIT %d OFFSET %d', self::CHUNK, $offset ) );
            if ( ! $rows ) {
                return;
            }
            foreach ( $rows as $row ) {
                yield $row;
            }
            $offset += self::CHUNK;
        }
    }

    




    protected function count_blogs( array $where ): int {
        global $wpdb;
        $conditions = self::build_where_conditions( $where );
        $where_sql  = $conditions ? " WHERE $conditions" : '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as blog_rows().
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->blogs}` $where_sql" );
    }

    





    public static function build_where_conditions( array $where ): string {
        global $wpdb;
        $conditions = array();
        foreach ( $where as $key => $value ) {
            if ( is_array( $value ) ) {
                $conditions[] = $key . ' IN (' . implode( ',', array_map( 'intval', $value ) ) . ')';
            } elseif ( is_numeric( $key ) ) {
                continue;
            } else {
                $conditions[] = $key . $wpdb->prepare( ' = %s', $value );
            }
        }
        return implode( ' AND ', $conditions );
    }

    





    protected function get_network_row( $network_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's own query.
        $networks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->site WHERE id = %d", $network_id ) );
        if ( ! empty( $networks ) ) {
            return $networks[0];
        }
        return false;
    }

    protected function is_subdomain_install(): bool {
        return (bool) \is_subdomain_install();
    }

    
    protected function get_subdirectory_reserved_names(): array {
        if ( function_exists( 'get_subdirectory_reserved_names' ) ) {
            return array_values( (array) \get_subdirectory_reserved_names() );
        }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, as WP-CLI applies it.
        return array_values( (array) \apply_filters( 'subdirectory_reserved_names', self::RESERVED_FALLBACK ) );
    }

    protected function sanitize_email( string $email ): string {
        return (string) \sanitize_email( $email );
    }

    
    protected function is_email( string $email ) {
        return \is_email( $email );
    }

    
    protected function get_super_admins(): array {
        return (array) \get_super_admins();
    }

    
    protected function get_user_by( string $field, string $value ) {
        return \get_user_by( $field, $value );
    }

    
    protected function email_exists( string $email ) {
        return \email_exists( $email );
    }

    protected function wp_generate_password( int $length, bool $special_chars ): string {
        return (string) \wp_generate_password( $length, $special_chars );
    }

    
    protected function wpmu_create_user( string $user_name, string $password, string $email ) {
        return \wpmu_create_user( $user_name, $password, $email );
    }

    
    protected function wp_new_user_notification( int $user_id ): void {
        \wp_new_user_notification( $user_id, null, 'both' );
    }

    








    protected function wpmu_create_blog( string $domain, string $path, string $title, int $user_id, array $options, int $network_id ) {
        global $wpdb;
        $shown = $wpdb->hide_errors();
        try {
            return \wpmu_create_blog( $domain, $path, $title, $user_id, $options, $network_id );
        } finally {
            $wpdb->show_errors( (bool) $shown );
        }
    }

    protected function domain_exists( string $domain, string $path, int $network_id ): bool {
        return null !== $this->created_site_id( $domain, $path, $network_id );
    }

    
    protected function created_site_id( string $domain, string $path, int $network_id ): ?int {
        $id = \domain_exists( $domain, $path, $network_id );
        return $id ? (int) $id : null;
    }

    
    protected function is_wp_error( $value ): bool {
        return \is_wp_error( $value );
    }

    protected function is_super_admin( int $user_id ): bool {
        return \is_super_admin( $user_id );
    }

    
    protected function get_user_option( string $option, int $user_id ) {
        return \get_user_option( $option, $user_id );
    }

    
    protected function update_user_option( int $user_id, string $option, int $value, bool $is_global ): void {
        \update_user_option( $user_id, $option, $value, $is_global );
    }

    
    protected function wp_slash( string $value ): string {
        return \wp_slash( $value );
    }
}
