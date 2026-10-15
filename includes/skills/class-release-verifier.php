<?php





















namespace Easy_MCP_AI\Skills;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-skill-trust.php';

final class Release_Verifier {

    








    public static function verify( $release_bytes, $signature_file, array $roots, int $now, ?int $min_serial = null, ?int $min_revocations_issued = null ): array {
        $file = $signature_file;
        if ( ! is_array( $file ) || ! isset( $file['format'] ) || Skill_Trust::FORMAT !== $file['format']
            || ! isset( $file['signatures'] ) || ! is_array( $file['signatures'] )
            || ! isset( $file['sha256'] ) || ! is_string( $file['sha256'] ) || ! is_string( $release_bytes ) ) {
            return self::refuse( 'malformed' );
        }
        $sha256 = hash( 'sha256', $release_bytes );
        if ( ! hash_equals( $sha256, $file['sha256'] ) ) {
            return self::refuse( 'digest_mismatch' );
        }

        $release = json_decode( $release_bytes, true );
        if ( ! is_array( $release ) || ! isset( $release['format'] ) || Skill_Trust::FORMAT !== $release['format']
            || ! isset( $release['skills'] ) || ! is_array( $release['skills'] ) ) {
            return self::refuse( 'malformed' );
        }
        if ( ! isset( $release['serial'], $file['serial'] ) || ! is_int( $release['serial'] ) || $release['serial'] !== $file['serial'] ) {
            return self::refuse( 'serial_mismatch' );
        }
        if ( null !== $min_serial && $release['serial'] < $min_serial ) {
            return self::refuse( 'rollback' );
        }
        foreach ( $release['skills'] as $skill ) {
            if ( ! is_array( $skill ) || ! isset( $skill['files'] ) || ! is_array( $skill['files'] ) ) {
                return self::refuse( 'malformed' );
            }
            foreach ( $skill['files'] as $f ) {
                if ( ! is_array( $f ) || ! isset( $f['text'], $f['digest'], $f['size'] ) || ! is_string( $f['text'] ) ) {
                    return self::refuse( 'malformed' );
                }
                if ( 'sha256:' . hash( 'sha256', $f['text'] ) !== $f['digest'] || strlen( $f['text'] ) !== $f['size'] ) {
                    return self::refuse( 'file_digest_mismatch' );
                }
            }
        }

        $rev      = isset( $file['revocations'] ) ? $file['revocations'] : null;
        $rev_root = ( is_array( $rev ) && isset( $rev['revoked'] ) && is_array( $rev['revoked'] ) && isset( $rev['root'] ) ) ? self::root( $roots, $rev['root'] ) : null;
        if ( null === $rev_root || ! isset( $rev['issued'], $rev['sig'] ) || ! is_int( $rev['issued'] ) || ! is_string( $rev['sig'] )
            || ! self::ed25519_verify( $rev_root['public_key'], Skill_Trust::revocation_statement( $rev ), $rev['sig'] ) ) {
            return self::refuse( 'bad_revocations' );
        }
        if ( null !== $min_revocations_issued && $rev['issued'] < $min_revocations_issued ) {
            return self::refuse( 'revocations_rollback' );
        }
        $revoked = array();
        foreach ( $rev['revoked'] as $entry ) {
            if ( is_array( $entry ) && isset( $entry['public_key'] ) && is_string( $entry['public_key'] ) ) {
                $revoked[ $entry['public_key'] ] = true;
            }
        }

        $envelope = Skill_Trust::release_envelope( $release['serial'], $sha256 );
        $reasons  = array();
        foreach ( $file['signatures'] as $entry ) {
            $d = ( is_array( $entry ) && isset( $entry['delegation'] ) && is_array( $entry['delegation'] ) ) ? $entry['delegation'] : null;
            if ( null !== $d && isset( $d['public_key'] ) && is_string( $d['public_key'] ) && isset( $revoked[ $d['public_key'] ] ) ) {
                $reasons[] = 'key_revoked';
                continue;
            }
            $root = ( null !== $d && isset( $d['root'] ) ) ? self::root( $roots, $d['root'] ) : null;
            if ( null === $root ) {
                $reasons[] = 'unknown_root';
                continue;
            }
            if ( ! isset( $entry['keyid'], $d['keyid'], $d['sig'], $d['public_key'], $d['not_before'], $d['not_after'] )
                || $entry['keyid'] !== $d['keyid'] || ! is_string( $d['sig'] )
                || ! self::ed25519_verify( $root['public_key'], Skill_Trust::delegation_statement( $d ), $d['sig'] ) ) {
                $reasons[] = 'bad_delegation';
                continue;
            }
            if ( $now < $d['not_before'] ) {
                $reasons[] = 'delegation_not_yet_valid';
                continue;
            }
            if ( $now > $d['not_after'] ) {
                $reasons[] = 'delegation_expired';
                continue;
            }
            if ( ! isset( $entry['sig'] ) || ! is_string( $entry['sig'] ) || ! self::ed25519_verify( $d['public_key'], $envelope, $entry['sig'] ) ) {
                $reasons[] = 'bad_signature';
                continue;
            }
            return array(
                'ok'                 => true,
                'keyid'              => (string) $entry['keyid'],
                'public_key'         => $d['public_key'],
                'release'            => $release,
                'revocations_issued' => $rev['issued'],
                'revoked'            => array_keys( $revoked ),
            );
        }
        return array(
            'ok'      => false,
            'reasons' => $reasons ? $reasons : array( 'bad_signature' ),
        );
    }

    








    private static function ed25519_verify( $public_key_b64, string $message, $signature_b64 ): bool {
        if ( ! is_string( $public_key_b64 ) || ! is_string( $signature_b64 ) ) {
            return false;
        }
        $public_key = base64_decode( $public_key_b64, true );
        $signature  = base64_decode( $signature_b64, true );
        if ( false === $public_key || false === $signature || 32 !== strlen( $public_key ) || 64 !== strlen( $signature ) ) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached( $signature, $message, $public_key );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    




    private static function root( array $roots, $keyid ): ?array {
        foreach ( $roots as $root ) {
            if ( is_array( $root ) && isset( $root['keyid'], $root['public_key'] ) && $root['keyid'] === $keyid ) {
                return $root;
            }
        }
        return null;
    }

    
    private static function refuse( string $reason ): array {
        return array(
            'ok'      => false,
            'reasons' => array( $reason ),
        );
    }
}
