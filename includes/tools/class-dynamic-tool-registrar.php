<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Dynamic_Tool_Registrar {

    public function register_to( Tool_Registry $registry ) {
        $this->register_ability_tools( $registry );
    }

    private function register_ability_tools( Tool_Registry $registry ): void {
        if ( ! \function_exists( 'wp_get_abilities' ) ) {
            return;
        }

        $all_abilities = Excluded_Abilities::without( (array) \wp_get_abilities() );
        Tool_Policy::observe_abilities( $all_abilities );

        $enabled_slugs = (array) \get_option( 'easy_mcp_ai_enabled_abilities', array() );
        if ( empty( $enabled_slugs ) ) {
            return;
        }

        foreach ( $enabled_slugs as $slug ) {
            $slug = (string) $slug;
            if ( ! isset( $all_abilities[ $slug ] ) ) {
                continue;
            }

            $ability = $all_abilities[ $slug ];

            $tool_name = self::build_tool_name( $slug );

            $label = $ability->get_label() ?: $slug;

            $description = $ability->get_description();
            if ( ! empty( $description ) ) {
                $description = "{$description} (ability: {$slug})";
            } else {
                $description = "Execute WordPress Ability: {$label} ({$slug})";
            }

            $policy_note = Ability_Input_Policy::describe( $slug );
            if ( '' !== $policy_note ) {
                $description .= ' ' . $policy_note;
            }

            $raw_input_schema  = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : null;
            $raw_input_schema  = Ability_Input_Policy::strip_schema( $slug, $raw_input_schema );
            $input_needs_wrap  = self::schema_needs_value_wrapper( $raw_input_schema );
            $input_schema      = self::finalize_input_schema( $raw_input_schema );

            $raw_output_schema = method_exists( $ability, 'get_output_schema' ) ? $ability->get_output_schema() : null;
            $output_needs_wrap = self::schema_needs_value_wrapper( $raw_output_schema );
            $output_schema     = self::finalize_output_schema( $raw_output_schema );

            $annotations = self::annotations_of( $ability );

            $captured_ability = $ability;
            $captured_slug    = $slug;

            $tool_config = array(
                'name'        => $tool_name,
                'description' => $description,
                'category'    => 'abilities',
                'capability'  => self::required_capability( $slug ),
                'input_schema' => $input_schema,
                'annotations' => $annotations,
                'accepted_arguments' => $input_needs_wrap ? null : self::closed_argument_names( $raw_input_schema ),
                'ability'            => $slug,
                'executor' => function ( array $arguments ) use ( $captured_ability, $captured_slug, $input_needs_wrap, $output_needs_wrap ) {
                    $ability   = self::current_ability( $captured_slug, $captured_ability );
                    $exec_args = self::ability_input( $ability, $arguments, $input_needs_wrap );
                    $refusal   = self::call_refusal( $ability, $captured_slug, $exec_args );
                    if ( null !== $refusal ) {
                        throw new \RuntimeException( $refusal ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    }

                    $result = $ability->execute( $exec_args );
                    if ( \is_wp_error( $result ) ) {
                        if ( 'ability_invalid_input' === $result->get_error_code() ) {
                            throw \Easy_MCP_AI\MCP\Detailed_Runtime_Error::for_validation( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        }
                        throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    }

                    return $output_needs_wrap ? array( 'value' => $result ) : $result;
                },
                'refusal'  => function ( array $arguments ) use ( $captured_ability, $captured_slug, $input_needs_wrap ): ?string {
                    $ability = self::current_ability( $captured_slug, $captured_ability );
                    return self::call_refusal( $ability, $captured_slug, self::ability_input( $ability, $arguments, $input_needs_wrap ) );
                },
            );
            if ( null !== $output_schema ) {
                $tool_config['output_schema'] = $output_schema;
            }

            if ( method_exists( $ability, 'get_meta_item' ) ) {
                $task_contract = \Easy_MCP_AI\Tasks\Task_Contract::normalize( $ability->get_meta_item( 'task' ), $raw_input_schema );
                if ( null !== $task_contract && ! $input_needs_wrap ) {
                    $tool_config['task'] = $task_contract;
                }
            }

            $registry->register( new Dynamic_Tool( $tool_config ) );
        }
    }

    public static function required_capability( string $slug ): string {
        return 0 === strpos( $slug, \Easy_MCP_AI\Compat\Breakdance_Abilities::PREFIX ) ? \Easy_MCP_AI\Compat\Breakdance_Abilities::CAPABILITY : 'read';
    }

    private static function current_ability( string $slug, object $captured ): object {
        $ability = \function_exists( 'wp_get_ability' ) ? \wp_get_ability( $slug ) : $captured;
        if ( ! $ability ) {
            throw new \RuntimeException( "Ability '{$slug}' is no longer registered." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $ability;
    }

    private static function ability_input( object $ability, array $arguments, bool $needs_wrap ) {
        if ( $needs_wrap ) {
            return array_key_exists( 'value', $arguments ) ? $arguments['value'] : null;
        }
        return method_exists( $ability, 'get_input_schema' ) && ! empty( $ability->get_input_schema() ) ? $arguments : null;
    }

    private static function call_refusal( object $ability, string $slug, $input ): ?string {
        $violation = Ability_Input_Policy::violation( $slug, $input );
        if ( null !== $violation ) {
            return $violation;
        }
        $permission = $ability->check_permissions( $input );
        if ( \is_wp_error( $permission ) ) {
            return 'Permission denied: ' . $permission->get_error_message();
        }
        return false === $permission ? "Permission denied for ability: {$slug}" : null;
    }

    public static function normalize_identifier( $value ) {
        $normalized = strtolower( (string) $value );
        $normalized = preg_replace( '/[^a-z0-9]+/', '_', $normalized );
        return trim( $normalized, '_' );
    }

    public static function build_tool_name( $slug ) {
        $prefix     = 'wp_ability_';
        $normalized = self::normalize_identifier( $slug );
        $tool_name  = $prefix . $normalized;

        if ( strlen( $tool_name ) <= 64 ) {
            return $tool_name;
        }

        $hash      = substr( md5( (string) $slug ), 0, 8 );
        $budget    = 64 - strlen( $prefix ) - 1 - strlen( $hash );
        $truncated = rtrim( substr( $normalized, 0, $budget ), '_' );

        return $prefix . $truncated . '_' . $hash;
    }

    public static function annotations_of( $ability ) {
        $annotations_meta = array();
        if ( method_exists( $ability, 'get_meta_item' ) ) {
            $annotations_meta = (array) $ability->get_meta_item( 'annotations' );
        } elseif ( method_exists( $ability, 'get_annotations' ) ) {
            $annotations_meta = (array) $ability->get_annotations();
        }
        $label = $ability->get_label() ?: $ability->get_name();
        return self::build_annotations( (string) $label, $annotations_meta );
    }

    private static function build_annotations( $label, array $annotations_meta ) {
        $annotations = array(
            'title'         => $label,
            'openWorldHint' => true,
        );
        foreach ( array(
            'readonly'    => 'readOnlyHint',
            'destructive' => 'destructiveHint',
            'idempotent'  => 'idempotentHint',
        ) as $meta_key => $hint_key ) {
            if ( isset( $annotations_meta[ $meta_key ] ) ) {
                $annotations[ $hint_key ] = (bool) $annotations_meta[ $meta_key ];
            }
        }
        return $annotations;
    }

    private static function schema_needs_value_wrapper( $schema ) {
        if ( empty( $schema ) || ! is_array( $schema ) || ! isset( $schema['type'] ) ) {
            return false;
        }
        $type = $schema['type'];
        if ( 'object' === $type ) {
            return false;
        }
        if ( is_array( $type ) && in_array( 'object', $type, true ) ) {
            return false;
        }
        return true;
    }

    private static function closed_argument_names( $schema ): ?array {
        if ( ! is_array( $schema ) || ! array_key_exists( 'additionalProperties', $schema ) || false !== $schema['additionalProperties'] ) {
            return null;
        }
        return array_map( 'strval', array_keys( (array) ( $schema['properties'] ?? array() ) ) );
    }

    private static function finalize_input_schema( $input_schema ) {
        if ( empty( $input_schema ) || ! is_array( $input_schema ) ) {
            return array( 'type' => 'object', 'properties' => new \stdClass() );
        }

        if ( self::schema_needs_value_wrapper( $input_schema ) ) {
            return array(
                'type'       => 'object',
                'properties' => array( 'value' => self::normalize_json_schema( $input_schema ) ),
                'required'   => array( 'value' ),
            );
        }

        $input_schema['type'] = 'object';
        if ( ! isset( $input_schema['properties'] ) ) {
            $input_schema['properties'] = new \stdClass();
        }

        return self::normalize_json_schema( $input_schema );
    }

    private static function finalize_output_schema( $output_schema ) {
        if ( empty( $output_schema ) || ! is_array( $output_schema ) ) {
            return null;
        }

        if ( self::schema_needs_value_wrapper( $output_schema ) ) {
            return array(
                'type'       => 'object',
                'properties' => array( 'value' => self::normalize_json_schema( $output_schema ) ),
                'required'   => array( 'value' ),
            );
        }

        $output_schema['type'] = 'object';
        if ( ! isset( $output_schema['properties'] ) ) {
            $output_schema['properties'] = new \stdClass();
        }

        return self::normalize_json_schema( $output_schema );
    }

    private static function normalize_json_schema( array $schema ) {
        foreach ( array( 'properties', 'patternProperties', '$defs', 'definitions' ) as $map_key ) {
            if ( array_key_exists( $map_key, $schema ) && is_array( $schema[ $map_key ] ) ) {
                if ( empty( $schema[ $map_key ] ) ) {
                    $schema[ $map_key ] = new \stdClass();
                } else {
                    foreach ( $schema[ $map_key ] as $name => $child ) {
                        if ( is_array( $child ) ) {
                            $schema[ $map_key ][ $name ] = self::normalize_json_schema( $child );
                        }
                    }
                }
            }
        }

        foreach ( array( 'allOf', 'anyOf', 'oneOf', 'prefixItems' ) as $list_key ) {
            if ( array_key_exists( $list_key, $schema ) && is_array( $schema[ $list_key ] ) ) {
                foreach ( $schema[ $list_key ] as $i => $child ) {
                    if ( is_array( $child ) ) {
                        $schema[ $list_key ][ $i ] = self::normalize_json_schema( $child );
                    }
                }
            }
        }

        foreach ( array( 'additionalProperties', 'additionalItems', 'contains', 'propertyNames', 'not', 'if', 'then', 'else' ) as $sub_key ) {
            if ( array_key_exists( $sub_key, $schema ) && is_array( $schema[ $sub_key ] ) ) {
                $schema[ $sub_key ] = empty( $schema[ $sub_key ] )
                    ? new \stdClass()
                    : self::normalize_json_schema( $schema[ $sub_key ] );
            }
        }

        if ( array_key_exists( 'items', $schema ) && is_array( $schema['items'] ) ) {
            if ( self::is_list_array( $schema['items'] ) ) {
                foreach ( $schema['items'] as $i => $child ) {
                    if ( is_array( $child ) ) {
                        $schema['items'][ $i ] = self::normalize_json_schema( $child );
                    }
                }
            } else {
                $schema['items'] = self::normalize_json_schema( $schema['items'] );
            }
        }

        return $schema;
    }

    private static function is_list_array( array $arr ) {
        $i = 0;
        foreach ( $arr as $k => $unused ) {
            if ( $k !== $i++ ) {
                return false;
            }
        }
        return true;
    }
}
