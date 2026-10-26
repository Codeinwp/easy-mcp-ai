<?php
namespace Easy_MCP_AI\Tools\Mail;

use Easy_MCP_AI\Approvals\Approval_Gate;
use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\MCP\Server;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

















class Send_Email extends Base_Tool {

    const NAME = 'wp_send_email';

    
    const MAX_RECIPIENTS = 50;

    
    const MESSAGE_MAX = 5000;

    const SUBJECT_MAX = 200;

    
    const NO_REASON = 'WordPress did not accept it and the mailer gave no reason.';

    const ACCEPTED_NOTE = 'accepted means WordPress handed the email to the site\'s mailer. It does not mean the email was delivered: a mail plugin may queue it, simulate it or only log it.';

    
    protected $identity = null;

    public function get_name() {
        return self::NAME;
    }

    public function get_description() {
        return 'Sends a plain-text service email (an announcement, a notice, a test that the site\'s mail works) through WordPress\'s wp_mail(), so the site\'s own mail setup sends it. Not for marketing. '
            . 'Required: `subject`, `message` (plain text, no HTML, at most ' . self::MESSAGE_MAX . ' characters), and at least one recipient: `emails` (addresses), `user_ids` (users of this site), `roles` (every user of this site with that role) or `admin: true` (the site\'s admin email address). '
            . 'One email is sent per recipient, so no recipient sees another\'s address; at most ' . self::MAX_RECIPIENTS . ' recipients per call, after duplicates are removed. '
            . 'An address that is not a user of this site is sent to only when a person approved this exact call; otherwise the call is refused before anything is sent, naming why. '
            . 'Returns { accepted, not_accepted, recipients: [ { to, from, user_id?, accepted, error? } ], note }. accepted means WordPress handed the email to the site\'s mailer, NOT that it was delivered. '
            . 'When no recipient was accepted, the call is an error. A retry sends again, so do not repeat a call whose recipients were accepted. '
            . 'Off by default; an administrator switches it on.';
    }

    public function get_category() {
        return 'mail';
    }

    public function get_required_capability() {
        return 'manage_options';
    }

    public function get_annotations() {
        return array(
            'title'           => 'Send Email',
            'readOnlyHint'    => false,
            
            'destructiveHint' => true,
            'idempotentHint'  => false,
            'openWorldHint'   => true,
        );
    }

    public function is_disabled_by_default() {
        return true;
    }

    
    public function get_approval_policy( array $arguments ) {
        return 'always';
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'subject'  => array(
                    'type'        => 'string',
                    'description' => sprintf( 'The subject line, at most %d characters.', self::SUBJECT_MAX ),
                ),
                'message'  => array(
                    'type'        => 'string',
                    'description' => sprintf( 'The body, in plain text (line breaks are kept; HTML tags are refused), at most %d characters.', self::MESSAGE_MAX ),
                ),
                'emails'   => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Email addresses. An address of a user of this site counts as that user; any other address needs a person to approve the call.',
                ),
                'user_ids' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'IDs of users of this site (wp_list_users).',
                ),
                'roles'    => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Role slugs, e.g. ["subscriber"]: every user of this site with that role.',
                ),
                'admin'    => array(
                    'type'        => 'boolean',
                    'description' => 'When true, also send to the site\'s admin email address (Settings › General).',
                ),
            ),
            'required'   => array( 'subject', 'message' ),
        );
    }

    
    public function redact_for_record( array $arguments ) {
        if ( array_key_exists( 'message', $arguments ) ) {
            $arguments['message'] = '[REDACTED]';
        }
        return $arguments;
    }

    
    public function set_request_identity( array $identity ): void {
        $this->identity = $identity;
    }

    







    public function describe( array $arguments ) {
        try {
            $subject    = self::parse_subject( $arguments );
            $message    = self::parse_message( $arguments );
            $resolution = $this->resolve_recipients( $arguments );
        } catch ( \Throwable $e ) {
            return parent::describe( $arguments );
        }
        return sprintf(
            /* translators: 1: subject, 2: number of recipients, 3: the recipients, 4: the message */
            __( 'Send the email "%1$s" to %2$d recipient(s): %3$s. Message: %4$s', 'easy-mcp-ai' ),
            $subject,
            count( $resolution['recipients'] ),
            implode( '; ', $resolution['summary'] ),
            $message
        );
    }

    






    public function approval_refusal( array $arguments ): ?string {
        $length = Approval_Gate::preview_overflow( (string) $this->describe( $arguments ) );
        if ( null === $length ) {
            return null;
        }
        return sprintf(
            /* translators: 1: preview length, 2: the most the approval page shows */
            __( 'This email\'s approval preview (its subject, recipients and message) is %1$d characters long and the approval page shows %2$d, so it was not sent for approval: a person must read every word they approve. Shorten the message or send to fewer explicit addresses per call.', 'easy-mcp-ai' ),
            $length,
            Approval_Gate::PREVIEW_MAX
        );
    }

    











    public function precheck( array $arguments ): void {
        self::parse_subject( $arguments );
        self::parse_message( $arguments );
        self::check_recipient_arguments( $arguments );
    }

    





    public function execute( array $arguments ) {
        $subject    = self::parse_subject( $arguments );
        $message    = self::parse_message( $arguments );
        $resolution = $this->resolve_recipients( $arguments );

        $outside = array();
        foreach ( $resolution['recipients'] as $recipient ) {
            if ( 'email' === $recipient['from'] ) {
                $outside[] = $recipient['to'];
            }
        }
        if ( ! empty( $outside ) && 'redeem' !== Server::current_call_approval_kind() ) {
            throw $this->unapproved_refusal( $outside, $arguments );
        }

        $results  = array();
        $accepted = 0;
        foreach ( $resolution['recipients'] as $recipient ) {
            $outcome = $this->send_one( $recipient['to'], $subject, $message );
            $row     = array(
                'to'   => $recipient['to'],
                'from' => $recipient['from'],
            );
            if ( isset( $recipient['user_id'] ) ) {
                $row['user_id'] = $recipient['user_id'];
            }
            $row['accepted'] = $outcome['accepted'];
            if ( ! $outcome['accepted'] ) {
                $row['error'] = $outcome['error'];
            } else {
                ++$accepted;
            }
            $results[] = $row;
        }

        if ( 0 === $accepted ) {
            $lines = array();
            foreach ( $results as $row ) {
                $lines[] = $row['to'] . ': ' . $row['error'];
            }
            throw new Detailed_Tool_Error(
                sprintf( 'WordPress accepted none of the %d emails.', count( $results ) ),
                implode( "\n", $lines )
            );
        }

        return array(
            'accepted'     => $accepted,
            'not_accepted' => count( $results ) - $accepted,
            'recipients'   => $results,
            'note'         => self::ACCEPTED_NOTE,
        );
    }

    






    private function send_one( string $to, string $subject, string $message ): array {
        $errors   = array();
        $listener = static function ( $error ) use ( &$errors ) {
            if ( is_object( $error ) && method_exists( $error, 'get_error_message' ) ) {
                $text = trim( (string) $error->get_error_message() );
                if ( '' !== $text && ! in_array( $text, $errors, true ) ) {
                    $errors[] = $text;
                }
            }
        };
        add_action( 'wp_mail_failed', $listener );
        try {
            $accepted = true === wp_mail( $to, $subject, $message, array( 'Content-Type: text/plain' ) );
        } catch ( \Throwable $e ) {
            $accepted = false;
            $errors[] = $e->getMessage();
        } finally {
            remove_action( 'wp_mail_failed', $listener );
        }
        if ( $accepted ) {
            return array( 'accepted' => true, 'error' => '' );
        }
        $error = empty( $errors ) ? self::NO_REASON : implode( '; ', $errors );
        return array( 'accepted' => false, 'error' => Server::sanitize_error_message( $error ) );
    }

    








    private function unapproved_refusal( array $outside, array $arguments ): Detailed_Tool_Error {
        if ( ! Approval_Gate::enabled() ) {
            $why = 'approvals are off on this site ("Ask before destructive actions" in Settings)';
        } elseif ( ! Approval_Gate::requires_approval( $this, $arguments ) ) {
            
            
            $never = Approval_Gate::matches_any( self::NAME, (array) \Easy_MCP_AI\Config::get( 'approval_never' ) )
                && ! Approval_Gate::matches_any( self::NAME, (array) \Easy_MCP_AI\Config::get( 'approval_always' ) );
            $why   = $never
                ? 'the approval settings list this tool under "Never ask for"'
                : 'site code (the easy_mcp_ai_requires_approval filter) exempts this call from approval';
        } elseif ( $this->is_unattended_key() ) {
            $why = 'this API key is set to never pause for approval';
        } else {
            $why = 'this call was not approved by a person';
        }
        return new Detailed_Tool_Error(
            sprintf( 'Nothing was sent: %s, so this tool emails only this site\'s users and its admin address.', $why ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            sprintf(
                'Not users of this site: %s. An address outside the site\'s users is sent to only when a person approves the call. Send to users of this site (user_ids, roles, or their addresses) or admin: true instead.',
                implode( ', ', $outside )
            )
        );
    }

    
    private function is_unattended_key(): bool {
        if ( ! is_array( $this->identity ) || 'legacy' !== $this->identity['auth_source'] ) {
            return false;
        }
        return $this->token_is_unattended( (int) $this->identity['token_id'] );
    }

    
    protected function token_is_unattended( int $token_id ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\Plugin' ) ) {
            return false;
        }
        $manager = \Easy_MCP_AI\Plugin::instance()->get_token_manager();
        $row     = $manager ? $manager->get_token_by_id( $token_id ) : null;
        return is_array( $row ) && ! empty( $row['unattended'] );
    }

    








    private function resolve_recipients( array $arguments ): array {
        self::check_recipient_arguments( $arguments );
        $recipients = array();
        $summary    = array();
        $admin      = self::admin_address();

        
        
        $add = static function ( string $address, string $from, ?int $user_id, string $label ) use ( &$recipients, &$summary ): void {
            $key = strtolower( $address );
            if ( isset( $recipients[ $key ] ) ) {
                return;
            }
            $recipients[ $key ] = array( 'to' => $address, 'from' => $from );
            if ( null !== $user_id ) {
                $recipients[ $key ]['user_id'] = $user_id;
            }
            if ( '' !== $label ) {
                $summary[] = $label;
            }
        };
        $add_user = static function ( $user, string $from, string $label = 'user' ) use ( $add ): void {
            $add( (string) $user->user_email, $from, (int) $user->ID, 'user' === $label ? sprintf( '%s (user #%d)', $user->user_email, (int) $user->ID ) : $label );
        };

        foreach ( self::list_argument( $arguments, 'emails' ) as $email ) {
            $address = trim( (string) $email );
            $user    = $this->site_user_by_email( $address );
            if ( $user ) {
                $add_user( $user, 'user' );
            } elseif ( null !== $admin && 0 === strcasecmp( $address, $admin ) ) {
                $add( $admin, 'admin', null, sprintf( 'the site admin (%s)', $admin ) );
            } else {
                $add( $address, 'email', null, sprintf( '%s (not a user of this site)', $address ) );
            }
        }
        $missing = array();
        foreach ( self::list_argument( $arguments, 'user_ids' ) as $id ) {
            $user = $this->site_user( (int) $id );
            if ( ! $user ) {
                $missing[] = (string) $id;
                continue;
            }
            $add_user( $user, 'user' );
        }
        if ( ! empty( $missing ) ) {
            throw self::no_such_users( $missing );
        }

        $roles = self::list_argument( $arguments, 'roles' );
        $known = empty( $roles ) ? null : wp_roles();
        foreach ( $roles as $role ) {
            $slug = trim( (string) $role );
            if ( ! isset( $known->roles[ $slug ] ) ) {
                throw self::no_such_role( $role );
            }
            $ids = self::role_member_ids( $slug );
            if ( count( $ids ) > self::MAX_RECIPIENTS ) {
                throw new \InvalidArgumentException( sprintf( 'The role "%s" has %d users, more than the %d recipients one call may send to. Nothing was sent.', $slug, count( $ids ), self::MAX_RECIPIENTS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            
            
            
            sort( $ids );
            $name      = isset( $known->role_names[ $slug ] ) ? (string) $known->role_names[ $slug ] : $slug;
            $summary[] = sprintf(
                'every user with the %s role (%d: %s)',
                $name,
                count( $ids ),
                implode( ', ', array_map( static function ( int $id ): string {
                    return '#' . $id;
                }, $ids ) )
            );
            foreach ( $ids as $id ) {
                $user = $this->site_user( $id );
                if ( $user ) {
                    $add_user( $user, 'role:' . $slug, '' );
                }
            }
        }

        if ( ! empty( $arguments['admin'] ) && rest_sanitize_boolean( $arguments['admin'] ) ) {
            if ( null === $admin ) {
                throw new \InvalidArgumentException( 'The site\'s admin email address (Settings › General) is not a valid address. Nothing was sent.' );
            }
            $add( $admin, 'admin', null, sprintf( 'the site admin (%s)', $admin ) );
        }

        if ( empty( $recipients ) ) {
            throw new \InvalidArgumentException( 'No recipient: give at least one of emails, user_ids, roles (with at least one user) or admin: true. Nothing was sent.' );
        }
        if ( count( $recipients ) > self::MAX_RECIPIENTS ) {
            throw new \InvalidArgumentException( sprintf( 'This call has %d recipients; one call may send to at most %d. Nothing was sent.', count( $recipients ), self::MAX_RECIPIENTS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return array(
            'recipients' => array_values( $recipients ),
            'summary'    => $summary,
        );
    }

    








    private static function check_recipient_arguments( array $arguments ): void {
        $invalid = array();
        foreach ( self::list_argument( $arguments, 'emails' ) as $email ) {
            $address = is_string( $email ) ? trim( $email ) : '';
            if ( '' === $address || ! is_email( $address ) ) {
                $invalid[] = is_scalar( $email ) ? (string) $email : gettype( $email );
            }
        }
        if ( ! empty( $invalid ) ) {
            throw new \InvalidArgumentException( sprintf( 'Nothing was sent: not valid email addresses: %s.', implode( ', ', $invalid ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $malformed = array();
        foreach ( self::list_argument( $arguments, 'user_ids' ) as $id ) {
            if ( ! ( is_numeric( $id ) && (int) $id > 0 && (string) (int) $id === (string) $id ) ) {
                $malformed[] = is_scalar( $id ) ? (string) $id : gettype( $id );
            }
        }
        if ( ! empty( $malformed ) ) {
            throw self::no_such_users( $malformed );
        }

        $roles = self::list_argument( $arguments, 'roles' );
        foreach ( $roles as $role ) {
            if ( ! is_string( $role ) || '' === trim( $role ) ) {
                throw self::no_such_role( $role );
            }
        }

        $admin = ! empty( $arguments['admin'] ) && rest_sanitize_boolean( $arguments['admin'] );
        if ( ! $admin && empty( $roles ) && empty( self::list_argument( $arguments, 'emails' ) ) && empty( self::list_argument( $arguments, 'user_ids' ) ) ) {
            throw new \InvalidArgumentException( 'No recipient: give at least one of emails, user_ids, roles (with at least one user) or admin: true. Nothing was sent.' );
        }
    }

    
    private static function no_such_users( array $ids ): \InvalidArgumentException {
        return new \InvalidArgumentException( sprintf( 'Nothing was sent: no user of this site with a valid email address has the ID %s. Use wp_list_users to find users.', implode( ', ', $ids ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    
    private static function no_such_role( $role ): \InvalidArgumentException {
        return new \InvalidArgumentException( sprintf( 'No role "%s" on this site. Nothing was sent.', is_scalar( $role ) ? (string) $role : gettype( $role ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    





    private function site_user( int $id ): ?object {
        $user = get_userdata( $id );
        if ( ! $user || empty( $user->user_email ) || ! is_email( (string) $user->user_email ) ) {
            return null;
        }
        if ( is_multisite() && ! is_user_member_of_blog( (int) $user->ID, get_current_blog_id() ) ) {
            return null;
        }
        return $user;
    }

    
    private function site_user_by_email( string $address ): ?object {
        $user = get_user_by( 'email', $address );
        return $user ? $this->site_user( (int) $user->ID ) : null;
    }

    




    private static function role_member_ids( string $slug ): array {
        $ids = array();
        foreach ( (array) get_users( array( 'role' => $slug, 'fields' => 'ID' ) ) as $row ) {
            $ids[] = is_object( $row ) ? (int) $row->ID : (int) $row;
        }
        return $ids;
    }

    
    private static function admin_address(): ?string {
        $admin = trim( (string) get_option( 'admin_email', '' ) );
        return '' !== $admin && is_email( $admin ) ? $admin : null;
    }

    



    private static function list_argument( array $arguments, string $key ): array {
        if ( ! isset( $arguments[ $key ] ) ) {
            return array();
        }
        if ( ! is_array( $arguments[ $key ] ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an array. Nothing was sent.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array_values( $arguments[ $key ] );
    }

    





    private static function parse_subject( array $arguments ): string {
        $subject = isset( $arguments['subject'] ) && is_string( $arguments['subject'] ) ? $arguments['subject'] : '';
        self::refuse_invalid_utf8( $subject, 'subject' );
        $subject = trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $subject ) );
        if ( '' === $subject ) {
            throw new \InvalidArgumentException( 'subject is required and must be a non-empty string. Nothing was sent.' );
        }
        if ( mb_strlen( $subject ) > self::SUBJECT_MAX ) {
            throw new \InvalidArgumentException( sprintf( 'subject is %d characters; at most %d are allowed. Nothing was sent.', mb_strlen( $subject ), self::SUBJECT_MAX ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $subject;
    }

    






    private static function parse_message( array $arguments ): string {
        $message = isset( $arguments['message'] ) && is_string( $arguments['message'] ) ? $arguments['message'] : '';
        self::refuse_invalid_utf8( $message, 'message' );
        $message = str_replace( array( "\r\n", "\r" ), "\n", $message );
        $message = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message );
        if ( '' === trim( $message ) ) {
            throw new \InvalidArgumentException( 'message is required and must be non-empty plain text. Nothing was sent.' );
        }
        if ( strip_tags( $message ) !== $message ) {
            throw new \InvalidArgumentException( 'message is plain text, and it contains HTML tags (or a "<" that starts one). Remove them; nothing was sent.' );
        }
        if ( mb_strlen( $message ) > self::MESSAGE_MAX ) {
            throw new \InvalidArgumentException( sprintf( 'message is %d characters; at most %d are allowed. Nothing was sent.', mb_strlen( $message ), self::MESSAGE_MAX ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $message;
    }

    
    private static function refuse_invalid_utf8( string $text, string $field ): void {
        if ( 1 !== preg_match( '//u', $text ) ) {
            throw new \InvalidArgumentException( sprintf( '%s is not valid UTF-8 text. Nothing was sent.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
}
