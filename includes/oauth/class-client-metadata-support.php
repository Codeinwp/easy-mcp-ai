<?php
namespace Easy_MCP_AI\OAuth;

use Easy_MCP_AI\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Client_Metadata_Support {

    const SCHEMA_VERSION = '1.0.11';

    const HOOK = 'easy_mcp_ai_oauth_cimd_probe';

    const OPTION_STATE = 'easy_mcp_ai_oauth_cimd_state';

    const PROBE_URLS = array(
        'https://claude.ai/oauth/mcp-oauth-client-metadata',
        'https://claude.ai/oauth/claude-code-client-metadata',
        'https://chatgpt.com/oauth/client.json',
        'https://vscode.dev/oauth/client-metadata.json',
    );

    const PROBE_VALID = 604800;

    const PROBE_INTERVAL = 72000;

    const WITHDRAW_AFTER = 3;

    const WRITE_ATTEMPTS = 5;

    public static function switched_on(): bool {
        $on = true === Config::get( 'oauth_cimd' );
        return true === apply_filters( 'easy_mcp_ai_oauth_cimd', $on );
    }

    public static function schema_ready(): bool {
        return version_compare( (string) get_option( OAuth_Schema::VERSION_OPTION, '0' ), self::SCHEMA_VERSION, '>=' );
    }

    public static function resolves(): bool {
        return self::switched_on() && self::schema_ready();
    }

    public static function accepts_sign_ins(): bool {
        return self::resolves() && Client_Registry::is_dcr_enabled();
    }

    public static function advertised(): bool {
        return 'advertised' === self::status()['reason'];
    }

    public static function status(): array {
        $state  = self::state();
        $status = array(
            'reason'   => 'advertised',
            'testedAt' => $state['probe_at'],
            'results'  => $state['results'],
            'failures' => $state['failures'],
        );
        if ( ! self::switched_on() ) {
            $status['reason'] = 'switched_off';
        } elseif ( ! self::schema_ready() ) {
            $status['reason'] = 'schema_pending';
        } elseif ( ! Client_Registry::is_dcr_enabled() ) {
            $status['reason'] = 'registration_off';
        } elseif ( ! Client_Metadata::can_read_ipv6() ) {
            $status['reason'] = 'ipv6_unreadable';
        } elseif ( 0 === $state['probe_at'] ) {
            $status['reason'] = 'not_tested';
        } elseif ( ! $state['probe_ok'] ) {
            $status['reason'] = 'test_failed';
        } elseif ( time() - $state['probe_at'] > self::PROBE_VALID ) {
            $status['reason'] = 'test_stale';
        } elseif ( $state['failures'] >= self::WITHDRAW_AFTER ) {
            $status['reason'] = 'withdrawn';
        }
        return $status;
    }

    public static function arm(): bool {
        if ( ! self::accepts_sign_ins() ) {
            return false;
        }
        $state = self::state();
        if ( $state['probe_at'] > 0 && time() - $state['probe_at'] < self::PROBE_INTERVAL ) {
            return true;
        }
        if ( false === wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_single_event( time(), self::HOOK );
        }
        return true;
    }

    public static function run_probe(): string {
        if ( ! self::accepts_sign_ins() ) {
            return 'disabled';
        }
        $state = self::state( true );
        if ( $state['probe_at'] > 0 && time() - $state['probe_at'] < self::PROBE_INTERVAL ) {
            return 'recent';
        }

        $results = array();
        $passed  = true;
        foreach ( self::probe_urls() as $url ) {
            $outcome         = Client_Metadata::probe( $url );
            $results[ $url ] = '' === $outcome ? 'ok' : $outcome;
            $passed          = $passed && '' === $outcome;
        }
        $passed = $passed && array() !== $results;

        $tested_at = time();
        self::change(
            static function ( array $now ) use ( $tested_at, $passed, $results ): array {
                $now['probe_at'] = $tested_at;
                $now['probe_ok'] = $passed;
                $now['results']  = $results;
                if ( $passed ) {
                    $now['failures'] = 0;
                }
                return $now;
            }
        );
        return $passed ? 'passed' : 'failed';
    }

    public static function record_fetch( bool $transport_failed ): void {
        self::change(
            static function ( array $state ) use ( $transport_failed ): ?array {
                if ( $transport_failed ) {
                    ++$state['failures'];
                    return $state;
                }
                if ( 0 === $state['failures'] ) {
                    return null;
                }
                $state['failures'] = 0;
                return $state;
            }
        );
    }

    public static function probe_urls(): array {
        $urls = apply_filters( 'easy_mcp_ai_oauth_cimd_probe_urls', self::PROBE_URLS );
        if ( ! is_array( $urls ) ) {
            return array();
        }
        $valid = array();
        foreach ( $urls as $url ) {
            if ( is_string( $url ) && Client_Metadata::valid_url( $url ) ) {
                $valid[] = $url;
            }
        }
        return array_values( array_unique( $valid ) );
    }

    public static function state( bool $fresh = false ): array {
        if ( ! $fresh ) {
            return self::validated( get_option( self::OPTION_STATE, array() ) );
        }
        $stored = self::stored();
        return self::validated( null === $stored ? array() : maybe_unserialize( $stored ) );
    }

    private static function validated( $raw ): array {
        $raw   = is_array( $raw ) ? $raw : array();
        $state = array(
            'probe_at' => isset( $raw['probe_at'] ) && is_int( $raw['probe_at'] ) && $raw['probe_at'] > 0 ? $raw['probe_at'] : 0,
            'probe_ok' => isset( $raw['probe_ok'] ) && true === $raw['probe_ok'],
            'results'  => array(),
            'failures' => isset( $raw['failures'] ) && is_int( $raw['failures'] ) && $raw['failures'] > 0 ? $raw['failures'] : 0,
        );
        if ( isset( $raw['results'] ) && is_array( $raw['results'] ) ) {
            foreach ( $raw['results'] as $url => $outcome ) {
                if ( is_string( $url ) && is_string( $outcome ) ) {
                    $state['results'][ $url ] = $outcome;
                }
            }
        }
        return $state;
    }

    private static function stored(): ?string {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A compare-and-swap reads the row itself, past the options cache.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION_STATE ) );
        return is_object( $row ) && isset( $row->option_value ) ? (string) $row->option_value : null;
    }

    private static function change( callable $change ): void {
        global $wpdb;
        for ( $attempt = 0; $attempt < self::WRITE_ATTEMPTS; ++$attempt ) {
            $stored = self::stored();
            $next   = $change( self::validated( null === $stored ? array() : maybe_unserialize( $stored ) ) );
            if ( null === $next ) {
                return;
            }
            $value = maybe_serialize( $next );
            if ( $value === $stored ) {
                return;
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A compare-and-swap cannot go through the options cache.
            if ( null === $stored ) {
                $written = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", self::OPTION_STATE, $value, self::autoload() ) );
            } else {
                $written = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, self::OPTION_STATE, $stored ) );
            }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( 1 === (int) $written ) {
                self::forget_cached();
                return;
            }
        }
    }

    private static function autoload(): string {
        return function_exists( 'wp_autoload_values_to_autoload' ) ? 'on' : 'yes';
    }

    private static function forget_cached(): void {
        wp_cache_delete( self::OPTION_STATE, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
        wp_cache_delete( 'alloptions', 'options' );
    }
}
