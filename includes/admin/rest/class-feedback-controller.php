<?php











namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Feedback\Feedback_Queue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Feedback_Controller extends Admin_Rest_Controller {

    const ID_PATTERN = '(?P<id>[0-9a-f-]{36})';

    
    private $queue_factory;

    


    public function __construct( ?callable $queue_factory = null ) {
        $this->queue_factory = is_callable( $queue_factory ) ? $queue_factory : array( Feedback_Queue::class, 'create' );
    }

    public function register_routes() {
        $this->register_route( '/feedback-reports', \WP_REST_Server::READABLE, array( $this, 'list_reports' ) );
        $this->register_route( '/feedback-reports/' . self::ID_PATTERN . '/sent', \WP_REST_Server::CREATABLE, array( $this, 'sent' ) );
        $this->register_route( '/feedback-reports/' . self::ID_PATTERN . '/deny', \WP_REST_Server::CREATABLE, array( $this, 'deny' ) );
    }

    private function queue(): Feedback_Queue {
        return call_user_func( $this->queue_factory );
    }

    



    public function list_reports( $request ) {
        $reports = array();
        foreach ( $this->queue()->pending() as $row ) {
            $reports[] = self::to_public( $row );
        }
        return $this->ok(
            array(
                'reports' => $reports,
                'privacy' => Feedback_Queue::privacy_sentence(),
            )
        );
    }

    



    public function sent( $request ) {
        return $this->decided( $this->queue()->mark_sent( (string) $request->get_param( 'id' ), \get_current_user_id(), 'admin' ) );
    }

    



    public function deny( $request ) {
        return $this->decided( $this->queue()->deny( (string) $request->get_param( 'id' ), \get_current_user_id(), 'admin' ) );
    }

    



    private function decided( array $outcome ) {
        if ( ! empty( $outcome['ok'] ) ) {
            return $this->ok( array( 'status' => $outcome['status'], 'message' => $outcome['message'] ) );
        }
        $http = array(
            'not_found'   => 404,
            'decided'     => 409,
            'expired'     => 409,
        );
        $status = isset( $http[ $outcome['status'] ] ) ? $http[ $outcome['status'] ] : 400;
        return $this->fail( 'easy_mcp_ai_feedback_' . $outcome['status'], $outcome['message'], $status );
    }

    





    public static function to_public( array $row ): array {
        $arguments = is_string( $row['arguments'] ?? null ) ? json_decode( $row['arguments'], true ) : null;
        $user      = (int) $row['wp_user_id'] > 0 ? \get_userdata( (int) $row['wp_user_id'] ) : false;
        return array(
            'id'          => (string) $row['approval_id'],
            'kind'        => is_array( $arguments ) && isset( $arguments['kind'] ) ? (string) $arguments['kind'] : '',
            'text'        => (string) $row['preview'],
            'requestedBy' => $user && isset( $user->display_name ) ? (string) $user->display_name : '',
            'createdAt'   => Dashboard_Controller::iso8601( $row['created_at'] ?? null ),
            'expiresAt'   => Dashboard_Controller::iso8601( $row['expires_at'] ?? null ),
        );
    }
}
