<?php






















namespace Easy_MCP_AI\Skills;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




final class Skills {

    
    const BUNDLED_DIR = 'assets/skills';

    
    const REQUIREMENT_KEYS = array( 'plugin', 'plugins', 'theme' );

    
    private static $registry = null;

    
    private static $catalog = null;

    
    private static $bundled_dir = null;

    
    public static function use_registry( ?\Easy_MCP_AI\Tools\Tool_Registry $registry ): void {
        self::$registry = $registry;
        self::$catalog  = null;
    }

    
    public static function reset(): void {
        self::$catalog = null;
    }

    
    public static function use_bundled_dir( ?string $dir ): void {
        self::$bundled_dir = $dir;
        self::$catalog     = null;
    }

    
    public static function catalog(): array {
        if ( null !== self::$catalog ) {
            return self::$catalog;
        }
        $notes  = array();
        $active = null;
        $dir    = Local_Skills::directory();
        if ( null !== $dir ) {
            $loaded = Local_Skills::load( $dir, self::tool_names() );
            $notes  = $loaded['problems'];
            if ( ! empty( $loaded['skills'] ) ) {
                $active = new Skill_Set( $loaded['skills'], Skill_Set::SOURCE_LOCAL );
            } else {
                $notes[] = 'The local skills folder holds no valid skill; the copy bundled with the plugin is served.';
            }
        }
        if ( null === $active ) {
            $bundled = self::bundled();
            $synced  = ( null === $dir && Skill_Sync::enabled() ) ? Skill_Sync::stored() : null;
            if ( null !== $synced && ( null === $bundled || $synced['set']->serial() >= $bundled->serial() ) ) {
                $active = $synced['set'];
            } elseif ( null !== $bundled ) {
                $active = $bundled;
            }
        }
        if ( null === $active ) {
            $active = new Skill_Set( array(), Skill_Set::SOURCE_NONE );
        }
        self::$catalog = array(
            'served' => $active->filtered( array( __CLASS__, 'requirements_met' ) ),
            'active' => $active,
            'notes'  => $notes,
        );
        return self::$catalog;
    }

    
    public static function served(): Skill_Set {
        return self::catalog()['served'];
    }

    





    public static function bundled(): ?Skill_Set {
        $dir  = null !== self::$bundled_dir ? self::$bundled_dir : ( defined( 'EASY_MCP_AI_PLUGIN_DIR' ) ? EASY_MCP_AI_PLUGIN_DIR . self::BUNDLED_DIR : '' );
        $body = is_readable( $dir . '/release.json' ) ? (string) file_get_contents( $dir . '/release.json' ) : '';
        $sig  = is_readable( $dir . '/release.sig' ) ? json_decode( (string) file_get_contents( $dir . '/release.sig' ), true ) : null;
        if ( '' === $body || ! is_array( $sig ) ) {
            return null;
        }
        $result = Release_Verifier::verify( $body, $sig, Skill_Trust::roots(), time() );
        if ( ! $result['ok'] ) {
            return null;
        }
        if ( Skill_Sync::is_revoked( $result['public_key'] ) ) {
            return null;
        }
        return Skill_Set::from_release( $result['release'], Skill_Set::SOURCE_BUNDLED );
    }

    


    public static function requirements_met( array $skill ): bool {
        $requires = $skill['requires'];
        foreach ( array_keys( $requires ) as $key ) {
            if ( ! in_array( $key, self::REQUIREMENT_KEYS, true ) ) {
                return false;
            }
        }
        if ( isset( $requires['plugin'] ) && ! self::version_satisfies( $requires['plugin'] ) ) {
            return false;
        }
        if ( isset( $requires['plugins'] ) ) {
            if ( ! is_array( $requires['plugins'] ) ) {
                return false;
            }
            $active = self::active_plugin_slugs();
            foreach ( $requires['plugins'] as $slug ) {
                if ( ! is_string( $slug ) || ! isset( $active[ $slug ] ) ) {
                    return false;
                }
            }
        }
        if ( isset( $requires['theme'] ) ) {
            $block = function_exists( 'wp_is_block_theme' ) && \wp_is_block_theme();
            if ( ! in_array( $requires['theme'], array( 'block', 'classic' ), true ) || ( 'block' === $requires['theme'] ) !== $block ) {
                return false;
            }
        }
        if ( null !== self::$registry ) {
            foreach ( $skill['tools'] as $tool ) {
                if ( null === self::$registry->get_tool( $tool ) ) {
                    return false;
                }
            }
        }
        return true;
    }

    








    public static function version_satisfies( $constraint, ?string $version = null ): bool {
        if ( ! is_string( $constraint ) || ! preg_match( '/^\s*(>=|<=|>|<|==|=)?\s*(\d+(?:\.\d+){0,3})\s*$/', $constraint, $m ) ) {
            return false;
        }
        $operator = '' === $m[1] ? '>=' : ( '=' === $m[1] ? '==' : $m[1] );
        if ( null === $version ) {
            $version = defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : '0';
        }
        return version_compare( (string) $version, $m[2], $operator );
    }

    
    private static function active_plugin_slugs(): array {
        $files = (array) \get_option( 'active_plugins', array() );
        if ( \is_multisite() ) {
            $files = array_merge( $files, array_keys( (array) \get_site_option( 'active_sitewide_plugins', array() ) ) );
        }
        $slugs = array();
        foreach ( $files as $file ) {
            if ( is_string( $file ) && '' !== $file ) {
                $slug           = false !== strpos( $file, '/' ) ? strstr( $file, '/', true ) : basename( $file, '.php' );
                $slugs[ $slug ] = true;
            }
        }
        return $slugs;
    }

    
    private static function tool_names(): array {
        return null !== self::$registry ? (array) self::$registry->get_all_tool_names() : array();
    }
}
