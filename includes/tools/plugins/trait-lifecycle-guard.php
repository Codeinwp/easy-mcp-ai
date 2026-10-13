<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












trait Lifecycle_Guard {

    





    protected function load_plugin_admin_functions() {
        if ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'is_plugin_active_for_network' ) || ! function_exists( 'validate_plugin' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    






    protected function load_upgrader() {
        if ( ! function_exists( 'request_filesystem_credentials' ) || ! function_exists( 'show_message' ) ) {
            require_once ABSPATH . 'wp-admin/includes/admin.php';
        }
        if ( ! class_exists( 'Plugin_Upgrader', false ) || ! class_exists( 'Theme_Upgrader', false ) || ! class_exists( 'WP_Ajax_Upgrader_Skin', false ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
    }

    










    protected function parse_plugin_argument( array $arguments ) {
        $raw = isset( $arguments['plugin'] ) ? $arguments['plugin'] : '';
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            throw new \InvalidArgumentException( 'Missing required parameters: plugin' );
        }
        $plugin = plugin_basename( trim( $raw ) );
        if ( '' === $plugin || 0 !== validate_file( $plugin ) || '.php' !== substr( $plugin, -4 ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Invalid plugin "%s". Pass the plugin file relative to the plugins directory, e.g. "hello-dolly/hello.php", as returned in the `plugin` field of wp_list_plugins.', $raw ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return $plugin;
    }

    



    protected function parse_network_wide( array $arguments ) {
        return isset( $arguments['network_wide'] ) && rest_sanitize_boolean( $arguments['network_wide'] );
    }

    







    protected function is_own_plugin( $plugin ) {
        $own = defined( 'EASY_MCP_AI_PLUGIN_BASENAME' ) ? (string) EASY_MCP_AI_PLUGIN_BASENAME : '';
        return '' !== $own && $plugin === $own;
    }

    









    protected function assert_network_wide_allowed() {
        if ( ! is_multisite() ) {
            throw new \RuntimeException( 'network_wide is only available on a multisite network. On a single site, omit it (or pass false).' );
        }
        if ( ! is_super_admin() || ! current_user_can( 'manage_network_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, network-wide plugin changes require a super admin with the manage_network_plugins capability.' );
        }
    }

    









    protected function assert_network_update_allowed( $network_cap ) {
        if ( is_multisite() && ( ! is_super_admin() || ! current_user_can( $network_cap ) ) ) {
            throw new \RuntimeException(
                sprintf( 'Sorry, on a multisite network updates require a super admin with the %s capability.', $network_cap ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    









    protected function assert_file_mods_allowed( $what ) {
        if ( ! wp_is_file_mod_allowed( 'capability_update_core' ) ) {
            throw new \RuntimeException(
                sprintf( 'File modifications are disabled on this site (DISALLOW_FILE_MODS is set in wp-config.php, or a file_mod_allowed filter refuses them), so %s cannot be updated over MCP or in wp-admin.', $what ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    












    protected function assert_filesystem_available( string $context ) {
        if ( 'direct' === get_filesystem_method( array(), $context ) ) {
            return;
        }
        ob_start();
        $stored = request_filesystem_credentials( self_admin_url(), '', false, $context );
        ob_end_clean();
        if ( ! $stored ) {
            
            
            throw new \RuntimeException( 'Filesystem credentials required: WordPress cannot write here directly and no FTP/SSH credentials are stored (FTP_HOST, FTP_USER, FTP_PASS in wp-config.php). wp-admin would prompt; MCP cannot.' );
        }
    }

    




    protected function raise_time_limit() {
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- may be disabled by the host; a failure only leaves the default limit.
        }
    }

    

















    protected function interpret_upgrade_result( $result, \WP_Ajax_Upgrader_Skin $skin, $upgrader, $key, $failed ) {
        if ( is_wp_error( $skin->result ) ) {
            throw new \RuntimeException( $this->redact_urls( $skin->result->get_error_message() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $skin->get_errors()->has_errors() ) {
            throw new \RuntimeException( $this->redact_urls( (string) $skin->get_error_messages() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( is_array( $result ) && ! empty( $result[ $key ] ) ) {
            if ( true === $result[ $key ] ) {
                $up_to_date = isset( $upgrader->strings['up_to_date'] ) ? $upgrader->strings['up_to_date'] : 'Already up to date.';
                throw new \RuntimeException( $up_to_date ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return $result[ $key ];
        }
        if ( false === $result ) {
            throw new \RuntimeException( $this->redact_urls( $this->filesystem_error_message() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        throw new \RuntimeException( $failed ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    






    private function filesystem_error_message() {
        global $wp_filesystem;
        if ( is_object( $wp_filesystem ) && isset( $wp_filesystem->errors ) && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
            return $wp_filesystem->errors->get_error_message();
        }
        return 'Unable to connect to the filesystem. Please confirm your credentials.';
    }

    





























    protected function redact_urls( $text ) {
        
        
        
        
        $redacted = preg_replace_callback(
            '~(?:\b([a-z][a-z0-9+.\-]*):)?//(?:[^\s/?#<>"\']*@)?([a-z0-9](?:[a-z0-9.\-]*[a-z0-9])?|\[[0-9a-f:.]+\])(:\d+)?(?:(?!&\#\d+;)[^\s<>"\'])*~i',
            function ( $m ) {
                $scheme = '' !== $m[1] ? strtolower( $m[1] ) . ':' : '';
                $port   = isset( $m[3] ) ? $m[3] : '';
                return $scheme . '//' . $m[2] . $port . '/[redacted]';
            },
            (string) $text
        );
        return null === $redacted ? '[message withheld: it could not be checked for credentials]' : $redacted;
    }

    








    protected function upgrade_messages( \WP_Ajax_Upgrader_Skin $skin ) {
        $messages = method_exists( $skin, 'get_upgrade_messages' ) ? (array) $skin->get_upgrade_messages() : array();
        $out      = array();
        foreach ( array_slice( $messages, -20 ) as $message ) {
            $line = trim( $this->redact_urls( wp_strip_all_tags( (string) $message ) ) );
            if ( '' === $line ) {
                continue;
            }
            $out[] = strlen( $line ) > 300 ? substr( $line, 0, 297 ) . '...' : $line;
        }
        return $out;
    }

    







    protected function update_field( $entry, $field ) {
        if ( is_object( $entry ) && isset( $entry->$field ) ) {
            return $entry->$field;
        }
        if ( is_array( $entry ) && isset( $entry[ $field ] ) ) {
            return $entry[ $field ];
        }
        return null;
    }

    








    protected function update_response( $transient ): array {
        return ( is_object( $transient ) && isset( $transient->response ) && is_array( $transient->response ) ) ? $transient->response : array();
    }

    







    protected function is_active_in_scope( string $plugin, bool $network_wide ): bool {
        return $network_wide ? is_plugin_active_for_network( $plugin ) : is_plugin_active( $plugin );
    }
}
