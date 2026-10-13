<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}













class Approval_Card_Resource extends Base_Resource {

    const URI       = 'ui://easy-mcp-ai/approval';
    const MIME_TYPE = 'text/html;profile=mcp-app';

    
    const I18N_MARKER = '<!-- easy-mcp-ai:i18n -->';

    public function get_uri() {
        return self::URI;
    }

    public function get_name() {
        return 'Approval card';
    }

    public function get_description() {
        return 'Approve or deny a paused destructive tool call in the chat.';
    }

    public function get_mime_type() {
        return self::MIME_TYPE;
    }

    
    public function get_meta() {
        return array(
            'ui' => array(
                'csp'           => new \stdClass(),
                'prefersBorder' => true,
            ),
        );
    }

    public function get_definition() {
        $definition          = parent::get_definition();
        $definition['_meta'] = $this->get_meta();
        return $definition;
    }

    public function read() {
        $file = dirname( __DIR__, 2 ) . '/assets/approval-card.html';
        $html = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plugin-owned static asset.
        if ( ! is_string( $html ) || '' === $html ) {
            throw new \RuntimeException( 'Approval card asset is missing.' );
        }
        $html = str_replace( '<html lang="en">', '<html lang="' . \esc_attr( self::lang() ) . '">', $html );
        return str_replace( self::I18N_MARKER, self::i18n_script( self::labels() ), $html );
    }

    






    public static function labels() {
        return array(
            'connecting'      => __( 'Connecting to the chat host…', 'easy-mcp-ai' ),
            'waiting'         => __( 'Waiting for the tool result…', 'easy-mcp-ai' ),
            'title'           => __( 'Approve this operation?', 'easy-mcp-ai' ),
            /* translators: 1: tool name, 2: expiry date and time (UTC) */
            'meta'            => __( 'Tool: %1$s · expires %2$s UTC', 'easy-mcp-ai' ),
            'working'         => __( 'Working…', 'easy-mcp-ai' ),
            'approve'         => __( 'Approve', 'easy-mcp-ai' ),
            'deny'            => __( 'Deny', 'easy-mcp-ai' ),
            'noSecret'        => __( 'This host did not receive a card secret; use the link in the chat to approve.', 'easy-mcp-ai' ),
            'notRun'          => __( 'Not run', 'easy-mcp-ai' ),
            'decisionRefused' => __( 'The decision was refused.', 'easy-mcp-ai' ),
            'denied'          => __( 'Denied', 'easy-mcp-ai' ),
            'deniedText'      => __( 'Nothing was changed. Your assistant has been told the operation was refused.', 'easy-mcp-ai' ),
            'failedText'      => __( 'The operation was approved but could not be carried out.', 'easy-mcp-ai' ),
            'done'            => __( 'Done', 'easy-mcp-ai' ),
            'doneText'        => __( 'Approved and carried out.', 'easy-mcp-ai' ),
            'hostRefused'     => __( 'The host refused the call.', 'easy-mcp-ai' ),
            'needed'          => __( 'Approval needed', 'easy-mcp-ai' ),
            'noHandshake'     => __( 'This chat host did not complete the card handshake. Use the approval link in the message above, then ask the assistant to run the operation again.', 'easy-mcp-ai' ),
        );
    }

    





    public static function i18n_script( array $labels ) {
        $json = \wp_json_encode( $labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
        return '<script>window.EASY_MCP_AI_CARD_I18N = ' . ( is_string( $json ) ? $json : '{}' ) . ';</script>';
    }

    
    private static function lang() {
        $locale = function_exists( 'determine_locale' ) ? \determine_locale() : \get_locale();
        return str_replace( '_', '-', (string) $locale );
    }
}
