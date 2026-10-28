<?php

namespace Easy_MCP_AI\Skills;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Local_Skills {

    const FILTER = 'easy_mcp_ai_skills_directory';

    const TEXT_EXTENSIONS = array( 'md', 'txt', 'json', 'yaml', 'yml', 'csv' );

    const NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    const NAME_MAX = 64;
    const DESCRIPTION_MAX = 1024;

    const CORE_NUMBER = '/^(?:[-+]?[0-9]+|0o[0-7]+|0x[0-9a-fA-F]+|[-+]?(?:\.[0-9]+|[0-9]+(?:\.[0-9]*)?)(?:[eE][-+]?[0-9]+)?|[-+]?\.(?:inf|Inf|INF)|\.(?:nan|NaN|NAN))$/';

    const TOOL_TOKEN = '/\bwp_[a-z0-9_]+\b/';

    public static function directory(): ?string {
        $dir = \apply_filters( self::FILTER, null );
        if ( ! is_string( $dir ) || '' === trim( $dir ) ) {
            return null;
        }
        return rtrim( $dir, '/\\' );
    }

    public static function load( string $dir, array $known_tools ): array {
        if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
            return array(
                'skills'   => array(),
                'problems' => array( 'The skills folder is missing or not readable.' ),
            );
        }
        $known      = array_fill_keys( $known_tools, true );
        $skills     = array();
        $problems   = array();
        $unreadable = array();
        foreach ( self::skill_paths( $dir, $unreadable ) as $path ) {
            try {
                $skills[] = self::load_skill( $dir, $path, $known );
            } catch ( \InvalidArgumentException $e ) {
                $problems[] = $path . ': ' . $e->getMessage();
            }
        }

        foreach ( $unreadable as $folder ) {
            $problems[] = $folder . ': this folder cannot be read; any skill in it is not served';
        }

        $by_name = array();
        foreach ( $skills as $skill ) {
            $by_name[ $skill['name'] ][] = $skill['path'];
        }
        $kept = array();
        foreach ( $skills as $skill ) {
            if ( count( $by_name[ $skill['name'] ] ) > 1 ) {
                $problems[] = $skill['path'] . ': another skill has the name "' . $skill['name'] . '"; both are left out';
                continue;
            }
            $kept[] = $skill;
        }
        return array(
            'skills'   => $kept,
            'problems' => $problems,
        );
    }

    public static function skill_paths( string $dir, array &$unreadable = array() ): array {
        $paths = array();
        foreach ( self::files( $dir, $unreadable ) as $relative ) {
            if ( 'SKILL.md' === $relative ) {
                continue;
            }
            if ( '/SKILL.md' === substr( $relative, -9 ) ) {
                $paths[] = substr( $relative, 0, -9 );
            }
        }
        sort( $paths, SORT_STRING );
        return $paths;
    }

    public static function files( string $dir, array &$unreadable = array() ): array {
        $out = array();
        self::walk( $dir, '', $out, $unreadable );
        sort( $out, SORT_STRING );
        return $out;
    }

    private static function walk( string $dir, string $relative, array &$out, array &$unreadable ): void {
        $entries = is_readable( $dir ) ? @scandir( $dir ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- reported through $unreadable.
        if ( false === $entries ) {
            $unreadable[] = '' === $relative ? '.' : $relative;
            return;
        }
        foreach ( $entries as $name ) {
            if ( '' === $name || '.' === $name[0] ) {
                continue;
            }
            $path = $dir . '/' . $name;
            $rel  = '' === $relative ? $name : $relative . '/' . $name;
            if ( is_link( $path ) ) {
                continue;
            }
            if ( is_dir( $path ) ) {
                self::walk( $path, $rel, $out, $unreadable );
            } elseif ( is_file( $path ) ) {
                $out[] = $rel;
            } elseif ( ! file_exists( $path ) ) {
                $unreadable[] = '' === $relative ? '.' : $relative;
                return;
            }
        }
    }

    private static function load_skill( string $dir, string $path, array $known ): array {
        $segments = explode( '/', $path );
        foreach ( $segments as $segment ) {
            if ( ! preg_match( self::NAME_PATTERN, $segment ) || strlen( $segment ) > self::NAME_MAX ) {
                throw new \InvalidArgumentException( 'folder "' . $segment . '" must be lowercase letters, digits and single hyphens, at most ' . self::NAME_MAX . ' characters' );
            }
        }
        $skill_dir  = $dir . '/' . $path;
        $unreadable = array();
        $names      = self::files( $skill_dir, $unreadable );
        if ( ! empty( $unreadable ) ) {
            throw new \InvalidArgumentException( 'the folder "' . $unreadable[0] . '" in it cannot be read, so the skill would be incomplete' );
        }
        if ( count( $names ) > Skill_Trust::SKILL_MAX_FILES ) {
            throw new \InvalidArgumentException( count( $names ) . ' files, the limit is ' . Skill_Trust::SKILL_MAX_FILES );
        }
        $files = array();
        $total = 0;
        $text  = '';
        foreach ( $names as $name ) {
            $extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
            if ( ! in_array( $extension, self::TEXT_EXTENSIONS, true ) ) {
                throw new \InvalidArgumentException( $name . ' is not a text file; skills are text only (' . implode( ', ', self::TEXT_EXTENSIONS ) . ')' );
            }
            $bytes = is_readable( $skill_dir . '/' . $name ) ? file_get_contents( $skill_dir . '/' . $name ) : false;
            if ( false === $bytes ) {
                throw new \InvalidArgumentException( $name . ' cannot be read, so the skill would be incomplete' );
            }
            if ( 1 !== preg_match( '//u', $bytes ) ) {
                throw new \InvalidArgumentException( $name . ' is not valid UTF-8' );
            }
            $total += strlen( $bytes );
            if ( $total > Skill_Trust::SKILL_MAX_BYTES ) {
                throw new \InvalidArgumentException( 'more than ' . Skill_Trust::SKILL_MAX_BYTES . ' bytes' );
            }
            $files[] = array(
                'path'   => $name,
                'digest' => 'sha256:' . hash( 'sha256', $bytes ),
                'size'   => strlen( $bytes ),
                'text'   => $bytes,
            );
            $text .= "\n" . $bytes;
        }

        $skill_md    = $files[ array_search( 'SKILL.md', $names, true ) ]['text'];
        $frontmatter = self::parse_frontmatter( $skill_md );
        $name        = end( $segments );
        if ( ! isset( $frontmatter['name'] ) || $frontmatter['name'] !== $name ) {
            throw new \InvalidArgumentException( 'the frontmatter name must equal the folder name "' . $name . '"' );
        }
        if ( ! isset( $frontmatter['description'] ) || ! is_string( $frontmatter['description'] ) || '' === trim( $frontmatter['description'] ) ) {
            throw new \InvalidArgumentException( 'the frontmatter needs a non-empty description' );
        }
        if ( strlen( $frontmatter['description'] ) > self::DESCRIPTION_MAX ) {
            throw new \InvalidArgumentException( 'the description is longer than ' . self::DESCRIPTION_MAX . ' characters' );
        }

        preg_match_all( self::TOOL_TOKEN, $text, $matches );
        $tools = array();
        foreach ( array_unique( $matches[0] ) as $token ) {
            if ( isset( $known[ $token ] ) ) {
                $tools[] = $token;
            }
        }
        sort( $tools, SORT_STRING );

        return Skill_Set::normalise(
            array(
                'path'        => $path,
                'frontmatter' => $frontmatter,
                'requires'    => array(),
                'tools'       => $tools,
                'files'       => $files,
            )
        );
    }

    public static function parse_frontmatter( string $text ): array {
        if ( ! preg_match( '/\A---\r?\n(.*?)\r?\n---\r?\n/s', $text, $match ) ) {
            throw new \InvalidArgumentException( 'SKILL.md must begin with YAML frontmatter between --- lines' );
        }
        $out    = array();
        $parent = null;
        foreach ( preg_split( '/\r?\n/', $match[1] ) as $i => $line ) {
            if ( '' === trim( $line ) || '#' === ltrim( $line )[0] ) {
                continue;
            }
            if ( preg_match( '/^([A-Za-z0-9_-]+):(?=[ \t]|$)[ \t]*(.*)$/', $line, $kv ) ) {
                self::refuse_duplicate( $out, $kv[1], $i );
                if ( '' === trim( $kv[2] ) ) {
                    $out[ $kv[1] ] = null;
                    $parent        = $kv[1];
                } else {
                    $out[ $kv[1] ] = self::scalar( $kv[2], $i );
                    $parent        = null;
                }
                continue;
            }
            if ( null !== $parent && preg_match( '/^[ ]{2}([A-Za-z0-9_.\/-]+):[ \t]+(\S.*)$/', $line, $kv ) ) {
                if ( ! is_array( $out[ $parent ] ) ) {
                    $out[ $parent ] = array();
                }
                self::refuse_duplicate( $out[ $parent ], $kv[1], $i );
                $out[ $parent ][ $kv[1] ] = self::scalar( $kv[2], $i );
                continue;
            }
            throw new \InvalidArgumentException( 'frontmatter line ' . ( $i + 2 ) . ' is not supported: use "key: value" lines and one level of nesting' );
        }
        return $out;
    }

    private static function refuse_duplicate( array $map, string $key, int $i ): void {
        if ( array_key_exists( $key, $map ) ) {
            throw new \InvalidArgumentException( 'frontmatter line ' . ( $i + 2 ) . ': "' . $key . '" is already set above' );
        }
    }

    private static function scalar( string $raw, int $i ) {
        $raw = trim( $raw );
        if ( '"' === $raw[0] ) {
            $value = json_decode( $raw );
            if ( ! is_string( $value ) ) {
                throw new \InvalidArgumentException( 'frontmatter line ' . ( $i + 2 ) . ': unterminated or invalid double-quoted value' );
            }
            return $value;
        }
        if ( "'" === $raw[0] ) {
            if ( strlen( $raw ) < 2 || "'" !== substr( $raw, -1 ) ) {
                throw new \InvalidArgumentException( 'frontmatter line ' . ( $i + 2 ) . ': unterminated single-quoted value' );
            }
            return str_replace( "''", "'", substr( $raw, 1, -1 ) );
        }
        if ( false !== strpos( '[{|>&*!', $raw[0] ) ) {
            throw new \InvalidArgumentException( 'frontmatter line ' . ( $i + 2 ) . ': lists, maps and block values are not supported' );
        }
        if ( false !== strpos( '#%@`,', $raw[0] ) || preg_match( '/^[-?:](\s|$)/', $raw ) || false !== strpos( $raw, ': ' ) || ':' === substr( $raw, -1 ) ) {
            throw new \InvalidArgumentException( 'frontmatter line ' . ( $i + 2 ) . ': quote this value' );
        }
        $raw = (string) preg_replace( '/\s+#.*$/', '', $raw );
        if ( in_array( $raw, array( 'true', 'True', 'TRUE', 'false', 'False', 'FALSE' ), true ) ) {
            return 't' === strtolower( $raw[0] );
        }
        if ( in_array( $raw, array( 'null', 'Null', 'NULL', '~' ), true ) ) {
            return null;
        }
        if ( preg_match( '/^-?(?:0|[1-9][0-9]{0,14})$/', $raw ) ) {
            return (int) $raw;
        }
        if ( preg_match( '/^-?(?:0|[1-9][0-9]*)\.[0-9]+$/', $raw ) ) {
            return (float) $raw;
        }
        if ( preg_match( self::CORE_NUMBER, $raw ) ) {
            throw new \InvalidArgumentException( 'frontmatter line ' . ( $i + 2 ) . ': quote this value' );
        }
        return $raw;
    }
}
