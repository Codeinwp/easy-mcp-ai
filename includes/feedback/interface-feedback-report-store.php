<?php
namespace Easy_MCP_AI\Feedback;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







interface Feedback_Report_Store {
    






    public function list_pending( string $now, int $limit ): array;

    







    public function list_pending_for( array $identity, string $now, int $limit ): array;

    







    public function find_pending_by_hash( array $identity, string $args_hash, string $now ): ?array;

    
    public function delete_report( string $approval_id ): bool;
}
