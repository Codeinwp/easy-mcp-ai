<?php














namespace Easy_MCP_AI\Skills;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





final class Skill_Set {

    
    const SOURCE_SYNCED  = 'synced';
    const SOURCE_BUNDLED = 'bundled';
    const SOURCE_LOCAL   = 'local';
    const SOURCE_NONE    = 'none';

    
    private $skills;

    
    private $source;

    
    private $serial;

    




    public function __construct( array $skills, string $source, ?int $serial = null ) {
        $this->skills = array_values( $skills );
        $this->source = $source;
        $this->serial = $serial;
    }

    






    public static function from_release( array $release, string $source ): self {
        $skills = array();
        foreach ( isset( $release['skills'] ) && is_array( $release['skills'] ) ? $release['skills'] : array() as $raw ) {
            $skill = self::normalise( $raw );
            if ( null !== $skill ) {
                $skills[] = $skill;
            }
        }
        return new self( $skills, $source, isset( $release['serial'] ) && is_int( $release['serial'] ) ? $release['serial'] : null );
    }

    



    public static function normalise( $raw ): ?array {
        if ( ! is_array( $raw ) || ! isset( $raw['path'], $raw['frontmatter'], $raw['files'] ) || ! is_string( $raw['path'] )
            || ! is_array( $raw['frontmatter'] ) || ! is_array( $raw['files'] ) || ! isset( $raw['frontmatter']['name'] )
            || ! is_string( $raw['frontmatter']['name'] ) || '' === $raw['frontmatter']['name'] ) {
            return null;
        }
        $files = array();
        foreach ( $raw['files'] as $file ) {
            if ( is_array( $file ) && isset( $file['path'], $file['text'], $file['digest'], $file['size'] ) && is_string( $file['path'] ) && is_string( $file['text'] ) ) {
                $files[] = array(
                    'path'   => $file['path'],
                    'digest' => (string) $file['digest'],
                    'size'   => (int) $file['size'],
                    'text'   => $file['text'],
                );
            }
        }
        usort(
            $files,
            static function ( array $a, array $b ): int {
                if ( 'SKILL.md' === $a['path'] ) {
                    return -1;
                }
                return 'SKILL.md' === $b['path'] ? 1 : strcmp( $a['path'], $b['path'] );
            }
        );
        if ( empty( $files ) || 'SKILL.md' !== $files[0]['path'] ) {
            return null;
        }
        $tools = array();
        foreach ( isset( $raw['tools'] ) && is_array( $raw['tools'] ) ? $raw['tools'] : array() as $tool ) {
            if ( is_string( $tool ) && '' !== $tool ) {
                $tools[] = $tool;
            }
        }
        return array(
            'path'        => $raw['path'],
            'name'        => $raw['frontmatter']['name'],
            'frontmatter' => $raw['frontmatter'],
            'requires'    => isset( $raw['requires'] ) && is_array( $raw['requires'] ) ? $raw['requires'] : array(),
            'tools'       => array_values( array_unique( $tools ) ),
            'files'       => $files,
        );
    }

    public function source(): string {
        return $this->source;
    }

    
    public function serial(): ?int {
        return $this->serial;
    }

    
    public function skills(): array {
        return $this->skills;
    }

    public function is_empty(): bool {
        return empty( $this->skills );
    }

    



    public function find( string $name ): ?array {
        foreach ( $this->skills as $skill ) {
            if ( $skill['name'] === $name ) {
                return $skill;
            }
        }
        return null;
    }

    





    public function find_file_by_uri( string $uri ): ?array {
        foreach ( $this->skills as $skill ) {
            foreach ( $skill['files'] as $file ) {
                if ( self::uri( $skill, $file['path'] ) === $uri ) {
                    return array(
                        'skill' => $skill,
                        'file'  => $file,
                    );
                }
            }
        }
        return null;
    }

    
    public function filtered( callable $keep ): self {
        return new self( array_values( array_filter( $this->skills, $keep ) ), $this->source, $this->serial );
    }

    



    public static function uri( array $skill, string $file = 'SKILL.md' ): string {
        return 'skill://' . $skill['path'] . '/' . $file;
    }

    



    public static function version( array $skill ): ?string {
        $meta = isset( $skill['frontmatter']['metadata'] ) && is_array( $skill['frontmatter']['metadata'] ) ? $skill['frontmatter']['metadata'] : array();
        return isset( $meta['version'] ) && is_scalar( $meta['version'] ) ? (string) $meta['version'] : null;
    }

    



    public static function description( array $skill ): string {
        return isset( $skill['frontmatter']['description'] ) && is_string( $skill['frontmatter']['description'] ) ? $skill['frontmatter']['description'] : '';
    }

    






    public static function entry( array $skill ): array {
        $resources = array();
        foreach ( $skill['files'] as $file ) {
            $resources[] = array(
                'uri'    => self::uri( $skill, $file['path'] ),
                'digest' => $file['digest'],
                'size'   => $file['size'],
            );
        }
        return array(
            'uri'         => self::uri( $skill ),
            'frontmatter' => self::as_object( $skill['frontmatter'] ),
            'resources'   => $resources,
        );
    }

    





    private static function as_object( $value ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }
        if ( array() === $value ) {
            return new \stdClass();
        }
        if ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
            return array_map( array( __CLASS__, 'as_object' ), $value );
        }
        $out = new \stdClass();
        foreach ( $value as $k => $v ) {
            $out->{$k} = self::as_object( $v );
        }
        return $out;
    }
}
