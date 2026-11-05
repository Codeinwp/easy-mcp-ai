<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi_Access {

    const THEME_BUILDER_TYPES = array( 'et_header_layout', 'et_body_layout', 'et_footer_layout' );

    const LIBRARY_TYPE = 'et_pb_layout';

    const OP_PERMISSIONS = array(
        'insert'     => array( 'add_module' ),
        'duplicate'  => array( 'add_module' ),
        'remove'     => array( 'add_module' ),
        'move'       => array( 'move_module' ),
        'set_text'   => array( 'edit_module', 'general_settings' ),
        'set_link'   => array( 'edit_module', 'general_settings' ),
        'set_image'  => array( 'edit_module', 'general_settings' ),
        'set_fields' => array( 'edit_module', 'general_settings' ),
    );

    const CATEGORY_PERMISSIONS = array(
        'basic_option'  => 'edit_content',
        'color_option'  => 'edit_colors',
        'font_option'   => 'edit_fonts',
        'button'        => 'edit_buttons',
        'layout'        => 'edit_layout',
        'configuration' => 'edit_configuration',
        'border'        => 'edit_borders',
    );

    const PERMISSION_LABELS = array(
        'add_module'         => 'Add/Delete Item',
        'move_module'        => 'Move Item',
        'edit_module'        => 'Edit Item',
        'lock_module'        => 'Lock Item',
        'general_settings'   => 'Content Settings',
        'edit_content'       => 'Edit Content',
        'edit_colors'        => 'Edit Colors',
        'edit_fonts'         => 'Edit Fonts',
        'edit_buttons'       => 'Edit Buttons',
        'edit_layout'        => 'Edit Layout',
        'edit_configuration' => 'Edit Configuration',
        'edit_borders'       => 'Edit Borders',
    );

    public static function refusal( object $post ): ?string {
        if ( ! function_exists( 'et_pb_is_allowed' ) ) {
            return 'Divi\'s Role Editor cannot be read on this site, so this layout can be read but not edited.';
        }
        $type = (string) ( $post->post_type ?? '' );
        if ( in_array( $type, self::THEME_BUILDER_TYPES, true ) ) {
            if ( ! et_pb_is_allowed( 'theme_builder' ) || ! current_user_can( 'edit_others_posts' ) ) {
                return 'Divi lets only accounts with its Theme Builder permission (Divi › Role Editor) and the right to edit others\' posts change Theme Builder layouts, so this one can be read but not edited.';
            }
            return null;
        }
        if ( self::LIBRARY_TYPE === $type ) {
            if ( ! et_pb_is_allowed( 'divi_library' ) ) {
                return 'Divi\'s Role Editor does not give this account the Divi Library, so this item can be read but not edited.';
            }
            if ( has_term( 'global', 'scope', $post ) && ! et_pb_is_allowed( 'edit_global_library' ) ) {
                return 'Divi\'s Role Editor does not let this account edit global Library items, so this one can be read but not edited.';
            }
            return null;
        }
        if ( ! et_pb_is_allowed( 'use_visual_builder' ) ) {
            return 'Divi\'s Role Editor does not give this account the Visual Builder, so this layout can be read but not edited.';
        }
        return null;
    }

    public static function op_refusal( string $op, string $module = '' ): ?string {
        if ( ! function_exists( 'et_pb_is_allowed' ) ) {
            return null;
        }
        foreach ( self::OP_PERMISSIONS[ $op ] ?? array() as $permission ) {
            if ( ! et_pb_is_allowed( $permission ) ) {
                return self::missing( $permission, $op );
            }
        }
        return 'insert' === $op && '' !== $module ? self::module_refusal( $module ) : null;
    }

    public static function module_refusal( string $module ): ?string {
        if ( ! function_exists( 'et_pb_is_allowed' ) || et_pb_is_allowed( $module ) ) {
            return null;
        }
        return sprintf( 'Divi\'s Role Editor does not let this account use %s modules (Module Use).', $module );
    }

    public static function field_refusal( string $field, string $category ): ?string {
        $permission = self::CATEGORY_PERMISSIONS[ $category ] ?? '';
        if ( '' === $permission || ! function_exists( 'et_pb_is_allowed' ) || et_pb_is_allowed( $permission ) ) {
            return null;
        }
        return self::missing( $permission, sprintf( 'field "%s"', $field ) );
    }

    public static function lock_refusal( string $element ): ?string {
        if ( ! function_exists( 'et_pb_is_allowed' ) || et_pb_is_allowed( 'lock_module' ) ) {
            return null;
        }
        return sprintf( '%s is locked in Divi, and only an account with "Lock Item" (Divi › Role Editor) may change a locked element or what it holds.', $element );
    }

    private static function missing( string $permission, string $what ): string {
        return sprintf( 'Divi\'s Role Editor does not give this account "%s" (Divi › Role Editor), which %s needs.', self::PERMISSION_LABELS[ $permission ], $what );
    }

    public static function is_divi_type( object $post ): bool {
        return in_array( (string) ( $post->post_type ?? '' ), array_merge( self::THEME_BUILDER_TYPES, array( self::LIBRARY_TYPE ) ), true );
    }

    public static function after_save( int $post_id ): void {
        do_action( 'et_save_post', $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Divi's own hook, fired as its builder fires it.
        do_action( 'et_update_post', $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Divi's own hook, fired as its builder fires it.
    }
}
