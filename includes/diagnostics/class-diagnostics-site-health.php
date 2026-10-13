<?php
























namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Diagnostics_Site_Health {

    
    const GROUP_LABELS = array(
        'a' => 'Authentication and transport',
        'b' => 'Sessions and caching',
        'c' => 'Database schema',
        'd' => 'Tool visibility',
        'e' => 'Plugin conflicts',
        'f' => 'Configuration',
        'g' => 'Server environment',
        'h' => 'Activity',
        'i' => 'Multisite',
    );

    public static function register() {
        \add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
        \add_filter( 'debug_information', array( __CLASS__, 'debug_information' ) );
    }

    



















    private static function ensure_report_exists_before_first_read() {
        if ( null === Diagnostics::last_run_at() ) {
            Diagnostics::maybe_run();
        }
    }

    


    public static function tests( $tests ) {
        self::ensure_report_exists_before_first_read();

        if ( ! is_array( $tests ) ) {
            $tests = array();
        }
        if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
            $tests['direct'] = array();
        }

        foreach ( self::group_results( Diagnostics::cached() ) as $prefix => $results ) {
            
            $has_reportable = false;
            foreach ( $results as $result ) {
                if ( $result->renders_in_site_health() ) {
                    $has_reportable = true;
                    break;
                }
            }
            if ( ! $has_reportable ) {
                continue;
            }

            $tests['direct'][ 'easy_mcp_ai_' . $prefix ] = array(
                'label' => self::group_label( $prefix ),
                'test'  => function () use ( $prefix ) {
                    return self::run_group_test( $prefix );
                },
            );
        }

        return $tests;
    }

    




    public static function run_group_test( $prefix ) {
        $groups = self::group_results( Diagnostics::cached() );
        if ( ! isset( $groups[ $prefix ] ) ) {
            return array();
        }

        $reportable = array_values( array_filter( $groups[ $prefix ], function ( $r ) {
            return $r->renders_in_site_health();
        } ) );
        if ( empty( $reportable ) ) {
            return array();
        }

        $problems = array();
        $status   = 'good';
        foreach ( $reportable as $result ) {
            if ( ! $result->is_problem() ) {
                continue; 
            }
            $problems[] = $result;
            if ( $result->renders_in_notice() ) {
                $status = 'critical';
            } elseif ( 'critical' !== $status ) {
                $status = 'recommended';
            }
        }

        $description = '';
        if ( empty( $problems ) ) {
            $description = '<p>' . \esc_html__( 'No problems found in this area.', 'easy-mcp-ai' ) . '</p>';
        } else {
            













            $description = '<ul>';
            foreach ( $problems as $problem ) {
                $description .= '<li>' . $problem->problem_badge_html()
                    . '<strong>' . \esc_html( $problem->label() ) . '</strong> — '
                    . \esc_html( $problem->detail() );
                if ( '' !== $problem->fix() ) {
                    $description .= ' <em>' . \esc_html( $problem->fix() ) . '</em>';
                }
                $description .= '</li>';
            }
            $description .= '</ul>';
        }

        return array(
            'label'       => self::group_label( $prefix ),
            'status'      => $status,
            'badge'       => array(
                'label' => __( 'Easy MCP AI', 'easy-mcp-ai' ),
                'color' => 'blue',
            ),
            'description' => $description,
            'actions'     => sprintf(
                '<a href="%s">%s</a>',
                \esc_url( \admin_url( 'admin.php?page=easy-mcp-ai' ) ),
                \esc_html__( 'Open Easy MCP AI diagnostics', 'easy-mcp-ai' )
            ),
            'test'        => 'easy_mcp_ai_' . $prefix,
        );
    }

    



































    const COPY_SAFE_IDS = array(
        'a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9',
        'b1', 'b2', 'b3',
        'c1', 'c2', 'c3', 'c4',
        'd3', 'd4', 'd5', 'd6', 'd7',
        'e1', 'e2', 'e3', 'e4', 'e5',
        'f1', 'f2', 'f3', 'f4', 'f6', 'f8', 'f10',
        'g1', 'g2', 'g3', 'g4',
        'h1', 'h3', 'h4',
        'i2',
    );

    
    public static function is_copy_safe( $id ) {
        return in_array( (string) $id, self::COPY_SAFE_IDS, true );
    }

    




    public static function debug_information( $info ) {
        self::ensure_report_exists_before_first_read();

        if ( ! is_array( $info ) ) {
            $info = array();
        }

        $fields = array();
        foreach ( Diagnostics::cached() as $result ) {
            $value = $result->status();
            if ( '' !== $result->detail() ) {
                $value .= ' — ' . $result->detail();
            }
            $fields[ $result->id() ] = array(
                'label'   => $result->label(),
                'value'   => $value,
                'private' => ! self::is_copy_safe( $result->id() ),
            );
        }

        $summary = Diagnostics::summary();

        
        
        
        
        
        
        
        
        
        $deferred   = isset( $summary['deferred'] ) ? (int) $summary['deferred'] : 0;
        $restricted = 0;
        foreach ( Diagnostics::cached() as $r ) {
            if ( Diagnostic_Result::STATUS_UNKNOWN !== $r->status() ) {
                continue;
            }
            $evidence = $r->evidence();
            if ( is_array( $evidence ) && ! empty( $evidence['not_permitted'] ) ) {
                $restricted++;
            }
        }
        $blocked = max( 0, (int) $summary['unknown'] - $deferred - $restricted );

        $description = sprintf(
            /* translators: 1: total checks, 2: passed, 3: warnings, 4: failures, 5: could not run on this host. */
            __( '%1$d checks: %2$d passed, %3$d warnings, %4$d failed, %5$d could not be checked on this host.', 'easy-mcp-ai' ),
            $summary['total'],
            $summary['pass'],
            $summary['warn'],
            $summary['fail'],
            $blocked
        );
        if ( $deferred > 0 ) {
            $description .= ' ' . sprintf(
                /* translators: %d: number of slower checks not run on this page load. */
                __( '(%d slower checks not run — press Re-run checks.)', 'easy-mcp-ai' ),
                $deferred
            );
        }
        if ( $restricted > 0 ) {
            $description .= ' ' . sprintf(
                /* translators: %d: number of checks hidden because the viewer is not a network administrator. */
                __( '(%d hidden — only a network administrator can see them.)', 'easy-mcp-ai' ),
                $restricted
            );
        }

        $info['easy-mcp-ai'] = array(
            'label'       => __( 'Easy MCP AI diagnostics', 'easy-mcp-ai' ),
            'description' => $description,
            'fields'      => $fields,
        );

        return $info;
    }

    





    public static function group_results( array $results ) {
        $groups = array();

        foreach ( $results as $result ) {
            $prefix = strtolower( substr( $result->id(), 0, 1 ) );
            if ( '' === $prefix ) {
                continue;
            }
            if ( ! isset( $groups[ $prefix ] ) ) {
                $groups[ $prefix ] = array();
            }
            $groups[ $prefix ][] = $result;
        }

        ksort( $groups );

        return $groups;
    }

    private static function group_label( $prefix ) {
        $name = isset( self::GROUP_LABELS[ $prefix ] ) ? self::GROUP_LABELS[ $prefix ] : $prefix;

        return sprintf(
            /* translators: %s: diagnostics group name, e.g. "Database schema". */
            __( 'Easy MCP AI: %s', 'easy-mcp-ai' ),
            $name
        );
    }
}
