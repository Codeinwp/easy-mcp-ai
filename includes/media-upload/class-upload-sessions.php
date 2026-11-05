<?php
namespace Easy_MCP_AI\Media_Upload;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Upload_Sessions {

    const PREFIX = 'easy_mcp_ai_media_upload_';

    const TTL = 900;

    const MAX_OPEN = 10;

    const FOLDER = 'easy-mcp-ai-incoming';

    const ORPHAN_AGE = 3600;

    const SWEEP_PAGE = 500;

    const SWEEP_PAGES = 20;

    const STATUS_PENDING   = 'pending';
    const STATUS_RECEIVING = 'receiving';
    const STATUS_RECEIVED  = 'received';

    private $now;

    public function __construct( ?callable $now = null ) {
        $this->now = is_callable( $now ) ? $now : 'time';
    }

    public function time(): int {
        return (int) call_user_func( $this->now );
    }

    public static function new_id(): string {
        return bin2hex( random_bytes( 16 ) );
    }

    public static function new_key(): string {
        return bin2hex( random_bytes( 32 ) );
    }

    public static function valid_id( $id ): bool {
        return is_string( $id ) && (bool) preg_match( '/^[a-f0-9]{32}\z/', $id );
    }

    public static function credential_key( array $identity ): string {
        $user = isset( $identity['wp_user_id'] ) ? (int) $identity['wp_user_id'] : 0;
        if ( isset( $identity['auth_source'] ) && 'oauth' === $identity['auth_source'] ) {
            $client = isset( $identity['oauth_client_id'] ) && is_string( $identity['oauth_client_id'] ) ? $identity['oauth_client_id'] : '';
            return 'oauth:' . $client . ':' . $user;
        }
        return 'legacy:' . ( isset( $identity['token_id'] ) ? (int) $identity['token_id'] : 0 ) . ':' . $user;
    }

    public function is_expired( array $session ): bool {
        return (int) $session['expires'] <= $this->time();
    }

    public static function key_matches( array $session, $key ): bool {
        return is_string( $key ) && '' !== $key && hash_equals( (string) $session['key_hash'], hash( 'sha256', $key ) );
    }

    public function create( array $session ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a conditional row cannot go through the options cache.
        return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::PREFIX . $session['id'], self::encode( $session ) ) );
    }

    public function find( string $id ): ?array {
        if ( ! self::valid_id( $id ) ) {
            return null;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read past the options cache: another request may have changed the row.
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::PREFIX . $id ) );
        return is_string( $raw ) ? self::decode( $raw, $id ) : null;
    }

    public function replace( array $old, array $new ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a conditional update cannot go through the options cache.
        return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", self::encode( $new ), self::PREFIX . $old['id'], (string) $old['_raw'] ) );
    }

    public function swap( array $old, array $new ): ?array {
        if ( ! $this->replace( $old, $new ) ) {
            return null;
        }
        $new['_raw'] = self::encode( $new );
        return $new;
    }

    public function remove( array $session ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a conditional delete cannot go through the options cache.
        return 1 === (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::PREFIX . $session['id'], (string) $session['_raw'] ) );
    }

    public function discard( array $session ): bool {
        if ( ! $this->remove( $session ) ) {
            return false;
        }
        $this->delete_part( (string) $session['part'] );
        return true;
    }

    public function all( int $limit = self::SWEEP_PAGE ): array {
        return self::decode_rows( $this->rows_after( '', $limit ) );
    }

    protected function rows_after( string $after, int $limit ): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the sessions are never in the options cache.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name > %s ORDER BY option_name LIMIT %d", $wpdb->esc_like( self::PREFIX ) . '%', self::PREFIX . $after, $limit ), ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }

    private static function decode_rows( array $rows ): array {
        $out = array();
        foreach ( $rows as $row ) {
            $session = self::decode( (string) $row['option_value'], self::row_id( $row ) );
            if ( null !== $session ) {
                $out[] = $session;
            }
        }
        return $out;
    }

    private static function row_id( array $row ): string {
        return substr( (string) $row['option_name'], strlen( self::PREFIX ) );
    }

    public function owned_by( string $owner ): array {
        global $wpdb;
        $needle = substr( (string) \wp_json_encode( array( 'owner' => $owner ), JSON_UNESCAPED_SLASHES ), 1, -1 );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the sessions are never in the options cache.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s", $wpdb->esc_like( self::PREFIX ) . '%', '%' . $wpdb->esc_like( $needle ) . '%' ), ARRAY_A );
        $out  = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $session = self::decode( (string) $row['option_value'], substr( (string) $row['option_name'], strlen( self::PREFIX ) ) );
            if ( null !== $session && $owner === $session['owner'] ) {
                $out[] = $session;
            }
        }
        return $out;
    }

    public function open_count( string $owner ): int {
        $count = 0;
        foreach ( $this->owned_by( $owner ) as $session ) {
            if ( $this->is_expired( $session ) ) {
                if ( $this->discard( $session ) ) {
                    continue;
                }
                if ( null === $this->find( (string) $session['id'] ) ) {
                    continue;
                }
            }
            ++$count;
        }
        return $count;
    }

    public function sweep(): int {
        $deleted = 0;
        $after   = '';
        for ( $page = 0; $page < self::SWEEP_PAGES; $page++ ) {
            $rows = $this->rows_after( $after, self::SWEEP_PAGE );
            foreach ( self::decode_rows( $rows ) as $session ) {
                if ( $this->is_expired( $session ) && $this->discard( $session ) ) {
                    ++$deleted;
                }
            }
            if ( count( $rows ) < self::SWEEP_PAGE ) {
                break;
            }
            $after = self::row_id( (array) end( $rows ) );
        }
        $folder = $this->folder( false );
        if ( '' !== $folder ) {
            foreach ( (array) glob( $folder . '/*.part' ) as $file ) {
                $mtime = is_string( $file ) ? filemtime( $file ) : false;
                if ( false !== $mtime && $mtime < $this->time() - self::ORPHAN_AGE ) {
                    \wp_delete_file( $file );
                }
            }
        }
        return $deleted;
    }

    public static function daily_sweep(): void {
        ( new self() )->sweep();
    }

    public function purge(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the sessions are never in the options cache.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::PREFIX ) . '%' ) );
        $folder = $this->folder( false );
        if ( '' === $folder ) {
            return;
        }
        foreach ( array_merge( (array) glob( $folder . '/*.part' ), array( $folder . '/index.php', $folder . '/.htaccess' ) ) as $file ) {
            if ( is_string( $file ) && is_file( $file ) ) {
                \wp_delete_file( $file );
            }
        }
        if ( array( '.', '..' ) === array_values( (array) scandir( $folder ) ) ) {
            rmdir( $folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- an empty folder this class made.
        }
    }

    public function folder( bool $create = true ): string {
        $uploads = function_exists( 'wp_get_upload_dir' ) ? \wp_get_upload_dir() : array();
        if ( empty( $uploads['basedir'] ) || ! empty( $uploads['error'] ) ) {
            return '';
        }
        $folder = rtrim( (string) $uploads['basedir'], '/\\' ) . '/' . self::FOLDER;
        if ( is_dir( $folder ) ) {
            return $folder;
        }
        if ( ! $create || ! \wp_mkdir_p( $folder ) ) {
            return '';
        }
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- two small guard files in a folder this class owns.
        file_put_contents( $folder . '/index.php', "<?php\n// Silence is golden.\n" );
        file_put_contents( $folder . '/.htaccess', "# Easy MCP AI: files waiting for wp_upload_media. Never served.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
        // phpcs:enable
        return $folder;
    }

    public static function new_part( string $id ): string {
        return $id . '-' . bin2hex( random_bytes( 8 ) ) . '.part';
    }

    public static function valid_part( $part ): bool {
        return is_string( $part ) && (bool) preg_match( '/^[a-f0-9]{32}-[a-f0-9]{16}\.part\z/', $part );
    }

    public function part_path( string $part ): string {
        $folder = $this->folder( false );
        return '' !== $folder && self::valid_part( $part ) ? $folder . '/' . $part : '';
    }

    public function delete_part( string $part ): void {
        $path = $this->part_path( $part );
        if ( '' !== $path && file_exists( $path ) ) {
            \wp_delete_file( $path );
        }
    }

    protected static function encode( array $session ): string {
        unset( $session['_raw'] );
        return (string) \wp_json_encode( $session, JSON_UNESCAPED_SLASHES );
    }

    protected static function decode( string $raw, string $id ): ?array {
        $session = json_decode( $raw, true );
        if ( ! is_array( $session ) || ! isset( $session['id'] ) || $session['id'] !== $id || ! self::valid_id( $id ) ) {
            return null;
        }
        foreach ( array( 'key_hash', 'owner', 'filename', 'sha256', 'status', 'part' ) as $text ) {
            if ( ! isset( $session[ $text ] ) || ! is_string( $session[ $text ] ) ) {
                return null;
            }
        }
        foreach ( array( 'size', 'created', 'expires' ) as $number ) {
            if ( ! isset( $session[ $number ] ) || ! is_int( $session[ $number ] ) ) {
                return null;
            }
        }
        if ( ! in_array( $session['status'], array( self::STATUS_PENDING, self::STATUS_RECEIVING, self::STATUS_RECEIVED ), true ) ) {
            return null;
        }
        $session['_raw'] = $raw;
        return $session;
    }
}
