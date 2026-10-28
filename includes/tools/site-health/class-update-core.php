<?php
namespace Easy_MCP_AI\Tools\Site_Health;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;
use Easy_MCP_AI\Tools\Package_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Core extends Base_Tool {

    use Lifecycle_Guard;
    use Package_Guard;

    const CAPABILITY = 'update_core';

    public function get_name() {
        return 'wp_update_core';
    }

    public function get_title() {
        return 'Update WordPress Core';
    }

    public function get_description() {
        return sprintf(
            'Updates WordPress itself (core files) to a newer release from wordpress.org, as Dashboard → Updates does, and as `wp core update` does. Optional: `version` ("latest" by default; a newer release such as "6.8.3"; or "nightly" for the development build), `minor` (boolean, default false: the newest patch release of the installed branch instead of the latest release, e.g. 6.8.1 → 6.8.3; not combined with `version`), `locale` (the package language, e.g. "de_DE"; default the site language). Returns { changed, old_version, new_version, locale, source, messages (the updater log, every URL cut to its host) }; when there is nothing newer, changed is false with a `reason`. A version older than the installed one is refused: downgrades and reinstalls are not offered. Runs within this request and can take minutes; while files are copied the whole site, this connection included, answers 503 (maintenance mode). Requires the %s capability; refused when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress cannot write its own folder or wp-content directly, when another core update is running, when the release needs a newer PHP, and on multisite unless you are a super admin with manage_network_options. Change history cannot record or undo it. Off by default; an administrator switches it on.',
            self::CAPABILITY
        );
    }

    public function get_category() {
        return 'site_health';
    }

    public function get_required_capability() {
        return self::CAPABILITY;
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => true,
        );
    }

    public function is_disabled_by_default() {
        return true;
    }

    public function get_approval_policy( array $arguments ) {
        return 'always';
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'version' => array(
                    'type'        => 'string',
                    'description' => '"latest" (the default) for the newest release wordpress.org offers this site; a specific newer release such as "6.8.3"; or "nightly" for the development build. A release older than the installed one is refused.',
                    'default'     => 'latest',
                ),
                'minor'   => array(
                    'type'        => 'boolean',
                    'description' => 'Install only the newest patch release of the installed branch (6.8.1 → 6.8.3, never 6.9). Default false. Not combined with "version".',
                    'default'     => false,
                ),
                'locale'  => array(
                    'type'        => 'string',
                    'description' => 'Language of the WordPress package, e.g. "de_DE" or "en_US". Default: the site language.',
                ),
            ),
        );
    }

    public function describe( array $arguments ) {
        try {
            $request   = $this->parse_request( $arguments );
            $installed = $this->installed_wp_version();
        } catch ( \Throwable $e ) {
            return parent::describe( $arguments );
        }
        if ( $request['minor'] ) {
            $target = __( 'the newest patch release of its branch', 'easy-mcp-ai' );
        } elseif ( 'latest' === $request['version'] ) {
            $target = __( 'the latest release wordpress.org offers', 'easy-mcp-ai' );
        } elseif ( 'nightly' === $request['version'] ) {
            $target = __( 'the nightly development build', 'easy-mcp-ai' );
        } else {
            /* translators: %s: WordPress version */
            $target = sprintf( __( 'version %s', 'easy-mcp-ai' ), $request['version'] );
        }
        return sprintf(
            /* translators: 1: installed version, 2: target, 3: locale */
            __( 'Update WordPress core from %1$s to %2$s (package language %3$s). Core files are replaced in place and the site is in maintenance mode while they are copied. It cannot be undone from here.', 'easy-mcp-ai' ),
            $installed,
            $target,
            null === $request['locale'] ? get_locale() : $request['locale']
        );
    }

    public function execute( array $arguments ) {
        $request = $this->parse_request( $arguments );

        $this->refuse_unless_may_update();

        $this->load_upgrader();
        $this->refuse_unless_direct_filesystem( ABSPATH, 'the WordPress folder' );
        $this->raise_time_limit();

        $installed = str_replace( '-src', '', $this->installed_wp_version() );
        $locale    = null === $request['locale'] ? (string) get_locale() : $request['locale'];
        $offers    = $this->core_update_offers();

        $choice = $this->choose_offer( $request, $installed, $locale, $offers );
        if ( is_string( $choice ) ) {
            return $this->unchanged( $installed, $locale, $choice );
        }
        list( $offer, $source ) = $choice;
        self::refuse_unmet_php( $offer );

        $skin                     = $this->new_upgrader_skin();
        list( $result, $printed ) = $this->run_core_upgrade( $skin, $offer );

        $reason = $this->unchanged_reason( $result );
        if ( null !== $reason ) {
            return $this->unchanged( $installed, $locale, $reason );
        }

        $out = array(
            'changed'     => true,
            'old_version' => $installed,
            'new_version' => $result,
            'locale'      => isset( $offer->locale ) && is_string( $offer->locale ) ? $offer->locale : $locale,
            'source'      => $source,
            'messages'    => $this->upgrade_messages( $skin ),
        );
        if ( '' !== $printed ) {
            $out['output_discarded'] = true;
        }
        return $out;
    }

    private function refuse_unless_may_update(): void {
        $this->refuse_when_file_mods_are_off( 'WordPress cannot be updated' );
        if ( ! current_user_can( self::CAPABILITY ) ) {
            throw new \RuntimeException( sprintf( 'Sorry, you are not allowed to update this site: updating WordPress needs the %s capability.', self::CAPABILITY ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $this->refuse_unless_network_file_admin( 'manage_network_options', 'updating WordPress' );
    }

    private static function refuse_unmet_php( object $offer ): void {
        $needs_php = isset( $offer->php_version ) && is_string( $offer->php_version ) ? $offer->php_version : '';
        if ( '' !== $needs_php && version_compare( PHP_VERSION, $needs_php, '<' ) ) {
            throw new \RuntimeException( sprintf( 'WordPress %s requires PHP %s or higher; this server runs PHP %s. Nothing was changed.', (string) $offer->current, $needs_php, PHP_VERSION ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function run_core_upgrade( \WP_Ajax_Upgrader_Skin $skin, object $offer ): array {
        $upgrader = $this->new_core_upgrader( $skin );
        $feedback = array( $skin, 'feedback' );
        add_filter( 'update_feedback', $feedback );
        try {
            return $this->without_output(
                function () use ( $upgrader, $offer ) {
                    return $upgrader->upgrade( $offer );
                }
            );
        } finally {
            remove_filter( 'update_feedback', $feedback );
        }
    }

    private function unchanged_reason( $result ): ?string {
        if ( is_wp_error( $result ) ) {
            if ( 'up_to_date' === $result->get_error_code() ) {
                return 'WordPress is up to date.';
            }
            if ( 'locked' === $result->get_error_code() ) {
                throw new \RuntimeException( 'Another WordPress update is already in progress (the core_updater.lock option is held for up to 15 minutes). Nothing was changed; try again later.' );
            }
            throw new \RuntimeException( sprintf( 'WordPress was not updated: %s', $this->upgrader_error_text( $result ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! is_string( $result ) || '' === $result ) {
            throw new \RuntimeException( $this->redact_urls( $this->relative_paths( $this->filesystem_failure_message() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return null;
    }

    private function parse_request( array $arguments ): array {
        $version = self::version_argument( $arguments );

        $minor = isset( $arguments['minor'] ) && rest_sanitize_boolean( $arguments['minor'] );
        if ( $minor && 'latest' !== $version ) {
            throw new \InvalidArgumentException( '"minor" picks the newest patch release of the installed branch, so it cannot be combined with "version". Omit one of them.' );
        }

        $locale = self::locale_argument( $arguments );
        if ( 'nightly' === $version && null !== $locale && 'en_US' !== $locale ) {
            throw new \InvalidArgumentException( 'Nightly builds exist only in English (en_US). Omit "locale" or pass "en_US".' );
        }

        return array(
            'version' => $version,
            'minor'   => $minor,
            'locale'  => $locale,
        );
    }

    private static function version_argument( array $arguments ): string {
        if ( ! isset( $arguments['version'] ) ) {
            return 'latest';
        }
        $raw = $arguments['version'];
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            throw new \InvalidArgumentException( '"version" must be "latest", "nightly" or a release such as "6.8.3".' );
        }
        $version = strtolower( trim( $raw ) );
        if ( 'trunk' === $version ) {
            $version = 'nightly';
        }
        if ( 'latest' !== $version && 'nightly' !== $version && ! preg_match( '/^[0-9]{1,3}\.[0-9]{1,3}(?:\.[0-9]{1,3})?(?:-(?:beta|rc)[0-9]{1,2})?\z/', $version ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid version "%s". Pass "latest", "nightly" or a release such as "6.8.3".', substr( trim( (string) $raw ), 0, 40 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return str_replace( '-rc', '-RC', $version );
    }

    private static function locale_argument( array $arguments ): ?string {
        if ( ! isset( $arguments['locale'] ) ) {
            return null;
        }
        $raw = $arguments['locale'];
        if ( ! is_string( $raw ) || ! preg_match( '/^[a-z]{2,3}(?:_[A-Za-z0-9]+){0,2}\z/', trim( $raw ) ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid locale "%s". Pass a WordPress locale such as "de_DE" or "en_US".', is_string( $raw ) ? substr( $raw, 0, 40 ) : gettype( $raw ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return trim( $raw );
    }

    private function choose_offer( array $request, string $installed, string $locale, array $offers ) {
        if ( 'latest' === $request['version'] ) {
            return $this->latest_offer( $request['minor'], null !== $request['locale'], $installed, $locale, $offers );
        }
        return self::requested_release( $request['version'], $installed, $locale, $offers );
    }

    private function latest_offer( bool $minor, bool $explicit, string $installed, string $locale, array $offers ) {
        if ( empty( $offers ) ) {
            throw new \RuntimeException( 'wordpress.org returned no update information for this site (the version check failed or was blocked), so nothing was changed. Try again later.' );
        }
        $pool = $this->offer_pool( $offers, $minor, $installed );
        if ( empty( $pool ) ) {
            if ( $minor ) {
                return 'WordPress is at the latest minor release.';
            }
            throw new \RuntimeException( 'wordpress.org returned no update information for this site (the version check failed or was blocked), so nothing was changed. Try again later.' );
        }
        $offer = $this->offer_in_locale( $pool, $locale, $explicit );
        if ( null === $offer ) {
            throw new \RuntimeException( sprintf( 'wordpress.org offers this update only in: %s. Pass one of those as "locale", or a "version" to download that release in another language.', implode( ', ', $this->locales_of( $pool ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( self::installs_nothing_new( $offer, $installed ) ) {
            return 'WordPress is up to date.';
        }
        return array( $offer, 'wordpress.org update offer' );
    }

    private function offer_pool( array $offers, bool $minor, string $installed ): array {
        $pool = array();
        foreach ( $offers as $offer ) {
            if ( $minor ) {
                if ( isset( $offer->version ) && $this->is_patch_release( (string) $offer->version, $installed ) ) {
                    $pool[] = $offer;
                }
            } elseif ( ! isset( $offer->response ) || 'autoupdate' !== $offer->response ) {
                $pool[] = $offer;
            }
        }
        return $pool;
    }

    private static function installs_nothing_new( object $offer, string $installed ): bool {
        $current = isset( $offer->current ) ? (string) $offer->current : '';
        return ( isset( $offer->response ) && 'latest' === $offer->response ) || '' === $current || version_compare( $current, $installed, '<=' );
    }

    private static function requested_release( string $version, string $installed, string $locale, array $offers ) {
        if ( 'nightly' !== $version ) {
            if ( version_compare( $version, $installed, '=' ) ) {
                return sprintf( 'WordPress is already at version %s.', $installed );
            }
            if ( version_compare( $version, $installed, '<' ) ) {
                throw new \RuntimeException( sprintf( 'Version %s is older than the installed WordPress %s. Downgrades and reinstalls are not offered over MCP; nothing was changed.', $version, $installed ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $listed = self::listed_offer( $version, $locale, $offers );
            if ( null !== $listed ) {
                return array( $listed, 'wordpress.org update offer' );
            }
        }
        return array( self::archive_offer( $version, $locale ), 'wordpress.org release archive' );
    }

    private static function listed_offer( string $version, string $locale, array $offers ): ?object {
        foreach ( $offers as $offer ) {
            if ( isset( $offer->current, $offer->locale ) && $version === (string) $offer->current && $locale === (string) $offer->locale && ( ! isset( $offer->response ) || 'latest' !== $offer->response ) ) {
                return $offer;
            }
        }
        return null;
    }

    private static function archive_offer( string $version, string $locale ): object {
        $package = self::download_url( $version, 'nightly' === $version ? 'en_US' : $locale );
        return (object) array(
            'response'        => 'upgrade',
            'download'        => $package,
            'locale'          => 'nightly' === $version ? 'en_US' : $locale,
            'packages'        => (object) array(
                'full'        => $package,
                'no_content'  => false,
                'new_bundled' => false,
                'partial'     => false,
                'rollback'    => false,
            ),
            'current'         => $version,
            'version'         => $version,
            'php_version'     => '',
            'mysql_version'   => '',
            'new_bundled'     => '',
            'partial_version' => '',
        );
    }

    private function offer_in_locale( array $pool, string $locale, bool $explicit ): ?object {
        $wanted = $explicit ? array( $locale ) : array_unique( array( $locale, 'en_US' ) );
        foreach ( $wanted as $code ) {
            foreach ( $pool as $offer ) {
                if ( isset( $offer->locale ) && $code === (string) $offer->locale ) {
                    return $offer;
                }
            }
        }
        return $explicit ? null : reset( $pool );
    }

    private function locales_of( array $pool ): array {
        $codes = array();
        foreach ( $pool as $offer ) {
            if ( isset( $offer->locale ) && is_string( $offer->locale ) ) {
                $codes[] = $offer->locale;
            }
        }
        return array_values( array_unique( $codes ) );
    }

    private function is_patch_release( string $candidate, string $installed ): bool {
        if ( ! version_compare( $candidate, $installed, '>' ) ) {
            return false;
        }
        $have = explode( '.', explode( '-', $installed )[0] );
        $new  = explode( '.', explode( '-', $candidate )[0] );
        return isset( $have[1], $new[1] ) && $have[0] === $new[0] && $have[1] === $new[1];
    }

    private static function download_url( string $version, string $locale ): string {
        if ( 'nightly' === $version ) {
            return 'https://wordpress.org/nightly-builds/wordpress-latest.zip';
        }
        $subdomain = 'en_US' === $locale ? '' : substr( $locale, 0, 2 ) . '.';
        $suffix    = 'en_US' === $locale ? '' : '-' . $locale;
        if ( substr_count( $version, '.' ) > 1 && '.0' === substr( $version, -2 ) ) {
            $version = substr( $version, 0, -2 );
        }
        return 'https://' . $subdomain . 'wordpress.org/wordpress-' . $version . $suffix . '.zip';
    }

    private function unchanged( string $installed, string $locale, string $reason ): array {
        return array(
            'changed'     => false,
            'old_version' => $installed,
            'new_version' => $installed,
            'locale'      => $locale,
            'reason'      => $reason,
        );
    }

    protected function installed_wp_version(): string {
        if ( function_exists( 'wp_get_wp_version' ) ) {
            return (string) wp_get_wp_version();
        }
        return isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
    }

    protected function core_update_offers(): array {
        if ( ! function_exists( 'wp_version_check' ) ) {
            require_once ABSPATH . WPINC . '/update.php';
        }
        wp_version_check();
        $from_api = get_site_transient( 'update_core' );
        if ( ! is_object( $from_api ) || ! isset( $from_api->updates ) || ! is_array( $from_api->updates ) ) {
            return array();
        }
        return array_values( array_filter( $from_api->updates, 'is_object' ) );
    }

    protected function new_core_upgrader( \WP_Ajax_Upgrader_Skin $skin ): object {
        return new \Core_Upgrader( $skin );
    }
}
