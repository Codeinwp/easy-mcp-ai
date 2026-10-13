<?php




































namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Discovery {

    
    const TIMEOUT = 5;

    
    const MAX_BODY_BYTES = 262144;

    


    public static function run() {
        try {
            $mirrors = array();
            $roots   = array();
            foreach ( self::mirror_urls() as $name => $url ) {
                $mirrors[ $name ] = self::probe( $url );
            }
            foreach ( self::root_urls() as $name => $url ) {
                $roots[ $name ] = self::probe( $url );
            }
        } catch ( \Throwable $e ) {
            return array( self::unknown( __( 'The discovery test could not run on this site.', 'easy-mcp-ai' ) ) );
        }

        $result   = self::evaluate( $mirrors, $roots );
        $mcp_path = null;
        if ( true === self::root_blocked_verdict( $result ) ) {
            try {
                $probes = array();
                foreach ( self::mcp_path_urls() as $name => $url ) {
                    $probes[ $name ] = self::probe( $url );
                }
                $mcp_path = self::mcp_path_verdict( $probes, \rest_url( 'easy-mcp-ai/v1/mcp' ) );
            } catch ( \Throwable $e ) {
                $mcp_path = null;
            }
        }
        self::record( $result, $mcp_path );
        if ( defined( 'EASY_MCP_AI_PLUGIN_DIR' ) ) {
            require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/oauth/class-root-fallback.php';
            $result = self::with_workaround(
                $result,
                \Easy_MCP_AI\OAuth\Root_Fallback::mode(),
                \Easy_MCP_AI\OAuth\Root_Fallback::is_armed(),
                $mcp_path,
                '' === (string) \get_option( 'permalink_structure' )
            );
        }
        return array( $result );
    }

    






















    public static function with_workaround( Diagnostic_Result $result, $mode, $armed, $mcp_path, $plain ) {
        $evidence = (array) $result->evidence();
        if ( Diagnostic_Result::STATUS_FAIL !== $result->status() || ! isset( $evidence['reason'] ) || 'blocked' !== $evidence['reason'] ) {
            return $result;
        }

        if ( 'off' === $mode ) {
            $evidence['workaround'] = 'off';
            return Diagnostic_Result::fail(
                'a10',
                Diagnostic_Result::TIER_BLOCKER,
                $result->label(),
                $result->detail() . ' ' . __( 'This plugin\'s workaround for blocked hosts is switched off on this site (EASY_MCP_AI_OAUTH_ROOT_FALLBACK).', 'easy-mcp-ai' ),
                $result->fix(),
                $evidence
            );
        }

        if ( ! $armed || $plain || ! is_bool( $mcp_path ) ) {
            return $result;
        }

        if ( $mcp_path ) {
            $evidence['workaround'] = 'mcp_path';
            return Diagnostic_Result::warn(
                'a10',
                Diagnostic_Result::TIER_WARNING,
                $result->label(),
                __( 'Your host intercepts /.well-known/, and this plugin is working around it. The sign-in documents at your domain root never reach WordPress, so this plugin points AI clients at copies served under this site\'s AI connection address instead; ChatGPT and Claude connect through them. A client that only looks at the domain root can still fail.', 'easy-mcp-ai' ),
                __( 'Nothing is needed for ChatGPT or Claude. To make every client work, ask your host to let /.well-known/ reach WordPress, or at least the two addresses starting /.well-known/oauth-. Full instructions: https://easymcpai.com/well-known', 'easy-mcp-ai' ),
                $evidence
            );
        }

        $evidence['workaround'] = 'root';
        return Diagnostic_Result::warn(
            'a10',
            Diagnostic_Result::TIER_WARNING,
            $result->label(),
            __( 'Your host blocks /.well-known/, and ChatGPT and Codex can\'t connect. The host refuses that path at your domain root and under this site\'s AI connection address, so the sign-in documents never reach WordPress. Claude and most other clients should still connect through this plugin\'s workaround at the site root; ChatGPT and Codex look for the documents only at addresses your host refuses.', 'easy-mcp-ai' ),
            __( 'Ask your host to let /.well-known/ reach WordPress. A rule that refuses every path containing /. (common in nginx configurations) blocks it too. Full instructions: https://easymcpai.com/well-known', 'easy-mcp-ai' ),
            $evidence
        );
    }

    





    public static function mcp_path_urls() {
        return array(
            'oauth-protected-resource'   => \rest_url( 'easy-mcp-ai/v1/mcp/.well-known/oauth-protected-resource' ),
            'oauth-authorization-server' => \rest_url( 'easy-mcp-ai/v1/mcp/.well-known/oauth-authorization-server' ),
            'openid-configuration'       => \rest_url( 'easy-mcp-ai/v1/mcp/.well-known/openid-configuration' ),
        );
    }

    















    public static function mcp_path_verdict( array $probes, $mcp_url ) {
        foreach ( array_keys( self::mcp_path_urls() ) as $name ) {
            $probe = isset( $probes[ $name ] ) ? $probes[ $name ] : null;
            if ( ! is_array( $probe ) || 200 !== (int) $probe['status'] ) {
                return false;
            }
            $data = json_decode( (string) $probe['body'], true );
            if ( ! is_array( $data ) ) {
                return false;
            }
            $named = 'oauth-protected-resource' === $name
                ? ( isset( $data['authorization_servers'] ) ? $data['authorization_servers'] : null )
                : ( isset( $data['issuer'] ) ? array( $data['issuer'] ) : null );
            if ( array( (string) $mcp_url ) !== $named ) {
                return false;
            }
        }
        return true;
    }

    















    public static function root_blocked_verdict( Diagnostic_Result $result ): ?bool {
        $evidence = (array) $result->evidence();
        $reason   = isset( $evidence['reason'] ) ? $evidence['reason'] : '';
        if ( 'blocked' === $reason && Diagnostic_Result::STATUS_UNKNOWN !== $result->status() ) {
            return true;
        }
        if ( Diagnostic_Result::STATUS_PASS === $result->status() || 'stale' === $reason || Diagnostic_Result::STATUS_WARN === $result->status() ) {
            return false;
        }
        return null;
    }

    







    private static function record( Diagnostic_Result $result, $mcp_path = null ): void {
        $blocked = self::root_blocked_verdict( $result );
        if ( null === $blocked || ! defined( 'EASY_MCP_AI_PLUGIN_DIR' ) ) {
            return;
        }
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/oauth/class-root-fallback.php';
        \Easy_MCP_AI\OAuth\Root_Fallback::record_root_discovery( $blocked, $mcp_path );
    }

    





    const SIGN_IN_FIELDS = array(
        'issuer',
        'resource',
        'authorization_servers',
        'authorization_endpoint',
        'token_endpoint',
        'registration_endpoint',
        'revocation_endpoint',
    );

    




    public static function root_urls() {
        return array(
            'oauth-protected-resource'   => \home_url( '/.well-known/oauth-protected-resource' ),
            'oauth-authorization-server' => \home_url( '/.well-known/oauth-authorization-server' ),
        );
    }

    









    public static function mirror_urls() {
        
        
        
        
        
        
        
        
        
        
        
        
        
        return array(
            'oauth-protected-resource'   => \rest_url( 'easy-mcp-ai/v1/discovery/oauth-protected-resource' ),
            'oauth-authorization-server' => \rest_url( 'easy-mcp-ai/v1/discovery/oauth-authorization-server' ),
        );
    }

    






    public static function sign_in_fields_that_differ( array $root, array $mirror ) {
        $differ = array();
        foreach ( self::SIGN_IN_FIELDS as $field ) {
            $a = isset( $root[ $field ] ) ? $root[ $field ] : null;
            $b = isset( $mirror[ $field ] ) ? $mirror[ $field ] : null;
            if ( $a !== $b ) {
                $differ[] = $field;
            }
        }
        return $differ;
    }

    








    public static function expected_marker() {
        return \home_url();
    }

    


















    public static function is_our_document( $result ) {
        if ( ! is_array( $result ) || 200 !== (int) $result['status'] ) {
            return false;
        }

        
        
        $data = json_decode( (string) $result['body'], true );
        if ( ! is_array( $data ) ) {
            return false;
        }

        
        
        $marker = self::expected_marker();
        foreach ( array( 'issuer', 'resource' ) as $field ) {
            if ( isset( $data[ $field ] ) && is_string( $data[ $field ] )
                && 0 === strpos( $data[ $field ], $marker ) ) {
                return true;
            }
        }

        return isset( $data['authorization_servers'][0] )
            && $data['authorization_servers'][0] === $marker;
    }

    
















    public static function looks_like_our_document( $result ) {
        if ( ! is_array( $result ) || 200 !== (int) $result['status'] ) {
            return false;
        }

        $data = json_decode( (string) $result['body'], true );
        if ( ! is_array( $data ) ) {
            return false;
        }

        
        
        return isset( $data['resource'] ) || isset( $data['issuer'] );
    }

    



    public static function evaluate( array $mirrors, array $roots ) {
        $label = self::label();

        $blocked  = array();
        $stale    = array();   
        $measured = 0;
        $evidence = array();

        foreach ( self::root_urls() as $name => $unused ) {
            $mirror = isset( $mirrors[ $name ] ) ? $mirrors[ $name ] : null;
            $root   = isset( $roots[ $name ] ) ? $roots[ $name ] : null;

            $evidence[ $name ] = is_array( $root )
                ? (int) $root['status'] . ( '' !== $root['type'] ? ' ' . $root['type'] : '' )
                : 'no response';

            
            
            if ( ! self::is_our_document( $mirror ) ) {
                $evidence[ $name . '_mirror' ] = is_array( $mirror ) ? $mirror['status'] : 'no response';
                continue;
            }
            ++$measured;

            if ( ! self::looks_like_our_document( $root ) ) {
                $blocked[] = $name;
                continue;
            }

            
            
            
            
            
            
            
            $a = json_decode( (string) $root['body'], true );
            $b = json_decode( (string) $mirror['body'], true );
            
            
            
            
            
            
            
            
            
            
            self::ksort_recursive( $a );
            self::ksort_recursive( $b );
            if ( $a !== $b ) {
                $stale[ $name ] = self::sign_in_fields_that_differ( $a, $b );
            }
        }

        
        if ( 0 === $measured ) {
            
            
            
            
            
            
            
            
            
            
            
            
            
            $redirected = false;
            
            
            
            
            $scanned = array_merge( array_values( (array) $mirrors ), array_values( (array) $roots ) );
            foreach ( $scanned as $result ) {
                if ( is_array( $result ) && (int) $result['status'] >= 300 && (int) $result['status'] < 400 ) {
                    $redirected = true;
                    break;
                }
            }

            if ( $redirected ) {
                return self::unknown(
                    __( 'This site\'s own address redirects somewhere else, so nothing could be checked. Every test request was sent away rather than answered. The usual cause is the address in Settings → General not matching the one the server actually serves — http:// where the server forces https://, or the bare domain where it adds www. Correct it there, or ask your host which address is the real one, then run these checks again.', 'easy-mcp-ai' ),
                    $evidence
                );
            }

            return self::unknown(
                __( 'The test request did not reach this site\'s own code, so the result would not mean anything. Some hosts stop a site from calling its own address; on its own that is not a fault.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        
        
        
        
        
        
        $partial = '';
        if ( $measured < count( self::root_urls() ) ) {
            $unmeasured = array();
            foreach ( self::root_urls() as $name => $unused ) {
                if ( ! self::is_our_document( isset( $mirrors[ $name ] ) ? $mirrors[ $name ] : null ) ) {
                    $unmeasured[] = $name;
                }
            }
            $partial = ' ' . sprintf(
                /* translators: %s: comma-separated document name(s) not checked. */
                __( 'Note that %s could not be checked at all, because this site could not fetch its own copy of it — nothing is known about that one either way.', 'easy-mcp-ai' ),
                implode( ', ', $unmeasured )
            );
        }

        
        
        if ( ! empty( $blocked ) ) {
            $evidence['reason'] = 'blocked';
            return Diagnostic_Result::fail(
                'a10',
                Diagnostic_Result::TIER_BLOCKER,
                $label,
                self::blocked_detail( $blocked, $roots ) . $partial,
                __( 'Ask your host to let /.well-known/ reach WordPress, or at least the two addresses starting /.well-known/oauth-. This is a rule on their side; the plugin cannot move these addresses, because the standards AI clients follow define them as sitting at your domain root. If your host will not change it, they can instead place the two documents in the .well-known folder of your site as ordinary files — copy each one from the /wp-json/ address that still works. Full instructions: https://easymcpai.com/well-known', 'easy-mcp-ai' ),
                $evidence
            );
        }

        if ( ! empty( $stale ) ) {
            $critical = array_filter( $stale );
            $names    = implode( ', ', array_keys( $stale ) );
            $fix      = __( 'Replace the out-of-date copy. Open the matching /wp-json/easy-mcp-ai/v1/discovery/ address on this site, which always shows the current version, and save it over the file in your .well-known folder.', 'easy-mcp-ai' );

            if ( ! empty( $critical ) ) {
                $evidence['reason'] = 'stale';
                return Diagnostic_Result::fail(
                    'a10',
                    Diagnostic_Result::TIER_BLOCKER,
                    $label,
                    sprintf(
                        /* translators: 1: document name(s), 2: comma-separated field names. */
                        __( 'The sign-in details published at this site\'s address are out of date, and the parts that differ are the ones a client needs to sign in: %2$s (in %1$s). These addresses are being served from a saved copy rather than by WordPress, and the copy no longer matches this site.', 'easy-mcp-ai' ),
                        
                        
                        
                        
                        
                        implode( ', ', array_keys( $critical ) ),
                        implode( ', ', array_unique( call_user_func_array( 'array_merge', array_values( $critical ) ) ) )
                    ),
                    $fix,
                    $evidence
                );
            }

            return Diagnostic_Result::warn(
                'a10',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: document name(s). */
                    __( 'The sign-in details published at this site\'s address can be read, but they no longer match what this site would produce (%s). Signing in should still work; the list of permissions an AI client is offered may be out of date. These addresses are being served from a saved copy rather than by WordPress.', 'easy-mcp-ai' ),
                    $names
                ),
                $fix,
                $evidence
            );
        }

        
        
        
        
        
        
        
        
        
        
        $total = count( self::root_urls() );
        if ( $measured < $total ) {
            $unmeasured = array();
            foreach ( self::root_urls() as $name => $unused ) {
                if ( ! self::is_our_document( isset( $mirrors[ $name ] ) ? $mirrors[ $name ] : null ) ) {
                    $unmeasured[] = $name;
                }
            }

            return self::unknown(
                sprintf(
                    /* translators: %s: comma-separated document name(s) that could not be checked. */
                    __( 'Only part of the sign-in details could be checked. What was checked is being served correctly, but this site could not fetch its own copy of %s, so nothing is known about that one either way. Some hosts stop a site from calling its own address; on its own that is not a fault. Run the checks again, and if it keeps happening ask your host whether the site can reach itself.', 'easy-mcp-ai' ),
                    implode( ', ', $unmeasured )
                ),
                $evidence
            );
        }

        return Diagnostic_Result::pass(
            'a10',
            Diagnostic_Result::TIER_BLOCKER,
            $label,
            __( 'Both sign-in documents are readable at this site\'s address and match what this site publishes, as AI clients require.', 'easy-mcp-ai' ),
            $evidence
        );
    }

    





    public static function blocked_detail( array $blocked, array $roots = array() ) {
        
        
        
        
        
        
        
        
        $answered = false;
        foreach ( $blocked as $name ) {
            if ( isset( $roots[ $name ] ) && is_array( $roots[ $name ] ) ) {
                $answered = true;
                break;
            }
        }

        $intro = $answered
            ? __( 'Before an AI client can sign in, it has to read two small documents from this site\'s address. Something in front of WordPress is answering for them instead, so the request never reaches this site\'s own code. The same documents are served correctly on a different address, which is how this was measured — so nothing is wrong with the plugin or with WordPress.', 'easy-mcp-ai' )
            : __( 'Before an AI client can sign in, it has to read two small documents from this site\'s address. Those requests got no reply at all, so a client cannot begin. The same documents are served correctly on a different address, which is how this was measured — so nothing is wrong with the plugin or with WordPress. A firewall rule, a timeout or a DNS problem on that address are the usual causes.', 'easy-mcp-ai' );

        if ( count( $blocked ) > 1 ) {
            return $intro . ' ' . __( 'Both documents are affected, so connecting will fail at the first step.', 'easy-mcp-ai' );
        }

        return $intro . ' ' . sprintf(
            /* translators: %s: the name of the blocked discovery document, e.g. oauth-authorization-server. */
            __( 'One document is affected: %s.', 'easy-mcp-ai' ),
            $blocked[0]
        );
    }

    

    






    private static function probe( $url ) {
        if ( ! function_exists( 'wp_remote_get' ) ) {
            return null;
        }

        $response = \wp_remote_get(
            $url,
            array(
                'timeout'     => self::TIMEOUT,
                'redirection' => 0,
                
                
                
                
                
                
                'limit_response_size' => self::MAX_BODY_BYTES,
                
                
                
                'sslverify'   => false,
            )
        );

        if ( \is_wp_error( $response ) ) {
            return null;
        }

        return array(
            'status' => (int) \wp_remote_retrieve_response_code( $response ),
            'type'   => (string) \wp_remote_retrieve_header( $response, 'content-type' ),
            'body'   => (string) \wp_remote_retrieve_body( $response ),
        );
    }

    private static function label() {
        return __( 'AI clients can read this site\'s sign-in details', 'easy-mcp-ai' );
    }

    private static function unknown( $reason, array $evidence = array() ) {
        return Diagnostic_Result::unknown( 'a10', Diagnostic_Result::TIER_BLOCKER, self::label(), $reason, $evidence );
    }

    










    private static function ksort_recursive( &$value ) {
        if ( ! is_array( $value ) ) {
            return;
        }

        foreach ( $value as &$child ) {
            self::ksort_recursive( $child );
        }
        unset( $child );

        
        if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
            ksort( $value );
        }
    }
}
