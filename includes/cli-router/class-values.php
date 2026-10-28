<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Values {

    public static function print_value( Output $out, $value, array $assoc_args = array() ): void {
        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : null;
        if ( 'json' === $format ) {
            $value = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes; wp_json_encode() changes the bytes.
        } elseif ( 'yaml' === $format ) {
            $value = Yaml_Dumper::dump( $value );
        } elseif ( is_array( $value ) || is_object( $value ) ) {
            $value = var_export( $value, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- WP-CLI's own output for an array or object value.
        }

        $out->out( $value . "\n" );
    }

    public static function report_batch( Output $out, string $noun, string $verb, int $total, int $successes, int $failures, ?int $skips = null ): void {
        $plural_noun           = $noun . 's';
        $past_tense_verb       = self::past_tense_verb( $verb );
        $past_tense_verb_upper = ucfirst( $past_tense_verb );
        if ( $failures ) {
            $failed_skipped_message = null === $skips ? '' : " ({$failures} failed" . ( $skips ? ", {$skips} skipped" : '' ) . ')';
            if ( $successes ) {
                $out->error( "Only {$past_tense_verb} {$successes} of {$total} {$plural_noun}{$failed_skipped_message}." );
            } else {
                $out->error( "No {$plural_noun} {$past_tense_verb}{$failed_skipped_message}." );
            }
        } else {
            $skipped_message = $skips ? " ({$skips} skipped)" : '';
            if ( $successes || $skips ) {
                $out->success( "{$past_tense_verb_upper} {$successes} of {$total} {$plural_noun}{$skipped_message}." );
            } else {
                $message = $total > 1 ? ucfirst( $plural_noun ) : ucfirst( $noun );
                $out->success( "{$message} already {$past_tense_verb}." );
            }
        }
    }

    public static function past_tense_verb( string $verb ): string {
        static $irregular = array(
            'reset' => 'reset',
        );
        if ( isset( $irregular[ $verb ] ) ) {
            return $irregular[ $verb ];
        }
        $last = substr( $verb, -1 );
        if ( 'e' === $last ) {
            $verb = substr( $verb, 0, -1 );
        } elseif ( 'y' === $last && ! preg_match( '/[aeiou]y$/', $verb ) ) {
            $verb = substr( $verb, 0, -1 ) . 'i';
        } elseif ( preg_match( '/^[^aeiou]*[aeiou][^aeiouhwxy]$/', $verb ) ) {
            $verb .= $last;
        }
        return $verb . 'ed';
    }

    public static function pick_fields( $item, array $fields ): array {
        $values = array();

        if ( is_object( $item ) ) {
            foreach ( $fields as $field ) {
                $values[ $field ] = isset( $item->$field ) ? $item->$field : null;
            }
        } else {
            foreach ( $fields as $field ) {
                $values[ $field ] = isset( $item[ $field ] ) ? $item[ $field ] : null;
            }
        }

        return $values;
    }
}
