<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





























final class Confirm {

    
    const NEEDS_YES = 'This command asks "%s [y/n]" before it runs, and wp_cli_run has no terminal to answer it. Add --yes to the command to answer yes.';

    
    const NO_YES = "This command asks \"%s [y/n]\" before it runs, and wp_cli_run has no terminal to answer it. 'wp %s' has no --yes flag that answers it in advance, so wp_cli_run stops here and changes nothing.";

    





    public static function ask( Invocation $inv, Context $ctx, string $question, bool $passes_assoc = true ): void {
        $has_yes = self::synopsis_has_yes( $inv->leaf );
        if ( $passes_assoc && $has_yes && Read_Value::flag( $inv->assoc, 'yes' ) ) {
            return;
        }
        if ( $passes_assoc && $has_yes ) {
            $ctx->out->error( sprintf( self::NEEDS_YES, $question ) );
        }
        $ctx->out->error( sprintf( self::NO_YES, $question, $inv->command ) );
    }

    




    public static function synopsis_has_yes( array $leaf ): bool {
        foreach ( isset( $leaf['params'] ) ? (array) $leaf['params'] : array() as $param ) {
            if ( isset( $param['type'], $param['name'] ) && 'flag' === $param['type'] && 'yes' === $param['name'] ) {
                return true;
            }
        }
        return false;
    }
}
