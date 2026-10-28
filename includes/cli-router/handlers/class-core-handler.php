<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Cli_Exit;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Core_Handler extends Command_Handler {

    const NETWORK_SITE_CAP = 50;

    const UPDATE_FIELDS = array( 'version', 'update_type', 'package_url' );

    const CHECKSUMS_ENDPOINT = 'https://api.wordpress.org/core/checksums/1.0/';

    const CAPABILITY = 'update_core';

    const REASON_FILE_MODS  = "File modifications are disabled on this site (DISALLOW_FILE_MODS in wp-config.php, or a file_mod_allowed filter): WordPress then withholds the %2\$s capability from every user, administrators included, so wp_cli_run does not run 'wp %1\$s'.";
    const REASON_NETWORK_CAP = "wp_cli_run upgrades at most %1\$d sites in one 'wp core update-db --network' command, and this network has %2\$d sites to upgrade. Upgrade them from Network Admin › Upgrade Network in wp-admin.";
    const NOTE_VERSION_CHECK = "WordPress's update check ran (wp_version_check()) and may have refreshed the %s site transient, a cache WordPress refreshes on its own; nothing else was written.";

    const NOT_CAPTURED_UPGRADE = 'Database upgrade: change history does not record the table changes wp_upgrade() makes (dbDelta) and records its option writes only where its capture settings allow; an upgrade cannot be undone from change history.';

    public function preflight_update_core( Invocation $inv, Context $ctx ): void {
        self::require_update_core( $inv->command, $ctx );
    }

    public function preflight_update_db( Invocation $inv, Context $ctx ): void {
        $network = Read_Value::flag( $inv->assoc, 'network' );
        if ( $network && ! \is_multisite() ) {
            $ctx->out->error( 'This is not a multisite installation.' );
        }
        self::require_update_core( $inv->command, $ctx );
        if ( $network ) {
            $ctx->require_network( 'upgrade_network' );
            $count = (int) $this->wp( 'network_blog_count' );
            if ( $count > self::NETWORK_SITE_CAP ) {
                $ctx->out->error( sprintf( self::REASON_NETWORK_CAP, self::NETWORK_SITE_CAP, $count ) );
            }
        }
    }

    public function is_dry_run( Invocation $inv ): bool {
        return (bool) Read_Value::flag( $inv->assoc, 'dry-run' );
    }

    public static function require_update_core( string $command, Context $ctx ): void {
        if ( function_exists( 'wp_is_file_mod_allowed' ) && ! \wp_is_file_mod_allowed( 'capability_update_core' ) ) {
            $ctx->out->error( sprintf( self::REASON_FILE_MODS, $command, self::CAPABILITY ) );
        }
        $ctx->require_cap( self::CAPABILITY );
    }

    public function check_update( Invocation $inv, Context $ctx ): void {
        $assoc  = $inv->assoc;
        $format = Read_Value::flag( $assoc, 'format', 'table' );

        $updates = $this->get_updates( $assoc, $ctx );

        if ( $updates || 'table' !== $format ) {
            $updates   = array_reverse( $updates );
            $formatter = $this->formatter( $ctx, $assoc, self::UPDATE_FIELDS );
            $formatter->display_items( $updates );
        } else {
            $ctx->out->success( 'WordPress is at the latest version.' );
        }
    }

    public function is_installed( Invocation $inv, Context $ctx ): void {
        if ( $this->wp( 'is_blog_installed' ) && ( ! Read_Value::flag( $inv->assoc, 'network' ) || \is_multisite() ) ) {
            $ctx->out->halt( 0 );
        }
        $ctx->out->halt( 1 );
    }

    public function version( Invocation $inv, Context $ctx ): void {
        $details = $this->get_wp_details( $ctx, 'installation' );

        if ( ! Read_Value::flag( $inv->assoc, 'extra' ) ) {
            $ctx->out->line( (string) $details['wp_version'] );
            return;
        }

        $match                   = array();
        $found_version           = preg_match( '/(\d)(\d+)-/', (string) $details['tinymce_version'], $match );
        $human_readable_tiny_mce = $found_version ? "{$match[1]}.{$match[2]}" : '';

        $values = array(
            'wp-version'    => $details['wp_version'],
            'db-version'    => $details['wp_db_version'],
            'local-package' => empty( $details['wp_local_package'] ) ? 'en_US' : $details['wp_local_package'],
            'mce-version'   => $human_readable_tiny_mce ? "{$human_readable_tiny_mce} ({$details['tinymce_version']})" : $details['tinymce_version'],
        );
        foreach ( $values as $key => $value ) {
            $values[ $key ] = htmlspecialchars( (string) $value, ENT_COMPAT, 'UTF-8' );
        }
        $ctx->out->out(
            'WordPress version: ' . $values['wp-version'] . "\n"
            . 'Database revision: ' . $values['db-version'] . "\n"
            . 'TinyMCE version:   ' . $values['mce-version'] . "\n"
            . 'Package language:  ' . $values['local-package'] . "\n"
        );
    }

    public function update_db( Invocation $inv, Context $ctx ): void {
        $network = Read_Value::flag( $inv->assoc, 'network' );
        if ( $network && ! \is_multisite() ) {
            $ctx->out->error( 'This is not a multisite installation.' );
        }

        $dry_run = (bool) Read_Value::flag( $inv->assoc, 'dry-run' );
        if ( $dry_run ) {
            $ctx->out->log( 'Performing a dry run, with no database modification.' );
        }

        if ( $network ) {
            $this->update_db_network( $dry_run, $ctx );
            return;
        }

        $result = $this->wp( 'update_db_site', array( $dry_run ) );
        if ( ! empty( $result['changed'] ) ) {
            $ctx->mark_changed();
            $ctx->not_captured( self::NOT_CAPTURED_UPGRADE );
        }
        $ctx->out->success( (string) $result['success'] );
    }

    public function verify_checksums( Invocation $inv, Context $ctx ): void {
        list( $wp_version, $locale, $include_root, $exclude ) = $this->checksum_args( $inv->assoc, $ctx );

        $checksums  = $this->core_checksums( $wp_version, $locale, $ctx );
        $has_errors = $this->verify_files( $checksums, $exclude, $ctx );
        $this->warn_additional_files( $checksums, $include_root, $exclude, $ctx );

        if ( ! $has_errors ) {
            $ctx->out->success( 'WordPress installation verifies against checksums.' );
        } else {
            $ctx->out->error( "WordPress installation doesn't verify against checksums." );
        }
    }

    private function checksum_args( array $assoc_args, Context $ctx ): array {
        $wp_version   = '';
        $locale       = '';
        $include_root = ! empty( $assoc_args['include-root'] );
        $exclude      = array();

        if ( ! empty( $assoc_args['version'] ) ) {
            $wp_version = $assoc_args['version'];
        }
        if ( ! empty( $assoc_args['locale'] ) ) {
            $locale = $assoc_args['locale'];
        }
        if ( ! empty( $assoc_args['exclude'] ) ) {
            $exclude = explode( ',', (string) Read_Value::flag( $assoc_args, 'exclude', '' ) );
        }

        if ( empty( $wp_version ) ) {
            $details    = $this->get_wp_details( $ctx, 'install' );
            $wp_version = $details['wp_version'];
            if ( empty( $locale ) ) {
                $locale = $details['wp_local_package'];
            }
        }

        return array( (string) $wp_version, empty( $locale ) ? 'en_US' : (string) $locale, $include_root, $exclude );
    }

    private function core_checksums( string $wp_version, string $locale, Context $ctx ): array {
        $response = $this->wp( 'core_checksums', array( $wp_version, $locale ) );
        if ( isset( $response['exception'] ) ) {
            $ctx->out->error( (string) $response['exception'] );
        }
        $checksums = isset( $response['checksums'] ) ? $response['checksums'] : false;
        if ( ! is_array( $checksums ) ) {
            $ctx->out->error( "Couldn't get checksums from WordPress.org." );
        }
        return $checksums;
    }

    private static function files_to_hash( array $checksums, array $exclude ): array {
        $to_hash = array();
        foreach ( $checksums as $file => $checksum ) {
            $file = (string) $file;
            if ( 'wp-content' === substr( $file, 0, 10 ) || in_array( $file, $exclude, true ) ) {
                continue;
            }
            $to_hash[] = $file;
        }
        return $to_hash;
    }

    private function verify_files( array $checksums, array $exclude, Context $ctx ): bool {
        $hashes = (array) $this->wp( 'file_hashes', array( self::files_to_hash( $checksums, $exclude ) ) );

        $has_errors = false;
        foreach ( $checksums as $file => $checksum ) {
            $file = (string) $file;
            if ( 'wp-content' === substr( $file, 0, 10 ) ) {
                continue;
            }
            if ( in_array( $file, $exclude, true ) ) {
                continue;
            }
            $md5 = array_key_exists( $file, $hashes ) ? $hashes[ $file ] : false;
            if ( false === $md5 ) {
                $ctx->out->warning( "File doesn't exist: {$file}" );
                $has_errors = true;
                continue;
            }
            if ( $md5 !== $checksum ) {
                $ctx->out->warning( "File doesn't verify against checksum: {$file}" );
                $has_errors = true;
            }
        }
        return $has_errors;
    }

    private function warn_additional_files( array $checksums, bool $include_root, array $exclude, Context $ctx ): void {
        $core_checksums_files = array_filter(
            array_map( 'strval', array_keys( $checksums ) ),
            static function ( $file ) use ( $include_root ) {
                return self::filter_file( $file, $include_root );
            }
        );
        $walked = $this->wp( 'walk_files', array( $include_root ) );
        if ( isset( $walked['error'] ) ) {
            $ctx->out->error( (string) $walked['error'] );
        }
        $additional_files = array_diff( (array) $walked['files'], $core_checksums_files );
        foreach ( $additional_files as $additional_file ) {
            if ( in_array( $additional_file, $exclude, true ) ) {
                continue;
            }
            $ctx->out->warning( "File should not exist: {$additional_file}" );
        }
    }

    public function describe_update_db( Invocation $inv ): array {
        $dry_run = (bool) Read_Value::flag( $inv->assoc, 'dry-run' );
        if ( ! Read_Value::flag( $inv->assoc, 'network' ) ) {
            $versions = (array) $this->wp( 'db_versions' );
            return array( self::describe_versions( 'Database of this site', $versions, $dry_run ) );
        }
        $lines = array();
        foreach ( (array) $this->wp( 'network_blogs' ) as $blog ) {
            $versions = (array) $this->wp( 'blog_db_versions', array( (int) $blog['blog_id'] ) );
            $lines[]  = self::describe_versions( sprintf( 'Database of site %d (%s%s)', (int) $blog['blog_id'], $blog['domain'], $blog['path'] ), $versions, $dry_run );
        }
        if ( ! $dry_run ) {
            $lines[] = sprintf( 'Network option wpmu_upgrade_site → db version %d on each network, once every site above upgraded', (int) ( $this->wp( 'db_versions' )['target'] ?? 0 ) );
        }
        return $lines;
    }

    private static function describe_versions( string $label, array $versions, bool $dry_run ): string {
        $current = (int) ( $versions['current'] ?? 0 );
        $target  = (int) ( $versions['target'] ?? 0 );
        if ( $current === $target ) {
            return sprintf( '%s: db version %d → nothing to do (already the latest)', $label, $target );
        }
        return sprintf( '%s: db version %d → %s to db version %d', $label, $current, $dry_run ? 'dry run, nothing written; would upgrade' : 'upgrade (wp_upgrade())', $target );
    }

    private function get_updates( array $assoc_args, Context $ctx ): array {
        $force_check = (bool) Read_Value::flag( $assoc_args, 'force-check' );
        $from_api    = $this->wp( 'version_check', array( $force_check ) );
        $ctx->note( sprintf( self::NOTE_VERSION_CHECK, 'update_core' ) );
        if ( ! $from_api ) {
            return array();
        }

        $compare_version = str_replace( '-src', '', (string) $this->wp( 'running_wp_version' ) );
        $updates         = self::newest_offers( (array) $from_api['updates'], $compare_version );

        foreach ( array( 'major', 'minor' ) as $type ) {
            if ( true === Read_Value::flag( $assoc_args, $type ) ) {
                return ! empty( $updates[ $type ] ) ? array( $updates[ $type ] ) : array();
            }
        }
        return array_values( $updates );
    }

    private static function newest_offers( array $offers, string $compare_version ): array {
        $updates = array(
            'major' => false,
            'minor' => false,
        );
        foreach ( $offers as $offer ) {
            $update_type = self::wordpress_update_type( (string) $offer['version'], $compare_version );
            if ( ! $update_type ) {
                continue;
            }

            if ( ! empty( $updates[ $update_type ] ) && ! version_compare( (string) $offer['version'], (string) $updates[ $update_type ]['version'], '>' ) ) {
                continue;
            }

            $updates[ $update_type ] = array(
                'version'     => $offer['version'],
                'update_type' => $update_type,
                'package_url' => ! empty( $offer['partial'] ) ? $offer['partial'] : $offer['full'],
            );
        }

        foreach ( $updates as $type => $value ) {
            if ( empty( $value ) ) {
                unset( $updates[ $type ] );
            }
        }
        return $updates;
    }

    private static function wordpress_update_type( string $new_version, string $original_version ): string {
        $update_type = self::named_sem_ver( $new_version, $original_version );

        if ( 'minor' === $update_type ) {
            return 'major';
        }
        if ( 'patch' === $update_type ) {
            return 'minor';
        }
        return $update_type;
    }

    public static function named_sem_ver( string $new_version, string $original_version ): string {
        if ( ! version_compare( $new_version, $original_version, '>' ) ) {
            return '';
        }

        $original = self::original_major_minor( $original_version );
        $new      = self::new_major_minor( $new_version );
        if ( null === $original || null === $new ) {
            return '';
        }
        list( $major, $minor )         = $original;
        list( $new_major, $new_minor ) = $new;

        if ( null !== $minor && $new_major === $major && $new_minor === $minor ) {
            return 'patch';
        }
        if ( $new_major === $major ) {
            return 'minor';
        }
        return 'major';
    }

    private static function original_major_minor( string $original_version ): ?array {
        $parts = explode( '-', $original_version );
        $bits  = explode( '.', $parts[0] );
        $major = $bits[0];
        $minor = isset( $bits[1] ) ? $bits[1] : null;
        if ( ! ctype_digit( (string) $major ) || ( null !== $minor && ! ctype_digit( (string) $minor ) ) ) {
            return null;
        }
        return array( (int) $major, null === $minor ? null : (int) $minor );
    }

    private static function new_major_minor( string $new_version ): ?array {
        if ( ! preg_match( '/^v?(\d+)(?:\.(\d+))?(?:\.\d+){0,2}(?:[._-]?(?:stable|beta|b|rc|alpha|a|patch|pl|p)(?:[.-]?\d+)*)?(?:[.-]?dev)?\z/i', $new_version, $m ) ) {
            return null;
        }
        return array( (int) $m[1], isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0 );
    }

    public static function filter_file( string $filepath, bool $include_root ): bool {
        if ( true === $include_root ) {
            return ( 1 !== preg_match( '/^(\.htaccess$|\.maintenance$|wp-config\.php$|wp-content\/)/', $filepath ) );
        }

        return ( 0 === strpos( $filepath, 'wp-admin/' )
            || 0 === strpos( $filepath, 'wp-includes/' )
            || 1 === preg_match( '/^wp-(?!config\.php)([^\/]*)$/', $filepath )
        );
    }

    private function get_wp_details( Context $ctx, string $noun ): array {
        $version_content = $this->wp( 'version_file' );
        if ( null === $version_content ) {
            $ctx->out->error(
                "This does not seem to be a WordPress {$noun}.\n" .
                'Pass --path=`path/to/wordpress` or run `wp core download`.'
            );
        }
        $result = array();
        foreach ( array( 'wp_version', 'wp_db_version', 'tinymce_version', 'wp_local_package' ) as $var_name ) {
            $result[ $var_name ] = self::find_var( $var_name, (string) $version_content );
        }
        return $result;
    }

    private static function find_var( string $var_name, string $code ): ?string {
        $start = strpos( $code, '$' . $var_name . ' = ' );
        if ( ! $start ) {
            return null;
        }
        $start = $start + strlen( $var_name ) + 3;
        $end   = strpos( $code, ';', $start );
        $value = substr( $code, $start, false === $end ? 0 : $end - $start );
        return trim( (string) $value, " '" );
    }

    private function update_db_network( bool $dry_run, Context $ctx ): void {
        $success  = 0;
        $total    = 0;
        $site_ids = array();
        foreach ( (array) $this->wp( 'network_blogs' ) as $blog ) {
            ++$total;
            $site_ids[] = (int) $blog['site_id'];
            $url        = $blog['domain'] . $blog['path'];
            $result     = (array) $this->wp( 'blog_update_db', array( (int) $blog['blog_id'], $dry_run ) );
            if ( ! empty( $result['ok'] ) ) {
                if ( isset( $result['success'] ) && '' !== (string) $result['success'] ) {
                    $message = rtrim( (string) $result['success'], '.' ) . " on {$url}";
                } else {
                    $message = "Database upgraded successfully on {$url}";
                }
                if ( ! empty( $result['changed'] ) ) {
                    $ctx->mark_changed();
                    $ctx->not_captured( self::NOT_CAPTURED_UPGRADE );
                }
                $ctx->out->log( $message );
                ++$success;
            } else {
                $ctx->out->warning( "Database failed to upgrade on {$url}" );
            }
        }
        if ( ! $dry_run && $total && $success === $total ) {
            if ( $this->wp( 'mark_network_upgraded', array( array_values( array_unique( $site_ids ) ) ) ) ) {
                $ctx->mark_changed();
            }
        }
        $ctx->out->success( "WordPress database upgraded on {$success}/{$total} sites." );
    }

    protected function wp( string $call, array $args = array() ) {
        return $this->{'call_' . $call}( ...$args );
    }

    protected function call_is_blog_installed(): bool {
        return (bool) \is_blog_installed();
    }

    protected function call_running_wp_version(): string {
        return isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
    }

    protected function call_version_check( bool $force ) {
        if ( ! function_exists( 'wp_version_check' ) ) {
            require_once ABSPATH . WPINC . '/update.php';
        }
        \wp_version_check( array(), $force );
        $from_api = \get_site_transient( 'update_core' );
        if ( ! $from_api ) {
            return false;
        }
        $updates = array();
        $offers  = ( is_object( $from_api ) && isset( $from_api->updates ) && is_array( $from_api->updates ) ) ? $from_api->updates : array();
        foreach ( $offers as $offer ) {
            $updates[] = self::offer_row( is_object( $offer ) ? $offer : new \stdClass() );
        }
        return array( 'updates' => $updates );
    }

    private static function offer_row( object $offer ): array {
        $packages = isset( $offer->packages ) && is_object( $offer->packages ) ? $offer->packages : new \stdClass();
        return array(
            'version'  => isset( $offer->version ) ? $offer->version : null,
            'locale'   => isset( $offer->locale ) ? $offer->locale : null,
            'response' => isset( $offer->response ) ? $offer->response : null,
            'partial'  => isset( $packages->partial ) ? $packages->partial : null,
            'full'     => isset( $packages->full ) ? $packages->full : null,
        );
    }

    protected function call_version_file(): ?string {
        $path = ABSPATH . 'wp-includes/version.php';
        if ( ! is_readable( $path ) ) {
            return null;
        }
        return (string) file_get_contents( $path, false, null, 6, 2048 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WP-CLI's exact read of a local file.
    }

    protected function call_db_versions(): array {
        global $wp_db_version;
        if ( ! function_exists( '__get_option' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        return array(
            'current' => (int) \__get_option( 'db_version' ),
            'target'  => (int) $wp_db_version,
        );
    }

    protected function call_blog_db_versions( int $blog_id ): array {
        \switch_to_blog( $blog_id );
        try {
            return $this->call_db_versions();
        } finally {
            \restore_current_blog();
        }
    }

    protected function call_update_db_site( bool $dry_run ): array {
        global $wpdb, $wp_current_db_version;
        $versions = $this->call_db_versions();
        $current  = $versions['current'];
        $target   = $versions['target'];
        if ( $target === $current ) {
            return array(
                'success' => "WordPress database already at latest db version {$target}.",
                'changed' => false,
            );
        }
        if ( $dry_run ) {
            return array(
                'success' => "WordPress database will be upgraded from db version {$current} to {$target}.",
                'changed' => false,
            );
        }
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP-CLI sets it before wp_upgrade(), which reads it.
        $wp_current_db_version = $current;
        $previous              = $wpdb->suppress_errors();
        try {
            \wp_upgrade();
        } finally {
            $wpdb->suppress_errors( $previous );
        }
        return array(
            'success' => "WordPress database upgraded successfully from db version {$current} to {$target}.",
            'changed' => true,
        );
    }

    protected function call_blog_update_db( int $blog_id, bool $dry_run ): array {
        \switch_to_blog( $blog_id );
        try {
            $result = $this->call_update_db_site( $dry_run );
            return array(
                'ok'      => true,
                'success' => $result['success'],
                'changed' => $result['changed'],
            );
        } catch ( Cli_Exit $e ) {
            throw $e;
        } catch ( \Throwable $e ) {
            return array( 'ok' => false );
        } finally {
            \restore_current_blog();
        }
    }

    protected function call_network_blogs(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's TableIterator reads wp_blogs itself.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id, site_id, domain, path FROM {$wpdb->blogs} WHERE spam = 0 AND deleted = 0 AND archived = 0 AND site_id = %d ORDER BY blog_id", self::current_network_id() ), ARRAY_A );
        $out  = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $out[] = array(
                'blog_id' => (int) $row['blog_id'],
                'site_id' => (int) $row['site_id'],
                'domain'  => (string) $row['domain'],
                'path'    => (string) $row['path'],
            );
        }
        return $out;
    }

    protected function call_network_blog_count(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the count behind the cap.
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->blogs} WHERE spam = 0 AND deleted = 0 AND archived = 0 AND site_id = %d", self::current_network_id() ) );
    }

    private static function current_network_id(): int {
        return function_exists( 'get_current_network_id' ) ? (int) \get_current_network_id() : 1;
    }

    protected function call_mark_network_upgraded( array $site_ids ): bool {
        global $wp_db_version;
        $changed = false;
        foreach ( $site_ids as $site_id ) {
            $changed = (bool) \update_metadata( 'site', (int) $site_id, 'wpmu_upgrade_site', $wp_db_version ) || $changed;
        }
        return $changed;
    }

    protected function call_core_checksums( string $version, string $locale ): array {
        $url      = sprintf( '%s?%s', self::CHECKSUMS_ENDPOINT, http_build_query( array( 'version' => $version, 'locale' => $locale ), '', '&' ) );
        $response = \wp_remote_get(
            $url,
            array(
                'headers' => array( 'Accept' => 'application/json' ),
                'timeout' => 30,
            )
        );
        if ( \is_wp_error( $response ) ) {
            return array( 'exception' => sprintf( "RuntimeException: Failed to get url '%s': %s.", $url, $response->get_error_message() ) );
        }
        $code = (int) \wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return array( 'exception' => sprintf( "RuntimeException: Couldn't fetch response from %s (HTTP code %d).", $url, $code ) );
        }
        $data = json_decode( trim( (string) \wp_remote_retrieve_body( $response ) ), true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return array( 'exception' => 'RuntimeException: Failed to decode JSON: ' . json_last_error_msg() );
        }
        if ( ! is_array( $data ) || ! isset( $data['checksums'] ) || ! is_array( $data['checksums'] ) ) {
            return array( 'checksums' => false );
        }
        return array( 'checksums' => $data['checksums'] );
    }

    protected function call_file_hashes( array $files ): array {
        $out = array();
        foreach ( $files as $file ) {
            if ( ! file_exists( ABSPATH . $file ) ) {
                $out[ $file ] = false;
                continue;
            }
            $md5          = md5_file( ABSPATH . $file );
            $out[ $file ] = false === $md5 ? '' : $md5;
        }
        return $out;
    }

    protected function call_walk_files( bool $include_root ): array {
        $path           = ABSPATH;
        $filtered_files = array();
        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ),
                    static function ( $current ) use ( $path, $include_root ) {
                        return self::filter_file( str_replace( '\\', '/', substr( $current->getPathname(), strlen( $path ) ) ), $include_root );
                    }
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ( $files as $file_info ) {
                if ( $file_info->isFile() ) {
                    $filtered_files[] = str_replace( '\\', '/', substr( $file_info->getPathname(), strlen( $path ) ) );
                }
            }
        } catch ( \Exception $e ) {
            return array( 'error' => $e->getMessage() );
        }
        return array( 'files' => $filtered_files );
    }
}
