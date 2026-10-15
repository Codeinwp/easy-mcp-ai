<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Db_Object_Command;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Tools\Post_Type_Rest_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( Post_Type_Rest_Base::class, false ) ) {
    require_once dirname( __DIR__, 2 ) . '/tools/class-post-type-rest-base.php';
}

























































final class Post_Adapter extends Db_Object_Command {

    protected $obj_type = 'post';

    protected $obj_fields = array( 'ID', 'post_title', 'post_name', 'post_date', 'post_status' );

    
    const ROW_CAP = 1000;

    
    const PAGE_SIZE = 100;

    
    const BUILTIN_READ_BASES = array(
        'post'       => 'posts',
        'page'       => 'pages',
        'attachment' => 'media',
        'wp_block'   => 'blocks',
    );

    
    const TOOLS = array(
        'create' => array(
            'post' => 'wp_create_post',
            'page' => 'wp_create_page',
            'cpt'  => 'wp_create_cpt_item',
        ),
        'update' => array(
            'post' => 'wp_update_post',
            'page' => 'wp_update_page',
            'cpt'  => 'wp_update_cpt_item',
        ),
        'list'   => array(
            'post'  => 'wp_list_posts',
            'page'  => 'wp_list_pages',
            'media' => 'wp_list_media',
            'cpt'   => 'wp_list_cpt_items',
        ),
    );

    





    const FIELDS = array(
        'post_title'     => array( 'title', 'title' ),
        'post_content'   => array( 'content', 'editor' ),
        'post_excerpt'   => array( 'excerpt', 'excerpt' ),
        'post_status'    => array( 'status', '' ),
        'post_date'      => array( 'date', '' ),
        'post_date_gmt'  => array( 'date_gmt', '' ),
        'post_author'    => array( 'author', 'author' ),
        'post_name'      => array( 'slug', '' ),
        'post_password'  => array( 'password', '' ),
        'comment_status' => array( 'comment_status', 'comments' ),
        'ping_status'    => array( 'ping_status', 'comments' ),
        'post_parent'    => array( 'parent', 'hierarchical' ),
        'menu_order'     => array( 'menu_order', 'page-attributes' ),
        'page_template'  => array( 'template', '' ),
    );

    
    const INT_FIELDS = array( 'post_author', 'post_parent', 'menu_order' );

    
    const CLEARABLE = array(
        'post_title'   => 'title',
        'post_content' => 'content',
        'post_excerpt' => 'excerpt',
    );

    
    const CONTROL_KEYS = array(
        'create' => array( 'post_type', 'post_category', 'tags_input', 'meta_input', 'porcelain' ),
        'update' => array( 'post_type', 'post_category', 'tags_input', 'meta_input', 'defer-term-counting' ),
    );

    



    const LIST_VARS = array(
        'post_type'           => array( 'post', 'page', 'cpt', 'media' ),
        'post_status'         => array( 'post', 'page', 'cpt', 'media' ),
        's'                   => array( 'post', 'page', 'cpt' ),
        'author'              => array( 'post', 'page', 'cpt', 'media' ),
        'author__in'          => array( 'post', 'page', 'cpt', 'media' ),
        'author__not_in'      => array( 'post', 'page', 'cpt', 'media' ),
        'post__in'            => array( 'post', 'page', 'cpt', 'media' ),
        'post__not_in'        => array( 'post', 'page', 'cpt', 'media' ),
        'post_name__in'       => array( 'post', 'page', 'cpt' ),
        'posts_per_page'      => array( 'post', 'page', 'cpt', 'media' ),
        'paged'               => array( 'post', 'page', 'cpt', 'media' ),
        'offset'              => array( 'post', 'page', 'cpt', 'media' ),
        'orderby'             => array( 'post', 'page', 'cpt', 'media' ),
        'order'               => array( 'post', 'page', 'cpt', 'media' ),
        'has_password'        => array( 'post', 'page', 'cpt' ),
        'date_query'          => array( 'post', 'page', 'cpt' ),
        'tax_query'           => array( 'post', 'page', 'cpt' ),
        'meta_query'          => array( 'post', 'page', 'cpt' ),
        'post_parent'         => array( 'page', 'cpt', 'media' ),
        'post_parent__not_in' => array( 'page', 'cpt', 'media' ),
        'meta_key'            => array( 'post' ),
        'meta_value'          => array( 'post' ),
        'meta_compare'        => array( 'post' ),
        'comment_count'       => array( 'post' ),
        'year'                => array( 'post' ),
        'monthnum'            => array( 'post' ),
        'day'                 => array( 'post' ),
        'post_mime_type'      => array( 'post', 'media' ),
        'cat'                 => array( 'post' ),
        'category__in'        => array( 'post' ),
        'tag_id'              => array( 'post' ),
        'tag__in'             => array( 'post' ),
        'ignore_sticky_posts' => array( 'post', 'page', 'cpt', 'media' ),
    );

    
    const LIST_ID_LISTS = array(
        'author__not_in'      => 'author_exclude',
        'post__in'            => 'include',
        'post__not_in'        => 'exclude',
        'post_parent__not_in' => 'parent_exclude',
        'category__in'        => 'categories',
        'tag__in'             => 'tags',
    );

    
    const LIST_ORDERBY = array(
        'date'           => 'date',
        'post_date'      => 'date',
        'ID'             => 'id',
        'title'          => 'title',
        'post_title'     => 'title',
        'name'           => 'slug',
        'post_name'      => 'slug',
        'modified'       => 'modified',
        'post_modified'  => 'modified',
        'author'         => 'author',
        'post_author'    => 'author',
        'parent'         => 'parent',
        'post_parent'    => 'parent',
        'menu_order'     => 'menu_order',
        'comment_count'  => 'comment_count',
        'rand'           => 'rand',
        'post__in'       => 'include',
        'meta_value'     => 'meta_value',
        'meta_value_num' => 'meta_value_num',
    );

    
    const MEDIA_ORDERBY = array( 'date', 'id', 'title', 'slug', 'modified', 'author', 'parent', 'include' );

    
    const NOT_HOME_VARS = array( 's', 'year', 'monthnum', 'day' );

    
    
    

    const MSG_PARAM_FRAME = "Parameter errors:\n%s";

    const MSG_PARAM = ' --%s parameter is not available through wp_cli_run: %s';

    const MSG_NOT_A_FIELD = 'it is not a wp_insert_post() field any tool takes';

    const MSG_TYPE_UNREGISTERED = "'wp %s' cannot act on post type '%s': it is not registered on this site.";

    const MSG_TYPE_BUILTIN = "'wp %s' cannot act on post type '%s' through wp_cli_run: no tool performs it for that built-in type.";

    const MSG_TYPE_NOT_REST = "'wp %s' cannot act on post type '%s': it is not shown in the REST API (show_in_rest is false), and the tools reach posts only through it.";

    const MSG_TYPE_NAMESPACE = "'wp %s' cannot act on post type '%s': its REST routes live under %s, and the tools address /wp/v2 only.";

    const MSG_TYPE_BASE = "'wp %s' cannot act on post type '%s': its REST base '%s' is not one the tools can address safely.";

    const MSG_TYPE_SHARED_BASE = "'wp %s' cannot act on post type '%s': its REST base '%s' belongs to post type '%s' first, so the tools would reach that type.";

    const MSG_EDIT_CAP = "'wp %s' reads post %d through wp_get_post_full in edit context, which needs the edit_post capability for that post.";

    const MSG_TITLE_CLEANED = "--post_title is not available through wp_cli_run as given: %s stores titles through sanitize_text_field(), which would change this one (tags, line breaks, tabs, repeated spaces or %%xx sequences). Nothing was written.";

    const MSG_NO_SUPPORT = "--%s is not available for post type '%s': it does not support '%s', so the REST API would drop the value. Nothing was written.";

    const MSG_NO_ARGUMENT = "--%s is not available for post type '%s': %s takes no %s.";

    const MSG_NEEDS_VALUE = '--%s needs a value: WP-CLI would store %s.';

    const MSG_NOT_INT = '--%s must be a whole number of 0 or more, as WordPress stores it; WP-CLI would store %s as 0.';

    const MSG_EMPTY_KEEP = "--%s='' is not available through wp_cli_run: WP-CLI stores the empty value, and %s reads an empty value as \"keep the current one\".";

    const MSG_META_NOT_JSON = '--meta_input must be a JSON object such as {"key":"value"}: WP-CLI passes any other value to wp_insert_post() as a string, which drops it.';

    const MSG_META_NOT_REST = "--meta_input key '%s' is not registered for the REST API on post type '%s', so %s would create the post and drop it. Create the post first, then run `post meta update <id> %s <value>`.";

    const MSG_NO_CATEGORIES = "--post_category is not available for post type '%s': it has no category taxonomy shown in the REST API, and WP-CLI would ignore it.";

    const MSG_TAGS_ONLY_POSTS = "--tags_input is available for post type 'post' only: %s takes no tag names for post type '%s'.";

    const MSG_UPDATE_TYPE = "--post_type is not available on update: changing a post's type is not possible through the REST API the tools use. Nothing was written.";

    const MSG_UPDATE_TRASH = '--post_status=trash is not available on update: use `post delete <id>` to move a post to the trash. Nothing was written.';

    const MSG_UPDATE_FILE = "<file> is not available through wp_cli_run: WP-CLI reads '%s' from the server's disk. Pass the content with --post_content.";

    const MSG_FORCE_DRAFT = "The site's \"Force draft on create\" setting saved post %d with status '%s', not '%s'.";

    const MSG_META_DROPPED = 'Post %d was saved, but %s did not store these meta keys: %s.';

    const MSG_LIST_TYPES = "--post_type=%s is not available through wp_cli_run: it lists one post type per command, each through its own tool. List each type separately.";

    const MSG_LIST_VAR = 'it is not a WP_Query variable %s maps';

    const MSG_LIST_KIND = "%s takes no %s for post type '%s'";

    const MSG_MEDIA_SEARCH = "wp_list_media also matches file names, which WP-CLI's search does not, so the rows would differ. Search with wp_list_media itself, or list by --post__in";

    const MSG_MEDIA_MIME = 'wp_list_media filters only on exact MIME types this user may upload, and drops any other value without filtering at all; name each type exactly (e.g. image/jpeg)';

    const MSG_MEDIA_MIME_ONE = "this site's media collection takes one MIME type at a time (a list from WordPress 6.9) and would drop a list without filtering at all; list one type per command";

    const MSG_MEDIA_MIME_EMPTY = 'WP_Query reads an empty entry as every type and drops the whole MIME filter; remove the empty entry';

    const MSG_MEDIA_ORDERBY = 'wp_list_media orders only by date, ID, title, name, modified, author, parent or post__in';

    const MSG_LIST_PAGING = '--%s needs --posts_per_page: WP_Query ignores it when every post is listed (posts_per_page -1).';

    const MSG_LIST_CAP = "wp_cli_run returns at most %d rows from 'wp post list', and this query matches %d. Page through it with --posts_per_page=<n> and --paged=<page> (or --offset=<n>), or use --format=count.";

    const MSG_LIST_VALUE = '--%s=%s is not a value wp_cli_run can pass on: %s';

    const MSG_LIST_STRING_BOOL = 'WP_Query reads it only as a flag (--%1$s or --no-%1$s) and ignores a string value';

    const MSG_LIST_SINGLE = "--name is not available through wp_cli_run: WP_Query treats it as a single-post request. Use --post_name__in=<slug>.";

    const MSG_INNER_SHAPE = '%s returned no %s, so wp_cli_run cannot print what WP-CLI prints.';

    const MSG_UNSAFE_TAG = 'wp_cli_run refuses a tag name in --tags_input that has a control character or a non-ASCII byte: the term tables compare names without regard to accents or control characters, so such a name can attach another tag than the one named. Save the post without it, then attach the tag by ID: `post term add <id> post_tag <term_id> --by=id` (`term list post_tag --fields=term_id,name,slug` prints the IDs).';

    const MSG_UNSAFE_CATEGORY = 'wp_cli_run refuses a category name in --post_category that has a control character or a non-ASCII byte: the term tables compare names without regard to accents or control characters, so such a name can match another category than the one named. Pass the category by ID instead (`term list category --fields=term_id,name,slug` prints the IDs).';

    
    
    

    
    public function get( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $post       = Fetchers::of( 'post' )->get_check_for(
            $ctx,
            $inv,
            'wp_get_post_full',
            isset( $inv->args[0] ) ? $inv->args[0] : '',
            static function ( object $post ) use ( $inv ): ?string {
                return \current_user_can( 'edit_post', (int) $post->ID ) ? null : sprintf( self::MSG_EDIT_CAP, $inv->command, (int) $post->ID );
            }
        );
        $post_arr = self::raw_post( $ctx, $post, $inv->command );

        if ( empty( $assoc_args['fields'] ) ) {
            $assoc_args['fields'] = array_keys( $post_arr );
        }

        $formatter = $this->get_formatter( $ctx, $assoc_args );
        $formatter->display_item( $post_arr );
    }

    







    private static function raw_post( Context $ctx, object $post, string $command ): array {
        $base = self::read_base( (string) $post->post_type, $command );
        if ( ! is_array( $base ) ) {
            $ctx->out->error( $base );
        }

        $result = $ctx->delegate(
            'wp_get_post_full',
            array(
                'id'        => (int) $post->ID,
                'post_type' => $base[0],
                'include'   => array(),
                'context'   => 'edit',
                'raw_post'  => true,
            )
        );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $post_arr = isset( $result['payload']['raw_post'] ) && is_array( $result['payload']['raw_post'] ) ? $result['payload']['raw_post'] : null;
        if ( null === $post_arr ) {
            $ctx->out->error( sprintf( self::MSG_INNER_SHAPE, 'wp_get_post_full', 'raw_post' ) );
        }
        unset( $post_arr['filter'] );

        if ( ! isset( $post_arr['url'] ) ) {
            $post_arr['url'] = isset( $result['payload']['post']['link'] ) && is_string( $result['payload']['post']['link'] ) ? $result['payload']['post']['link'] : \get_permalink( (int) $post->ID );
        }
        return $post_arr;
    }

    
    
    

    
    public function create( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        self::refuse_unknown_fields( $ctx, $assoc, 'create' );
        $type = self::post_type_argument( $ctx, $assoc );
        $unsafe = self::unsafe_term_names( $assoc );
        if ( null !== $unsafe ) {
            $ctx->out->error( $unsafe );
        }

        $target = self::write_target( $type, $inv->command );
        if ( ! is_array( $target ) ) {
            $ctx->out->error( $target );
        }
        $tool = self::TOOLS['create'][ $target[0] ];
        Fetchers::require_tool( $ctx, $inv, $tool );

        $call = self::write_call( 'create', $type, $target, $assoc, null );
        if ( ! is_array( $call ) ) {
            $ctx->out->error( $call );
        }

        $this->_create(
            $inv,
            $ctx,
            $inv->args,
            $assoc,
            static function () use ( $ctx, $call, $assoc ) {
                $result = $ctx->delegate( $call[0], $call[1] );
                if ( ! $result['ok'] ) {
                    return Db_Object_Command::failure( (string) $result['error'] );
                }
                $id = isset( $result['payload']['id'] ) ? (int) $result['payload']['id'] : 0;
                if ( $id < 1 ) {
                    return Db_Object_Command::failure( sprintf( self::MSG_INNER_SHAPE, $call[0], 'post ID' ) );
                }
                self::report_saved( $ctx, $call, $result['payload'], $id, $assoc );
                return $id;
            }
        );
    }

    
    
    

    
    public function update( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        $ids   = self::update_ids( $ctx, $inv->args );
        self::refuse_unknown_fields( $ctx, $assoc, 'update' );
        if ( array_key_exists( 'post_type', $assoc ) ) {
            $ctx->out->error( self::MSG_UPDATE_TYPE );
        }
        if ( isset( $assoc['post_status'] ) && 'trash' === $assoc['post_status'] ) {
            $ctx->out->error( self::MSG_UPDATE_TRASH );
        }
        $unsafe = self::unsafe_term_names( $assoc );
        if ( null !== $unsafe ) {
            $ctx->out->error( $unsafe );
        }

        $fields = $assoc;
        unset( $fields['defer-term-counting'] );
        $this->_update(
            $inv,
            $ctx,
            $ids,
            $assoc,
            static function ( array $params, Batch $batch ) use ( $ctx, $inv, $fields ) {
                $id      = (int) $params['ID'];
                $planned = self::update_call( $id, $fields, $inv->command, $ctx );
                if ( ! isset( $planned['call'] ) ) {
                    return $planned['response'];
                }
                $call   = $planned['call'];
                $result = $batch->delegate( $call[0], $call[1] );
                if ( ! $result['ok'] ) {
                    return Db_Object_Command::failure( (string) $result['error'] );
                }
                self::report_saved( $ctx, $call, $result['payload'], $id, $fields );
                return $id;
            }
        );
    }

    








    public function plan_update( Invocation $inv ): array {
        $assoc = $inv->assoc;
        if ( ! self::plannable_update( $assoc ) ) {
            return array();
        }
        unset( $assoc['defer-term-counting'] );
        $plan = array();
        foreach ( array_values( $inv->args ) as $arg ) {
            if ( ! Ids::is_digits( $arg ) ) {
                return array();
            }
            $planned = self::update_call( (int) $arg, $assoc, $inv->command, null );
            if ( ! isset( $planned['call'] ) ) {
                continue;
            }
            $call = $planned['call'];
            if ( ! self::caller_may_use( $call[0] ) ) {
                return array();
            }
            $plan[] = array(
                'tool'      => $call[0],
                'arguments' => $call[1],
            );
        }
        return $plan;
    }

    






    private static function plannable_update( array $assoc ): bool {
        if ( array_key_exists( 'post_type', $assoc ) || ( isset( $assoc['post_status'] ) && 'trash' === $assoc['post_status'] ) || ! $assoc ) {
            return false;
        }
        foreach ( array_keys( $assoc ) as $key ) {
            if ( ! isset( self::FIELDS[ (string) $key ] ) && ! in_array( (string) $key, self::CONTROL_KEYS['update'], true ) ) {
                return false;
            }
        }
        return true;
    }

    






    public function preflight_update( Invocation $inv, Context $ctx ): void {
        self::update_ids( $ctx, $inv->args );
    }

    
    
    

    





    public function preflight_delete( Invocation $inv, Context $ctx ): void {
        Ids::require_all( $ctx, array_values( $inv->args ), 'a post ID' );
    }

    
    public function delete( Invocation $inv, Context $ctx ): void {
        $assoc_args = array_merge( array( 'force' => false ), $inv->assoc );
        $this->_delete(
            $inv,
            $ctx,
            array_values( $inv->args ),
            $assoc_args,
            static function ( string $post_id, array $assoc_args, Batch $batch ) use ( $ctx, $inv ): array {
                $call = self::delete_call( $post_id, ! empty( $assoc_args['force'] ), $inv->command, $ctx );
                if ( ! isset( $call['tool'] ) ) {
                    return $call;
                }
                $result = $batch->delegate( $call['tool'], $call['arguments'] );
                if ( ! $result['ok'] ) {
                    return array( 'error', (string) $result['error'] );
                }
                return array( 'success', "{$call['action']} post {$post_id}." );
            }
        );
    }

    




    public function plan_delete( Invocation $inv ): array {
        $force = ! empty( $inv->assoc['force'] );
        $plan  = array();
        foreach ( array_values( $inv->args ) as $arg ) {
            if ( ! Ids::is_digits( $arg ) ) {
                return array();
            }
            $call = self::delete_call( (string) $arg, $force, $inv->command, null );
            if ( ! isset( $call['tool'] ) ) {
                continue;
            }
            if ( ! self::caller_may_use( $call['tool'] ) ) {
                return array();
            }
            $plan[] = array(
                'tool'      => $call['tool'],
                'arguments' => $call['arguments'],
            );
        }
        return $plan;
    }

    
    
    

    
    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $formatter  = $this->get_formatter( $ctx, $assoc_args );
        $format     = $formatter->format;
        $query      = self::list_query( $assoc_args );
        $type       = self::list_post_type( $ctx, $query );
        $target     = self::list_target( $type, $inv->command );
        if ( ! is_array( $target ) ) {
            $ctx->out->error( $target );
        }
        $tool = self::TOOLS['list'][ $target[0] ];

        $mapped = self::list_arguments( $query, $target, $tool );
        if ( is_string( $mapped ) ) {
            $ctx->out->error( $mapped );
        }
        Fetchers::require_tool( $ctx, $inv, $tool );
        list( $args, $paging ) = $mapped;

        if ( 'count' === $format && ! $formatter->field ) {
            
            
            $first = self::list_page( $ctx, $tool, $target[0], $args, $paging['start'], 1 );
            $formatter->display_items( array_fill( 0, self::rows_expected( $first['total'], $paging ), 0 ) );
            return;
        }

        $rows = self::list_rows( $ctx, $tool, $target[0], $args, $paging );

        if ( 'ids' === $format || 'count' === $format ) {
            self::display_ids( $ctx, $formatter, $rows, $format );
            return;
        }
        $formatter->display_items( self::list_objects( $ctx, $rows, $query, 'post' === $type && $paging['page'] <= 1 ) );
    }

    







    private static function list_query( array $assoc_args ): array {
        foreach ( array( 'date_query', 'tax_query', 'meta_query' ) as $key ) {
            if ( isset( $assoc_args[ $key ] ) && is_string( $assoc_args[ $key ] ) && self::is_json( $assoc_args[ $key ] ) ) {
                $assoc_args[ $key ] = json_decode( $assoc_args[ $key ], true );
            }
        }
        return self::process_csv_arguments_to_arrays( $assoc_args );
    }

    






    private static function list_post_type( Context $ctx, array $query ): string {
        if ( ! isset( $query['post_type'] ) ) {
            return 'post';
        }
        $type = is_string( $query['post_type'] ) ? $query['post_type'] : '';
        if ( 'any' === $type || '' === $type || false !== strpos( $type, ',' ) ) {
            $ctx->out->error( sprintf( self::MSG_LIST_TYPES, $type ) );
        }
        return $type;
    }

    





    private static function display_ids( Context $ctx, Formatter $formatter, array $rows, string $format ): void {
        $ids = array();
        foreach ( $rows as $row ) {
            $ids[] = (int) $row['ID'];
        }
        if ( 'ids' === $format ) {
            $ctx->out->out( implode( ' ', $ids ) );
        } else {
            $formatter->display_items( $ids );
        }
    }

    








    private static function list_objects( Context $ctx, array $rows, array $query, bool $first_post_page ): array {
        if ( $first_post_page && empty( $query['ignore_sticky_posts'] ) && self::is_home( $query ) ) {
            $rows = self::move_sticky_posts( $ctx, $rows, $query );
        }
        $items = array();
        foreach ( $rows as $row ) {
            $items[] = (object) $row;
        }
        return $items;
    }

    
    
    

    







    private static function media_takes_mime_list(): bool {
        $type       = \get_post_type_object( 'attachment' );
        $controller = is_object( $type ) && method_exists( $type, 'get_rest_controller' ) ? $type->get_rest_controller() : null;
        $params     = is_object( $controller ) && method_exists( $controller, 'get_collection_params' ) ? (array) $controller->get_collection_params() : array();
        return isset( $params['mime_type']['type'] ) && 'array' === $params['mime_type']['type'];
    }

    





    public static function list_target( string $post_type, string $command ) {
        return 'attachment' === $post_type ? array( 'media', 'media' ) : self::write_target( $post_type, $command );
    }

    





    public static function write_target( string $post_type, string $command ) {
        if ( 'post' === $post_type ) {
            return array( 'post', 'posts' );
        }
        if ( 'page' === $post_type ) {
            return array( 'page', 'pages' );
        }
        $object = \get_post_type_object( $post_type );
        if ( ! $object ) {
            return sprintf( self::MSG_TYPE_UNREGISTERED, $command, $post_type );
        }
        if ( in_array( $post_type, Post_Type_Rest_Base::BUILTIN_TYPES, true ) ) {
            return sprintf( self::MSG_TYPE_BUILTIN, $command, $post_type );
        }
        $base = self::custom_rest_base( $post_type, $object, $command );
        return is_array( $base ) ? array( 'cpt', $base[0] ) : $base;
    }

    






    public static function read_base( string $post_type, string $command ) {
        if ( isset( self::BUILTIN_READ_BASES[ $post_type ] ) ) {
            return array( self::BUILTIN_READ_BASES[ $post_type ] );
        }
        $object = \get_post_type_object( $post_type );
        if ( ! $object ) {
            return sprintf( self::MSG_TYPE_UNREGISTERED, $command, $post_type );
        }
        if ( in_array( $post_type, Post_Type_Rest_Base::BUILTIN_TYPES, true ) ) {
            return sprintf( self::MSG_TYPE_BUILTIN, $command, $post_type );
        }
        return self::custom_rest_base( $post_type, $object, $command );
    }

    








    public static function custom_rest_base( string $post_type, object $object, string $command ) {
        $found = Post_Type_Rest_Base::inspect( $post_type, $object );
        switch ( $found['reason'] ) {
            case Post_Type_Rest_Base::NOT_REST:
                return sprintf( self::MSG_TYPE_NOT_REST, $command, $post_type );
            case Post_Type_Rest_Base::FOREIGN_NAMESPACE:
                return sprintf( self::MSG_TYPE_NAMESPACE, $command, $post_type, $found['namespace'] );
            case Post_Type_Rest_Base::UNSAFE_BASE:
                return sprintf( self::MSG_TYPE_BASE, $command, $post_type, $found['base'] );
            case Post_Type_Rest_Base::SHARED_BASE:
                return sprintf( self::MSG_TYPE_SHARED_BASE, $command, $post_type, $found['base'], $found['owner'] );
        }
        return array( $found['base'] );
    }

    
    
    

    









    public static function write_call( string $mode, string $type, array $target, array $assoc, ?int $post_id ) {
        list( $kind, $base ) = $target;
        $tool                = self::TOOLS[ $mode ][ $kind ];
        $refusal             = self::field_refusals( $mode, $type, $kind, $tool, $assoc );
        if ( null === $refusal ) {
            $refusal = self::unsafe_term_names( $assoc );
        }
        if ( null !== $refusal ) {
            return $refusal;
        }
        $terms = self::term_arguments( $mode, $type, $kind, $tool, $assoc );
        if ( is_string( $terms ) ) {
            return $terms;
        }
        $meta = self::meta_arguments( $type, $kind, $tool, $assoc );
        if ( is_string( $meta ) ) {
            return $meta;
        }
        $args = array_merge( self::target_arguments( $mode, $kind, $base, $post_id ), self::field_values( $mode, $assoc ), $terms, $meta );
        if ( 'create' === $mode ) {
            $args = self::create_title( $type, $args );
        }
        return is_array( $args ) ? array( $tool, $args ) : $args;
    }

    





    private static function target_arguments( string $mode, string $kind, string $base, ?int $post_id ): array {
        $args = array();
        if ( 'cpt' === $kind ) {
            $args['rest_base'] = $base;
        }
        if ( 'update' === $mode ) {
            $id_key                   = array(
                'post' => 'post_id',
                'page' => 'page_id',
                'cpt'  => 'item_id',
            );
            $args[ $id_key[ $kind ] ] = (int) $post_id;
        }
        return $args;
    }

    





    private static function field_refusals( string $mode, string $type, string $kind, string $tool, array $assoc ): ?string {
        foreach ( self::FIELDS as $field => $spec ) {
            if ( ! array_key_exists( $field, $assoc ) ) {
                continue;
            }
            $refusal = self::field_refusal( $mode, $type, $kind, $tool, $field, $assoc[ $field ] );
            if ( null !== $refusal ) {
                return $refusal;
            }
        }
        return null;
    }

    







    private static function field_refusal( string $mode, string $type, string $kind, string $tool, string $field, $value ): ?string {
        if ( ! is_string( $value ) ) {
            return sprintf( self::MSG_NEEDS_VALUE, $field, true === $value ? "'1'" : 'an empty value' );
        }
        $refusal = self::field_support( $field, self::FIELDS[ $field ][1], $kind, $type, $tool );
        if ( null !== $refusal ) {
            return $refusal;
        }
        if ( '' === $value ) {
            return self::empty_refusal( $mode, $field, $tool );
        }
        if ( in_array( $field, self::INT_FIELDS, true ) && ! preg_match( '/^[0-9]+\z/', $value ) ) {
            return sprintf( self::MSG_NOT_INT, $field, "'" . $value . "'" );
        }
        if ( 'post_title' === $field && function_exists( 'sanitize_text_field' ) && \sanitize_text_field( $value ) !== $value ) {
            return sprintf( self::MSG_TITLE_CLEANED, $tool );
        }
        return null;
    }

    







    private static function empty_refusal( string $mode, string $field, string $tool ): ?string {
        if ( ( 'update' === $mode && isset( self::CLEARABLE[ $field ] ) ) || 'post_password' === $field ) {
            return null;
        }
        if ( 'update' === $mode || in_array( $field, self::INT_FIELDS, true ) ) {
            return sprintf( self::MSG_EMPTY_KEEP, $field, $tool );
        }
        
        
        
        return null;
    }

    







    private static function field_values( string $mode, array $assoc ): array {
        $args  = array();
        $clear = array();
        foreach ( self::FIELDS as $field => $spec ) {
            if ( ! array_key_exists( $field, $assoc ) ) {
                continue;
            }
            $value = (string) $assoc[ $field ];
            if ( '' === $value ) {
                if ( 'update' === $mode && isset( self::CLEARABLE[ $field ] ) ) {
                    $clear[] = self::CLEARABLE[ $field ];
                } elseif ( 'post_password' === $field ) {
                    $args['password'] = '';
                }
                continue;
            }
            $args[ $spec[0] ] = in_array( $field, self::INT_FIELDS, true ) ? (int) $value : $value;
        }
        if ( $clear ) {
            $args['clear'] = $clear;
        }
        return $args;
    }

    






    private static function term_arguments( string $mode, string $type, string $kind, string $tool, array $assoc ) {
        $args = array();
        if ( array_key_exists( 'post_category', $assoc ) ) {
            $args = self::category_arguments( $type, $kind, $assoc['post_category'] );
            if ( is_string( $args ) ) {
                return $args;
            }
        }
        if ( array_key_exists( 'tags_input', $assoc ) ) {
            $tags = self::tag_arguments( $mode, $type, $kind, $tool, $assoc['tags_input'] );
            if ( is_string( $tags ) ) {
                return $tags;
            }
            $args = array_merge( $args, $tags );
        }
        return $args;
    }

    







    private static function category_arguments( string $type, string $kind, $value ) {
        $categories = self::category_ids( $value );
        if ( is_string( $categories ) ) {
            return $categories;
        }
        if ( 'post' === $kind ) {
            return array( 'categories' => $categories );
        }
        if ( self::type_has_rest_taxonomy( $type, 'category' ) ) {
            return array( 'tax_input' => array( 'category' => $categories ) );
        }
        return sprintf( self::MSG_NO_CATEGORIES, $type );
    }

    







    private static function tag_arguments( string $mode, string $type, string $kind, string $tool, $value ) {
        if ( 'post' !== $kind ) {
            return sprintf( self::MSG_TAGS_ONLY_POSTS, $tool, $type );
        }
        if ( ! is_string( $value ) ) {
            return sprintf( self::MSG_NEEDS_VALUE, 'tags_input', 'no tags' );
        }
        $names = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
        if ( $names ) {
            return array( 'tag_names' => $names );
        }
        return 'update' === $mode ? array( 'tags' => array() ) : array();
    }

    






    private static function meta_arguments( string $type, string $kind, string $tool, array $assoc ) {
        if ( ! array_key_exists( 'meta_input', $assoc ) ) {
            return array();
        }
        $meta = self::meta_object( $assoc['meta_input'] );
        if ( null === $meta ) {
            return self::MSG_META_NOT_JSON;
        }
        if ( 'cpt' === $kind && ! \post_type_supports( $type, 'custom-fields' ) ) {
            return sprintf( self::MSG_NO_SUPPORT, 'meta_input', $type, 'custom-fields' );
        }
        $rest_keys = self::rest_meta_keys( $type );
        foreach ( array_keys( $meta ) as $key ) {
            if ( ! isset( $rest_keys[ (string) $key ] ) ) {
                return sprintf( self::MSG_META_NOT_REST, (string) $key, $type, $tool, (string) $key );
            }
        }
        return $meta ? array( 'meta' => $meta ) : array();
    }

    






    private static function meta_object( $value ): ?array {
        $meta = is_string( $value ) && self::is_json( $value ) ? json_decode( $value, true ) : null;
        if ( ! is_array( $meta ) || ( $meta && array_keys( $meta ) === range( 0, count( $meta ) - 1 ) ) ) {
            return null;
        }
        return $meta;
    }

    







    private static function create_title( string $type, array $args ) {
        $title = isset( $args['title'] ) ? (string) $args['title'] : '';
        if ( '' !== $title ) {
            return $args;
        }
        if ( ! isset( $args['content'] ) && ! isset( $args['excerpt'] ) && \post_type_supports( $type, 'editor' ) && \post_type_supports( $type, 'title' ) && \post_type_supports( $type, 'excerpt' ) ) {
            return 'Content, title, and excerpt are empty.';
        }
        $args['allow_empty_title'] = true;
        return $args;
    }

    


    private static function field_support( string $field, string $support, string $kind, string $type, string $tool ): ?string {
        if ( 'post' === $kind && in_array( $field, array( 'post_parent', 'menu_order' ), true ) ) {
            return sprintf( self::MSG_NO_ARGUMENT, $field, $type, $tool, 'post_parent' === $field ? 'parent' : 'menu_order' );
        }
        if ( 'cpt' !== $kind || '' === $support ) {
            return null;
        }
        if ( 'hierarchical' === $support ) {
            $object = \get_post_type_object( $type );
            return ( is_object( $object ) && ! empty( $object->hierarchical ) ) ? null : sprintf( self::MSG_NO_SUPPORT, $field, $type, 'hierarchical' );
        }
        return \post_type_supports( $type, $support ) ? null : sprintf( self::MSG_NO_SUPPORT, $field, $type, $support );
    }

    







    private static function update_call( int $post_id, array $fields, string $command, ?Context $ctx ): array {
        $post = $post_id > 0 ? \get_post( $post_id ) : null;
        if ( ! $post ) {
            
            return array( 'response' => Db_Object_Command::missing( 'Invalid post ID.' ) );
        }
        $type   = (string) $post->post_type;
        $target = self::write_target( $type, $command );
        if ( ! is_array( $target ) ) {
            return array( 'response' => Db_Object_Command::failure( $target ) );
        }
        $tool = self::TOOLS['update'][ $target[0] ];
        if ( null !== $ctx && ! $ctx->may_use( $tool ) ) {
            return array( 'response' => Db_Object_Command::failure( Fetchers::tool_unavailable( $ctx, $command, $tool ) ) );
        }
        $call = self::write_call( 'update', $type, $target, $fields, (int) $post->ID );
        return is_array( $call ) ? array( 'call' => $call ) : array( 'response' => Db_Object_Command::failure( $call ) );
    }

    





    private static function delete_call( string $post_id, bool $force, string $command, ?Context $ctx ): array {
        $post = \get_post( (int) $post_id );
        if ( ! $post ) {
            
            
            
            
            
            
            
            
            return Db_Object_Command::missing( $force ? "Failed deleting post {$post_id}." : sprintf( Fetchers::MESSAGES['post'], $post_id ) );
        }
        $status    = (string) $post->post_status;
        $post_type = (string) $post->post_type;
        if ( ! $force && 'post' !== $post_type && 'page' !== $post_type ) {
            return array(
                'error',
                "Posts of type '{$post_type}' do not support being sent to trash.\n"
                . 'Please use the --force flag to skip trash and delete them permanently.',
            );
        }

        $call = self::delete_tool_call( $post, $force || 'trash' === $status, $command );
        if ( is_string( $call ) ) {
            return array( 'error', $call );
        }
        if ( null !== $ctx && ! $ctx->may_use( $call[0] ) ) {
            return array( 'error', Fetchers::tool_unavailable( $ctx, $command, $call[0] ) );
        }
        return array(
            'tool'      => $call[0],
            'arguments' => $call[1],
            'action'    => self::delete_action( $force, $status, $post_type ),
        );
    }

    










    private static function delete_tool_call( object $post, bool $force_or_trashed, string $command ) {
        $permanent = $force_or_trashed || ( defined( 'EMPTY_TRASH_DAYS' ) && ! EMPTY_TRASH_DAYS );
        $id        = (int) $post->ID;
        $post_type = (string) $post->post_type;
        if ( 'post' === $post_type ) {
            return array( 'wp_delete_post', array( 'post_id' => $id, 'force' => $permanent ) );
        }
        if ( 'page' === $post_type ) {
            return array( 'wp_delete_page', array( 'page_id' => $id, 'force' => $permanent ) );
        }
        if ( 'attachment' === $post_type ) {
            return array( 'wp_delete_media', array( 'media_id' => $id, 'force' => true ) );
        }
        if ( 'revision' === $post_type ) {
            return array( 'wp_delete_revision', array( 'post_id' => (int) $post->post_parent, 'revision_id' => $id ) );
        }
        $target = self::write_target( $post_type, $command );
        if ( ! is_array( $target ) ) {
            return $target;
        }
        return array( 'wp_delete_cpt_item', array( 'rest_base' => $target[1], 'item_id' => $id, 'force' => true ) );
    }

    






    private static function delete_action( bool $force, string $status, string $post_type ): string {
        return $force || 'trash' === $status || 'revision' === $post_type ? 'Deleted' : 'Trashed';
    }

    







    private static function report_saved( Context $ctx, array $call, array $payload, int $id, array $assoc ): void {
        $creating = 0 === strpos( $call[0], 'wp_create_' );
        if ( $creating && isset( $call[1]['status'], $payload['status'] ) && is_string( $payload['status'] ) && $payload['status'] !== $call[1]['status'] && self::force_draft_on() ) {
            $ctx->out->warning( sprintf( self::MSG_FORCE_DRAFT, $id, $payload['status'], (string) $call[1]['status'] ) );
        }
        if ( ! empty( $payload['meta_ignored'] ) && is_array( $payload['meta_ignored'] ) ) {
            $ctx->out->warning( sprintf( self::MSG_META_DROPPED, $id, $call[0], implode( ', ', array_map( 'strval', $payload['meta_ignored'] ) ) ) );
        }
    }

    
    private static function force_draft_on(): bool {
        return class_exists( '\\Easy_MCP_AI\\Config' ) && (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_force_draft_on_create', false );
    }

    







    private static function update_ids( Context $ctx, array $args ): array {
        $ids = array();
        foreach ( array_values( $args ) as $arg ) {
            if ( ! is_numeric( $arg ) ) {
                Read_Value::refuse_stdin_marker( $ctx->out, (string) $arg, 'file' );
                $ctx->out->error( sprintf( self::MSG_UPDATE_FILE, (string) $arg ) );
            }
            
            
            Ids::require_digits( $ctx, $arg, 'a post ID' );
            $ids[] = (string) $arg;
        }
        return $ids;
    }

    








    private static function unsafe_term_names( array $assoc ): ?string {
        if ( isset( $assoc['tags_input'] ) && is_string( $assoc['tags_input'] ) && self::has_unsafe_name( $assoc['tags_input'] ) ) {
            return self::MSG_UNSAFE_TAG;
        }
        if ( isset( $assoc['post_category'] ) && is_string( $assoc['post_category'] ) && self::has_unsafe_name( $assoc['post_category'] ) ) {
            return self::MSG_UNSAFE_CATEGORY;
        }
        return null;
    }

    



    private static function has_unsafe_name( string $list ): bool {
        foreach ( explode( ',', $list ) as $name ) {
            if ( Meta_Command::unsafe_key( trim( $name ) ) ) {
                return true;
            }
        }
        return false;
    }

    




    private static function post_type_argument( Context $ctx, array $assoc ): string {
        if ( ! array_key_exists( 'post_type', $assoc ) ) {
            return 'post';
        }
        if ( ! is_string( $assoc['post_type'] ) || '' === $assoc['post_type'] ) {
            $ctx->out->error( sprintf( self::MSG_NEEDS_VALUE, 'post_type', 'no post type' ) );
        }
        return (string) $assoc['post_type'];
    }

    







    private static function refuse_unknown_fields( Context $ctx, array $assoc, string $mode ): void {
        $errors = array();
        foreach ( array_keys( $assoc ) as $key ) {
            $key = (string) $key;
            if ( isset( self::FIELDS[ $key ] ) || in_array( $key, self::CONTROL_KEYS[ $mode ], true ) ) {
                continue;
            }
            $errors[] = sprintf( self::MSG_PARAM, $key, self::MSG_NOT_A_FIELD );
        }
        if ( $errors ) {
            $ctx->out->error( sprintf( self::MSG_PARAM_FRAME, implode( "\n", $errors ) ) );
        }
    }

    







    private static function category_ids( $arg ) {
        if ( ! is_string( $arg ) ) {
            return sprintf( self::MSG_NEEDS_VALUE, 'post_category', 'no category' );
        }
        $ids = array();
        foreach ( explode( ',', $arg ) as $post_category ) {
            if ( ! trim( $post_category ) ) {
                continue;
            }
            if ( is_numeric( $post_category ) && (int) $post_category ) {
                $found = \term_exists( (int) $post_category, 'category' );
            } else {
                $found = \term_exists( $post_category, 'category' );
            }
            $id = is_array( $found ) ? (int) $found['term_id'] : (int) $found;
            if ( ! $id ) {
                return "No such post category '{$post_category}'.";
            }
            $ids[] = $id;
        }
        if ( ! $ids ) {
            
            
            return sprintf( self::MSG_NEEDS_VALUE, 'post_category', 'the default category' );
        }
        return $ids;
    }

    
    private static function type_has_rest_taxonomy( string $type, string $taxonomy ): bool {
        if ( ! in_array( $taxonomy, (array) \get_object_taxonomies( $type, 'names' ), true ) ) {
            return false;
        }
        $object = \get_taxonomy( $taxonomy );
        return is_object( $object ) && ! empty( $object->show_in_rest );
    }

    






    private static function rest_meta_keys( string $type ): array {
        $keys = array();
        if ( ! function_exists( 'get_registered_meta_keys' ) ) {
            return $keys;
        }
        foreach ( array( '', $type ) as $subtype ) {
            foreach ( (array) \get_registered_meta_keys( 'post', $subtype ) as $key => $args ) {
                if ( is_array( $args ) && ! empty( $args['show_in_rest'] ) ) {
                    $keys[ (string) $key ] = true;
                }
            }
        }
        return $keys;
    }

    




    public static function is_rest_meta_key( string $type, string $key ): bool {
        return isset( self::rest_meta_keys( $type )[ $key ] );
    }

    




    private static function caller_may_use( string $tool ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) || ! method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' ) ) {
            return true;
        }
        return false !== \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool );
    }

    
    private static function is_json( string $argument ): bool {
        if ( '' === $argument || ! in_array( $argument[0], array( '{', '[' ), true ) ) {
            return false;
        }
        json_decode( $argument, true );
        return JSON_ERROR_NONE === json_last_error();
    }

    
    
    

    







    private static function list_arguments( array $query, array $target, string $tool ) {
        $refusal = self::list_var_refusal( $query, $target[0], $tool );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $args = self::list_filter_arguments( $query, $target );
        if ( is_string( $args ) ) {
            return $args;
        }
        $paging = self::list_paging( $query );
        if ( is_string( $paging ) ) {
            return $paging;
        }
        return array( $args, $paging );
    }

    





    private static function list_var_refusal( array $query, string $kind, string $tool ): ?string {
        if ( array_key_exists( 'name', $query ) ) {
            return self::MSG_LIST_SINGLE;
        }
        $type   = isset( $query['post_type'] ) && is_string( $query['post_type'] ) ? $query['post_type'] : 'post';
        $errors = array();
        foreach ( array_keys( $query ) as $key ) {
            $key = (string) $key;
            if ( ! isset( self::LIST_VARS[ $key ] ) ) {
                $errors[] = sprintf( self::MSG_PARAM, $key, sprintf( self::MSG_LIST_VAR, $tool ) );
            } elseif ( 'media' === $kind && 's' === $key ) {
                $errors[] = sprintf( self::MSG_PARAM, $key, self::MSG_MEDIA_SEARCH );
            } elseif ( ! in_array( $kind, self::LIST_VARS[ $key ], true ) ) {
                $errors[] = sprintf( self::MSG_PARAM, $key, sprintf( self::MSG_LIST_KIND, $tool, $key, $type ) );
            }
        }
        return $errors ? sprintf( self::MSG_PARAM_FRAME, implode( "\n", $errors ) ) : null;
    }

    







    private static function list_filter_arguments( array $query, array $target ) {
        $args = array(
            'context'     => 'edit',
            'raw_columns' => true,
        );
        if ( 'cpt' === $target[0] ) {
            $args['rest_base'] = $target[1];
        }
        $status = self::list_status_arguments( $query, $target[0] );
        if ( is_string( $status ) ) {
            return $status;
        }
        $author = self::list_author_arguments( $query );
        if ( is_string( $author ) ) {
            return $author;
        }
        $ids = self::list_id_arguments( $query );
        if ( is_string( $ids ) ) {
            return $ids;
        }
        $names = self::list_name_parent_arguments( $query );
        if ( is_string( $names ) ) {
            return $names;
        }
        $values = self::list_value_arguments( $query );
        if ( is_string( $values ) ) {
            return $values;
        }
        $clauses = self::list_clause_arguments( $query );
        if ( is_string( $clauses ) ) {
            return $clauses;
        }
        $order = self::list_order_arguments( $query );
        if ( is_string( $order ) ) {
            return $order;
        }
        if ( 'media' === $target[0] ) {
            $values = self::media_arguments( $query, $values, $order );
            if ( is_string( $values ) ) {
                return $values;
            }
        }
        return array_merge( $args, $status, $author, $ids, $names, $values, $clauses, $order );
    }

    













    private static function media_arguments( array $query, array $values, array $order ) {
        if ( isset( $order['orderby'] ) && ! in_array( $order['orderby'], self::MEDIA_ORDERBY, true ) ) {
            return sprintf( self::MSG_LIST_VALUE, 'orderby', self::text( $query['orderby'] ), self::MSG_MEDIA_ORDERBY );
        }
        if ( ! isset( $values['post_mime_type'] ) ) {
            return array();
        }
        if ( '' === trim( (string) $values['post_mime_type'] ) ) {
            return array();
        }
        
        
        
        $types = array_map( 'trim', explode( ',', (string) $values['post_mime_type'] ) );
        if ( in_array( '', $types, true ) ) {
            return sprintf( self::MSG_LIST_VALUE, 'post_mime_type', self::text( $query['post_mime_type'] ), self::MSG_MEDIA_MIME_EMPTY );
        }
        if ( count( $types ) > 1 && ! self::media_takes_mime_list() ) {
            return sprintf( self::MSG_LIST_VALUE, 'post_mime_type', self::text( $query['post_mime_type'] ), self::MSG_MEDIA_MIME_ONE );
        }
        $allowed = array_values( \get_allowed_mime_types() );
        foreach ( $types as $type ) {
            if ( ! in_array( $type, $allowed, true ) ) {
                return sprintf( self::MSG_LIST_VALUE, 'post_mime_type', self::text( $query['post_mime_type'] ), self::MSG_MEDIA_MIME );
            }
        }
        return array( 'mime_type' => implode( ',', $types ) );
    }

    






    private static function list_status_arguments( array $query, string $kind ) {
        $status = isset( $query['post_status'] ) ? $query['post_status'] : 'any';
        if ( ! is_string( $status ) || '' === trim( $status ) ) {
            return sprintf( self::MSG_LIST_VALUE, 'post_status', is_string( $status ) ? "''" : 'true', 'WP_Query reads a status name or a comma-separated list' );
        }
        $statuses = array_values( array_filter( array_map( 'trim', explode( ',', $status ) ), 'strlen' ) );
        if ( 'post' === $kind ) {
            $one = 1 === count( $statuses ) && in_array( $statuses[0], array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ), true );
            return $one ? array( 'status' => $statuses[0] ) : array( 'statuses' => $statuses );
        }
        if ( 'page' === $kind ) {
            return 1 === count( $statuses ) ? array( 'status' => $statuses[0] ) : sprintf( self::MSG_LIST_VALUE, 'post_status', $status, 'wp_list_pages takes one status at a time' );
        }
        return array( 'status' => implode( ',', $statuses ) );
    }

    






    private static function list_author_arguments( array $query ) {
        $args = array();
        if ( array_key_exists( 's', $query ) ) {
            if ( ! is_string( $query['s'] ) ) {
                return sprintf( self::MSG_LIST_VALUE, 's', 'true', 'a search needs words' );
            }
            if ( '' !== $query['s'] ) {
                $args['search'] = $query['s'];
            }
        }
        $author = self::list_author( $query );
        if ( is_string( $author ) ) {
            return $author;
        }
        if ( null !== $author ) {
            $args['author'] = $author;
        }
        return $args;
    }

    






    private static function list_author( array $query ) {
        $author = null;
        if ( isset( $query['author'] ) ) {
            if ( ! is_string( $query['author'] ) || ! preg_match( '/^[1-9][0-9]*\z/', $query['author'] ) ) {
                return sprintf( self::MSG_LIST_VALUE, 'author', is_string( $query['author'] ) ? $query['author'] : 'true', 'the list tools take one author ID; use --author__not_in to leave authors out' );
            }
            $author = (int) $query['author'];
        }
        if ( isset( $query['author__in'] ) ) {
            $in = self::id_list( $query['author__in'] );
            if ( null === $in || 1 !== count( $in ) || ( null !== $author && $author !== $in[0] ) ) {
                return sprintf( self::MSG_LIST_VALUE, 'author__in', self::text( $query['author__in'] ), 'the list tools take one author ID' );
            }
            $author = $in[0];
        }
        return $author;
    }

    






    private static function list_id_arguments( array $query ) {
        $args = array();
        foreach ( self::LIST_ID_LISTS as $var => $arg ) {
            if ( isset( $query[ $var ] ) ) {
                $ids = self::id_list( $query[ $var ], 'post_parent__not_in' === $var );
                if ( null === $ids ) {
                    return sprintf( self::MSG_LIST_VALUE, $var, self::text( $query[ $var ] ), 'it takes a comma-separated list of IDs' );
                }
                $args[ $arg ] = $ids;
            }
        }
        foreach ( array( 'cat' => 'categories', 'tag_id' => 'tags' ) as $var => $arg ) {
            if ( isset( $query[ $var ] ) ) {
                $ids = self::id_list( $query[ $var ] );
                if ( null === $ids || isset( $args[ $arg ] ) ) {
                    return sprintf( self::MSG_LIST_VALUE, $var, self::text( $query[ $var ] ), 'wp_cli_run maps only positive IDs, and only one of cat/category__in or tag_id/tag__in' );
                }
                $args[ $arg ] = $ids;
            }
        }
        return $args;
    }

    





    private static function list_name_parent_arguments( array $query ) {
        $args = array();
        if ( isset( $query['post_name__in'] ) ) {
            $slugs = is_array( $query['post_name__in'] ) ? array_values( array_filter( array_map( 'strval', $query['post_name__in'] ), 'strlen' ) ) : array();
            if ( ! $slugs ) {
                return sprintf( self::MSG_LIST_VALUE, 'post_name__in', self::text( $query['post_name__in'] ), 'it takes a comma-separated list of slugs' );
            }
            $args['slug'] = $slugs;
        }
        if ( isset( $query['post_parent'] ) ) {
            if ( ! is_string( $query['post_parent'] ) || ! preg_match( '/^[0-9]+\z/', $query['post_parent'] ) ) {
                return sprintf( self::MSG_LIST_VALUE, 'post_parent', self::text( $query['post_parent'] ), 'it takes one parent ID' );
            }
            $args['parent'] = (int) $query['post_parent'];
        }
        return $args;
    }

    







    private static function list_value_arguments( array $query ) {
        $args = array();
        foreach ( array( 'comment_count', 'year', 'monthnum', 'day' ) as $var ) {
            if ( isset( $query[ $var ] ) ) {
                if ( ! is_string( $query[ $var ] ) || ! preg_match( '/^[0-9]+\z/', $query[ $var ] ) ) {
                    return sprintf( self::MSG_LIST_VALUE, $var, self::text( $query[ $var ] ), 'it takes a whole number' );
                }
                $args[ $var ] = (int) $query[ $var ];
            }
        }
        foreach ( array( 'post_mime_type', 'meta_key', 'meta_value', 'meta_compare' ) as $var ) {
            if ( isset( $query[ $var ] ) ) {
                if ( ! is_string( $query[ $var ] ) ) {
                    return sprintf( self::MSG_LIST_VALUE, $var, 'true', 'it takes a value' );
                }
                $args[ $var ] = $query[ $var ];
            }
        }
        return $args;
    }

    






    private static function list_clause_arguments( array $query ) {
        $args = array();
        if ( array_key_exists( 'has_password', $query ) ) {
            if ( ! is_bool( $query['has_password'] ) ) {
                return sprintf( self::MSG_LIST_VALUE, 'has_password', self::text( $query['has_password'] ), sprintf( self::MSG_LIST_STRING_BOOL, 'has_password' ) );
            }
            $args['has_password'] = $query['has_password'];
        }
        foreach ( array( 'date_query', 'tax_query', 'meta_query' ) as $var ) {
            if ( isset( $query[ $var ] ) ) {
                if ( ! is_array( $query[ $var ] ) ) {
                    return sprintf( self::MSG_LIST_VALUE, $var, self::text( $query[ $var ] ), 'it takes a JSON array of clauses' );
                }
                $args[ $var ] = $query[ $var ];
            }
        }
        return $args;
    }

    






    private static function list_order_arguments( array $query ) {
        $args = array();
        if ( isset( $query['orderby'] ) ) {
            $orderby = is_string( $query['orderby'] ) ? $query['orderby'] : '';
            if ( ! isset( self::LIST_ORDERBY[ $orderby ] ) ) {
                return sprintf( self::MSG_LIST_VALUE, 'orderby', self::text( $query['orderby'] ), 'wp_cli_run maps one of ' . implode( ', ', array_keys( self::LIST_ORDERBY ) ) );
            }
            $args['orderby'] = self::LIST_ORDERBY[ $orderby ];
        }
        if ( isset( $query['order'] ) ) {
            $order = is_string( $query['order'] ) ? strtolower( $query['order'] ) : '';
            if ( 'asc' !== $order && 'desc' !== $order ) {
                return sprintf( self::MSG_LIST_VALUE, 'order', self::text( $query['order'] ), 'it takes ASC or DESC' );
            }
            $args['order'] = $order;
        }
        return $args;
    }

    







    private static function list_paging( array $query ) {
        $limit = self::list_limit( $query );
        if ( is_string( $limit ) ) {
            return $limit;
        }
        if ( -1 === $limit ) {
            return self::list_unpaged( $query );
        }
        $page = self::list_whole( $query, 'paged', 'it takes a page number' );
        if ( is_string( $page ) ) {
            return $page;
        }
        $page   = null === $page ? 1 : max( 1, $page );
        $offset = self::list_whole( $query, 'offset', 'it takes a whole number' );
        if ( is_string( $offset ) ) {
            return $offset;
        }
        return array(
            'start' => null === $offset ? ( $page - 1 ) * $limit : $offset,
            'limit' => $limit,
            'page'  => $page,
        );
    }

    







    private static function list_limit( array $query ) {
        if ( ! array_key_exists( 'posts_per_page', $query ) ) {
            return -1;
        }
        $ppp = $query['posts_per_page'];
        if ( is_string( $ppp ) && ( '' === $ppp || '0' === $ppp ) ) {
            return max( 1, (int) \get_option( 'posts_per_page', 10 ) );
        }
        if ( is_string( $ppp ) && preg_match( '/^-?[0-9]+\z/', $ppp ) ) {
            $limit = (int) $ppp;
            return $limit < -1 ? abs( $limit ) : $limit;
        }
        return sprintf( self::MSG_LIST_VALUE, 'posts_per_page', self::text( $ppp ), 'it takes a whole number, or -1 for every post' );
    }

    






    private static function list_unpaged( array $query ) {
        foreach ( array( 'paged', 'offset' ) as $var ) {
            if ( array_key_exists( $var, $query ) ) {
                return sprintf( self::MSG_LIST_PAGING, $var );
            }
        }
        return array(
            'start' => 0,
            'limit' => -1,
            'page'  => 1,
        );
    }

    






    private static function list_whole( array $query, string $var, string $why ) {
        if ( ! array_key_exists( $var, $query ) ) {
            return null;
        }
        if ( ! is_string( $query[ $var ] ) || ! preg_match( '/^[0-9]+\z/', $query[ $var ] ) ) {
            return sprintf( self::MSG_LIST_VALUE, $var, self::text( $query[ $var ] ), $why );
        }
        return (int) $query[ $var ];
    }

    







    private static function list_page( Context $ctx, string $tool, string $kind, array $args, int $offset, int $per_page ): array {
        $args['per_page'] = $per_page;
        if ( $offset > 0 ) {
            $args['offset'] = $offset;
        }
        $result = $ctx->delegate( $tool, $args );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $rows = self::page_rows( $ctx, $tool, $kind, $result['payload'] );
        return array(
            'rows'  => $rows,
            'total' => isset( $result['payload']['total'] ) ? (int) $result['payload']['total'] : count( $rows ),
        );
    }

    







    private static function page_rows( Context $ctx, string $tool, string $kind, array $payload ): array {
        $list_key = array(
            'post'  => 'posts',
            'page'  => 'pages',
            'media' => 'media',
            'cpt'   => 'items',
        );
        $items    = isset( $payload[ $list_key[ $kind ] ] ) && is_array( $payload[ $list_key[ $kind ] ] ) ? $payload[ $list_key[ $kind ] ] : null;
        if ( null === $items ) {
            $ctx->out->error( sprintf( self::MSG_INNER_SHAPE, $tool, $list_key[ $kind ] ) );
        }
        $rows = array();
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['raw'] ) || ! is_array( $item['raw'] ) ) {
                $ctx->out->error( sprintf( self::MSG_INNER_SHAPE, $tool, 'raw columns' ) );
            }
            $row        = $item['raw'];
            $row['url'] = isset( $item['link'] ) && is_string( $item['link'] ) ? $item['link'] : \get_permalink( (int) $row['ID'] );
            $rows[]     = $row;
        }
        return $rows;
    }

    

















    private static function list_rows( Context $ctx, string $tool, string $kind, array $args, array $paging ): array {
        $asked    = -1 === $paging['limit'] ? self::PAGE_SIZE : max( 1, min( self::PAGE_SIZE, $paging['limit'] ) );
        $first    = self::list_page( $ctx, $tool, $kind, $args, $paging['start'], $asked );
        $expected = self::rows_expected( $first['total'], $paging );
        if ( $expected > self::ROW_CAP ) {
            $ctx->out->error( sprintf( self::MSG_LIST_CAP, self::ROW_CAP, $expected ) );
        }
        $rows = array_slice( $first['rows'], 0, min( $asked, $expected ) );
        for ( $window = $asked; $window < $expected; $window += $asked ) {
            $asked = min( self::PAGE_SIZE, $expected - $window );
            $page  = self::list_page( $ctx, $tool, $kind, $args, $paging['start'] + $window, $asked );
            $rows  = array_merge( $rows, array_slice( $page['rows'], 0, $asked ) );
        }
        return $rows;
    }

    




    private static function rows_expected( int $total, array $paging ): int {
        $left = max( 0, $total - $paging['start'] );
        return -1 === $paging['limit'] ? $left : min( $paging['limit'], $left );
    }

    







    private static function is_home( array $query ): bool {
        foreach ( self::NOT_HOME_VARS as $var ) {
            if ( 's' === $var ? array_key_exists( 's', $query ) : ! empty( $query[ $var ] ) ) {
                return false;
            }
        }
        if ( ! empty( $query['author'] ) && '0' !== (string) $query['author'] ) {
            return false;
        }
        foreach ( array( 'cat', 'category__in', 'tag_id', 'tag__in' ) as $var ) {
            if ( ! empty( $query[ $var ] ) ) {
                return false;
            }
        }
        return ! self::has_tax_clause( $query );
    }

    





    private static function has_tax_clause( array $query ): bool {
        if ( ! isset( $query['tax_query'] ) || ! is_array( $query['tax_query'] ) ) {
            return false;
        }
        foreach ( $query['tax_query'] as $key => $clause ) {
            if ( 'relation' === $key || ! is_array( $clause ) || ! isset( $clause['taxonomy'] ) ) {
                continue;
            }
            $operator = isset( $clause['operator'] ) ? strtoupper( (string) $clause['operator'] ) : 'IN';
            if ( 'NOT IN' !== $operator ) {
                return true;
            }
        }
        return false;
    }

    









    private static function move_sticky_posts( Context $ctx, array $rows, array $query ): array {
        $sticky_posts = \get_option( 'sticky_posts' );
        if ( ! is_array( $sticky_posts ) || ! $sticky_posts ) {
            return $rows;
        }
        $num_posts     = count( $rows );
        $sticky_offset = 0;
        for ( $i = 0; $i < $num_posts; $i++ ) {
            if ( in_array( (int) $rows[ $i ]['ID'], $sticky_posts, true ) ) {
                $sticky_post = $rows[ $i ];
                array_splice( $rows, $i, 1 );
                array_splice( $rows, $sticky_offset, 0, array( $sticky_post ) );
                ++$sticky_offset;
                $offset = array_search( (int) $sticky_post['ID'], $sticky_posts, true );
                unset( $sticky_posts[ $offset ] );
            }
        }
        if ( $sticky_posts && ! empty( $query['post__not_in'] ) ) {
            $sticky_posts = array_diff( $sticky_posts, array_map( 'intval', (array) $query['post__not_in'] ) );
        }
        if ( $sticky_posts ) {
            $page = self::list_page(
                $ctx,
                'wp_list_posts',
                'post',
                array(
                    'context'     => 'edit',
                    'raw_columns' => true,
                    'status'      => 'publish',
                    'include'     => array_values( array_map( 'intval', $sticky_posts ) ),
                ),
                0,
                min( self::PAGE_SIZE, count( $sticky_posts ) )
            );
            foreach ( $page['rows'] as $sticky_post ) {
                array_splice( $rows, $sticky_offset, 0, array( $sticky_post ) );
                ++$sticky_offset;
            }
        }
        return array_values( $rows );
    }

    









    private static function id_list( $value, bool $zero = false ): ?array {
        if ( is_string( $value ) ) {
            $value = explode( ',', $value );
        }
        if ( ! is_array( $value ) || ! $value ) {
            return null;
        }
        $ids = array();
        foreach ( $value as $entry ) {
            $entry = trim( (string) $entry );
            if ( ! preg_match( $zero ? '/^(0|[1-9][0-9]*)\z/' : '/^[1-9][0-9]*\z/', $entry ) ) {
                return null;
            }
            $ids[] = (int) $entry;
        }
        return $ids;
    }

    





    private static function text( $value ): string {
        if ( is_string( $value ) ) {
            return $value;
        }
        return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
    }
}
