<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Menu_Adapter extends Command_Handler {

    const ROW_CAP = 1000;

    const PER_PAGE = 100;

    const MENU_FIELDS = array( 'term_id', 'name', 'slug', 'locations', 'count' );

    const ITEM_FIELDS = array( 'db_id', 'type', 'title', 'link', 'position' );

    const TARGETS = array( '', '_blank', '_self' );

    const ROW_CAP_MESSAGE    = "'wp %s' would print %d rows, and wp_cli_run returns at most %d rows in one command. WP-CLI's %s has no paging parameters; --format=count still counts every row.";
    const STRING_FLAG        = "--%s needs a value (--%s=<%s>) when wp_cli_run runs 'wp %s'.";
    const BAD_NUMBER         = "wp_cli_run refuses --%s=%s for 'wp %s': it must be a whole number of 0 or more. WP-CLI would store a cast of it.";
    const BAD_TARGET         = "wp_cli_run refuses --target=%s for 'wp %s': %s accepts only _blank (a new tab), _self or an empty target (the same tab). WP-CLI would store any word as the link target.";
    const BAD_DB_ID          = "Couldn't delete menu item %s.";
    const FOLDED_MENU        = "wp_cli_run refuses the menu '%s': it matches the menu '%s' (ID %d) only through the database's accent- and case-insensitive comparison, so the menu this command changes may not be the one meant. Pass the menu's ID, or its name or slug exactly as `menu list` prints it.";
    const REFUSED_ITEM_NOTE  = '%s refused menu item %s: %s';
    const REFUSED_MENU_NOTE  = "%s refused the menu '%s': %s";
    const OVER_CAP           = "wp_cli_run runs at most %d items in one 'wp %s' command, and this one names %d. Run it in smaller groups.";

    private static $reader = null;

    public function create( Invocation $inv, Context $ctx ): void {
        $result = $ctx->delegate( 'wp_create_menu', array( 'name' => (string) $inv->args[0] ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $ctx->mark_changed();
        $menu_id = isset( $result['payload']['id'] ) ? (int) $result['payload']['id'] : 0;
        if ( Read_Value::flag( $inv->assoc, 'porcelain' ) ) {
            $ctx->out->line( (string) $menu_id );
        } else {
            $ctx->out->success( "Created menu {$menu_id}." );
        }
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, 'wp_delete_menu' );
        $batch = new Batch( $inv, $ctx, 'menu', 'delete' );
        $batch->run(
            array_values( $inv->args ),
            static function ( $arg, Batch $batch ) use ( $ctx ) {
                $arg     = (string) $arg;
                $refusal = null;
                $menu    = self::resolve_menu( $arg, true, $refusal );
                if ( null === $menu ) {
                    $ctx->out->warning( "Couldn't delete menu '{$arg}'." );
                    if ( null !== $refusal ) {
                        $ctx->out->warning( $refusal );
                    }
                    return Batch::FAILURE;
                }
                $result = $batch->delegate( 'wp_delete_menu', array( 'menu_id' => (int) $menu['term_id'] ) );
                if ( Batch::is_rate_limited( $result ) ) {
                    return Batch::FAILURE;
                }
                if ( ! $result['ok'] ) {
                    $ctx->out->warning( "Couldn't delete menu '{$arg}'." );
                    $ctx->note( sprintf( self::REFUSED_MENU_NOTE, 'wp_delete_menu', $arg, (string) $result['error'] ) );
                    return Batch::FAILURE;
                }
                $ctx->mark_changed();
                $ctx->out->log( "Deleted menu '{$arg}'." );
                return Batch::SUCCESS;
            },
            static function ( $arg ) {
                return 'menu ' . (string) $arg;
            }
        );
        $batch->report();
    }

    public function list_menus( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        $menus = array();
        foreach ( self::all_pages( $inv, $ctx, 'wp_list_menus', array(), 'menus' ) as $row ) {
            $menus[] = self::menu_row( $row );
        }

        if ( ! isset( $assoc['format'] ) || in_array( $assoc['format'], array( 'csv', 'table' ), true ) ) {
            foreach ( $menus as $menu ) {
                $menu->locations = implode( ',', $menu->locations );
            }
        }

        $formatter = $this->formatter( $ctx, $assoc, self::MENU_FIELDS, 'nav_menu' );
        if ( 'ids' === $formatter->format ) {
            $ids = array();
            foreach ( $menus as $menu ) {
                $ids[] = $menu->term_id;
            }
            $formatter->display_items( $ids );
        } else {
            $formatter->display_items( $menus );
        }
    }

    public function item_list( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, 'wp_list_menu_items' );
        $menu = self::resolve_menu( (string) $inv->args[0], false );
        if ( null === $menu ) {
            $ctx->out->error( 'Invalid menu.' );
        }
        $rows  = array();
        $pages = self::all_pages(
            $inv,
            $ctx,
            'wp_list_menu_items',
            array(
                'menu_id'  => (int) $menu['term_id'],
                'per_page' => self::PER_PAGE,
            ),
            'items'
        );
        foreach ( $pages as $row ) {
            if ( isset( $row['id'] ) ) {
                $rows[] = $row;
            }
        }
        $items = self::valid_items( $rows );

        $assoc = $inv->assoc;
        if ( ! empty( $assoc['format'] ) && 'ids' === $assoc['format'] ) {
            $db_ids = array();
            foreach ( $items as $item ) {
                $db_ids[] = $item->db_id;
            }
            $items = $db_ids;
        }

        $this->formatter( $ctx, $assoc, self::ITEM_FIELDS )->display_items( $items );
    }

    private static function all_pages( Invocation $inv, Context $ctx, string $tool, array $arguments, string $key ): array {
        $rows = array();
        $page = 1;
        do {
            $result = $ctx->delegate( $tool, $arguments + array( 'page' => $page ) );
            if ( ! $result['ok'] ) {
                $ctx->out->error( (string) $result['error'] );
            }
            $payload = $result['payload'];
            if ( 1 === $page ) {
                self::refuse_over_row_cap( $inv, $ctx, $payload );
            }
            foreach ( isset( $payload[ $key ] ) ? (array) $payload[ $key ] : array() as $row ) {
                if ( is_array( $row ) ) {
                    $rows[] = $row;
                }
            }
            $pages = isset( $payload['total_pages'] ) ? (int) $payload['total_pages'] : 1;
            ++$page;
        } while ( $page <= $pages );
        return $rows;
    }

    private static function refuse_over_row_cap( Invocation $inv, Context $ctx, array $payload ): void {
        $count = isset( $inv->assoc['format'] ) && 'count' === $inv->assoc['format'];
        if ( ! $count && isset( $payload['total'] ) && (int) $payload['total'] > self::ROW_CAP ) {
            $ctx->out->error( sprintf( self::ROW_CAP_MESSAGE, $inv->command, (int) $payload['total'], self::ROW_CAP, $inv->command ) );
        }
    }

    private static function valid_items( array $rows ): array {
        $ids = array();
        foreach ( $rows as $row ) {
            $ids[] = (int) $row['id'];
        }
        $invalid = $ids ? array_map( 'intval', (array) self::wp( 'invalid_items', array( $ids ), array( __CLASS__, 'live_invalid_items' ) ) ) : array();

        $items    = array();
        $position = 0;
        foreach ( $rows as $row ) {
            if ( in_array( (int) $row['id'], $invalid, true ) ) {
                continue;
            }
            $items[] = self::item_row( $row, ++$position );
        }
        return $items;
    }

    public function item_add_custom( Invocation $inv, Context $ctx ): void {
        $this->add_item( $inv, $ctx, 'custom' );
    }

    public function item_add_post( Invocation $inv, Context $ctx ): void {
        $this->add_item( $inv, $ctx, 'post_type' );
    }

    public function item_add_term( Invocation $inv, Context $ctx ): void {
        $this->add_item( $inv, $ctx, 'taxonomy' );
    }

    public function item_update( Invocation $inv, Context $ctx ): void {
        $fields = $this->item_fields( $inv, $ctx, false );
        Fetchers::require_tool( $ctx, $inv, 'wp_update_menu_item' );
        $db_id = (string) $inv->args[0];
        $menu_id = ctype_digit( $db_id ) ? (int) self::wp( 'item_menu', array( (int) $db_id ), array( __CLASS__, 'live_item_menu' ) ) : 0;
        if ( $menu_id < 1 ) {
            $ctx->out->error( 'Invalid menu.' );
        }
        $result = $ctx->delegate( 'wp_update_menu_item', array_merge( array( 'item_id' => (int) $db_id ), $fields ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        if ( ! empty( $result['payload']['changed'] ) ) {
            $ctx->mark_changed();
        }
        $ctx->out->success( 'Menu item updated.' );
    }

    public function item_delete( Invocation $inv, Context $ctx ): void {
        $batch = new Batch( $inv, $ctx, 'menu item', 'delete' );
        $batch->run(
            array_values( $inv->args ),
            static function ( $arg, Batch $batch ) use ( $ctx ) {
                $arg = (string) $arg;
                if ( ! ctype_digit( $arg ) ) {
                    $ctx->out->warning( sprintf( self::BAD_DB_ID, $arg ) );
                    return Batch::FAILURE;
                }
                $result = $batch->delegate( 'wp_delete_menu_item', self::delete_item_arguments( $arg ) );
                if ( Batch::is_rate_limited( $result ) ) {
                    return Batch::FAILURE;
                }
                if ( ! $result['ok'] ) {
                    $ctx->out->warning( sprintf( self::BAD_DB_ID, $arg ) );
                    $ctx->note( sprintf( self::REFUSED_ITEM_NOTE, 'wp_delete_menu_item', $arg, (string) $result['error'] ) );
                    return Batch::FAILURE;
                }
                $ctx->mark_changed();
                if ( isset( $result['payload']['error'] ) && is_string( $result['payload']['error'] ) ) {
                    $ctx->out->warning( $result['payload']['error'] );
                }
                return Batch::SUCCESS;
            },
            static function ( $arg ) {
                return 'menu item ' . (string) $arg;
            }
        );
        $batch->report();
    }

    public function preflight_delete( Invocation $inv, Context $ctx ): void {
        $cap = Batch::cap( $inv );
        if ( count( $inv->args ) > $cap ) {
            $ctx->out->error( sprintf( self::OVER_CAP, $cap, $inv->command, count( $inv->args ) ) );
        }
    }

    public function plan_delete( Invocation $inv ): array {
        if ( count( $inv->args ) > Batch::cap( $inv ) || ! self::plan_may_read( 'wp_delete_menu' ) ) {
            return array();
        }
        $plan = array();
        foreach ( $inv->args as $arg ) {
            $menu = self::resolve_menu( (string) $arg, true );
            if ( null !== $menu ) {
                $plan[] = array(
                    'tool'      => 'wp_delete_menu',
                    'arguments' => array( 'menu_id' => (int) $menu['term_id'] ),
                );
            }
        }
        return $plan;
    }

    public function plan_item_delete( Invocation $inv ): array {
        $plan = array();
        foreach ( $inv->args as $arg ) {
            if ( ctype_digit( (string) $arg ) ) {
                $plan[] = array(
                    'tool'      => 'wp_delete_menu_item',
                    'arguments' => self::delete_item_arguments( (string) $arg ),
                );
            }
        }
        return $plan;
    }

    private static function plan_may_read( string $tool ): bool {
        if ( class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) && method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' )
            && false === \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool ) ) {
            return false;
        }
        return (bool) \current_user_can( 'edit_theme_options' );
    }

    public static function resolve_menu( string $arg, bool $strict, ?string &$refusal = null ): ?array {
        $refusal = null;
        $menu    = self::wp( 'menu', array( $arg, $strict && ! ctype_digit( $arg ) ), array( __CLASS__, 'live_menu' ) );
        if ( ! is_array( $menu ) || ! isset( $menu['term_id'] ) ) {
            return null;
        }
        $menu = array(
            'term_id' => (int) $menu['term_id'],
            'name'    => isset( $menu['name'] ) ? (string) $menu['name'] : '',
            'slug'    => isset( $menu['slug'] ) ? (string) $menu['slug'] : '',
        );
        if ( $strict && preg_match( '/[^\x20-\x7E]/', $arg ) && $menu['name'] !== $arg && $menu['slug'] !== $arg ) {
            $refusal = sprintf( self::FOLDED_MENU, $arg, $menu['name'], $menu['term_id'] );
            return null;
        }
        return $menu;
    }

    public static function wp( string $key, array $args, callable $live ) {
        if ( null !== self::$reader ) {
            return call_user_func( self::$reader, $key, $args, $live );
        }
        return call_user_func_array( $live, $args );
    }

    public static function use_reader( ?callable $reader ): void {
        self::$reader = $reader;
    }

    public static function live_menu( string $arg, bool $skip_id ): ?array {
        if ( $skip_id ) {
            $menu = '' !== $arg ? \get_term_by( 'slug', $arg, 'nav_menu' ) : false;
            if ( ! $menu && '' !== $arg ) {
                $menu = \get_term_by( 'name', $arg, 'nav_menu' );
            }
            if ( ! $menu || \is_wp_error( $menu ) ) {
                $menu = false;
            }
            $menu = \apply_filters( 'wp_get_nav_menu_object', $menu, $arg );
        } else {
            $menu = \wp_get_nav_menu_object( $arg );
        }
        if ( ! is_object( $menu ) || \is_wp_error( $menu ) || ! isset( $menu->term_id ) ) {
            return null;
        }
        return array(
            'term_id' => (int) $menu->term_id,
            'name'    => (string) $menu->name,
            'slug'    => (string) $menu->slug,
        );
    }

    public static function live_post_type( string $arg ): ?string {
        $post = \get_post( $arg );
        return $post ? (string) $post->post_type : null;
    }

    public static function live_term_exists( string $taxonomy, string $term_id ): bool {
        return (bool) \get_term_by( 'id', $term_id, $taxonomy );
    }

    public static function live_item_menu( int $item_id ): int {
        $terms = \get_the_terms( $item_id, 'nav_menu' );
        return ( $terms && ! \is_wp_error( $terms ) ) ? (int) $terms[0]->term_id : 0;
    }

    public static function live_invalid_items( array $ids ): array {
        $invalid = array();
        foreach ( $ids as $id ) {
            $post = \get_post( (int) $id );
            if ( ! $post ) {
                continue;
            }
            $item = \wp_setup_nav_menu_item( $post );
            if ( ! empty( $item->_invalid ) ) {
                $invalid[] = (int) $id;
            }
        }
        return $invalid;
    }

    private function add_item( Invocation $inv, Context $ctx, string $type ): void {
        $fields = $this->item_fields( $inv, $ctx, true );
        Fetchers::require_tool( $ctx, $inv, 'wp_create_menu_item' );

        $arguments = self::object_arguments( $inv, $ctx, $type );
        $menu      = self::write_menu( (string) $inv->args[0], $ctx );

        $result = $ctx->delegate( 'wp_create_menu_item', array_merge( array( 'menu_id' => $menu['term_id'] ), $arguments, $fields ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $ctx->mark_changed();
        if ( isset( $result['payload']['error'] ) && is_string( $result['payload']['error'] ) ) {
            $ctx->out->warning( $result['payload']['error'] );
        }
        if ( ! empty( $inv->assoc['porcelain'] ) ) {
            $ctx->out->line( (string) ( isset( $result['payload']['id'] ) ? (int) $result['payload']['id'] : 0 ) );
        } else {
            $ctx->out->success( 'Menu item added.' );
        }
    }

    private static function object_arguments( Invocation $inv, Context $ctx, string $type ): array {
        $arguments = array( 'object_type' => $type );
        if ( 'post_type' === $type ) {
            $post_id   = (string) $inv->args[1];
            $post_type = self::wp( 'post_type', array( $post_id ), array( __CLASS__, 'live_post_type' ) );
            if ( ! is_string( $post_type ) ) {
                $ctx->out->error( 'Invalid post.' );
            }
            $arguments['object']    = $post_type;
            $arguments['object_id'] = (int) $post_id;
        } elseif ( 'taxonomy' === $type ) {
            $taxonomy = (string) $inv->args[1];
            $term_id  = (string) $inv->args[2];
            if ( ! self::wp( 'term_exists', array( $taxonomy, $term_id ), array( __CLASS__, 'live_term_exists' ) ) ) {
                $ctx->out->error( 'Invalid term.' );
            }
            $arguments['object']    = $taxonomy;
            $arguments['object_id'] = (int) $term_id;
        } else {
            $arguments['title'] = (string) $inv->args[1];
            $arguments['url']   = (string) $inv->args[2];
        }
        return $arguments;
    }

    private static function write_menu( string $arg, Context $ctx ): array {
        $refusal = null;
        $menu    = self::resolve_menu( $arg, true, $refusal );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
        if ( null === $menu ) {
            $ctx->out->error( 'Invalid menu.' );
        }
        return $menu;
    }

    private function item_fields( Invocation $inv, Context $ctx, bool $add ): array {
        $fields = array();
        $map    = array(
            'title'       => 'title',
            'link'        => 'url',
            'description' => 'description',
            'attr-title'  => 'attr_title',
            'target'      => 'target',
            'classes'     => 'classes',
            'position'    => 'position',
            'parent-id'   => 'parent',
        );
        foreach ( $map as $flag => $argument ) {
            if ( ! array_key_exists( $flag, $inv->assoc ) ) {
                continue;
            }
            $value = $inv->assoc[ $flag ];
            if ( ! is_string( $value ) ) {
                $ctx->out->error( sprintf( self::STRING_FLAG, $flag, $flag, $flag, $inv->command ) );
            }
            $fields = array_merge( $fields, self::item_field( $flag, $argument, $value, $inv, $ctx, $add ) );
        }
        return $fields;
    }

    private static function item_field( string $flag, string $argument, string $value, Invocation $inv, Context $ctx, bool $add ): array {
        switch ( $flag ) {
            case 'target':
                if ( ! in_array( $value, self::TARGETS, true ) ) {
                    $ctx->out->error( sprintf( self::BAD_TARGET, $value, $inv->command, $add ? 'wp_create_menu_item' : 'wp_update_menu_item' ) );
                }
                return array( 'target' => $value );
            case 'classes':
                return array( 'classes' => explode( ' ', $value ) );
            case 'position':
            case 'parent-id':
                return self::number_field( $flag, $argument, $value, $inv, $ctx, $add );
            default:
                return array( $argument => $value );
        }
    }

    private static function number_field( string $flag, string $argument, string $value, Invocation $inv, Context $ctx, bool $add ): array {
        if ( ! ctype_digit( $value ) ) {
            $ctx->out->error( sprintf( self::BAD_NUMBER, $flag, $value, $inv->command ) );
        }
        if ( 'position' === $flag && $add ) {
            return (int) $value > 0 ? array( 'position' => (int) $value, 'shift_following' => true ) : array();
        }
        return array( $argument => (int) $value );
    }

    private static function delete_item_arguments( string $db_id ): array {
        return array(
            'item_id'           => (int) $db_id,
            'reparent_children' => true,
        );
    }

    private static function menu_row( array $row ): \stdClass {
        return (object) array(
            'term_id'          => self::int_field( $row, 'id' ),
            'name'             => self::text_field( $row, 'name' ),
            'slug'             => self::text_field( $row, 'slug' ),
            'term_group'       => self::int_field( $row, 'term_group' ),
            'term_taxonomy_id' => self::int_field( $row, 'term_taxonomy_id' ),
            'taxonomy'         => self::text_field( $row, 'taxonomy', 'nav_menu' ),
            'description'      => self::text_field( $row, 'description' ),
            'parent'           => self::int_field( $row, 'parent' ),
            'count'            => self::int_field( $row, 'count' ),
            'filter'           => 'raw',
            'locations'        => isset( $row['locations'] ) ? array_values( array_map( 'strval', (array) $row['locations'] ) ) : array(),
        );
    }

    private static function item_row( array $row, int $position ): \stdClass {
        $xfn = isset( $row['xfn'] ) && is_array( $row['xfn'] ) ? implode( ' ', array_map( 'strval', $row['xfn'] ) ) : '';
        return (object) array(
            'ID'               => (int) $row['id'],
            'post_type'        => 'nav_menu_item',
            'post_status'      => 'publish',
            'menu_order'       => $position,
            'db_id'            => (int) $row['id'],
            'menu_item_parent' => (string) self::int_field( $row, 'parent' ),
            'object_id'        => (string) self::int_field( $row, 'object_id' ),
            'object'           => self::text_field( $row, 'object' ),
            'type'             => self::text_field( $row, 'type' ),
            'type_label'       => self::text_field( $row, 'type_label' ),
            'url'              => self::text_field( $row, 'url' ),
            'title'            => self::text_field( $row, 'title_raw' ),
            'target'           => self::text_field( $row, 'target' ),
            'attr_title'       => self::text_field( $row, 'attr_title' ),
            'description'      => self::text_field( $row, 'description' ),
            'classes'          => isset( $row['classes'] ) && is_array( $row['classes'] ) ? array_values( array_map( 'strval', $row['classes'] ) ) : array(),
            'xfn'              => $xfn,
            'position'         => $position,
            'link'             => self::text_field( $row, 'url' ),
        );
    }

    private static function int_field( array $row, string $key ): int {
        return isset( $row[ $key ] ) ? (int) $row[ $key ] : 0;
    }

    private static function text_field( array $row, string $key, string $absent = '' ): string {
        return isset( $row[ $key ] ) ? (string) $row[ $key ] : $absent;
    }
}
