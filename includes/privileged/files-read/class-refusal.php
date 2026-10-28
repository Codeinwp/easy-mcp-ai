<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Refusal extends \RuntimeException {

    public static function for_path( string $path ): self {
        $shown = ( '' !== $path && strlen( $path ) <= 200 && ! preg_match( '/[^\x20-\x7e]/', $path ) ) ? '"' . $path . '"' : 'The path';
        return new self( $shown . ' was not found or is not allowed. Paths are relative to wp-content (for example "themes/twentytwentyfive/functions.php"); dot-files, uploads, mu-plugins, backup and cache folders, this plugin and wp-config files are never read, and only source and text files are.' );
    }
}
