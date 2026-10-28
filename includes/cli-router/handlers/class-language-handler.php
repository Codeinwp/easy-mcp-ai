<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Cli_Exit;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Router;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Language_Handler extends Command_Handler {

    const OBJ_FIELDS = array(
        'core'    => array( 'language', 'english_name', 'native_name', 'status', 'update', 'updated' ),
        'plugins' => array( 'plugin', 'language', 'english_name', 'native_name', 'status', 'update', 'updated' ),
        'themes'  => array( 'theme', 'language', 'english_name', 'native_name', 'status', 'update', 'updated' ),
    );

    const INSTALL_CAP = 20;

    const UNINSTALL_CAP = 50;

    const OBJECT_CAP = 20;

    const ROW_CAP = 1000;

    const REASON_FILE_MODS   = "File modifications are disabled on this site (DISALLOW_FILE_MODS in wp-config.php, or a file_mod_allowed filter): WordPress then withholds the %s capability from every user, administrators included, so wp_cli_run does not run 'wp %s'.";
    const REASON_FILESYSTEM  = "WordPress cannot write to the languages folder directly here (its filesystem method is '%s'), and wp_cli_run cannot answer an FTP or SSH credentials prompt; WP-CLI forces direct file access.";
    const REASON_OVER_CAP    = "wp_cli_run %1\$s at most %2\$d language packs in one 'wp %3\$s' command, and this one names %4\$d%5\$s. Run it in smaller groups.";
    const REASON_OBJECTS_CAP = "wp_cli_run looks up at most %1\$d %2\$s on wordpress.org in one 'wp %3\$s' command, and this one names %4\$d. Name the %2\$s in smaller groups.";
    const REASON_ROW_CAP     = "wp_cli_run lists at most %1\$d languages in one 'wp %2\$s' command, and %3\$d match. Narrow it with a filter such as --status=installed, name fewer %4\$s, or ask for --format=count.";
    const REASON_FILTER      = "wp_cli_run refuses --%1\$s: no listed language has a '%1\$s' field, so WP-CLI would ignore the filter and list every language. Filter on one of: %2\$s.";
    const MISSING_PACK       = 'No language pack named in this command exists on wordpress.org for the %s, so nothing was installed.';
    const MISSING_INSTALLED  = 'Nothing was uninstalled: no file of the language packs named in this command exists for the %s named.';
    const NOTE_UPDATE_CHECK  = "WordPress's update check ran and may have refreshed the %s site transient, a cache WordPress refreshes on its own.";
    const NOTE_UNCHANGED     = "Nothing changed: the site language stayed '%s'. WP-CLI prints the same Success line.";

    const NOT_CAPTURED_FILES = 'Language files: change history does not record files; installed, updated or deleted translations cannot be restored from it.';

    public function preflight_core_install( Invocation $inv, Context $ctx ): void {
        $this->require_language_files( $inv, $ctx, 'install_languages' );
    }

    const ACTIVATE_TOOL = 'wp_update_site_settings';

    public function split_activate( Invocation $inv, Context $ctx ): ?array {
        if ( ! Read_Value::flag( $inv->assoc, 'activate' ) ) {
            return null;
        }
        $codes = array_map( 'strval', array_values( $inv->args ) );
        if ( count( $codes ) > 1 ) {
            $ctx->out->error( 'Only a single language can be active.' );
        }
        if ( class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) && method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' )
            && false === \Easy_MCP_AI\MCP\Server::current_call_may_use( self::ACTIVATE_TOOL ) ) {
            $ctx->out->error( Fetchers::tool_unavailable( $ctx, $inv->command . ' --activate', self::ACTIVATE_TOOL ) );
        }
        $code   = (string) reset( $codes );
        $target = 'en_US' === $code ? '' : $code;
        $active = (string) $this->wp( 'locale' ) === $target;
        $parts  = array(
            array(
                'tool'      => 'wp_cli_run',
                'arguments' => array( 'command' => 'language core install ' . self::shell_word( $code ) ),
                'render'    => $active ? 'render_install_active' : 'render_install',
            ),
        );
        if ( ! $active ) {
            $parts[] = array(
                'tool'      => self::ACTIVATE_TOOL,
                'arguments' => array( 'language' => $target ),
                'render'    => 'render_activate',
                'label'     => self::ACTIVATE_TOOL . ' language ' . $code,
            );
        }
        return $parts;
    }

    public function render_install( Invocation $inv, Context $ctx, array $result, array &$state ): bool {
        return $this->render_install_part( $ctx, $result, $state, null );
    }

    public function render_install_active( Invocation $inv, Context $ctx, array $result, array &$state ): bool {
        $code = (string) array_values( $inv->args )[0];
        return $this->render_install_part( $ctx, $result, $state, 'en_US' === $code ? '' : $code );
    }

    public function render_activate( Invocation $inv, Context $ctx, array $result, array &$state ): bool {
        if ( empty( $result['ok'] ) ) {
            $ctx->out->error_continue( (string) $result['error'] );
            return false;
        }
        $payload = (array) $result['payload'];
        if ( ! empty( $payload['updated'] ) ) {
            $ctx->mark_changed();
        } else {
            $language = isset( $payload['language'] ) ? (string) $payload['language'] : '';
            $ctx->note( sprintf( self::NOTE_UNCHANGED, '' === $language ? 'en_US' : $language ) );
        }
        $ctx->out->success( 'Language activated.' );
        $ctx->out->out( isset( $state['summary'] ) ? (string) $state['summary'] : '' );
        return true;
    }

    private function render_install_part( Context $ctx, array $result, array &$state, ?string $active_code ): bool {
        $part = isset( $result['part'] ) ? $result['part'] : null;
        if ( ! $part instanceof Context ) {
            return self::render_stored_install( $ctx, $result );
        }
        list( $stdout, $summary ) = self::split_summary( $part->out->stdout() );
        if ( empty( $result['ok'] ) ) {
            $ctx->out->out( $stdout );
            foreach ( $part->out->warnings() as $warning ) {
                $ctx->out->warning( $warning );
            }
            $ctx->out->error_continue( 'Language not installed.' );
            return false;
        }
        Router::merge_part( $ctx, $part, $stdout );
        if ( null === $active_code ) {
            $state['summary'] = $summary;
            return true;
        }
        $ctx->out->warning( "Language '{$active_code}' already active." );
        $ctx->out->out( $summary );
        return true;
    }

    private static function render_stored_install( Context $ctx, array $result ): bool {
        if ( empty( $result['ok'] ) ) {
            return false;
        }
        $payload = (array) $result['payload'];
        $ctx->out->out( isset( $payload['output'] ) && is_string( $payload['output'] ) ? $payload['output'] : '' );
        if ( ! empty( $payload['changed'] ) ) {
            $ctx->mark_changed();
        }
        return true;
    }

    private static function split_summary( string $stdout ): array {
        $summary = '';
        $trimmed = rtrim( $stdout, "\n" );
        $cut     = strrpos( $trimmed, "\n" );
        $last    = false === $cut ? $trimmed : substr( $trimmed, $cut + 1 );
        if ( 0 === strpos( $last, 'Success: ' ) ) {
            $summary = substr( $stdout, false === $cut ? 0 : $cut + 1 );
            $stdout  = false === $cut ? '' : substr( $stdout, 0, $cut + 1 );
        }
        return array( $stdout, $summary );
    }

    private static function shell_word( string $word ): string {
        if ( '' !== $word && preg_match( '#^[A-Za-z0-9_\-.,:/@%+=]+\z#', $word ) ) {
            return $word;
        }
        return "'" . str_replace( "'", "'\\''", $word ) . "'";
    }

    public function is_dry_run( Invocation $inv ): bool {
        return (bool) Read_Value::flag( $inv->assoc, 'dry-run' );
    }

    public function preflight_core_uninstall( Invocation $inv, Context $ctx ): void {
        $this->require_language_files( $inv, $ctx, 'install_languages' );
    }

    public function preflight_core_update( Invocation $inv, Context $ctx ): void {
        $this->require_language_files( $inv, $ctx, 'update_languages' );
    }

    public function preflight_object_install( Invocation $inv, Context $ctx ): void {
        $this->require_language_files( $inv, $ctx, 'install_languages' );
        $type = self::type_of( $inv );
        $args = array_values( $inv->args );
        $all  = (bool) Read_Value::flag( $inv->assoc, 'all', false );
        if ( ! $all && count( $args ) >= 2 && ! $this->wp( 'object_exists', array( $type, (string) $args[0] ) ) ) {
            $ctx->out->error( sprintf( Fetchers::MESSAGES[ self::noun( $type ) ], (string) $args[0] ) );
        }
        $this->enforce_pack_cap( $inv, $ctx, $type, 'downloads', self::INSTALL_CAP );
    }

    public function preflight_object_uninstall( Invocation $inv, Context $ctx ): void {
        $this->require_language_files( $inv, $ctx, 'install_languages' );
        $this->enforce_pack_cap( $inv, $ctx, self::type_of( $inv ), 'deletes', self::UNINSTALL_CAP );
    }

    public function preflight_object_update( Invocation $inv, Context $ctx ): void {
        $this->require_language_files( $inv, $ctx, 'update_languages' );
        $this->enforce_object_cap( $inv, $ctx );
    }

    public function preflight_object_list( Invocation $inv, Context $ctx ): void {
        $this->enforce_object_cap( $inv, $ctx );
    }

    public function core_list( Invocation $inv, Context $ctx ): void {
        $assoc_args   = $inv->assoc;
        $translations = $this->get_all_languages( 'core', null, $ctx );
        $available    = $this->get_installed_languages( 'core' );
        $updates      = $this->get_translation_updates( 'core', $ctx );

        $current_locale = (string) $this->wp( 'locale' );

        $translations = array_map(
            static function ( $translation ) use ( $available, $current_locale, $updates ) {
                $translation['status'] = 'uninstalled';
                if ( in_array( $translation['language'], $available, true ) ) {
                    $translation['status'] = 'installed';
                }

                if ( $current_locale === $translation['language'] ) {
                    $translation['status'] = 'active';
                }

                $update = self::list_filter( $updates, array( 'language' => $translation['language'] ) );
                if ( $update ) {
                    $translation['update'] = 'available';
                } else {
                    $translation['update'] = 'none';
                }

                return $translation;
            },
            $translations
        );

        $this->refuse_unknown_filters( $inv, $ctx, $translations );

        foreach ( $translations as $key => $translation ) {
            foreach ( array_keys( $translation ) as $field ) {
                if ( isset( $assoc_args[ $field ] ) && ! in_array( $translation[ $field ], array_map( 'trim', explode( ',', (string) $assoc_args[ $field ] ) ), true ) ) {
                    unset( $translations[ $key ] );
                }
            }
        }

        $this->display_list( $inv, $ctx, 'core', $translations );
    }

    public function core_is_installed( Invocation $inv, Context $ctx ): void {
        list( $language_code ) = array_values( $inv->args );
        $available             = $this->get_installed_languages( 'core' );
        $ctx->out->halt( in_array( (string) $language_code, $available, true ) ? 0 : 1 );
    }

    public function core_install( Invocation $inv, Context $ctx ): void {
        $language_codes = array_map( 'strval', array_values( $inv->args ) );
        $count          = count( $language_codes );

        if ( $count > 1 && in_array( true, $inv->assoc, true ) ) {
            $ctx->out->error( 'Only a single language can be active.' );
        }

        $available = $this->get_installed_languages( 'core' );

        $successes = 0;
        $errors    = 0;
        $skips     = 0;
        $missing   = 0;
        foreach ( $language_codes as $language_code ) {

            if ( in_array( $language_code, $available, true ) ) {
                $ctx->out->log( "Language '{$language_code}' already installed." );
                ++$skips;
            } else {
                $response = $this->download_language_pack( 'core', $language_code, null, $ctx );

                if ( is_array( $response ) ) {
                    $ctx->out->warning( self::error_to_string( $response ) );
                    $ctx->out->log( "Language '{$language_code}' not installed." );

                    if ( 'not_found' === $response['code'] ) {
                        ++$skips;
                        ++$missing;
                    } else {
                        ++$errors;
                    }
                } else {
                    $ctx->out->log( "Language '{$language_code}' installed." );
                    ++$successes;
                }
            }

        }

        $this->finish_install( $ctx, $count, $successes, $errors, $skips, $missing, 'WordPress core' );
    }

    public function core_uninstall( Invocation $inv, Context $ctx ): void {
        $dir   = (string) $this->wp( 'lang_dir' );
        $files = $this->language_files( $dir, $ctx );

        $language_codes = array_map( 'strval', array_values( $inv->args ) );
        $available      = $this->get_installed_languages( 'core' );
        $current_locale = (string) $this->wp( 'locale' );

        foreach ( $language_codes as $language_code ) {
            if ( ! in_array( $language_code, $available, true ) ) {
                $ctx->out->error( 'Language not installed.' );
            }

            if ( $language_code === $current_locale ) {
                $ctx->out->warning( "The '{$language_code}' language is active." );
                $ctx->out->halt( 0 );
            }

            if ( $this->delete_core_pack( $language_code, $files, $dir, $ctx ) ) {
                $ctx->out->success( 'Language uninstalled.' );
            } else {
                $ctx->out->error( "Couldn't uninstall language." );
            }
        }
    }

    private function language_files( string $dir, Context $ctx ): array {
        $files = $this->wp( 'dir_entries', array( $dir ) );
        if ( ! $files ) {
            $ctx->out->error( 'No files found in language directory.' );
        }
        return (array) $files;
    }

    private function delete_core_pack( string $language_code, array $files, string $dir, Context $ctx ): bool {
        $files_to_remove = array(
            "$language_code.po",
            "$language_code.mo",
            "$language_code.l10n.php",
            "admin-$language_code.po",
            "admin-$language_code.mo",
            "admin-$language_code.l10n.php",
            "admin-network-$language_code.po",
            "admin-network-$language_code.mo",
            "admin-network-$language_code.l10n.php",
            "continents-cities-$language_code.po",
            "continents-cities-$language_code.mo",
            "continents-cities-$language_code.l10n.php",
        );

        $this->wp( 'filesystem_init' );
        $deleted = false;
        foreach ( $files as $file ) {
            $file = (string) $file;
            if ( '' === $file || '.' === $file[0] ) {
                continue;
            }

            if (
                ! in_array( $file, $files_to_remove, true ) &&
                ! preg_match( '/' . preg_quote( $language_code, '/' ) . '-\w{32}\.json/', $file )
            ) {
                continue;
            }

            $deleted = (bool) $this->wp( 'delete_file', array( $dir . '/' . $file ) );
            if ( $deleted ) {
                $ctx->mark_changed();
                $ctx->not_captured( self::NOT_CAPTURED_FILES );
            }
        }
        return $deleted;
    }

    public function core_update( Invocation $inv, Context $ctx ): void {
        $this->update( 'core', array(), $inv, $ctx );
    }

    public function object_list( Invocation $inv, Context $ctx ): void {
        $type       = self::type_of( $inv );
        $noun       = self::noun( $type );
        $assoc_args = $inv->assoc;
        $args       = array_map( 'strval', array_values( $inv->args ) );
        $all        = Read_Value::flag( $assoc_args, 'all', false );

        if ( ! $all && empty( $args ) ) {
            $ctx->out->error( "Please specify one or more {$noun}s, or use --all." );
        }

        if ( $all ) {
            $args = $this->all_object_names( $type );

            if ( empty( $args ) ) {
                $ctx->out->success( "No {$noun}s installed." );
                return;
            }
        }

        $updates        = $this->get_translation_updates( $type, $ctx );
        $current_locale = (string) $this->wp( 'locale' );

        $rows = array();
        foreach ( $args as $object ) {
            if ( ! $this->wp( 'object_exists', array( $type, $object ) ) ) {
                $ctx->out->warning( ucfirst( $noun ) . " '{$object}' not found." );
                continue;
            }
            $rows = array_merge( $rows, $this->object_rows( $type, $object, $updates, $current_locale, $ctx ) );
        }

        $this->refuse_unknown_filters( $inv, $ctx, $rows );

        $this->display_list( $inv, $ctx, $type, self::matching_rows( $rows, $assoc_args ) );
    }

    private function object_rows( string $type, string $object, array $updates, string $current_locale, Context $ctx ): array {
        $noun                   = self::noun( $type );
        $installed_translations = $this->get_installed_languages( $type, $object );
        $available_translations = $this->get_all_languages( $type, $object, $ctx );

        $rows = array();
        foreach ( $available_translations as $translation ) {
            $translation[ $noun ]  = $object;
            $translation['status'] = in_array( $translation['language'], $installed_translations, true ) ? 'installed' : 'uninstalled';

            if ( $current_locale === $translation['language'] ) {
                $translation['status'] = 'active';
            }

            $filter_args = array(
                'language' => $translation['language'],
                'type'     => $noun,
                'slug'     => $object,
            );
            $update      = self::list_filter( $updates, $filter_args );

            $translation['update'] = $update ? 'available' : 'none';

            $rows[] = $translation;
        }
        return $rows;
    }

    private static function matching_rows( array $rows, array $assoc_args ): array {
        $translations = array();
        foreach ( $rows as $translation ) {
            foreach ( array_keys( $translation ) as $field ) {
                if ( isset( $assoc_args[ $field ] ) && ! in_array( $translation[ $field ], array_map( 'trim', explode( ',', (string) $assoc_args[ $field ] ) ), true ) ) {
                    continue 2;
                }
            }

            $translations[] = $translation;
        }
        return $translations;
    }

    public function object_is_installed( Invocation $inv, Context $ctx ): void {
        $args           = array_map( 'strval', array_values( $inv->args ) );
        $object         = array_shift( $args );
        $language_codes = $args;

        $available = $this->get_installed_languages( self::type_of( $inv ), (string) $object );

        foreach ( $language_codes as $language_code ) {
            if ( ! in_array( $language_code, $available, true ) ) {
                $ctx->out->halt( 1 );
            }
        }

        $ctx->out->halt( 0 );
    }

    public function object_install( Invocation $inv, Context $ctx ): void {
        $type  = self::type_of( $inv );
        $noun  = self::noun( $type );
        $args  = array_map( 'strval', array_values( $inv->args ) );
        $assoc = $inv->assoc;
        $all   = Read_Value::flag( $assoc, 'all', false );

        if ( ! $all && count( $args ) < 2 ) {
            $ctx->out->error( "Please specify a {$noun}, or use --all." );
        }

        if ( $all ) {
            $this->install_many( $type, $args, $assoc, $ctx );
        } else {
            $this->install_one( $type, $args, $ctx );
        }
    }

    public function object_uninstall( Invocation $inv, Context $ctx ): void {
        $type       = self::type_of( $inv );
        $noun       = self::noun( $type );
        $args       = array_map( 'strval', array_values( $inv->args ) );
        $assoc_args = $inv->assoc;

        $assoc_args['format'] = self::batch_format( $assoc_args, $ctx );

        $all = Read_Value::flag( $assoc_args, 'all', false );

        if ( ! $all && count( $args ) < 2 ) {
            $ctx->out->error( "Please specify one or more {$noun}s, or use --all." );
        }

        if ( $all ) {
            $objects = $this->all_object_names( $type );

            if ( empty( $objects ) ) {
                $ctx->out->success( "No {$noun}s installed." );
                return;
            }
        } else {
            $objects = array( (string) array_shift( $args ) );
        }

        $language_codes = $args;
        $job            = array(
            'noun'   => $noun,
            'all'    => (bool) $all,
            'locale' => (string) $this->wp( 'locale' ),
            'dir'    => (string) $this->wp( 'lang_dir' ) . "/$type",
            'files'  => array(),
        );
        $job['files']   = $this->language_files( $job['dir'], $ctx );

        $this->wp( 'filesystem_init' );

        $count = count( $objects ) * count( $language_codes );

        $results = array();
        $tally = array(
            'successes'   => 0,
            'errors'      => 0,
            'skips'       => 0,
            'not_present' => 0,
        );

        foreach ( $objects as $object ) {
            $available = $this->get_installed_languages( $type, $object );

            foreach ( $language_codes as $language_code ) {
                $results[] = $this->uninstall_pair( $object, $language_code, $available, $job, $tally, $ctx );
            }
        }

        if ( 'summary' !== $assoc_args['format'] ) {
            $this->format_items( $ctx, (string) $assoc_args['format'], $results, array( 'name', 'locale', 'status' ) );
        }

        Values::report_batch( $ctx->out, 'language', 'uninstall', $count, $tally['successes'], $tally['errors'], $tally['skips'] );

        if ( $count > 0 && $tally['not_present'] === $count ) {
            $ctx->fail_missing_target( sprintf( self::MISSING_INSTALLED, $noun . 's' ) );
        }
    }

    private function uninstall_pair( string $object, string $language_code, array $available, array $job, array &$tally, Context $ctx ): object {
        $result = array(
            'name'   => $object,
            'locale' => $language_code,
            'status' => 'not available',
        );

        if ( ! in_array( $language_code, $available, true ) ) {
            $result['status'] = 'not installed';
            $ctx->out->warning( "Language '{$language_code}' not installed." );
            ++$tally[ $job['all'] ? 'skips' : 'errors' ];
            ++$tally['not_present'];
            return (object) $result;
        }

        if ( $language_code === $job['locale'] ) {
            $ctx->out->warning( "The '{$language_code}' language is active." );
            $ctx->out->halt( 0 );
        }

        list( $to_remove, $removed ) = $this->delete_object_pack( $object, $language_code, $job, $ctx );

        if ( 0 === $to_remove ) {
            ++$tally['not_present'];
        }

        list( $result['status'], $counted ) = self::uninstall_outcome( $to_remove, $removed, $language_code, $object, $job['noun'], $ctx );
        ++$tally[ $counted ];

        return (object) $result;
    }

    private function delete_object_pack( string $object, string $language_code, array $job, Context $ctx ): array {
        $files_to_remove = array(
            "$object-$language_code.po",
            "$object-$language_code.mo",
            "$object-$language_code.l10n.php",
        );

        $count_files_to_remove = 0;
        $count_files_removed   = 0;

        foreach ( $job['files'] as $file ) {
            $file = (string) $file;
            if ( '' === $file || '.' === $file[0] ) {
                continue;
            }

            if (
                ! in_array( $file, $files_to_remove, true ) &&
                ! preg_match( '/^' . preg_quote( "$object-$language_code", '/' ) . '-\w{32}\.json\z/', $file )
            ) {
                continue;
            }

            ++$count_files_to_remove;

            if ( $this->wp( 'delete_file', array( $job['dir'] . '/' . $file ) ) ) {
                ++$count_files_removed;
                $ctx->mark_changed();
                $ctx->not_captured( self::NOT_CAPTURED_FILES );
            } else {
                $ctx->out->error( "Couldn't uninstall language: $language_code from {$job['noun']} $object." );
            }
        }
        return array( $count_files_to_remove, $count_files_removed );
    }

    private static function uninstall_outcome( int $to_remove, int $removed, string $language_code, string $object, string $noun, Context $ctx ): array {
        if ( $to_remove === $removed ) {
            $ctx->out->log( "Language '{$language_code}' for '{$object}' uninstalled." );
            return array( 'uninstalled', 'successes' );
        }
        if ( $removed ) {
            $ctx->out->log( "Language '{$language_code}' for '{$object}' partially uninstalled." );
            return array( 'partial uninstall', 'errors' );
        }
        if ( $to_remove > 0 ) {
            $ctx->out->log( "Couldn't uninstall language '{$language_code}' from {$noun} {$object}." );
            return array( 'failed to uninstall', 'errors' );
        }
        $ctx->out->log( "Language '{$language_code}' for '{$object}' already uninstalled." );
        return array( 'already uninstalled', 'skips' );
    }

    public function object_update( Invocation $inv, Context $ctx ): void {
        $type = self::type_of( $inv );
        $noun = self::noun( $type );
        $args = array_map( 'strval', array_values( $inv->args ) );
        $all  = Read_Value::flag( $inv->assoc, 'all', false );

        if ( ! $all && empty( $args ) ) {
            $ctx->out->error( "Please specify one or more {$noun}s, or use --all." );
        }

        if ( $all ) {
            $args = $this->all_object_names( $type );
            if ( empty( $args ) ) {
                $ctx->out->success( "No {$noun}s installed." );

                return;
            }
        }

        $this->update( $type, $args, $inv, $ctx );
    }

    public function describe_core_install( Invocation $inv ): array {
        $available = $this->get_installed_languages( 'core' );
        $lines     = array();
        foreach ( array_map( 'strval', array_values( $inv->args ) ) as $code ) {
            $lines[] = in_array( $code, $available, true )
                ? sprintf( "Language pack '%s' for WordPress core: installed → nothing to download", $code )
                : sprintf( "Language pack '%s' for WordPress core: not installed → download from wordpress.org and install", $code );
        }
        return $lines;
    }

    public function describe_core_uninstall( Invocation $inv ): array {
        $available = $this->get_installed_languages( 'core' );
        $locale    = (string) $this->wp( 'locale' );
        $lines     = array();
        foreach ( array_map( 'strval', array_values( $inv->args ) ) as $code ) {
            if ( ! in_array( $code, $available, true ) ) {
                $lines[] = sprintf( "Language pack '%s' for WordPress core: not installed → nothing to delete", $code );
            } elseif ( $code === $locale ) {
                $lines[] = sprintf( "Language pack '%s' for WordPress core: installed and active → nothing deleted (WP-CLI stops here)", $code );
            } else {
                $lines[] = sprintf( "Language pack '%s' for WordPress core: installed → delete its files from the languages folder", $code );
            }
        }
        return $lines;
    }

    public function describe_core_update( Invocation $inv ): array {
        return array( self::describe_update_line( 'WordPress core', $this->get_installed_languages( 'core' ), (bool) Read_Value::flag( $inv->assoc, 'dry-run' ) ) );
    }

    public function describe_object_install( Invocation $inv ): array {
        $type  = self::type_of( $inv );
        $lines = array();
        foreach ( $this->pairs( $inv, $type ) as $pair ) {
            list( $object, $code ) = $pair;
            $installed             = in_array( $code, $this->get_installed_languages( $type, $object ), true );
            $lines[]               = sprintf( "Language pack '%s' for %s '%s': %s", $code, self::noun( $type ), $object, $installed ? 'installed → nothing to download' : 'not installed → download from wordpress.org and install' );
        }
        return $lines;
    }

    public function describe_object_uninstall( Invocation $inv ): array {
        $type   = self::type_of( $inv );
        $locale = (string) $this->wp( 'locale' );
        $lines  = array();
        foreach ( $this->pairs( $inv, $type ) as $pair ) {
            list( $object, $code ) = $pair;
            if ( ! in_array( $code, $this->get_installed_languages( $type, $object ), true ) ) {
                $state = 'not installed → nothing to delete';
            } elseif ( $code === $locale ) {
                $state = 'installed and active → nothing deleted (WP-CLI stops here)';
            } else {
                $state = 'installed → delete its files from the languages folder';
            }
            $lines[] = sprintf( "Language pack '%s' for %s '%s': %s", $code, self::noun( $type ), $object, $state );
        }
        return $lines;
    }

    public function describe_object_update( Invocation $inv ): array {
        $type    = self::type_of( $inv );
        $dry_run = (bool) Read_Value::flag( $inv->assoc, 'dry-run' );
        $objects = Read_Value::flag( $inv->assoc, 'all', false ) ? $this->all_object_names( $type ) : array_map( 'strval', array_values( $inv->args ) );
        $lines   = array();
        foreach ( $objects as $object ) {
            $lines[] = self::describe_update_line( self::noun( $type ) . " '{$object}'", $this->get_installed_languages( $type, $object ), $dry_run );
        }
        return $lines;
    }

    private static function describe_update_line( string $label, array $installed, bool $dry_run ): string {
        $codes = array_values( array_diff( $installed, array( 'en_US' ) ) );
        sort( $codes );
        if ( ! $codes ) {
            return sprintf( 'Language packs for %s: none installed → nothing to update', $label );
        }
        return sprintf( 'Language packs for %s: %s → %s', $label, implode( ', ', $codes ), $dry_run ? 'dry run, nothing written' : 'replaced where wordpress.org offers a newer pack' );
    }

    private function update( string $type, array $args, Invocation $inv, Context $ctx ): void {
        $dry_run = (bool) Read_Value::flag( $inv->assoc, 'dry-run' );
        $updates = $this->get_translation_updates( $type, $ctx );

        if ( empty( $updates ) ) {
            $ctx->out->success( 'Translations are up to date.' );

            return;
        }

        if ( empty( $args ) ) {
            $args = array( null );
        }

        $results       = array();
        $num_to_update = 0;

        foreach ( $args as $slug ) {
            $available_updates = $this->updates_for( $type, $slug, $updates, $ctx );

            if ( ! is_array( $available_updates ) ) {
                continue;
            }

            $num_to_update += count( $available_updates );

            if ( ! $dry_run ) {
                $results = array_merge( $results, $this->apply_updates( $available_updates, $ctx ) );
            }
        }

        if ( $dry_run ) {
            $this->print_dry_run( $updates, $ctx );

            return;
        }

        self::report_updates( $results, $num_to_update, $ctx );
    }

    private function updates_for( string $type, ?string $slug, array $updates, Context $ctx ): ?array {
        $all_languages = $this->get_all_languages( $type, $slug, $ctx );

        $updates_per_type = array();

        foreach ( $updates as $update ) {
            if ( null !== $slug && $update->slug !== $slug ) {
                continue;
            }

            $this->label_update( $update, $all_languages );

            if ( ! isset( $updates_per_type[ $update->type ] ) ) {
                $updates_per_type[ $update->type ] = array();
            }
            $updates_per_type[ $update->type ][] = $update;
        }

        $obj_type = rtrim( $type, 's' );
        return isset( $updates_per_type[ $obj_type ] ) ? $updates_per_type[ $obj_type ] : null;
    }

    private function label_update( object $update, array $all_languages ): void {
        $name = 'WordPress';

        if ( 'plugin' === $update->type ) {
            $plugin_data = $this->wp( 'plugin_data', array( (string) $update->slug ) );
            $name        = is_array( $plugin_data ) && isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : null;
        } elseif ( 'theme' === $update->type ) {
            $theme_data = (array) $this->wp( 'theme_data', array( (string) $update->slug ) );
            $name       = isset( $theme_data['Name'] ) ? $theme_data['Name'] : null;
        }

        $translation = self::list_filter( $all_languages, array( 'language' => $update->language ) );
        $translation = (object) reset( $translation );

        $update->Type    = ucfirst( (string) $update->type ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP-CLI's column names.
        $update->Name    = $name; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP-CLI's column names.
        $update->Version = $update->version; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP-CLI's column names.

        if ( isset( $translation->english_name ) ) {
            $update->Language = $translation->english_name; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP-CLI's column names.
        }
    }

    private function apply_updates( array $available_updates, Context $ctx ): array {
        $results = array();
        foreach ( $available_updates as $update ) {
            $language = property_exists( $update, 'Language' ) ? $update->Language : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP-CLI's column names.
            $ctx->out->line( "Updating '{$language}' translation for {$update->Name} {$update->Version}..." ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP-CLI's column names.

            $upgraded = (array) $this->wp( 'upgrade_language_pack', array( (array) $update, array() ) );
            self::print_feedback( $ctx, (array) ( $upgraded['feedback'] ?? array() ) );
            $result = $upgraded['result'] ?? null;
            if ( true === $result ) {
                $ctx->mark_changed();
                $ctx->not_captured( self::NOT_CAPTURED_FILES );
            }

            $results[] = $result;
        }
        return $results;
    }

    private function print_dry_run( array $updates, Context $ctx ): void {
        $update_count = count( $updates );

        $ctx->out->line(
            sprintf(
                'Found %d translation %s that would be processed:',
                $update_count,
                self::pluralize( 'update', $update_count )
            )
        );

        $this->format_items( $ctx, 'table', $updates, array( 'Type', 'Name', 'Version', 'Language' ) );
    }

    private static function report_updates( array $results, int $num_to_update, Context $ctx ): void {
        $num_updated = count( array_filter( $results ) );

        $line = sprintf( "Updated $num_updated/$num_to_update %s.", self::pluralize( 'translation', $num_updated ) );

        if ( $num_to_update === $num_updated ) {
            $ctx->out->success( $line );
        } elseif ( $num_updated > 0 ) {
            $ctx->out->warning( $line );
        } else {
            $ctx->out->error( $line );
        }
    }

    private function get_translation_updates( string $type, Context $ctx ): array {
        $available = $this->get_installed_languages( $type );
        $updates   = array();
        foreach ( (array) $this->wp( 'translation_updates', array( $type, $available ) ) as $translation ) {
            $updates[] = (object) $translation;
        }
        $ctx->note( sprintf( self::NOTE_UPDATE_CHECK, 'plugins' === $type ? 'update_plugins' : ( 'themes' === $type ? 'update_themes' : 'update_core' ) ) );
        return $updates;
    }

    private function get_installed_languages( string $type, string $slug = 'default' ): array {
        $available   = (array) $this->wp( 'installed_translations', array( $type ) );
        $available   = ! empty( $available[ $slug ] ) ? array_map( 'strval', array_keys( (array) $available[ $slug ] ) ) : array();
        $available[] = 'en_US';

        return $available;
    }

    private function get_all_languages( string $type, ?string $slug, Context $ctx ): array {
        $args = array(
            'version' => $this->wp( 'wp_version_value' ),
        );

        if ( $slug ) {
            $args['slug']    = $slug;
            $args['version'] = $this->object_version( $type, $slug, (string) $args['version'] );
        }

        $response = $this->wp( 'translations_api', array( $type, $args ) );

        if ( is_array( $response ) && isset( $response['wp_error'] ) ) {
            $ctx->out->error( self::error_to_string( (array) $response['wp_error'] ) );
        }

        $translations = is_array( $response ) && ! empty( $response['translations'] ) ? (array) $response['translations'] : array();

        $en_us = array(
            'language'     => 'en_US',
            'english_name' => 'English (United States)',
            'native_name'  => 'English (United States)',
            'updated'      => '',
        );

        $translations[] = $en_us;

        uasort(
            $translations,
            static function ( $a, $b ) {
                return strnatcasecmp( (string) $a['language'], (string) $b['language'] );
            }
        );

        return $translations;
    }

    private function object_version( string $type, string $slug, string $wp_version ): string {
        if ( 'plugins' === $type ) {
            $plugin_data = $this->wp( 'plugin_data', array( $slug ) );
            if ( is_array( $plugin_data ) && isset( $plugin_data['Version'] ) ) {
                return $plugin_data['Version'];
            }
        } elseif ( 'themes' === $type ) {
            $theme_data = (array) $this->wp( 'theme_data', array( $slug ) );
            if ( isset( $theme_data['Version'] ) ) {
                return $theme_data['Version'];
            }
        }
        return $wp_version;
    }

    private function download_language_pack( string $type, string $download, ?string $slug, Context $ctx ) {
        $translation_to_load = self::find_language( $this->get_all_languages( $type, $slug, $ctx ), $download );

        if ( ! $translation_to_load ) {
            return self::wp_error( 'not_found', $slug ? "Language '{$download}' for '{$slug}' not available." : "Language '{$download}' not available." );
        }

        $translation         = $translation_to_load;
        $translation['type'] = rtrim( $type, 's' );

        if ( ! isset( $translation['slug'] ) ) {
            $translation['slug'] = $slug;
        }

        $upgraded = (array) $this->wp( 'upgrade_language_pack', array( $translation, array( 'clear_update_cache' => false ) ) );
        self::print_feedback( $ctx, (array) ( $upgraded['feedback'] ?? array() ) );
        $result = $upgraded['result'] ?? null;

        if ( is_array( $result ) && isset( $result['wp_error'] ) ) {
            return (array) $result['wp_error'];
        }

        if ( ! $result ) {
            return self::wp_error( 'not_installed', $slug ? "Could not install language '{$download}' for '{$slug}'." : "Could not install language '{$download}'." );
        }

        $ctx->mark_changed();
        $ctx->not_captured( self::NOT_CAPTURED_FILES );
        return (string) $translation['language'];
    }

    private static function find_language( array $translations, string $download ): ?array {
        foreach ( $translations as $translation ) {
            if ( $translation['language'] === $download ) {
                return $translation;
            }
        }
        return null;
    }

    private function install_one( string $type, array $args, Context $ctx ): void {
        $object         = (string) array_shift( $args );
        $language_codes = $args;
        $count          = count( $language_codes );

        $available = $this->get_installed_languages( $type, $object );

        $successes = 0;
        $errors    = 0;
        $skips     = 0;
        $missing   = 0;
        foreach ( $language_codes as $language_code ) {

            if ( in_array( $language_code, $available, true ) ) {
                $ctx->out->log( "Language '{$language_code}' already installed." );
                ++$skips;
            } else {
                $response = $this->download_language_pack( $type, $language_code, $object, $ctx );

                if ( is_array( $response ) ) {
                    $ctx->out->warning( self::error_to_string( $response ) );
                    $ctx->out->log( "Language '{$language_code}' not installed." );

                    if ( 'not_found' === $response['code'] ) {
                        ++$skips;
                        ++$missing;
                    } else {
                        ++$errors;
                    }
                } else {
                    $ctx->out->log( "Language '{$language_code}' installed." );
                    ++$successes;
                }
            }
        }

        $this->finish_install( $ctx, $count, $successes, $errors, $skips, $missing, self::noun( $type ) . " '{$object}'" );
    }

    private function install_many( string $type, array $args, array $assoc_args, Context $ctx ): void {
        $language_codes = $args;
        $objects        = (array) $this->wp( 'all_objects', array( $type ) );

        $assoc_args['format'] = self::batch_format( $assoc_args, $ctx );

        if ( empty( $objects ) ) {
            $ctx->out->success( 'No ' . self::noun( $type ) . 's installed.' );
            return;
        }

        $count = count( $objects ) * count( $language_codes );

        $results = array();

        $successes = 0;
        $errors    = 0;
        $skips     = 0;
        $missing   = 0;
        foreach ( $objects as $path => $details ) {
            $object_name  = self::object_name( $type, (string) $path );
            $display_name = isset( $details['Name'] ) ? (string) $details['Name'] : '';

            $available = $this->get_installed_languages( $type, $object_name );

            foreach ( $language_codes as $language_code ) {
                $result = array(
                    'name'   => $object_name,
                    'locale' => $language_code,
                );

                if ( in_array( $language_code, $available, true ) ) {
                    $ctx->out->log( "Language '{$language_code}' for '{$display_name}' already installed." );
                    $result['status'] = 'already installed';
                    ++$skips;
                } else {
                    $response = $this->download_language_pack( $type, $language_code, $object_name, $ctx );

                    if ( is_array( $response ) ) {
                        $ctx->out->warning( self::error_to_string( $response ) );
                        $ctx->out->log( "Language '{$language_code}' for '{$display_name}' not installed." );

                        if ( 'not_found' === $response['code'] ) {
                            $result['status'] = 'not available';
                            ++$skips;
                            ++$missing;
                        } else {
                            $result['status'] = 'not installed';
                            ++$errors;
                        }
                    } else {
                        $ctx->out->log( "Language '{$language_code}' for '{$display_name}' installed." );
                        $result['status'] = 'installed';
                        ++$successes;
                    }
                }

                $results[] = (object) $result;
            }
        }

        if ( 'summary' !== $assoc_args['format'] ) {
            $this->format_items( $ctx, (string) $assoc_args['format'], $results, array( 'name', 'locale', 'status' ) );
        }

        $this->finish_install( $ctx, $count, $successes, $errors, $skips, $missing, 'installed ' . self::noun( $type ) . 's' );
    }

    private static function batch_format( array $assoc_args, Context $ctx ): string {
        $format = empty( $assoc_args['format'] ) ? 'table' : $assoc_args['format'];
        if ( in_array( $format, array( 'json', 'csv' ), true ) ) {
            $ctx->out->set_quiet( true );
        }
        return (string) $format;
    }

    private function finish_install( Context $ctx, int $count, int $successes, int $errors, int $skips, int $missing, string $what ): void {
        Values::report_batch( $ctx->out, 'language', 'install', $count, $successes, $errors, $skips );
        if ( $count > 0 && $missing === $count ) {
            $ctx->fail_missing_target( sprintf( self::MISSING_PACK, $what ) );
        }
    }

    private function display_list( Invocation $inv, Context $ctx, string $type, array $translations ): void {
        $assoc_args = $inv->assoc;
        if ( count( $translations ) > self::ROW_CAP && 'count' !== Read_Value::flag( $assoc_args, 'format' ) ) {
            $ctx->out->error( sprintf( self::REASON_ROW_CAP, self::ROW_CAP, $inv->command, count( $translations ), 'core' === $type ? 'filters' : self::noun( $type ) . 's' ) );
        }
        $formatter = new Formatter( $ctx->out, $assoc_args, self::OBJ_FIELDS[ $type ], $type );
        $formatter->display_items( $translations );
    }

    private function refuse_unknown_filters( Invocation $inv, Context $ctx, array $rows ): void {
        if ( ! $rows ) {
            return;
        }
        $fields = array();
        foreach ( $rows as $row ) {
            foreach ( array_keys( $row ) as $field ) {
                $fields[ (string) $field ] = true;
            }
        }
        foreach ( array_keys( $inv->assoc ) as $key ) {
            $key = (string) $key;
            if ( in_array( $key, array( 'field', 'fields', 'format', 'all' ), true ) ) {
                continue;
            }
            if ( ! isset( $fields[ $key ] ) ) {
                $ctx->out->error( sprintf( self::REASON_FILTER, $key, implode( ', ', array_keys( $fields ) ) ) );
            }
        }
    }

    private function format_items( Context $ctx, string $format, array $items, array $fields ): void {
        $assoc_args = array(
            'format' => $format,
            'fields' => $fields,
        );
        $formatter  = new Formatter( $ctx->out, $assoc_args );
        $formatter->display_items( $items );
    }

    private function require_language_files( Invocation $inv, Context $ctx, string $cap ): void {
        if ( function_exists( 'wp_is_file_mod_allowed' ) && ! \wp_is_file_mod_allowed( 'can_install_language_pack' ) ) {
            $ctx->out->error( sprintf( self::REASON_FILE_MODS, $cap, $inv->command ) );
        }
        $ctx->require_cap( $cap );
        $method = (string) $this->wp( 'filesystem_method' );
        if ( 'direct' !== $method ) {
            $ctx->out->error( sprintf( self::REASON_FILESYSTEM, $method ) );
        }
    }

    private function enforce_pack_cap( Invocation $inv, Context $ctx, string $type, string $verb, int $cap ): void {
        $args = array_values( $inv->args );
        if ( Read_Value::flag( $inv->assoc, 'all', false ) ) {
            $objects = count( $this->all_object_names( $type ) );
            $count   = $objects * count( $args );
            $detail  = sprintf( ' (%d %ss × %d languages)', $objects, self::noun( $type ), count( $args ) );
        } else {
            $count  = max( 0, count( $args ) - 1 );
            $detail = '';
        }
        if ( $count > $cap ) {
            $ctx->out->error( sprintf( self::REASON_OVER_CAP, $verb, $cap, $inv->command, $count, $detail ) );
        }
    }

    private function enforce_object_cap( Invocation $inv, Context $ctx ): void {
        $type  = self::type_of( $inv );
        $count = Read_Value::flag( $inv->assoc, 'all', false ) ? count( $this->all_object_names( $type ) ) : count( $inv->args );
        if ( $count > self::OBJECT_CAP ) {
            $ctx->out->error( sprintf( self::REASON_OBJECTS_CAP, self::OBJECT_CAP, self::noun( $type ) . 's', $inv->command, $count ) );
        }
    }

    private function pairs( Invocation $inv, string $type ): array {
        $args = array_map( 'strval', array_values( $inv->args ) );
        if ( Read_Value::flag( $inv->assoc, 'all', false ) ) {
            $objects = $this->all_object_names( $type );
        } else {
            $objects = array( (string) array_shift( $args ) );
        }
        $pairs = array();
        foreach ( $objects as $object ) {
            foreach ( $args as $code ) {
                $pairs[] = array( $object, $code );
            }
        }
        return $pairs;
    }

    private function all_object_names( string $type ): array {
        $names = array();
        foreach ( array_keys( (array) $this->wp( 'all_objects', array( $type ) ) ) as $path ) {
            $names[] = self::object_name( $type, (string) $path );
        }
        return $names;
    }

    private static function object_name( string $type, string $path ): string {
        if ( 'themes' === $type ) {
            return basename( $path );
        }
        if ( false === strpos( $path, '/' ) ) {
            return basename( $path, '.php' );
        }
        return dirname( $path );
    }

    private static function type_of( Invocation $inv ): string {
        return 0 === strpos( $inv->command, 'language theme ' ) ? 'themes' : 'plugins';
    }

    private static function noun( string $type ): string {
        return 'themes' === $type ? 'theme' : 'plugin';
    }

    private static function list_filter( array $list, array $args ): array {
        $out = array();
        foreach ( $list as $key => $obj ) {
            $matched = 0;
            foreach ( $args as $m_key => $m_value ) {
                if ( is_array( $obj ) ) {
                    // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- WP_List_Util::filter() compares loosely.
                    if ( array_key_exists( $m_key, $obj ) && ( $m_value == $obj[ $m_key ] ) ) {
                        ++$matched;
                    }
                } elseif ( is_object( $obj ) ) {
                    // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- WP_List_Util::filter() compares loosely.
                    if ( isset( $obj->{$m_key} ) && ( $m_value == $obj->{$m_key} ) ) {
                        ++$matched;
                    }
                }
            }
            if ( count( $args ) === $matched ) {
                $out[ $key ] = $obj;
            }
        }
        return $out;
    }

    private static function pluralize( string $noun, int $count ): string {
        return 1 === $count ? $noun : $noun . 's';
    }

    private static function wp_error( string $code, string $message ): array {
        return array(
            'code'     => $code,
            'messages' => array( $message ),
            'data'     => '',
        );
    }

    public static function wp_error_array( \WP_Error $error ): array {
        $data = $error->get_error_data();
        return array(
            'code'     => (string) $error->get_error_code(),
            'messages' => array_map( 'strval', (array) $error->get_error_messages() ),
            'data'     => ( is_scalar( $data ) || is_array( $data ) || null === $data ) ? $data : json_decode( (string) json_encode( $data ), true ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain-data copy.
        );
    }

    public static function error_to_string( array $error ): string {
        $data = $error['data'] ?? null;
        foreach ( (array) ( $error['messages'] ?? array() ) as $message ) {
            if ( $data ) {
                return $message . ' ' . ( is_array( $data ) || is_object( $data ) ? (string) json_encode( $data ) : '"' . $data . '"' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WP-CLI's exact call.
            }
            return (string) $message;
        }
        return '';
    }

    public static function print_feedback( Context $ctx, array $feedback ): void {
        foreach ( $feedback as $entry ) {
            if ( ! is_array( $entry ) || ! isset( $entry[0], $entry[1] ) ) {
                continue;
            }
            if ( 'warning' === $entry[0] ) {
                $ctx->out->warning( (string) $entry[1] );
            } else {
                $ctx->out->log( (string) $entry[1] );
            }
        }
    }

    public static function new_skin( \ArrayObject $sink ): \WP_Upgrader_Skin {
        return new class( $sink ) extends \WP_Upgrader_Skin {

            public $language_update;

            public $api;

            private \ArrayObject $sink;

            public function __construct( \ArrayObject $sink ) {
                parent::__construct();
                $this->sink = $sink;
            }

            public function header() {}

            public function footer() {}

            public function bulk_header() {}

            public function bulk_footer() {}

            public function error( $error ) {
                if ( ! $error ) {
                    return;
                }
                if ( is_string( $error ) && isset( $this->upgrader->strings[ $error ] ) ) {
                    $error = $this->upgrader->strings[ $error ];
                }
                $this->sink[] = array( 'warning', is_string( $error ) ? $error : ( \is_wp_error( $error ) ? Language_Handler::error_to_string( Language_Handler::wp_error_array( $error ) ) : '' ) );
            }

            public function feedback( $feedback, ...$args ) {
                $string = $feedback;
                if ( isset( $this->upgrader->strings[ $string ] ) ) {
                    $string = $this->upgrader->strings[ $string ];
                }
                if ( ! empty( $args ) && false !== strpos( (string) $string, '%' ) ) {
                    $string = vsprintf( (string) $string, $args );
                }
                if ( empty( $string ) ) {
                    return;
                }
                $string       = str_replace( '&#8230;', '...', \wp_strip_all_tags( (string) $string ) );
                $string       = html_entity_decode( $string, ENT_QUOTES, \get_bloginfo( 'charset' ) );
                $this->sink[] = array( 'log', $string );
            }
        };
    }

    protected function wp( string $call, array $args = array() ) {
        return $this->{'call_' . $call}( ...$args );
    }

    protected function call_installed_translations( string $type ): array {
        return (array) \wp_get_installed_translations( $type );
    }

    protected function call_locale(): string {
        return (string) \get_locale();
    }

    protected function call_wp_version_value(): string {
        require ABSPATH . WPINC . '/version.php';
        return isset( $wp_version ) ? (string) $wp_version : '';
    }

    protected function call_plugin_data( string $slug ): ?array {
        self::load_plugin_functions();
        $plugins     = \get_plugins( '/' . $slug );
        $plugin_data = array_shift( $plugins );
        if ( ! is_array( $plugin_data ) ) {
            return null;
        }
        return array(
            'Name'    => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : null,
            'Version' => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : null,
        );
    }

    protected function call_theme_data( string $slug ): array {
        $theme = \wp_get_theme( $slug );
        return array(
            'Name'    => isset( $theme['Name'] ) ? $theme['Name'] : null,
            'Version' => isset( $theme['Version'] ) ? $theme['Version'] : null,
        );
    }

    protected function call_translations_api( string $type, array $args ): array {
        if ( ! function_exists( 'translations_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        }
        $response = \translations_api( $type, $args );
        if ( \is_wp_error( $response ) ) {
            return array( 'wp_error' => self::wp_error_array( $response ) );
        }
        return is_array( $response ) ? $response : array();
    }

    protected function call_translation_updates( string $type, array $available ): array {
        if ( ! function_exists( 'wp_update_plugins' ) ) {
            require_once ABSPATH . WPINC . '/update.php';
        }
        $func = static function () use ( $available ) {
            return $available;
        };

        switch ( $type ) {
            case 'plugins':
                self::load_plugin_functions();
                \add_filter( 'plugins_update_check_locales', $func );
                \wp_clean_plugins_cache();
                \wp_update_plugins();
                \remove_filter( 'plugins_update_check_locales', $func );
                $transient = 'update_plugins';
                break;
            case 'themes':
                \add_filter( 'themes_update_check_locales', $func );
                \wp_clean_themes_cache();
                \wp_update_themes();
                \remove_filter( 'themes_update_check_locales', $func );
                $transient = 'update_themes';
                break;
            default:
                \delete_site_transient( 'update_core' );
                \wp_version_check();
                $transient = 'update_core';
                break;
        }

        $transient = \get_site_transient( $transient );
        if ( ! is_object( $transient ) || empty( $transient->translations ) ) {
            return array();
        }
        $updates = array();
        foreach ( (array) $transient->translations as $translation ) {
            $updates[] = (array) $translation;
        }
        return $updates;
    }

    protected function call_all_objects( string $type ): array {
        $out = array();
        if ( 'themes' === $type ) {
            foreach ( \wp_get_themes() as $stylesheet => $theme ) {
                $out[ (string) $stylesheet ] = array( 'Name' => (string) $theme['Name'] );
            }
            return $out;
        }
        self::load_plugin_functions();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, as WP-CLI reads it.
        foreach ( (array) \apply_filters( 'all_plugins', \get_plugins() ) as $file => $headers ) {
            $out[ (string) $file ] = array( 'Name' => isset( $headers['Name'] ) ? (string) $headers['Name'] : '' );
        }
        return $out;
    }

    protected function call_object_exists( string $type, string $name ): bool {
        return (bool) Fetchers::of( self::noun( $type ) )->get( $name );
    }

    protected function call_lang_dir(): string {
        return (string) WP_LANG_DIR;
    }

    protected function call_dir_entries( string $dir ) {
        $files = is_dir( $dir ) ? scandir( $dir ) : false;
        if ( ! $files ) {
            return false;
        }
        $out = array();
        foreach ( $files as $file ) {
            if ( '.' !== $file[0] && is_dir( $dir . '/' . $file ) ) {
                continue;
            }
            $out[] = (string) $file;
        }
        return $out;
    }

    protected function call_filesystem_init(): bool {
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        return (bool) \WP_Filesystem();
    }

    protected function call_delete_file( string $path ): bool {
        global $wp_filesystem;
        return is_object( $wp_filesystem ) && (bool) $wp_filesystem->delete( $path );
    }

    protected function call_filesystem_method(): string {
        if ( ! function_exists( 'get_filesystem_method' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        return (string) \get_filesystem_method( array(), WP_LANG_DIR );
    }

    protected function call_upgrade_language_pack( array $translation, array $upgrade_args ): array {
        if ( ! function_exists( 'request_filesystem_credentials' ) ) {
            require_once ABSPATH . 'wp-admin/includes/admin.php';
        }
        if ( ! class_exists( 'Language_Pack_Upgrader', false ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        $sink     = new \ArrayObject();
        $upgrader = new class( self::new_skin( $sink ) ) extends \Language_Pack_Upgrader {
            public function upgrade_strings() {
                $switched_locale = function_exists( 'switch_to_locale' ) && \switch_to_locale( 'en_US' );
                parent::upgrade_strings();
                if ( $switched_locale ) {
                    \restore_previous_locale();
                }
            }

            public function generic_strings() {
                $switched_locale = function_exists( 'switch_to_locale' ) && \switch_to_locale( 'en_US' );
                parent::generic_strings();
                if ( $switched_locale ) {
                    \restore_previous_locale();
                }
            }
        };
        $result = $upgrader->upgrade( (object) $translation, $upgrade_args );
        if ( \is_wp_error( $result ) ) {
            $shape = array( 'wp_error' => self::wp_error_array( $result ) );
        } elseif ( $result ) {
            $shape = true;
        } else {
            $shape = null === $result ? null : false;
        }
        return array(
            'result'   => $shape,
            'feedback' => $sink->getArrayCopy(),
        );
    }

    private static function load_plugin_functions(): void {
        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'wp_clean_plugins_cache' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
}
