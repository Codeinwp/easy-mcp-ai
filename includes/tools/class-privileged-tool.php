<?php
namespace Easy_MCP_AI\Tools;

use Easy_MCP_AI\Privileged\Privileged_Groups;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

















abstract class Privileged_Tool extends Base_Tool {

    
    const META_KEY = 'easy-mcp-ai/privileged';

    
    const DESCRIPTION_SUFFIX = 'Privileged: it runs code, reads server files or rewrites the database. While approvals are on, each call waits for a person to approve it.';

    
    const READ_ONLY_DESCRIPTION_SUFFIX = 'Privileged: it reads files on the server. It writes nothing, so it never waits for approval; every call is in the audit log.';

    
    abstract public function get_privileged_group();

    





    abstract protected function run( array $arguments );

    







    abstract protected function describe_payload( array $arguments );

    final public function get_required_capability() {
        return Privileged_Groups::capability( $this->get_privileged_group() );
    }

    final public function get_category() {
        return Privileged_Groups::CATEGORY;
    }

    
    final public function is_disabled_by_default() {
        return true;
    }

    










    final public function get_approval_policy( array $arguments ) {
        if ( Privileged_Groups::is_read_only( $this->get_privileged_group() ) ) {
            return 'never';
        }
        return $this->is_status_read( $arguments ) ? 'never' : 'always';
    }

    











    protected function is_status_read( array $arguments ) {
        return false;
    }

    









    public function records_own_history() {
        return false;
    }

    
    final public function get_redacted_arguments() {
        return array();
    }

    



    final public function describe( array $arguments ) {
        return sprintf(
            /* translators: 1: tool name, 2: privileged group key */
            __( 'Privileged operation: %1$s (group %2$s). Nothing runs until you approve it.', 'easy-mcp-ai' ),
            $this->get_name(),
            $this->get_privileged_group()
        ) . "\n\n" . (string) $this->describe_payload( $arguments );
    }

    public function get_definition() {
        $read_only                 = Privileged_Groups::is_read_only( $this->get_privileged_group() );
        $definition                = parent::get_definition();
        $definition['description'] = rtrim( (string) $definition['description'] ) . ' ' . ( $read_only ? self::READ_ONLY_DESCRIPTION_SUFFIX : self::DESCRIPTION_SUFFIX );
        if ( ! isset( $definition['_meta'] ) || ! is_array( $definition['_meta'] ) ) {
            $definition['_meta'] = array();
        }
        $definition['_meta'][ self::META_KEY ] = array(
            'group'    => $this->get_privileged_group(),
            'approval' => $read_only ? 'never' : 'always',
        );
        return $definition;
    }

    



    final public function execute( array $arguments ) {
        $previous = Privileged_Groups::apply_soft_limits();
        try {
            $result = $this->run( $arguments );
        } finally {
            Privileged_Groups::restore_soft_limits( $previous );
        }
        return Privileged_Groups::cap_output( $result );
    }
}
