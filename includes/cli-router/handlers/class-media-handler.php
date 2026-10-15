<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Confirm;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Router;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










































class Media_Handler extends Command_Handler {

    
    const BULK = 25;

    
    const NOT_CAPTURED_FILES = 'Image files: regenerating or rotating an image rewrites and deletes files in the uploads folder, which change history never records; it records only the post meta WordPress updates for the attachment, such as _wp_attachment_metadata, so the old files cannot be restored from it.';

    const OVER_CAP      = "wp_cli_run runs at most %d items in one 'wp %s' command, and this one has %d. Run it for explicit attachment IDs in smaller groups.";
    const NOT_EDITABLE  = 'Sorry, you are not allowed to change %s: wp_cli_run needs the edit_post capability for that attachment.';
    const NOT_FOUND_IDS = 'No %1$s attachment has ID %2$s, so wp_cli_run leaves it out.';

    
    
    

    








    public function preflight_regenerate( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        Ids::require_all( $ctx, $args, 'an attachment ID' );

        $image_size = (string) Read_Value::flag( $inv->assoc, 'image_size', '' );
        if ( '' !== $image_size && ! in_array( $image_size, $this->intermediate_sizes(), true ) ) {
            $ctx->out->error( sprintf( 'Unknown image size "%s".', $image_size ) );
        }

        if ( empty( $args ) ) {
            if ( '' !== $image_size ) {
                Confirm::ask( $inv, $ctx, sprintf( 'Do you really want to regenerate the "%s" image size for all images?', $image_size ) );
            } else {
                Confirm::ask( $inv, $ctx, 'Do you really want to regenerate all images?' );
            }
        }
    }

    






    public function preflight_fix_orientation( Invocation $inv, Context $ctx ): void {
        if ( ! $this->is_dry_run( $inv ) ) {
            Ids::require_all( $ctx, array_values( $inv->args ), 'an attachment ID' );
        }
    }

    
    public function is_dry_run( Invocation $inv ): bool {
        return (bool) Read_Value::flag( $inv->assoc, 'dry-run' );
    }

    
    
    

    
    public function image_size( Invocation $inv, Context $ctx ): void {
        $assoc_args = array_merge(
            array( 'fields' => 'name,width,height,crop,ratio' ),
            $inv->assoc
        );

        $sizes = array();
        foreach ( $this->registered_subsizes() as $size => $size_args ) {
            $crop    = filter_var( $size_args['crop'], FILTER_VALIDATE_BOOLEAN );
            $sizes[] = array(
                'name'   => $size,
                'width'  => $size_args['width'],
                'height' => $size_args['height'],
                'crop'   => empty( $crop ) || is_array( $size_args['crop'] ) ? 'soft' : 'hard',
                'ratio'  => empty( $crop ) || is_array( $size_args['crop'] ) ? 'N/A' : self::ratio( (int) $size_args['width'], (int) $size_args['height'] ),
            );
        }

        usort(
            $sizes,
            static function ( $a, $b ) {
                if ( $a['width'] === $b['width'] ) {
                    return 0;
                }
                return ( $a['width'] < $b['width'] ) ? 1 : -1;
            }
        );
        array_unshift(
            $sizes,
            array(
                'name'   => 'full',
                'width'  => '',
                'height' => '',
                'crop'   => 'N/A',
                'ratio'  => 'N/A',
            )
        );

        
        $format_args = array(
            'format' => $assoc_args['format'],
            'fields' => explode( ',', (string) $assoc_args['fields'] ),
        );
        $this->formatter( $ctx, $format_args )->display_items( $sizes );
    }

    
    
    

    



    public function regenerate( Invocation $inv, Context $ctx ): void {
        $args       = array_values( $inv->args );
        $assoc_args = array_merge( array( 'image_size' => '' ), $inv->assoc );

        
        
        $image_size = $assoc_args['image_size'];

        $skip_delete  = Read_Value::flag( $assoc_args, 'skip-delete' );
        $only_missing = Read_Value::flag( $assoc_args, 'only-missing' );
        if ( $only_missing ) {
            $skip_delete = true;
        }
        $delete_unknown = Read_Value::flag( $assoc_args, 'delete-unknown' );
        if ( $delete_unknown ) {
            $skip_delete = false;
        }

        
        $images = $this->find_images( $args, array( 'image', 'application/pdf' ), 'image or PDF', $ctx );
        $count  = count( $images );

        if ( ! $count ) {
            $ctx->out->warning( 'No images found.' );
            if ( $args ) {
                $ctx->fail_missing_target( 'No images found.' );
            }
            return;
        }
        $this->enforce_cap( $inv, $ctx, $count );

        $ctx->not_captured( self::NOT_CAPTURED_FILES );
        $ctx->out->log( sprintf( 'Found %1$d %2$s to regenerate.', $count, 1 === $count ? 'image' : 'images' ) );

        $successes = 0;
        $errors    = 0;
        $skips     = 0;
        $number    = 0;
        if ( $image_size ) {
            $this->size_filters( (string) $image_size, true );
        }
        try {
            foreach ( $images as $post_id ) {
                ++$number;
                $this->process_regeneration( $ctx, (int) $post_id, (bool) $skip_delete, (bool) $only_missing, (bool) $delete_unknown, (string) $image_size, $number . '/' . $count, $successes, $errors, $skips );
            }
        } finally {
            if ( $image_size ) {
                $this->size_filters( (string) $image_size, false );
            }
        }

        Values::report_batch( $ctx->out, 'image', 'regenerate', $count, $successes, $errors, $skips );
    }

    




    public function describe_regenerate( Invocation $inv ): array {
        $image_size = (string) Read_Value::flag( $inv->assoc, 'image_size', '' );
        $what       = '' !== $image_size ? sprintf( 'its "%s" thumbnail is regenerated', $image_size ) : 'its thumbnails are regenerated';
        if ( Read_Value::flag( $inv->assoc, 'delete-unknown' ) ) {
            $what = 'the files of unregistered image sizes are deleted';
        } elseif ( ! Read_Value::flag( $inv->assoc, 'skip-delete' ) && ! Read_Value::flag( $inv->assoc, 'only-missing' ) ) {
            $what .= ' and the old ones deleted';
        }
        return $this->describe_images( array_values( $inv->args ), array( 'image', 'application/pdf' ), $what );
    }

    




    private function process_regeneration( Context $ctx, int $id, bool $skip_delete, bool $only_missing, bool $delete_unknown, string $image_size, string $progress, int &$successes, int &$errors, int &$skips ): void {
        $att_desc       = $this->att_desc( $id );
        $thumbnail_desc = $image_size ? sprintf( '"%s" thumbnail', $image_size ) : 'thumbnail';

        $fullsizepath = $this->editable_original( $ctx, $id, $att_desc, true );
        if ( null === $fullsizepath ) {
            ++$errors;
            return;
        }

        $is_pdf        = 'application/pdf' === $this->mime_type( $id );
        $original_meta = $this->attachment_metadata( $id );

        if ( $delete_unknown ) {
            $this->delete_unknown_image_sizes( $id, $fullsizepath, $original_meta );
            $ctx->mark_changed();
            $ctx->out->log( "$progress Deleted unknown image sizes for $att_desc." );
            ++$successes;
            return;
        }

        $skip_it            = false;
        $needs_regeneration = $this->needs_regeneration( $ctx, $id, $fullsizepath, $is_pdf, $image_size, $skip_delete, $original_meta, $skip_it );

        if ( $skip_it ) {
            $ctx->out->log( "$progress Skipped $thumbnail_desc regeneration for $att_desc." );
            ++$skips;
            return;
        }

        if ( $only_missing && ! $needs_regeneration ) {
            $ctx->out->log( "$progress No $thumbnail_desc regeneration needed for $att_desc." );
            ++$successes;
            return;
        }

        $metadata = $this->generate_metadata( $id, $fullsizepath );
        $ctx->mark_changed();

        $metadata_error = self::metadata_error( $metadata, $is_pdf, $image_size );
        if ( null !== $metadata_error ) {
            $ctx->out->warning( sprintf( $metadata_error, $id ) );
            $ctx->out->log( "$progress Couldn't regenerate thumbnails for $att_desc." );
            ++$errors;
            return;
        }

        if ( $image_size ) {
            if ( $this->update_attachment_metadata_for_image_size( $id, $metadata, $image_size, $original_meta ) ) {
                $ctx->out->log( "$progress Regenerated $thumbnail_desc for $att_desc." );
            } else {
                $ctx->out->log( "$progress No $thumbnail_desc regeneration needed for $att_desc." );
            }
        } else {
            $this->update_metadata( $id, $metadata );
            $ctx->out->log( "$progress Regenerated thumbnails for $att_desc." );
        }
        ++$successes;
    }

    





    private function needs_regeneration( Context $ctx, int $att_id, string $fullsizepath, bool $is_pdf, string $image_size, bool $skip_delete, $metadata, bool &$skip_it ): bool {
        $skip_it = false;

        $image_sizes = $this->sizes_for_attachment( $fullsizepath, $is_pdf, $metadata, $att_id );

        if ( isset( $image_sizes['error'] ) && 'image_no_editor' === $image_sizes['error'] ) {
            if ( ! $is_pdf && is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
                $ctx->out->warning( sprintf( '%s (ID %d)', (string) $image_sizes['message'], $att_id ) );
            }
            $skip_it = true;
            return false;
        }

        $metadata = self::with_sizes( $metadata );

        if ( ! $skip_delete && $this->remove_old_images( $metadata, $fullsizepath, $image_size ) ) {
            $ctx->mark_changed();
        }

        if ( isset( $image_sizes['error'] ) ) {
            $ctx->out->warning( sprintf( '%s (ID %d)', (string) $image_sizes['message'], $att_id ) );
            return true;
        }

        return $this->sizes_need_regeneration( (array) $image_sizes['sizes'], $metadata['sizes'], $fullsizepath, $image_size );
    }

    






    private static function with_sizes( $metadata ): array {
        if ( ! is_array( $metadata ) ) {
            $metadata = array();
        }
        if ( ! isset( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
            $metadata['sizes'] = array();
        }
        return $metadata;
    }

    







    private function sizes_need_regeneration( array $image_sizes, array $meta_sizes, string $fullsizepath, string $image_size ): bool {
        if ( $image_size ) {
            if ( empty( $image_sizes[ $image_size ] ) ) {
                return false;
            }
            if ( empty( $meta_sizes[ $image_size ] ) ) {
                return true;
            }
            $meta_sizes = array( $image_size => $meta_sizes[ $image_size ] );
        }

        if ( self::image_sizes_differ( $image_sizes, $meta_sizes ) ) {
            return true;
        }

        $dir_path = dirname( $fullsizepath ) . '/';
        foreach ( $meta_sizes as $size_info ) {
            $intermediate_path = $dir_path . $size_info['file'];
            if ( $intermediate_path === $fullsizepath ) {
                continue;
            }
            if ( ! $this->file_exists( $intermediate_path ) ) {
                return true;
            }
        }
        return false;
    }

    




    private function remove_old_images( array $metadata, string $fullsizepath, string $image_size ): bool {
        if ( empty( $metadata['sizes'] ) ) {
            return false;
        }
        if ( $image_size ) {
            if ( empty( $metadata['sizes'][ $image_size ] ) ) {
                return false;
            }
            $metadata['sizes'] = array( $image_size => $metadata['sizes'][ $image_size ] );
        }
        $dir_path = dirname( $fullsizepath ) . '/';
        $deleted  = false;
        foreach ( $metadata['sizes'] as $size_info ) {
            $intermediate_path = $dir_path . $size_info['file'];
            if ( $intermediate_path === $fullsizepath ) {
                continue;
            }
            if ( $this->file_exists( $intermediate_path ) ) {
                $this->delete_file( $intermediate_path );
                $deleted = true;
            }
        }
        return $deleted;
    }

    





    private static function image_sizes_differ( array $image_sizes, array $meta_sizes ): bool {
        if ( array_diff( array_keys( $image_sizes ), array_keys( $meta_sizes ) ) ) {
            return true;
        }
        foreach ( $image_sizes as $name => $image_size ) {
            if ( $image_size['width'] !== $meta_sizes[ $name ]['width'] || $image_size['height'] !== $meta_sizes[ $name ]['height'] ) {
                return true;
            }
        }
        return false;
    }

    





    private function update_attachment_metadata_for_image_size( int $id, array $new_metadata, string $image_size, $metadata ): bool {
        if ( ! is_array( $metadata ) ) {
            return false;
        }
        if ( ! empty( $new_metadata['sizes'][ $image_size ] ) ) {
            $metadata['sizes'][ $image_size ] = $new_metadata['sizes'][ $image_size ];
            $this->update_metadata( $id, $metadata );
            return true;
        }
        if ( ! empty( $metadata['sizes'][ $image_size ] ) ) {
            unset( $metadata['sizes'][ $image_size ] );
            $this->update_metadata( $id, $metadata );
        }
        return false;
    }

    




    private function delete_unknown_image_sizes( int $id, string $fullsizepath, $original_meta ): void {
        $image_sizes     = array_keys( $this->registered_subsizes() );
        $dir_path        = dirname( $fullsizepath ) . '/';
        $sizes_to_delete = array();

        if ( is_array( $original_meta ) && isset( $original_meta['sizes'] ) ) {
            foreach ( $original_meta['sizes'] as $size_name => $size_meta ) {
                if ( 'full' === $size_name ) {
                    continue;
                }
                if ( ! in_array( $size_name, $image_sizes, true ) ) {
                    $intermediate_path = $dir_path . $size_meta['file'];
                    if ( $intermediate_path === $fullsizepath ) {
                        continue;
                    }
                    if ( $this->file_exists( $intermediate_path ) ) {
                        $this->delete_file( $intermediate_path );
                    }
                    $sizes_to_delete[] = $size_name;
                }
            }
            foreach ( $sizes_to_delete as $size_name ) {
                unset( $original_meta['sizes'][ $size_name ] );
            }
        }

        $this->update_metadata( $id, $original_meta );
    }

    
    
    

    
    public function fix_orientation( Invocation $inv, Context $ctx ): void {
        if ( ! $this->exif_available( 'extension' ) ) {
            $ctx->out->error( "'EXIF' extension is not loaded, it is required for this operation." );
        } elseif ( ! $this->exif_available( 'function' ) ) {
            $ctx->out->error( "Function 'exif_read_data' does not exist, it is required for this operation." );
        }

        $args    = array_values( $inv->args );
        $images  = $this->find_images( $args, array( 'image' ), 'image', $ctx );
        $count   = count( $images );
        $dry_run = (bool) Read_Value::flag( $inv->assoc, 'dry-run' );

        if ( ! $count ) {
            $ctx->out->error( 'No images found.' );
        }
        $this->enforce_cap( $inv, $ctx, $count );
        if ( ! $dry_run ) {
            $ctx->not_captured( self::NOT_CAPTURED_FILES );
        }

        $number    = 0;
        $successes = 0;
        $errors    = 0;
        foreach ( $images as $post_id ) {
            ++$number;
            $this->process_orientation_fix( $ctx, (int) $post_id, "{$number}/{$count}", $successes, $errors, $dry_run );
        }

        if ( $dry_run ) {
            $ctx->out->success( sprintf( '%s of %s %s will be affected.', $successes, $count, 1 === $count ? 'image' : 'images' ) );
        } else {
            Values::report_batch( $ctx->out, 'image', 'fix', $count, $successes, $errors );
        }
    }

    




    public function describe_fix_orientation( Invocation $inv ): array {
        if ( Read_Value::flag( $inv->assoc, 'dry-run' ) ) {
            return array( 'Dry run: the orientation of each image is read; nothing is written.' );
        }
        return $this->describe_images( array_values( $inv->args ), array( 'image' ), 'rotated or flipped in place when its EXIF orientation is above 1, its sub-sizes regenerated' );
    }

    
    private function process_orientation_fix( Context $ctx, int $id, string $progress, int &$successes, int &$errors, bool $dry_run ): void {
        $att_desc = $this->att_desc( $id );

        $full_size_path = $this->editable_original( $ctx, $id, $att_desc, ! $dry_run );
        if ( null === $full_size_path ) {
            ++$errors;
            return;
        }

        $orientation = $this->read_orientation( $full_size_path );

        if ( null !== $orientation && $orientation > 1 ) {
            if ( ! $dry_run ) {
                $ctx->out->log( "{$progress} Fixing orientation for {$att_desc}." );
                $result = $this->flip_rotate_image( $id, $full_size_path, $orientation );
                if ( $result['attempted'] ) {
                    $ctx->mark_changed();
                }
                if ( $result['fixed'] ) {
                    ++$successes;
                } else {
                    ++$errors;
                    $ctx->out->log( "Couldn't fix orientation for {$att_desc}." );
                }
            } else {
                $ctx->out->log( "{$progress} {$att_desc} will be affected." );
                ++$successes;
            }
        } else {
            $ctx->out->log( "{$progress} No orientation fix required for {$att_desc}." );
        }
    }

    




    public static function calculate_transformation( int $orientation ): array {
        $rotate = false;
        $flip   = false;
        $degree = 0;
        switch ( $orientation ) {
            case 2:
                $flip = array( false, true );
                break;
            case 3:
                $flip = array( true, true );
                break;
            case 4:
                $flip = array( true, false );
                break;
            case 5:
                $degree = -90;
                $rotate = true;
                $flip   = array( false, true );
                break;
            case 6:
                $degree = -90;
                $rotate = true;
                break;
            case 7:
                $degree = 90;
                $rotate = true;
                $flip   = array( false, true );
                break;
            case 8:
                $degree = 90;
                $rotate = true;
                break;
            default:
                $degree = 0;
                $rotate = true;
                break;
        }
        return array(
            'flip'   => $flip,
            'degree' => $degree,
            'rotate' => $rotate,
        );
    }

    
    
    

    







    private function find_images( array $args, array $mime_types, string $kind, Context $ctx ): array {
        $ids = array_map( 'intval', $this->query_images( $args, $mime_types ) );
        foreach ( array_values( array_unique( array_map( 'strval', $args ) ) ) as $arg ) {
            
            if ( ! in_array( abs( (int) $arg ), $ids, true ) ) {
                $ctx->out->warning( sprintf( self::NOT_FOUND_IDS, $kind, $arg ) );
            }
        }
        return $ids;
    }

    
    private function enforce_cap( Invocation $inv, Context $ctx, int $count ): void {
        $cap = isset( $inv->route['bulk'] ) ? (int) $inv->route['bulk'] : self::BULK;
        if ( $count > $cap ) {
            $ctx->out->error( sprintf( self::OVER_CAP, $cap, $inv->command, $count ) );
        }
    }

    






    private function describe_images( array $args, array $mime_types, string $what ): array {
        $ids = array_map( 'intval', $this->query_images( $args, $mime_types ) );
        sort( $ids );
        $lines = array();
        foreach ( $ids as $id ) {
            $lines[] = sprintf( '%s → %s', $this->att_desc( $id ), $what );
        }
        if ( ! $lines ) {
            $lines[] = 'No matching attachment → nothing is changed';
        }
        return $lines;
    }

    
    private function att_desc( int $id ): string {
        $title = $this->title( $id );
        if ( '' === $title ) {
            if ( $this->has_cover_hash( $id ) ) {
                return sprintf( 'cover attachment (ID %d)', $id );
            }
            return sprintf( '"(no title)" (ID %d)', $id );
        }
        return sprintf( '"%1$s" (ID %2$d)', $title, $id );
    }

    




    private function editable_original( Context $ctx, int $id, string $att_desc, bool $needs_edit ): ?string {
        if ( $needs_edit && ! $this->user_can( 'edit_post', $id ) ) {
            $ctx->out->warning( sprintf( self::NOT_EDITABLE, $att_desc ) );
            return null;
        }

        $path = $this->attached_file( $id );
        if ( false === $path || ! $this->file_exists( (string) $path ) ) {
            $ctx->out->warning( "Can't find {$att_desc}." );
            return null;
        }
        return (string) $path;
    }

    






    private static function metadata_error( array $metadata, bool $is_pdf, string $image_size ): ?string {
        if ( empty( $metadata ) && ! ( $is_pdf && $image_size ) ) {
            return 'No metadata. (ID %d)';
        }
        if ( 1 === count( $metadata ) && array_key_exists( 'filesize', $metadata ) && ! ( $is_pdf && $image_size ) ) {
            return 'Read error while retrieving metadata. (ID %d)';
        }
        return null;
    }

    
    private static function ratio( int $width, int $height ): string {
        if ( 0 === $height ) {
            return "0:{$width}";
        }
        if ( 0 === $width ) {
            return "{$height}:0";
        }
        $num1 = $width;
        $num2 = $height;
        while ( 0 !== $num2 ) {
            $t    = $num1 % $num2;
            $num1 = $num2;
            $num2 = $t;
        }
        $gcd = $num1;
        return ( $width / $gcd ) . ':' . ( $height / $gcd );
    }

    
    
    

    








    protected function call( string $name, array $args, callable $real ) {
        return $real();
    }

    
    protected function registered_subsizes(): array {
        return (array) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                return \wp_get_registered_image_subsizes();
            }
        );
    }

    
    protected function intermediate_sizes(): array {
        return (array) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                return array_values( \get_intermediate_image_sizes() );
            }
        );
    }

    






    protected function query_images( array $args, array $mime_types ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $args, $mime_types ),
            static function () use ( $args, $mime_types ) {
                $query = new \WP_Query(
                    array(
                        'post_type'              => 'attachment',
                        'post__in'               => $args,
                        'post_mime_type'         => $mime_types,
                        'post_status'            => 'any',
                        'posts_per_page'         => -1,
                        'fields'                 => 'ids',
                        'update_post_meta_cache' => false,
                        'update_post_term_cache' => false,
                    )
                );
                return array_map( 'intval', (array) $query->posts );
            }
        );
    }

    
    protected function user_can( string $cap, int ...$args ): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array_merge( array( $cap ), $args ),
            static function () use ( $cap, $args ) {
                return \current_user_can( $cap, ...$args );
            }
        );
    }

    protected function title( int $id ): string {
        return (string) $this->call(
            __FUNCTION__,
            array( $id ),
            static function () use ( $id ) {
                return \get_the_title( $id );
            }
        );
    }

    protected function has_cover_hash( int $id ): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array( $id ),
            static function () use ( $id ) {
                return \metadata_exists( 'post', $id, '_cover_hash' );
            }
        );
    }

    
    protected function attached_file( int $id ) {
        return $this->call(
            __FUNCTION__,
            array( $id ),
            static function () use ( $id ) {
                if ( function_exists( 'wp_get_original_image_path' ) ) {
                    $filepath = \wp_get_original_image_path( $id );
                    if ( false !== $filepath ) {
                        return $filepath;
                    }
                }
                return \get_attached_file( $id );
            }
        );
    }

    protected function file_exists( string $path ): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array( $path ),
            static function () use ( $path ) {
                return file_exists( $path );
            }
        );
    }

    protected function delete_file( string $path ): void {
        $this->call(
            __FUNCTION__,
            array( $path ),
            static function () use ( $path ) {
                \wp_delete_file( $path );
                return null;
            }
        );
    }

    protected function mime_type( int $id ): string {
        return (string) $this->call(
            __FUNCTION__,
            array( $id ),
            static function () use ( $id ) {
                return \get_post_mime_type( $id );
            }
        );
    }

    
    protected function attachment_metadata( int $id ) {
        return $this->call(
            __FUNCTION__,
            array( $id ),
            static function () use ( $id ) {
                return \wp_get_attachment_metadata( $id );
            }
        );
    }

    
    protected function generate_metadata( int $id, string $path ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $id, $path ),
            static function () use ( $id, $path ) {
                self::admin_includes();
                return (array) \wp_generate_attachment_metadata( $id, $path );
            }
        );
    }

    
    protected function update_metadata( int $id, $metadata ): void {
        $this->call(
            __FUNCTION__,
            array( $id, $metadata ),
            static function () use ( $id, $metadata ) {
                \wp_update_attachment_metadata( $id, $metadata );
                return null;
            }
        );
    }

    
    protected function size_filters( string $image_size, bool $on ): void {
        $this->call(
            __FUNCTION__,
            array( $image_size, $on ),
            function () use ( $image_size, $on ) {
                if ( $on ) {
                    $this->image_size_filters = array(
                        'intermediate_image_sizes_advanced'  => static function ( $sizes ) use ( $image_size ) {
                            return isset( $sizes[ $image_size ] ) ? array( $image_size => $sizes[ $image_size ] ) : array();
                        },
                        'fallback_intermediate_image_sizes' => static function ( $fallback_sizes ) use ( $image_size ) {
                            return in_array( $image_size, (array) $fallback_sizes, true ) ? array( $image_size ) : array();
                        },
                    );
                    foreach ( $this->image_size_filters as $name => $filter ) {
                        \add_filter( $name, $filter, PHP_INT_MAX );
                    }
                } else {
                    foreach ( $this->image_size_filters as $name => $filter ) {
                        \remove_filter( $name, $filter, PHP_INT_MAX );
                    }
                    $this->image_size_filters = array();
                }
                return null;
            }
        );
    }

    
    private array $image_size_filters = array();

    






    protected function sizes_for_attachment( string $fullsizepath, bool $is_pdf, $metadata, int $att_id ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $fullsizepath, $is_pdf, $metadata, $att_id ),
            static function () use ( $fullsizepath, $is_pdf, $metadata, $att_id ) {
                $editor = \wp_get_image_editor( $fullsizepath );
                if ( \is_wp_error( $editor ) ) {
                    return array(
                        'error'   => $editor->get_error_code(),
                        'message' => $editor->get_error_message(),
                    );
                }
                $result = $editor->load();
                if ( \is_wp_error( $result ) ) {
                    return array(
                        'error'   => $result->get_error_code(),
                        'message' => $result->get_error_message(),
                    );
                }
                list( $width, $height ) = array_values( $editor->get_size() );
                unset( $editor );

                $sizes = array();
                foreach ( self::intermediate_sizes_for( $is_pdf, $metadata, $att_id ) as $name => $size ) {
                    $dims = \image_resize_dimensions( $width, $height, $size['width'], $size['height'], $size['crop'] );
                    if ( ( $width !== $size['width'] || $height !== $size['height'] ) && $dims ) {
                        list( , , , , $dst_w, $dst_h ) = $dims;
                        $sizes[ $name ] = array(
                            'width'  => $dst_w,
                            'height' => $dst_h,
                        );
                    }
                }
                return array( 'sizes' => $sizes );
            }
        );
    }

    





    private static function intermediate_sizes_for( bool $is_pdf, $metadata, int $att_id ): array {
        if ( $is_pdf ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook.
            $intermediate_image_sizes = \apply_filters( 'fallback_intermediate_image_sizes', array( 'thumbnail', 'medium', 'large' ), $metadata );
        } else {
            $intermediate_image_sizes = \get_intermediate_image_sizes();
        }
        $additional = \wp_get_additional_image_sizes();
        $sizes      = array();
        foreach ( $intermediate_image_sizes as $s ) {
            $sizes[ $s ]['width']  = isset( $additional[ $s ]['width'] ) ? (int) $additional[ $s ]['width'] : (int) \get_option( "{$s}_size_w" );
            $sizes[ $s ]['height'] = isset( $additional[ $s ]['height'] ) ? (int) $additional[ $s ]['height'] : (int) \get_option( "{$s}_size_h" );
            if ( isset( $additional[ $s ]['crop'] ) ) {
                $sizes[ $s ]['crop'] = (bool) $additional[ $s ]['crop'];
            } elseif ( $is_pdf && 'thumbnail' === $s ) {
                $sizes[ $s ]['crop'] = false;
            } else {
                $sizes[ $s ]['crop'] = (bool) \get_option( "{$s}_crop" );
            }
        }
        if ( ! $is_pdf && is_array( $metadata ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook.
            $sizes = \apply_filters( 'intermediate_image_sizes_advanced', $sizes, $metadata, $att_id );
        }
        return $sizes;
    }

    
    protected function exif_available( string $what ): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array( $what ),
            static function () use ( $what ) {
                return 'extension' === $what ? extension_loaded( 'exif' ) : function_exists( 'exif_read_data' );
            }
        );
    }

    









    protected function read_orientation( string $path ): ?int {
        $value = $this->call(
            __FUNCTION__,
            array( $path ),
            static function () use ( $path ) {
                self::admin_includes();
                $meta = \wp_read_image_metadata( $path );
                if ( ! is_array( $meta ) || ! isset( $meta['orientation'] ) ) {
                    return null;
                }
                $orientation = \absint( $meta['orientation'] );
                if ( $orientation > 1 && self::would_auto_rotate( $path ) ) {
                    return 1;
                }
                return $orientation;
            }
        );
        return null === $value ? null : (int) $value;
    }

    




    private static function would_auto_rotate( string $path ): bool {
        $editor = \wp_get_image_editor( $path );
        if ( \is_wp_error( $editor ) || 'image/jpeg' !== \wp_get_image_mime( $path ) || ! is_callable( 'exif_read_data' ) ) {
            return false;
        }
        $exif        = @exif_read_data( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- as core's maybe_exif_rotate().
        $orientation = ! empty( $exif['Orientation'] ) ? (int) $exif['Orientation'] : null;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook.
        $orientation = \apply_filters( 'wp_image_maybe_exif_rotate', $orientation, $path );
        return $orientation && 1 !== (int) $orientation;
    }

    






    protected function flip_rotate_image( int $id, string $full_size_path, int $orientation ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $id, $full_size_path, $orientation ),
            static function () use ( $id, $full_size_path, $orientation ) {
                self::admin_includes();
                $editor = \wp_get_image_editor( $full_size_path );
                if ( \is_wp_error( $editor ) ) {
                    return array(
                        'attempted' => false,
                        'fixed'     => false,
                    );
                }
                $operations = self::calculate_transformation( $orientation );
                if ( true === $operations['rotate'] ) {
                    $editor->rotate( $operations['degree'] );
                }
                if ( false !== $operations['flip'] ) {
                    $editor->flip( $operations['flip'][0], $operations['flip'][1] );
                }
                $editor->save( $full_size_path );
                $metadata   = \wp_generate_attachment_metadata( $id, $full_size_path );
                $image_meta = empty( $metadata['image_meta'] ) ? array() : $metadata['image_meta'];
                \wp_update_attachment_metadata( $id, $metadata );
                return array(
                    'attempted' => true,
                    'fixed'     => isset( $image_meta['orientation'] ) && 0 === \absint( $image_meta['orientation'] ),
                );
            }
        );
    }

    
    private static function admin_includes(): void {
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) || ! function_exists( 'wp_read_image_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
    }
}
