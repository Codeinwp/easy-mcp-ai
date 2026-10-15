<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Handlers\Term_Meta_Handler;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! trait_exists( '\\Easy_MCP_AI\\Tools\\Taxonomy\\Term_Meta_Auth_Guard', false ) ) {
    require_once dirname( __DIR__, 2 ) . '/tools/taxonomy/trait-term-meta-auth-guard.php';
}









































final class Term_Meta_Adapter extends Meta_Command {

    use Planned_Run;

    use \Easy_MCP_AI\Tools\Taxonomy\Term_Meta_Auth_Guard;

    
    protected $meta_type = 'term';

    
    const NOT_FOUND = 'Could not find the term with ID %s.';

    const KEY_REWRITTEN = "wp_cli_run cannot pass the term meta key '%s' on unchanged: the term meta tools clean a key with sanitize_text_field(), which turns it into '%s', so they would address another key. Pass the key as `term meta list` prints it.";

    const WITHHELD = '%d protected term meta %s left out: reading protected keys needs the manage_options capability.';

    
    const TOOLS = array(
        'list'   => array( 'wp_get_term_meta' ),
        'get'    => array( 'wp_get_term_meta' ),
        'pluck'  => array( 'wp_get_term_meta' ),
        'delete' => array( 'wp_delete_term_meta' ),
        'update' => array( 'wp_get_term_meta', 'wp_update_term_meta' ),
        'patch'  => array( 'wp_get_term_meta', 'wp_update_term_meta' ),
    );

    
    
    

    





    public function preflight_id( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        $at   = isset( $inv->leaf['name'] ) && 'patch' === $inv->leaf['name'] ? 1 : 0;
        Ids::require_digits( $ctx, isset( $args[ $at ] ) ? $args[ $at ] : '', 'a term ID' );
    }

    









    public function plan_patch( Invocation $inv ): array {
        $args = array_values( $inv->args );
        if ( ! Ids::is_digits( isset( $args[1] ) ? $args[1] : '' ) ) {
            return array();
        }
        return $this->planned_calls(
            $inv,
            'patch',
            self::TOOLS['patch'],
            array( 'wp_get_term_meta' => array( '\\Easy_MCP_AI\\Tools\\Taxonomy\\Get_Term_Meta', 'taxonomy/class-get-term-meta.php' ) )
        );
    }

    






    protected function check_object_id( $object_id, string $subcommand ) {
        $tools = $this->required_tools( $subcommand );
        foreach ( $tools as $tool ) {
            Fetchers::require_tool( $this->ctx, $this->inv, $tool );
        }

        $term = Term_Meta_Handler::term( $object_id );
        if ( null === $term ) {
            $this->ctx->out->error( sprintf( self::NOT_FOUND, $object_id ) );
        }
        $this->require_term_caps( $term, $tools, $subcommand );
        if ( in_array( $subcommand, array( 'update', 'patch' ), true ) ) {
            $args = array_values( $this->inv->args );
            $key  = (string) ( 'patch' === $subcommand ? ( $args[2] ?? '' ) : ( $args[1] ?? '' ) );
            $this->authorize_write( $key, $term );
        }
        return (int) $term->term_id;
    }

    





    private function required_tools( string $subcommand ): array {
        $tools = isset( self::TOOLS[ $subcommand ] ) ? self::TOOLS[ $subcommand ] : array( 'wp_get_term_meta' );
        if ( 'delete' === $subcommand && Read_Value::flag( $this->inv->assoc, 'all' ) ) {
            $tools[] = 'wp_get_term_meta';
        }
        return $tools;
    }

    






    private function require_term_caps( object $term, array $tools, string $subcommand ): void {
        $term_id = (int) $term->term_id;
        if ( in_array( 'wp_get_term_meta', $tools, true ) ) {
            $tax = \get_taxonomy( $term->taxonomy );
            if ( ! $tax || ! \current_user_can( $tax->cap->edit_terms ) ) {
                $this->ctx->out->error( sprintf( 'Insufficient capability for taxonomy %s.', $term->taxonomy ) );
            }
        }
        if ( in_array( $subcommand, array( 'update', 'patch', 'delete' ), true ) && ! \current_user_can( 'edit_term', $term_id ) ) {
            $this->ctx->out->error( sprintf( 'Insufficient capability for taxonomy %s: WordPress does not grant edit_term for term %d to the current user.', $term->taxonomy, $term_id ) );
        }
    }

    





    private function authorize_write( string $key, object $term ): void {
        $refusal = Term_Meta_Handler::integration_refusal( $key );
        if ( null === $refusal ) {
            try {
                $this->assert_term_meta_key_allowed( $key, $term, 'edit_term_meta' );
            } catch ( \RuntimeException $e ) {
                $refusal = $e->getMessage();
            }
        }
        if ( null !== $refusal ) {
            $this->ctx->out->error( $refusal );
        }
    }

    






    protected function get_metadata( $object_id, string $meta_key = '', bool $single = false ) {
        $arguments = array( 'term_id' => (int) $object_id );
        if ( '' !== $meta_key ) {
            $arguments['key']    = $meta_key;
            $arguments['single'] = $single;
        }
        $result = $this->delegate( 'wp_get_term_meta', $arguments );
        if ( ! $result['ok'] ) {
            $this->ctx->out->error( (string) $result['error'] );
        }
        $payload = $result['payload'];
        if ( '' === $meta_key ) {
            $this->warn_withheld( (int) ( $payload['withheld_count'] ?? 0 ) );
            return isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : array();
        }
        if ( array_key_exists( 'value', $payload ) ) {
            return $payload['value'];
        }
        return $single ? '' : array();
    }

    
    private function warn_withheld( int $withheld ): void {
        if ( $withheld > 0 ) {
            $this->ctx->out->warning( sprintf( self::WITHHELD, $withheld, 1 === $withheld ? 'key was' : 'keys were' ) );
        }
    }

    






    protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool {
        throw new \LogicException( 'term meta add is Handlers\\Term_Meta_Handler.' );
    }

    





    protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool {
        $value     = function_exists( 'wp_unslash' ) ? \wp_unslash( $meta_value ) : $meta_value;
        $arguments = array(
            'term_id' => (int) $object_id,
            'key'     => $meta_key,
        );
        if ( is_string( $value ) ) {
            $arguments['value']        = $value;
            $arguments['value_format'] = 'plaintext';
        } else {
            $arguments['value']        = (string) json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- decoded again by the tool's value_format json.
            $arguments['value_format'] = 'json';
        }
        $result = $this->delegate( 'wp_update_term_meta', $arguments );
        if ( ! $result['ok'] ) {
            $this->ctx->out->error( (string) $result['error'] );
        }
        return ! empty( $result['payload']['updated'] );
    }

    







    protected function delete_metadata( $object_id, string $meta_key, string $meta_value = '' ): bool {
        $arguments = array(
            'term_id' => (int) $object_id,
            'key'     => $meta_key,
        );
        if ( '' !== $meta_value ) {
            $arguments['value'] = $meta_value;
        }
        $result = $this->delegate( 'wp_delete_term_meta', $arguments );
        if ( Batch::is_rate_limited( $result ) && Read_Value::flag( $this->inv->assoc, 'all' ) ) {
            return false; 
        }
        if ( ! $result['ok'] ) {
            
            
            if ( Read_Value::flag( $this->inv->assoc, 'all' ) ) {
                $this->ctx->out->warning( (string) $result['error'] );
                return false;
            }
            $this->ctx->out->error( (string) $result['error'] );
        }
        return ! empty( $result['payload']['deleted'] );
    }

    



    protected function protect_key( string $key, bool $write ): ?string {
        $refusal = parent::protect_key( $key, $write );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $sub = null !== $this->inv && isset( $this->inv->leaf['name'] ) ? (string) $this->inv->leaf['name'] : '';
        if ( 'list' !== $sub && '' !== $key && function_exists( 'sanitize_text_field' ) ) {
            $clean = \sanitize_text_field( $key );
            if ( $clean !== $key ) {
                return sprintf( self::KEY_REWRITTEN, $key, $clean );
            }
        }
        return null;
    }
}
