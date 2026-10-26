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

        
        
        
        $ceiling      = ! empty( $scope_list ) ? $scope_list : array_values( array_filter( explode( ' ', $default_scope ) ) );
        $ceiling_all  = in_array( 'mcp', $ceiling, true );
        $level_scopes = array(
            'read' => $ceiling_all ? Scope_Map::get_read_scopes() : array_values( array_intersect( Scope_Map::get_read_scopes(), $ceiling ) ),
            'full' => $ceiling_all ? array( 'mcp' ) : array(),
        );
        $default_level  = $ceiling_all ? 'full' : 'custom';
        $checked_scopes = $ceiling;
        $tick_all       = $ceiling_all;

        
        
        
        
        $privileged_rows = array();
        foreach ( \Easy_MCP_AI\Privileged\Privileged_Groups::enabled_groups() as $group ) {
            $privileged_rows[] = array(
                'scope' => \Easy_MCP_AI\Privileged\Privileged_Groups::scope_for_group( $group ),
                'label' => \Easy_MCP_AI\Privileged\Privileged_Groups::group_label( $group ),
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
            $carried_scopes = array_values(
                array_diff(
                    array_intersect( $held, array_diff( Scope_Map::get_all_scopes(), array( 'mcp' ) ) ),
                    self::box_scopes( $categories, $privileged_rows, $ceiling, $ceiling_all )
                )
            );
        }

        $step_up_notice = array() === $new_scopes ? '' : self::step_up_notice( $client_name, $new_scopes, $categories, $privileged_rows );

        
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
            'carried_scopes'    => $carried_scopes,
        );

        
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract( $template_vars );

        ob_start();
        include __DIR__ . '/views/consent.php';
        return ob_get_clean();
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

    









    private static function step_up_notice( $client_name, array $new_scopes, array $categories, array $privileged_rows ) {
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
        return sprintf(
            /* translators: 1: application name, 2: comma-separated permission names */
            _n(
                '%1$s asked for one more permission to finish a task: %2$s. It is ticked below and marked New, and the access you already gave this app stays ticked.',
                '%1$s asked for more permissions to finish a task: %2$s. They are ticked below and marked New, and the access you already gave this app stays ticked.',
                count( $names ),
                'easy-mcp-ai'
            ),
            $client_name,
            implode( ', ', $names )
        );
    }
}
