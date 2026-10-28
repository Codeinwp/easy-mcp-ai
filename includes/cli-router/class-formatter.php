<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Formatter {

    const STOP_AFTER_BYTES = Envelope::OUTPUT_MAX_BYTES;

    private $out;

    private $args;

    private $prefix;

    public function __construct( Output $out, array &$assoc_args, ?array $fields = null, ?string $prefix = null ) {
        $format_args = array(
            'format' => 'table',
            'fields' => $fields,
            'field'  => null,
        );

        foreach ( array( 'format', 'fields', 'field' ) as $key ) {
            if ( isset( $assoc_args[ $key ] ) ) {
                $format_args[ $key ] = $assoc_args[ $key ];
                unset( $assoc_args[ $key ] );
            }
        }

        if ( ! is_array( $format_args['fields'] ) ) {
            $format_args['fields'] = explode( ',', (string) $format_args['fields'] );
        }

        $format_args['fields'] = array_map(
            static function ( $field ): string {
                return trim( (string) $field );
            },
            $format_args['fields']
        );

        $this->out    = $out;
        $this->args   = $format_args;
        $this->prefix = $prefix;
    }

    public static function past_cap( Output $out, int $pending = 0 ): bool {
        return $out->stdout_bytes() + $pending > self::STOP_AFTER_BYTES;
    }

    public function display_items( $items, bool $ascii_pre_colorized = false ): void {
        if ( $this->args['field'] ) {
            $this->show_single_field( $items, $this->args['field'] );
            return;
        }

        if ( in_array( $this->args['format'], array( 'csv', 'json', 'table' ), true ) ) {
            $items = $this->resolve_fields( $items );
        }

        $transform = in_array( $this->args['format'], array( 'table', 'csv' ), true );
        if ( $transform && is_array( $items ) ) {
            $this->check_fields( $items );
        }

        $this->format( $items, $transform );
    }

    private function resolve_fields( $items ) {
        $item = is_array( $items ) && ! empty( $items ) ? array_shift( $items ) : false;
        if ( $item && ! empty( $this->args['fields'] ) ) {
            foreach ( $this->args['fields'] as $index => $field ) {
                $this->args['fields'][ $index ] = $this->find_item_key( $item, $field );
            }
            array_unshift( $items, $item );
        }
        return $items;
    }

    private function check_fields( array $items ): void {
        foreach ( $items as $item ) {
            foreach ( $this->args['fields'] as $field ) {
                $this->find_item_key( $item, $field );
            }
        }
    }

    public function display_item( $item, bool $ascii_pre_colorized = false ): void {
        if ( isset( $this->args['field'] ) ) {
            $item  = (object) $item;
            $key   = $this->find_item_key( $item, $this->args['field'] );
            $value = $item->$key;
            if ( in_array( $this->args['format'], array( 'table', 'csv' ), true ) && ( is_object( $value ) || is_array( $value ) ) ) {
                $value = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes.
            }
            Values::print_value(
                $this->out,
                $value,
                array(
                    'format' => $this->args['format'],
                )
            );
        } else {
            $this->show_multiple_fields( $item, $this->args['format'] );
        }
    }

    public function __get( string $key ) {
        return array_key_exists( $key, $this->args ) ? $this->args[ $key ] : null;
    }

    public function transform_item_values_to_json( $item ) {
        foreach ( $this->args['fields'] as $field ) {
            $true_field = $this->find_item_key( $item, $field );
            $value      = is_object( $item ) ? $item->$true_field : $item[ $true_field ];
            if ( is_array( $value ) || is_object( $value ) ) {
                if ( is_object( $item ) ) {
                    $item->$true_field = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes.
                } elseif ( is_array( $item ) ) {
                    $item[ $true_field ] = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes.
                }
            }
        }
        return $item;
    }

    private function format( $items, bool $transform ): void {
        $fields = $this->args['fields'];
        $map    = $transform ? array( $this, 'transform_item_values_to_json' ) : null;

        switch ( $this->args['format'] ) {
            case 'count':
                $this->show_count( $items );
                break;

            case 'ids':
                $this->show_ids( $items );
                break;

            case 'table':
                $this->show_table( $items, $fields, $map );
                break;

            case 'csv':
                Csv::write_each( $this->out, $items, $fields, $map );
                break;

            case 'json':
            case 'yaml':
                if ( 'json' === $this->args['format'] ) {
                    $this->show_json( $items, $fields );
                } elseif ( 'yaml' === $this->args['format'] ) {
                    $this->show_yaml( $items, $fields );
                }
                break;

            default:
                $this->out->error( 'Invalid format: ' . $this->args['format'] );
        }
    }

    private function show_count( $items ): void {
        if ( ! is_array( $items ) ) {
            $items = iterator_to_array( $items );
        }
        $this->out->out( (string) count( $items ) );
    }

    private function show_ids( $items ): void {
        if ( ! is_array( $items ) ) {
            $items = iterator_to_array( $items );
        }
        $separator = '';
        foreach ( $items as $id ) {
            if ( self::past_cap( $this->out ) ) {
                break;
            }
            $this->out->out( $separator . implode( ' ', array( $id ) ) );
            $separator = ' ';
        }
    }

    private function show_json( $items, array $fields ): void {
        $this->out->out( '[' );
        $separator = '';
        foreach ( $items as $item ) {
            if ( self::past_cap( $this->out ) ) {
                break;
            }
            $this->out->out( $separator . json_encode( Values::pick_fields( $item, $fields ), JSON_PARTIAL_OUTPUT_ON_ERROR ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes.
            $separator = ',';
        }
        $this->out->out( ']' );
    }

    private function show_yaml( $items, array $fields ): void {
        $this->out->out( "---\n" );
        foreach ( $items as $item ) {
            if ( self::past_cap( $this->out ) ) {
                break;
            }
            $this->out->out( Yaml_Dumper::sequence_entry( Values::pick_fields( $item, $fields ) ) );
        }
    }

    private function show_single_field( $items, $field ): void {
        $key     = null;
        $is_json = 'json' === $this->args['format'];
        $encoded = array();
        $pending = 1;
        $failed  = false;

        foreach ( $items as $item ) {
            if ( self::past_cap( $this->out, $is_json ? $pending : 0 ) ) {
                break;
            }

            $item = (object) $item;

            if ( null === $key ) {
                $key = $this->find_item_key( $item, $field );
            }

            $value = self::read_property( $item, $key );
            if ( $is_json ) {
                $piece = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes.
                if ( false === $piece ) {
                    $failed = true;
                    $piece  = '';
                }
                $pending  += strlen( $piece ) + ( $encoded ? 1 : 0 );
                $encoded[] = $piece;
            } else {
                Values::print_value(
                    $this->out,
                    $value,
                    array(
                        'format' => $this->args['format'],
                    )
                );
            }
        }

        if ( $is_json ) {
            $this->out->out( $failed ? '' : '[' . implode( ',', $encoded ) . ']' );
        }
    }

    private static function read_property( object $item, $key ) {
        if ( property_exists( $item, (string) $key ) || isset( $item->$key ) || method_exists( $item, '__get' ) ) {
            return $item->$key;
        }
        return null;
    }

    private function find_item_key( $item, $field ): string {
        foreach ( array( $field, $this->prefix . '_' . $field ) as $maybe_key ) {
            if ( ( is_object( $item ) && ( property_exists( $item, (string) $maybe_key ) || isset( $item->$maybe_key ) ) ) || ( is_array( $item ) && array_key_exists( $maybe_key, $item ) ) ) {
                $key = $maybe_key;
                break;
            }
        }

        if ( ! isset( $key ) ) {
            $this->out->error( "Invalid field: $field." );
        }

        return $key;
    }

    private function show_multiple_fields( $data, $format ): void {
        $true_fields = array();
        foreach ( $this->args['fields'] as $field ) {
            $true_fields[] = $this->find_item_key( $data, $field );
        }
        $data = self::keep_fields( $data, $true_fields );

        switch ( $format ) {

            case 'table':
            case 'csv':
                $rows   = $this->assoc_array_to_rows( $data );
                $fields = array( 'Field', 'Value' );
                if ( 'table' === $format ) {
                    $this->show_table( $rows, $fields, null );
                } elseif ( 'csv' === $format ) {
                    Csv::write_each( $this->out, $rows, $fields );
                }
                break;

            case 'yaml':
            case 'json':
                Values::print_value(
                    $this->out,
                    $data,
                    array(
                        'format' => $format,
                    )
                );
                break;

            default:
                $this->out->error( 'Invalid format: ' . $format );

        }
    }

    private static function keep_fields( $data, array $true_fields ) {
        foreach ( $data as $key => $value ) {
            if ( ! in_array( $key, $true_fields, true ) ) {
                if ( is_array( $data ) ) {
                    unset( $data[ $key ] );
                } elseif ( is_object( $data ) ) {
                    unset( $data->$key );
                }
            }
        }
        return $data;
    }

    private function show_table( $items, array $fields, ?callable $map ): void {
        $text = Tabular::row( $fields ) . "\n";

        foreach ( $items as $item ) {
            if ( self::past_cap( $this->out, strlen( $text ) ) ) {
                break;
            }
            if ( null !== $map ) {
                $item = call_user_func( $map, $item );
            }
            $text .= Tabular::body_lines( array_values( Values::pick_fields( $item, $fields ) ) );
        }

        $this->out->out( $text );
    }

    private function assoc_array_to_rows( $fields ): array {
        $rows = array();

        foreach ( $fields as $field => $value ) {

            if ( ! is_string( $value ) ) {
                $value = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes.
            }

            $rows[] = (object) array(
                'Field' => $field,
                'Value' => $value,
            );
        }

        return $rows;
    }
}
