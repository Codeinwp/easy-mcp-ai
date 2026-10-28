<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Term_Meta extends Base_Tool {

    use Term_Meta_Auth_Guard;

    public function get_name() {
        return 'wp_update_term_meta';
    }

    public function get_description() {
        return 'Updates a single term meta key/value. Required: `term_id`, `key`, `value`. Optional: `value_format` (auto/plaintext/json, default auto). Returns { updated, unchanged, term_id, taxonomy, key } — `updated` is true when WordPress stored the value, `unchanged` is true when the key already held exactly this value so nothing was written; both false means WordPress did not store it. Scalars are stored as sent; an array or object (repeater rows, a settings blob) is sent as a JSON-encoded string and is stored serialised — wp_get_term_meta returns it structured again. With value_format plaintext a string is stored exactly as sent, backslashes included, even when it looks like JSON; with json the string is decoded as JSON first (any JSON value but null). Authorization is WordPress\'s own: the per-term `edit_term` capability, and for a protected key (leading underscore) core\'s `edit_term_meta` check for that term and key — the key\'s registered auth callback plus any auth_term_meta filters; a protected key nothing has registered needs administrator privileges. Not for Yoast SEO: Yoast keeps category and tag SEO in its own settings and never reads _yoast_wpseo_* term meta, so those keys are refused here, and the refusal names the tool that writes them.';
    }

    public function get_category() {
        return 'taxonomy';
    }

    public function get_required_capability() {
        return 'read';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'term_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the term.',
                ),
                'key'     => array(
                    'type'        => 'string',
                    'description' => 'Meta key to update.',
                ),
                'value'   => array(
                    'description' => 'Meta value to set: a string, number, boolean, array or object (null is refused). Scalars: for booleans use "1" / "0" (WordPress meta storage semantics). Arrays and objects — repeater rows, a settings blob — are stored serialised and wp_get_term_meta returns them structured; JSON-encoded text starting with "[" or "{" is decoded the same way. Consequence: with the default value_format a string that is itself valid JSON for an array or object cannot be stored as literal text; send value_format plaintext for that.',
                ),
                'value_format' => array(
                    'type'        => 'string',
                    'description' => 'How `value` is read. auto (default): as described for `value`. plaintext: a string is stored exactly as sent, backslashes included, and never decoded; arrays and objects are refused. json: `value` is JSON text and is decoded first — any JSON value except null, so "[1]" stores an array and "\\"[1]\\"" the text [1]; invalid JSON is refused.',
                    'enum'        => array( 'auto', 'plaintext', 'json' ),
                    'default'     => 'auto',
                ),
            ),
            'required'   => array( 'term_id', 'key', 'value' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'term_id', 'key' ) );
        $this->refuse_missing_value( $arguments );
        $format  = $this->value_format_arg( $arguments );
        $term_id = $this->parse_required_id( $arguments['term_id'], 'term_id' );
        $key     = $this->meta_key_arg( $arguments );
        $term    = $this->editable_term( $term_id );
        $this->assert_term_meta_key_allowed( $key, $term, 'edit_term_meta' );
        $this->refuse_blocked_key( $key );

        $to_store = $this->value_to_store( $arguments['value'], $format );
        $result   = $this->write_term_meta( $term_id, $key, $to_store );
        if ( is_wp_error( $result ) ) {
            throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $unchanged = ( false === $result ) && $this->already_stored( $term_id, $key, wp_unslash( $to_store ), (string) $term->taxonomy );

        return array(
            'updated'   => ( false !== $result ),
            'unchanged' => $unchanged,
            'term_id'   => $term_id,
            'taxonomy'  => $term->taxonomy,
            'key'       => $key,
        );
    }

    private function refuse_missing_value( array $arguments ): void {
        if ( ! array_key_exists( 'value', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameter: value' );
        }
        if ( null === $arguments['value'] ) {
            throw new \InvalidArgumentException( 'Parameter value cannot be null. Use wp_delete_term_meta to remove the key.' );
        }
    }

    private function value_format_arg( array $arguments ): string {
        $format = 'auto';
        if ( array_key_exists( 'value_format', $arguments ) && null !== $arguments['value_format'] ) {
            $format = is_string( $arguments['value_format'] ) ? strtolower( $arguments['value_format'] ) : '';
            $this->refuse_unless_one_of( 'value_format', $format, array( 'auto', 'plaintext', 'json' ), $arguments['value_format'], 'auto, plaintext or json' );
        }
        return $format;
    }

    private function meta_key_arg( array $arguments ): string {
        $key = sanitize_text_field( (string) $arguments['key'] );
        if ( '' === $key ) {
            throw new \InvalidArgumentException( 'Key cannot be empty.' );
        }

        if ( 0 === strpos( $key, '_yoast_wpseo_' ) && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
            throw new \InvalidArgumentException( $this->yoast_term_key_refusal( $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $key;
    }

    private function editable_term( int $term_id ): object {
        $term = get_term( $term_id );
        if ( ! $term || is_wp_error( $term ) ) {
            throw new \InvalidArgumentException( 'Term not found.' );
        }
        $tax_obj = get_taxonomy( $term->taxonomy );
        if ( ! $tax_obj ) {
            throw new \InvalidArgumentException( 'Invalid taxonomy.' );
        }
        if ( ! current_user_can( 'edit_term', $term_id ) ) {
            throw new \RuntimeException( sprintf( 'Insufficient capability for taxonomy %s: WordPress does not grant edit_term for term %d to the current user.', $term->taxonomy, $term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $term;
    }

    private function refuse_blocked_key( string $key ): void {
        $blocked_patterns = apply_filters( 'easy_mcp_ai_term_meta_blocked_key_patterns', array() );
        foreach ( $blocked_patterns as $pattern ) {
            if ( fnmatch( $pattern, $key ) ) {
                throw new \RuntimeException( 'This meta key cannot be modified via MCP.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    protected function write_term_meta( int $term_id, string $key, $value ) {
        return update_term_meta( $term_id, $key, $value );
    }

    private function value_to_store( $value, string $format ) {
        if ( 'plaintext' === $format ) {
            if ( is_array( $value ) || is_object( $value ) ) {
                throw new \InvalidArgumentException( 'value_format plaintext stores text as sent; an array or object cannot be plain text. Send it with value_format auto or json.' );
            }
            return wp_slash( $value );
        }
        if ( 'json' === $format ) {
            if ( is_string( $value ) ) {
                $decoded = json_decode( $value, true );
                if ( null === $decoded ) {
                    throw new \InvalidArgumentException( 'value_format json: value is not valid JSON, or is JSON null (there is no null meta value to store; use wp_delete_term_meta to remove the key).' );
                }
                $value = $decoded;
            } elseif ( is_object( $value ) ) {
                $value = json_decode( wp_json_encode( $value ), true );
            }
            return wp_slash( $value );
        }
        return $this->normalize_value( $value );
    }

    private function already_stored( int $term_id, string $key, $value, string $taxonomy ): bool {
        $rows = get_term_meta( $term_id, $key, false );
        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return false;
        }
        $intended = self::stored_form( sanitize_meta( $key, $value, 'term', $taxonomy ) );
        foreach ( $rows as $row ) {
            if ( self::stored_form( $row ) !== $intended ) {
                return false;
            }
        }
        return true;
    }

    private static function stored_form( $value ): string {
        if ( is_array( $value ) || is_object( $value ) ) {
            return serialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- comparison with maybe_serialize()'s output.
        }
        if ( is_bool( $value ) ) {
            return $value ? '1' : '';
        }
        return null === $value ? '' : (string) $value;
    }

    private function yoast_term_key_refusal( $key ) {
        $message = sprintf( 'Yoast SEO does not read term meta "%s": it keeps category and tag SEO in its own settings, so this write would change nothing on the site.', $key );
        $enabled = get_option( 'easy_mcp_ai_enabled_plugin_groups', array() );
        if ( is_array( $enabled ) && in_array( 'yoast-seo', $enabled, true ) ) {
            return $message . ' Use wp_yoast_update_term_seo.';
        }
        return $message . ' Enable the Yoast SEO integration under Easy MCP AI → Tools → Plugins to get wp_yoast_update_term_seo.';
    }

    private function normalize_value( $value ) {
        if ( is_object( $value ) ) {
            $value = json_decode( wp_json_encode( $value ), true );
        }
        if ( is_array( $value ) ) {
            return $value;
        }
        if ( ! is_string( $value ) ) {
            return $value;
        }
        $lead = ltrim( $value );
        if ( '' === $lead || ( '[' !== $lead[0] && '{' !== $lead[0] ) ) {
            return $value;
        }
        $decoded = json_decode( $value, true );
        return is_array( $decoded ) ? $decoded : $value;
    }
}
