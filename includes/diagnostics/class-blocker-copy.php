<?php









namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Blocker_Copy {

    




    const ORDER = array( 'a8', 'a9', 'a12', 'a7', 'a10', 'c1', 'f8', 'b1', 'd1' );

    



    public static function sort( array $results ) {
        $rank = array_flip( self::ORDER );
        $keyed = array();
        foreach ( array_values( $results ) as $index => $result ) {
            $keyed[] = array( isset( $rank[ $result->id() ] ) ? $rank[ $result->id() ] : count( $rank ) + $index, $result );
        }
        usort( $keyed, static function ( $a, $b ) {
            return $a[0] - $b[0];
        } );
        return array_column( $keyed, 1 );
    }

    



    public static function for_result( Diagnostic_Result $r ) {
        $evidence = (array) $r->evidence();
        switch ( $r->id() ) {
            case 'a7':
                $copy = array(
                    __( 'Permalinks are set to Plain', 'easy-mcp-ai' ),
                    __( 'permalinks are set to Plain', 'easy-mcp-ai' ),
                    __( 'WordPress won’t route /wp-json/ or /.well-known/, so clients can’t find this site’s OAuth endpoints.', 'easy-mcp-ai' ),
                    __( 'Settings → Permalinks → choose any structure other than Plain (Post name is the usual choice) → Save. No other change is needed.', 'easy-mcp-ai' ),
                );
                break;
            case 'a8':
                $copy = isset( $evidence['transport_problem'] ) && 'proxy' === $evidence['transport_problem']
                    ? array(
                        __( 'PHP doesn’t see this site’s HTTPS', 'easy-mcp-ai' ),
                        __( 'PHP doesn’t see this site’s HTTPS', 'easy-mcp-ai' ),
                        __( 'A CDN or proxy handles HTTPS and passes requests on unencrypted, so OAuth requests are refused. Existing API tokens keep working.', 'easy-mcp-ai' ),
                        $r->fix(),
                    )
                    : array(
                        __( 'This site isn’t served over HTTPS', 'easy-mcp-ai' ),
                        __( 'the site isn’t on HTTPS', 'easy-mcp-ai' ),
                        __( 'OAuth requires HTTPS, so sign-in and discovery requests are refused. Existing API tokens keep working.', 'easy-mcp-ai' ),
                        __( 'Serve the site over HTTPS, then update WordPress Address and Site Address in Settings → General to match.', 'easy-mcp-ai' ),
                    );
                break;
            case 'a9':
                $copy = array(
                    __( 'A firewall or CDN is blocking AI assistants', 'easy-mcp-ai' ),
                    __( 'a firewall or CDN is blocking AI assistants', 'easy-mcp-ai' ),
                    __( 'Requests carrying the assistant’s name are rejected before WordPress sees them. Sign-in looks fine, then the client fails.', 'easy-mcp-ai' ),
                    __( 'This is a setting on your CDN, firewall or security plugin, not in WordPress. On Cloudflare, look for the AI bot or “AI Scrapers and Crawlers” blocking option and let this site’s AI endpoint through. A robots.txt that disallows ClaudeBot is a sign the same rule set is on.', 'easy-mcp-ai' ),
                );
                break;
            case 'a10':
                if ( ! empty( $evidence['trailing_slash'] ) ) {
                    
                    
                    $copy = array(
                        __( 'Your server adds a trailing slash to /.well-known/', 'easy-mcp-ai' ),
                        __( 'your server adds a trailing slash to /.well-known/', 'easy-mcp-ai' ),
                        __( 'The two sign-in documents at your domain root are redirected to a trailing slash, so a client that does not follow redirects can’t sign in.', 'easy-mcp-ai' ),
                        $r->fix(),
                    );
                    break;
                }
                $copy = isset( $evidence['reason'] ) && 'stale' === $evidence['reason']
                    ? array(
                        __( 'Your saved sign-in documents are out of date', 'easy-mcp-ai' ),
                        __( 'the saved sign-in documents are out of date', 'easy-mcp-ai' ),
                        __( 'The copies served at /.well-known/ no longer match this site, so clients can’t sign in.', 'easy-mcp-ai' ),
                        $r->fix(),
                    )
                    : array(
                        __( 'Your host intercepts /.well-known/', 'easy-mcp-ai' ),
                        __( 'your host intercepts /.well-known/', 'easy-mcp-ai' ),
                        isset( $evidence['workaround'] ) && 'off' === $evidence['workaround']
                            ? __( 'Clients can’t read the two sign-in documents at your domain root, and this plugin’s workaround for blocked hosts is switched off on this site.', 'easy-mcp-ai' )
                            : __( 'Clients can’t read the two sign-in documents at your domain root, so connecting fails at the first step.', 'easy-mcp-ai' ),
                        __( 'Ask your host to let /.well-known/ reach WordPress, or at least the two addresses starting /.well-known/oauth-. If they won’t, they can place both documents in /.well-known/ as ordinary files, copied from the /wp-json/ address. Full instructions at easymcpai.com/well-known.', 'easy-mcp-ai' ),
                    );
                break;
            case 'a12':
                $copy = array(
                    __( 'POST requests with JSON are refused', 'easy-mcp-ai' ),
                    __( 'POST requests with JSON are refused', 'easy-mcp-ai' ),
                    __( 'Something in front of WordPress rejects the request type AI clients use, so no call gets through.', 'easy-mcp-ai' ),
                    __( 'Ask your host, CDN or security plugin to allow POST requests carrying JSON to /wp-json/easy-mcp-ai/. A rule that only permits GET, or that rejects JSON bodies, is the usual cause.', 'easy-mcp-ai' ),
                );
                break;
            case 'b1':
                $copy = array(
                    __( 'Sessions can’t be stored', 'easy-mcp-ai' ),
                    __( 'sessions can’t be stored', 'easy-mcp-ai' ),
                    self::session_short( $evidence ),
                    __( 'If this site uses Redis or Memcached, check that the service is running. Otherwise remove wp-content/object-cache.php so WordPress falls back to storing sessions in the database.', 'easy-mcp-ai' ),
                );
                break;
            case 'c1':
                $copy = self::tables_copy( isset( $evidence['missing_tables'] ) ? (array) $evidence['missing_tables'] : array() );
                break;
            case 'd1':
                $copy = self::visibility_copy( $r, isset( $evidence['tokens'] ) ? (array) $evidence['tokens'] : array() );
                break;
            case 'f8':
                $copy = array(
                    __( 'No working token or OAuth grant', 'easy-mcp-ai' ),
                    __( 'there’s no working token or OAuth grant', 'easy-mcp-ai' ),
                    __( 'Every API token and OAuth grant has expired or been revoked.', 'easy-mcp-ai' ),
                    __( 'Create a new API token, or reconnect your AI client to issue a fresh OAuth grant.', 'easy-mcp-ai' ),
                );
                break;
            default:
                $copy = array(
                    /* translators: %s: the check's name, which states the healthy property. */
                    sprintf( __( 'Check failed: %s', 'easy-mcp-ai' ), $r->label() ),
                    /* translators: %s: the check's name, which states the healthy property. */
                    sprintf( __( 'the “%s” check failed', 'easy-mcp-ai' ), $r->label() ),
                    (string) $r->detail(),
                    $r->fix(),
                );
        }

        return array(
            'id'     => $r->id(),
            'title'  => (string) $copy[0],
            'inline' => (string) $copy[1],
            'short'  => (string) $copy[2],
            'fix'    => (string) $copy[3],
        );
    }

    private static function session_short( array $evidence ) {
        if ( isset( $evidence['read_back'] ) && 'altered' === $evidence['read_back'] ) {
            return __( 'A test value came back changed, so session data is corrupted and connections drop unpredictably.', 'easy-mcp-ai' );
        }
        if ( isset( $evidence['read_back'] ) && false === $evidence['read_back'] ) {
            return __( 'A test value vanished as soon as it was stored, so every AI session expires the moment it’s created.', 'easy-mcp-ai' );
        }
        return __( 'WordPress refused to store a test value, so every AI session is rejected the moment it’s created.', 'easy-mcp-ai' );
    }

    private static function tables_copy( array $missing ) {
        $names = implode( ', ', array_map( 'strval', $missing ) );
        if ( count( $missing ) > 1 ) {
            return array(
                __( 'Plugin database tables are missing', 'easy-mcp-ai' ),
                __( 'plugin database tables are missing', 'easy-mcp-ai' ),
                /* translators: %s: comma-separated database table names. */
                sprintf( __( '%s are missing, so the features that use them fail and authentication may too.', 'easy-mcp-ai' ), $names ),
                __( 'Deactivate and reactivate the plugin to recreate them. If they’re still missing afterwards, the database user probably can’t create tables. See the database privileges check.', 'easy-mcp-ai' ),
            );
        }
        return array(
            __( 'A plugin database table is missing', 'easy-mcp-ai' ),
            __( 'a plugin database table is missing', 'easy-mcp-ai' ),
            /* translators: %s: database table name. */
            sprintf( __( '%s is missing, so the features that use it fail and authentication may too.', 'easy-mcp-ai' ), $names ),
            __( 'Deactivate and reactivate the plugin to recreate it. If it’s still missing afterwards, the database user probably can’t create tables. See the database privileges check.', 'easy-mcp-ai' ),
        );
    }

    private static function visibility_copy( Diagnostic_Result $r, array $tokens ) {
        $blind = array_values( array_filter( $tokens, static function ( $t ) {
            return is_array( $t ) && 0 === (int) ( isset( $t['visible'] ) ? $t['visible'] : 0 );
        } ) );

        if ( 1 !== count( $blind ) ) {
            $names = array();
            foreach ( $blind as $t ) {
                $names[] = isset( $t['name'] ) ? (string) $t['name'] : __( 'unnamed', 'easy-mcp-ai' );
            }
            /* translators: %d: number of API tokens. */
            $title = sprintf( __( '%d API tokens see no tools', 'easy-mcp-ai' ), count( $blind ) );
            return array(
                $title,
                $title,
                /* translators: %s: comma-separated API token names. */
                sprintf( __( 'None of these tokens can use a single tool: %s.', 'easy-mcp-ai' ), implode( ', ', $names ) ),
                $r->fix(),
            );
        }

        $token = $blind[0];
        $name  = isset( $token['name'] ) ? (string) $token['name'] : __( 'unnamed', 'easy-mcp-ai' );
        $user  = isset( $token['user'] ) ? (string) $token['user'] : '?';
        $stage = isset( $token['zeroed_by'] ) ? $token['zeroed_by'] : null;
        /* translators: %s: API token name. */
        $title = sprintf( __( '“%s” sees no tools', 'easy-mcp-ai' ), $name );

        switch ( $stage ) {
            case 'disabled_tools':
                /* translators: %s: WordPress username. */
                $short = sprintf( __( 'Every tool this token (user %s) is allowed to use is switched off in Settings.', 'easy-mcp-ai' ), $user );
                $fix   = __( 'Re-enable the tools you need under Settings → Disabled tools.', 'easy-mcp-ai' );
                break;
            case 'token_allowlist':
                /* translators: %s: WordPress username. */
                $short = sprintf( __( 'This token (user %s) is limited to tools that don’t exist or aren’t registered.', 'easy-mcp-ai' ), $user );
                $fix   = $r->fix();
                break;
            case 'allowed_tool_patterns':
                /* translators: %s: WordPress username. */
                $short = sprintf( __( 'The tool filter patterns in Settings exclude every tool this token (user %s) may use.', 'easy-mcp-ai' ), $user );
                $fix   = $r->fix();
                break;
            case 'capability':
                /* translators: %s: WordPress username. */
                $short = sprintf( __( 'The WordPress user this token belongs to (%s) doesn’t have permission for any of its tools.', 'easy-mcp-ai' ), $user );
                $fix   = $r->fix();
                break;
            default:
                $short = __( 'No tools are registered.', 'easy-mcp-ai' );
                $fix   = $r->fix();
        }

        return array( $title, $title, $short, $fix );
    }
}
