<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

















trait Package_Guard {

    










    protected function refuse_when_file_mods_are_off( string $doing ): void {
        if ( ! wp_is_file_mod_allowed( 'capability_update_core' ) ) {
            throw new \RuntimeException(
                sprintf( 'File modifications are disabled on this site (DISALLOW_FILE_MODS in wp-config.php, or a file_mod_allowed filter), so %s over MCP or in wp-admin.', $doing ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    











    protected function refuse_unless_network_file_admin( string $network_cap, string $doing ): void {
        if ( is_multisite() && ( ! is_super_admin() || ! current_user_can( $network_cap ) ) ) {
            throw new \RuntimeException(
                sprintf( 'Sorry, on a multisite network %s requires a super admin with the %s capability: the files are shared by every site.', $doing, $network_cap ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    


























    protected function refuse_unless_direct_filesystem( string $context, string $where ): void {
        if ( ! function_exists( 'get_filesystem_method' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $checks = array( array( $context, $where ) );
        if ( rtrim( str_replace( '\\', '/', $context ), '/' ) !== rtrim( str_replace( '\\', '/', (string) WP_CONTENT_DIR ), '/' ) ) {
            $checks[] = array( (string) WP_CONTENT_DIR, 'wp-content' );
        }
        foreach ( $checks as $check ) {
            $method = get_filesystem_method( array(), $check[0] );
            if ( 'direct' !== $method ) {
                throw new \RuntimeException(
                    sprintf( 'WordPress cannot write to %s directly (filesystem method "%s"). This tool requires the "direct" method: the web server user must be able to write the files.', $check[1], is_string( $method ) ? $method : 'unknown' ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
        }
    }

    










    protected function parse_wporg_slug( array $arguments, string $kind ): string {
        $raw = isset( $arguments['slug'] ) && is_string( $arguments['slug'] ) ? trim( $arguments['slug'] ) : '';
        if ( '' === $raw ) {
            throw new \InvalidArgumentException( 'Missing required parameters: slug' );
        }
        self::refuse_url_zip_or_path( $raw, $kind );
        
        
        
        
        if ( strlen( $raw ) > 200 || ! preg_match( '/^[a-z0-9][a-z0-9_-]*\z/', $raw ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s slug "%s": a wordpress.org slug is lowercase letters, digits and hyphens.', $kind, substr( $raw, 0, 100 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $raw;
    }

    








    private static function refuse_url_zip_or_path( string $raw, string $kind ): void {
        if ( false !== strpos( $raw, '://' ) ) {
            throw new \InvalidArgumentException( sprintf( 'URLs are not accepted: pass the wordpress.org %s slug, e.g. "akismet". Installing from a URL or a zip file is not available over MCP.', $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( '.zip' === strtolower( substr( $raw, -4 ) ) ) {
            throw new \InvalidArgumentException( sprintf( 'Zip files are not accepted: pass the wordpress.org %s slug. Installing from a zip file or a URL is not available over MCP.', $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( false !== strpos( $raw, '/' ) || false !== strpos( $raw, '\\' ) ) {
            throw new \InvalidArgumentException( sprintf( 'Paths are not accepted: pass the wordpress.org %s slug (the last part of its wordpress.org address), e.g. "%s".', $kind, 'plugin' === $kind ? 'akismet' : 'twentytwentyfive' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    protected function parse_package_version( array $arguments, bool $allow_dev ): ?string {
        if ( ! isset( $arguments['version'] ) ) {
            return null;
        }
        $raw = $arguments['version'];
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            throw new \InvalidArgumentException( $allow_dev ? '"version" must be a version string such as "5.3.2", or "dev". Omit it to install the latest version.' : '"version" must be a version string such as "1.2". Omit it to install the latest version.' );
        }
        $version = trim( $raw );
        if ( ! $allow_dev && 'dev' === $version ) {
            throw new \InvalidArgumentException( 'wordpress.org themes have no development version; pass a listed version such as "1.2", or omit "version".' );
        }
        if ( strlen( $version ) > 64 || ! preg_match( '/^[0-9A-Za-z][0-9A-Za-z.+_-]*\z/', $version ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid version "%s". Pass a version wordpress.org lists, such as "1.2.3".', substr( $version, 0, 64 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $version;
    }

    









    protected function wporg_information( string $kind, string $slug, bool $versions ) {
        $fields = array( 'sections' => false );
        if ( $versions ) {
            $fields['versions'] = true;
        }
        if ( 'plugin' === $kind ) {
            if ( ! function_exists( 'plugins_api' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }
            return plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => $fields ) );
        }
        if ( ! function_exists( 'themes_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }
        return themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => $fields ) );
    }

    





















    protected function wporg_package( string $kind, string $slug, ?string $version ): array {
        $info   = $this->wporg_answer( $kind, $slug, $version );
        $name   = self::wporg_name( $info );
        $prefix = 'https://downloads.wordpress.org/' . $kind . '/';

        list( $package, $listed ) = null === $version
            ? self::wporg_latest_package( $info )
            : self::wporg_versioned_package( $info, $kind, $slug, $version );
        
        
        if ( 0 === strpos( $package, 'http://downloads.wordpress.org/' ) ) {
            $package = 'https://' . substr( $package, 7 );
        }
        if ( 0 !== strpos( $package, $prefix ) ) {
            throw new \RuntimeException( sprintf( 'The %s "%s" is not offered from downloads.wordpress.org (a plugin on this site answers wordpress.org information requests for it), so it was not installed.', $kind, $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array(
            'package' => $package,
            'name'    => $name,
            'version' => $listed,
        );
    }

    









    private function wporg_answer( string $kind, string $slug, ?string $version ): object {
        $info = $this->wporg_information( $kind, $slug, null !== $version );
        if ( is_wp_error( $info ) ) {
            $why = trim( $this->redact_urls( wp_strip_all_tags( $info->get_error_message() ) ) );
            if ( in_array( $info->get_error_code(), array( 'plugins_api_failed', 'themes_api_failed' ), true ) && preg_match( '/not found/i', $why ) ) {
                throw new \RuntimeException( sprintf( 'The %s "%s" was not found in the wordpress.org %s directory. Pass the slug from its wordpress.org address.', $kind, $slug, $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            throw new \RuntimeException( sprintf( 'wordpress.org did not answer for the %s "%s": %s', $kind, $slug, $why ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( is_array( $info ) ) {
            $info = (object) $info;
        }
        if ( ! is_object( $info ) ) {
            throw new \RuntimeException( sprintf( 'wordpress.org returned no information for the %s "%s", so nothing was installed.', $kind, $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $info;
    }

    






    private static function wporg_name( object $info ): string {
        return isset( $info->name ) && is_scalar( $info->name ) ? trim( wp_strip_all_tags( html_entity_decode( (string) $info->name, ENT_QUOTES, 'UTF-8' ) ) ) : '';
    }

    





    private static function wporg_latest_package( object $info ): array {
        $package = isset( $info->download_link ) && is_string( $info->download_link ) ? $info->download_link : '';
        $listed  = isset( $info->version ) && is_scalar( $info->version ) ? (string) $info->version : '';
        return array( $package, $listed );
    }

    











    private static function wporg_versioned_package( object $info, string $kind, string $slug, string $version ): array {
        $versions = self::wporg_version_list( $info );
        $key      = 'dev' === $version ? 'trunk' : $version;
        if ( 'trunk' === $version || ! isset( $versions[ $key ] ) || ! is_string( $versions[ $key ] ) ) {
            $latest = array_slice( array_values( array_diff( array_map( 'strval', array_keys( $versions ) ), array( 'trunk' ) ) ), -6 );
            throw new \RuntimeException( sprintf( 'wordpress.org lists no version "%s" of the %s "%s".%s', $version, $kind, $slug, empty( $latest ) ? '' : ' Latest listed: ' . implode( ', ', $latest ) . '.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array( $versions[ $key ], 'dev' === $version ? 'dev' : $version );
    }

    






    private static function wporg_version_list( object $info ): array {
        return ( isset( $info->versions ) && ( is_array( $info->versions ) || is_object( $info->versions ) ) ) ? (array) $info->versions : array();
    }

    















    protected function interpret_install_result( $result, \WP_Ajax_Upgrader_Skin $skin, string $failed ): void {
        if ( is_wp_error( $result ) ) {
            throw new \RuntimeException( $this->upgrader_error_text( $result ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( is_wp_error( $skin->result ) ) {
            throw new \RuntimeException( $this->upgrader_error_text( $skin->result ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $skin->get_errors()->has_errors() ) {
            throw new \RuntimeException( $this->redact_urls( $this->relative_paths( wp_strip_all_tags( (string) $skin->get_error_messages() ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( null === $result || false === $result ) {
            throw new \RuntimeException( $this->redact_urls( $this->relative_paths( $this->filesystem_failure_message() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( true !== $result ) {
            throw new \RuntimeException( $failed ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    








    protected function upgrader_error_text( \WP_Error $error ): string {
        $text = wp_strip_all_tags( (string) $error->get_error_message() );
        $data = $error->get_error_data();
        if ( is_string( $data ) && '' !== trim( $data ) ) {
            $text .= ' ' . wp_strip_all_tags( $data );
        }
        $text = trim( $this->redact_urls( $this->relative_paths( $text ) ) );
        return '' === $text ? 'WordPress refused the operation without giving a reason.' : $text;
    }

    






    protected function relative_paths( string $text ): string {
        $root = rtrim( str_replace( '\\', '/', (string) ABSPATH ), '/' ) . '/';
        if ( '/' === $root ) {
            return $text;
        }
        return str_replace( array( $root, str_replace( '/', '\\', $root ) ), '', $text );
    }

    





    protected function filesystem_failure_message(): string {
        global $wp_filesystem;
        if ( is_object( $wp_filesystem ) && isset( $wp_filesystem->errors ) && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
            return (string) $wp_filesystem->errors->get_error_message();
        }
        return 'Unable to connect to the filesystem. Please confirm your credentials.';
    }

    







    protected function new_upgrader_skin(): \WP_Ajax_Upgrader_Skin {
        return new \WP_Ajax_Upgrader_Skin();
    }

    













    protected function refuse_own_plugin_files( string $plugin, string $why ): void {
        $own = defined( 'EASY_MCP_AI_PLUGIN_BASENAME' ) ? (string) EASY_MCP_AI_PLUGIN_BASENAME : '';
        if ( '' === $own ) {
            return;
        }
        $same_folder = false !== strpos( $own, '/' ) && false !== strpos( $plugin, '/' ) && dirname( $own ) === dirname( $plugin );
        if ( $plugin === $own || $same_folder ) {
            throw new \RuntimeException( sprintf( 'Refused: %s is Easy MCP AI\'s own %s, and %s. Use wp-admin → Plugins instead.', $plugin, $plugin === $own ? 'plugin file' : 'folder', $why ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    protected function installed_plugin( string $plugin ): array {
        $installed = get_plugins();
        if ( ! isset( $installed[ $plugin ] ) || ! is_array( $installed[ $plugin ] ) ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" is not installed. Use wp_list_plugins to see installed plugins.', $plugin ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $installed[ $plugin ];
    }

    




    protected function plugin_label( string $plugin, array $data ): string {
        return ! empty( $data['Name'] ) && is_string( $data['Name'] ) ? $data['Name'] : $plugin;
    }

    

















    protected function refuse_required_plugin( string $plugin, string $label ): void {
        $dependents = $this->dependents_of( $plugin );
        if ( ! empty( $dependents ) ) {
            sort( $dependents );
            $shown = array_slice( $dependents, 0, 3 );
            throw new \RuntimeException( sprintf( 'Plugin "%s" is required by %s%s. Remove those first, as the Plugins screen requires.', $label, implode( ', ', $shown ), count( $dependents ) > 3 ? ' and ' . ( count( $dependents ) - 3 ) . ' more' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    private function dependents_of( string $plugin ): array {
        $installed  = get_plugins();
        $slug       = $this->dependency_slug( $plugin );
        $own_needs  = isset( $installed[ $plugin ]['RequiresPlugins'] ) ? $this->dependency_slugs( $installed[ $plugin ]['RequiresPlugins'] ) : array();
        $dependents = array();
        foreach ( $installed as $file => $header ) {
            if ( $file === $plugin || ! is_array( $header ) || empty( $header['RequiresPlugins'] ) ) {
                continue;
            }
            if ( ! in_array( $slug, $this->dependency_slugs( $header['RequiresPlugins'] ), true ) ) {
                continue;
            }
            if ( in_array( $this->dependency_slug( (string) $file ), $own_needs, true ) ) {
                continue;
            }
            $dependents[] = (string) $file;
        }
        return $dependents;
    }

    





    private function dependency_slug( string $plugin ): string {
        if ( 'hello.php' === $plugin ) {
            return 'hello-dolly';
        }
        return false !== strpos( $plugin, '/' ) ? dirname( $plugin ) : str_replace( '.php', '', $plugin );
    }

    






    private function dependency_slugs( $header ): array {
        $slugs = array();
        foreach ( explode( ',', is_string( $header ) ? $header : '' ) as $slug ) {
            
            $slug = apply_filters( 'wp_plugin_dependencies_slug', trim( $slug ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, applied as core applies it.
            if ( is_string( $slug ) && preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug ) ) {
                $slugs[] = $slug;
            }
        }
        return array_values( array_unique( $slugs ) );
    }

    







    protected function describe_removal( array $arguments, string $format ): string {
        try {
            $plugin = $this->parse_plugin_argument( $arguments );
            $this->load_plugin_admin_functions();
            $installed = get_plugins();
        } catch ( \Throwable $e ) {
            return Base_Tool::generic_description( $this, $arguments );
        }
        $data    = isset( $installed[ $plugin ] ) && is_array( $installed[ $plugin ] ) ? $installed[ $plugin ] : array();
        $version = ! empty( $data['Version'] ) ? ' ' . $data['Version'] : '';
        return sprintf( $format, '"' . $this->plugin_label( $plugin, $data ) . '"' . $version, $plugin );
    }

    









    protected function without_output( callable $run ): array {
        $level = ob_get_level();
        ob_start();
        try {
            $value = $run();
        } finally {
            $printed = '';
            while ( ob_get_level() > $level ) {
                $printed = (string) ob_get_clean() . $printed;
            }
        }
        return array( $value, $printed );
    }
}
