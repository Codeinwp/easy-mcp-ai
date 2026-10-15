<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}











trait Menu_Item_Refusal {

    








    private function refuse( string $format, array $ids, string $details = '' ): void {
        $prefix = 'Refused, nothing was changed: ';
        $budget = Detailed_Tool_Error::MAX_MESSAGE - strlen( $prefix . sprintf( $format, '' ) );
        throw new Detailed_Tool_Error( $prefix . sprintf( $format, self::refusal_id_list( $ids, $budget ) ), $details ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    








    private static function refusal_id_list( array $ids, int $budget ): string {
        for ( $shown = min( count( $ids ), self::IDS_IN_MESSAGE ); $shown > 0; $shown-- ) {
            $rest = count( $ids ) - $shown;
            $list = implode( ', ', array_slice( $ids, 0, $shown ) ) . ( $rest > 0 ? sprintf( ' (+%d more)', $rest ) : '' );
            if ( strlen( $list ) <= $budget ) {
                return $list;
            }
        }
        return sprintf( '(+%d more)', count( $ids ) );
    }
}
