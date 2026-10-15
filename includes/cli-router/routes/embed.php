<?php























if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_embed = static function ( string $method, bool $write, array $params, array $extra = array() ): array {
    return array_merge(
        array(
            'route'   => 'own',
            'handler' => 'Embed_Handler',
            'method'  => $method,
            'cap'     => 'edit_posts',
            'network' => null,
            'write'   => $write,
            'params'  => $params,
        ),
        $extra
    );
};

return array(
    'embed cache clear'    => array(
        'route'     => 'delegate',
        'adapter'   => 'Embed_Adapter',
        'method'    => 'cache_clear',
        'tools'     => array( 'wp_delete_post_meta' ),
        'write'     => true,
        'params'    => array( 'post_id' ),
        'preflight' => 'preflight_clear',
    ),
    'embed cache find'     => $emai_embed( 'cache_find', false, array( 'url', 'width', 'height', 'discover' ) ),
    'embed cache trigger'  => $emai_embed(
        'cache_trigger',
        true,
        array( 'post_id' ),
        array(
            'preflight' => 'preflight_trigger',
            'describe'  => 'describe_trigger',
        )
    ),
    'embed fetch'          => $emai_embed(
        'fetch',
        true,
        array( 'url', 'width', 'height', 'post-id', 'discover', 'skip-cache', 'skip-sanitization', 'do-shortcode', 'limit-response-size', 'raw', 'raw-format' ),
        array(
            'preflight'      => 'preflight_fetch',
            'describe'       => 'describe_fetch',
            'read_only_when' => 'is_raw',
        )
    ),
    'embed handler list'   => $emai_embed( 'handler_list', false, array( 'field', 'fields', 'format' ) ),
    'embed provider list'  => $emai_embed( 'provider_list', false, array( 'field', 'fields', 'format', 'force-regex' ) ),
    'embed provider match' => $emai_embed( 'provider_match', false, array( 'url', 'discover', 'limit-response-size', 'link-type' ) ),
);
