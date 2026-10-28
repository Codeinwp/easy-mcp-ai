<?php
namespace Easy_MCP_AI\Tools\Feedback;

use Easy_MCP_AI\Feedback\Feedback_Availability;
use Easy_MCP_AI\Feedback\Feedback_Queue;
use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Tool_State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Send_Feedback extends Base_Tool {

    const NAME = 'wp_send_feedback';

    protected $identity = null;

    public function get_name() {
        return self::NAME;
    }

    public function get_description() {
        return 'Report to the authors of this WordPress MCP plugin that the user needed something this site cannot do: a missing tool or ability (kind "missing_feature") or a tool that does not work as described (kind "bug"). '
            . 'Use it only after checking the tools you have, and only when the request is a reasonable thing for this plugin to do. '
            . 'It never interrupts you and never sends anything by itself: the report is queued and returns at once with sent: false, and it reaches the authors only after a person approves its exact text. If the user is present, give them the approve_url from the reply; otherwise carry on with your task. '
            . 'Per the authors\' policy, a requested feature is added within 1-2 weeks once its report is approved; you may tell the user so. '
            . 'Write a short summary of what the user wanted and, optionally, details of what you tried. Never include personal data, credentials, customer details or site content. '
            . 'If the site already has what is asked for but it is switched off, the call is refused and names what an administrator can switch on instead.'
            . ( self::list_tool_is_on() ? ' Check wp_list_disabled_tools first for that.' : '' );
    }

    private static function list_tool_is_on(): bool {
        return Tool_State::resolve( 'wp_list_disabled_tools', false )[0]
            && \Easy_MCP_AI\MCP\Server::matches_tool_patterns( 'wp_list_disabled_tools', (array) \Easy_MCP_AI\Config::get( 'allowed_tool_patterns' ) );
    }

    public function get_category() {
        return 'feedback';
    }

    public function get_required_capability() {
        return 'read';
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'kind'    => array(
                    'type'        => 'string',
                    'enum'        => array_keys( Feedback_Queue::KINDS ),
                    'description' => 'missing_feature: the site has no tool for what the user asked. bug: a tool exists but did not work as described.',
                ),
                'summary' => array(
                    'type'        => 'string',
                    'description' => sprintf( 'What the user wanted to do, in one sentence (%d-%d characters). Example: "Bulk-update the stock of WooCommerce product variations".', Feedback_Queue::SUMMARY_MIN, Feedback_Queue::SUMMARY_MAX ),
                ),
                'details' => array(
                    'type'        => 'string',
                    'description' => sprintf( 'Optional, up to %d characters: what you tried and why it fell short. No personal data, credentials or site content.', Feedback_Queue::DETAILS_MAX ),
                ),
            ),
            'required'   => array( 'kind', 'summary' ),
        );
    }

    public function get_annotations() {
        return array(
            'title'           => 'Send Feedback to the Plugin Authors',
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => true,
        );
    }

    public function get_approval_policy( array $arguments ) {
        return 'never';
    }

    public function set_request_identity( array $identity ): void {
        $this->identity = $identity;
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'kind', 'summary' ) );
        if ( ! is_array( $this->identity ) ) {
            throw new \RuntimeException( 'No request identity, so the report was not queued.' );
        }

        $kind    = $arguments['kind'];
        $summary = $arguments['summary'];
        $details = isset( $arguments['details'] ) ? $arguments['details'] : '';
        Feedback_Queue::compose( $kind, $summary, $details );

        $found = Feedback_Availability::find(
            is_string( $summary ) ? $summary : '',
            is_string( $details ) ? $details : '',
            $this->registry(),
            static function ( $tool_name ) {
                return \Easy_MCP_AI\MCP\Server::current_call_tool_status( (string) $tool_name );
            },
            self::NAME
        );
        if ( ! empty( $found ) ) {
            throw Feedback_Availability::refusal( $found, true === \Easy_MCP_AI\MCP\Server::current_call_may_use( 'wp_list_disabled_tools' ) );
        }

        return $this->queue()->queue( $this->identity, $kind, $summary, $details );
    }

    protected function registry(): ?\Easy_MCP_AI\Tools\Tool_Registry {
        return class_exists( '\\Easy_MCP_AI\\Plugin' ) ? \Easy_MCP_AI\Plugin::instance()->get_tool_registry() : null;
    }

    protected function queue(): Feedback_Queue {
        return Feedback_Queue::create();
    }
}
