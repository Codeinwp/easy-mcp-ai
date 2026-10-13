<?php
namespace Easy_MCP_AI\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





















class Rebuild_Planner {

    
    const MAX_CALLS = 50;

    
    const NEW_ID = '{new_id}';

    
    const SITE_SETTINGS = array(
        'blogname'        => 'title',
        'blogdescription' => 'description',
        'timezone_string' => 'timezone',
        'date_format'     => 'date_format',
        'time_format'     => 'time_format',
        'posts_per_page'  => 'posts_per_page',
        'show_on_front'   => 'show_on_front',
        'page_on_front'   => 'page_on_front',
        'page_for_posts'  => 'page_for_posts',
    );

    




    const FRONT_PAGE_OPTIONS = array( 'show_on_front', 'page_on_front', 'page_for_posts' );

    
    const PAGE_OPTIONS = array( 'page_on_front', 'page_for_posts' );

    
    const INTEGER_SETTINGS = array( 'posts_per_page', 'page_on_front', 'page_for_posts' );

    
    const UNSUPPORTED_POST_TYPES = array(
        'revision', 'nav_menu_item', 'wp_template', 'wp_template_part', 'wp_global_styles',
        'wp_navigation', 'wp_block', 'customize_changeset', 'oembed_cache', 'user_request',
        'wp_font_family', 'wp_font_face', 'product', 'product_variation', 'shop_order',
        'shop_order_refund', 'shop_coupon',
    );

    
    private $new_ids = array( 'post' => array(), 'term' => array(), 'comment' => array() );

    
    private $own_term_rows = array();

    



    private $recreated_post_types = array();

    
    private $deleted_in_call = array();

    
    private $rest_meta_fields = array();

    



    private $role_rows = array();

    





    private $front_page = null;

    


    public function __construct( array $rows = array() ) {
        foreach ( $rows as $row ) {
            if ( 'object_terms' === ( $row['object_type'] ?? '' ) ) {
                $this->own_term_rows[ (string) $row['object_id'] ] = true;
            }
            if ( 'post' === ( $row['object_type'] ?? '' ) && 'delete' === ( $row['action'] ?? '' ) ) {
                $this->deleted_in_call[ (int) $row['object_id'] ] = true;
            }
            if ( 'user' === ( $row['object_type'] ?? '' ) && 'update' === ( $row['action'] ?? '' ) && in_array( 'roles', self::changed( $row ), true ) ) {
                $this->remember_role_row( $row );
            }
            if ( self::is_front_page_row( $row ) ) {
                $this->remember_front_page_row( $row );
            }
        }
    }

    






    private static function is_front_page_row( array $row ): bool {
        if ( 'option' !== ( $row['object_type'] ?? '' ) || 'update' !== ( $row['action'] ?? '' )
            || ! in_array( (string) ( $row['object_id'] ?? '' ), self::FRONT_PAGE_OPTIONS, true )
            || false !== strpos( (string) ( $row['object_subtype'] ?? '' ), ':' ) ) {
            return false;
        }
        $before = self::before( $row );
        return ! isset( $before['value_unavailable'] ) && array_key_exists( 'value', $before )
            && null === self::value_problem( $row, $before['value'] );
    }

    






    private function remember_front_page_row( array $row ): void {
        $id   = (int) $row['id'];
        $name = (string) $row['object_id'];
        if ( null === $this->front_page ) {
            $this->front_page = array( 'rows' => array(), 'before' => array(), 'after' => array(), 'oldest' => array(), 'latest' => array() );
        }
        $group                = &$this->front_page;
        $group['rows'][ $id ] = $name;
        if ( ! isset( $group['oldest'][ $name ] ) || $id < $group['oldest'][ $name ] ) {
            $group['oldest'][ $name ] = $id;
            $group['before'][ $name ] = self::before( $row )['value'];
        }
        if ( ! isset( $group['latest'][ $name ] ) || $id > $group['latest'][ $name ] ) {
            $group['latest'][ $name ] = $id;
            $group['after'][ $name ]  = self::after( $row )['value'] ?? null;
        }
    }

    







    private function remember_role_row( array $row ) {
        $user = (int) $row['object_id'];
        $id   = (int) $row['id'];
        if ( ! isset( $this->role_rows[ $user ] ) ) {
            $this->role_rows[ $user ] = array( 'newest' => $id, 'oldest' => $id, 'roles' => self::roles_of( self::before( $row ) ) );
            return;
        }
        if ( $id > $this->role_rows[ $user ]['newest'] ) {
            $this->role_rows[ $user ]['newest'] = $id;
        }
        if ( $id < $this->role_rows[ $user ]['oldest'] ) {
            $this->role_rows[ $user ]['oldest'] = $id;
            $this->role_rows[ $user ]['roles']  = self::roles_of( self::before( $row ) );
        }
    }

    



    private static function roles_of( array $snapshot ) {
        return array_values( array_filter( (array) ( $snapshot['roles'] ?? array() ), 'is_string' ) );
    }

    
    public function remember_new_id( $kind, $old, $new ) {
        if ( isset( $this->new_ids[ $kind ] ) && (int) $old > 0 && (int) $new > 0 ) {
            $this->new_ids[ $kind ][ (int) $old ] = (int) $new;
        }
    }

    
    public function id( $kind, $old ) {
        return $this->new_ids[ $kind ][ (int) $old ] ?? (int) $old;
    }

    

    







    public function plan( array $row ) {
        $type   = (string) ( $row['object_type'] ?? '' );
        $action = (string) ( $row['action'] ?? '' );

        $incidental = self::incidental_reason( $row );
        if ( null !== $incidental ) {
            return array( 'status' => 'incidental', 'reason' => $incidental, 'calls' => array(), 'not_restored' => array(), 'restore_fields' => array(), 'new_id_kind' => null );
        }

        if ( 'create' === $action ) {
            $delete = self::delete_tool_for( $row );
            return self::skip(
                null === $delete
                    ? __( 'this entry records a creation; wp_history_apply never deletes', 'easy-mcp-ai' )
                    /* translators: %s: a delete tool name */
                    : sprintf( __( 'this entry records a creation; wp_history_apply never deletes — %s removes it', 'easy-mcp-ai' ), $delete )
            );
        }

        switch ( $type ) {
            case 'post':
                return 'delete' === $action ? $this->plan_post_delete( $row ) : $this->plan_post_update( $row );
            case 'meta':
            case 'term_meta':
            case 'user_meta':
                return $this->plan_meta( $row );
            case 'object_terms':
                return $this->plan_object_terms( $row );
            case 'term':
                return 'delete' === $action ? $this->plan_term_delete( $row ) : $this->plan_term_update( $row );
            case 'user':
                if ( 'delete' === $action ) {
                    $reassign = self::before( $row )['reassign'] ?? null;
                    return self::refuse(
                        null === $reassign
                            ? __( 'a deleted user cannot be recreated: the email is never recorded and a new account needs a password', 'easy-mcp-ai' )
                            /* translators: %d: user id */
                            : sprintf( __( 'a deleted user cannot be recreated: the email is never recorded and a new account needs a password. Their content went to user %d', 'easy-mcp-ai' ), (int) $reassign )
                    );
                }
                return $this->plan_user_update( $row );
            case 'comment':
                return $this->plan_comment( $row );
            case 'option':
                return $this->plan_option( $row );
            case 'comment_meta':
                return self::refuse( __( 'no tool writes comment meta', 'easy-mcp-ai' ) );
            case 'site_option':
                return self::refuse( __( 'no tool writes network options', 'easy-mcp-ai' ) );
            case 'wc_product':
            case 'wc_order':
                return self::refuse( __( 'WooCommerce objects are not rebuilt by wp_history_apply yet', 'easy-mcp-ai' ) );
            case 'db_row':
            case 'db_sql':
                return self::refuse( __( 'raw database entries are a record, not something a tool can write back', 'easy-mcp-ai' ) );
            case 'external':
                return self::refuse( __( 'the change happened in another service; nothing local can put it back', 'easy-mcp-ai' ) );
        }
        /* translators: %s: object type */
        return self::refuse( sprintf( __( 'wp_history_apply does not rebuild %s entries', 'easy-mcp-ai' ), $type ) );
    }

    





    public static function count_calls( array $plans ) {
        $n = 0;
        foreach ( $plans as $plan ) {
            $n += 'restore' === ( $plan['status'] ?? '' ) ? count( $plan['calls'] ) : 0;
        }
        return $n;
    }

    

    





    public static function post_tools( $post_type ) {
        if ( 'post' === $post_type ) {
            return array( 'update' => 'wp_update_post', 'create' => 'wp_create_post', 'id_arg' => 'post_id', 'kind' => 'post', 'rest_base' => 'posts' );
        }
        if ( 'page' === $post_type ) {
            return array( 'update' => 'wp_update_page', 'create' => 'wp_create_page', 'id_arg' => 'page_id', 'kind' => 'page', 'rest_base' => 'pages' );
        }
        if ( 'attachment' === $post_type ) {
            return array( 'update' => 'wp_update_media', 'create' => null, 'id_arg' => 'media_id', 'kind' => 'attachment', 'rest_base' => 'media' );
        }
        if ( '' === (string) $post_type || in_array( $post_type, self::UNSUPPORTED_POST_TYPES, true ) ) {
            return null;
        }
        $object = \function_exists( '\get_post_type_object' ) ? \get_post_type_object( $post_type ) : null;
        if ( ! $object || empty( $object->show_in_rest ) ) {
            return null;
        }
        $rest_base = ! empty( $object->rest_base ) ? (string) $object->rest_base : (string) $post_type;
        return array( 'update' => 'wp_update_cpt_item', 'create' => 'wp_create_cpt_item', 'id_arg' => 'item_id', 'kind' => 'cpt', 'rest_base' => $rest_base );
    }

    
    private static function post_field_map( $kind ) {
        $map = array(
            'post_title'   => 'title',
            'post_status'  => 'status',
            'post_excerpt' => 'excerpt',
            'post_name'    => 'slug',
        );
        if ( 'post' === $kind || 'page' === $kind ) {
            $map += array(
                'post_author'    => 'author',
                'comment_status' => 'comment_status',
                'ping_status'    => 'ping_status',
            );
        }
        if ( 'page' === $kind ) {
            $map += array( 'post_parent' => 'parent', 'menu_order' => 'menu_order' );
        }
        if ( 'attachment' === $kind ) {
            $map = array( 'post_title' => 'title', 'post_excerpt' => 'caption', 'post_content' => 'description' );
        }
        return $map;
    }

    private function plan_post_update( array $row ) {
        $tools = self::post_tools( (string) ( $row['object_subtype'] ?? '' ) );
        if ( null === $tools ) {
            return self::unsupported_post_type( $row );
        }
        $before  = self::before( $row );
        $changed = self::changed( $row );
        if ( ! $before ) {
            return self::refuse( __( 'no previous state was recorded for this post', 'easy-mcp-ai' ) );
        }
        $kind    = $tools['kind'];
        $map     = self::post_field_map( $kind );
        $id      = $this->id( 'post', (int) $row['object_id'] );
        $args    = array( $tools['id_arg'] => $id );
        if ( 'cpt' === $kind ) {
            $args = array( 'rest_base' => $tools['rest_base'], 'item_id' => $id );
        }
        $calls         = array();
        $not_restored  = array();
        $restore       = array();
        $blank_ignored = in_array( $kind, array( 'post', 'page', 'attachment' ), true );

        foreach ( $changed as $field ) {
            if ( 'post_content' === $field && 'attachment' !== $kind ) {
                
                
                
                
                $body = $this->body_before( $row, $before );
                if ( isset( $body['content'] ) ) {
                    $args['content'] = $body['content'];
                    $restore[]       = 'post_content';
                } else {
                    $not_restored['post_content'] = $body['reason'];
                }
                continue;
            }
            if ( ! isset( $map[ $field ] ) ) {
                $not_restored[ $field ] = 'post_password' === $field
                    ? __( 'redacted: the log never keeps a password', 'easy-mcp-ai' )
                    /* translators: %s: tool name */
                    : sprintf( __( '%s has no argument for it', 'easy-mcp-ai' ), $tools['update'] );
                continue;
            }
            $value   = $before[ $field ] ?? null;
            $problem = self::value_problem( $row, $value );
            if ( null !== $problem ) {
                $not_restored[ $field ] = $problem;
                continue;
            }
            if ( 'post_status' === $field ) {
                $allowed = 'cpt' === $kind ? array( 'publish', 'draft', 'pending', 'private' ) : array( 'publish', 'draft', 'pending', 'private', 'future' );
                if ( 'trash' === $value ) {
                    $not_restored[ $field ] = __( 'putting a post back in the trash is not a rebuild; wp_history_apply never trashes', 'easy-mcp-ai' );
                    continue;
                }
                if ( ! in_array( $value, $allowed, true ) ) {
                    /* translators: %s: post status */
                    $not_restored[ $field ] = sprintf( __( 'the tool cannot set the status "%s"', 'easy-mcp-ai' ), (string) $value );
                    continue;
                }
            }
            if ( $blank_ignored && in_array( $map[ $field ], array( 'title', 'excerpt', 'slug', 'caption', 'description' ), true ) && '' === (string) $value ) {
                $not_restored[ $field ] = __( 'the previous value was empty, and the tool keeps the current value when given an empty string', 'easy-mcp-ai' );
                continue;
            }
            if ( in_array( $field, array( 'post_author', 'post_parent', 'menu_order' ), true ) ) {
                $value = 'post_parent' === $field ? $this->id( 'post', (int) $value ) : (int) $value;
            }
            $args[ $map[ $field ] ] = $value;
            $restore[]              = $field;
        }

        $base_keys = 'cpt' === $kind ? array( 'rest_base', 'item_id' ) : array( $tools['id_arg'] );
        if ( array_diff( array_keys( $args ), $base_keys ) ) {
            $calls[] = array( 'tool' => $tools['update'], 'arguments' => $args );
        }
        if ( ! $calls ) {
            return $not_restored
                ? self::refuse( __( 'nothing in this entry can be written back', 'easy-mcp-ai' ), $not_restored )
                : self::skip( __( 'no recorded field changed', 'easy-mcp-ai' ) );
        }
        return self::restore( $calls, $not_restored, $restore );
    }

    







    private function body_before( array $row, array $before ) {
        if ( isset( $before['content_revision_id'] ) ) {
            
            
            
            
            $revision_id = (int) $before['content_revision_id'];
            $revision    = \function_exists( '\wp_get_post_revision' ) ? \wp_get_post_revision( $revision_id ) : null;
            if ( ! $revision ) {
                return array( 'reason' => 'revision_gone' );
            }
            if ( '' === (string) ( $revision->post_content ?? '' ) ) {
                return array( 'reason' => __( 'the previous body was empty, and the tool keeps the current body when given an empty string', 'easy-mcp-ai' ) );
            }
            if ( '0' === (string) $revision->post_content ) {
                return array( 'reason' => __( 'the previous body was "0", and WordPress\'s REST update stores that as an empty body', 'easy-mcp-ai' ) );
            }
            return array( 'content' => (string) $revision->post_content );
        }
        if ( array_key_exists( 'post_content', $before ) ) {
            $problem = self::value_problem( $row, $before['post_content'] );
            if ( null !== $problem ) {
                return array( 'reason' => $problem );
            }
            if ( '' === (string) $before['post_content'] ) {
                return array( 'reason' => __( 'the previous body was empty, and the tool keeps the current body when given an empty string', 'easy-mcp-ai' ) );
            }
            
            
            if ( '0' === (string) $before['post_content'] ) {
                return array( 'reason' => __( 'the previous body was "0", and WordPress\'s REST update stores that as an empty body', 'easy-mcp-ai' ) );
            }
            return array( 'content' => (string) $before['post_content'] );
        }
        $after = self::after( $row );
        return array( 'reason' => isset( $after['content_capture'] ) ? 'not_stored' : 'not_recorded' );
    }

    private function plan_post_delete( array $row ) {
        $subtype = (string) ( $row['object_subtype'] ?? '' );
        if ( 'revision' === $subtype ) {
            return self::skip( __( 'a revision goes with its parent post; the parent entry carries the body', 'easy-mcp-ai' ) );
        }
        if ( 'attachment' === $subtype ) {
            return self::refuse( __( 'a deleted media file cannot be rebuilt: the file itself is never recorded (the entry keeps its details and URL)', 'easy-mcp-ai' ) );
        }
        $tools = self::post_tools( $subtype );
        if ( null === $tools || null === $tools['create'] ) {
            return self::unsupported_post_type( $row );
        }
        $before = self::before( $row );
        if ( ! $before || isset( $before['value_unavailable'] ) ) {
            return self::refuse( __( 'no pre-delete snapshot was recorded', 'easy-mcp-ai' ) );
        }
        if ( ! array_key_exists( 'post_content', $before ) ) {
            return self::refuse( __( 'this entry was written before permanent deletes kept the body; recreating it would leave the post empty', 'easy-mcp-ai' ) );
        }
        foreach ( array( 'post_title', 'post_content' ) as $field ) {
            $problem = self::value_problem( $row, $before[ $field ] ?? null );
            if ( null !== $problem ) {
                return self::refuse( __( 'a value the recreated post needs cannot be given back', 'easy-mcp-ai' ), array( $field => $problem ) );
            }
        }
        if ( '' === trim( (string) ( $before['post_title'] ?? '' ) ) ) {
            return self::refuse( __( 'the post had no title, and the create tools require one', 'easy-mcp-ai' ) );
        }

        $kind         = $tools['kind'];
        $not_restored = array(
            'ID' => __( 'the recreated post gets a new id; entries after it in this call use the new one', 'easy-mcp-ai' ),
        );
        $status  = (string) ( $before['post_status'] ?? 'draft' );
        if ( 'trash' === $status ) {
            $status = (string) ( $before['status_before_trash'] ?? 'draft' );
        }
        $allowed = 'cpt' === $kind ? array( 'publish', 'draft', 'pending', 'private' ) : array( 'publish', 'draft', 'pending', 'private', 'future' );
        if ( ! in_array( $status, $allowed, true ) ) {
            /* translators: %s: post status */
            $not_restored['post_status'] = sprintf( __( 'recreated as a draft: the tool cannot set the status "%s"', 'easy-mcp-ai' ), $status );
            $status                      = 'draft';
        }
        if ( 'draft' !== $status && \class_exists( '\Easy_MCP_AI\Config' ) && \Easy_MCP_AI\Config::get( 'easy_mcp_ai_force_draft_on_create', false ) ) {
            $not_restored['post_status'] = __( 'the site\'s Force draft on create setting makes the recreated post a draft', 'easy-mcp-ai' );
        }

        $args = array(
            'title'   => (string) $before['post_title'],
            'content' => (string) $before['post_content'],
            'status'  => $status,
        );
        if ( 'cpt' === $kind ) {
            $args = array( 'rest_base' => $tools['rest_base'] ) + $args;
        }
        if ( '' !== (string) ( $before['post_excerpt'] ?? '' ) ) {
            $args['excerpt'] = (string) $before['post_excerpt'];
        }
        if ( '' !== (string) ( $before['post_name'] ?? '' ) ) {
            $args['slug'] = (string) $before['post_name'];
        }
        if ( 'cpt' !== $kind ) {
            if ( ! empty( $before['post_date'] ) ) {
                $args['date'] = str_replace( ' ', 'T', (string) $before['post_date'] );
            }
            if ( ! empty( $before['post_author'] ) ) {
                $args['author'] = (int) $before['post_author'];
            }
            foreach ( array( 'comment_status', 'ping_status' ) as $field ) {
                if ( in_array( $before[ $field ] ?? null, array( 'open', 'closed' ), true ) ) {
                    $args[ $field ] = $before[ $field ];
                }
            }
        } else {
            foreach ( array( 'post_date', 'post_author', 'comment_status', 'ping_status' ) as $field ) {
                if ( isset( $before[ $field ] ) ) {
                    $not_restored[ $field ] = __( 'wp_create_cpt_item has no argument for it', 'easy-mcp-ai' );
                }
            }
        }
        if ( 'page' === $kind ) {
            if ( ! empty( $before['post_parent'] ) ) {
                $args['parent'] = $this->id( 'post', (int) $before['post_parent'] );
            }
            if ( ! empty( $before['menu_order'] ) ) {
                $args['menu_order'] = (int) $before['menu_order'];
            }
        } elseif ( ! empty( $before['post_parent'] ) || ! empty( $before['menu_order'] ) ) {
            /* translators: %s: tool name */
            $not_restored['post_parent'] = sprintf( __( '%s has no argument for the parent or menu order', 'easy-mcp-ai' ), $tools['create'] );
        }
        if ( 'post' === $kind && ! empty( $before['sticky'] ) ) {
            $args['sticky'] = true;
        }
        $not_restored['post_password'] = __( 'redacted: if the post had a password, set it again by hand', 'easy-mcp-ai' );

        
        
        $terms   = is_array( $before['terms'] ?? null ) ? $before['terms'] : array();
        $follow  = array();
        foreach ( $terms as $taxonomy => $ids ) {
            $ids = array_values( array_map( function ( $id ) {
                return $this->id( 'term', (int) $id );
            }, (array) $ids ) );
            if ( ! $ids ) {
                continue;
            }
            if ( 'post' === $kind && 'category' === $taxonomy ) {
                $args['categories'] = $ids;
            } elseif ( 'post' === $kind && 'post_tag' === $taxonomy ) {
                $args['tags'] = $ids;
            } elseif ( 'post_format' === $taxonomy ) {
                $format = self::format_from_term( (int) $ids[0] );
                if ( 'post' === $kind && null !== $format ) {
                    $args['format'] = $format;
                } else {
                    $not_restored['terms.post_format'] = __( 'the post format could not be set on the recreated post', 'easy-mcp-ai' );
                }
            } else {
                $follow[] = array(
                    'tool'      => 'wp_add_post_terms',
                    'arguments' => array( 'post_id' => self::NEW_ID, 'taxonomy' => (string) $taxonomy, 'terms' => $ids, 'append' => false ),
                );
            }
        }

        $calls = array_merge( array( array( 'tool' => $tools['create'], 'arguments' => $args ) ), $follow );
        $this->recreated_post_types[ (int) $row['object_id'] ] = $subtype;
        return self::restore( $calls, $not_restored, array( '*' ), 'post' );
    }

    
    private static function format_from_term( $term_id ) {
        if ( ! \function_exists( '\get_term' ) ) {
            return null;
        }
        $term = \get_term( $term_id, 'post_format' );
        if ( ! is_object( $term ) || empty( $term->slug ) || 0 !== strpos( $term->slug, 'post-format-' ) ) {
            return null;
        }
        return substr( $term->slug, strlen( 'post-format-' ) );
    }

    private static function unsupported_post_type( array $row ) {
        /* translators: %s: post type */
        return self::refuse( sprintf( __( 'wp_history_apply does not rebuild "%s" posts', 'easy-mcp-ai' ), (string) ( $row['object_subtype'] ?? '' ) ) );
    }

    

    private function plan_meta( array $row ) {
        $type  = (string) $row['object_type'];
        $parts = explode( ':', (string) $row['object_id'], 2 );
        if ( count( $parts ) !== 2 ) {
            return self::refuse( __( 'the entry does not name the meta key', 'easy-mcp-ai' ) );
        }
        $key    = $parts[1];
        $before = self::before( $row );
        if ( 'user_meta' === $type && self::is_role_key( $key ) ) {
            $chain = $this->role_rows[ (int) $parts[0] ] ?? null;
            if ( null !== $chain ) {
                /* translators: %d: change-history entry id */
                return self::incidental( sprintf( __( 'WordPress\'s storage of the user\'s roles, which wp_update_user rewrites; entry %d puts the roles back', 'easy-mcp-ai' ), $chain['newest'] ) );
            }
            return self::refuse( __( 'WordPress\'s storage of the user\'s roles, which wp_update_user_meta never writes; apply the user entry that changed the roles', 'easy-mcp-ai' ) );
        }
        if ( ! empty( $before['multiple'] ) ) {
            return self::refuse( __( 'the key held several values; the meta tools write one', 'easy-mcp-ai' ) );
        }
        
        
        
        
        
        if ( isset( $before['value_unavailable'] ) || ! array_key_exists( 'value', $before ) || null === $before['value'] ) {
            return self::refuse( __( 'the previous value was not captured', 'easy-mcp-ai' ), array( 'value' => 'value_unavailable' ) );
        }
        $value   = $before['value'];
        $problem = self::value_problem( $row, $value );
        if ( null !== $problem ) {
            return self::refuse( __( 'the previous value cannot be given back', 'easy-mcp-ai' ), array( 'value' => $problem ) );
        }

        if ( 'term_meta' === $type ) {
            $term_id = $this->id( 'term', (int) $parts[0] );
            return self::restore( array( array( 'tool' => 'wp_update_term_meta', 'arguments' => array( 'term_id' => $term_id, 'key' => $key, 'value' => $value ) ) ), array(), array( 'value' ) );
        }
        if ( 'user_meta' === $type ) {
            if ( ! is_scalar( $value ) ) {
                return self::refuse( __( 'wp_update_user_meta writes a string, and the previous value was not one', 'easy-mcp-ai' ) );
            }
            return self::restore( array( array( 'tool' => 'wp_update_user_meta', 'arguments' => array( 'user_id' => (int) $parts[0], 'key' => $key, 'value' => (string) $value ) ) ), array(), array( 'value' ) );
        }

        $post_id = $this->id( 'post', (int) $parts[0] );
        $post    = \function_exists( '\get_post' ) ? \get_post( $post_id ) : null;
        $type    = $post ? (string) ( $post->post_type ?? '' ) : ( $this->recreated_post_types[ (int) $parts[0] ] ?? null );
        if ( null === $type ) {
            return self::refuse( __( 'the post this meta belongs to no longer exists', 'easy-mcp-ai' ) );
        }
        $tools = self::post_tools( $type );
        if ( null === $tools ) {
            return self::unsupported_post_type( array( 'object_subtype' => $type ) );
        }
        
        if ( '_thumbnail_id' === $key || '_wp_page_template' === $key ) {
            if ( ! in_array( $tools['kind'], array( 'post', 'page' ), true ) ) {
                /* translators: %s: tool name */
                return self::refuse( sprintf( __( '%s has no argument for it', 'easy-mcp-ai' ), $tools['update'] ) );
            }
            
            
            
            $template = 'default' === (string) $value ? '' : (string) $value;
            $arg      = '_thumbnail_id' === $key ? array( 'featured_media' => (int) $value ) : array( 'template' => $template );
            return self::restore( array( array( 'tool' => $tools['update'], 'arguments' => array( $tools['id_arg'] => $post_id ) + $arg ) ), array(), array( 'value' ) );
        }
        
        
        
        if ( ! $this->rest_writes_meta( $type, $key ) ) {
            return self::refuse( __( 'the key is not registered for the REST API on this post type, so wp_update_post_meta cannot write it', 'easy-mcp-ai' ) );
        }
        return self::restore(
            array( array(
                'tool'        => 'wp_update_post_meta',
                'arguments'   => array( 'post_id' => $post_id, 'meta' => array( $key => $value ), 'post_type' => $tools['rest_base'] ),
                
                
                'ignored_key' => $key,
            ) ),
            array(),
            array( 'value' )
        );
    }

    












    private function rest_writes_meta( $post_type, $key ) {
        if ( ! class_exists( '\\WP_REST_Post_Meta_Fields' ) ) {
            return true;
        }
        if ( ! isset( $this->rest_meta_fields[ $post_type ] ) ) {
            $schema                               = ( new \WP_REST_Post_Meta_Fields( $post_type ) )->get_field_schema();
            $this->rest_meta_fields[ $post_type ] = isset( $schema['properties'] ) ? (array) $schema['properties'] : array();
        }
        return array_key_exists( $key, $this->rest_meta_fields[ $post_type ] );
    }

    

    private function plan_object_terms( array $row ) {
        $parts = explode( ':', (string) $row['object_id'], 2 );
        if ( count( $parts ) !== 2 ) {
            return self::refuse( __( 'the entry does not name the taxonomy', 'easy-mcp-ai' ) );
        }
        $before = self::before( $row );
        if ( ! empty( $before['unresolved_term_taxonomy_ids'] ) ) {
            return self::refuse( __( 'the previous set includes terms that could not be identified', 'easy-mcp-ai' ), array( 'term_ids' => 'value_unavailable' ) );
        }
        $post_id  = $this->id( 'post', (int) $parts[0] );
        $taxonomy = $parts[1];
        $ids      = $this->term_ids( $before['term_ids'] ?? array() );
        if ( ! $ids ) {
            if ( 'post_tag' === $taxonomy ) {
                return self::restore( array( array( 'tool' => 'wp_update_post', 'arguments' => array( 'post_id' => $post_id, 'tags' => array() ) ) ), array(), array( 'term_ids' ) );
            }
            if ( 'category' === $taxonomy ) {
                return self::refuse( __( 'WordPress always gives a post a category, so an empty category list cannot be restored', 'easy-mcp-ai' ) );
            }
            return self::refuse( __( 'wp_add_post_terms cannot empty a taxonomy', 'easy-mcp-ai' ) );
        }
        return self::restore(
            array( array( 'tool' => 'wp_add_post_terms', 'arguments' => array( 'post_id' => $post_id, 'taxonomy' => $taxonomy, 'terms' => $ids, 'append' => false ) ) ),
            array(),
            array( 'term_ids' )
        );
    }

    
    private function term_ids( $ids ) {
        $out = array();
        foreach ( (array) $ids as $id ) {
            $out[] = $this->id( 'term', (int) $id );
        }
        $out = array_values( array_unique( $out ) );
        sort( $out );
        return $out;
    }

    

    private function plan_term_update( array $row ) {
        $before   = self::before( $row );
        $taxonomy = (string) ( $row['object_subtype'] ?? ( $before['taxonomy'] ?? '' ) );
        if ( ! $before || '' === $taxonomy ) {
            return self::refuse( __( 'no previous state was recorded for this term', 'easy-mcp-ai' ) );
        }
        $args         = array( 'term_id' => $this->id( 'term', (int) $row['object_id'] ), 'taxonomy' => $taxonomy );
        $not_restored = array();
        $restore      = array();
        foreach ( self::changed( $row ) as $field ) {
            if ( ! in_array( $field, array( 'name', 'slug', 'description', 'parent' ), true ) ) {
                if ( ! in_array( $field, array( 'count', 'filter', 'term_taxonomy_id' ), true ) ) {
                    $not_restored[ $field ] = __( 'wp_update_term has no argument for it', 'easy-mcp-ai' );
                }
                continue;
            }
            $value = $before[ $field ] ?? null;
            if ( 'parent' !== $field && '' === (string) $value ) {
                $not_restored[ $field ] = __( 'the previous value was empty, and the tool keeps the current value when given an empty string', 'easy-mcp-ai' );
                continue;
            }
            $args[ $field ] = 'parent' === $field ? $this->id( 'term', (int) $value ) : (string) $value;
            $restore[]      = $field;
        }
        if ( ! $restore ) {
            return $not_restored
                ? self::refuse( __( 'nothing in this entry can be written back', 'easy-mcp-ai' ), $not_restored )
                : self::skip( __( 'no recorded field changed', 'easy-mcp-ai' ) );
        }
        return self::restore( array( array( 'tool' => 'wp_update_term', 'arguments' => $args ) ), $not_restored, $restore );
    }

    private function plan_term_delete( array $row ) {
        $before   = self::before( $row );
        $taxonomy = (string) ( $row['object_subtype'] ?? ( $before['taxonomy'] ?? '' ) );
        if ( ! $before || isset( $before['value_unavailable'] ) || '' === $taxonomy || '' === (string) ( $before['name'] ?? '' ) ) {
            return self::refuse( __( 'no pre-delete snapshot was recorded', 'easy-mcp-ai' ) );
        }
        $args = array( 'name' => (string) $before['name'], 'taxonomy' => $taxonomy );
        if ( '' !== (string) ( $before['slug'] ?? '' ) ) {
            $args['slug'] = (string) $before['slug'];
        }
        if ( '' !== (string) ( $before['description'] ?? '' ) ) {
            $args['description'] = (string) $before['description'];
        }
        if ( ! empty( $before['parent'] ) ) {
            $args['parent'] = $this->id( 'term', (int) $before['parent'] );
        }
        $not_restored = array(
            'term_id' => __( 'the recreated term gets a new id; entries after it in this call use the new one', 'easy-mcp-ai' ),
        );
        if ( ! empty( $before['term_group'] ) ) {
            $not_restored['term_group'] = __( 'wp_create_term has no argument for it', 'easy-mcp-ai' );
        }

        
        
        
        $calls    = array( array( 'tool' => 'wp_create_term', 'arguments' => $args ) );
        $post_tax = self::taxonomy_is_for_posts( $taxonomy );
        $missing  = 0;
        foreach ( (array) ( $before['object_ids'] ?? array() ) as $object_id ) {
            $object_id = (int) $object_id;
            if ( isset( $this->own_term_rows[ $object_id . ':' . $taxonomy ] ) ) {
                continue;
            }
            if ( ! $post_tax ) {
                $not_restored['object_ids'] = __( 'this taxonomy is not for posts; wp_add_post_terms cannot re-attach its objects', 'easy-mcp-ai' );
                break;
            }
            $post_id = $this->id( 'post', $object_id );
            if ( \function_exists( '\get_post' ) && ! \get_post( $post_id ) ) {
                ++$missing;
                continue;
            }
            $calls[] = array(
                'tool'      => 'wp_add_post_terms',
                'arguments' => array( 'post_id' => $post_id, 'taxonomy' => $taxonomy, 'terms' => array( self::NEW_ID ), 'append' => true ),
            );
        }
        if ( $missing ) {
            /* translators: %d: number of posts */
            $not_restored['object_ids'] = sprintf( _n( '%d post that carried the term no longer exists', '%d posts that carried the term no longer exist', $missing, 'easy-mcp-ai' ), $missing );
        }
        if ( ! empty( $before['object_ids_truncated'] ) ) {
            $not_restored['object_ids'] = __( 'the term was on more objects than the log keeps; only the recorded ones are re-attached', 'easy-mcp-ai' );
        }
        return self::restore( $calls, $not_restored, array( '*' ), 'term' );
    }

    private static function taxonomy_is_for_posts( $taxonomy ) {
        if ( ! \function_exists( '\get_taxonomy' ) ) {
            return true;
        }
        $object = \get_taxonomy( $taxonomy );
        if ( ! $object || empty( $object->object_type ) ) {
            return false;
        }
        foreach ( (array) $object->object_type as $type ) {
            if ( \function_exists( '\post_type_exists' ) ? \post_type_exists( $type ) : true ) {
                return true;
            }
        }
        return false;
    }

    

    private function plan_user_update( array $row ) {
        $before       = self::before( $row );
        $not_restored = array();
        $restore      = array();
        $covered      = false;
        $user         = (int) $row['object_id'];
        $chain        = $this->role_rows[ $user ] ?? null;
        $args         = array( 'user_id' => $user );
        foreach ( self::changed( $row ) as $field ) {
            if ( 'roles' === $field ) {
                if ( null !== $chain && (int) $row['id'] !== $chain['newest'] ) {
                    $covered = true;
                    continue;
                }
                $roles = null !== $chain ? $chain['roles'] : self::roles_of( $before );
                if ( ! $roles ) {
                    $not_restored['roles'] = __( 'the user had no role, and wp_update_user cannot remove every role', 'easy-mcp-ai' );
                    continue;
                }
                $args['roles'] = $roles;
                $restore[]     = 'roles';
            } elseif ( 'user_email' === $field ) {
                $not_restored['user_email'] = __( 'redacted: the log never keeps an email address', 'easy-mcp-ai' );
            } else {
                $not_restored[ $field ] = __( 'wp_update_user has no argument for it', 'easy-mcp-ai' );
            }
        }
        if ( ! $restore ) {
            if ( $covered && ! $not_restored ) {
                /* translators: %d: change-history entry id */
                return self::incidental( sprintf( __( 'a step of this call\'s role change; entry %d puts the roles back', 'easy-mcp-ai' ), $chain['newest'] ) );
            }
            return $not_restored
                ? self::refuse( __( 'nothing in this entry can be written back', 'easy-mcp-ai' ), $not_restored )
                : self::skip( __( 'no recorded field changed', 'easy-mcp-ai' ) );
        }
        return self::restore( array( array( 'tool' => 'wp_update_user', 'arguments' => $args ) ), $not_restored, $restore );
    }

    

    
    private static function comment_status( $approved ) {
        $map = array( '1' => 'approve', '0' => 'hold', 'spam' => 'spam' );
        return $map[ (string) $approved ] ?? null;
    }

    private function plan_comment( array $row ) {
        $before = self::before( $row );
        $id     = $this->id( 'comment', (int) $row['object_id'] );
        if ( 'delete' === $row['action'] && 'trash' === ( $row['object_subtype'] ?? '' ) ) {
            return self::restore( array( array( 'tool' => 'wp_untrash_comment', 'arguments' => array( 'comment_id' => $id ) ) ), array(), array( 'comment_approved' ) );
        }
        if ( ! $before || isset( $before['value_unavailable'] ) ) {
            return self::refuse( __( 'no previous state was recorded for this comment', 'easy-mcp-ai' ) );
        }
        if ( 'delete' === $row['action'] ) {
            $content = (string) ( $before['comment_content'] ?? '' );
            $problem = self::value_problem( $row, $content );
            if ( null !== $problem || '' === $content ) {
                return self::refuse( __( 'the comment text cannot be given back', 'easy-mcp-ai' ), array( 'comment_content' => null !== $problem ? $problem : 'value_unavailable' ) );
            }
            $args = array(
                'post'    => $this->id( 'post', (int) ( $before['comment_post_ID'] ?? 0 ) ),
                'content' => $content,
            );
            if ( '' !== (string) ( $before['comment_author'] ?? '' ) ) {
                
                
                
                
                if ( \function_exists( '\get_option' ) && \get_option( 'require_name_email' ) ) {
                    return self::refuse( __( 'this site requires a name and an email for a new comment (Settings > Discussion), and the change history never keeps the email', 'easy-mcp-ai' ), array( 'author_email' => 'redacted' ) );
                }
                $args['author_name'] = (string) $before['comment_author'];
            }
            $status = self::comment_status( $before['comment_approved'] ?? '' );
            if ( null !== $status ) {
                $args['status'] = $status;
            }
            $not_restored = array(
                'comment_ID' => __( 'the recreated comment gets a new id', 'easy-mcp-ai' ),
                'author_email' => __( 'never recorded', 'easy-mcp-ai' ),
            );
            if ( ! empty( $before['comment_parent'] ) ) {
                $not_restored['comment_parent'] = __( 'wp_create_comment cannot set a parent, so the reply is recreated outside its thread', 'easy-mcp-ai' );
            }
            if ( ! empty( $before['comment_date_gmt'] ) ) {
                $not_restored['comment_date_gmt'] = __( 'wp_create_comment cannot set the date', 'easy-mcp-ai' );
            }
            if ( ! empty( $before['user_id'] ) ) {
                $not_restored['user_id'] = __( 'wp_create_comment cannot set the registered author', 'easy-mcp-ai' );
            }
            return self::restore( array( array( 'tool' => 'wp_create_comment', 'arguments' => $args ) ), $not_restored, array( '*' ), 'comment' );
        }

        $args         = array( 'comment_id' => $id );
        $not_restored = array();
        $restore      = array();
        foreach ( self::changed( $row ) as $field ) {
            if ( 'comment_content' === $field ) {
                $value   = (string) ( $before['comment_content'] ?? '' );
                $problem = self::value_problem( $row, $value );
                if ( null !== $problem || '' === $value ) {
                    $not_restored[ $field ] = null !== $problem ? $problem : __( 'the previous text was empty', 'easy-mcp-ai' );
                    continue;
                }
                $args['content'] = $value;
                $restore[]       = $field;
            } elseif ( 'comment_approved' === $field ) {
                $status = self::comment_status( $before['comment_approved'] ?? '' );
                if ( null === $status ) {
                    /* translators: %s: comment status */
                    $not_restored[ $field ] = sprintf( __( 'the tool cannot set the status "%s"', 'easy-mcp-ai' ), (string) ( $before['comment_approved'] ?? '' ) );
                    continue;
                }
                $args['status'] = $status;
                $restore[]      = $field;
            } else {
                $not_restored[ $field ] = __( 'wp_update_comment has no argument for it', 'easy-mcp-ai' );
            }
        }
        if ( ! $restore ) {
            return $not_restored
                ? self::refuse( __( 'nothing in this entry can be written back', 'easy-mcp-ai' ), $not_restored )
                : self::skip( __( 'no recorded field changed', 'easy-mcp-ai' ) );
        }
        return self::restore( array( array( 'tool' => 'wp_update_comment', 'arguments' => $args ) ), $not_restored, $restore );
    }

    

    private function plan_option( array $row ) {
        $name = (string) $row['object_id'];
        if ( false !== strpos( (string) ( $row['object_subtype'] ?? '' ), ':' ) ) {
            return self::refuse( __( 'this entry records one part of an option; no tool writes a part', 'easy-mcp-ai' ) );
        }
        if ( 'sticky_posts' === $name ) {
            return $this->plan_sticky( $row );
        }
        if ( null !== $this->front_page && isset( $this->front_page['rows'][ (int) $row['id'] ] ) ) {
            return $this->plan_front_page( $row );
        }
        if ( ! isset( self::SITE_SETTINGS[ $name ] ) ) {
            /* translators: %s: option name */
            return self::refuse( sprintf( __( 'no tool writes the option "%s"; its previous value is in this entry for a person to restore', 'easy-mcp-ai' ), $name ) );
        }
        $before = self::before( $row );
        if ( isset( $before['value_unavailable'] ) || ! array_key_exists( 'value', $before ) ) {
            return self::refuse( __( 'the previous value was not captured', 'easy-mcp-ai' ), array( 'value' => 'value_unavailable' ) );
        }
        $problem = self::value_problem( $row, $before['value'] );
        if ( null !== $problem ) {
            return self::refuse( __( 'the previous value cannot be given back', 'easy-mcp-ai' ), array( 'value' => $problem ) );
        }
        return self::restore(
            array( array( 'tool' => 'wp_update_site_settings', 'arguments' => array( self::SITE_SETTINGS[ $name ] => self::setting_value( $name, $before['value'] ) ) ) ),
            array(),
            array( 'value' )
        );
    }

    









    private function plan_front_page( array $row ): array {
        $kept_off = $this->front_page_ids_kept_off();
        $name     = (string) $row['object_id'];
        if ( isset( $kept_off[ $name ] ) ) {
            return self::refuse( $kept_off[ $name ] );
        }
        $carrier = $this->front_page_carrier();
        if ( (int) $row['id'] !== $carrier ) {
            /* translators: %d: change-history entry id */
            return self::incidental( sprintf( __( 'a step of this call\'s homepage change; entry %d puts the homepage settings back', 'easy-mcp-ai' ), $carrier ) );
        }
        $args = array();
        foreach ( $this->front_page_written() as $option ) {
            $value           = self::setting_value( $option, $this->front_page['before'][ $option ] );
            $args[ $option ] = 'show_on_front' === $option ? $value : $this->id( 'post', $value ); 
        }
        return self::restore( array( array( 'tool' => 'wp_update_site_settings', 'arguments' => $args ) ), array(), array( 'value' ) );
    }

    




    private function front_page_written(): array {
        $kept_off = $this->front_page_ids_kept_off();
        return array_values( array_filter( self::FRONT_PAGE_OPTIONS, function ( $option ) use ( $kept_off ) {
            return array_key_exists( $option, $this->front_page['before'] ) && ! isset( $kept_off[ $option ] );
        } ) );
    }

    




    private function front_page_carrier(): int {
        $written = $this->front_page_written();
        $ids     = array_keys( array_filter( $this->front_page['rows'], function ( $option ) use ( $written ) {
            return in_array( $option, $written, true );
        } ) );
        return max( $ids );
    }

    








    private function front_page_ids_kept_off(): array {
        $before = $this->front_page['before'];
        if ( ! array_key_exists( 'show_on_front', $before ) || 'page' === (string) $before['show_on_front'] ) {
            return array();
        }
        $kept_off = array();
        foreach ( self::PAGE_OPTIONS as $option ) {
            if ( array_key_exists( $option, $before ) && 0 !== (int) $before[ $option ] ) {
                $kept_off[ $option ] = __( 'a page ID the site kept while its homepage showed the latest posts; it had no effect there, and wp_update_site_settings refuses one sent with show_on_front "posts"', 'easy-mcp-ai' );
            }
        }
        return $kept_off;
    }

    






    private static function setting_value( string $name, $value ) {
        return in_array( self::SITE_SETTINGS[ $name ], self::INTEGER_SETTINGS, true ) ? (int) $value : (string) $value;
    }

    






    private function plan_sticky( array $row ) {
        $before = self::before( $row );
        if ( isset( $before['value_unavailable'] ) || ! array_key_exists( 'value', $before ) ) {
            return self::refuse( __( 'the previous value was not captured', 'easy-mcp-ai' ), array( 'value' => 'value_unavailable' ) );
        }
        $was = self::post_ids( $before['value'] );
        $now = 'delete' === (string) $row['action'] ? array() : self::post_ids( self::after( $row )['value'] ?? array() );

        $changes = array();
        foreach ( array_diff( $was, $now ) as $id ) {
            $changes[ $id ] = true;
        }
        foreach ( array_diff( $now, $was ) as $id ) {
            $changes[ $id ] = false;
        }
        ksort( $changes );

        $calls        = array();
        $not_restored = array();
        $covered      = false;
        foreach ( $changes as $old_id => $sticky ) {
            if ( isset( $this->deleted_in_call[ $old_id ] ) ) {
                $covered = true;
                continue;
            }
            $post_id = $this->id( 'post', $old_id );
            if ( \function_exists( '\get_post' ) && ! \get_post( $post_id ) ) {
                $not_restored[ 'post:' . $old_id ] = __( 'the post no longer exists', 'easy-mcp-ai' );
                continue;
            }
            $calls[] = array( 'tool' => 'wp_update_post', 'arguments' => array( 'post_id' => $post_id, 'sticky' => $sticky ) );
        }
        if ( ! $calls ) {
            if ( $not_restored ) {
                return self::refuse( __( 'no post it names exists any more', 'easy-mcp-ai' ), $not_restored );
            }
            return self::skip(
                $covered
                    ? __( 'the post is recreated by this same call, with its sticky flag', 'easy-mcp-ai' )
                    : __( 'no post was stuck or unstuck', 'easy-mcp-ai' )
            );
        }
        return self::restore( $calls, $not_restored, array( 'value' ) );
    }

    
    private static function post_ids( $value ) {
        $ids = array();
        foreach ( (array) $value as $id ) {
            if ( is_scalar( $id ) && (int) $id > 0 ) {
                $ids[] = (int) $id;
            }
        }
        $ids = array_values( array_unique( $ids ) );
        sort( $ids );
        return $ids;
    }

    






    private static function timestamps_only( array $row ) {
        foreach ( array( self::before( $row ), self::after( $row ) ) as $side ) {
            if ( array_key_exists( 'value', $side ) && null !== $side['value'] && ! ( is_scalar( $side['value'] ) && ctype_digit( (string) $side['value'] ) ) ) {
                return false;
            }
        }
        return true;
    }

    







    private static function incidental_reason( array $row ) {
        $type = (string) ( $row['object_type'] ?? '' );
        $id   = (string) ( $row['object_id'] ?? '' );
        if ( 'meta' === $type ) {
            $key = explode( ':', $id, 2 )[1] ?? '';
            if ( '_pingme' === $key || '_encloseme' === $key ) {
                return __( 'WordPress\'s queue of pings and enclosures to send; it fills it again when the post is published', 'easy-mcp-ai' );
            }
            if ( '_wp_desired_post_slug' === $key || 0 === strpos( $key, '_wp_trash_meta_' ) ) {
                return __( 'WordPress\'s own record of a trash, not content of the post', 'easy-mcp-ai' );
            }
            return null;
        }
        
        
        
        
        
        if ( 'user_meta' === $type && 'last_update' === ( explode( ':', $id, 2 )[1] ?? '' )
            && \function_exists( '\wc_set_user_last_update_time' ) && self::timestamps_only( $row ) ) {
            return __( 'WooCommerce\'s record of when the customer last changed; it stamps it again on the next profile update', 'easy-mcp-ai' );
        }
        if ( 'option' === $type && '_children' === substr( $id, -9 ) ) {
            $taxonomy = substr( $id, 0, -9 );
            if ( '' !== $taxonomy && \function_exists( '\is_taxonomy_hierarchical' ) && \is_taxonomy_hierarchical( $taxonomy ) ) {
                /* translators: %s: taxonomy name */
                return sprintf( __( 'WordPress\'s cache of the %s hierarchy; it rebuilds it itself', 'easy-mcp-ai' ), $taxonomy );
            }
        }
        return null;
    }

    

    











    public function drift( array $row, array $plan, $can_view_all ) {
        $type   = (string) $row['object_type'];
        $action = (string) $row['action'];
        $after  = self::after( $row );
        $fields = (array) ( $plan['restore_fields'] ?? array() );

        switch ( $type ) {
            case 'post':
                if ( 'delete' === $action ) {
                    return self::moved_if( \function_exists( '\get_post' ) && \get_post( (int) $row['object_id'] ) ? array( '*' ) : array() );
                }
                $id   = $this->id( 'post', (int) $row['object_id'] );
                $live = Live_State::read( 'post', $id, $can_view_all, $row['object_subtype'] ?? null );
                if ( null === $live ) {
                    return self::unreadable();
                }
                if ( array() === $live ) {
                    return self::moved_if( array( '*' ) );
                }
                $moved = self::compare( $fields, $after, $live );
                if ( in_array( 'post_content', $fields, true ) ) {
                    $expected = $this->body_after( $row, $after );
                    $same     = null === $expected ? null : Live_State::body_matches( $id, $expected, $can_view_all );
                    if ( null === $same ) {
                        return self::unreadable();
                    }
                    if ( ! $same ) {
                        $moved[] = 'post_content';
                    }
                }
                return self::moved_if( $moved );

            case 'meta':
            case 'term_meta':
            case 'user_meta':
                $parts = explode( ':', (string) $row['object_id'], 2 );
                $kind  = 'term_meta' === $type ? 'term' : ( 'user_meta' === $type ? null : 'post' );
                $id    = null === $kind ? (int) $parts[0] : $this->id( $kind, (int) $parts[0] );
                $same  = Live_State::meta_matches( $type, $id . ':' . ( $parts[1] ?? '' ), $after['value'] ?? null, 'delete' === $action, $can_view_all );
                return null === $same ? self::unreadable() : self::moved_if( $same ? array() : array( 'value' ) );

            case 'object_terms':
                $parts = explode( ':', (string) $row['object_id'], 2 );
                $live  = Live_State::read( 'object_terms', $this->id( 'post', (int) $parts[0] ) . ':' . ( $parts[1] ?? '' ), $can_view_all );
                if ( null === $live ) {
                    return self::unreadable();
                }
                return self::moved_if( $this->term_ids( $after['term_ids'] ?? array() ) === ( $live['term_ids'] ?? null ) ? array() : array( 'term_ids' ) );

            case 'term':
                $taxonomy = (string) ( $row['object_subtype'] ?? '' );
                if ( 'delete' === $action ) {
                    $moved = array();
                    $term  = \function_exists( '\get_term' ) ? \get_term( (int) $row['object_id'], $taxonomy ) : null;
                    if ( is_object( $term ) && ! ( $term instanceof \WP_Error ) ) {
                        $moved[] = '*';
                    }
                    $slug = (string) ( self::before( $row )['slug'] ?? '' );
                    if ( '' !== $slug && \function_exists( '\term_exists' ) && \term_exists( $slug, $taxonomy ) ) {
                        $moved[] = 'slug';
                    }
                    return self::moved_if( $moved );
                }
                $live = Live_State::read( 'term', $this->id( 'term', (int) $row['object_id'] ), $can_view_all, $taxonomy );
                if ( null === $live ) {
                    return self::unreadable();
                }
                return self::moved_if( array() === $live ? array( '*' ) : self::compare( $fields, $after, $live ) );

            case 'user':
                $live = Live_State::read( 'user', (int) $row['object_id'], $can_view_all );
                if ( null === $live ) {
                    return self::unreadable();
                }
                if ( array() === $live ) {
                    return self::moved_if( array( '*' ) );
                }
                $a = (array) ( $after['roles'] ?? array() );
                $b = (array) ( $live['roles'] ?? array() );
                sort( $a );
                sort( $b );
                return self::moved_if( $a === array_values( $b ) ? array() : array( 'roles' ) );

            case 'comment':
                if ( 'delete' === $action && 'trash' !== ( $row['object_subtype'] ?? '' ) ) {
                    return self::moved_if( \function_exists( '\get_comment' ) && \get_comment( (int) $row['object_id'] ) ? array( '*' ) : array() );
                }
                $live = Live_State::read( 'comment', $this->id( 'comment', (int) $row['object_id'] ), $can_view_all );
                if ( null === $live ) {
                    return self::unreadable();
                }
                if ( array() === $live ) {
                    return self::moved_if( array( '*' ) );
                }
                if ( 'delete' === $action ) {
                    return self::moved_if( 'trash' === ( $live['comment_approved'] ?? null ) ? array() : array( 'comment_approved' ) );
                }
                return self::moved_if( self::compare( $fields, $after, $live ) );

            case 'option':
                if ( 'sticky_posts' === (string) $row['object_id'] ) {
                    
                    $moved = array();
                    foreach ( (array) ( $plan['calls'] ?? array() ) as $call ) {
                        $post_id = (int) ( $call['arguments']['post_id'] ?? 0 );
                        $same    = Live_State::sticky_matches( $post_id, empty( $call['arguments']['sticky'] ), $can_view_all );
                        if ( null === $same ) {
                            return self::unreadable();
                        }
                        if ( ! $same ) {
                            $moved[] = 'post:' . $post_id;
                        }
                    }
                    return self::moved_if( $moved );
                }
                if ( null !== $this->front_page && (int) $row['id'] === $this->front_page_carrier() ) {
                    
                    $moved = array();
                    foreach ( $this->front_page_written() as $name ) {
                        $value = $this->front_page['after'][ $name ];
                        $live  = Live_State::read( 'option', (string) $name, $can_view_all );
                        if ( null === $live ) {
                            return self::unreadable();
                        }
                        if ( ! Live_State::same_value( $value, $live['value'] ?? null ) ) {
                            $moved[] = (string) $name;
                        }
                    }
                    return self::moved_if( $moved );
                }
                $live = Live_State::read( 'option', (string) $row['object_id'], $can_view_all, $row['object_subtype'] ?? null );
                if ( null === $live ) {
                    return self::unreadable();
                }
                if ( 'delete' === $action ) {
                    return self::moved_if( false === ( $live['value'] ?? false ) ? array() : array( 'value' ) );
                }
                return self::moved_if( Live_State::same_value( $after['value'] ?? null, $live['value'] ?? null ) ? array() : array( 'value' ) );
        }
        return self::unreadable();
    }

    






    private function body_after( array $row, array $after ) {
        if ( array_key_exists( 'post_content', $after ) ) {
            return (string) $after['post_content']; 
        }
        $revision_id = (int) ( $row['revision_id'] ?? 0 );
        if ( $revision_id <= 0 || ! \function_exists( '\wp_get_post_revision' ) ) {
            return null;
        }
        $revision = \wp_get_post_revision( $revision_id );
        return $revision ? (string) ( $revision->post_content ?? '' ) : null;
    }

    







    private static function compare( array $fields, array $after, array $live ) {
        $moved = array();
        foreach ( $fields as $field ) {
            if ( 'post_content' === $field || ! array_key_exists( $field, $after ) || ! array_key_exists( $field, $live ) ) {
                continue;
            }
            if ( ! Live_State::same_value( $after[ $field ], $live[ $field ] ) ) {
                $moved[] = $field;
            }
        }
        return $moved;
    }

    

    






    public static function value_problem( array $row, $value ) {
        if ( '[REDACTED]' === $value ) {
            return 'redacted';
        }
        if ( is_array( $value ) ) {
            foreach ( $value as $inner ) {
                $problem = self::value_problem( $row, $inner );
                if ( null !== $problem ) {
                    return $problem;
                }
            }
            return null;
        }
        $max = \class_exists( '\Easy_MCP_AI\History\Change_Redactor' ) ? Change_Redactor::MAX_BYTES : 262144;
        if ( ! empty( $row['truncated'] ) && is_string( $value ) && strlen( $value ) === $max ) {
            return 'truncated';
        }
        return null;
    }

    





    private static function delete_tool_for( array $row ) {
        switch ( (string) ( $row['object_type'] ?? '' ) ) {
            case 'post':
                $by_type = array( 'post' => 'wp_delete_post', 'page' => 'wp_delete_page', 'attachment' => 'wp_delete_media' );
                return $by_type[ (string) ( $row['object_subtype'] ?? '' ) ] ?? 'wp_delete_cpt_item';
            case 'term':
                return 'wp_delete_term';
            case 'comment':
                return 'wp_delete_comment';
            case 'user':
                return 'wp_delete_user';
            case 'meta':
                return 'wp_delete_post_meta';
            case 'term_meta':
                return 'wp_delete_term_meta';
            case 'user_meta':
                return 'wp_delete_user_meta';
        }
        return null;
    }

    



    private static function before( array $row ) {
        return is_array( $row['before_value'] ?? null ) ? $row['before_value'] : array();
    }

    



    private static function after( array $row ) {
        return is_array( $row['after_value'] ?? null ) ? $row['after_value'] : array();
    }

    



    private static function changed( array $row ) {
        return array_values( array_map( 'strval', is_array( $row['changed_fields'] ?? null ) ? $row['changed_fields'] : array() ) );
    }

    






    private static function restore( array $calls, array $not_restored, array $restore_fields, $new_id_kind = null ) {
        return array(
            'status'         => 'restore',
            'reason'         => '',
            'calls'          => $calls,
            'not_restored'   => $not_restored,
            'restore_fields' => $restore_fields,
            'new_id_kind'    => $new_id_kind,
        );
    }

    



    private static function skip( $reason ) {
        return array( 'status' => 'skip', 'reason' => $reason, 'calls' => array(), 'not_restored' => array(), 'restore_fields' => array(), 'new_id_kind' => null );
    }

    



    private static function incidental( $reason ) {
        return array( 'status' => 'incidental', 'reason' => $reason, 'calls' => array(), 'not_restored' => array(), 'restore_fields' => array(), 'new_id_kind' => null );
    }

    







    private static function is_role_key( $key ) {
        global $wpdb;
        $prefixes = array( 'wp_' );
        if ( isset( $wpdb ) && ! empty( $wpdb->base_prefix ) ) {
            $prefixes[] = $wpdb->base_prefix;
        }
        foreach ( array_unique( $prefixes ) as $prefix ) {
            if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '(\d+_)?(capabilities|user_level)$/', $key ) ) {
                return true;
            }
        }
        return false;
    }

    




    private static function refuse( $reason, array $not_restored = array() ) {
        return array( 'status' => 'refuse', 'reason' => $reason, 'calls' => array(), 'not_restored' => $not_restored, 'restore_fields' => array(), 'new_id_kind' => null );
    }

    
    private static function unreadable() {
        return array( 'readable' => false, 'moved' => array() );
    }

    



    private static function moved_if( array $moved ) {
        return array( 'readable' => true, 'moved' => array_values( array_unique( $moved ) ) );
    }
}
