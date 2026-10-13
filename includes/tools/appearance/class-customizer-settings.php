<?php
namespace Easy_MCP_AI\Tools\Appearance;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../../mcp/class-detailed-tool-error.php';

















































































final class Customizer_Settings {

    















    const DENIED_REASONS = array(
        'nav_menu_locations' => 'Menu locations ("nav_menu_locations") cannot be changed with this tool. Assign menus to theme locations in wp-admin (Appearance → Menus → Manage Locations); the menus themselves are managed with wp_list_menus and wp_update_menu.',
        'custom_css_post_id' => '"custom_css_post_id" is WordPress\'s internal pointer to the Additional CSS post and cannot be edited: pointing it at another post would print that post as CSS on every page. Use wp_update_custom_css to change Additional CSS.',
        'custom_css'         => 'Additional CSS is not a theme mod. Use wp_get_custom_css and wp_update_custom_css.',
    );

    










    const REGISTERED_ONLY_IN_SCREEN_HINT = 'If the setting appears in Appearance → Customize, the theme registers it only inside that screen and it cannot be changed through this tool.';

    
    const VALID_KEY_LIST_LIMIT = 40;

    




    const WP_DIE_HANDLER_FILTERS = array(
        'wp_die_handler',
        'wp_die_ajax_handler',
        'wp_die_json_handler',
        'wp_die_jsonp_handler',
        'wp_die_xmlrpc_handler',
        'wp_die_xml_handler',
    );

    
    const WP_DIE_MESSAGE_LIMIT = 300;

    
    const WHILE_LOADING = 'loading';

    
    const WHILE_PUBLISHING = 'publishing';

    




    const REFUSAL_TAILS = array(
        ' Nothing was changed: send this call in its own request.',
        ', so this tool cannot run on this site. Nothing was changed.',
    );

    
    const MANAGER_FILE = 'class-wp-customize-manager.php';

    
    const NO_CUSTOMIZER = 'This site has no Customizer (WordPress\'s Customizer files are missing), so theme mods and Additional CSS cannot be changed with these tools. Nothing was changed.';

    
    private static $manager_factory = null;

    
    private static $manager_built = false;

    
    private static $request_before_save = null;

    




    public static function reset_request_state(): void {
        self::$manager_built = false;
        self::restore_request();
    }

    





    public static function set_manager_factory( ?callable $factory ): void {
        self::$manager_factory = $factory;
    }

    







    public static function is_customizer_available(): bool {
        return null !== self::$manager_factory || is_readable( ABSPATH . WPINC . '/' . self::MANAGER_FILE );
    }

    






    public static function base_of( string $key ): string {
        $key = str_replace( ']', '', $key );
        $pos = strpos( $key, '[' );
        return false === $pos ? $key : substr( $key, 0, $pos );
    }

    






    public static function parse_key( $raw ): string {
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            throw new \InvalidArgumentException( '"key" must be a non-empty string (a Customizer setting id).' );
        }
        return trim( $raw );
    }

    





    public static function denied_reason( string $key ): ?string {
        $base = self::base_of( $key );
        return isset( self::DENIED_REASONS[ $base ] ) ? self::DENIED_REASONS[ $base ] : null;
    }

    






    public static function assert_can_customize(): void {
        if ( ! current_user_can( 'customize' ) ) {
            throw new \RuntimeException( 'You do not have permission to customize this site (the "customize" capability is required, as for Appearance → Customize).' );
        }
    }

    







    public static function with_manager( callable $fn ) {
        if ( isset( $GLOBALS['wp_customize'] ) && is_object( $GLOBALS['wp_customize'] ) ) {
            throw new \RuntimeException( 'This request is already running the Customizer (a preview or changeset request). Call the tool without Customizer parameters.' );
        }
        self::refuse_second_manager();

        
        
        
        unset( $_POST['customized'], $_REQUEST['customized'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $had_global   = array_key_exists( 'wp_customize', $GLOBALS );
        $previous     = $had_global ? $GLOBALS['wp_customize'] : null;
        $ob_level     = ob_get_level();
        $filter_depth = self::current_filter_depth();
        ob_start();

        try {
            $registered_before = self::customize_register_callbacks();

            $wp_die_filter = self::trap_wp_die();
            try {
                $manager = null !== self::$manager_factory
                    ? call_user_func( self::$manager_factory )
                    : self::construct_manager();

                remove_action( 'setup_theme', array( $manager, 'setup_theme' ) );
                remove_action( 'wp_loaded', array( $manager, 'wp_loaded' ) );

                self::run_after_core_registration( $registered_before );

                
                
                
                
                
                
                
                if ( class_exists( '\WP_Customize_Setting', false ) && method_exists( '\WP_Customize_Setting', 'reset_aggregated_multidimensionals' ) ) {
                    \WP_Customize_Setting::reset_aggregated_multidimensionals();
                }

                $GLOBALS['wp_customize'] = $manager;
                $manager->wp_loaded();
            } finally {
                self::release_wp_die( $wp_die_filter );
            }

            return $fn( $manager );
        } finally {
            
            
            
            
            
            for ( $level = ob_get_level(); $level > $ob_level; $level-- ) {
                try {
                    ob_end_clean();
                } catch ( \Throwable $e ) {
                    unset( $e );
                }
            }
            if ( $had_global ) {
                $GLOBALS['wp_customize'] = $previous;
            } else {
                unset( $GLOBALS['wp_customize'] );
            }
            self::restore_request();
            self::unwind_current_filter( $filter_depth );
        }
    }

    

















    private static function refuse_second_manager(): void {
        if ( self::$manager_built ) {
            throw new \Easy_MCP_AI\MCP\Detailed_Tool_Error(
                'Only one Customizer tool call can run per request, and this request already ran one.' . self::REFUSAL_TAILS[0],
                'Themes load their Customizer code once per request, as on the Customizer screen; a second Customizer in the same JSON-RPC batch can end the whole response with a fatal error.'
            );
        }
        self::$manager_built = true;
    }

    




















    private static function present_save_request( array $values ): void {
        if ( null === self::$request_before_save ) {
            self::$request_before_save = array(
                'post'    => $_POST, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- saved to be restored, not read.
                'request' => $_REQUEST, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- saved to be restored, not read.
            );
        }
        $nonce      = wp_create_nonce( 'save-customize_' . get_stylesheet() );
        $customized = wp_slash( (string) wp_json_encode( $values ) );

        $_POST['nonce']         = $nonce;
        $_REQUEST['nonce']      = $nonce;
        $_POST['customized']    = $customized;
        $_REQUEST['customized'] = $customized;
    }

    




    private static function restore_request(): void {
        if ( null === self::$request_before_save ) {
            return;
        }
        $_POST                     = self::$request_before_save['post'];
        $_REQUEST                  = self::$request_before_save['request'];
        self::$request_before_save = null;
    }

    




    private static function current_filter_depth(): int {
        return isset( $GLOBALS['wp_current_filter'] ) && is_array( $GLOBALS['wp_current_filter'] ) ? count( $GLOBALS['wp_current_filter'] ) : 0;
    }

    









    private static function unwind_current_filter( int $depth ): void {
        if ( self::current_filter_depth() > $depth ) {
            array_splice( $GLOBALS['wp_current_filter'], $depth );
        }
    }

    



























    private static function trap_wp_die( string $while = self::WHILE_LOADING ): \Closure {
        $handler = static function ( $message, $title = '', $args = array() ) use ( $while ): void {
            if ( is_array( $args ) && array_key_exists( 'exit', $args ) && ! $args['exit'] ) {
                return;
            }
            throw self::wp_die_refusal( $message, debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ), $while ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- names the caller in the refusal.
        };
        $filter = static function () use ( $handler ) {
            return $handler;
        };
        foreach ( self::WP_DIE_HANDLER_FILTERS as $hook ) {
            add_filter( $hook, $filter, PHP_INT_MAX );
        }
        return $filter;
    }

    





    private static function release_wp_die( \Closure $filter ): void {
        foreach ( self::WP_DIE_HANDLER_FILTERS as $hook ) {
            remove_filter( $hook, $filter, PHP_INT_MAX );
        }
    }

    








    private static function wp_die_refusal( $message, array $trace, string $while = self::WHILE_LOADING ): \Easy_MCP_AI\MCP\Detailed_Tool_Error {
        $text = '';
        if ( is_object( $message ) && method_exists( $message, 'get_error_message' ) ) {
            $text = (string) $message->get_error_message();
        } elseif ( is_string( $message ) ) {
            $text = $message;
        }
        $text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
        if ( strlen( $text ) > self::WP_DIE_MESSAGE_LIMIT ) {
            $text = rtrim( substr( $text, 0, self::WP_DIE_MESSAGE_LIMIT ) ) . '…';
        }

        $caller = self::wp_die_caller( $trace );
        $format = self::WHILE_PUBLISHING === $while
            ? '%s stopped the Customizer while it published the change.'
            : '%s stopped the Customizer from loading' . self::REFUSAL_TAILS[1];
        return new \Easy_MCP_AI\MCP\Detailed_Tool_Error(
            sprintf( $format, '' === $caller ? 'Code on this site' : $caller ),
            ( '' === $text ? 'It gave no message.' : 'Its message: "' . $text . '".' ) . ' The conflict is in that code, not in the request: retrying fails the same way until it changes.'
        );
    }

    







    public static function cause_of( \Throwable $e ): string {
        $message = $e->getMessage();
        foreach ( self::REFUSAL_TAILS as $tail ) {
            if ( strlen( $message ) > strlen( $tail ) && substr( $message, -strlen( $tail ) ) === $tail ) {
                $message = rtrim( substr( $message, 0, -strlen( $tail ) ), '.' ) . '.';
                break;
            }
        }
        $details = $e instanceof \Easy_MCP_AI\MCP\Detailed_Tool_Error ? trim( $e->get_details() ) : '';
        return '' === $details ? $message : $message . ' ' . $details;
    }

    







    private static function wp_die_caller( array $trace ): string {
        foreach ( $trace as $i => $frame ) {
            if ( 'wp_die' !== ( $frame['function'] ?? '' ) || isset( $frame['class'] ) ) {
                continue;
            }
            $name = '';
            if ( isset( $trace[ $i + 1 ]['function'] ) && false === strpos( (string) $trace[ $i + 1 ]['function'], '{closure' ) ) {
                $outer = $trace[ $i + 1 ];
                $name  = ( isset( $outer['class'] ) ? $outer['class'] . '::' : '' ) . $outer['function'] . '()';
            }
            $owner = isset( $frame['file'] ) ? self::code_owner( (string) $frame['file'] ) : '';
            if ( '' === $name ) {
                return '' === $owner ? '' : 'Code in ' . $owner;
            }
            return '' === $owner ? $name : $name . ' in ' . $owner;
        }
        return '';
    }

    





    private static function code_roots(): array {
        $roots = array();
        if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
            $roots['the must-use plugin'] = (string) WPMU_PLUGIN_DIR;
        }
        if ( defined( 'WP_PLUGIN_DIR' ) ) {
            $roots['the plugin'] = (string) WP_PLUGIN_DIR;
        }
        if ( function_exists( 'get_theme_root' ) ) {
            $roots['the theme'] = (string) get_theme_root();
        }
        return $roots;
    }

    






    private static function code_owner( string $file ): string {
        $normalize = static function ( string $path ): string {
            return trailingslashit( str_replace( '\\', '/', $path ) );
        };
        
        
        
        $file = str_replace( '\\', '/', $file );
        foreach ( self::code_roots() as $label => $root ) {
            $resolved = realpath( $root );
            foreach ( array_unique( array( $normalize( $root ), $normalize( false === $resolved ? $root : $resolved ) ) ) as $prefix ) {
                if ( '/' !== $prefix && 0 === strpos( $file, $prefix ) ) {
                    $slug = explode( '/', substr( $file, strlen( $prefix ) ) )[0];
                    return '' === $slug ? '' : $label . ' "' . $slug . '"';
                }
            }
        }
        return '';
    }

    





    private static function customize_register_callbacks(): array {
        $out = array();
        if ( ! isset( $GLOBALS['wp_filter']['customize_register'] ) || ! is_object( $GLOBALS['wp_filter']['customize_register'] ) || ! isset( $GLOBALS['wp_filter']['customize_register']->callbacks ) ) {
            return $out;
        }
        foreach ( (array) $GLOBALS['wp_filter']['customize_register']->callbacks as $priority => $callbacks ) {
            foreach ( (array) $callbacks as $entry ) {
                if ( isset( $entry['function'] ) ) {
                    $out[] = array( (int) $priority, $entry['function'], isset( $entry['accepted_args'] ) ? (int) $entry['accepted_args'] : 1 );
                }
            }
        }
        return $out;
    }

    






















    private static function run_after_core_registration( array $callbacks ): void {
        foreach ( $callbacks as $callback ) {
            if ( remove_action( 'customize_register', $callback[1], $callback[0] ) ) {
                add_action( 'customize_register', $callback[1], $callback[0], $callback[2] );
            }
        }
    }

    




    private static function construct_manager(): \WP_Customize_Manager {
        if ( ! self::is_customizer_available() ) {
            throw new \RuntimeException( self::NO_CUSTOMIZER );
        }
        require_once ABSPATH . WPINC . '/' . self::MANAGER_FILE;
        return new \WP_Customize_Manager(
            array(
                'theme'              => get_stylesheet(),
                'messenger_channel'  => '',
                'settings_previewed' => false,
            )
        );
    }

    










    public static function resolve_writable_setting( object $manager, string $key ): object {
        $setting = $manager->get_setting( $key );
        if ( ! $setting ) {
            
            
            
            $manager->add_dynamic_settings( array( $key ) );
            $setting = $manager->get_setting( $key );
        }

        if ( ! $setting ) {
            throw self::unknown_key_refusal( $manager, $key );
        }

        $data   = $setting->id_data();
        $denied = self::denied_reason( $data['base'] );
        if ( null !== $denied ) {
            throw new \InvalidArgumentException( $denied ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( 'theme_mod' !== $setting->type ) {
            throw new \InvalidArgumentException( sprintf( '"%s" is a Customizer setting of type "%s", not a theme mod, and cannot be changed with this tool.', $setting->id, $setting->type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        
        
        
        
        if ( 'WP_Customize_Filter_Setting' === get_class( $setting ) ) {
            throw new \InvalidArgumentException( sprintf( '"%s" is not stored by the Customizer on its own and cannot be changed with this tool.', $setting->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        
        
        if ( $setting->capability && ! current_user_can( $setting->capability ) ) {
            throw new \RuntimeException( sprintf( 'You do not have permission to change "%s" (it requires the "%s" capability).', $setting->id, is_array( $setting->capability ) ? implode( ', ', $setting->capability ) : $setting->capability ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! $setting->check_capabilities() ) {
            throw new \RuntimeException( sprintf( '"%s" belongs to a theme feature the active theme does not support, so the Customizer does not offer it.', $setting->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $setting;
    }

    





    public static function editable_settings( object $manager ): array {
        $out = array();
        foreach ( (array) $manager->settings() as $id => $setting ) {
            if ( ! is_object( $setting ) || 'theme_mod' !== $setting->type ) {
                continue;
            }
            if ( 'WP_Customize_Filter_Setting' === get_class( $setting ) ) {
                continue;
            }
            $data = $setting->id_data();
            if ( null !== self::denied_reason( $data['base'] ) ) {
                continue;
            }
            if ( ! $setting->check_capabilities() ) {
                continue;
            }
            $out[ (string) $setting->id ] = $setting;
        }
        ksort( $out );
        return $out;
    }

    






    public static function describe_editable( object $manager ): array {
        $settings = self::editable_settings( $manager );
        $controls = array();
        foreach ( (array) $manager->controls() as $control ) {
            if ( ! is_object( $control ) || empty( $control->settings ) || ! is_array( $control->settings ) ) {
                continue;
            }
            foreach ( $control->settings as $control_setting ) {
                if ( ! is_object( $control_setting ) || ! isset( $control_setting->id ) ) {
                    continue;
                }
                $id = (string) $control_setting->id;
                if ( isset( $settings[ $id ] ) && ! isset( $controls[ $id ] ) ) {
                    $controls[ $id ] = $control;
                }
            }
        }

        $out = array();
        foreach ( $settings as $id => $setting ) {
            $row = array(
                'key'     => $id,
                'default' => $setting->default,
            );
            if ( isset( $controls[ $id ] ) ) {
                $control             = $controls[ $id ];
                $row['label']        = isset( $control->label ) ? wp_strip_all_tags( (string) $control->label ) : '';
                $row['control_type'] = isset( $control->type ) ? (string) $control->type : '';
                if ( ! empty( $control->choices ) && is_array( $control->choices ) ) {
                    $row['choices'] = array_map(
                        static function ( $label ) {
                            return is_scalar( $label ) ? wp_strip_all_tags( (string) $label ) : $label;
                        },
                        $control->choices
                    );
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    















    public static function unknown_key_refusal( object $manager, string $key ): \Easy_MCP_AI\MCP\Detailed_Tool_Error {
        $ids      = array_keys( self::editable_settings( $manager ) );
        $total    = count( $ids );
        $guidance = Theme_Ability_Guidance::assess();
        $details  = array();

        switch ( $guidance['case'] ) {
            case Theme_Ability_Guidance::CALLABLE:
                $format    = '"%1$s" is not a theme mod this tool can change. %2$s manages its settings through its own tools; the ones you can call are listed below.';
                $details[] = sprintf( 'Tools of the %s theme you can call:', $guidance['theme'] );
                $details[] = Theme_Ability_Guidance::tool_lines( $guidance['tools'] );
                $details[] = sprintf( 'This tool can still change %d Customizer settings (wp_get_theme_mods with include_editable: true lists them).', $total );
                break;
            case Theme_Ability_Guidance::NOT_ALLOWED:
                $format = '"%1$s" is not a theme mod this tool can change. %2$s provides its own tools for its settings, but they are not available to this API key or grant.';
                break;
            case Theme_Ability_Guidance::NOT_ENABLED:
                $format = '"%1$s" is not a theme mod this tool can change. %2$s provides abilities for its settings; an administrator can enable them in Easy MCP AI → Abilities.';
                break;
            default:
                $format = 0 === $total
                    ? '"%1$s" is not a theme mod this tool can change; the active theme registers none that you can edit here.'
                    : '"%1$s" is not a theme mod this tool can change. The keys it can change are listed below.';
        }

        if ( Theme_Ability_Guidance::CALLABLE !== $guidance['case'] ) {
            if ( 0 === $total ) {
                $details[] = 'The active theme registers no theme mod settings you can edit through the Customizer here.';
            } else {
                $more      = $total > self::VALID_KEY_LIST_LIMIT
                    ? sprintf( ' … and %d more (wp_get_theme_mods with include_editable: true lists them all with labels)', $total - self::VALID_KEY_LIST_LIMIT )
                    : '';
                $details[] = 'Valid keys: ' . implode( ', ', array_slice( $ids, 0, self::VALID_KEY_LIST_LIMIT ) ) . $more . '.';
            }
        }
        $details[] = self::REGISTERED_ONLY_IN_SCREEN_HINT;

        return new \Easy_MCP_AI\MCP\Detailed_Tool_Error(
            self::fit_message( $format, $key, $guidance['theme'] ),
            implode( "\n", $details )
        );
    }

    








    private static function fit_message( string $format, string $key, string $theme ): string {
        $measure = static function ( $text ) {
            return strlen( class_exists( '\Easy_MCP_AI\Config' ) ? \Easy_MCP_AI\Config::brand( $text ) : $text );
        };
        $message = sprintf( $format, $key, $theme );
        $cut     = mb_strlen( $key );
        while ( $measure( $message ) > \Easy_MCP_AI\MCP\Detailed_Tool_Error::MAX_MESSAGE && $cut > 1 ) {
            $cut     = max( 1, $cut - 8 );
            $message = sprintf( $format, mb_substr( $key, 0, $cut ) . '…', $theme );
        }
        return $message;
    }

    












    public static function stored_value( object $setting ) {
        $data = $setting->id_data();
        $mods = get_theme_mods();
        if ( ! is_array( $mods ) || ! isset( $mods[ $data['base'] ] ) ) {
            return null;
        }
        return self::walk( $mods[ $data['base'] ], $data['keys'] );
    }

    







    private static function walk( $node, array $keys ) {
        foreach ( $keys as $k ) {
            if ( ! is_array( $node ) || ! isset( $node[ $k ] ) ) {
                return null;
            }
            $node = $node[ $k ];
        }
        return $node;
    }

    



    public static function is_stored( object $setting ): bool {
        return null !== self::stored_value( $setting );
    }

    







    public static function effective_value( object $setting ) {
        $data = $setting->id_data();
        if ( empty( $data['keys'] ) ) {
            return get_theme_mod( $data['base'], $setting->default );
        }
        $node = self::walk( get_theme_mod( $data['base'], null ), $data['keys'] );
        return null === $node ? $setting->default : $node;
    }

    









    public static function validate_value( object $manager, object $setting, $value ): void {
        $id = (string) $setting->id;

        do_action( 'customize_save_validation_before', $manager );

        $validities = $manager->validate_setting_values(
            array( $id => $value ),
            array(
                'validate_capability' => true,
                'validate_existence'  => true,
            )
        );
        $validity   = isset( $validities[ $id ] ) ? $validities[ $id ] : true;
        if ( is_wp_error( $validity ) ) {
            
            
            
            
            $messages = array_filter( array_map( 'strval', $validity->get_error_messages() ) );
            $detail   = empty( $messages ) ? $validity->get_error_code() : implode( ' ', $messages );
            throw new \InvalidArgumentException( sprintf( 'WordPress rejected the value for "%s": %s', $id, html_entity_decode( wp_strip_all_tags( (string) $detail ), ENT_QUOTES, 'UTF-8' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    









    public static function validate_and_save( object $manager, object $setting, $value ): void {
        $id = (string) $setting->id;
        self::validate_value( $manager, $setting, $value );

        $manager->set_post_value( $id, $value );
        self::present_save_request( $manager->unsanitized_post_values( array( 'exclude_changeset' => true ) ) );

        do_action( 'customize_save', $manager );

        
        
        if ( false === $setting->save() ) {
            throw new \RuntimeException( sprintf( 'WordPress did not save "%s" (the value was invalid after sanitizing, or the setting refused the write).', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    



























    public static function publish_changeset( object $manager, int $changeset_id, object $setting, array $values, bool &$saved = false ): bool {
        $data  = $setting->id_data();
        $saved = false;
        $mark  = static function ( $saving ) use ( $setting, &$saved ) {
            if ( $saving === $setting ) {
                $saved = true;
            }
        };
        $hook  = 'customize_save_' . $data['base'];

        
        
        
        $manager->unsanitized_post_values();
        self::present_save_request( $values );

        add_action( $hook, $mark, PHP_INT_MAX );
        $wp_die_filter = self::trap_wp_die( self::WHILE_PUBLISHING );
        try {
            wp_publish_post( $changeset_id );
        } finally {
            self::release_wp_die( $wp_die_filter );
            remove_action( $hook, $mark, PHP_INT_MAX );
        }
        return $saved;
    }

    





    public static function after_save( object $manager ): void {
        do_action( 'customize_save_after', $manager );
    }
}
