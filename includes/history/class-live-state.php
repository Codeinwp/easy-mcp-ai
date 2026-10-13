<?php
namespace Easy_MCP_AI\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
















class Live_State {

    























    public static function read( $type, $id, $can_view_all = false, $object_subtype = null ) {
        switch ( $type ) {
            case 'post':
                if ( ! function_exists( '\get_post' ) ) {
                    return null;
                }
                $p = \get_post( (int) $id );
                if ( ! $p ) {
                    return array();
                }
                
                
                
                if ( ! $can_view_all && ! \current_user_can( 'read_post', (int) $id ) ) {
                    return null;
                }
                
                
                
                
                
                
                
                return array(
                    'post_title'     => $p->post_title     ?? null,
                    'post_status'    => $p->post_status    ?? null,
                    'post_type'      => $p->post_type      ?? null,
                    'post_excerpt'   => $p->post_excerpt   ?? null,
                    'post_parent'    => $p->post_parent    ?? null,
                    'menu_order'     => $p->menu_order     ?? null,
                    'comment_status' => $p->comment_status ?? null,
                    'ping_status'    => $p->ping_status    ?? null,
                    
                    
                    
                    
                    'post_password'  => '[REDACTED]',
                    'post_name'      => $p->post_name      ?? null,
                    'post_author'    => $p->post_author    ?? null,
                );

            case 'option':
                
                
                
                
                $allowlist = \Easy_MCP_AI\History\Change_Recorder::option_allowlist();
                if ( ! in_array( (string) $id, $allowlist, true ) ) {
                    return null;
                }
                
                
                
                if ( ! $can_view_all && ! \current_user_can( 'manage_options' ) ) {
                    return null;
                }
                if ( ! function_exists( '\get_option' ) ) {
                    return null;
                }
                
                
                $scope = \Easy_MCP_AI\History\Change_Recorder::option_scope_from_subtype( (string) $id, $object_subtype );
                $live  = \get_option( $id );
                return array( 'value' => null === $scope ? $live : \Easy_MCP_AI\History\Change_Recorder::option_slice( $live, $scope ) );

            case 'term':
                if ( ! function_exists( '\get_term' ) ) {
                    return null;
                }
                
                
                
                
                
                
                
                if ( ! $can_view_all && ! \current_user_can( 'manage_categories' ) ) {
                    return null;
                }
                
                
                $term = $object_subtype
                    ? \get_term( (int) $id, (string) $object_subtype )
                    : \get_term( (int) $id );
                if ( ! $term || \is_wp_error( $term ) ) {
                    return array();
                }
                return array(
                    'term_id'     => isset( $term->term_id )     ? (int) $term->term_id     : null,
                    'name'        => isset( $term->name )        ? (string) $term->name     : null,
                    'slug'        => isset( $term->slug )        ? (string) $term->slug     : null,
                    'description' => isset( $term->description ) ? (string) $term->description : null,
                    'parent'      => isset( $term->parent )      ? (int) $term->parent      : null,
                    'count'       => isset( $term->count )       ? (int) $term->count       : null,
                    'taxonomy'    => isset( $term->taxonomy )    ? (string) $term->taxonomy : null,
                );

            case 'user':
                if ( ! function_exists( '\get_userdata' ) ) {
                    return null;
                }
                
                
                
                
                $can_read = $can_view_all
                    || ( (int) \get_current_user_id() === (int) $id )
                    || \current_user_can( 'list_users' );
                if ( ! $can_read ) {
                    return null;
                }
                $u = \get_userdata( (int) $id );
                if ( ! $u ) {
                    return array();
                }
                
                
                
                
                
                
                
                return array(
                    'ID'           => isset( $u->ID )           ? (int) $u->ID           : null,
                    'user_login'   => isset( $u->user_login )   ? (string) $u->user_login : null,
                    'user_email'   => '[REDACTED]',
                    'display_name' => isset( $u->display_name ) ? (string) $u->display_name : null,
                    'roles'        => isset( $u->roles )        ? $u->roles : array(),
                );

            case 'comment':
                if ( ! function_exists( '\get_comment' ) ) {
                    return null;
                }
                $c = \get_comment( (int) $id );
                if ( ! $c ) {
                    return array();
                }
                
                
                $parent = isset( $c->comment_post_ID ) ? (int) $c->comment_post_ID : 0;
                if ( ! $can_view_all && $parent > 0 && ! \current_user_can( 'read_post', $parent ) ) {
                    return null;
                }
                return array(
                    'comment_ID'       => isset( $c->comment_ID )       ? (string) $c->comment_ID       : null,
                    'comment_post_ID'  => isset( $c->comment_post_ID )  ? (string) $c->comment_post_ID  : null,
                    'comment_author'   => isset( $c->comment_author )   ? (string) $c->comment_author   : null,
                    'comment_content'  => isset( $c->comment_content )  ? (string) $c->comment_content  : null,
                    'comment_approved' => isset( $c->comment_approved ) ? (string) $c->comment_approved : null,
                    
                    'comment_parent'   => isset( $c->comment_parent )   ? (string) $c->comment_parent   : null,
                    'comment_date_gmt' => isset( $c->comment_date_gmt ) ? (string) $c->comment_date_gmt : null,
                    'user_id'          => isset( $c->user_id )          ? (string) $c->user_id          : null,
                );

            case 'wc_product':
                if ( ! function_exists( '\wc_get_product' ) ) {
                    return null;
                }
                if ( ! $can_view_all && ! \current_user_can( 'edit_products' ) && ! \current_user_can( 'manage_woocommerce' ) ) {
                    return null;
                }
                $product = \wc_get_product( (int) $id );
                if ( ! $product || ! method_exists( $product, 'get_data' ) ) {
                    return array();
                }
                
                
                
                
                
                
                return json_decode( wp_json_encode(
                    \Easy_MCP_AI\History\Change_Recorder::expand_for_storage( $product->get_data() )
                ), true ) ?: array();

            case 'wc_order':
                if ( ! function_exists( '\wc_get_order' ) ) {
                    return null;
                }
                if ( ! $can_view_all && ! \current_user_can( 'edit_shop_orders' ) && ! \current_user_can( 'manage_woocommerce' ) ) {
                    return null;
                }
                $order = \wc_get_order( (int) $id );
                if ( ! $order || ! method_exists( $order, 'get_data' ) ) {
                    return array();
                }
                
                
                
                return json_decode( wp_json_encode(
                    \Easy_MCP_AI\History\Change_Recorder::expand_for_storage( $order->get_data() )
                ), true ) ?: array();

            case 'bp_activity':
                if ( ! function_exists( '\bp_activity_get_specific' ) ) {
                    return null;
                }
                
                
                
                
                if ( ! $can_view_all && ! \current_user_can( 'bp_moderate' ) ) {
                    return null;
                }
                $resp = \bp_activity_get_specific( array( 'activity_ids' => array( (int) $id ) ) );
                if ( empty( $resp['activities'][0] ) ) {
                    return array();
                }
                $a = $resp['activities'][0];
                return array(
                    'id'        => isset( $a->id )        ? (int) $a->id        : null,
                    'user_id'   => isset( $a->user_id )   ? (int) $a->user_id   : null,
                    'component' => isset( $a->component ) ? (string) $a->component : null,
                    'type'      => isset( $a->type )      ? (string) $a->type   : null,
                    'action'    => isset( $a->action )    ? (string) $a->action : null,
                    'content'   => isset( $a->content )   ? (string) $a->content : null,
                    'item_id'   => isset( $a->item_id )   ? (int) $a->item_id   : null,
                    'hide_sitewide' => isset( $a->hide_sitewide ) ? (int) $a->hide_sitewide : null,
                );

            case 'meta':
                
                
                $parts = explode( ':', (string) $id, 2 );
                if ( count( $parts ) !== 2 || ! function_exists( '\get_post_meta' ) ) {
                    return null;
                }
                $post_id  = (int) $parts[0];
                $meta_key = $parts[1];
                
                
                
                if ( ! $can_view_all && ! \current_user_can( 'edit_post', $post_id ) ) {
                    return null;
                }
                
                
                
                if ( function_exists( '\is_protected_meta' ) && \is_protected_meta( $meta_key, 'post' ) ) {
                    return null;
                }
                return array( 'value' => \get_post_meta( $post_id, $meta_key, true ) );

            case 'term_meta':
            case 'user_meta':
            case 'comment_meta':
                
                
                
                
                
                
                
                
                
                return self::meta_read( $type, (string) $id, $can_view_all );

            case 'site_option':
                
                
                
                
                $allowlist = \Easy_MCP_AI\History\Change_Recorder::option_allowlist();
                if ( ! in_array( (string) $id, $allowlist, true ) ) {
                    return null;
                }
                $is_network = function_exists( '\is_multisite' ) && \is_multisite();
                $network_ok = $is_network
                    ? \current_user_can( 'manage_network_options' )
                    : \current_user_can( 'manage_options' );
                
                
                
                
                
                
                
                
                
                
                
                if ( ! $network_ok && ( $is_network || ! $can_view_all ) ) {
                    return null;
                }
                if ( function_exists( '\get_site_option' ) ) {
                    return array( 'value' => \get_site_option( (string) $id ) );
                }
                return function_exists( '\get_option' )
                    ? array( 'value' => \get_option( (string) $id ) )
                    : null;

            case 'object_terms':
                
                
                
                
                $parts = explode( ':', (string) $id, 2 );
                if ( count( $parts ) !== 2 || ! function_exists( '\\wp_get_object_terms' ) ) {
                    return null;
                }
                $post_id  = (int) $parts[0];
                $taxonomy = $parts[1];
                if ( ! $can_view_all && ! \current_user_can( 'edit_post', $post_id ) ) {
                    return null;
                }
                if ( function_exists( '\\get_post' ) && ! \get_post( $post_id ) ) {
                    return array();
                }
                $ids = \wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
                if ( ! is_array( $ids ) ) {
                    return null; 
                }
                $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
                sort( $ids );
                return array( 'term_ids' => $ids );

            default:
                
                
                return null;
        }
    }

    










    private static function meta_read( $type, $id, $can_view_all ) {
        $parts = explode( ':', $id, 2 );
        if ( count( $parts ) !== 2 ) {
            return null;
        }
        $parent_id = (int) $parts[0];
        $meta_key  = $parts[1];

        switch ( $type ) {
            case 'term_meta':
                if ( ! $can_view_all && ! \current_user_can( 'manage_categories' ) ) {
                    return null;
                }
                return function_exists( '\get_term_meta' )
                    ? array( 'value' => \get_term_meta( $parent_id, $meta_key, true ) )
                    : null;

            case 'user_meta':
                
                
                if ( \Easy_MCP_AI\History\Change_Recorder::user_meta_is_blocked( $meta_key ) ) {
                    return null;
                }
                $can_read = $can_view_all
                    || ( (int) \get_current_user_id() === $parent_id )
                    || \current_user_can( 'list_users' );
                if ( ! $can_read ) {
                    return null;
                }
                return function_exists( '\get_user_meta' )
                    ? array( 'value' => \get_user_meta( $parent_id, $meta_key, true ) )
                    : null;

            case 'comment_meta':
                if ( ! $can_view_all && ! \current_user_can( 'moderate_comments' ) ) {
                    return null;
                }
                return function_exists( '\get_comment_meta' )
                    ? array( 'value' => \get_comment_meta( $parent_id, $meta_key, true ) )
                    : null;
        }

        return null;
    }

    











    public static function body_matches( $post_id, $expected, $can_view_all = false ) {
        if ( ! function_exists( '\\get_post' ) ) {
            return null;
        }
        $post = \get_post( (int) $post_id );
        if ( ! $post ) {
            return null;
        }
        if ( ! $can_view_all && ! \current_user_can( 'edit_post', (int) $post_id ) ) {
            return null;
        }
        return (string) ( $post->post_content ?? '' ) === (string) $expected;
    }

    









    public static function sticky_matches( $post_id, $expected, $can_view_all = false ) {
        if ( ! function_exists( '\\get_post' ) || ! function_exists( '\\is_sticky' ) || ! \get_post( (int) $post_id ) ) {
            return null;
        }
        if ( ! $can_view_all && ! \current_user_can( 'edit_post', (int) $post_id ) ) {
            return null;
        }
        return \is_sticky( (int) $post_id ) === (bool) $expected;
    }

    















    public static function meta_matches( $type, $id, $expected, $expect_absent = false, $can_view_all = false ) {
        $parts = explode( ':', (string) $id, 2 );
        if ( count( $parts ) !== 2 ) {
            return null;
        }
        $object_id = (int) $parts[0];
        $key       = $parts[1];
        $meta_type = array( 'meta' => 'post', 'term_meta' => 'term', 'user_meta' => 'user' );
        if ( ! isset( $meta_type[ $type ] ) || ! function_exists( '\\metadata_exists' ) || ! function_exists( '\\get_metadata' ) ) {
            return null;
        }
        if ( 'meta' === $type ) {
            if ( ! $can_view_all && ! \current_user_can( 'edit_post', $object_id ) ) {
                return null;
            }
        } elseif ( null === self::meta_read( $type, (string) $id, $can_view_all ) ) {
            return null; 
        }
        $exists = \metadata_exists( $meta_type[ $type ], $object_id, $key );
        if ( $expect_absent ) {
            return ! $exists;
        }
        if ( ! $exists ) {
            return false;
        }
        return self::same_value( \get_metadata( $meta_type[ $type ], $object_id, $key, true ), $expected );
    }

    













    public static function same_value( $a, $b ) {
        return self::stored_form( $a ) === self::stored_form( $b );
    }

    
    private static function stored_form( $value ) {
        if ( is_object( $value ) || is_array( $value ) ) {
            $value = json_decode( (string) \wp_json_encode( $value ), true );
        }
        if ( is_array( $value ) ) {
            $out = array();
            foreach ( $value as $k => $v ) {
                $out[ (string) $k ] = self::stored_form( $v );
            }
            return $out;
        }
        if ( true === $value ) {
            return '1';
        }
        if ( false === $value || null === $value ) {
            return '';
        }
        return (string) $value;
    }
}
