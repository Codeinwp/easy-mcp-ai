<?php






namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










final class Unmanaged_Layers {

    





    const LAYERS = array(
        'cloudflare' => array(
            'label'     => 'Cloudflare',
            'headers'   => array( 'HTTP_CF_RAY', 'HTTP_CF_CONNECTING_IP' ),
            'purged_by' => array( 'cloudflare', 'super-page-cache', 'wp-fastest-cache', 'w3-total-cache', 'kinsta', 'wp-engine', 'bigscoots' ),
            'where'     => 'The Cloudflare dashboard (Caching > Configuration > Purge Cache). It matters only if APO or a Cache Rule caches HTML; by default Cloudflare does not cache HTML.',
        ),
        'sucuri'     => array(
            'label'     => 'Sucuri firewall',
            'headers'   => array( 'HTTP_X_SUCURI_CLIENTIP' ),
            'purged_by' => array(),
            'where'     => 'The Sucuri dashboard, or the Sucuri plugin\'s Firewall (WAF) page.',
        ),
        'varnish'    => array(
            'label'     => 'Varnish',
            'headers'   => array( 'HTTP_X_VARNISH' ),
            'purged_by' => array( 'proxy-cache-purge', 'breeze', 'wp-fastest-cache', 'w3-total-cache', 'wp-rocket', 'nginx-helper' ),
            'where'     => 'The hosting panel\'s cache tools, or a Varnish purge plugin such as Proxy Cache Purge.',
        ),
        'fastly'     => array(
            'label'     => 'Fastly',
            'headers'   => array( 'HTTP_FASTLY_CLIENT_IP' ),
            'purged_by' => array(),
            'where'     => 'The Fastly dashboard, or the Fastly WordPress plugin.',
        ),
        'bunny'      => array(
            'label'     => 'bunny.net',
            'headers'   => array( 'HTTP_CDN_PULLZONEID', 'HTTP_CDN_REQUESTID' ),
            'purged_by' => array(),
            'where'     => 'The bunny.net dashboard (Pull Zone > Purge Cache).',
        ),
    );

    






    public static function detect( Vendor_Calls $calls, array $layers ): array {
        $handled = array();
        foreach ( $layers as $layer ) {
            if ( in_array( $layer->status(), array( Layer_Result::CLEARED, Layer_Result::QUEUED, Layer_Result::WOULD_CLEAR ), true ) ) {
                $handled[] = $layer->id();
            }
        }
        $found = array();
        foreach ( self::LAYERS as $id => $layer ) {
            $evidence = self::evidence( $calls, $layer['headers'] );
            if ( '' === $evidence || array() !== array_intersect( $layer['purged_by'], $handled ) ) {
                continue;
            }
            $found[] = array(
                'id'       => $id,
                'label'    => $layer['label'],
                'evidence' => $evidence,
                'where'    => $layer['where'],
                'detail'   => sprintf( '%s sits in front of this site and nothing in WordPress cleared it in this call. Only the site owner can clear it.', $layer['label'] ),
            );
        }
        return $found;
    }

    






    private static function evidence( Vendor_Calls $calls, array $headers ): string {
        foreach ( $headers as $key ) {
            if ( '' !== $calls->server( $key ) ) {
                return str_replace( '_', '-', substr( $key, 5 ) ) . ' header on this request';
            }
        }
        return '';
    }
}
