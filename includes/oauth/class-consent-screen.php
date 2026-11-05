<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Consent_Screen {

    public static function render( $client, $user, $scope, array $request_params, $script_nonce = '', array $options = array() ) {

        $client_name       = isset( $client->client_name ) ? $client->client_name : __( 'Unknown Application', 'easy-mcp-ai' );
        $client_id         = isset( $client->client_id ) ? $client->client_id : '';
        $client_id_prefix  = substr( $client_id, 0, 8 );
        $redirect_host     = isset( $request_params['redirect_uri'] ) ? wp_parse_url( $request_params['redirect_uri'], PHP_URL_HOST ) : '';
        $user_display_name = isset( $user->display_name ) ? (string) $user->display_name : '';
        $user_roles        = implode( ', ', isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : array() );
        $categories        = Scope_Map::get_categories();
        $default_scope     = Scope_Map::get_default_scope();
        $scope_list        = array_filter( array_map( 'trim', explode( ' ', $scope ) ) );
        $is_mcp_wildcard   = in_array( 'mcp', $scope_list, true );

        $default_hidden = array();
        foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'resource', 'scope', 'scope_sig' ) as $field ) {
            $default_hidden[ $field ] = isset( $request_params[ $field ] ) ? (string) $request_params[ $field ] : '';
        }

        $form_action   = isset( $options['form_action'] ) ? (string) $options['form_action'] : home_url( '?easy_mcp_ai_oauth=authorize' );
        $hidden_fields = isset( $options['hidden_fields'] ) && is_array( $options['hidden_fields'] ) ? $options['hidden_fields'] : $default_hidden;
        $nonce_action  = isset( $options['nonce_action'] ) ? (string) $options['nonce_action'] : 'easy_mcp_ai_oauth_consent_' . $client_id;
        $context_label = isset( $options['context_label'] ) ? (string) $options['context_label'] : __( 'Redirects to:', 'easy-mcp-ai' );
        $context_value = isset( $options['context_value'] ) ? (string) $options['context_value'] : (string) $redirect_host;
        $notice        = isset( $options['notice'] ) ? (string) $options['notice'] : '';
        $client_label  = isset( $options['client_label'] ) ? (string) $options['client_label'] : '';

        $open_request = array() === $scope_list;
        $ceiling      = $open_request ? array( 'mcp' ) : $scope_list;
        $ceiling_all  = in_array( 'mcp', $ceiling, true );
        $role_limit   = isset( $options['role_limit'] ) && is_array( $options['role_limit'] ) ? $options['role_limit'] : array();
        $role_choices = self::role_choices( $user, $role_limit );
        $level_scopes = array(
            'read' => $ceiling_all ? Scope_Map::get_read_scopes() : array_values( array_intersect( Scope_Map::get_read_scopes(), $ceiling ) ),
            'full' => $ceiling_all ? array( 'mcp' ) : array(),
            'role' => array() === $role_choices ? array() : ( $ceiling_all ? array( 'mcp' ) : $ceiling ),
        );
        $default_level  = $ceiling_all && ! $open_request ? 'full' : 'custom';
        $checked_scopes = $open_request ? array_values( array_filter( explode( ' ', $default_scope ) ) ) : $ceiling;
        $tick_all       = $ceiling_all && ! $open_request;

        $privileged_rows = array();
        foreach ( \Easy_MCP_AI\Privileged\Privileged_Groups::enabled_groups() as $group ) {
            $privileged_rows[] = array(
                'scope'     => \Easy_MCP_AI\Privileged\Privileged_Groups::scope_for_group( $group ),
                'label'     => \Easy_MCP_AI\Privileged\Privileged_Groups::group_label( $group ),
                'read_only' => \Easy_MCP_AI\Privileged\Privileged_Groups::is_read_only( $group ),
            );
        }

        $new_scopes = array();
        if ( isset( $options['step_up']['new'] ) && is_array( $options['step_up']['new'] ) ) {
            $new_scopes = array_values( array_intersect( array_map( 'strval', $options['step_up']['new'] ), $ceiling_all ? Scope_Map::get_all_scopes() : $ceiling ) );
        }
        $carried_scopes = array();
        if ( array() !== $new_scopes ) {
            $held           = isset( $options['step_up']['held'] ) && is_array( $options['step_up']['held'] ) ? array_map( 'strval', $options['step_up']['held'] ) : array();
            $default_level  = 'custom';
            $tick_all       = false;
            $checked_scopes = array_values( array_unique( array_merge( $held, $new_scopes ) ) );
            $carried_scopes = self::carried_scopes( $held, $categories, $privileged_rows, $ceiling, $ceiling_all );
        } elseif ( isset( $options['held'] ) && is_array( $options['held'] ) ) {
            $carried_scopes = self::carried_scopes( array_map( 'strval', $options['held'] ), $categories, $privileged_rows, $ceiling, $ceiling_all );
        }
        $carried_rows = self::carried_rows( $carried_scopes, $categories );

        $role_selected = '';
        if ( array() !== $role_choices ) {
            $role_selected = (string) $role_choices[0]['value'];
            if ( array() !== $role_limit ) {
                $default_level  = 'role';
                $role_selected  = (string) reset( $role_limit );
                $tick_all       = $ceiling_all;
                $checked_scopes = $ceiling_all ? $checked_scopes : $ceiling;
            }
        }

        $registry = Scope_Availability::registry();

        $step_up_notice = array() === $new_scopes ? '' : self::step_up_notice( $client_name, $new_scopes, $categories, $privileged_rows, 'role' === $default_level );

        $template_vars = array(
            'client_name'       => $client_name,
            'client_id'         => $client_id,
            'client_id_prefix'  => $client_id_prefix,
            'client_label'      => $client_label,
            'redirect_host'     => $redirect_host,
            'user_display_name' => $user_display_name,
            'user_roles'        => $user_roles,
            'categories'        => $categories,
            'scope_list'        => $scope_list,
            'is_mcp_wildcard'   => $is_mcp_wildcard,
            'default_scope'     => $default_scope,
            'request_params'    => $request_params,
            'script_nonce'      => $script_nonce,
            'form_action'       => $form_action,
            'hidden_fields'     => $hidden_fields,
            'nonce_action'      => $nonce_action,
            'context_label'     => $context_label,
            'context_value'     => $context_value,
            'notice'            => $notice,
            'ceiling'           => $ceiling,
            'ceiling_all'       => $ceiling_all,
            'level_scopes'      => $level_scopes,
            'default_level'     => $default_level,
            'checked_scopes'    => $checked_scopes,
            'privileged_rows'   => $privileged_rows,
            'tick_all'          => $tick_all,
            'new_scopes'        => $new_scopes,
            'step_up_notice'    => $step_up_notice,
            'carried_rows'      => $carried_rows,
            'availability'      => Scope_Availability::for_current_user( $categories, $registry ),
            'role_choices'      => $role_choices,
            'role_selected'     => $role_selected,
            'role_refused'      => Scope_Availability::refused_for_roles( $categories, array_column( $role_choices, 'value' ), $registry ),
            'switched_off'      => self::switched_off( $registry ),
            'higher_role'       => Scope_Availability::refused_titles( $categories, $registry ),
        );

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract( $template_vars );

        ob_start();
        include __DIR__ . '/views/consent.php';
        return ob_get_clean();
    }

    private static function role_choices( $user, array $current ): array {
        if ( ! $user instanceof \WP_User || ! Role_Limit::column_ready() ) {
            return array();
        }
        $offered = Role_Limit::offered( $user );
        $labels  = array_column( \Easy_MCP_AI\Auth\Connect_Policy::role_choices(), 'label', 'value' );
        $rows    = array();
        foreach ( $labels as $value => $label ) {
            if ( in_array( $value, $offered, true ) && ( in_array( $value, $current, true ) || ! self::is_whole_account( $user, (string) $value ) ) ) {
                $rows[] = array( 'value' => (string) $value, 'label' => $label );
            }
        }
        foreach ( array_diff( $current, $offered ) as $value ) {
            $rows[] = array(
                'value' => (string) $value,
                /* translators: %s: a role name, or its slug when the role no longer exists. */
                'label' => sprintf( __( '%s (no longer available to you)', 'easy-mcp-ai' ), isset( $labels[ $value ] ) ? $labels[ $value ] : $value ),
            );
        }
        return $rows;
    }

    private static function is_whole_account( \WP_User $user, string $role ): bool {
        if ( array( $role ) !== array_values( (array) $user->roles ) ) {
            return false;
        }
        if ( function_exists( 'is_multisite' ) && \is_multisite() && \is_super_admin( $user->ID ) ) {
            return false;
        }
        foreach ( isset( $user->caps ) && is_array( $user->caps ) ? $user->caps : array() as $cap => $grant ) {
            if ( $grant && $role !== $cap ) {
                return false;
            }
        }
        return true;
    }

    private static function switched_off( $registry ): array {
        try {
            $entries = array_merge(
                \Easy_MCP_AI\Tools\Switched_Off_Inventory::integration_tools(),
                \Easy_MCP_AI\Tools\Switched_Off_Inventory::tools( $registry )
            );
        } catch ( \Throwable $e ) {
            return array();
        }
        $titles = array_map( 'strval', array_column( $entries, 'label' ) );
        sort( $titles, SORT_NATURAL | SORT_FLAG_CASE );
        return $titles;
    }

    private static function carried_scopes( array $held, array $categories, array $privileged_rows, array $ceiling, $ceiling_all ) {
        return array_values(
            array_diff(
                array_intersect( $held, array_diff( Scope_Map::get_all_scopes(), array( 'mcp' ) ) ),
                self::box_scopes( $categories, $privileged_rows, $ceiling, $ceiling_all )
            )
        );
    }

    private static function carried_rows( array $carried, array $categories ) {
        $rows = array();
        foreach ( $categories as $category ) {
            $row = array(
                'label' => isset( $category['label'] ) ? (string) $category['label'] : '',
                'flag'  => __( 'Not active now', 'easy-mcp-ai' ),
                'read'          => '',
                'write'         => '',
                'read_included' => false,
            );
            foreach ( array( 'read', 'write' ) as $kind ) {
                $scope = isset( $category[ $kind . '_scope' ] ) ? (string) $category[ $kind . '_scope' ] : '';
                if ( '' !== $scope && in_array( $scope, $carried, true ) ) {
                    $row[ $kind ] = $scope;
                    $carried      = array_values( array_diff( $carried, array( $scope ) ) );
                }
            }
            if ( '' !== $row['read'] || '' !== $row['write'] ) {
                $rows[] = $row;
            }
        }
        foreach ( $carried as $scope ) {
            $ability = 0 === strpos( $scope, 'mcp:abilities' );
            $rows[]  = array(
                'label'         => 'mcp:abilities' === $scope ? __( 'All abilities', 'easy-mcp-ai' ) : $scope,
                'flag'          => '',
                'read'          => $ability ? '' : $scope,
                'write'         => $ability ? $scope : '',
                'read_included' => $ability,
            );
        }
        return $rows;
    }

    private static function box_scopes( array $categories, array $privileged_rows, array $ceiling, $ceiling_all ) {
        $boxes = array();
        foreach ( $categories as $category ) {
            if ( ! empty( $category['plugin_required'] ) && ! Scope_Map::is_plugin_category_active( (string) $category['slug'] ) ) {
                continue;
            }
            foreach ( array( 'read_scope', 'write_scope' ) as $key ) {
                if ( ! empty( $category[ $key ] ) ) {
                    $boxes[] = (string) $category[ $key ];
                }
            }
        }
        foreach ( $privileged_rows as $row ) {
            if ( $ceiling_all || in_array( $row['scope'], $ceiling, true ) ) {
                $boxes[] = $row['scope'];
            }
        }
        return $boxes;
    }

    private static function step_up_notice( $client_name, array $new_scopes, array $categories, array $privileged_rows, bool $role_limited ) {
        $labels = array();
        foreach ( $categories as $category ) {
            $label = isset( $category['label'] ) ? (string) $category['label'] : '';
            if ( ! empty( $category['is_ability'] ) ) {
                $labels[ (string) $category['read_scope'] ] = $label;
                continue;
            }
            if ( ! empty( $category['read_scope'] ) ) {
                /* translators: %s: permission category, e.g. "Posts & Pages" */
                $labels[ (string) $category['read_scope'] ] = sprintf( __( '%s (read)', 'easy-mcp-ai' ), $label );
            }
            if ( ! empty( $category['write_scope'] ) ) {
                /* translators: %s: permission category, e.g. "Posts & Pages" */
                $labels[ (string) $category['write_scope'] ] = sprintf( __( '%s (write)', 'easy-mcp-ai' ), $label );
            }
        }
        foreach ( $privileged_rows as $row ) {
            $labels[ $row['scope'] ] = $row['label'];
        }
        $names = array();
        foreach ( $new_scopes as $scope ) {
            $names[] = isset( $labels[ $scope ] ) ? $labels[ $scope ] : $scope;
        }
        if ( $role_limited ) {
            /* translators: 1: application name, 2: comma-separated permission names */
            $notice = _n(
                '%1$s asked for one more permission to finish a task: %2$s. It is marked New below. The access you already gave this app is kept, limited to the role chosen below.',
                '%1$s asked for more permissions to finish a task: %2$s. They are marked New below. The access you already gave this app is kept, limited to the role chosen below.',
                count( $names ),
                'easy-mcp-ai'
            );
        } else {
            /* translators: 1: application name, 2: comma-separated permission names */
            $notice = _n(
                '%1$s asked for one more permission to finish a task: %2$s. It is ticked below and marked New, and the access you already gave this app stays ticked.',
                '%1$s asked for more permissions to finish a task: %2$s. They are ticked below and marked New, and the access you already gave this app stays ticked.',
                count( $names ),
                'easy-mcp-ai'
            );
        }
        return sprintf( $notice, $client_name, implode( ', ', $names ) );
    }
}
