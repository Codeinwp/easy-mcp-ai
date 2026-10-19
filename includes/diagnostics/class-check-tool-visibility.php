<?php

































namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Tool_Visibility {

    
    const ABILITIES_MIN_WP = '6.9';

    
    const LOW_VISIBILITY_RATIO = 0.25;

    











    public static function run( $registry = null ) {
        $registry = self::registry( $registry );
        $results  = self::run_with_definitions(
            self::tool_definitions( $registry ),
            self::off_names( $registry ),
            (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_allowed_tool_patterns', array() )
        );
        $results[] = self::evaluate_override_names( self::override_names( $registry ) );
        return $results;
    }

    







    private static function off_names( $registry ) {
        if ( null === $registry ) {
            return (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_tools', array() );
        }
        return \Easy_MCP_AI\Tools\Tool_State::off_names( $registry );
    }

    
















    public static function run_with_definitions( array $defs, array $disabled, array $patterns ) {
        if ( empty( $defs ) ) {
            $reason = __( 'Could not read the list of registered tools on this request.', 'easy-mcp-ai' );

            return array(
                Diagnostic_Result::unknown( 'd1', Diagnostic_Result::TIER_BLOCKER, __( 'Every API token can see tools', 'easy-mcp-ai' ), $reason ),
                Diagnostic_Result::unknown( 'd2', Diagnostic_Result::TIER_WARNING, __( 'Token users can reach the tools they need', 'easy-mcp-ai' ), $reason ),
                Diagnostic_Result::unknown( 'd3', Diagnostic_Result::TIER_WARNING, __( 'Tool filter patterns match something', 'easy-mcp-ai' ), $reason ),
                Diagnostic_Result::unknown( 'd4', Diagnostic_Result::TIER_WARNING, __( 'All tool categories are available', 'easy-mcp-ai' ), $reason ),
                self::evaluate_grant_scopes( self::grant_rows() ),
                Diagnostic_Result::unknown( 'd6', Diagnostic_Result::TIER_WARNING, __( 'WordPress Abilities available', 'easy-mcp-ai' ), $reason ),
                Diagnostic_Result::unknown( 'd7', Diagnostic_Result::TIER_WARNING, __( 'Plugin ability schemas are client-compatible', 'easy-mcp-ai' ), $reason ),
            );
        }

        $tokens = self::token_visibility_rows( $defs, $disabled, $patterns );
        $scan   = self::scan_ability_schemas( $defs );

        return array(
            self::evaluate_token_visibility( $tokens ),
            self::evaluate_low_capability( $tokens ),
            self::evaluate_dead_patterns( self::dead_patterns( $defs, $patterns ) ),
            self::evaluate_disabled_categories( self::fully_disabled_categories( $defs, $disabled ) ),
            self::evaluate_grant_scopes( self::grant_rows() ),
            self::evaluate_abilities(
                \get_bloginfo( 'version' ),
                self::count_abilities( $defs ),
                self::count_registered_abilities(),
                self::enabled_abilities_missing_from_registry()
            ),
            self::evaluate_sanitizer(
                null === $scan ? array() : $scan['rewritten'],
                null === $scan ? array() : $scan['unrepairable'],
                null !== $scan,
                null === $scan ? null : $scan['scanned']
            ),
        );
    }

    





















    public static function scan_ability_schemas( array $defs ) {
        if ( ! class_exists( '\Easy_MCP_AI\MCP\Gemini_Safe_Schema' ) ) {
            return null;
        }

        $rewritten    = array();
        $unrepairable = array();
        $scanned      = 0;

        foreach ( $defs as $def ) {
            $name = isset( $def['name'] ) ? (string) $def['name'] : '';
            if ( '' === $name || 0 !== strpos( $name, 'wp_ability_' ) ) {
                continue;
            }
            $scanned++;

            if ( ! isset( $def['schema'] ) || ! is_array( $def['schema'] ) ) {
                continue;
            }

            try {
                $result = \Easy_MCP_AI\MCP\Gemini_Safe_Schema::sanitize( $def['schema'] );
            } catch ( \Throwable $e ) {
                continue; 
            }

            $map = ( isset( $result['map'] ) && is_array( $result['map'] ) ) ? $result['map'] : array();
            if ( empty( $map ) ) {
                continue;
            }

            $rewritten[ $name ] = count( $map );

            
            
            
            
            
            if ( array() !== \Easy_MCP_AI\MCP\Gemini_Safe_Schema::unreachable_paths( $map ) ) {
                $unrepairable[] = $name;
            }
        }

        return array(
            'rewritten'    => $rewritten,
            'unrepairable' => array_values( array_unique( $unrepairable ) ),
            'scanned'      => $scanned,
        );
    }

    















    public static function resolve( array $defs, array $allowed, array $disabled, array $patterns, callable $can ) {
        $names = array_map( static function ( $d ) {
            return isset( $d['name'] ) ? (string) $d['name'] : '';
        }, $defs );
        $names = array_values( array_filter( $names ) );

        if ( empty( $names ) ) {
            return array( 'visible' => array(), 'zeroed_by' => null, 'eligible' => 0, 'missing_caps' => array() );
        }

        
        
        $surviving = array_values( array_filter( $names, static function ( $name ) use ( $allowed ) {
            return \Easy_MCP_AI\Auth\Permission_Guard::allowlist_permits( $allowed, $name );
        } ) );
        if ( empty( $surviving ) ) {
            return array( 'visible' => array(), 'zeroed_by' => 'token_allowlist', 'eligible' => 0, 'missing_caps' => array() );
        }

        
        if ( ! empty( $disabled ) ) {
            $surviving = array_values( array_filter( $surviving, static function ( $name ) use ( $disabled ) {
                return ! in_array( $name, $disabled, true );
            } ) );
            if ( empty( $surviving ) ) {
                return array( 'visible' => array(), 'zeroed_by' => 'disabled_tools', 'eligible' => 0, 'missing_caps' => array() );
            }
        }

        
        if ( ! empty( $patterns ) ) {
            $surviving = array_values( array_filter( $surviving, static function ( $name ) use ( $patterns ) {
                return self::matches_pattern_filter( $name, $patterns );
            } ) );
            if ( empty( $surviving ) ) {
                return array( 'visible' => array(), 'zeroed_by' => 'allowed_tool_patterns', 'eligible' => 0, 'missing_caps' => array() );
            }
        }

        
        $by_name = array();
        foreach ( $defs as $d ) {
            if ( isset( $d['name'] ) ) {
                $by_name[ (string) $d['name'] ] = $d;
            }
        }

        
        
        $eligible = count( $surviving );

        $missing_caps = array();

        $surviving = array_values( array_filter( $surviving, static function ( $name ) use ( $by_name, $can, &$missing_caps ) {
            if ( ! isset( $by_name[ $name ] ) ) {
                return true; 
            }
            $def = $by_name[ $name ];
            $cap = self::effective_capability(
                isset( $def['category'] ) ? $def['category'] : '',
                isset( $def['capability'] ) ? $def['capability'] : ''
            );
            if ( ! $cap || (bool) call_user_func( $can, $cap ) ) {
                return true;
            }
            
            
            $missing_caps[ $cap ] = true;
            return false;
        } ) );

        $missing_caps = array_keys( $missing_caps );

        if ( empty( $surviving ) ) {
            return array( 'visible' => array(), 'zeroed_by' => 'capability', 'eligible' => $eligible, 'missing_caps' => $missing_caps );
        }

        return array( 'visible' => $surviving, 'zeroed_by' => null, 'eligible' => $eligible, 'missing_caps' => $missing_caps );
    }

    





    public static function evaluate_token_visibility( $tokens ) {
        $label = __( 'Every API token can see tools', 'easy-mcp-ai' );

        if ( ! is_array( $tokens ) ) {
            return Diagnostic_Result::unknown( 'd1', Diagnostic_Result::TIER_BLOCKER, $label, __( 'Could not read the API tokens.', 'easy-mcp-ai' ) );
        }

        $blind = array();

        foreach ( $tokens as $t ) {
            if ( 0 === (int) ( isset( $t['visible'] ) ? $t['visible'] : 0 ) ) {
                $blind[] = $t;
            }
        }

        if ( empty( $blind ) ) {
            
            
            
            
            
            return Diagnostic_Result::pass(
                'd1',
                Diagnostic_Result::TIER_BLOCKER,
                $label,
                empty( $tokens ) ? __( 'No API tokens exist yet, so there was nothing to check.', 'easy-mcp-ai' ) : self::describe_counts( $tokens ),
                array( 'tokens' => $tokens )
            );
        }

        $lines = array();
        foreach ( $blind as $t ) {
            $lines[] = sprintf(
                /* translators: 1: token name, 2: WordPress user, 3: explanation of which filter hid the tools. */
                __( '"%1$s" (user %2$s) sees no tools — %3$s', 'easy-mcp-ai' ),
                isset( $t['name'] ) ? $t['name'] : __( 'unnamed', 'easy-mcp-ai' ),
                isset( $t['user'] ) ? $t['user'] : '?',
                self::stage_explanation( isset( $t['zeroed_by'] ) ? $t['zeroed_by'] : null )
            );
        }

        return Diagnostic_Result::fail(
            'd1',
            Diagnostic_Result::TIER_BLOCKER,
            $label,
            implode( ' ', $lines ),
            self::stage_fix( isset( $blind[0]['zeroed_by'] ) ? $blind[0]['zeroed_by'] : null ),
            array( 'tokens' => $tokens )
        );
    }

    
    public static function evaluate_low_capability( $tokens ) {
        $label   = __( 'Token users can reach the tools they need', 'easy-mcp-ai' );

        if ( ! is_array( $tokens ) ) {
            return Diagnostic_Result::unknown( 'd2', Diagnostic_Result::TIER_WARNING, $label, __( 'Could not read the API tokens.', 'easy-mcp-ai' ) );
        }

        $limited = array();

        foreach ( $tokens as $t ) {
            $visible = (int) ( isset( $t['visible'] ) ? $t['visible'] : 0 );

            
            
            
            
            
            
            
            
            
            if ( ! isset( $t['eligible'] ) ) {
                continue;
            }
            $eligible = (int) $t['eligible'];

            if ( $visible > 0 && $eligible > 0 && ( $visible / $eligible ) < self::LOW_VISIBILITY_RATIO ) {
                $limited[] = sprintf(
                    '%s (%d/%d%s)',
                    isset( $t['name'] ) ? $t['name'] : __( 'unnamed', 'easy-mcp-ai' ),
                    $visible,
                    $eligible,
                    ! empty( $t['missing_caps'] ) ? '; ' . implode( ', ', (array) $t['missing_caps'] ) : ''
                );
            }
        }

        if ( ! empty( $limited ) ) {
            return Diagnostic_Result::warn(
                'd2',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: token names with visible/total counts and missing capabilities. */
                    __( 'These tokens reach only a small part of the tool set because of their WordPress user\'s role: %s.', 'easy-mcp-ai' ),
                    implode( '; ', $limited )
                ),
                __( 'Assign the token to a user with a higher role, or lower the minimum capability for External Data tools under Easy MCP AI → Settings → Advanced.', 'easy-mcp-ai' ),
                array( 'limited_tokens' => $limited )
            );
        }

        return Diagnostic_Result::pass(
            'd2',
            Diagnostic_Result::TIER_WARNING,
            $label,
            empty( $tokens ) ? __( 'No API tokens exist yet, so there was nothing to check.', 'easy-mcp-ai' ) : '',
            array( 'limited_tokens' => array() )
        );
    }

    
    public static function evaluate_dead_patterns( array $dead ) {
        $label = __( 'Tool filter patterns match something', 'easy-mcp-ai' );

        if ( ! empty( $dead ) ) {
            return Diagnostic_Result::warn(
                'd3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated glob patterns. */
                    __( 'These tool filter patterns match no tool at all: %s. A pattern that matches nothing hides tools without any warning.', 'easy-mcp-ai' ),
                    implode( ', ', $dead )
                ),
                __( 'Correct or remove the pattern under Easy MCP AI → Settings → Advanced → Whitelist tools.', 'easy-mcp-ai' ),
                array( 'dead_patterns' => $dead )
            );
        }

        return Diagnostic_Result::pass( 'd3', Diagnostic_Result::TIER_WARNING, $label, '', array( 'dead_patterns' => array() ) );
    }

    
















    public static function evaluate_override_names( $status ): Diagnostic_Result {
        $label = __( 'Every tool EASY_MCP_AI_TOOLS names exists', 'easy-mcp-ai' );

        if ( null === $status ) {
            return Diagnostic_Result::unknown( 'd8', Diagnostic_Result::TIER_WARNING, $label, __( 'Could not read the list of tools this plugin ships.', 'easy-mcp-ai' ) );
        }
        if ( '' === $status['source'] ) {
            return Diagnostic_Result::pass( 'd8', Diagnostic_Result::TIER_WARNING, $label, __( 'EASY_MCP_AI_TOOLS is not set.', 'easy-mcp-ai' ), $status );
        }
        if ( 0 === $status['named'] ) {
            return Diagnostic_Result::pass( 'd8', Diagnostic_Result::TIER_WARNING, $label, __( 'EASY_MCP_AI_TOOLS names no tool.', 'easy-mcp-ai' ), $status );
        }

        $where = 'environment' === $status['source']
            ? __( 'the EASY_MCP_AI_TOOLS environment variable', 'easy-mcp-ai' )
            : __( 'the EASY_MCP_AI_TOOLS constant in wp-config.php', 'easy-mcp-ai' );

        $abilities = '';
        if ( ! empty( $status['abilities'] ) ) {
            $abilities = sprintf(
                /* translators: %s: comma-separated ability tool names. */
                __( 'These ability tools are not registered on this site now: %s. That is expected while the plugin that provides the ability is inactive; if it is active, the name is misspelled.', 'easy-mcp-ai' ),
                implode( ', ', $status['abilities'] )
            );
        }

        if ( ! empty( $status['unknown'] ) ) {
            $detail = sprintf(
                /* translators: 1: where EASY_MCP_AI_TOOLS is set, 2: comma-separated tool names. */
                __( 'These names in %1$s match no tool: %2$s. A name that matches no tool switches nothing, so the tool you meant is on or off exactly as if the entry were not there.', 'easy-mcp-ai' ),
                $where,
                implode( ', ', $status['unknown'] )
            );
            return Diagnostic_Result::warn(
                'd8',
                Diagnostic_Result::TIER_WARNING,
                $label,
                '' === $abilities ? $detail : $detail . ' ' . $abilities,
                sprintf(
                    /* translators: %s: where EASY_MCP_AI_TOOLS is set. */
                    __( 'Correct the names in %s. Tool names are exact and lower case, for example wp_delete_post.', 'easy-mcp-ai' ),
                    $where
                ),
                $status
            );
        }

        if ( '' !== $abilities ) {
            return Diagnostic_Result::warn(
                'd8',
                Diagnostic_Result::TIER_INFO,
                $label,
                $abilities,
                sprintf(
                    /* translators: %s: where EASY_MCP_AI_TOOLS is set. */
                    __( 'If the plugin is active, correct the name in %s: an ability tool is named wp_ability_ followed by the ability, as the Tools screen lists it.', 'easy-mcp-ai' ),
                    $where
                ),
                $status
            );
        }

        return Diagnostic_Result::pass(
            'd8',
            Diagnostic_Result::TIER_WARNING,
            $label,
            sprintf(
                /* translators: %d: number of tool names EASY_MCP_AI_TOOLS lists. */
                _n( 'The %d name it lists is a tool.', 'All %d names it lists are tools.', $status['named'], 'easy-mcp-ai' ),
                $status['named']
            ),
            $status
        );
    }

    







    public static function evaluate_disabled_categories( array $categories ) {
        $label = __( 'All tool categories are available', 'easy-mcp-ai' );

        if ( ! empty( $categories ) ) {
            return Diagnostic_Result::pass(
                'd4',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated tool category names. */
                    __( 'Every tool is switched off in: %s. If an AI client cannot do something in these areas, this is why.', 'easy-mcp-ai' ),
                    implode( ', ', $categories )
                ),
                array( 'disabled_categories' => $categories )
            );
        }

        return Diagnostic_Result::pass( 'd4', Diagnostic_Result::TIER_WARNING, $label, '', array( 'disabled_categories' => array() ) );
    }

    







    public static function evaluate_grant_scopes( $grants ) {
        $label = __( 'Connected clients have usable permissions', 'easy-mcp-ai' );

        if ( ! is_array( $grants ) ) {
            return Diagnostic_Result::unknown( 'd5', Diagnostic_Result::TIER_WARNING, $label, __( 'Could not read the connected clients.', 'easy-mcp-ai' ) );
        }

        $empty = array();

        foreach ( $grants as $g ) {
            if ( 0 === (int) ( isset( $g['tools'] ) ? $g['tools'] : 0 ) ) {
                $empty[] = isset( $g['client'] ) ? (string) $g['client'] : __( 'unnamed client', 'easy-mcp-ai' );
            }
        }

        
        
        
        $scope = ! empty( $grants[0]['truncated'] )
            ? ' ' . sprintf(
                /* translators: %d: number of connected clients examined. */
                __( '(Only the first %d connected clients were checked.)', 'easy-mcp-ai' ),
                self::GRANT_SCAN_LIMIT
            )
            : '';

        if ( ! empty( $empty ) ) {
            return Diagnostic_Result::warn(
                'd5',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated OAuth client names. */
                    __( 'These connected clients were granted permissions that resolve to no tools: %s. They can sign in and will see an empty tool list.', 'easy-mcp-ai' ),
                    implode( ', ', $empty )
                ) . $scope,
                __( 'Disconnect the client and reconnect it, approving broader permissions on the consent screen.', 'easy-mcp-ai' ),
                array( 'empty_grants' => $empty )
            );
        }

        return Diagnostic_Result::pass( 'd5', Diagnostic_Result::TIER_WARNING, $label, trim( $scope ), array( 'empty_grants' => array() ) );
    }

    



    public static function evaluate_abilities( $wp_version, $ability_count, $registered_in_wp = null, $enabled_but_missing = array() ) {
        $label    = __( 'WordPress Abilities available', 'easy-mcp-ai' );
        $missing  = array_values( array_filter( array_map( 'strval', (array) $enabled_but_missing ) ) );
        $evidence = array(
            'wp_version'          => (string) $wp_version,
            'abilities'           => (int) $ability_count,
            'registered_in_wp'    => $registered_in_wp,
            'enabled_but_missing' => $missing,
        );

        
        
        
        
        
        
        
        
        
        
        
        
        
        if ( $missing ) {
            return Diagnostic_Result::warn(
                'd6',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: 1: number of abilities, 2: comma-separated ability slugs. */
                    \_n(
                        '%1$d ability is switched on but WordPress has no record of it, so no tool was created for it: %2$s',
                        '%1$d abilities are switched on but WordPress has no record of them, so no tools were created for them: %2$s',
                        count( $missing ),
                        'easy-mcp-ai'
                    ),
                    count( $missing ),
                    implode( ', ', $missing )
                ),
                __( 'The plugin that published these abilities may have been deactivated, renamed them, or may conflict with another plugin bundling the same abilities library. Untick them under Easy MCP AI → Tools → Abilities, or resolve the conflicting plugin. Every other tool is unaffected.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        if ( version_compare( (string) $wp_version, self::ABILITIES_MIN_WP, '<' ) ) {
            return Diagnostic_Result::warn(
                'd6',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: 1: current WordPress version, 2: minimum required version. */
                    __( 'This site runs WordPress %1$s. Abilities published by other plugins only become available as MCP tools on WordPress %2$s and later.', 'easy-mcp-ai' ),
                    (string) $wp_version,
                    self::ABILITIES_MIN_WP
                ),
                __( 'Update WordPress to make plugin abilities available to your AI client.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        if ( 0 === (int) $ability_count ) {
            
            
            
            
            
            
            
            if ( is_int( $registered_in_wp ) && $registered_in_wp > 0 ) {
                return Diagnostic_Result::pass(
                    'd6',
                    Diagnostic_Result::TIER_WARNING,
                    $label,
                    sprintf(
                        /* translators: 1: "1 ability" or "N abilities", 2: nothing — the count is already inside %1$s. */
                        __( '%1$s available on this site. None are switched on, which is the default — abilities are opt-in under Easy MCP AI → Tools → Abilities.', 'easy-mcp-ai' ),
                        sprintf(
                            /* translators: %d: number of abilities registered in WordPress. */
                            \_n( '%d ability is', '%d abilities are', (int) $registered_in_wp, 'easy-mcp-ai' ),
                            (int) $registered_in_wp
                        )
                    ),
                    $evidence
                );
            }

            return Diagnostic_Result::pass(
                'd6',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Supported, but no installed plugin publishes any abilities yet. Nothing is wrong.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::pass(
            'd6',
            Diagnostic_Result::TIER_WARNING,
            $label,
            sprintf(
                /* translators: %d: number of registered abilities. */
                __( '%d abilities registered.', 'easy-mcp-ai' ),
                (int) $ability_count
            ),
            $evidence
        );
    }

    




















    public static function evaluate_sanitizer( array $rewritten, array $unrepairable, $sanitizer_available = true, $scanned_count = null ) {
        $label = __( 'Plugin ability schemas are client-compatible', 'easy-mcp-ai' );

        if ( ! $sanitizer_available ) {
            return Diagnostic_Result::unknown(
                'd7',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Could not verify — the schema compatibility component is not available on this request.', 'easy-mcp-ai' )
            );
        }

        if ( ! empty( $unrepairable ) ) {
            return Diagnostic_Result::warn(
                'd7',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated ability names. */
                    __( 'These plugin abilities publish a tool description this plugin cannot make safe: %s. Some AI clients reject the entire tool list when one entry is invalid, so this can hide every tool at once.', 'easy-mcp-ai' ),
                    implode( ', ', $unrepairable )
                ),
                __( 'Report the ability names above to the plugin that provides them. Deactivating that plugin restores the rest of the tool list in the meantime.', 'easy-mcp-ai' ),
                array( 'unrepairable' => $unrepairable, 'rewritten' => $rewritten )
            );
        }

        if ( ! empty( $rewritten ) ) {
            return Diagnostic_Result::pass(
                'd7',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %d: number of abilities adjusted automatically. */
                    __( '%d plugin ability schema(s) are adjusted automatically for client compatibility. No action needed.', 'easy-mcp-ai' ),
                    count( $rewritten )
                ),
                array( 'rewritten' => $rewritten )
            );
        }

        
        
        
        
        if ( null !== $scanned_count && 0 === (int) $scanned_count ) {
            return Diagnostic_Result::pass(
                'd7',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'No plugin abilities are registered, so there was nothing to check.', 'easy-mcp-ai' ),
                array( 'rewritten' => array(), 'unrepairable' => array() )
            );
        }

        return Diagnostic_Result::pass( 'd7', Diagnostic_Result::TIER_WARNING, $label, '', array( 'rewritten' => array(), 'unrepairable' => array() ) );
    }

    

    



    private static function matches_pattern_filter( $name, array $patterns ) {
        foreach ( $patterns as $pattern ) {
            $pattern = trim( (string) $pattern );
            if ( '' === $pattern ) {
                continue;
            }
            if ( false === strpos( $pattern, '*' ) && false === strpos( $pattern, '?' ) ) {
                $pattern = '*' . $pattern . '*';
            }
            if ( fnmatch( $pattern, $name ) ) {
                return true;
            }
        }
        return false;
    }

    



    private static function effective_capability( $category, $default_cap ) {
        if ( class_exists( '\Easy_MCP_AI\MCP\Server' ) ) {
            return \Easy_MCP_AI\MCP\Server::effective_required_capability( $category, $default_cap );
        }
        return $default_cap;
    }

    private static function stage_explanation( $stage ) {
        switch ( $stage ) {
            case 'token_allowlist':
                return __( 'the token is limited to tools that do not exist or are not registered.', 'easy-mcp-ai' );
            case 'disabled_tools':
                return __( 'every tool it is allowed to use has been switched off in Settings.', 'easy-mcp-ai' );
            case 'allowed_tool_patterns':
                return __( 'the tool filter patterns in Settings exclude all of them.', 'easy-mcp-ai' );
            case 'capability':
                return __( 'the WordPress user this token belongs to does not have permission for any of them.', 'easy-mcp-ai' );
            default:
                return __( 'no tools are registered.', 'easy-mcp-ai' );
        }
    }

    private static function stage_fix( $stage ) {
        switch ( $stage ) {
            case 'token_allowlist':
                return __( 'Edit the token under Easy MCP AI → Connections → API tokens and widen the tools it may use.', 'easy-mcp-ai' );
            case 'disabled_tools':
                return __( 'Re-enable the tools you need under Easy MCP AI → Tools → Core.', 'easy-mcp-ai' );
            case 'allowed_tool_patterns':
                return __( 'Correct or clear the patterns under Easy MCP AI → Settings → Advanced → Whitelist tools.', 'easy-mcp-ai' );
            case 'capability':
                return __( 'Assign the token to a WordPress user whose role permits these actions — an Editor or Administrator for most tools.', 'easy-mcp-ai' );
            default:
                return __( 'Check that the plugin is fully activated.', 'easy-mcp-ai' );
        }
    }

    

    
    private static function registry( $registry ) {
        if ( null !== $registry ) {
            return $registry;
        }
        if ( ! class_exists( '\Easy_MCP_AI\Tools\Tool_Registry' ) ) {
            return null;
        }
        $registry = new \Easy_MCP_AI\Tools\Tool_Registry();
        if ( method_exists( $registry, 'auto_discover' ) ) {
            $registry->auto_discover();
        }
        return $registry;
    }

    


    private static function tool_definitions( $registry ) {
        if ( null === $registry || ! method_exists( $registry, 'get_all_definitions' ) ) {
            return array();
        }

        $defs = array();
        foreach ( (array) $registry->get_all_definitions() as $definition ) {
            $name = isset( $definition['name'] ) ? (string) $definition['name'] : '';
            if ( '' === $name ) {
                continue;
            }
            $tool = method_exists( $registry, 'get_tool' ) ? $registry->get_tool( $name ) : null;
            $defs[] = array(
                'name'       => $name,
                'category'   => ( $tool && method_exists( $tool, 'get_category' ) ) ? $tool->get_category() : '',
                'capability' => ( $tool && method_exists( $tool, 'get_required_capability' ) ) ? $tool->get_required_capability() : '',
                
                
                'schema'     => ( isset( $definition['inputSchema'] ) && is_array( $definition['inputSchema'] ) ) ? $definition['inputSchema'] : null,
            );
        }

        return $defs;
    }

    
    const TOKEN_SCAN_LIMIT = 50;

    














    public static function decode_allowed_tools( $json ) {
        $decoded = json_decode( (string) $json, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    


    private static function token_visibility_rows( array $defs, array $disabled, array $patterns ) {
        $wpdb = self::wpdb( 'get_results' );
        if ( null === $wpdb || empty( $defs ) ) {
            return null; 
        }

        $table = $wpdb->prefix . 'easy_mcp_ai_tokens';
        
        
        $probe_limit = self::TOKEN_SCAN_LIMIT + 1;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; diagnostics must read live state.
        $rows = $wpdb->get_results(
            "SELECT name, wp_user_id, allowed_tools FROM `{$table}` WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) ORDER BY id LIMIT {$probe_limit}",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! is_array( $rows ) ) {
            return null;
        }

        $truncated = count( $rows ) > self::TOKEN_SCAN_LIMIT;
        if ( $truncated ) {
            $rows = array_slice( $rows, 0, self::TOKEN_SCAN_LIMIT );
        }

        $out = array();
        foreach ( $rows as $row ) {
            $user_id = (int) ( isset( $row['wp_user_id'] ) ? $row['wp_user_id'] : 0 );
            $allowed = self::decode_allowed_tools( isset( $row['allowed_tools'] ) ? $row['allowed_tools'] : '' );

            $resolved = self::resolve(
                $defs,
                $allowed,
                $disabled,
                $patterns,
                static function ( $cap ) use ( $user_id ) {
                    return \user_can( $user_id, $cap );
                }
            );

            $user = \get_userdata( $user_id );
            $out[] = array(
                'name'         => isset( $row['name'] ) ? (string) $row['name'] : '',
                'user'         => ( $user && isset( $user->user_login ) ) ? $user->user_login : (string) $user_id,
                'visible'      => count( $resolved['visible'] ),
                
                'eligible'     => isset( $resolved['eligible'] ) ? (int) $resolved['eligible'] : 0,
                'total'        => count( $defs ),
                'zeroed_by'    => $resolved['zeroed_by'],
                'missing_caps' => isset( $resolved['missing_caps'] ) ? (array) $resolved['missing_caps'] : array(),
                'truncated'    => $truncated,
            );
        }

        return $out;
    }

    
    private static function dead_patterns( array $defs, array $patterns ) {
        if ( empty( $patterns ) || empty( $defs ) ) {
            return array();
        }

        $dead = array();
        foreach ( $patterns as $pattern ) {
            if ( '' === trim( (string) $pattern ) ) {
                continue;
            }
            $matched = false;
            foreach ( $defs as $def ) {
                if ( self::matches_pattern_filter( (string) $def['name'], array( $pattern ) ) ) {
                    $matched = true;
                    break;
                }
            }
            if ( ! $matched ) {
                $dead[] = (string) $pattern;
            }
        }

        return $dead;
    }

    







    private static function override_names( $registry ) {
        return self::classify_override_names(
            \Easy_MCP_AI\Tools\Tool_State::override_source(),
            array_keys( \Easy_MCP_AI\Tools\Tool_State::overrides() ),
            static function () use ( $registry ) {
                return self::known_tool_names( $registry );
            }
        );
    }

    










    public static function classify_override_names( $source, array $named, callable $known ) {
        $named  = array_map( 'strval', $named );
        $status = array(
            'source'    => (string) $source,
            'named'     => count( $named ),
            'unknown'   => array(),
            'abilities' => array(),
        );
        if ( empty( $named ) ) {
            return $status;
        }

        $known = $known();
        if ( empty( $known ) ) {
            return null;
        }
        foreach ( $named as $name ) {
            if ( ! isset( $known[ $name ] ) ) {
                $status[ 0 === strpos( $name, 'wp_ability_' ) ? 'abilities' : 'unknown' ][] = $name;
            }
        }
        sort( $status['unknown'] );
        sort( $status['abilities'] );
        return $status;
    }

    










    private static function known_tool_names( $registry ) {
        $catalogue = new \Easy_MCP_AI\Tools\Tool_Registry();
        $catalogue->auto_discover( true, \Easy_MCP_AI\Tools\Tool_Directories::all() );
        $names = (array) $catalogue->get_all_tool_names();
        if ( empty( $names ) ) {
            return array();
        }
        $names[] = \Easy_MCP_AI\Tools\Feedback\Send_Feedback::NAME;
        if ( null !== $registry && method_exists( $registry, 'get_all_tool_names' ) ) {
            $names = array_merge( $names, (array) $registry->get_all_tool_names() );
        }
        if ( function_exists( 'wp_get_abilities' ) ) {
            $abilities = \wp_get_abilities();
            foreach ( is_array( $abilities ) ? array_keys( $abilities ) : array() as $slug ) {
                $names[] = \Easy_MCP_AI\Tools\Dynamic_Tool_Registrar::build_tool_name( (string) $slug );
            }
        }
        return array_fill_keys( array_map( 'strval', $names ), true );
    }

    private static function fully_disabled_categories( array $defs, array $disabled ) {
        if ( empty( $disabled ) || empty( $defs ) ) {
            return array();
        }

        $by_category = array();
        foreach ( $defs as $def ) {
            $category = (string) $def['category'];
            if ( '' === $category ) {
                continue;
            }
            if ( ! isset( $by_category[ $category ] ) ) {
                $by_category[ $category ] = array( 'total' => 0, 'off' => 0 );
            }
            $by_category[ $category ]['total']++;
            if ( in_array( $def['name'], $disabled, true ) ) {
                $by_category[ $category ]['off']++;
            }
        }

        $fully_off = array();
        foreach ( $by_category as $category => $counts ) {
            if ( $counts['total'] > 0 && $counts['total'] === $counts['off'] ) {
                $fully_off[] = $category;
            }
        }

        return $fully_off;
    }

    
    const GRANT_SCAN_LIMIT = 50;

    












    private static function grant_rows() {
        $wpdb = self::wpdb( 'get_results' );
        if ( null === $wpdb ) {
            return null; 
        }

        
        
        
        if ( ! class_exists( '\Easy_MCP_AI\OAuth\Scope_Map' ) ) {
            return null;
        }

        $consents = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';
        $clients  = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        
        
        $probe_limit = self::GRANT_SCAN_LIMIT + 1;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned tables; diagnostics must read live state.
        $rows = $wpdb->get_results(
            "SELECT c.client_name AS client_name, s.scope AS scope
             FROM `{$consents}` s LEFT JOIN `{$clients}` c ON c.client_id = s.client_id
             ORDER BY s.id
             LIMIT {$probe_limit}",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! is_array( $rows ) ) {
            return null;
        }

        $truncated = count( $rows ) > self::GRANT_SCAN_LIMIT;
        if ( $truncated ) {
            $rows = array_slice( $rows, 0, self::GRANT_SCAN_LIMIT );
        }

        $out = array();
        foreach ( $rows as $row ) {
            
            
            
            $tools = \Easy_MCP_AI\OAuth\Scope_Map::resolve_allowed_tools(
                (string) ( isset( $row['scope'] ) ? $row['scope'] : '' )
            );
            $out[] = array(
                'client'    => isset( $row['client_name'] ) ? (string) $row['client_name'] : '',
                'tools'     => is_array( $tools ) ? count( $tools ) : 0,
                'truncated' => $truncated,
            );
        }

        return $out;
    }

    






    private static function count_registered_abilities() {
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return null;
        }
        $abilities = \wp_get_abilities();
        return is_array( $abilities ) ? count( $abilities ) : null;
    }

    











    private static function enabled_abilities_missing_from_registry() {
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return array();
        }
        $enabled = \get_option( 'easy_mcp_ai_enabled_abilities', array() );
        if ( ! is_array( $enabled ) || ! $enabled ) {
            return array();
        }
        $registered = \wp_get_abilities();
        if ( ! is_array( $registered ) ) {
            
            
            return array();
        }
        $missing = array();
        foreach ( $enabled as $slug ) {
            $slug = (string) $slug;
            if ( '' !== $slug && ! isset( $registered[ $slug ] ) ) {
                $missing[] = $slug;
            }
        }
        return $missing;
    }

    private static function count_abilities( array $defs ) {
        $count = 0;
        foreach ( $defs as $def ) {
            if ( 0 === strpos( (string) $def['name'], 'wp_ability_' ) ) {
                $count++;
            }
        }
        return $count;
    }

    private static function wpdb( $method = 'get_var' ) {
        global $wpdb;
        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, $method ) ) {
            return null;
        }
        return $wpdb;
    }

    private static function describe_counts( array $tokens ) {
        $parts = array();
        foreach ( $tokens as $t ) {
            $parts[] = sprintf(
                '%s: %d/%d',
                isset( $t['name'] ) ? $t['name'] : __( 'unnamed', 'easy-mcp-ai' ),
                (int) ( isset( $t['visible'] ) ? $t['visible'] : 0 ),
                (int) ( isset( $t['total'] ) ? $t['total'] : 0 )
            );
        }

        $detail = implode( ', ', $parts );

        
        
        if ( ! empty( $tokens[0]['truncated'] ) ) {
            $detail .= ' ' . sprintf(
                /* translators: %d: number of tokens examined. */
                __( '(Only the first %d tokens were checked.)', 'easy-mcp-ai' ),
                self::TOKEN_SCAN_LIMIT
            );
        }

        return $detail;
    }
}
