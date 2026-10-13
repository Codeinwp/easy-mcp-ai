<?php






namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

















class Console_Styles {

    
    private static $markup = '';

    










    public static function inline() {
        if ( '' !== self::$markup ) {
            return self::$markup;
        }

        $css = '';
        $file = EASY_MCP_AI_PLUGIN_DIR . 'assets/css/console.css';
        if ( is_readable( $file ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a stylesheet shipped inside this plugin, not a remote resource.
            $css = (string) file_get_contents( $file );
        }

        if ( '' === $css ) {
            
            
            self::$markup = '';
            return self::$markup;
        }

        self::$markup = '<style>' . self::font_face() . $css . '</style>';
        return self::$markup;
    }

    













    public static function logo() {
        
        if ( Config::is_white_labelled() ) {
            return '';
        }
        return '<span class="emcp-topbar__mark">'
            . '<svg viewBox="0 0 100 100" width="16" height="16" aria-hidden="true" focusable="false">'
            . '<path d="M15 25 L35 85 L50 45" fill="none" stroke="#C5F121" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M50 45 L65 85 L85 25" fill="none" stroke="#FFFFFF" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>'
            . '</svg></span>';
    }

    
    public static function site_label() {
        return Config::is_white_labelled() ? (string) Config::get( 'brand_name' ) : (string) \get_bloginfo( 'name' );
    }

    public static function site_name() {
        return '<span class="emcp-topbar__site">' . \esc_html( self::site_label() ) . '</span>';
    }

    









    private static function font_face() {
        $dir = EASY_MCP_AI_PLUGIN_DIR . 'assets/build/fonts';
        if ( ! is_dir( $dir ) ) {
            return '';
        }

        $files = glob( $dir . '/ibm-plex-*.woff2' );
        if ( empty( $files ) ) {
            return '';
        }

        
        
        
        $ranges = array(
            'latin'     => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD',
            'latin-ext' => 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF',
        );

        $css = '';
        foreach ( $files as $path ) {
            $name = basename( $path );
            if ( ! preg_match( '/^ibm-plex-(sans|mono)-(latin-ext|latin)-(\d{3})-normal\.[0-9a-f]+\.woff2$/', $name, $m ) ) {
                continue;
            }
            $family = 'sans' === $m[1] ? 'IBM Plex Sans' : 'IBM Plex Mono';
            $url    = esc_url( EASY_MCP_AI_PLUGIN_URL . 'assets/build/fonts/' . $name );

            $css .= '@font-face{font-family:"' . $family . '";font-style:normal;font-display:swap;font-weight:' . (int) $m[3] . ';'
                . 'src:url(' . $url . ') format("woff2");unicode-range:' . $ranges[ $m[2] ] . ';}';
        }

        return $css;
    }
}
