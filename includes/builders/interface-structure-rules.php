<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Structure_Rules {

    public function name(): string;

    public function base_tag( string $tag ): string;

    public function insertable( string $tag ): bool;

    public function holds_elements( string $tag ): bool;

    public function fixed_refusal( string $tag ): ?string;

    public function placement_refusal( string $child, array $chain, array $descendants ): ?string;

    public function tag_at( string $tag, array $ancestors ): string;

    public function markup( string $tag, string $atts ): string;

    public function trailing_breaks( string $tag ): int;

    public function always_saved( string $tag, string $parent ): array;

    public function copy_drops(): array;
}
