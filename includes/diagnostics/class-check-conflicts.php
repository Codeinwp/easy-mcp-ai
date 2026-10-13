<?php

























namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Conflicts {

    


























    const KNOWN_MCP_PLUGINS = array(
        'wordpress-mcp' => 'WordPress MCP',
        'mcp-adapter'   => 'MCP Adapter',
        'wp-mcp-server' => 'WP MCP Server',
    );

    
    const KNOWN_SECURITY_PLUGINS = array(
        'wordfence'                          => 'Wordfence Security',
        'better-wp-security'                 => 'Solid Security',
        'all-in-one-wp-security-and-firewall' => 'All-In-One Security',
        'ninjafirewall'                      => 'NinjaFirewall',
        'sg-security'                        => 'SiteGround Security',
        'wp-defender'                        => 'Defender',
        'sucuri-scanner'                     => 'Sucuri Security',
    );

    












    const KNOWN_COMING_SOON_PLUGINS = array(
        'coming-soon'                     => 'SeedProd',
        'under-construction-page'         => 'UnderConstructionPage',
        'minimal-coming-soon-maintenance-mode' => 'Minimal Coming Soon & Maintenance Mode',
        'maintenance'                     => 'Maintenance',
        'wp-maintenance-mode'             => 'WP Maintenance Mode',
        'cmp-coming-soon-maintenance'     => 'CMP — Coming Soon & Maintenance',
        'site-offline'                    => 'Site Offline / Coming Soon',
    );

    
    const KNOWN_CACHE_PLUGINS = array(
        'litespeed-cache'  => 'LiteSpeed Cache',
        'wp-rocket'        => 'WP Rocket',
        'w3-total-cache'   => 'W3 Total Cache',
        'wp-super-cache'   => 'WP Super Cache',
        'wp-fastest-cache' => 'WP Fastest Cache',
    );

    
    const CACHE_COVERED    = 'covered';
    const CACHE_UNCOVERED  = 'uncovered';
    const CACHE_UNREADABLE = 'unreadable';

    


    public static function run() {
        
        
        $foreign_rest_auth = self::foreign_rest_auth_callbacks( self::rest_auth_callback_names() );

        return array(
            self::evaluate_competing_mcp( self::active_from( 'easy_mcp_ai_diagnostics_known_mcp_plugins', self::KNOWN_MCP_PLUGINS ) ),
            self::evaluate_security_plugins( self::active_from( 'easy_mcp_ai_diagnostics_known_security_plugins', self::KNOWN_SECURITY_PLUGINS ) ),
            self::evaluate_rest_filters( array() !== $foreign_rest_auth, $foreign_rest_auth, self::rest_auth_override_active(), self::rest_auth_observation(), self::foreign_rest_auth_identities() ),
            self::evaluate_hook_stripping( self::count_hook_reassertions(), self::change_capture_enabled() ),
            self::evaluate_cache_plugins( self::active_from( 'easy_mcp_ai_diagnostics_known_cache_plugins', self::KNOWN_CACHE_PLUGINS ), self::cache_coverage_now() ),
            self::evaluate_coming_soon_plugins( self::active_from( 'easy_mcp_ai_diagnostics_known_coming_soon_plugins', self::KNOWN_COMING_SOON_PLUGINS ) ),
        );
    }

    













    public static function evaluate_competing_mcp( $found ) {
        
        
        if ( null === $found ) {
            return Diagnostic_Result::unknown( 'e1', Diagnostic_Result::TIER_WARNING, __( 'No competing MCP plugin', 'easy-mcp-ai' ), __( 'Could not read the list of active plugins.', 'easy-mcp-ai' ) );
        }
        $found = (array) $found;
        $label = __( 'No competing MCP plugin', 'easy-mcp-ai' );

        if ( ! empty( $found ) ) {
            return Diagnostic_Result::warn(
                'e1',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    
                    
                    
                    
                    count( $found ) > 1
                        /* translators: %s: comma-separated plugin names. */
                        ? __( 'Also active: %s. If they also serve MCP or OAuth discovery on this site, an AI client may connect to whichever answers first, so a connection can succeed and still reach the wrong plugin.', 'easy-mcp-ai' )
                        /* translators: %s: a plugin name. */
                        : __( 'Also active: %s. If it also serves MCP or OAuth discovery on this site, an AI client may connect to whichever answers first, so a connection can succeed and still reach the wrong plugin.', 'easy-mcp-ai' ),
                    implode( ', ', $found )
                ),
                __( 'Check which plugin your AI client is actually connected to. If it is the wrong one, keep a single MCP plugin active, deactivate the other, and reconnect.', 'easy-mcp-ai' ),
                array( 'competing_mcp_plugins' => $found )
            );
        }

        return Diagnostic_Result::pass(
            'e1',
            Diagnostic_Result::TIER_WARNING,
            $label,
            __( 'None found among the active plugins.', 'easy-mcp-ai' ),
            array( 'competing_mcp_plugins' => array() )
        );
    }

    






















    public static function evaluate_security_plugins( $found ) {
        
        
        
        
        $label = __( 'Firewall and security plugins', 'easy-mcp-ai' );

        
        
        if ( null === $found ) {
            return Diagnostic_Result::unknown( 'e2', Diagnostic_Result::TIER_INFO, $label, __( 'Could not read the list of active plugins.', 'easy-mcp-ai' ) );
        }
        $found = (array) $found;

        if ( ! empty( $found ) ) {
            return Diagnostic_Result::pass(
                'e2',
                Diagnostic_Result::TIER_INFO,
                $label,
                sprintf(
                    count( $found ) > 1
                        /* translators: %s: comma-separated plugin names. */
                        ? __( 'Detected: %s. These plugins work normally on most sites and nothing here indicates a problem. If an AI client cannot connect, a firewall rule refusing requests before WordPress sees them is worth ruling out: allow /wp-json/easy-mcp-ai/ and /.well-known/oauth-*, and check it is not filtering by user agent. Cover the ?rest_route=/easy-mcp-ai/ form of the same address too: a rule written only against the path silently misses it.', 'easy-mcp-ai' )
                        /* translators: %s: a plugin name. */
                        : __( 'Detected: %s. This plugin works normally on most sites and nothing here indicates a problem. If an AI client cannot connect, a firewall rule refusing requests before WordPress sees them is worth ruling out: allow /wp-json/easy-mcp-ai/ and /.well-known/oauth-*, and check it is not filtering by user agent. Cover the ?rest_route=/easy-mcp-ai/ form of the same address too: a rule written only against the path silently misses it.', 'easy-mcp-ai' ),
                    implode( ', ', $found )
                ),
                array( 'security_plugins' => $found )
            );
        }

        
        
        
        
        
        return Diagnostic_Result::pass(
            'e2',
            Diagnostic_Result::TIER_INFO,
            $label,
            __( 'No firewall plugin found among the active plugins. This cannot see protection that runs outside WordPress — a host-level firewall, a must-use plugin, or a CDN rule.', 'easy-mcp-ai' ),
            array( 'security_plugins' => array() )
        );
    }

    

















































    public static function evaluate_rest_filters( $hooked, array $foreign = array(), $override_active = false, $observation = null, $identities = null ) {
        $label = __( 'REST API authentication unmodified', 'easy-mcp-ai' );
        
        
        
        $identities = is_array( $identities ) ? $identities : $foreign;

        if ( $hooked && $override_active ) {
            $evidence = array(
                'rest_authentication_hooked' => true,
                'callbacks'                  => array_values( $foreign ),
                'override_active'            => true,
                'observation'                => $observation,
            );
            $fix_own_allowlist = __( 'Identify the plugin the callback above belongs to and allow the easy-mcp-ai/v1 endpoints in its own REST settings. Then press Re-run checks.', 'easy-mcp-ai' );

            
            
            
            if ( is_array( $observation ) && $observation['reached'] && self::observation_matches( $observation, $identities ) ) {
                $when = \wp_date( \get_option( 'date_format', 'Y-m-d' ), $observation['time'] );
                if ( $observation['seen'] && $observation['cleared'] ) {
                    $measured = sprintf(
                        /* translators: 1: date, 2: a WP_Error code such as rest_cannot_access. */
                        __( 'Measured on %1$s: a test request to this plugin\'s endpoints was refused with a sign-in challenge (%2$s) and let through to the plugin, which then checked the credential itself.', 'easy-mcp-ai' ),
                        $when,
                        '' !== $observation['code'] ? $observation['code'] : '401'
                    );
                } else {
                    $measured = sprintf(
                        /* translators: %s: date. */
                        __( 'Measured on %s: a test request to this plugin\'s endpoints reached the plugin without being refused by that filter.', 'easy-mcp-ai' ),
                        $when
                    );
                }
                return Diagnostic_Result::pass(
                    'e3',
                    Diagnostic_Result::TIER_INFO,
                    __( 'AI routes get past REST authentication filters', 'easy-mcp-ai' ),
                    __( 'Another plugin or theme filters REST API authentication on this site, and this plugin\'s own endpoints are exempted from a "sign in required" refusal there; the filter keeps protecting every other REST route.', 'easy-mcp-ai' ) . ' ' . $measured . self::named_callbacks( $foreign ),
                    $evidence
                );
            }

            
            
            
            
            if ( ! is_array( $observation ) ) {
                $why = __( 'Whether the exemption lets a request through on this site has not been measured yet.', 'easy-mcp-ai' );
                $fix = __( 'Press Re-run checks: the connection test sends a real request through that filter and records what it answered.', 'easy-mcp-ai' );
            } elseif ( ! self::observation_matches( $observation, $identities ) ) {
                $why = __( 'The filter callbacks have changed since the exemption was last measured, so the earlier measurement no longer applies.', 'easy-mcp-ai' );
                $fix = __( 'Press Re-run checks so the connection test measures the filter as it is now.', 'easy-mcp-ai' );
            } else {
                $why = sprintf(
                    /* translators: %s: date. */
                    __( 'Measured on %s: a test request to this plugin\'s endpoints did not get through to the plugin. The exemption covers a "sign in required" refusal only; a refusal that enforces a policy (an IP or rate rule, or a plugin that blocks at dispatch, as Solid Security\'s and All-In-One Security\'s REST restrictions do) is not cleared, and the callback named here is the likely cause.', 'easy-mcp-ai' ),
                    \wp_date( \get_option( 'date_format', 'Y-m-d' ), $observation['time'] )
                );
                $fix = $fix_own_allowlist;
            }

            return Diagnostic_Result::warn(
                'e3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Another plugin or theme filters REST API authentication on this site. This plugin exempts its own endpoints from a "sign in required" refusal there, but not from a policy refusal.', 'easy-mcp-ai' ) . ' ' . $why . self::named_callbacks( $foreign ),
                $fix,
                $evidence
            );
        }

        if ( $hooked ) {
            















            $named = self::named_callbacks( $foreign );

            return Diagnostic_Result::warn(
                'e3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Another plugin or theme filters REST API authentication on this site, and this plugin reaches WordPress through that API. Plugins that legitimately add their own authentication — WooCommerce, Jetpack and similar — are already excluded from this check, so what is named here is worth identifying: a "disable REST API" or "restrict REST API" feature works the same way.', 'easy-mcp-ai' ) . $named,
                __( 'Identify the plugin the callback above belongs to. If it offers a "disable REST API" or "restrict REST API" setting, exempt the easy-mcp-ai/v1 namespace so AI clients can authenticate.', 'easy-mcp-ai' ),
                
                
                
                
                
                array(
                    'rest_authentication_hooked' => true,
                    'callbacks'                  => array_values( $foreign ),
                )
            );
        }

        
        
        
        
        return Diagnostic_Result::pass(
            'e3',
            Diagnostic_Result::TIER_WARNING,
            $label,
            __( 'Nothing was modifying REST authentication at the time this check ran. A plugin that only hooks in during a REST request would not be visible from the admin screen.', 'easy-mcp-ai' ),
            array( 'rest_authentication_hooked' => false )
        );
    }

    





























    public static function evaluate_hook_stripping( $marker_count, $capture_enabled ) {
        $label = __( 'Change tracking hooks stay registered', 'easy-mcp-ai' );

        
        if ( null === $marker_count ) {
            return Diagnostic_Result::unknown(
                'e4',
                Diagnostic_Result::TIER_INFO,
                $label,
                __( 'Could not read the change history table.', 'easy-mcp-ai' )
            );
        }

        
        if ( ! $capture_enabled ) {
            return Diagnostic_Result::unknown(
                'e4',
                Diagnostic_Result::TIER_INFO,
                $label,
                __( 'Could not verify — change history recording is switched off, so no evidence is collected either way.', 'easy-mcp-ai' )
            );
        }

        if ( (int) $marker_count > 0 ) {
            return Diagnostic_Result::pass(
                'e4',
                Diagnostic_Result::TIER_INFO,
                $label,
                sprintf(
                    /* translators: 1: number of recorded reassertion events, 2: number of days in the reporting window. */
                    __( 'On %1$d occasion(s) in the last %2$d days, another plugin removed this plugin\'s tracking hooks during an AI request. They were re-registered automatically and the change was still recorded, so nothing was lost and no action is needed. If you are investigating missing history entries, Easy MCP AI → Activity → Change history lists the affected requests.', 'easy-mcp-ai' ),
                    (int) $marker_count,
                    self::REASSERTION_WINDOW_DAYS
                ),
                array( 'hook_reassertions' => (int) $marker_count )
            );
        }

        
        
        
        return Diagnostic_Result::pass(
            'e4',
            Diagnostic_Result::TIER_INFO,
            $label,
            sprintf(
                /* translators: %d: number of days in the reporting window. */
                __( 'No hook removals recorded in the last %d days.', 'easy-mcp-ai' ),
                self::REASSERTION_WINDOW_DAYS
            ),
            array( 'hook_reassertions' => 0 )
        );
    }

    






















    public static function evaluate_coming_soon_plugins( $found ) {
        
        
        
        $label = __( 'Coming-soon and maintenance pages', 'easy-mcp-ai' );

        
        
        if ( null === $found ) {
            return Diagnostic_Result::unknown(
                'e6',
                Diagnostic_Result::TIER_INFO,
                $label,
                __( 'Could not read the list of active plugins, so this was not checked.', 'easy-mcp-ai' )
            );
        }

        $found = (array) $found;

        if ( empty( $found ) ) {
            return Diagnostic_Result::pass(
                'e6',
                Diagnostic_Result::TIER_INFO,
                $label,
                __( 'No coming-soon or maintenance plugin found among the active plugins. A holding page added by your theme or your host would not be visible from here.', 'easy-mcp-ai' ),
                array( 'coming_soon_plugins' => array() )
            );
        }

        return Diagnostic_Result::pass(
            'e6',
            Diagnostic_Result::TIER_INFO,
            $label,
            sprintf(
                
                
                
                /* translators: %s: the plugin name, or a comma-separated list of plugin names when more than one is active. */
                __( 'Detected: %s. Nothing here indicates a problem — putting a site behind a holding page is a deliberate choice. Worth knowing, though: the screen where you approve an AI client is a normal page on the front of your site, not part of the admin area, so a holding page can hide it. If approving a connection never loads, allow the address ?easy_mcp_ai_oauth=authorize through, or switch the holding page off while you connect.', 'easy-mcp-ai' ),
                implode( ', ', $found )
            ),
            array( 'coming_soon_plugins' => $found )
        );
    }

    


















    public static function evaluate_cache_plugins( $found, $coverage = null ) {
        $label = __( 'Caching excludes API endpoints', 'easy-mcp-ai' );

        
        
        if ( null === $found ) {
            return Diagnostic_Result::unknown( 'e5', Diagnostic_Result::TIER_WARNING, $label, __( 'Could not read the list of active plugins.', 'easy-mcp-ai' ) );
        }
        $found = (array) $found;

        if ( empty( $found ) ) {
            return Diagnostic_Result::pass(
                'e5',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'No caching plugin found among the active plugins. Caching done by the host or a CDN is not visible from here.', 'easy-mcp-ai' ),
                array( 'cache_plugins' => array() )
            );
        }

        $coverage = is_array( $coverage ) ? $coverage : array();
        $uncovered = array();
        $unreadable = array();
        foreach ( $found as $name ) {
            $verdict = isset( $coverage[ $name ] ) ? $coverage[ $name ] : self::CACHE_UNREADABLE;
            if ( self::CACHE_UNCOVERED === $verdict ) {
                $uncovered[] = $name;
            } elseif ( self::CACHE_COVERED !== $verdict ) {
                $unreadable[] = $name;
            }
        }

        $evidence = array(
            'cache_plugins'  => $found,
            'coverage'       => $coverage,
            'checked_paths'  => array_values( self::protected_paths() ),
        );

        if ( $uncovered ) {
            return Diagnostic_Result::warn(
                'e5',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    count( $uncovered ) > 1
                        /* translators: %s: comma-separated plugin names. */
                        ? __( '%s are caching without an exclusion for this plugin\'s endpoints. A cached authentication or discovery response can make a connection succeed once and then fail, or keep working after a token is revoked.', 'easy-mcp-ai' )
                        /* translators: %s: a plugin name. */
                        : __( '%s is caching without an exclusion for this plugin\'s endpoints. A cached authentication or discovery response can make a connection succeed once and then fail, or keep working after a token is revoked.', 'easy-mcp-ai' ),
                    implode( ', ', $uncovered )
                ),
                sprintf(
                    count( $uncovered ) > 1
                        /* translators: %s: comma-separated URL paths to exclude. */
                        ? __( 'Exclude %s from page caching in each of those plugins\' settings.', 'easy-mcp-ai' )
                        /* translators: %s: comma-separated URL paths to exclude. */
                        : __( 'Exclude %s from page caching in that plugin\'s settings.', 'easy-mcp-ai' ),
                    self::list_paths( self::exclusion_hints() )
                ),
                $evidence
            );
        }

        if ( $unreadable ) {
            return Diagnostic_Result::unknown(
                'e5',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    count( $unreadable ) > 1
                        /* translators: 1: comma-separated plugin names, 2: comma-separated URL paths to exclude. */
                        ? __( 'Detected: %1$s. Their exclusion rules could not be read from here, so this was not verified either way. If AI connections drop or behave inconsistently, exclude %2$s from page caching.', 'easy-mcp-ai' )
                        /* translators: 1: a plugin name, 2: comma-separated URL paths to exclude. */
                        : __( 'Detected: %1$s. Its exclusion rules could not be read from here, so this was not verified either way. If AI connections drop or behave inconsistently, exclude %2$s from page caching.', 'easy-mcp-ai' ),
                    implode( ', ', $unreadable ),
                    self::list_paths( self::exclusion_hints() )
                ),
                $evidence
            );
        }

        return Diagnostic_Result::pass(
            'e5',
            Diagnostic_Result::TIER_WARNING,
            $label,
            sprintf(
                count( $found ) > 1
                    /* translators: %s: comma-separated plugin names. */
                    ? __( 'Detected: %s. Their own exclusion rules already cover this plugin\'s endpoints.', 'easy-mcp-ai' )
                    /* translators: %s: a plugin name. */
                    : __( 'Detected: %s. Its own exclusion rules already cover this plugin\'s endpoints.', 'easy-mcp-ai' ),
                implode( ', ', $found )
            ),
            $evidence
        );
    }

    

    











    public static function protected_paths() {
        $paths = array();

        
        
        
        
        
        
        foreach ( array(
            'MCP endpoint'        => array( 'rest_url', 'easy-mcp-ai/v1/mcp' ),
            'OAuth server'        => array( 'home_url', '/.well-known/oauth-authorization-server' ),
            'OAuth resource'      => array( 'home_url', '/.well-known/oauth-protected-resource' ),
            'OpenID discovery'    => array( 'home_url', '/.well-known/openid-configuration' ),
        ) as $label => $spec ) {
            list( $fn, $arg ) = $spec;
            if ( ! function_exists( $fn ) ) {
                continue;
            }
            $parts = \wp_parse_url( (string) $fn( $arg ) );
            if ( ! is_array( $parts ) || ! isset( $parts['path'] ) || '' === $parts['path'] ) {
                continue;
            }
            $subject = $parts['path'];
            if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
                $subject .= '?' . $parts['query'];
            }
            $paths[ $label ] = $subject;
        }

        return $paths;
    }

    












    private static function exclusion_hints() {
        $hints = array();

        foreach ( self::protected_paths() as $subject ) {
            if ( false !== strpos( $subject, 'rest_route=' ) ) {
                $hints[] = 'rest_route=/easy-mcp-ai/';
                continue;
            }
            
            
            $ns = strpos( $subject, '/easy-mcp-ai/' );
            if ( false !== $ns ) {
                $hints[] = substr( $subject, 0, $ns + strlen( '/easy-mcp-ai/' ) );
                continue;
            }
            $oauth = strpos( $subject, '/oauth-' );
            if ( false !== $oauth ) {
                $hints[] = substr( $subject, 0, $oauth + strlen( '/oauth-' ) ) . '*';
                continue;
            }
            $hints[] = $subject;
        }

        return array_values( array_unique( $hints ) );
    }

    



    private static function list_paths( array $hints ) {
        if ( count( $hints ) < 2 ) {
            return implode( '', $hints );
        }
        $last = array_pop( $hints );
        return sprintf(
            /* translators: 1: comma-separated list of all but the last item, 2: the last item. */
            __( '%1$s and %2$s', 'easy-mcp-ai' ),
            implode( ', ', $hints ),
            $last
        );
    }

    







    private static function regex_hits( $pattern, $subject, $insensitive = false ) {
        if ( '' === $pattern || false !== strpos( $pattern, '~' ) ) {
            return false;
        }
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- a third party's stored pattern may not compile; false is the answer, not a warning.
        return 1 === @preg_match( '~' . $pattern . '~' . ( $insensitive ? 'i' : '' ), $subject );
    }

    






















    private static function rule_covers( $slug, $rule, $subject ) {
        
        
        
        
        
        
        $candidates = array( $rule );
        if ( 'wp-rocket' === $slug ) {
            $candidates[] = rtrim( (string) $rule, '/' );
        }

        foreach ( array_unique( $candidates ) as $r ) {
            $r = trim( (string) $r );
            if ( '' === $r ) {
                continue;
            }

            if ( 'w3-total-cache' === $slug && self::regex_hits( $r, $subject, true ) ) {
                return true;
            }
            if ( 'wp-super-cache' === $slug && self::regex_hits( $r, $subject ) ) {
                return true;
            }
            if ( 'litespeed-cache' === $slug && self::litespeed_hits( $r, $subject ) ) {
                return true;
            }
            if ( 'wp-rocket' === $slug ) {
                
                
                
                if ( self::regex_hits( $r, $subject, true )
                    || false !== strpos( $subject, $r ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    
    private static function litespeed_hits( $rule, $subject ) {
        $starts = '^' === substr( $rule, 0, 1 );
        $ends   = '$' === substr( $rule, -1 );

        if ( $starts && $ends ) {
            return substr( $rule, 1, -1 ) === $subject;
        }
        if ( $ends ) {
            $needle = substr( $rule, 0, -1 );
            return '' !== $needle && substr( $subject, -strlen( $needle ) ) === $needle;
        }
        if ( $starts ) {
            $needle = substr( $rule, 1 );
            return '' !== $needle && 0 === strpos( $subject, $needle );
        }
        return false !== strpos( $subject, $rule );
    }

    
    private static function wpfc_hits( $entry, $subject ) {
        if ( ! is_object( $entry ) || ! isset( $entry->content ) ) {
            return false;
        }
        if ( isset( $entry->type ) && 'page' !== $entry->type ) {
            return false;
        }
        $content = trim( (string) $entry->content );
        $prefix  = isset( $entry->prefix ) ? (string) $entry->prefix : '';
        if ( '' === $content ) {
            return false;
        }

        switch ( $prefix ) {
            case 'exact':
                return strtolower( trim( $content, '/' ) ) === strtolower( trim( $subject, '/' ) );
            case 'regex':
                return self::regex_hits( $content, $subject, true );
            case 'startwith':
                
                
                
                
                
                return 0 === stripos( ltrim( $subject, '/' ), ltrim( $content, '/' ) );
            case 'contain':
                return false !== stripos( $subject, $content );
        }

        return false;
    }

    











    private static function cache_rules_for( $slug ) {
        try {
            switch ( $slug ) {
                case 'wp-rocket':
                    if ( function_exists( 'get_rocket_option' ) ) {
                        $rules = \get_rocket_option( 'cache_reject_uri', array() );
                        return is_array( $rules ) ? $rules : null;
                    }
                    return null;

                case 'wp-super-cache':
                    
                    
                    return isset( $GLOBALS['cache_rejected_uri'] ) && is_array( $GLOBALS['cache_rejected_uri'] )
                        ? $GLOBALS['cache_rejected_uri']
                        : null;

                case 'w3-total-cache':
                    if ( function_exists( 'w3tc_config' ) ) {
                        $config = \w3tc_config();
                        if ( is_object( $config ) && method_exists( $config, 'get_array' ) ) {
                            $rules = $config->get_array( 'pgcache.reject.uri' );
                            return is_array( $rules ) ? $rules : null;
                        }
                    }
                    return null;

                case 'litespeed-cache':
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    $rules = \apply_filters( 'litespeed_conf', 'cache-exc' );
                    return is_array( $rules ) ? $rules : null;

                case 'wp-fastest-cache':
                    
                    
                    
                    
                    
                    
                    $json = \get_option( 'WpFastestCacheExclude', '' );
                    if ( ! is_string( $json ) || '' === $json ) {
                        return array();
                    }
                    $rules = json_decode( $json );
                    return is_array( $rules ) ? $rules : null;
            }
        } catch ( \Throwable $e ) {
            
            
            return null;
        }

        return null;
    }

    





    public static function cache_exclusion_coverage( array $detected, ?array $paths = null ) {
        $paths = null === $paths ? self::protected_paths() : $paths;
        if ( ! $paths ) {
            
            return array_fill_keys( array_values( $detected ), self::CACHE_UNREADABLE );
        }

        $coverage = array();
        foreach ( $detected as $slug => $name ) {
            $rules = self::cache_rules_for( $slug );
            if ( null === $rules ) {
                $coverage[ $name ] = self::CACHE_UNREADABLE;
                continue;
            }

            $all_covered = true;
            foreach ( $paths as $subject ) {
                $hit = false;
                foreach ( $rules as $rule ) {
                    $hit = ( 'wp-fastest-cache' === $slug )
                        ? self::wpfc_hits( $rule, $subject )
                        : self::rule_covers( $slug, $rule, $subject );
                    if ( $hit ) {
                        break;
                    }
                }
                if ( ! $hit ) {
                    $all_covered = false;
                    break;
                }
            }

            $coverage[ $name ] = $all_covered ? self::CACHE_COVERED : self::CACHE_UNCOVERED;
        }

        return $coverage;
    }

    







    private static function cache_coverage_now() {
        $detected = self::detected_cache_plugins();
        return null === $detected ? null : self::cache_exclusion_coverage( $detected );
    }

    




    private static function detected_cache_plugins() {
        $known = \apply_filters( 'easy_mcp_ai_diagnostics_known_cache_plugins', self::KNOWN_CACHE_PLUGINS );
        if ( ! is_array( $known ) ) {
            $known = self::KNOWN_CACHE_PLUGINS;
        }

        $active = self::active_plugin_paths();
        if ( null === $active ) {
            return null;
        }

        $slugs = array();
        foreach ( $active as $path ) {
            $path = (string) $path;
            $dir  = ( false !== strpos( $path, '/' ) ) ? substr( $path, 0, strpos( $path, '/' ) ) : $path;

            $slugs[ strtolower( $dir ) ] = true;
        }

        $found = array();
        foreach ( $known as $slug => $name ) {
            if ( isset( $slugs[ strtolower( (string) $slug ) ] ) ) {
                $found[ (string) $slug ] = (string) $name;
            }
        }

        return $found;
    }

    

    






    private static function active_from( $filter, array $known ) {
        
        
        
        
        
        
        
        
        
        
        
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- active_from() is private with three call sites, each passing a literal 'easy_mcp_ai_diagnostics_known_*' name; the sniff only sees the variable.
        $filtered = \apply_filters( $filter, $known );
        if ( is_array( $filtered ) ) {
            $known = $filtered;
        }

        $active = self::active_plugin_paths();
        if ( null === $active ) {
            return null;
        }

        return self::match_known( $active, $known );
    }

    










    private static function active_plugin_paths() {
        $active = \get_option( 'active_plugins', null );
        if ( ! is_array( $active ) ) {
            return null;
        }

        
        
        
        if ( function_exists( 'is_multisite' ) && \is_multisite() && function_exists( 'get_site_option' ) ) {
            $network = \get_site_option( 'active_sitewide_plugins', array() );
            if ( is_array( $network ) ) {
                $active = array_merge( $active, array_keys( $network ) );
            }
        }

        return $active;
    }

    









    public static function match_known( array $active, array $known ) {
        $slugs = array();
        foreach ( $active as $path ) {
            $path = (string) $path;
            $dir  = ( false !== strpos( $path, '/' ) ) ? substr( $path, 0, strpos( $path, '/' ) ) : $path;
            $slugs[ strtolower( $dir ) ] = true;
        }

        $found = array();
        foreach ( $known as $slug => $name ) {
            if ( isset( $slugs[ strtolower( (string) $slug ) ] ) ) {
                $found[] = (string) $name;
            }
        }

        return $found;
    }

    














    



    const CORE_REST_AUTH_CALLBACKS = array(
        'rest_cookie_check_errors',
        'rest_application_password_check_errors',
        'wp_is_application_passwords_available',
    );

    













    






















    const BENIGN_REST_AUTH_CLASSES = array(
        
        'WC_REST_Authentication',
        
        'Automattic\\WooCommerce\\StoreApi\\Authentication',
        
        'Automattic\\Jetpack\\Connection\\Rest_Authentication',
        
        'Ai1wm_Rest_Controller',
    );

    




    private static function benign_rest_auth_classes() {
        $list = self::BENIGN_REST_AUTH_CLASSES;
        if ( function_exists( 'apply_filters' ) ) {
            $filtered = \apply_filters( 'easy_mcp_ai_diagnostics_benign_rest_auth_classes', $list );
            if ( is_array( $filtered ) ) {
                $list = $filtered;
            }
        }
        return array_map( 'strval', $list );
    }

    
    private static function is_benign_rest_auth_callback( $name ) {
        $sep = strpos( $name, '::' );
        if ( false === $sep ) {
            return false;
        }
        $class = ltrim( substr( $name, 0, $sep ), '\\' );
        foreach ( self::benign_rest_auth_classes() as $benign ) {
            if ( 0 === strcasecmp( $class, ltrim( $benign, '\\' ) ) ) {
                return true;
            }
        }
        return false;
    }

    










    public static function foreign_rest_auth_callbacks( array $callback_names ) {
        $foreign = array();
        foreach ( $callback_names as $name ) {
            $name = (string) $name;
            if ( '' === $name ) {
                continue;
            }
            if ( in_array( $name, self::CORE_REST_AUTH_CALLBACKS, true ) ) {
                continue;
            }
            if ( self::is_benign_rest_auth_callback( $name ) ) {
                continue;
            }
            if ( self::is_core_defined_function( $name ) ) {
                continue;
            }
            $foreign[] = $name;
        }
        return array_values( array_unique( $foreign ) );
    }

    


























    private static function is_core_defined_function( $name ) {
        if ( false !== strpos( $name, '::' ) || ! function_exists( $name ) ) {
            return false;
        }
        if ( ! defined( 'ABSPATH' ) || ! class_exists( '\ReflectionFunction' ) ) {
            return false;
        }
        try {
            $file = ( new \ReflectionFunction( $name ) )->getFileName();
        } catch ( \Throwable $e ) {
            return false;
        }
        if ( ! is_string( $file ) || '' === $file ) {
            return false; 
        }

        $core = \wp_normalize_path( ABSPATH . 'wp-includes/' );
        return 0 === strpos( \wp_normalize_path( $file ), $core );
    }

    





    private static function named_callbacks( array $foreign ) {
        $shown = array_slice( $foreign, 0, 3 );
        if ( ! $shown ) {
            return '';
        }
        $named = ' ' . sprintf(
            /* translators: %s: comma-separated list of PHP callback names. */
            __( 'Found: %s.', 'easy-mcp-ai' ),
            implode( ', ', $shown )
        );
        $remaining = count( $foreign ) - count( $shown );
        if ( $remaining > 0 ) {
            $named .= ' ' . sprintf(
                /* translators: %d: number of additional callbacks not listed. */
                _n( 'And %d more.', 'And %d more.', $remaining, 'easy-mcp-ai' ),
                $remaining
            );
        }
        return $named;
    }

    










    private static function rest_auth_override_active() {
        if ( ! class_exists( '\Easy_MCP_AI\MCP\Rest_Auth_Override' ) ) {
            $file = dirname( __DIR__ ) . '/mcp/class-rest-auth-override.php';
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }
        if ( ! class_exists( '\Easy_MCP_AI\MCP\Rest_Auth_Override' ) ) {
            return false;
        }
        return \Easy_MCP_AI\MCP\Rest_Auth_Override::enabled();
    }

    



    const REST_AUTH_OBSERVATION_OPTION = 'easy_mcp_ai_rest_auth_observed';

    
















    public static function record_rest_auth_observation( $body, $secret ) {
        $reached = is_array( $body ) && ! empty( $body['proof'] )
            && class_exists( '\Easy_MCP_AI\Diagnostics\Check_Header_Probe' )
            && hash_equals( Check_Header_Probe::expected_proof( $secret ), (string) $body['proof'] );

        $seen = ( $reached && isset( $body['rest_auth'] ) && is_array( $body['rest_auth'] ) ) ? $body['rest_auth'] : null;

        \update_option(
            self::REST_AUTH_OBSERVATION_OPTION,
            array(
                'time'      => time(),
                'reached'   => $reached,
                'seen'      => null !== $seen,
                'code'      => ( null !== $seen && isset( $seen['code'] ) ) ? (string) $seen['code'] : '',
                'status'    => ( null !== $seen && isset( $seen['status'] ) && is_numeric( $seen['status'] ) ) ? (int) $seen['status'] : null,
                'cleared'   => null !== $seen && ! empty( $seen['cleared'] ),
                
                'callbacks' => self::foreign_rest_auth_identities(),
            ),
            false
        );
    }

    






    public static function rest_auth_observation() {
        $o = \get_option( self::REST_AUTH_OBSERVATION_OPTION, null );
        if ( ! is_array( $o ) || ! isset( $o['time'], $o['reached'], $o['callbacks'] ) || ! is_array( $o['callbacks'] ) ) {
            return null;
        }
        return array(
            'time'      => (int) $o['time'],
            'reached'   => (bool) $o['reached'],
            'seen'      => ! empty( $o['seen'] ),
            'code'      => isset( $o['code'] ) ? (string) $o['code'] : '',
            'status'    => ( isset( $o['status'] ) && is_numeric( $o['status'] ) ) ? (int) $o['status'] : null,
            'cleared'   => ! empty( $o['cleared'] ),
            'callbacks' => array_values( array_map( 'strval', $o['callbacks'] ) ),
        );
    }

    








    public static function observation_matches( array $observation, array $foreign ) {
        
        
        
        $then = array_values( array_map( 'strval', $observation['callbacks'] ) );
        $now  = array_values( array_map( 'strval', $foreign ) );
        sort( $then );
        sort( $now );
        return $then === $now;
    }

    


    public static function has_third_party_rest_auth_hook( array $callback_names ) {
        
        
        return array() !== self::foreign_rest_auth_callbacks( $callback_names );
    }

    





    public static function rest_auth_callback_names() {
        $names = array();
        foreach ( self::rest_auth_callbacks() as $cb ) {
            $names[] = $cb['name'];
        }
        return $names;
    }

    


















    public static function rest_auth_callbacks() {
        global $wp_filter;

        if ( ! isset( $wp_filter['rest_authentication_errors'] ) ) {
            return array();
        }

        $hook = $wp_filter['rest_authentication_errors'];
        if ( ! isset( $hook->callbacks ) || ! is_array( $hook->callbacks ) ) {
            return array();
        }

        $out = array();
        foreach ( $hook->callbacks as $priority => $priority_group ) {
            if ( ! is_array( $priority_group ) ) {
                continue;
            }
            $i = 0;
            foreach ( $priority_group as $entry ) {
                $callback = isset( $entry['function'] ) ? $entry['function'] : null;
                ++$i;

                if ( is_string( $callback ) ) {
                    $out[] = array( 'name' => $callback, 'id' => $callback );
                } elseif ( is_array( $callback ) && isset( $callback[1] ) ) {
                    $owner = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
                    $name  = $owner . '::' . $callback[1];
                    $out[] = array( 'name' => $name, 'id' => $name );
                } elseif ( $callback instanceof \Closure ) {
                    
                    
                    $out[] = array( 'name' => 'closure', 'id' => self::closure_identity( $callback, (string) $priority . '#' . $i ) );
                } elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
                    $name  = get_class( $callback ) . '::__invoke';
                    $out[] = array( 'name' => $name, 'id' => $name );
                }
            }
        }

        return $out;
    }

    







    private static function closure_identity( $closure, $position ) {
        try {
            $ref  = new \ReflectionFunction( $closure );
            $file = $ref->getFileName();
            $line = $ref->getStartLine();
            if ( is_string( $file ) && '' !== $file && is_int( $line ) ) {
                $file = function_exists( 'wp_normalize_path' ) ? \wp_normalize_path( $file ) : str_replace( '\\', '/', $file );
                if ( defined( 'ABSPATH' ) ) {
                    $root = function_exists( 'wp_normalize_path' ) ? \wp_normalize_path( ABSPATH ) : str_replace( '\\', '/', ABSPATH );
                    if ( 0 === strpos( $file, $root ) ) {
                        $file = substr( $file, strlen( $root ) );
                    }
                }
                return 'closure:' . $file . ':' . $line;
            }
        } catch ( \Throwable $e ) {
            unset( $e );
        }
        return 'closure:@' . $position;
    }

    





    public static function foreign_rest_auth_identities() {
        $ids = array();
        foreach ( self::rest_auth_callbacks() as $cb ) {
            if ( array() !== self::foreign_rest_auth_callbacks( array( $cb['name'] ) ) ) {
                $ids[] = $cb['id'];
            }
        }
        return $ids;
    }

    private static function change_capture_enabled() {
        return (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_enabled', true );
    }

    



    
    const REASSERTION_WINDOW_DAYS = 30;

    









    private static function count_hook_reassertions() {
        global $wpdb;
        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
            return null; 
        }

        $table = $wpdb->prefix . 'easy_mcp_ai_change_log';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; diagnostics must read live state.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}`
                 WHERE object_type = %s AND object_id = %s
                   AND created_at >= DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY )",
                'system',
                '_hooks_reasserted',
                self::REASSERTION_WINDOW_DAYS
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return null === $count ? null : (int) $count;
    }
}
