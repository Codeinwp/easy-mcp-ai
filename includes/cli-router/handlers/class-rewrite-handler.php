<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





























class Rewrite_Handler extends Command_Handler {

    
    const ROW_CAP = 1000;

    const OVER_ROW_CAP = "wp_cli_run lists at most %1\$d rewrite rules in one 'rewrite list', and %2\$d match. Narrow it with --source=<source> or --match=<url>, or ask for --format=count.";

    const PLAIN = "wp_cli_run refuses Plain permalinks ('%s'): WordPress then has no rewrite rules, so the /wp-json/ routes this MCP connection uses stop resolving and the connection drops. Choose Plain under Settings › Permalinks in wp-admin if you mean it.";

    const NOTE_DEFERRED = 'WP-CLI flushes the rewrite rules in a new `wp rewrite flush` process after setting the structure, which cannot run inside this request. wp_cli_run deleted the stored rules instead, and WordPress rebuilds them from the new structure on the next request: the line above is the one that process prints.';

    const NOTE_HARD = 'Neither WP-CLI (without an apache_modules entry in a wp-cli.yml) nor wp_cli_run writes .htaccess: the rewrite rules were flushed in the database only.';

    
    const NOT_CAPTURED_HTACCESS = '.htaccess: when the permalink structure changes, the plugin that serves this MCP connection removes its own Plain-permalinks block from .htaccess if one is there; change history does not record files.';

    const HARD_WARNING      = 'Regenerating a .htaccess file requires special configuration. See usage docs.';
    const MULTISITE_WARNING = "WordPress can't generate .htaccess file for a multisite install.";
    const EMPTY_WARNING     = "Rewrite rules are empty, possibly because of a missing permalink_structure option. Use 'wp rewrite list' to verify, or 'wp rewrite structure' to update permalink_structure.";

    
    
    

    
    public function preflight_structure( Invocation $inv, Context $ctx ): void {
        $raw = (string) ( array_key_exists( 0, $inv->args ) ? $inv->args[0] : '' );
        if ( '' === self::requested_structure( $raw ) ) {
            $ctx->out->error( sprintf( self::PLAIN, $raw ) );
        }
    }

    
    
    

    
    public function flush( Invocation $inv, Context $ctx ): void {
        $hard = (bool) Read_Value::flag( $inv->assoc, 'hard' );

        if ( $hard ) {
            $this->hard_warnings( $ctx );
        }

        $before = serialize( $this->get_option( 'rewrite_rules' ) );
        
        $this->flush_rewrite_rules();
        $after = $this->get_option( 'rewrite_rules' );
        if ( serialize( $after ) !== $before ) {
            $ctx->mark_changed();
        }
        if ( $hard ) {
            $ctx->note( self::NOTE_HARD );
        }

        if ( ! $after ) {
            $ctx->out->warning( self::EMPTY_WARNING );
        } else {
            $ctx->out->success( 'Rewrite rules flushed.' );
        }
    }

    
    public function structure( Invocation $inv, Context $ctx ): void {
        $args       = $inv->args;
        $assoc_args = $inv->assoc;
        $raw        = (string) $args[0];
        if ( '' === self::requested_structure( $raw ) ) {
            $ctx->out->error( sprintf( self::PLAIN, $raw ) );
        }

        $before = $this->bases_state();
        $values = $this->structure_values( $raw, $assoc_args );

        $this->set_permalink_structure( $values['permalink_structure'] );

        
        if ( array_key_exists( 'category_base', $values ) ) {
            $this->set_category_base( $values['category_base'] );
        }

        if ( array_key_exists( 'tag_base', $values ) ) {
            $this->set_tag_base( $values['tag_base'] );
        }

        $ctx->out->success( 'Rewrite structure set.' );

        
        $hard = (bool) Read_Value::flag( $assoc_args, 'hard' );
        if ( $hard ) {
            $ctx->out->warning( self::HARD_WARNING );
            $ctx->note( self::NOTE_HARD );
        }

        $this->child_flush( $before, $hard, $ctx );
    }

    








    private function child_flush( array $before, bool $hard, Context $ctx ): void {
        $after = $this->bases_state();
        if ( $after !== $before ) {
            $ctx->mark_changed();
            if ( $after['permalink_structure'] !== $before['permalink_structure'] ) {
                $ctx->not_captured( self::NOT_CAPTURED_HTACCESS );
            }
            
            
            if ( $hard ) {
                $this->hard_warnings( $ctx );
            }
            $this->delete_option( 'rewrite_rules' );
            $ctx->note( self::NOTE_DEFERRED );
            $ctx->out->success( 'Rewrite rules flushed.' );
            return;
        }

        
        
        if ( $hard ) {
            $this->hard_warnings( $ctx );
        }
        $rules_before = serialize( $this->get_option( 'rewrite_rules' ) );
        $this->flush_rewrite_rules();
        $rules_after = $this->get_option( 'rewrite_rules' );
        if ( serialize( $rules_after ) !== $rules_before ) {
            $ctx->mark_changed();
        }
        if ( ! $rules_after ) {
            $ctx->out->warning( self::EMPTY_WARNING );
        } else {
            $ctx->out->success( 'Rewrite rules flushed.' );
        }
    }

    
    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;

        $rules = $this->get_option( 'rewrite_rules' );
        if ( ! $rules ) {
            $rules = array();
            $ctx->out->warning( 'No rewrite rules.' );
        }

        $defaults   = array(
            'source' => '',
            'match'  => '',
            'format' => 'table',
            'fields' => 'match,query,source',
        );
        $assoc_args = array_merge( $defaults, $assoc_args );

        if ( ! empty( $assoc_args['match'] ) ) {
            $assoc_args['match'] = self::match_path( (string) $assoc_args['match'] );
        }

        $rule_list = self::rule_list( (array) $rules, $assoc_args, $this->rules_by_source() );

        if ( count( $rule_list ) > self::ROW_CAP && 'count' !== $assoc_args['format'] ) {
            $ctx->out->error( sprintf( self::OVER_ROW_CAP, self::ROW_CAP, count( $rule_list ) ) );
        }

        
        $format_args = array(
            'format' => $assoc_args['format'],
            'fields' => explode( ',', (string) $assoc_args['fields'] ),
        );
        $formatter   = new Formatter( $ctx->out, $format_args );
        $formatter->display_items( $rule_list );
    }

    
    
    

    
    public function describe_flush( Invocation $inv ): array {
        $rules = $this->get_option( 'rewrite_rules' );
        $line  = sprintf(
            "Rewrite rules: %d stored → regenerated from the permalink structure '%s'",
            is_array( $rules ) ? count( $rules ) : 0,
            (string) $this->get_option( 'permalink_structure' )
        );
        if ( Read_Value::flag( $inv->assoc, 'hard' ) ) {
            $line .= '; .htaccess is not written';
        }
        return array( $line );
    }

    
    public function describe_structure( Invocation $inv ): array {
        $before = $this->bases_state();
        $values = $this->structure_values( (string) $inv->args[0], $inv->assoc );
        $labels = array(
            'permalink_structure' => 'Permalink structure',
            'category_base'       => 'Category base',
            'tag_base'            => 'Tag base',
        );
        $lines  = array();
        foreach ( $labels as $option => $label ) {
            if ( ! array_key_exists( $option, $values ) ) {
                continue;
            }
            
            
            $stored = (string) $this->sanitize_option( $option, $values[ $option ] );
            $line   = sprintf( "%s: '%s' → '%s'", $label, $before[ $option ], $stored );
            if ( $stored !== (string) $values[ $option ] ) {
                $line .= sprintf( " (what WordPress stores for '%s')", (string) $values[ $option ] );
            }
            $lines[] = $line;
        }
        $lines[] = 'Rewrite rules: rebuilt from the new structure' . ( Read_Value::flag( $inv->assoc, 'hard' ) ? '; .htaccess is not written' : '' );
        return $lines;
    }

    
    
    

    








    protected function seam( string $name, array $args, callable $real ) {
        return call_user_func_array( $real, $args );
    }

    
    protected function get_option( string $name ) {
        return $this->seam(
            'get_option',
            array( $name ),
            static function ( $name ) {
                return \get_option( $name );
            }
        );
    }

    





    protected function sanitize_option( string $name, $value ) {
        return $this->seam(
            'sanitize_option',
            array( $name, $value ),
            static function ( $name, $value ) {
                return \sanitize_option( $name, $value );
            }
        );
    }

    protected function delete_option( string $name ): bool {
        return (bool) $this->seam(
            'delete_option',
            array( $name ),
            static function ( $name ) {
                return \delete_option( $name );
            }
        );
    }

    
    protected function flush_rewrite_rules(): void {
        $this->seam(
            'flush_rewrite_rules',
            array( false ),
            static function ( $hard ) {
                \flush_rewrite_rules( $hard );
                return null;
            }
        );
    }

    protected function is_multisite(): bool {
        return (bool) $this->seam(
            'is_multisite',
            array(),
            static function () {
                return \is_multisite();
            }
        );
    }

    
    protected function blog_prefix_applies(): bool {
        return (bool) $this->seam(
            'blog_prefix_applies',
            array(),
            static function () {
                return \is_multisite() && ! \is_subdomain_install() && \is_main_site();
            }
        );
    }

    protected function set_permalink_structure( string $structure ): void {
        $this->seam(
            'set_permalink_structure',
            array( $structure ),
            static function ( $structure ) {
                global $wp_rewrite;
                $wp_rewrite->set_permalink_structure( $structure );
                return null;
            }
        );
    }

    
    protected function set_category_base( $base ): void {
        $this->seam(
            'set_category_base',
            array( $base ),
            static function ( $base ) {
                global $wp_rewrite;
                $wp_rewrite->set_category_base( $base );
                return null;
            }
        );
    }

    
    protected function set_tag_base( $base ): void {
        $this->seam(
            'set_tag_base',
            array( $base ),
            static function ( $base ) {
                global $wp_rewrite;
                $wp_rewrite->set_tag_base( $base );
                return null;
            }
        );
    }

    





    protected function rules_by_source(): array {
        return (array) $this->seam(
            'rewrite_rules_by_source',
            array(),
            static function () {
                global $wp_rewrite;
                $rewrite_rules_by_source             = array();
                $rewrite_rules_by_source['post']     = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->permalink_structure, EP_PERMALINK );
                $rewrite_rules_by_source['date']     = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_date_permastruct(), EP_DATE );
                $rewrite_rules_by_source['root']     = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->root . '/', EP_ROOT );
                $rewrite_rules_by_source['comments'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->root . $wp_rewrite->comments_base, EP_COMMENTS, true, true, true, false );
                $rewrite_rules_by_source['search']   = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_search_permastruct(), EP_SEARCH );
                $rewrite_rules_by_source['author']   = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_author_permastruct(), EP_AUTHORS );
                $rewrite_rules_by_source['page']     = $wp_rewrite->page_rewrite_rules();

                
                foreach ( $wp_rewrite->extra_permastructs as $permastructname => $permastruct ) {
                    if ( is_array( $permastruct ) ) {
                        $rewrite_rules_by_source[ $permastructname ] = $wp_rewrite->generate_rewrite_rules( $permastruct['struct'], $permastruct['ep_mask'], $permastruct['paged'], $permastruct['feed'], $permastruct['forcomments'], $permastruct['walk_dirs'], $permastruct['endpoints'] );
                    } else {
                        $rewrite_rules_by_source[ $permastructname ] = $wp_rewrite->generate_rewrite_rules( $permastruct, EP_NONE );
                    }
                }

                
                foreach ( $rewrite_rules_by_source as $source => $source_rules ) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- core's {source}_rewrite_rules filters, as WP-CLI applies them.
                    $rewrite_rules_by_source[ $source ] = \apply_filters( $source . '_rewrite_rules', $source_rules );
                    if ( 'post_tag' === $source ) {
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's filter.
                        $rewrite_rules_by_source[ $source ] = \apply_filters( 'post_tag_rewrite_rules', $source_rules );
                    }
                }
                return $rewrite_rules_by_source;
            }
        );
    }

    
    
    

    



    public static function requested_structure( string $raw ): string {
        $permalink_structure = ( 'default' === $raw ) ? '' : $raw;
        if ( ! empty( $permalink_structure ) ) {
            $permalink_structure = (string) preg_replace( '#/+#', '/', '/' . str_replace( '#', '', $permalink_structure ) );
        }
        return (string) $permalink_structure;
    }

    






    private function structure_values( string $raw, array $assoc_args ): array {
        

        $blog_prefix = '';
        $prefix      = $blog_prefix;
        if ( $this->blog_prefix_applies() ) {
            $blog_prefix = '/blog';
        }

        $permalink_structure = ( 'default' === $raw ) ? '' : $raw;

        if ( ! empty( $permalink_structure ) ) {
            $permalink_structure = preg_replace( '#/+#', '/', '/' . str_replace( '#', '', $permalink_structure ) );
            if ( $prefix && $blog_prefix ) {
                $permalink_structure = $prefix . preg_replace( '#^/?index\.php#', '', (string) $permalink_structure );
            } else {
                $permalink_structure = $blog_prefix . $permalink_structure;
            }
        }
        $values = array( 'permalink_structure' => (string) $permalink_structure );

        if ( isset( $assoc_args['category-base'] ) ) {

            $category_base = $assoc_args['category-base'];
            if ( ! empty( $category_base ) ) {
                $category_base = $blog_prefix . preg_replace( '#/+#', '/', '/' . str_replace( '#', '', (string) $category_base ) );
            }
            $values['category_base'] = $category_base;
        }

        if ( isset( $assoc_args['tag-base'] ) ) {

            $tag_base = $assoc_args['tag-base'];
            if ( ! empty( $tag_base ) ) {
                $tag_base = $blog_prefix . preg_replace( '#/+#', '/', '/' . str_replace( '#', '', (string) $tag_base ) );
            }
            $values['tag_base'] = $tag_base;
        }

        return $values;
    }

    
    private function hard_warnings( Context $ctx ): void {
        $ctx->out->warning( self::HARD_WARNING );
        if ( $this->is_multisite() ) {
            $ctx->out->warning( self::MULTISITE_WARNING );
        }
    }

    
    private static function match_path( string $match ): string {
        if ( 0 === stripos( $match, 'http://' ) || 0 === stripos( $match, 'https://' ) ) {
            $bits = self::parse_url( $match );
            return ( isset( $bits['path'] ) ? $bits['path'] : '' )
                . ( isset( $bits['query'] ) ? '?' . $bits['query'] : '' );
        }
        return $match;
    }

    









    private static function rule_list( array $rules, array $assoc_args, array $by_source ): array {
        $rule_list = array();
        foreach ( $rules as $match => $query ) {

            if ( ! empty( $assoc_args['match'] ) && ! preg_match( "!^$match!", trim( (string) $assoc_args['match'], '/' ) ) ) {
                continue;
            }

            $source = 'other';
            foreach ( $by_source as $rules_source => $source_rules ) {
                if ( is_array( $source_rules ) && array_key_exists( $match, $source_rules ) ) {
                    $source = $rules_source;
                }
            }

            if ( ! empty( $assoc_args['source'] ) && $source !== $assoc_args['source'] ) {
                continue;
            }

            $rule_list[] = compact( 'match', 'query', 'source' );
        }
        return $rule_list;
    }

    
    private function bases_state(): array {
        return array(
            'permalink_structure' => (string) $this->get_option( 'permalink_structure' ),
            'category_base'       => (string) $this->get_option( 'category_base' ),
            'tag_base'            => (string) $this->get_option( 'tag_base' ),
        );
    }

    





    private static function parse_url( string $url ): array {
        $parts = function_exists( 'wp_parse_url' ) ? \wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WP-CLI's own fallback.
        return is_array( $parts ) ? $parts : array();
    }
}
