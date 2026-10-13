<?php
namespace Easy_MCP_AI\Privileged\Search_Replace;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












interface Database {

    
    public function base_tables(): array;

    
    public function primary_key( string $table ): array;

    
    public function text_columns( string $table ): array;

    









    public function fetch( string $table, string $column, array $pk, array $needles, ?array $after, int $limit, array $conditions ): array;

    





    public function count( string $table, string $column, array $needles, array $conditions ): int;

    






    public function column_values( string $table, string $select, string $column, array $needles, array $conditions, int $limit ): array;

    




    public function update( string $table, string $column, array $key, string $value ): bool;
}
