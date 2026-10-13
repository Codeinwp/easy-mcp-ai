<?php

































namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Transport {

    
    const AUTH_RULE_MARKER = 'HTTP_AUTHORIZATION';

    
    const CLEANUP_EVENTS = array(
        'easy_mcp_ai_cleanup_audit_log',
        'easy_mcp_ai_cleanup_oauth',
        'easy_mcp_ai_cleanup_change_log',
        'easy_mcp_ai_cleanup_new_token_meta',
    );

    




    const CRON_OVERDUE_AFTER = 2 * DAY_IN_SECONDS;

    


    public static function run() {
        $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
        $is_apache_like  = self::is_apache_like( $server_software );
        $edges           = self::read_head_of_bootstrap_files();

        return array(
            self::evaluate_sapi( PHP_SAPI, $server_software ),
            self::evaluate_htaccess_rule(
                $is_apache_like ? self::read_htaccess( self::resolve_home_path(), ABSPATH ) : null,
                $is_apache_like,
                (string) \get_option( 'permalink_structure', '' ),
                PHP_SAPI,
                function_exists( 'is_multisite' ) && \is_multisite()
            ),
            self::evaluate_error_display(
                (bool) filter_var( ini_get( 'display_errors' ), FILTER_VALIDATE_BOOLEAN ),
                defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : false,
                defined( 'WP_DEBUG' ) ? (bool) WP_DEBUG : false
            ),
            self::evaluate_stray_output( $edges['heads'], $edges['tails'] ),
            self::evaluate_proxy_ip(
                isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                self::forwarding_header_present(),
                (bool) \has_filter( 'easy_mcp_ai_client_ip' ),
                self::trusted_proxies_configured(),
                self::resolved_client_ip()
            ),
            self::evaluate_cron(
                self::unscheduled_cleanup_events(),
                defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
                self::overdue_events( self::cleanup_event_next_runs(), time() )
            ),
        );
    }

    



    public static function evaluate_sapi( $sapi, $server_software ) {
        $sapi     = strtolower( (string) $sapi );
        $label    = __( 'PHP server interface', 'easy-mcp-ai' );
        $evidence = array( 'sapi' => $sapi, 'server_software' => (string) $server_software );

        if ( 'cgi-fcgi' === $sapi || 'fpm-fcgi' === $sapi ) {
            $detail = sprintf(
                /* translators: %s: PHP SAPI name. */
                __( 'Running as %s (FastCGI). If Bearer tokens are being lost, the web server is not handing the Authorization header to PHP, and PHP cannot recover it on its own. On Apache either the .htaccess rewrite WordPress writes or CGIPassAuth On in the server configuration hands it over; on nginx the server configuration needs the equivalent fastcgi_param.', 'easy-mcp-ai' ),
                $sapi
            );
        } elseif ( 'apache2handler' === $sapi ) {
            $detail = __( 'Running as mod_php. If Bearer tokens are being lost, the .htaccess rewrite that passes the Authorization header to PHP is missing — see the next check.', 'easy-mcp-ai' );
        } else {
            $detail = sprintf(
                /* translators: %s: PHP SAPI name. */
                __( 'Running as %s.', 'easy-mcp-ai' ),
                $sapi
            );
        }

        return Diagnostic_Result::pass( 'a2', Diagnostic_Result::TIER_INFO, $label, $detail, $evidence );
    }

    



    const FASTCGI_SAPIS = array( 'cgi-fcgi', 'fpm-fcgi' );

    














    public static function evaluate_htaccess_rule( $htaccess, $is_apache, $permalinks, $sapi = '', $is_multisite = false ) {
        $label = __( 'Authorization header rewrite present', 'easy-mcp-ai' );

        if ( ! $is_apache ) {
            return Diagnostic_Result::unknown(
                'a3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Not applicable — this check only applies to Apache and LiteSpeed, which read .htaccess.', 'easy-mcp-ai' )
            );
        }

        if ( $is_multisite ) {
            return Diagnostic_Result::unknown(
                'a3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Not checked — on Multisite, WordPress never writes this rewrite rule automatically. If Bearer tokens are being lost, it has to be added to .htaccess by hand; there is no settings screen that generates it here.', 'easy-mcp-ai' )
            );
        }

        
        
        
        if ( '' === trim( (string) $permalinks ) ) {
            return Diagnostic_Result::unknown(
                'a3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Not checked — permalinks are set to Plain, which is reported separately and is the cause to fix first.', 'easy-mcp-ai' )
            );
        }

        if ( null === $htaccess ) {
            return Diagnostic_Result::unknown(
                'a3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Could not read .htaccess. It may be absent, outside the readable path, or managed by the host.', 'easy-mcp-ai' )
            );
        }

        $rule_present = self::has_active_auth_rule( $htaccess );

        
        if ( in_array( strtolower( (string) $sapi ), self::FASTCGI_SAPIS, true ) ) {
            return self::evaluate_fastcgi_rule( $label, (string) $sapi, $rule_present );
        }

        
        
        
        
        if ( $rule_present ) {
            return Diagnostic_Result::pass( 'a3', Diagnostic_Result::TIER_WARNING, $label, '', array( 'rule_present' => true, 'sapi' => (string) $sapi ) );
        }

        return Diagnostic_Result::warn(
            'a3',
            Diagnostic_Result::TIER_WARNING,
            $label,
            __( 'The .htaccess rewrite that hands the Authorization header to PHP is missing. Bearer tokens can be dropped before WordPress ever sees them, which looks exactly like an invalid token.', 'easy-mcp-ai' ),
            __( 'Open Settings → Permalinks and press Save Changes — WordPress rewrites the rule. If .htaccess is not writable, add it manually above the WordPress block.', 'easy-mcp-ai' ),
            array( 'rule_present' => false, 'sapi' => (string) $sapi )
        );
    }

    













    private static function evaluate_fastcgi_rule( string $label, string $sapi, bool $rule_present ): Diagnostic_Result {
        $detail = $rule_present
            ? sprintf(
                /* translators: %s: PHP SAPI name, e.g. "fpm-fcgi". */
                __( 'The .htaccess rewrite is present. This site runs PHP as %s (FastCGI), where whether this rule delivers the Authorization header depends on how the web server hands requests to PHP, so the file alone cannot confirm the header arrives. The "Login credentials reach WordPress" check measures it on a full Re-run.', 'easy-mcp-ai' ),
                $sapi
            )
            : sprintf(
                /* translators: %s: PHP SAPI name, e.g. "fpm-fcgi". */
                __( 'The .htaccess rewrite is not present. This site runs PHP as %s (FastCGI), where the web server can deliver the Authorization header without it (CGIPassAuth On in its configuration), so a missing rule is not a fault on its own. The "Login credentials reach WordPress" check measures it on a full Re-run, and offers to add the rule if the header is lost.', 'easy-mcp-ai' ),
                $sapi
            );

        return Diagnostic_Result::unknown(
            'a3',
            Diagnostic_Result::TIER_WARNING,
            $label,
            $detail,
            array( 'rule_present' => $rule_present, 'sapi' => $sapi, 'measured_by' => 'a1' )
        );
    }

    



    public static function evaluate_error_display( $display_errors, $wp_debug_display, $wp_debug = false ) {
        $label = __( 'PHP errors hidden from responses', 'easy-mcp-ai' );

        
        
        
        
        
        
        
        
        
        $effective_debug_display = (bool) $wp_debug && (bool) $wp_debug_display;
        
        
        
        
        
        
        
        $evidence                = array(
            'display_errors'             => (bool) $display_errors,
            'wp_debug'                   => (bool) $wp_debug,
            'wp_debug_display'           => (bool) $wp_debug_display,
            'wp_debug_display_effective' => $effective_debug_display,
        );

        if ( $display_errors || $effective_debug_display ) {
            return Diagnostic_Result::warn(
                'a4',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'PHP is configured to print errors into the page. WordPress suppresses this for REST responses, so most MCP tool replies are unaffected — but OAuth discovery responses, anything emitted before WordPress finishes loading, and output a plugin echoes directly are all still at risk, and any of those can make a reply unreadable to the AI client.', 'easy-mcp-ai' ),
                __( 'Set WP_DEBUG_DISPLAY to false in wp-config.php, and display_errors to Off in php.ini on production.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::pass( 'a4', Diagnostic_Result::TIER_WARNING, $label, '', $evidence );
    }

    



















    public static function evaluate_stray_output( array $heads, array $tails = array() ) {
        $label = __( 'No stray output before headers', 'easy-mcp-ai' );

        if ( empty( $heads ) && empty( $tails ) ) {
            return Diagnostic_Result::unknown(
                'a5',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Could not read wp-config.php or the active theme\'s functions.php to check them.', 'easy-mcp-ai' )
            );
        }

        $dirty_before = array();
        foreach ( $heads as $name => $head ) {
            if ( self::has_stray_prefix( (string) $head ) ) {
                $dirty_before[] = (string) $name;
            }
        }

        $dirty_after = array();
        foreach ( $tails as $name => $tail ) {
            if ( self::has_stray_suffix( (string) $tail ) ) {
                $dirty_after[] = (string) $name;
            }
        }

        if ( ! empty( $dirty_before ) || ! empty( $dirty_after ) ) {
            $where = array();
            if ( ! empty( $dirty_before ) ) {
                $where[] = sprintf(
                    /* translators: %s: comma-separated file names. */
                    __( 'before the opening PHP tag in: %s', 'easy-mcp-ai' ),
                    implode( ', ', $dirty_before )
                );
            }
            if ( ! empty( $dirty_after ) ) {
                $where[] = sprintf(
                    /* translators: %s: comma-separated file names. */
                    __( 'after the closing PHP tag in: %s', 'easy-mcp-ai' ),
                    implode( ', ', $dirty_after )
                );
            }

            return Diagnostic_Result::warn(
                'a5',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: one or two clauses naming where and in which files. */
                    __( 'Content appears %s. That output is sent ahead of the response headers, which breaks OAuth discovery and can corrupt JSON replies.', 'easy-mcp-ai' ),
                    implode( '; and ', $where )
                ),
                __( 'Edit the listed files and remove any blank lines, spaces or byte-order marks outside the <?php ... ?> tags — including anything left after a trailing ?> at the end of the file.', 'easy-mcp-ai' ),
                array(
                    'files_with_stray_output_before' => $dirty_before,
                    'files_with_stray_output_after'  => $dirty_after,
                )
            );
        }

        return Diagnostic_Result::pass(
            'a5',
            Diagnostic_Result::TIER_WARNING,
            $label,
            '',
            array( 'files_checked' => array_values( array_unique( array_merge( array_keys( $heads ), array_keys( $tails ) ) ) ) )
        );
    }

    




















    public static function evaluate_proxy_ip( $remote_addr, $forwarding_header_present, $filter_hooked, $trusted_configured = false, $resolved_addr = null ) {
        $label    = __( 'Client IP resolves per visitor', 'easy-mcp-ai' );
        $evidence = array(
            'remote_addr'        => (string) $remote_addr,
            'forwarding_header'  => (bool) $forwarding_header_present,
            'filter_hooked'      => (bool) $filter_hooked,
            'trusted_proxies'    => (bool) $trusted_configured,
            'resolved_addr'      => null === $resolved_addr ? null : (string) $resolved_addr,
        );

        if ( self::is_loopback_or_private( (string) $remote_addr ) && $forwarding_header_present && ! $filter_hooked && ! $trusted_configured ) {
            return Diagnostic_Result::warn(
                'a6',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'This site appears to be behind a proxy or CDN: every request reaches PHP from the same internal address. Per-IP rate limits, the failed-authentication lockout, the IP whitelist and the addresses in the Audit Log are therefore shared across all visitors.', 'easy-mcp-ai' ),
                __( 'Declare the proxy with the EASY_MCP_AI_TRUSTED_PROXIES constant in wp-config.php — see https://docs.easymcpai.com/trusted-proxies. Best of all is fixing it on the server — mod_remoteip on Apache, or set_real_ip_from / real_ip_header on nginx — which corrects WordPress and every other plugin at once. The easy_mcp_ai_client_ip filter remains available.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        if ( $trusted_configured && $forwarding_header_present && ! $filter_hooked && null !== $resolved_addr && (string) $resolved_addr === (string) $remote_addr ) {
            return Diagnostic_Result::warn(
                'a6',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Trusted proxies are declared, but this request still resolved to the proxy address: the proxy that delivered it is not on the list, or the declared client-IP header is not the one it sends.', 'easy-mcp-ai' ),
                __( 'Add the address shown in the evidence to EASY_MCP_AI_TRUSTED_PROXIES in wp-config.php, or set EASY_MCP_AI_CLIENT_IP_HEADER to the header your proxy actually sends (Cloudflare sends CF-Connecting-IP) — see https://docs.easymcpai.com/trusted-proxies.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::pass( 'a6', Diagnostic_Result::TIER_WARNING, $label, '', $evidence );
    }

    






    public static function evaluate_cron( array $unscheduled, $cron_disabled, array $overdue = array() ) {
        $label    = __( 'Scheduled cleanup is registered', 'easy-mcp-ai' );
        $evidence = array(
            'unscheduled'     => $unscheduled,
            'overdue'         => $overdue,
            'disable_wp_cron' => (bool) $cron_disabled,
        );

        $note = $cron_disabled
            ? ' ' . __( 'DISABLE_WP_CRON is set on this site, so WordPress relies on a system cron to run scheduled work.', 'easy-mcp-ai' )
            : '';

        if ( ! empty( $unscheduled ) ) {
            return Diagnostic_Result::warn(
                'b3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated cron event names. */
                    __( 'These cleanup jobs are not scheduled: %s. Without them the audit log, change history and expired OAuth records grow without limit, which slows the database over time.', 'easy-mcp-ai' ),
                    implode( ', ', $unscheduled )
                ) . $note,
                __( 'Deactivate and reactivate the plugin to re-register its scheduled jobs.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        
        
        
        
        
        
        
        
        
        
        
        if ( ! empty( $overdue ) ) {
            return Diagnostic_Result::warn(
                'b3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated cron event names. */
                    __( 'These cleanup jobs are scheduled but have not run for days: %s. That usually means WordPress\'s scheduler is not firing at all, so the audit log, change history and expired OAuth records keep growing.', 'easy-mcp-ai' ),
                    implode( ', ', $overdue )
                ) . $note,
                $cron_disabled
                    ? __( 'DISABLE_WP_CRON is set, so check that the system cron job which calls wp-cron.php actually exists and is running.', 'easy-mcp-ai' )
                    : __( 'Check that wp-cron.php can be reached on this site — some hosts and security plugins block it.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::pass(
            'b3',
            Diagnostic_Result::TIER_WARNING,
            $label,
            trim( __( 'All cleanup jobs are scheduled.', 'easy-mcp-ai' ) . $note ),
            $evidence
        );
    }

    










    public static function overdue_events( array $next_runs, $now ) {
        $overdue = array();

        foreach ( $next_runs as $event => $timestamp ) {
            
            if ( empty( $timestamp ) ) {
                continue;
            }
            if ( ( (int) $now - (int) $timestamp ) > self::CRON_OVERDUE_AFTER ) {
                $overdue[] = (string) $event;
            }
        }

        return $overdue;
    }

    





    public static function wp_config_candidates( $abspath ) {
        $abspath = rtrim( (string) $abspath, '/\\' ) . '/';
        return array(
            $abspath . 'wp-config.php',
            dirname( rtrim( $abspath, '/\\' ) ) . '/wp-config.php',
        );
    }

    
















    public static function htaccess_candidates( $home_path, $abspath ) {
        $home_path = rtrim( (string) $home_path, '/\\' ) . '/';
        $abspath   = rtrim( (string) $abspath, '/\\' ) . '/';

        $candidates = array( $home_path . '.htaccess' );
        if ( $abspath !== $home_path ) {
            $candidates[] = $abspath . '.htaccess';
        }
        return $candidates;
    }

    













    public static function has_active_auth_rule( $htaccess ) {
        if ( ! class_exists( '\Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule' ) ) {
            require_once __DIR__ . '/class-htaccess-auth-rule.php';
        }
        return Htaccess_Auth_Rule::has_effective_rule( $htaccess );
    }

    

    private static function is_apache_like( $server_software ) {
        $s = strtolower( (string) $server_software );
        return false !== strpos( $s, 'apache' ) || false !== strpos( $s, 'litespeed' );
    }

    





















    private static function has_stray_prefix( $head ) {
        if ( '' === $head ) {
            return false;
        }
        if ( 0 === strncmp( $head, "\xEF\xBB\xBF", 3 ) ) {
            return true;
        }
        if ( preg_match( '/^<\?(php\b|=)/i', $head ) ) {
            return false;
        }
        if ( '<?' === substr( $head, 0, 2 ) ) {
            
            return ! self::short_open_tag_enabled();
        }
        return true;
    }

    
































































    private static function has_stray_suffix( $source ) {
        return self::emitted_output_bytes( $source ) > 0;
    }

    





    private static function emitted_output_bytes( $source ) {
        $source = (string) $source;
        if ( '' === $source || ! function_exists( 'token_get_all' ) ) {
            return 0;
        }

        
        
        
        
        
        
        
        if ( false === strpos( $source, '?' . '>' ) ) {
            return 0;
        }

        $tokens = @token_get_all( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a malformed file must not fatal a diagnostic.
        if ( ! is_array( $tokens ) ) {
            return 0;
        }

        $depth = 0;
        $bytes = 0;
        foreach ( $tokens as $i => $token ) {
            if ( is_string( $token ) ) {
                if ( '{' === $token ) {
                    $depth++;
                } elseif ( '}' === $token ) {
                    $depth = max( 0, $depth - 1 );
                }
                continue;
            }

            if ( T_CURLY_OPEN === $token[0] || T_DOLLAR_OPEN_CURLY_BRACES === $token[0] ) {
                
                $depth++;
                continue;
            }

            if ( T_INLINE_HTML === $token[0] && 0 === $depth && 0 !== $i ) {
                $bytes += strlen( $token[1] );
            }
        }

        return $bytes;
    }

    private static function read_file( $path ) {
        if ( ! is_readable( $path ) ) {
            return null;
        }
        $contents = @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local config read for diagnostics; WP_Filesystem is not initialised on every admin screen.
        return false === $contents ? null : $contents;
    }

    





    private static function read_htaccess( $home_path, $abspath ) {
        foreach ( self::htaccess_candidates( $home_path, $abspath ) as $candidate ) {
            $contents = self::read_file( $candidate );
            if ( null !== $contents ) {
                return $contents;
            }
        }
        return null;
    }

    








    private static function resolve_home_path() {
        if ( ! function_exists( 'get_home_path' ) ) {
            $file = ABSPATH . 'wp-admin/includes/file.php';
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }

        if ( function_exists( 'get_home_path' ) ) {
            $home_path = \get_home_path();
            if ( is_string( $home_path ) && '' !== $home_path ) {
                return $home_path;
            }
        }

        return ABSPATH;
    }

    






    private static function read_head_of_bootstrap_files() {
        $heads = array();
        $tails = array();

        foreach ( self::wp_config_candidates( ABSPATH ) as $candidate ) {
            $head = self::read_head( $candidate );
            if ( null !== $head ) {
                $heads['wp-config.php'] = $head;
                $source = self::read_file( $candidate );
                if ( null !== $source ) {
                    $tails['wp-config.php'] = $source;
                }
                break; 
            }
        }

        if ( function_exists( 'get_stylesheet_directory' ) ) {
            $functions = \get_stylesheet_directory() . '/functions.php';
            $head      = self::read_head( $functions );
            if ( null !== $head ) {
                $heads['functions.php'] = $head;
                $source = self::read_file( $functions );
                if ( null !== $source ) {
                    $tails['functions.php'] = $source;
                }
            }
        }

        return array( 'heads' => $heads, 'tails' => $tails );
    }

    private static function read_head( $path, $bytes = 8 ) {
        if ( ! is_readable( $path ) ) {
            return null;
        }
        $handle = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading the first bytes only; see read_file().
        if ( ! $handle ) {
            return null;
        }
        $head = fread( $handle, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
        fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        return false === $head ? null : $head;
    }

    private static function short_open_tag_enabled() {
        if ( isset( $GLOBALS['_wp_test_short_open_tag'] ) ) {
            return (bool) $GLOBALS['_wp_test_short_open_tag'];
        }
        return (bool) ini_get( 'short_open_tag' );
    }

    
    private static function trusted_proxies_configured() {
        if ( ! class_exists( '\\Easy_MCP_AI\\Client_IP' ) ) {
            require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-client-ip.php';
        }
        return count( \Easy_MCP_AI\Client_IP::trusted_proxies() ) > 0;
    }

    
    private static function resolved_client_ip() {
        if ( ! class_exists( '\\Easy_MCP_AI\\Client_IP' ) ) {
            require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-client-ip.php';
        }
        return (string) \Easy_MCP_AI\Client_IP::get();
    }

    private static function forwarding_header_present() {
        foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_REAL_IP', 'HTTP_FORWARDED', 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP' ) as $key ) {
            if ( isset( $_SERVER[ $key ] ) && '' !== $_SERVER[ $key ] ) {
                return true;
            }
        }
        return false;
    }

    private static function is_loopback_or_private( $ip ) {
        if ( '' === $ip ) {
            return false;
        }

        
        
        
        
        
        
        
        
        
        
        
        
        if ( 0 === stripos( $ip, '::ffff:' ) ) {
            $candidate = substr( $ip, 7 );
            if ( false !== filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                $ip = $candidate;
            }
        }

        if ( in_array( $ip, array( '127.0.0.1', '::1' ), true ) ) {
            return true;
        }
        return false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
    }

    private static function unscheduled_cleanup_events() {
        $missing = array();
        foreach ( self::CLEANUP_EVENTS as $event ) {
            if ( ! \wp_next_scheduled( $event ) ) {
                $missing[] = $event;
            }
        }
        return $missing;
    }

    
    private static function cleanup_event_next_runs() {
        $runs = array();
        foreach ( self::CLEANUP_EVENTS as $event ) {
            $runs[ $event ] = \wp_next_scheduled( $event );
        }
        return $runs;
    }
}
