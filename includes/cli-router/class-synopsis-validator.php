<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Synopsis_Validator {

    public static function validate( array $leaf, array $args, array $assoc, Output $out, array $config = array() ): array {
        $spec    = isset( $leaf['params'] ) && is_array( $leaf['params'] ) ? $leaf['params'] : array();
        $command = 'wp ' . $leaf['command'];

        self::warn_invalid_synopsis_parts( $spec, $command, $out );
        self::require_enough_positionals( $spec, $args, $leaf, $out );
        self::refuse_unknown_positionals( $spec, $args, $out );

        $errors = array(
            'fatal'   => self::apply_defaults( $spec, $args, $assoc, $out ),
            'warning' => array(),
        );

        list( $returned, $to_unset ) = self::validate_assoc( $spec, array_merge( $config, $assoc ) );
        foreach ( array( 'fatal', 'warning' ) as $error_type ) {
            $errors[ $error_type ] = array_merge( $errors[ $error_type ], $returned[ $error_type ] );
        }

        if ( 'help' !== $leaf['name'] ) {
            foreach ( self::unknown_assoc_errors( $spec, $assoc ) as $error ) {
                $errors['fatal'][] = $error;
            }
        }

        self::report( $spec, $errors, $out );

        foreach ( $to_unset as $key ) {
            unset( $assoc[ $key ] );
        }

        return array(
            'args'  => $args,
            'assoc' => $assoc,
        );
    }

    private static function warn_invalid_synopsis_parts( array $spec, string $command, Output $out ): void {
        foreach ( $spec as $param ) {
            if ( 'unknown' === $param['type'] ) {
                $out->warning( sprintf( 'The `%s` command has an invalid synopsis part: %s', $command, $param['token'] ) );
            }
        }
    }

    private static function require_enough_positionals( array $spec, array $args, array $leaf, Output $out ): void {
        $required = 0;
        foreach ( $spec as $param ) {
            if ( 'positional' === $param['type'] && false === $param['optional'] ) {
                ++$required;
            }
        }
        if ( count( $args ) < $required ) {
            $out->line( sprintf( '%s%s %s', 'usage: ', 'wp ' . $leaf['command'], $leaf['synopsis'] ) );
            $out->halt( 1 );
        }
    }

    private static function refuse_unknown_positionals( array $spec, array $args, Output $out ): void {
        $repeating = false;
        $single    = 0;
        foreach ( $spec as $param ) {
            if ( 'positional' !== $param['type'] ) {
                continue;
            }
            if ( true === $param['repeating'] ) {
                $repeating = true;
            } else {
                ++$single;
            }
        }
        $unknown_positionals = $repeating ? array() : array_slice( $args, $single );
        if ( ! empty( $unknown_positionals ) ) {
            $out->error( 'Too many positional arguments: ' . implode( ' ', $unknown_positionals ) );
        }
    }

    private static function apply_defaults( array $spec, array &$args, array &$assoc, Output $out ): array {
        $i     = 0;
        $fatal = array();
        foreach ( $spec as $param ) {
            if ( 'positional' === $param['type'] ) {
                $i = self::positional( $param, $args, $i, $out );
            } elseif ( 'assoc' === $param['type'] ) {
                $error = self::assoc( $param, $assoc );
                if ( null !== $error ) {
                    $fatal[ $param['name'] ] = $error;
                }
            }
        }
        return $fatal;
    }

    private static function positional( array $param, array &$args, int $i, Output $out ): int {
        if ( ! isset( $args[ $i ] ) && isset( $param['default'] ) ) {
            $args[ $i ] = $param['default'];
        }
        if ( ! isset( $param['options'] ) ) {
            return $i + 1;
        }
        if ( empty( $param['repeating'] ) ) {
            self::check_positional_option( $args, $i, $param['options'], $out );
            return $i + 1;
        }
        do {
            self::check_positional_option( $args, $i, $param['options'], $out );
            ++$i;
        } while ( isset( $args[ $i ] ) );
        return $i + 1;
    }

    private static function check_positional_option( array $args, int $i, array $options, Output $out ): void {
        // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- loose, as WP-CLI.
        if ( isset( $args[ $i ] ) && ! in_array( $args[ $i ], $options ) ) {
            $out->error( 'Invalid value specified for positional arg.' );
        }
    }

    private static function assoc( array $param, array &$assoc ): ?string {
        $name = $param['name'];
        if ( ! isset( $assoc[ $name ] ) && isset( $param['default'] ) ) {
            $assoc[ $name ] = $param['default'];
        }
        if ( ! isset( $assoc[ $name ], $param['options'] ) || self::is_valid_option( $assoc[ $name ], $param['options'] ) ) {
            return null;
        }
        return "Invalid value specified for '{$name}'";
    }

    private static function is_valid_option( $value, array $options ): bool {
        // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- loose, as WP-CLI.
        if ( in_array( $value, $options ) ) {
            return true;
        }
        $values = array_map( 'trim', explode( ',', (string) $value ) );
        $count  = count( $values );
        return $count > 1
            && count(
                array_filter(
                    $values,
                    static function ( string $v ) use ( $options ): bool {
                        return in_array( $v, $options, true );
                    }
                )
            ) === $count;
    }

    private static function validate_assoc( array $spec, array $merged ): array {
        $returned = array(
            'fatal'   => array(),
            'warning' => array(),
        );
        $to_unset = array();
        foreach ( $spec as $param ) {
            if ( 'assoc' !== $param['type'] ) {
                continue;
            }
            $key = $param['name'];
            if ( ! isset( $merged[ $key ] ) ) {
                if ( ! $param['optional'] ) {
                    $returned['fatal'][ $key ] = "missing --$key parameter";
                }
            } elseif ( true === $merged[ $key ] && empty( $param['value_optional'] ) ) {
                $error_type                      = ( ! $param['optional'] ) ? 'fatal' : 'warning';
                $returned[ $error_type ][ $key ] = "--$key parameter needs a value";

                $to_unset[] = $key;
            }
        }
        return array( $returned, $to_unset );
    }

    private static function unknown_assoc_errors( array $spec, array $assoc ): array {
        $errors = array();
        foreach ( self::unknown_assoc( $spec, $assoc ) as $key ) {
            $suggestion = Catalog::suggestion( (string) $key, self::parameters( $spec ) );

            $errors[] = sprintf(
                'unknown --%s parameter%s',
                $key,
                ! empty( $suggestion ) ? PHP_EOL . "Did you mean '--{$suggestion}'?" : ''
            );
        }
        return $errors;
    }

    private static function report( array $spec, array $errors, Output $out ): void {
        if ( ! empty( $errors['fatal'] ) ) {
            $message = 'Parameter errors:';
            foreach ( $errors['fatal'] as $key => $error ) {
                $message .= "\n {$error}";
                $desc     = self::param_desc( $spec, (string) $key );
                if ( '' !== $desc ) {
                    $message .= " ({$desc})";
                }
            }

            $out->error( $message );
        }

        foreach ( $errors['warning'] as $warning ) {
            $out->warning( $warning );
        }
    }

    private static function unknown_assoc( array $spec, array $assoc ): array {
        $known = array();
        foreach ( $spec as $param ) {
            if ( 'generic' === $param['type'] ) {
                return array();
            }
            if ( in_array( $param['type'], array( 'assoc', 'flag' ), true ) ) {
                $known[] = $param['name'];
            }
        }
        return array_diff( array_keys( $assoc ), $known );
    }

    private static function parameters( array $spec ): array {
        $local = array();
        foreach ( $spec as $param ) {
            if ( 'generic' !== $param['type'] && isset( $param['name'] ) && '' !== $param['name'] ) {
                $local[] = (string) $param['name'];
            }
        }
        return array_values( array_unique( array_merge( $local, Catalog::global_synopsis_names() ) ) );
    }

    private static function param_desc( array $spec, string $key ): string {
        foreach ( $spec as $param ) {
            if ( 'assoc' === $param['type'] && $param['name'] === $key ) {
                return isset( $param['desc'] ) ? (string) $param['desc'] : '';
            }
        }
        return '';
    }
}
