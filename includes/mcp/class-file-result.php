<?php
namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class File_Result {

    private $data;

    private $resource;

    public function __construct( array $data, string $uri, string $mime_type, string $bytes ) {
        $this->data     = $data;
        $this->resource = array(
            'uri'      => $uri,
            'mimeType' => $mime_type,
            'blob'     => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- MCP carries binary resource contents as base64.
        );
    }

    public function get_data(): array {
        return $this->data;
    }

    public function content_block(): array {
        return array(
            'type'     => 'resource',
            'resource' => $this->resource,
        );
    }
}
