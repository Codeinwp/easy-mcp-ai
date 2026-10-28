<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_rewrite = static function ( string $method, bool $write, array $params, ?string $preflight, ?string $describe ): array {
    $route = array(
        'route'   => 'own',
        'handler' => 'Rewrite_Handler',
        'method'  => $method,
        'cap'     => 'manage_options',
        'network' => null,
        'write'   => $write,
        'params'  => $params,
    );
    if ( null !== $preflight ) {
        $route['preflight'] = $preflight;
    }
    if ( null !== $describe ) {
        $route['describe'] = $describe;
    }
    return $route;
};

return array(
    'rewrite flush'     => $emai_rewrite( 'flush', true, array( 'hard' ), null, 'describe_flush' ),
    'rewrite list'      => $emai_rewrite( 'list_', false, array( 'match', 'source', 'fields', 'format' ), null, null ),
    'rewrite structure' => $emai_rewrite( 'structure', true, array( 'permastruct', 'category-base', 'tag-base', 'hard' ), 'preflight_structure', 'describe_structure' ),
);
