<?php
namespace Easy_MCP_AI\Tools;

use Easy_MCP_AI\OAuth\Client_Metadata;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Package_Source {

    protected function parse_package_source( array $arguments, string $kind ): array {
        $given = array_values( array_filter( array( 'slug', 'url', 'file', 'content_base64' ), static function ( $key ) use ( $arguments ) {
            return self::package_argument_given( $arguments, $key );
        } ) );
        if ( count( $given ) > 1 ) {
            throw new \InvalidArgumentException( sprintf( 'Pass one source, not %s: slug (wordpress.org), url, file or content_base64.', implode( ' and ', $given ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( array() === $given ) {
            throw new \InvalidArgumentException( sprintf( 'Missing required parameters: pass slug (a wordpress.org %s), url (an HTTPS link to a ZIP), file (a file handoff) or content_base64.', $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $folder = self::parse_package_folder( $arguments, $kind );

        if ( 'slug' === $given[0] ) {
            $source = self::slug_source( $this->parse_wporg_slug( $arguments, $kind ) );
        } elseif ( 'url' === $given[0] ) {
            $source = $this->url_source( $arguments['url'], $kind, $folder );
        } elseif ( 'file' === $given[0] ) {
            $source = self::file_source( $arguments['file'], $kind, $folder );
        } else {
            $source = self::inline_source( $arguments['content_base64'], $kind, $folder );
        }

        if ( 'slug' === $source['type'] && null !== $folder ) {
            throw new \InvalidArgumentException( sprintf( '"folder" applies to url, file and content_base64: a wordpress.org %s installs into its slug\'s folder. Omit "folder".', $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 'slug' !== $source['type'] && self::package_argument_given( $arguments, 'version' ) ) {
            throw new \InvalidArgumentException( '"version" applies to slug only: a ZIP installs the version it holds. Omit "version".' );
        }
        return $source;
    }

    private static function package_argument_given( array $arguments, string $key ): bool {
        return isset( $arguments[ $key ] ) && ! ( is_string( $arguments[ $key ] ) && '' === trim( $arguments[ $key ] ) );
    }

    private static function parse_package_folder( array $arguments, string $kind ): ?string {
        if ( ! self::package_argument_given( $arguments, 'folder' ) ) {
            return null;
        }
        $folder = is_string( $arguments['folder'] ) ? trim( $arguments['folder'] ) : '';
        if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/', $folder ) ) {
            throw new \InvalidArgumentException( sprintf( '"folder" must be one folder name of letters, digits, dots, hyphens and underscores, e.g. "my-%s".', $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $folder;
    }

    private static function slug_source( string $slug ): array {
        return self::source_shape( 'slug', array( 'slug' => $slug, 'name' => $slug, 'label' => 'wordpress.org/' . $slug ) );
    }

    private static function source_shape( string $type, array $fields ): array {
        return array_merge(
            array(
                'type'    => $type,
                'slug'    => null,
                'url'     => null,
                'bytes'   => null,
                'name'    => '',
                'folder'  => null,
                'rename'  => null,
                'repo'    => null,
                'label'   => '',
                'unnamed' => false,
            ),
            $fields
        );
    }

    private function url_source( $raw, string $kind, ?string $folder ): array {
        $url = is_string( $raw ) ? trim( $raw ) : '';
        if ( '' === $url ) {
            throw new \InvalidArgumentException( 'url must be an HTTPS link to a ZIP file.' );
        }
        if ( strlen( $url ) > 2048 ) {
            throw new \InvalidArgumentException( 'url is longer than 2048 characters.' );
        }
        $page = self::wporg_page( $url );
        if ( null !== $page ) {
            if ( $page['kind'] !== $kind ) {
                throw new \InvalidArgumentException( sprintf( 'That is a wordpress.org %1$s page: install it with %2$s.', $page['kind'], 'theme' === $page['kind'] ? 'wp_install_theme' : 'wp_install_plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return self::slug_source( $this->parse_wporg_slug( array( 'slug' => $page['slug'] ), $kind ) );
        }
        $problem = self::package_url_problem( $url );
        if ( null !== $problem ) {
            throw new \InvalidArgumentException( sprintf( 'url %s. Pass an HTTPS link to the ZIP file.', $problem ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $host = self::package_url_host( $url );
        self::refuse_unlisted_package_host( $host );

        $repo    = self::github_latest_release_repo( $url );
        $archive = self::github_archive_repo( $url );
        $rename  = null !== $folder ? $folder : $archive;
        $base    = null !== $rename ? $rename : ( null !== $repo ? substr( (string) strrchr( '/' . $repo, '/' ), 1 ) : rawurldecode( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) );
        return self::source_shape(
            'url',
            array(
                'url'     => $url,
                'name'    => self::package_file_name( $base, $kind ),
                'folder'  => $folder,
                'rename'  => $rename,
                'repo'    => $repo,
                'label'   => self::url_label( $url ),
                'unnamed' => '' === self::package_base( $base ),
            )
        );
    }

    private static function file_source( $file, string $kind, ?string $folder ): array {
        if ( ! is_array( $file ) ) {
            throw new \InvalidArgumentException( 'file must be a client file object carrying download_url and file_id; a path or a bare file ID cannot be fetched.' );
        }
        foreach ( array( 'download_url', 'file_id' ) as $key ) {
            if ( ! isset( $file[ $key ] ) || ! is_string( $file[ $key ] ) || '' === trim( $file[ $key ] ) ) {
                throw new \InvalidArgumentException( 'file.download_url and file.file_id must be non-empty strings supplied by the client.' );
            }
        }
        foreach ( array( 'file_name', 'mime_type' ) as $key ) {
            if ( array_key_exists( $key, $file ) && null !== $file[ $key ] && ! is_string( $file[ $key ] ) ) {
                throw new \InvalidArgumentException( 'file.file_name and file.mime_type must be strings when supplied.' );
            }
        }
        $url     = trim( $file['download_url'] );
        $problem = self::package_url_problem( $url );
        if ( null !== $problem ) {
            throw new \InvalidArgumentException( sprintf( 'file.download_url %s. Ask the client for a fresh file handoff.', $problem ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        self::refuse_unlisted_package_host( self::package_url_host( $url ) );
        $name = isset( $file['file_name'] ) && is_string( $file['file_name'] ) ? trim( $file['file_name'] ) : '';
        return self::source_shape(
            'file',
            array(
                'url'     => $url,
                'name'    => self::package_file_name( null !== $folder ? $folder : $name, $kind ),
                'folder'  => $folder,
                'rename'  => $folder,
                'label'   => '' !== $name ? 'file handoff "' . sanitize_file_name( $name ) . '"' : 'file handoff',
                'unnamed' => '' === self::package_base( null !== $folder ? $folder : $name ),
            )
        );
    }

    private static function inline_source( $raw, string $kind, ?string $folder ): array {
        $data = is_string( $raw ) ? trim( $raw ) : '';
        if ( '' === $data ) {
            throw new \InvalidArgumentException( 'content_base64 must be a ZIP file encoded as base64.' );
        }
        if ( array() !== self::package_url_hosts() ) {
            throw new \InvalidArgumentException( 'This site installs packages only from listed hosts (the easy_mcp_ai_package_url_hosts filter), so content_base64 is refused. Pass url instead.' );
        }
        if ( preg_match( '/^data:[^,]{0,100};base64,/i', $data, $prefix ) ) {
            $data = substr( $data, strlen( $prefix[0] ) );
        }
        $data = (string) preg_replace( '/\s+/', '', $data );
        $max  = self::inline_max_bytes();
        if ( strlen( $data ) > 4 * (int) ceil( $max / 3 ) ) {
            throw new \InvalidArgumentException( 'content_base64 is larger than 2 MB. Pass a url or a file handoff for a bigger ZIP.' );
        }
        $bytes = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the argument is a base64 ZIP by contract.
        if ( false === $bytes || '' === $bytes ) {
            throw new \InvalidArgumentException( 'content_base64 is not valid base64.' );
        }
        if ( strlen( $bytes ) > $max ) {
            throw new \InvalidArgumentException( 'content_base64 is larger than 2 MB. Pass a url or a file handoff for a bigger ZIP.' );
        }
        if ( ! self::is_zip_signature( $bytes ) ) {
            throw new \InvalidArgumentException( 'content_base64 is not a ZIP file: a package is a .zip archive.' );
        }
        return self::source_shape(
            'inline',
            array(
                'bytes'   => $bytes,
                'name'    => self::package_file_name( null !== $folder ? $folder : '', $kind ),
                'folder'  => $folder,
                'rename'  => $folder,
                'label'   => 'content_base64',
                'unnamed' => null === $folder,
            )
        );
    }

    private static function inline_max_bytes(): int {
        return 2 * 1024 * 1024;
    }

    protected static function package_max_bytes( string $kind ): int {
        $default = 50 * 1024 * 1024;
        $bytes = (int) apply_filters( 'easy_mcp_ai_package_max_bytes', $default, $kind );
        return $bytes > 0 ? $bytes : $default;
    }

    private static function package_url_hosts(): array {
        $hosts = apply_filters( 'easy_mcp_ai_package_url_hosts', array() );
        $out   = array();
        foreach ( is_array( $hosts ) ? $hosts : array() as $host ) {
            if ( is_string( $host ) && '' !== trim( $host ) ) {
                $out[] = strtolower( trim( $host ) );
            }
        }
        return array_values( array_unique( $out ) );
    }

    private static function refuse_unlisted_package_host( string $host ): void {
        $hosts = self::package_url_hosts();
        if ( array() === $hosts || in_array( $host, $hosts, true ) ) {
            return;
        }
        $shown = implode( ', ', array_slice( $hosts, 0, 3 ) ) . ( count( $hosts ) > 3 ? ' and others' : '' );
        throw new \InvalidArgumentException( sprintf( 'This site installs packages only from %1$s (the easy_mcp_ai_package_url_hosts filter), and %2$s is not one of them.', $shown, substr( $host, 0, 60 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function package_url_problem( string $url ): ?string {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
            return 'is not a valid URL';
        }
        if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
            return 'is not HTTPS (the site downloads code from it, and plain HTTP can be changed on the way)';
        }
        if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
            return 'carries a user name or password';
        }
        if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
            return 'uses a port other than 443';
        }
        return null;
    }

    private static function package_url_host( string $url ): string {
        return strtolower( trim( (string) wp_parse_url( $url, PHP_URL_HOST ), '[]' ) );
    }

    private static function url_label( string $url ): string {
        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $text = $host . ( '' === $path ? '/' : $path );
        return strlen( $text ) > 200 ? substr( $text, 0, 197 ) . '...' : $text;
    }

    private static function wporg_page( string $url ): ?array {
        if ( ! preg_match( '~^https?://(?:www\.)?wordpress\.org/(plugins|themes)/([^/?#]+)/?\z~i', $url, $m ) ) {
            return null;
        }
        return array(
            'kind' => 'themes' === strtolower( $m[1] ) ? 'theme' : 'plugin',
            'slug' => $m[2],
        );
    }

    private static function github_latest_release_repo( string $url ): ?string {
        return preg_match( '~^https://github\.com/([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+)/releases/latest/?\z~i', $url, $m ) ? $m[1] : null;
    }

    private static function github_archive_repo( string $url ): ?string {
        $patterns = array(
            '~^https://github\.com/[A-Za-z0-9_.-]+/([A-Za-z0-9_.-]+)/archive/~i',
            '~^https://codeload\.github\.com/[A-Za-z0-9_.-]+/([A-Za-z0-9_.-]+)/zip/~i',
            '~^https://api\.github\.com/repos/[A-Za-z0-9_.-]+/([A-Za-z0-9_.-]+)/zipball(?:/|\z)~i',
        );
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $url, $m ) && preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*\z/', $m[1] ) ) {
                return $m[1];
            }
        }
        return null;
    }

    private static function package_file_name( string $candidate, string $kind ): string {
        $base = self::package_base( $candidate );
        return '' === $base ? $kind : $base;
    }

    private static function package_base( string $candidate ): string {
        $base = (string) preg_replace( '/\.zip\z/i', '', basename( str_replace( '\\', '/', $candidate ) ) );
        $base = (string) preg_replace( '/[^A-Za-z0-9._-]+/', '-', $base );
        return trim( substr( trim( $base, '.-_' ), 0, 100 ), '.-_' );
    }

    private static function is_zip_signature( string $bytes ): bool {
        return "PK\x03\x04" === substr( $bytes, 0, 4 );
    }

    protected function fetch_package( array $source, string $kind ): array {
        $dir     = self::new_package_dir();
        $package = array(
            'path'    => $dir . '/' . $source['name'] . '.zip',
            'dir'     => $dir,
            'name'    => $source['name'],
            'rename'  => $source['rename'],
            'unnamed' => ! empty( $source['unnamed'] ),
            'sha256'  => '',
            'bytes'   => 0,
            'label'   => $source['label'],
            'notes'   => array(),
        );
        try {
            if ( 'inline' === $source['type'] ) {
                if ( strlen( (string) $source['bytes'] ) !== (int) file_put_contents( $package['path'], (string) $source['bytes'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a private temporary file, read back by the upgrader.
                    throw new \RuntimeException( 'WordPress could not write the package to its temporary folder, so nothing was installed.' );
                }
            } else {
                $package = $this->download_source( $source, $package, $kind );
            }
            $package['bytes']  = (int) filesize( $package['path'] );
            $package['sha256'] = (string) hash_file( 'sha256', $package['path'] );
            return $package;
        } catch ( \Throwable $e ) {
            $this->discard_package( $package );
            throw $e;
        }
    }

    private function download_source( array $source, array $package, string $kind ): array {
        $url = (string) $source['url'];
        if ( null !== $source['repo'] ) {
            $release = $this->latest_github_release( (string) $source['repo'] );
            $url     = $release['url'];
            if ( $release['zipball'] && null === $package['rename'] ) {
                $package['rename'] = substr( (string) strrchr( '/' . (string) $source['repo'], '/' ), 1 );
            }
            $package['notes'][] = sprintf( 'Latest release resolved to %1$s (%2$s).', $release['name'], $release['zipball'] ? 'the source code ZIP' : $release['asset'] );
        }
        $this->download_package_to( $url, $package['path'], self::package_max_bytes( $kind ) );
        if ( ! self::is_zip_signature( (string) file_get_contents( $package['path'], false, null, 0, 4 ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- four bytes of a local temporary file.
            $start = ltrim( (string) file_get_contents( $package['path'], false, null, 0, 64 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            throw new \RuntimeException( '<' === substr( $start, 0, 1 ) ? 'The download is a web page, not a ZIP file: the link may lead to a sign-in or download page. Pass the direct link to the .zip file.' : 'The download is not a ZIP file, so nothing was installed. Pass a link to the .zip file.' );
        }
        $package['notes'][] = sprintf( 'Downloading installation package from https://%s/[redacted]&#8230;', self::package_url_host( $url ) );
        return $package;
    }

    private function latest_github_release( string $repo ): array {
        $final    = $this->package_request( 'https://api.github.com/repos/' . $repo . '/releases/latest', null, 1024 * 1024, array( 'Accept' => 'application/vnd.github+json' ) );
        $response = $final['response'];
        $code     = (int) wp_remote_retrieve_response_code( $response );
        if ( 404 === $code ) {
            throw new \RuntimeException( sprintf( 'GitHub lists no published release for %s, so nothing was installed. Pass the URL of a release ZIP instead.', $repo ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 403 === $code || 429 === $code ) {
            throw new \RuntimeException( 'GitHub refused the release lookup (it allows 60 an hour per server address without a token). Pass the URL of the release ZIP instead.' );
        }
        if ( $code < 200 || $code >= 300 ) {
            throw new \RuntimeException( sprintf( 'GitHub answered HTTP %1$d to the release lookup for %2$s, so nothing was installed.', $code, $repo ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $release = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $release ) ) {
            throw new \RuntimeException( sprintf( 'GitHub\'s answer about the latest release of %s could not be read, so nothing was installed.', $repo ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $name = isset( $release['tag_name'] ) && is_string( $release['tag_name'] ) ? $release['tag_name'] : ( isset( $release['name'] ) && is_string( $release['name'] ) ? $release['name'] : 'the latest release' );
        $name = substr( sanitize_text_field( $name ), 0, 60 );
        foreach ( isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array() as $asset ) {
            $asset_name = is_array( $asset ) && isset( $asset['name'] ) && is_string( $asset['name'] ) ? $asset['name'] : '';
            $asset_url  = is_array( $asset ) && isset( $asset['browser_download_url'] ) && is_string( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';
            if ( '.zip' === strtolower( substr( $asset_name, -4 ) ) && null === self::package_url_problem( $asset_url ) ) {
                return array( 'url' => $asset_url, 'name' => $name, 'asset' => substr( sanitize_file_name( $asset_name ), 0, 100 ), 'zipball' => false );
            }
        }
        if ( isset( $release['zipball_url'] ) && is_string( $release['zipball_url'] ) && null === self::package_url_problem( $release['zipball_url'] ) ) {
            return array( 'url' => $release['zipball_url'], 'name' => $name, 'asset' => '', 'zipball' => true );
        }
        throw new \RuntimeException( sprintf( 'The latest release of %s has no ZIP file to install.', $repo ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private function download_package_to( string $url, string $file, int $max ): int {
        $final    = $this->package_request( $url, $file, $max, array( 'Accept' => 'application/zip, application/octet-stream;q=0.9, */*;q=0.5' ) );
        $response = $final['response'];
        $code     = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            $expired = in_array( $code, array( 401, 403, 404, 410 ), true ) ? ' The link may have expired: ask for a fresh one.' : '';
            throw new \RuntimeException( sprintf( 'The download answered HTTP %1$d, so nothing was installed.%2$s', $code, $expired ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $declared = wp_remote_retrieve_header( $response, 'content-length' );
        $declared = (int) ( is_array( $declared ) ? end( $declared ) : $declared );
        clearstatcache( true, $file );
        $bytes = is_file( $file ) ? (int) filesize( $file ) : 0;
        if ( $declared > $max || $bytes > $max ) {
            throw new \RuntimeException( self::too_large_message( $max ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $bytes <= 0 ) {
            throw new \RuntimeException( 'The download was empty, so nothing was installed.' );
        }
        return $bytes;
    }

    private static function too_large_message( int $max ): string {
        return sprintf( 'The package is larger than %s, the most this site downloads (the easy_mcp_ai_package_max_bytes filter raises it), so nothing was installed.', size_format( $max ) );
    }

    private function package_request( string $url, ?string $file, int $max, array $headers ): array {
        for ( $hop = 0; ; ++$hop ) {
            $problem = self::package_url_problem( $url );
            if ( null !== $problem ) {
                throw new \RuntimeException( sprintf( 'The download redirected to a URL that %s, so it was stopped.', $problem ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $host = self::package_url_host( $url );
            if ( ! self::package_host_is_public( $host ) ) {
                throw new \RuntimeException( sprintf( 'The host %s is not on the public internet (a private, local or reserved address, or it does not resolve), so nothing was downloaded.', substr( $host, 0, 60 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $response = $this->package_http_get( $url, $file, $max, $headers );
            if ( is_wp_error( $response ) ) {
                throw new \RuntimeException( self::transport_failure( $response, $max ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $code = (int) wp_remote_retrieve_response_code( $response );
            if ( ! in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
                return array(
                    'response' => $response,
                    'url'      => $url,
                );
            }
            if ( $hop >= 5 ) {
                throw new \RuntimeException( 'The download redirected more than 5 times, so it was stopped.' );
            }
            $location = wp_remote_retrieve_header( $response, 'location' );
            $location = trim( (string) ( is_array( $location ) ? end( $location ) : $location ) );
            if ( '' === $location ) {
                throw new \RuntimeException( sprintf( 'The download answered HTTP %d without saying where to go, so it was stopped.', $code ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $url = self::absolute_location( $location, $url );
        }
    }

    private static function package_host_is_public( string $host ): bool {
        if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return Client_Metadata::is_public_ip( $host );
        }
        return Client_Metadata::host_is_public( $host );
    }

    private static function absolute_location( string $location, string $base ): string {
        $r = self::uri_parts( $location );
        $b = self::uri_parts( $base );
        $t = array( 'fragment' => $r['fragment'] );
        if ( null !== $r['scheme'] ) {
            $t += array( 'scheme' => $r['scheme'], 'authority' => $r['authority'], 'path' => self::remove_dot_segments( $r['path'] ), 'query' => $r['query'] );
            return self::uri_join( $t );
        }
        $t['scheme'] = $b['scheme'];
        if ( null !== $r['authority'] ) {
            $t += array( 'authority' => $r['authority'], 'path' => self::remove_dot_segments( $r['path'] ), 'query' => $r['query'] );
            return self::uri_join( $t );
        }
        $t['authority'] = $b['authority'];
        if ( '' === $r['path'] ) {
            $t += array( 'path' => $b['path'], 'query' => null !== $r['query'] ? $r['query'] : $b['query'] );
            return self::uri_join( $t );
        }
        $slash = strrpos( $b['path'], '/' );
        if ( '/' === $r['path'][0] ) {
            $path = $r['path'];
        } elseif ( null !== $b['authority'] && '' === $b['path'] ) {
            $path = '/' . $r['path'];
        } else {
            $path = ( false === $slash ? '' : substr( $b['path'], 0, $slash + 1 ) ) . $r['path'];
        }
        $t += array( 'path' => self::remove_dot_segments( $path ), 'query' => $r['query'] );
        return self::uri_join( $t );
    }

    private static function uri_parts( string $uri ): array {
        preg_match( '~^(?:([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?~s', $uri, $m );
        return array(
            'scheme'    => isset( $m[1] ) && '' !== $m[1] ? $m[1] : null,
            'authority' => isset( $m[2] ) && '' !== $m[2] ? $m[3] : null,
            'path'      => isset( $m[4] ) ? $m[4] : '',
            'query'     => isset( $m[5] ) && '' !== $m[5] ? $m[6] : null,
            'fragment'  => isset( $m[7] ) && '' !== $m[7] ? $m[8] : null,
        );
    }

    private static function uri_join( array $t ): string {
        return ( null !== $t['scheme'] ? $t['scheme'] . ':' : '' )
            . ( null !== $t['authority'] ? '//' . $t['authority'] : '' )
            . $t['path']
            . ( null !== $t['query'] ? '?' . $t['query'] : '' )
            . ( null !== $t['fragment'] ? '#' . $t['fragment'] : '' );
    }

    private static function remove_dot_segments( string $path ): string {
        $out = '';
        while ( '' !== $path ) {
            if ( 0 === strpos( $path, '../' ) ) {
                $path = substr( $path, 3 );
            } elseif ( 0 === strpos( $path, './' ) || 0 === strpos( $path, '/./' ) ) {
                $path = substr( $path, 2 );
            } elseif ( '/.' === $path ) {
                $path = '/';
            } elseif ( 0 === strpos( $path, '/../' ) || '/..' === $path ) {
                $path = '/..' === $path ? '/' : substr( $path, 3 );
                $out  = substr( $out, 0, (int) strrpos( $out, '/' ) );
            } elseif ( '.' === $path || '..' === $path ) {
                $path = '';
            } else {
                $next = strpos( $path, '/', 1 );
                $out .= false === $next ? $path : substr( $path, 0, $next );
                $path = false === $next ? '' : substr( $path, $next );
            }
        }
        return $out;
    }

    private static function transport_failure( \WP_Error $error, int $max ): string {
        $message = (string) $error->get_error_message();
        if ( false !== strpos( $message, 'cURL error 63:' ) ) {
            return self::too_large_message( $max );
        }
        if ( false !== strpos( $message, 'cURL error 28:' ) ) {
            return 'The download timed out, so nothing was installed. Retry, or pass a smaller ZIP.';
        }
        return sprintf( 'The download failed (%s), so nothing was installed. Check the link, or ask for a fresh one if it expires, and retry.', sanitize_key( (string) $error->get_error_code() ) );
    }

    private function package_http_get( string $url, ?string $file, int $max, array $headers ) {
        $args = array(
            'timeout'             => 300,
            'redirection'         => 0,
            'limit_response_size' => $max + 1,
            'user-agent'          => 'EasyMCPAI/' . ( defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : '0' ) . ' (package install)',
            'headers'             => $headers,
        );
        if ( null !== $file ) {
            $args['stream']   = true;
            $args['filename'] = $file;
        }
        $args['easy_mcp_ai_package_request'] = true;
        $cap                                 = static function ( $handle, $request = array() ) use ( $max ) {
            if ( ! is_array( $request ) || empty( $request['easy_mcp_ai_package_request'] ) ) {
                return;
            }
            if ( defined( 'CURLOPT_MAXFILESIZE_LARGE' ) ) {
                curl_setopt( $handle, CURLOPT_MAXFILESIZE_LARGE, $max ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- the http_api_curl action hands over the transport's own handle.
            } elseif ( defined( 'CURLOPT_MAXFILESIZE' ) && $max <= 2147483647 ) {
                curl_setopt( $handle, CURLOPT_MAXFILESIZE, $max ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- as above.
            }
        };
        add_action( 'http_api_curl', $cap, 10, 2 );
        try {
            return wp_safe_remote_get( $url, $args );
        } finally {
            remove_action( 'http_api_curl', $cap, 10 );
        }
    }

    private static function new_package_dir(): string {
        $base = rtrim( (string) get_temp_dir(), '/\\' );
        for ( $i = 0; $i < 3; $i++ ) {
            $dir = $base . '/easy-mcp-ai-package-' . wp_generate_password( 12, false );
            if ( @mkdir( $dir, 0700 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- a private temporary folder; a failure is retried, then refused.
                return $dir;
            }
        }
        throw new \RuntimeException( 'WordPress could not create a temporary folder for the package (its temporary directory is not writable), so nothing was downloaded.' );
    }

    protected function discard_package( array $package ): void {
        if ( is_file( $package['path'] ) ) {
            wp_delete_file( $package['path'] );
        }
        if ( is_dir( $package['dir'] ) ) {
            @rmdir( $package['dir'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- the empty temporary folder fetch_package() made.
        }
    }

    protected function install_package_file( object $upgrader, array $package, bool $force, callable $check ): array {
        $state  = array(
            'renamed'   => false,
            'checked'   => false,
            'replacing' => false,
            'folder'    => null,
        );
        $rename = static function ( $source, $remote_source = '', $filter_upgrader = null ) use ( $upgrader, $package, &$state ) {
            if ( $filter_upgrader !== $upgrader || $state['renamed'] ) {
                return $source;
            }
            $state['renamed'] = true;
            return is_wp_error( $source ) || null === $package['rename'] ? $source : self::rename_source( (string) $source, (string) $package['rename'] );
        };
        $inspect = static function ( $source, $remote_source = '', $filter_upgrader = null ) use ( $upgrader, $package, $check, &$state ) {
            if ( $filter_upgrader !== $upgrader || $state['checked'] ) {
                return $source;
            }
            $state['checked'] = true;
            if ( is_wp_error( $source ) ) {
                return $source;
            }
            if ( $package['unnamed'] && untrailingslashit( (string) $source ) === untrailingslashit( (string) $remote_source ) ) {
                return new \WP_Error( 'easy_mcp_ai_package_unnamed', sprintf( 'The ZIP has no top folder, and nothing names one, so it would install into a folder called "%s". Pass folder to name the folder.', $package['name'] ) );
            }
            $state['folder'] = basename( untrailingslashit( (string) $source ) );
            $verdict         = $check( $state['folder'], $upgrader );
            if ( is_wp_error( $verdict ) ) {
                return $verdict;
            }
            $state['replacing'] = (bool) $verdict;
            return $source;
        };
        add_filter( 'upgrader_source_selection', $rename, 10, 3 );
        add_filter( 'upgrader_source_selection', $inspect, 999, 3 );
        try {
            list( $result, $printed ) = $this->without_output(
                static function () use ( $upgrader, $package, $force ) {
                    return $upgrader->install( $package['path'], array( 'overwrite_package' => $force ) );
                }
            );
        } finally {
            remove_filter( 'upgrader_source_selection', $rename, 10 );
            remove_filter( 'upgrader_source_selection', $inspect, 999 );
        }
        return array( $result, $printed, $state['replacing'], $state['folder'] );
    }

    private static function rename_source( string $source, string $folder ) {
        global $wp_filesystem;
        $current = basename( untrailingslashit( $source ) );
        if ( $current === $folder ) {
            return $source;
        }
        $target = trailingslashit( dirname( untrailingslashit( $source ) ) ) . $folder . '/';
        if ( is_object( $wp_filesystem ) && $wp_filesystem->move( $source, $target ) ) {
            return $target;
        }
        return new \WP_Error( 'easy_mcp_ai_rename_failed', sprintf( 'WordPress could not rename the unpacked folder to "%s", so nothing was installed.', $folder ) );
    }

    protected static function package_fields( array $package ): array {
        return array(
            'source' => $package['label'],
            'sha256' => $package['sha256'],
            'bytes'  => $package['bytes'],
        );
    }

    protected static function describe_package_source( array $source, string $kind ): string {
        if ( 'inline' === $source['type'] ) {
            return sprintf(
                /* translators: 1: size such as "12 KB", 2: first 12 characters of a sha256 */
                __( 'a ZIP sent with the request (%1$s, sha256 %2$s…)', 'easy-mcp-ai' ),
                size_format( strlen( (string) $source['bytes'] ), 1 ),
                substr( hash( 'sha256', (string) $source['bytes'] ), 0, 12 )
            );
        }
        $cap = size_format( self::package_max_bytes( $kind ) );
        if ( null !== $source['repo'] ) {
            /* translators: 1: owner/repo, 2: size cap such as "50 MB" */
            return sprintf( __( 'the latest GitHub release of %1$s (a ZIP of at most %2$s)', 'easy-mcp-ai' ), $source['repo'], $cap );
        }
        if ( 'file' === $source['type'] ) {
            /* translators: 1: "file handoff" with its file name, 2: size cap such as "50 MB" */
            return sprintf( __( 'the %1$s the client attached (at most %2$s)', 'easy-mcp-ai' ), $source['label'], $cap );
        }
        /* translators: 1: host and path, 2: size cap such as "50 MB" */
        return sprintf( __( 'the ZIP at %1$s (at most %2$s, downloaded over HTTPS)', 'easy-mcp-ai' ), $source['label'], $cap );
    }

    protected static function package_record( array $arguments ): array {
        if ( isset( $arguments['url'] ) && is_string( $arguments['url'] ) ) {
            $arguments['url'] = self::record_url( $arguments['url'] );
        } elseif ( isset( $arguments['url'] ) && ! is_scalar( $arguments['url'] ) ) {
            $arguments['url'] = '[redacted]';
        }
        return $arguments;
    }

    protected static function package_preview_arguments( array $arguments ): array {
        $arguments = self::package_record( $arguments );
        foreach ( array( 'file', 'content_base64' ) as $key ) {
            if ( array_key_exists( $key, $arguments ) ) {
                $arguments[ $key ] = '[redacted]';
            }
        }
        return $arguments;
    }

    private static function record_url( string $url ): string {
        $url   = trim( $url );
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || ! isset( $parts['host'] ) || '' === $parts['host'] ) {
            return '[redacted]';
        }
        return ( isset( $parts['scheme'] ) ? $parts['scheme'] . ':' : '' ) . '//'
            . ( isset( $parts['user'] ) || isset( $parts['pass'] ) ? '[redacted]@' : '' )
            . $parts['host'] . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' )
            . ( isset( $parts['path'] ) ? $parts['path'] : '' )
            . ( strcspn( $url, '?#' ) < strlen( $url ) ? '?[redacted]' : '' );
    }
}
