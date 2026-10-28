<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Media_Adapter extends Command_Handler {

    const TOOL = 'wp_upload_media_from_url';

    const LOCAL_FILE       = "wp_cli_run imports media only from URLs: '%s' is a path on the server, and reading server files is not available through wp_cli_run. Pass an http(s) URL.";
    const FEATURED_NO_POST = 'wp_cli_run refuses --featured_image without --post_id: the featured image is set on that post, and WP-CLI would import the file and set nothing.';
    const POST_MISSING     = 'wp_cli_run refuses --post_id=%1$s: no post has that ID, and WP-CLI would import the file unattached. Nothing was imported.';

    public function preflight_import( Invocation $inv, Context $ctx ): void {
        foreach ( $inv->args as $file ) {
            if ( '' === (string) self::host( (string) $file ) ) {
                $ctx->out->error( sprintf( self::LOCAL_FILE, (string) $file ) );
            }
        }
        $porcelain = Read_Value::flag( $inv->assoc, 'porcelain' );
        if ( is_string( $porcelain ) && ! in_array( $porcelain, array( 'url' ), true ) ) {
            $ctx->out->error( sprintf( 'Invalid value for <porcelain>: %s. Expected flag or \'url\'.', $porcelain ) );
        }
        if ( Read_Value::flag( $inv->assoc, 'featured_image' ) && ! isset( $inv->assoc['post_id'] ) ) {
            $ctx->out->error( self::FEATURED_NO_POST );
        }
    }

    public function plan_import( Invocation $inv ): array {
        $plan = array();
        foreach ( $inv->args as $file ) {
            $plan[] = array(
                'tool'      => self::TOOL,
                'arguments' => self::arguments( $inv, (string) $file ),
            );
        }
        return $plan;
    }

    public function import( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $files      = array_values( array_map( 'strval', $inv->args ) );
        $noun       = ( Read_Value::flag( $assoc_args, 'featured_image' ) || ! empty( $assoc_args['alt'] ) ) ? 'image' : 'item';
        $porcelain  = self::porcelain_mode( $assoc_args );
        $post_id    = isset( $assoc_args['post_id'] ) ? (string) $assoc_args['post_id'] : null;

        Fetchers::require_tool( $ctx, $inv, self::TOOL );
        if ( null !== $post_id && ! $this->post_exists( $post_id ) ) {
            $ctx->out->error( sprintf( self::POST_MISSING, $post_id ) );
        }

        $batch = new Batch( $inv, $ctx, $noun, 'import' );
        $batch->run(
            $files,
            function ( $file, Batch $batch ) use ( $inv, $ctx, $porcelain, $post_id ) {
                return $this->import_one( $inv, $ctx, $batch, (string) $file, $porcelain, $post_id );
            },
            static function ( $file ) {
                return 'file ' . (string) $file;
            }
        );

        if ( null === $porcelain ) {
            $batch->report();
        } elseif ( $batch->result()['failures'] ) {
            $ctx->out->halt( 1 );
        }
    }

    private function import_one( Invocation $inv, Context $ctx, Batch $batch, string $file, ?string $porcelain, ?string $post_id ): string {
        $result = $batch->delegate( self::TOOL, self::arguments( $inv, $file ) );
        if ( Batch::is_rate_limited( $result ) ) {
            return Batch::FAILURE;
        }
        if ( ! $result['ok'] ) {
            $ctx->out->warning( sprintf( "Unable to import file '%s'. Reason: %s", $file, (string) $result['error'] ) );
            return Batch::FAILURE;
        }
        $ctx->mark_changed();
        $id = (int) ( $result['payload']['id'] ?? 0 );

        if ( 'url' === $porcelain ) {
            $ctx->out->line( $this->original_url( $id, (string) ( $result['payload']['source_url'] ?? '' ) ) );
        } elseif ( 'id' === $porcelain ) {
            $ctx->out->line( (string) $id );
        } else {
            $ctx->out->log( sprintf( "Imported file '%s' as attachment ID %d%s.", $file, $id, self::imported_text( $inv->assoc, $file, $post_id ) ) );
        }
        return Batch::SUCCESS;
    }

    private static function porcelain_mode( array $assoc ): ?string {
        $porcelain = Read_Value::flag( $assoc, 'porcelain' );
        if ( ! $porcelain ) {
            return null;
        }
        return 'url' === strtolower( (string) $porcelain ) ? 'url' : 'id';
    }

    private static function imported_text( array $assoc, string $file, ?string $post_id ): string {
        $text = '';
        if ( ! empty( $assoc['file_name'] ) ) {
            $text .= ' with file name ' . self::import_name( $file, (string) $assoc['file_name'] );
        }
        if ( null !== $post_id ) {
            $text = " and attached to post {$post_id}";
            if ( Read_Value::flag( $assoc, 'featured_image' ) ) {
                $text .= ' as featured image';
            }
        }
        return $text;
    }

    public static function arguments( Invocation $inv, string $file ): array {
        $assoc = $inv->assoc;
        $args  = array( 'url' => $file );
        if ( isset( $assoc['post_id'] ) ) {
            $args['post_id'] = (int) $assoc['post_id'];
        }
        $args += self::text_arguments( $assoc );
        if ( ! empty( $assoc['file_name'] ) && is_string( $assoc['file_name'] ) ) {
            $args['filename'] = self::import_name( $file, $assoc['file_name'] );
        }
        if ( Read_Value::flag( $assoc, 'featured_image' ) ) {
            $args['featured_image'] = true;
        }
        return $args;
    }

    private static function text_arguments( array $assoc ): array {
        $args = array();
        foreach ( array( 'title' => 'title', 'caption' => 'caption', 'desc' => 'description', 'post_name' => 'slug' ) as $flag => $key ) {
            if ( isset( $assoc[ $flag ] ) && is_string( $assoc[ $flag ] ) && '' !== $assoc[ $flag ] ) {
                $args[ $key ] = $assoc[ $flag ];
            }
        }
        if ( ! empty( $assoc['alt'] ) && is_string( $assoc['alt'] ) ) {
            $args['alt_text'] = $assoc['alt'];
        }
        return $args;
    }

    public static function import_name( string $file, string $slug ): string {
        $name = (string) strtok( self::basename( $file ), '?' );
        return $slug . '.' . pathinfo( $name, PATHINFO_EXTENSION );
    }

    private static function basename( string $path ): string {
        return urldecode( basename( str_replace( array( '%2F', '%5C' ), '/', urlencode( $path ) ) ) );
    }

    private static function host( string $file ): string {
        $host = function_exists( 'wp_parse_url' ) ? \wp_parse_url( $file, PHP_URL_HOST ) : parse_url( $file, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- fallback, as WP-CLI.
        return is_string( $host ) ? $host : '';
    }

    protected function call( string $name, array $args, callable $real ) {
        return $real();
    }

    protected function post_exists( string $post_id ): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array( $post_id ),
            static function () use ( $post_id ) {
                return null !== \get_post( $post_id );
            }
        );
    }

    protected function original_url( int $id, string $fallback ): string {
        return (string) $this->call(
            __FUNCTION__,
            array( $id, $fallback ),
            static function () use ( $id, $fallback ) {
                if ( function_exists( 'wp_get_original_image_url' ) ) {
                    $url = \wp_get_original_image_url( $id );
                    if ( false !== $url ) {
                        return $url;
                    }
                }
                $url = \wp_get_attachment_url( $id );
                return false !== $url ? $url : $fallback;
            }
        );
    }
}
