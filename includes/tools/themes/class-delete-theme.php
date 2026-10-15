<?php
namespace Easy_MCP_AI\Tools\Themes;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;
use Easy_MCP_AI\Tools\Package_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/plugins/trait-package-guard.php';













class Delete_Theme extends Base_Tool {

    use Lifecycle_Guard;
    use Package_Guard;

    public function get_name() {
        return 'wp_delete_theme';
    }

    public function get_description() {
        return 'Deletes an installed theme\'s files from wp-content/themes, as the Delete button on Appearance → Themes does (its translations are removed too, and on multisite it is disabled for the network). Required: `stylesheet` (the theme folder name — the `stylesheet` field from wp_list_themes). Returns { stylesheet, name, version, deleted, changed }. Irreversible: the files are not backed up and change history cannot restore them. Refused when the theme is not installed, when it is the active theme or the parent of the active theme (switch themes first with wp_switch_theme), when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress cannot write the themes folder or wp-content directly, and on multisite unless you are a super admin with manage_network_themes (WordPress does not check whether other sites of the network use it). Off by default; an administrator switches it on.';
    }

    public function get_category() {
        return 'themes';
    }

    public function get_required_capability() {
        return 'delete_themes';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function is_disabled_by_default() {
        return true;
    }

    
    public function get_approval_policy( array $arguments ) {
        return 'always';
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'stylesheet' => array(
                    'type'        => 'string',
                    'description' => 'Theme folder name, e.g. "twentytwentythree" (the `stylesheet` field from wp_list_themes).',
                ),
            ),
            'required'   => array( 'stylesheet' ),
        );
    }

    public function describe( array $arguments ) {
        try {
            $stylesheet = $this->parse_stylesheet( $arguments );
            $theme      = wp_get_theme( $stylesheet );
        } catch ( \Throwable $e ) {
            return parent::describe( $arguments );
        }
        $name    = $theme->exists() ? (string) $theme->get( 'Name' ) : '';
        $version = $theme->exists() ? (string) $theme->get( 'Version' ) : '';
        return sprintf(
            /* translators: 1: theme name and version, 2: theme folder */
            __( 'Delete the theme %1$s (wp-content/themes/%2$s). The files cannot be restored.', 'easy-mcp-ai' ),
            '' !== $name ? '"' . $name . '"' . ( '' !== $version ? ' ' . $version : '' ) : '"' . $stylesheet . '"',
            $stylesheet
        );
    }

    public function execute( array $arguments ) {
        $stylesheet = $this->parse_stylesheet( $arguments );

        $this->refuse_when_file_mods_are_off( 'themes cannot be deleted' );
        
        if ( ! current_user_can( 'delete_themes' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to delete themes on this site.' );
        }
        $this->refuse_unless_network_file_admin( 'manage_network_themes', 'deleting themes' );

        
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() ) {
            throw new \RuntimeException( sprintf( 'Theme "%s" is not installed. Use wp_list_themes to see installed themes.', $stylesheet ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $name    = (string) $theme->get( 'Name' );
        $version = (string) $theme->get( 'Version' );

        $active = wp_get_theme();
        if ( $active->get_stylesheet() === $stylesheet ) {
            throw new \RuntimeException( sprintf( 'Theme "%s" is the active theme and cannot be deleted. Activate another theme first with wp_switch_theme.', $stylesheet ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $active->get_template() === $stylesheet ) {
            throw new \RuntimeException( sprintf( 'Theme "%s" is the parent of the active theme (%s) and cannot be deleted. Activate another theme first with wp_switch_theme.', $stylesheet, $active->get_stylesheet() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $this->refuse_unless_direct_filesystem( get_theme_root( $stylesheet ), 'wp-content/themes' );

        list( $deleted, $printed ) = $this->without_output(
            function () use ( $stylesheet ) {
                return $this->run_delete_theme( $stylesheet );
            }
        );
        if ( is_wp_error( $deleted ) ) {
            throw new \RuntimeException( sprintf( 'Theme "%s" was not deleted: %s', $stylesheet, $this->upgrader_error_text( $deleted ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( true !== $deleted ) {
            
            
            throw new \RuntimeException( sprintf( 'Theme "%s" could not be deleted.', $stylesheet ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $out = array(
            'stylesheet' => $stylesheet,
            'name'       => $name,
            'version'    => $version,
            'deleted'    => true,
            'changed'    => true,
        );
        if ( '' !== $printed ) {
            $out['output_discarded'] = true;
        }
        return $out;
    }

    








    private function parse_stylesheet( array $arguments ): string {
        $raw = isset( $arguments['stylesheet'] ) && is_string( $arguments['stylesheet'] ) ? trim( $arguments['stylesheet'] ) : '';
        if ( '' === $raw ) {
            throw new \InvalidArgumentException( 'Missing required parameters: stylesheet' );
        }
        $stylesheet = preg_replace( '/[^A-z0-9_\-]/', '', $raw );
        if ( $stylesheet !== $raw ) {
            throw new \InvalidArgumentException(
                sprintf( 'Invalid stylesheet "%s". Pass a theme folder name as returned by wp_list_themes.', substr( $raw, 0, 100 ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return $stylesheet;
    }

    






    protected function run_delete_theme( string $stylesheet ) {
        if ( ! function_exists( 'delete_theme' ) ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }
        return delete_theme( $stylesheet );
    }
}
