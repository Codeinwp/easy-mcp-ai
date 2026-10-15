<?php
namespace Easy_MCP_AI\Tools\Themes;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












class List_Themes extends Base_Tool {

    use Lifecycle_Guard;

    
    const UPDATE_FIELDS = 'update, update_version, update_unavailable_reason, auto_update';

    
    const VERSION_HIGHER = 'version higher than expected';

    public function get_name() {
        return 'wp_list_themes';
    }

    public function get_description() {
        return 'Lists all installed WordPress themes. Optional: `skip_update_check` (boolean, default true: read WordPress\'s stored update data and contact nobody; false deletes it and asks wordpress.org again first, as `wp theme list` does — only for a user with update_themes). Returns { themes: [{ stylesheet (theme folder name / identifier), name, version, status ("active"/"inactive"), author, update_available (boolean), new_version (only when an update is available), update ("none" | "available" | "unavailable" | "version higher than expected"), update_version, update_unavailable_reason, auto_update (boolean: in WordPress\'s auto-update list), requires (WordPress version), requires_php, active_parent (true for the parent of the active child theme, whose status stays "inactive"), enabled (multisite only: "network,site" | "network" | "site" | "no"; null on a single site) }], total, warnings }. The active theme has `status="active"`. update_available and new_version come from WordPress\'s stored update data (the same data Appearance → Themes shows). The fields update, update_version, update_unavailable_reason and auto_update are null for a user without the update_themes capability (a warning names the cause). requires and requires_php are those of the offered update when there is one, otherwise the theme\'s own header. Use `wp_get_active_theme` if you only need the current theme\'s details, `wp_switch_theme` to activate one and `wp_update_theme` to update one. Requires administrator access. ' . self::lifecycle_hint();
    }

    


    public static function lifecycle_hint(): string {
        return \Easy_MCP_AI\Config::brand( 'Installing and deleting themes have their own tools, which are off by default: when none is in your tools, tell the user an administrator can switch them on in wp-admin under Easy MCP AI › Settings › Advanced › Disabled tools, or allow them for this connection.' );
    }

    public function get_category() {
        return 'themes';
    }

    public function get_required_capability() {
        return 'switch_themes';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'skip_update_check' => array(
                    'type'        => 'boolean',
                    'description' => 'Default true: read the update data WordPress already stored, with no outbound request. false: delete it and ask wordpress.org again before listing, as `wp theme list` does without --skip-update-check (needs update_themes).',
                    'default'     => true,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        
        $skip_check  = isset( $arguments['skip_update_check'] ) ? rest_sanitize_boolean( $arguments['skip_update_check'] ) : true;
        $warnings    = array();
        $see_updates = $this->prepare_update_data( $skip_check, $warnings );

        $data    = $this->rest_request( 'GET', '/wp/v2/themes' );
        $context = $this->list_context( $see_updates, (array) $data );

        $themes = array();
        foreach ( $data as $theme ) {
            $themes[] = $this->with_cli_fields( $this->theme_item( $theme, $context['response'] ), $theme, $context );
        }

        return array(
            'themes'   => $themes,
            'total'    => count( $themes ),
            'warnings' => $warnings,
        );
    }

    









    private function prepare_update_data( bool $skip_check, array &$warnings ): bool {
        $see_updates = current_user_can( 'update_themes' );
        if ( ! $see_updates ) {
            $warnings[] = $this->hidden_updates_warning( $skip_check );
        } elseif ( ! $skip_check ) {
            delete_site_transient( 'update_themes' );
            wp_update_themes();
        }
        return $see_updates;
    }

    









    private function list_context( bool $see_updates, array $data ): array {
        $transient = get_site_transient( 'update_themes' );
        return array(
            'see_updates'   => $see_updates,
            'response'      => $this->update_response( $transient ),
            'no_update'     => ( is_object( $transient ) && isset( $transient->no_update ) && is_array( $transient->no_update ) ) ? $transient->no_update : array(),
            'auto_updates'  => $this->string_list( $see_updates ? get_site_option( 'auto_update_themes' ) : array() ),
            'parent'        => $this->active_parent_stylesheet( $data ),
            'multisite'     => is_multisite(),
            'site_allowed'  => is_multisite() ? $this->allowed_map( get_option( 'allowedthemes' ) ) : array(),
            'network_allow' => is_multisite() ? $this->allowed_map( get_site_option( 'allowedthemes' ) ) : array(),
        );
    }

    







    private function theme_item( $theme, array $response ): array {
        $stylesheet = isset( $theme['stylesheet'] ) ? $theme['stylesheet'] : '';
        $item       = array(
            'stylesheet'       => $stylesheet,
            'name'             => isset( $theme['name']['rendered'] ) ? wp_strip_all_tags( $theme['name']['rendered'] ) : '',
            'version'          => isset( $theme['version'] ) ? $theme['version'] : '',
            'status'           => isset( $theme['status'] ) ? $theme['status'] : '',
            'author'           => isset( $theme['author']['rendered'] ) ? wp_strip_all_tags( $theme['author']['rendered'] ) : '',
            'update_available' => false,
        );
        if ( '' !== $stylesheet && isset( $response[ $stylesheet ] ) ) {
            $item = $this->with_update( $item, $response[ $stylesheet ] );
        }
        return $item;
    }

    






    private function with_update( array $item, $entry ): array {
        $new_version              = (string) $this->update_field( $entry, 'new_version' );
        $item['update_available'] = true;
        if ( '' !== $new_version ) {
            $item['new_version'] = $new_version;
        }
        return $item;
    }

    







    private function with_cli_fields( array $item, $theme, array $context ): array {
        $stylesheet = (string) $item['stylesheet'];
        $header_wp  = self::string_header( $theme, 'requires_wp' );
        $header_php = self::string_header( $theme, 'requires_php' );

        if ( $context['see_updates'] ) {
            $item       += $this->update_fields(
                (string) $item['version'],
                self::entry_for( $context['response'], $stylesheet ),
                self::entry_for( $context['no_update'], $stylesheet ),
                $header_wp,
                $header_php
            );
            $auto_update = in_array( $stylesheet, $context['auto_updates'], true );
        } else {
            $item       += array(
                'update'                    => null,
                'update_version'            => null,
                'update_unavailable_reason' => null,
                'requires'                  => $header_wp,
                'requires_php'              => $header_php,
            );
            $auto_update = null;
        }
        return $this->in_cli_order( $item, $auto_update, $stylesheet, $context );
    }

    






    private static function string_header( $theme, string $field ): string {
        return isset( $theme[ $field ] ) && is_string( $theme[ $field ] ) ? $theme[ $field ] : '';
    }

    






    private static function entry_for( array $map, string $stylesheet ) {
        return ( '' !== $stylesheet && isset( $map[ $stylesheet ] ) ) ? $map[ $stylesheet ] : null;
    }

    










    private function in_cli_order( array $item, ?bool $auto_update, string $stylesheet, array $context ): array {
        $ordered = array();
        foreach ( array( 'update', 'update_version', 'update_unavailable_reason' ) as $key ) {
            $ordered[ $key ] = $item[ $key ];
        }
        $ordered['auto_update']   = $auto_update;
        $ordered['requires']      = $item['requires'];
        $ordered['requires_php']  = $item['requires_php'];
        $ordered['active_parent'] = '' !== $stylesheet && $stylesheet === $context['parent'];
        $ordered['enabled']       = $context['multisite'] ? $this->enabled_value( $stylesheet, $context['site_allowed'], $context['network_allow'] ) : null;

        foreach ( array_keys( $ordered ) as $key ) {
            unset( $item[ $key ] );
        }
        return $item + $ordered;
    }

    

















    private function update_fields( string $installed, $offer, $no_update, string $header_wp, string $header_php ): array {
        $requires     = null !== $offer ? $this->update_field( $offer, 'requires' ) : null;
        $requires_php = null !== $offer ? $this->update_field( $offer, 'requires_php' ) : null;

        list( $update, $reason ) = self::update_status( null !== $offer, $requires, $requires_php );

        if ( null === $offer && null !== $no_update && version_compare( $installed, (string) $this->update_field( $no_update, 'new_version' ), '>' ) ) {
            $update = self::VERSION_HIGHER;
        }

        return array(
            'update'                    => $update,
            'update_version'            => null !== $offer ? (string) $this->update_field( $offer, 'new_version' ) : '',
            'update_unavailable_reason' => $reason,
            'requires'                  => null !== $requires ? (string) $requires : $header_wp,
            'requires_php'              => null !== $requires_php ? (string) $requires_php : $header_php,
        );
    }

    








    private static function update_status( bool $offered, $requires, $requires_php ): array {
        $wp_version = (string) get_bloginfo( 'version' );
        if ( ! empty( $requires_php ) && ! version_compare( PHP_VERSION, (string) $requires_php, '>=' ) ) {
            return array( 'unavailable', sprintf( 'This update requires PHP version %s, but the version installed is %s.', (string) $requires_php, PHP_VERSION ) );
        }
        if ( ! empty( $requires ) && ! version_compare( $wp_version, (string) $requires, '>=' ) ) {
            return array( 'unavailable', sprintf( 'This update requires WordPress version %s, but the version installed is %s.', (string) $requires, $wp_version ) );
        }
        return array( $offered ? 'available' : 'none', '' );
    }

    







    private function active_parent_stylesheet( array $data ): string {
        foreach ( $data as $theme ) {
            if ( ! is_array( $theme ) || ! isset( $theme['status'], $theme['stylesheet'], $theme['template'] ) || 'active' !== $theme['status'] ) {
                continue;
            }
            return (string) $theme['template'] !== (string) $theme['stylesheet'] ? (string) $theme['template'] : '';
        }
        return '';
    }

    







    private function enabled_value( string $stylesheet, array $site_allowed, array $network_allow ): string {
        $site    = ! empty( $site_allowed[ $stylesheet ] );
        $network = ! empty( $network_allow[ $stylesheet ] );
        if ( $site && $network ) {
            return 'network,site';
        }
        if ( $network ) {
            return 'network';
        }
        return $site ? 'site' : 'no';
    }

    



    private function allowed_map( $value ): array {
        return is_array( $value ) ? $value : array();
    }

    



    private function string_list( $value ): array {
        return is_array( $value ) ? array_values( $value ) : array();
    }

    







    private function hidden_updates_warning( bool $skip_check ): string {
        if ( ! wp_is_file_mod_allowed( 'capability_update_core' ) ) {
            $cause = 'file modifications are disabled on this site (DISALLOW_FILE_MODS, or a file_mod_allowed filter), so WordPress withholds update data from every user';
        } elseif ( is_multisite() && ! is_super_admin() ) {
            $cause = 'on a multisite network WordPress shows update data only to super admins (the update_themes capability)';
        } else {
            $cause = 'they need the update_themes capability, which your account lacks';
        }
        return sprintf( 'The fields %s are empty (null): %s.%s', self::UPDATE_FIELDS, $cause, $skip_check ? '' : ' No update check was made.' );
    }
}
