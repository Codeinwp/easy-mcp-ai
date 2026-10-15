<?php



















namespace Easy_MCP_AI\Skills;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Skill_Trust {

    
    const FORMAT = 1;

    




    const ROOTS = array(
        array(
            'keyid'      => 'root-2026',
            'public_key' => 'vLp0Sl2xnmmW2srDsyGOQ+A2drn+5rOTKUtUucbJqXo=',
        ),
    );

    






    private static $roots = null;

    
    public static function roots(): array {
        return null === self::$roots ? self::ROOTS : self::$roots;
    }

    
    const SKILL_MAX_FILES = 512;
    const SKILL_MAX_BYTES = 16777216;

    
    const RELEASE_MAX_BYTES = 1048576;
    const SIGNATURE_MAX_BYTES = 65536;

    






    public static function release_envelope( $serial, string $sha256 ): string {
        return 'easymcp-skills-release:v' . self::FORMAT . "\n"
            . 'serial:' . $serial . "\n"
            . 'sha256:' . $sha256 . "\n";
    }

    





    public static function delegation_statement( array $d ): string {
        return 'easymcp-skills-delegation:v' . self::FORMAT . "\n"
            . 'root:' . self::field( $d, 'root' ) . "\n"
            . 'keyid:' . self::field( $d, 'keyid' ) . "\n"
            . 'public_key:' . self::field( $d, 'public_key' ) . "\n"
            . 'not_before:' . self::field( $d, 'not_before' ) . "\n"
            . 'not_after:' . self::field( $d, 'not_after' ) . "\n";
    }

    






    public static function revocation_statement( array $r ): string {
        $keys = array();
        foreach ( isset( $r['revoked'] ) && is_array( $r['revoked'] ) ? $r['revoked'] : array() as $entry ) {
            $keys[] = is_array( $entry ) ? self::field( $entry, 'public_key' ) : '';
        }
        sort( $keys, SORT_STRING );
        $out = 'easymcp-skills-revocations:v' . self::FORMAT . "\n"
            . 'root:' . self::field( $r, 'root' ) . "\n"
            . 'issued:' . self::field( $r, 'issued' ) . "\n";
        foreach ( $keys as $key ) {
            $out .= 'revoke:' . $key . "\n";
        }
        return $out;
    }

    







    private static function field( array $data, string $name ): string {
        if ( ! isset( $data[ $name ] ) || ! is_scalar( $data[ $name ] ) || is_bool( $data[ $name ] ) ) {
            return '';
        }
        return (string) $data[ $name ];
    }
}
