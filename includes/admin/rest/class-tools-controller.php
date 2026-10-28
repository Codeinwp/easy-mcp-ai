<?php

namespace Easy_MCP_AI\Admin\Rest;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tools_Controller extends Admin_Rest_Controller {

    const EXTERNAL_LABELS = array(
        'gsc'       => 'Google Search Console',
        'ga'        => 'Google Analytics',
        'dfs'       => 'DataForSEO',
        'semrush'   => 'Semrush',
        'seranking' => 'SE Ranking',
        'ahrefs'    => 'Ahrefs (DR)',
    );

    const CREDENTIAL_PROVIDERS = array( 'ga', 'gsc', 'dfs', 'semrush', 'seranking' );

    private $groups;

    private $catalogue;

    public function __construct( callable $groups, $catalogue = null ) {
        $this->groups    = $groups;
        $this->catalogue = $catalogue;
    }

    public function register_routes() {
        $this->register_route( '/tools', \WP_REST_Server::READABLE, array( $this, 'get_tools' ) );
        $this->register_route( '/tools/enabled', self::UPDATABLE, array( $this, 'patch_enabled' ) );
    }

    public function get_tools( $request ) {
        return $this->ok( $this->payload() );
    }

    public function patch_enabled( $request ) {
        if ( ! $this->catalogue ) {
            return $this->fail( 'easy_mcp_ai_unavailable', \__( 'The tool registry is not available on this request.', 'easy-mcp-ai' ), 503 );
        }
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            return $this->fail( 'easy_mcp_ai_invalid_param', \__( 'Expected a JSON object.', 'easy-mcp-ai' ), 400, array( 'field' => '' ) );
        }
        $applied = $this->catalogue->apply( $body );
        if ( \is_wp_error( $applied ) ) {
            return $applied;
        }
        return $this->ok( $this->payload() );
    }

    private function payload() {
        $groups = (array) call_user_func( $this->groups );
        $data   = self::shape( $groups );
        if ( $this->catalogue ) {
            $data = array_merge( $data, $this->catalogue->sections( $groups ) );
        }
        return $data;
    }

    public static function shape( array $groups ) {
        $hints = isset( $groups['hints'] ) ? (array) $groups['hints'] : array();

        $core = array();
        foreach ( (array) ( $groups['core'] ?? array() ) as $label => $tools ) {
            if ( empty( $tools ) ) {
                continue;
            }
            $core[] = array( 'label' => (string) $label, 'tools' => self::names( $tools ) );
        }

        $plugins = array();
        foreach ( (array) ( $groups['plugins'] ?? array() ) as $label => $row ) {
            if ( 'not_installed' === ( $row['status'] ?? '' ) ) {
                continue;
            }
            $plugins[] = array(
                'label'  => (string) $label,
                'status' => 'active' === $row['status'] ? 'active' : 'no_tools',
                'tools'  => self::names( $row['tools'] ?? array() ),
            );
        }

        $external = array();
        foreach ( (array) ( $groups['external'] ?? array() ) as $label => $row ) {
            if ( 'not_configured' === ( $row['status'] ?? '' ) ) {
                continue;
            }
            $key        = (string) ( $row['key'] ?? '' );
            $external[] = array(
                'label'  => self::EXTERNAL_LABELS[ $key ] ?? (string) $label,
                'status' => 'active' === $row['status'] ? 'active' : 'no_tools',
                'tools'  => self::names( $row['tools'] ?? array() ),
            );
        }

        $abilities = array();
        foreach ( (array) ( $groups['abilities'] ?? array() ) as $label => $row ) {
            if ( empty( $row['has_abilities'] ) ) {
                continue;
            }
            $tools       = self::names( $row['tools'] ?? array() );
            $abilities[] = array(
                'label'  => (string) $label,
                'status' => empty( $tools ) ? 'not_enabled' : 'active',
                'tools'  => $tools,
            );
        }

        $not_connected = array();
        foreach ( self::CREDENTIAL_PROVIDERS as $key ) {
            if ( ! empty( $hints[ $key . '_missing' ] ) ) {
                $not_connected[] = self::EXTERNAL_LABELS[ $key ];
            }
        }

        return array(
            'total'     => (int) ( $groups['total'] ?? 0 ),
            'hints'     => array(
                'globalFilters' => ! empty( $hints['has_global_overrides'] ),
                'whitelist'     => ! empty( $hints['has_allowed_patterns'] ),
                'turnedOff'     => array(
                    'abilities' => ! empty( $hints['disabled_abilities_present'] ),
                    'plugins'   => ! empty( $hints['disabled_plugin_tools_present'] ),
                    'external'  => ! empty( $hints['disabled_ga_present'] )
                        || ! empty( $hints['disabled_gsc_present'] )
                        || ! empty( $hints['disabled_dfs_present'] )
                        || ! empty( $hints['disabled_semrush_present'] )
                        || ! empty( $hints['disabled_seranking_present'] ),
                ),
                'notConnected'  => $not_connected,
                'ahrefsOff'     => ! empty( $hints['ahrefs_disabled'] ),
            ),
            'core'      => $core,
            'plugins'   => $plugins,
            'external'  => $external,
            'abilities' => $abilities,
        );
    }

    private static function names( $tools ) {
        $names = array();
        foreach ( (array) $tools as $def ) {
            $name = is_array( $def ) ? ( $def['name'] ?? '' ) : $def;
            if ( '' !== (string) $name ) {
                $names[] = (string) $name;
            }
        }
        return $names;
    }
}
