<?php









namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class App_Page {

    const SLUG_PREFIX = 'easy-mcp-ai';
    const HANDLE      = 'easy-mcp-ai-app';
    const ROOT_ID     = 'easy-mcp-ai-app';
    const MENU_POSITION = 80;

    
    private $hooks = array();

    
    private $setup_hook = '';

    
    const BODY_CLASS = 'easy-mcp-ai-app';

    
    const SETUP_BODY_CLASS = 'easy-mcp-ai-setup';

    public function __construct() {
        require_once __DIR__ . '/class-setup-state.php';
        
        \add_action( 'admin_menu', array( $this, 'register_menus' ), 9 );
        \add_action( 'admin_head', array( $this, 'hide_menu_entries' ) );
        \add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
        \add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        \add_filter( 'admin_body_class', array( $this, 'body_class' ) );
        \add_filter( 'submenu_file', array( $this, 'current_submenu' ) );
        \add_filter( 'admin_footer_text', array( $this, 'blank_footer' ), 20 );
        \add_filter( 'update_footer', array( $this, 'blank_footer' ), 20 );
        \add_filter( 'admin_title', array( $this, 'admin_title' ), 10, 2 );
    }

    






    public function blank_footer( $text ) {
        $hook = isset( $GLOBALS['hook_suffix'] ) ? $GLOBALS['hook_suffix'] : '';
        return $this->is_app_hook( $hook ) ? '' : $text;
    }

    







    public function admin_title( $admin_title, $title ) {
        $hook = isset( $GLOBALS['hook_suffix'] ) ? $GLOBALS['hook_suffix'] : '';
        if ( ! \Easy_MCP_AI\Config::is_white_labelled() || ! $this->is_app_hook( $hook ) ) {
            return $admin_title;
        }
        /* translators: Admin screen title. 1: Admin screen name, 2: Network or site name. */
        $format = \__( '%1$s &lsaquo; %2$s &#8212; WordPress' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's own string, so the title is translated as on every other screen.
        return sprintf( $format, $title, (string) \Easy_MCP_AI\Config::get( 'brand_name' ) );
    }

    



    private function is_app_hook( $hook ) {
        return in_array( (string) $hook, $this->hooks, true );
    }

    





    public function body_class( $classes ) {
        $hook = isset( $GLOBALS['hook_suffix'] ) ? $GLOBALS['hook_suffix'] : '';
        if ( ! $this->is_app_hook( $hook ) ) {
            return $classes;
        }
        $classes = trim( (string) $classes . ' ' . self::BODY_CLASS );
        if ( '' !== $this->setup_hook && $this->setup_hook === $hook ) {
            $classes .= ' ' . self::SETUP_BODY_CLASS;
        }
        return $classes;
    }

    



    public static function url_for( $key ) {
        $pages = self::pages();
        return \admin_url( 'admin.php?page=' . self::SLUG_PREFIX . ( isset( $pages[ $key ] ) ? $pages[ $key ][0] : '' ) );
    }

    







    public static function canvas_css() {
        $b = 'body.' . self::BODY_CLASS;
        
        
        
        
        
        return $b . ',' . $b . ' #wpwrap,' . $b . ' #wpcontent,' . $b . ' #wpfooter{background:#F3F4F6;}'
            . $b . ' #wpfooter{color:#5B6270;padding:0;line-height:0;}'
            . $b . ' #wpfooter a{color:#3F4654;}'
            . $b . ' #wpbody-content{padding-bottom:0;}'
            . $b . ' #wpcontent{padding-left:0;}'
            . self::notice_css()
            . self::skeleton_css()
            . self::setup_chrome_css()
            . self::element_reset_css();
    }

    





    public static function notice_css() {
        return 'body.' . self::BODY_CLASS . ' #wpbody-content > :is(div.notice,div.error,div.updated,div.update-nag){display:none;}';
    }

    







    public static function setup_chrome_css() {
        $b = 'body.' . self::SETUP_BODY_CLASS;
        return $b . ' #adminmenumain,' . $b . ' #wpadminbar,' . $b . ' #wpfooter,' . $b . ' #screen-meta-links{display:none;}'
            . $b . ' #wpcontent,' . $b . ' #wpbody-content{margin-left:0;}'
            . 'html.wp-toolbar:has(' . $b . '){padding-top:0;}';
    }

    








    public static function element_reset_css() {
        $scope = 'body :where(#' . self::ROOT_ID . ') ';
        return $scope . ':is(h1,h2,h3,h4,h5,h6,p,ul,ol,dl,dd,li,figure,pre,blockquote){margin:0;}'
            . $scope . ':is(h1,h2,h3,h4,h5,h6,p){font-size:inherit;font-weight:inherit;line-height:inherit;color:inherit;}'
            . $scope . ':is(ul,ol){padding:0;list-style:none;}';
    }

    






    public static function pages() {
        $pages = array(
            'dashboard'   => array( '', \__( 'Dashboard', 'easy-mcp-ai' ) ),
            'connections' => array( '-connections', \__( 'Connections', 'easy-mcp-ai' ) ),
            'tools'       => array( '-tools', \__( 'Tools', 'easy-mcp-ai' ) ),
            'activity'    => array( '-activity', \__( 'Activity', 'easy-mcp-ai' ) ),
            'settings'    => array( '-settings', \__( 'Settings', 'easy-mcp-ai' ) ),
            'setup'       => array( '-setup', \__( 'Set up', 'easy-mcp-ai' ) ),
        );
        
        
        
        
        if ( self::has_kit_page() ) {
            $pages['kit'] = array( '-kit', 'Kit', true );
        }
        return $pages;
    }

    











    public static function has_kit_page() {
        $chunk = defined( 'EASY_MCP_AI_PLUGIN_DIR' )
            ? EASY_MCP_AI_PLUGIN_DIR . 'assets/build/page-kit.js'
            : '';
        return (bool) \apply_filters( 'easy_mcp_ai_kit_page', '' !== $chunk && is_readable( $chunk ) );
    }

    



    public static function is_hidden_page( $key ) {
        $pages = self::pages();
        return isset( $pages[ $key ][2] ) && true === $pages[ $key ][2];
    }

    



    public static function page_key_for_slug( $slug ) {
        foreach ( self::pages() as $key => $page ) {
            if ( self::SLUG_PREFIX . $page[0] === $slug ) {
                return $key;
            }
        }
        return '';
    }

    public function register_menus() {
        $this->hooks[] = \add_menu_page(
            \__( 'Easy MCP AI', 'easy-mcp-ai' ),
            \__( 'Easy MCP AI', 'easy-mcp-ai' ),
            'manage_options',
            self::SLUG_PREFIX,
            array( $this, 'render_app' ),
            'dashicons-rest-api',
            self::MENU_POSITION
        );
        foreach ( self::pages() as $key => $page ) {
            $hook = \add_submenu_page(
                self::SLUG_PREFIX,
                $page[1],
                $page[1],
                'manage_options',
                self::SLUG_PREFIX . $page[0],
                array( $this, 'render_app' )
            );
            $this->hooks[] = $hook;
            if ( 'setup' === $key ) {
                
                
                
                $this->setup_hook = (string) $hook;
            }
        }
        
        
        \add_submenu_page(
            self::SLUG_PREFIX,
            \__( 'Change history', 'easy-mcp-ai' ),
            \__( 'Change history', 'easy-mcp-ai' ),
            'manage_options',
            self::change_history_slug(),
            '',
            array_search( 'activity', array_keys( self::pages() ), true ) + 1
        );
        $this->hooks = array_values( array_unique( array_filter( $this->hooks ) ) );
    }

    
    public static function change_history_slug() {
        return 'admin.php?page=' . self::SLUG_PREFIX . self::pages()['activity'][0] . '&section=changes';
    }

    






    public function current_submenu( $submenu_file ) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only menu highlight.
        $page    = isset( $_GET['page'] ) ? \sanitize_key( \wp_unslash( $_GET['page'] ) ) : '';
        $section = isset( $_GET['section'] ) ? \sanitize_key( \wp_unslash( $_GET['section'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( 'activity' === self::page_key_for_slug( $page ) && 'changes' === $section ) {
            return self::change_history_slug();
        }
        return $submenu_file;
    }

    





    public function hide_menu_entries() {
        $setup_pending = ! Setup_State::is_complete();
        foreach ( self::pages() as $key => $page ) {
            
            
            
            if ( self::is_hidden_page( $key ) || ( $setup_pending ? 'setup' !== $key : 'setup' === $key ) ) {
                \remove_submenu_page( self::SLUG_PREFIX, self::SLUG_PREFIX . $page[0] );
            }
        }
        if ( $setup_pending ) {
            \remove_submenu_page( self::SLUG_PREFIX, self::change_history_slug() );
            if ( class_exists( \Easy_MCP_AI\Themeisle_SDK::class ) ) {
                \remove_submenu_page( self::SLUG_PREFIX, \Easy_MCP_AI\Themeisle_SDK::ABOUT_US_MENU_SLUG );
            }
        }
    }

    






    public function maybe_redirect() {
        if ( \wp_doing_ajax() || ! \current_user_can( 'manage_options' ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check.
        $slug = isset( $_GET['page'] ) ? \sanitize_key( \wp_unslash( $_GET['page'] ) ) : '';
        $key  = self::page_key_for_slug( $slug );
        if ( '' !== $key && 'setup' !== $key && ! Setup_State::is_complete() ) {
            \wp_safe_redirect( self::url_for( 'setup' ) );
            exit;
        }

        if ( ! \get_transient( Setup_State::REDIRECT_TRANSIENT ) ) {
            return;
        }
        \delete_transient( Setup_State::REDIRECT_TRANSIENT );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only guard against bulk activation.
        if ( \is_network_admin() || isset( $_GET['activate-multi'] ) ) {
            return;
        }
        \wp_safe_redirect( self::url_for( Setup_State::is_complete() ? 'dashboard' : 'setup' ) );
        exit;
    }

    public function render_app() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing; mutates nothing.
        $slug = isset( $_GET['page'] ) ? \sanitize_key( \wp_unslash( $_GET['page'] ) ) : '';
        $key  = self::page_key_for_slug( $slug );
        $key  = '' !== $key ? $key : 'dashboard';
        echo '<div id="' . \esc_attr( self::ROOT_ID ) . '" data-page="' . \esc_attr( $key ) . '">' . self::skeleton_html( $key ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup, no input.
    }

    
    const FRAMED_PAGES = array( 'dashboard', 'connections', 'tools', 'settings' );

    







    public static function skeleton_html( $key ) {
        if ( 'setup' === $key ) {
            return '';
        }
        $bar  = '<span class="easy-mcp-ai-sk__bone easy-mcp-ai-sk__bone--line"></span>';
        $card = '<div class="easy-mcp-ai-sk__card">' . str_replace( '--line', '--line easy-mcp-ai-sk__bone--short', $bar ) . $bar . str_replace( '--line', '--line easy-mcp-ai-sk__bone--mid', $bar ) . '</div>';
        $main = '<div class="easy-mcp-ai-sk__col">' . $card . $card . '</div>';
        $body = in_array( $key, self::FRAMED_PAGES, true )
            ? '<div class="easy-mcp-ai-sk__frame"><span class="easy-mcp-ai-sk__bone easy-mcp-ai-sk__bone--title"></span>' . $main . '<div class="easy-mcp-ai-sk__col easy-mcp-ai-sk__aside">' . $card . $card . '</div></div>'
            : '<div class="easy-mcp-ai-sk__frame easy-mcp-ai-sk__frame--single"><span class="easy-mcp-ai-sk__bone easy-mcp-ai-sk__bone--title"></span>' . $main . '</div>';
        return '<div class="easy-mcp-ai-sk" aria-busy="true" data-testid="app-skeleton">'
            . '<div class="easy-mcp-ai-sk__bar"><span class="easy-mcp-ai-sk__bone easy-mcp-ai-sk__bone--logo"></span><span class="easy-mcp-ai-sk__bone easy-mcp-ai-sk__bone--status"></span></div>'
            . '<div class="easy-mcp-ai-sk__main">' . $body . '</div>'
            . '<span class="screen-reader-text">' . \esc_html__( 'Loading…', 'easy-mcp-ai' ) . '</span>'
            . '</div>';
    }

    







    public static function skeleton_css() {
        $s = '#' . self::ROOT_ID . ' .easy-mcp-ai-sk';
        return $s . '{display:flex;flex-direction:column;min-height:calc(100vh - var(--wp-admin--admin-bar--height,32px));}'
            . $s . '__bar{display:flex;align-items:center;gap:16px;height:3.25rem;padding:0 24px;background:#FFFFFF;border-bottom:1px solid #D9DCE1;box-sizing:border-box;}'
            . $s . '__main{padding:24px;}'
            . $s . '__frame{display:grid;grid-template-columns:minmax(0,1fr);gap:20px;max-width:87.5rem;margin:0 auto;}'
            . $s . '__col{display:flex;flex-direction:column;gap:20px;min-width:0;}'
            . $s . '__card{display:flex;flex-direction:column;gap:12px;padding:20px;background:#FFFFFF;border:1px solid #D9DCE1;border-radius:3px;}'
            . $s . '__bone{display:block;background:#EEF0F3;border-radius:3px;animation:easy-mcp-ai-sk-pulse 1.4s cubic-bezier(0.4,0,0.6,1) infinite;}'
            . $s . '__bone--line{height:12px;}'
            . $s . '__bone--short{width:40%;}'
            . $s . '__bone--mid{width:80%;}'
            . $s . '__bone--title{height:28px;width:12rem;}'
            . $s . '__bone--logo{height:24px;width:24px;}'
            . $s . '__bone--status{height:14px;width:6rem;}'
            . '@media (min-width:1100px){' . $s . '__frame{grid-template-columns:minmax(0,1fr) minmax(16.25rem,18.75rem);}' . $s . '__frame--single{grid-template-columns:minmax(0,1fr);}' . $s . '__bone--title{grid-column:1/-1;}}'
            . '@media (max-width:782px){' . $s . '__bar{padding:0 12px;}}'
            . '@keyframes easy-mcp-ai-sk-pulse{50%{opacity:.5;}}'
            . '@media (prefers-reduced-motion:reduce){' . $s . '__bone{animation:none;}}';
    }

    


    public function enqueue_assets( $hook ) {
        if ( ! $this->is_app_hook( $hook ) ) {
            return;
        }

        $build      = EASY_MCP_AI_PLUGIN_DIR . 'assets/build/';
        $asset_file = $build . 'index.asset.php';
        if ( ! is_readable( $asset_file ) ) {
            return;
        }
        $asset = require $asset_file;
        $deps  = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();
        $ver   = isset( $asset['version'] ) ? (string) $asset['version'] : EASY_MCP_AI_VERSION;

        
        
        if ( is_readable( $build . 'runtime.asset.php' ) && file_exists( $build . 'runtime.js' ) ) {
            $runtime = require $build . 'runtime.asset.php';
            \wp_enqueue_script(
                self::HANDLE . '-runtime',
                EASY_MCP_AI_PLUGIN_URL . 'assets/build/runtime.js',
                isset( $runtime['dependencies'] ) && is_array( $runtime['dependencies'] ) ? $runtime['dependencies'] : array(),
                isset( $runtime['version'] ) ? (string) $runtime['version'] : $ver,
                true
            );
            $deps[] = self::HANDLE . '-runtime';
        }

        \wp_enqueue_script( self::HANDLE, EASY_MCP_AI_PLUGIN_URL . 'assets/build/index.js', $deps, $ver, true );
        if ( file_exists( $build . 'index.css' ) ) {
            \wp_enqueue_style( self::HANDLE, EASY_MCP_AI_PLUGIN_URL . 'assets/build/index.css', array(), $ver );
        } else {
            \wp_register_style( self::HANDLE, false, array(), $ver );
            \wp_enqueue_style( self::HANDLE );
        }
        \wp_add_inline_style( self::HANDLE, self::canvas_css() );
        \wp_set_script_translations( self::HANDLE, 'easy-mcp-ai', EASY_MCP_AI_PLUGIN_DIR . 'languages' );
        \wp_add_inline_script(
            self::HANDLE,
            'window.easyMcpAi = ' . \wp_json_encode( self::bootstrap() ) . ';',
            'before'
        );
    }

    




    public static function bootstrap() {
        require_once __DIR__ . '/class-site-kind.php';
        require_once __DIR__ . '/rest/class-admin-rest-controller.php';

        $user = \wp_get_current_user();

        return array(
            'restUrl'           => \esc_url_raw( \rest_url( Rest\Admin_Rest_Controller::REST_NAMESPACE . '/' ) ),
            'nonce'             => \wp_create_nonce( 'wp_rest' ),
            'slugPrefix'        => self::SLUG_PREFIX,
            'siteKind'          => Site_Kind::detect( \home_url() ),
            'isRtl'             => \is_rtl(),
            'version'           => EASY_MCP_AI_VERSION,
            'userDisplayName'   => isset( $user->display_name ) ? (string) $user->display_name : '',
            'adminUrl'          => \admin_url( 'admin.php' ),
            
            
            'timezone'          => self::site_timezone(),
            'dateFormat'        => self::site_date_format(),
            
            'brandName'         => \__( 'Easy MCP AI', 'easy-mcp-ai' ),
            'whiteLabel'        => \Easy_MCP_AI\Config::is_white_labelled(),
        );
    }

    


    private static function site_timezone() {
        $zone = (string) \wp_timezone_string();
        return '' !== $zone ? $zone : 'UTC';
    }

    


    private static function site_date_format() {
        $format = \get_option( 'date_format', 'F j, Y' );
        return ( is_string( $format ) && '' !== $format ) ? $format : 'F j, Y';
    }
}
