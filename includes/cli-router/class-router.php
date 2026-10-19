<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





























































































final class Router {

    
    const CACHE_MAX = 32;

    
    const DEFAULT_BULK = 50;

    
    const PLUGIN_COMMANDS_NOTE = 'Commands added by plugins are not available through wp_cli_run.';

    
    const REFUSED_GLOBALS = array(
        'path'          => 'it loads WordPress from another directory, and wp_cli_run runs on the site this MCP connection belongs to',
        'ssh'           => 'it runs the command on another host',
        'http'          => 'it runs the command on another host',
        'require'       => 'it loads a PHP file from the server',
        'exec'          => 'it runs PHP code',
        'context'       => 'WordPress is already loaded for this request, so its load context cannot be chosen',
        'skip-plugins'  => 'WordPress is already loaded with every plugin for this request, so none can be skipped',
        'skip-themes'   => 'WordPress is already loaded with its theme for this request, so it cannot be skipped',
        'skip-packages' => 'wp_cli_run runs only the commands bundled with WP-CLI, so there is no package to skip',
        'prompt'        => 'there is no terminal to prompt on; pass every argument in the command',
        'debug'         => 'debug output is not available inside a web request',
    );

    
    const WP_DIE_FILTERS = array( 'wp_die_handler', 'wp_die_ajax_handler', 'wp_die_json_handler', 'wp_die_jsonp_handler', 'wp_die_xmlrpc_handler', 'wp_die_xml_handler' );

    
    const PRINTED_NOTE_BYTES = 500;

    
    const NESTED_SPLIT = "wp_cli_run refuses 'wp %s' as one part of another command: it would be split into further parts, and wp_cli_run splits a command one level deep only. Run it as a command of its own.";

    
    const NESTED_SPLIT_RUN = "wp_cli_run refuses 'wp %s' here: it runs as one part of another wp_cli_run command, and a part cannot be split into further parts. Run it as a command of its own.";

    
    const PART_FAILED = '%s refused the next call: %s';

    
    private static $cache = array();

    
    private static $depth = 0;

    
    private static $split_frames = array();

    
    private static $part_depth = 0;

    
    private static $part_handoff = null;

    
    private static $tool_resolver = null;

    
    public static function classify( string $command ): Classification {
        $key = self::cache_key( $command );
        if ( isset( self::$cache[ $key ] ) ) {
            return self::$cache[ $key ];
        }
        $classification = self::build( $command );
        if ( count( self::$cache ) >= self::CACHE_MAX ) {
            array_shift( self::$cache );
        }
        self::$cache[ $key ] = $classification;
        return $classification;
    }

    









    public static function approval_policy( Classification $c ): string {
        if ( 'own' === $c->kind ) {
            return $c->write ? 'always' : 'never';
        }
        if ( 'delegate' !== $c->kind || null === $c->plan ) {
            return 'never';
        }
        return self::plan_pauses( $c->plan ) ? 'always' : 'never';
    }

    






    private static function plan_pauses( array $plan ): bool {
        foreach ( $plan as $call ) {
            $tool = self::tool( (string) $call['tool'] );
            
            
            if ( ! is_object( $tool ) || ! class_exists( '\\Easy_MCP_AI\\Approvals\\Approval_Gate' ) ) {
                return true;
            }
            if ( \Easy_MCP_AI\Approvals\Approval_Gate::requires_approval( $tool, (array) $call['arguments'] ) ) {
                return true;
            }
        }
        return false;
    }

    




    public static function describe( Classification $c ): string {
        $lines = array( 'WP-CLI: ' . $c->masked );
        if ( 'own' === $c->kind && $c->write && null !== $c->invocation && ! empty( $c->invocation->route['describe'] ) ) {
            $lines = array_merge( $lines, self::own_describe_lines( $c->invocation ) );
        }
        if ( 'delegate' === $c->kind && null !== $c->plan ) {
            foreach ( $c->plan as $call ) {
                $text    = self::tool_describe( $call );
                $lines[] = (string) $call['tool'] . ': ' . ( '' !== $text ? $text : (string) json_encode( $call['arguments'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) );
            }
        }
        return implode( "\n", $lines );
    }

    





    private static function own_describe_lines( Invocation $inv ): array {
        $lines = array();
        try {
            $handler = self::handler_object( 'own', $inv->route );
            $method  = (string) $inv->route['describe'];
            if ( null !== $handler && is_callable( array( $handler, $method ) ) ) {
                foreach ( (array) $handler->$method( $inv ) as $line ) {
                    $lines[] = (string) $line;
                }
            }
        } catch ( \Throwable $e ) {
            $lines[] = 'The current state of the targets could not be read.';
        }
        return $lines;
    }

    





    private static function tool_describe( array $call ): string {
        $tool = self::tool( (string) $call['tool'] );
        if ( ! is_object( $tool ) || ! method_exists( $tool, 'describe' ) ) {
            return '';
        }
        try {
            return (string) $tool->describe( (array) $call['arguments'] );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    










    public static function run( array $tool_arguments, Context $ctx ): array {
        $command = isset( $tool_arguments['command'] ) && is_string( $tool_arguments['command'] ) ? $tool_arguments['command'] : '';
        $c       = self::classify( $command );
        
        
        $part = self::in_split_frame();
        ++self::$depth;
        try {
            self::start( $c, $ctx );
            list( $exit, $paged ) = self::run_dispatch( $c, $ctx, $tool_arguments, $part );
            $envelope             = Envelope::build( $ctx, $exit, 'delegate' === $c->kind );
            foreach ( $paged as $key => $value ) {
                $envelope[ $key ] = $value;
            }
            if ( $part ) {
                
                
                self::$part_handoff = array(
                    'ctx'  => $ctx,
                    'exit' => $exit,
                );
            }
            return self::finish( $c, $ctx, $exit, $envelope );
        } finally {
            --self::$depth;
        }
    }

    



    private static function start( Classification $c, Context $ctx ): void {
        $ctx->out = clone $c->output;
        foreach ( $c->notes as $note ) {
            $ctx->note( $note );
        }
        if ( null !== $c->plan ) {
            
            
            $ctx->approved_plan( $c->plan );
        }
    }

    





    private static function run_dispatch( Classification $c, Context $ctx, array $tool_arguments, bool $part ): array {
        $exit  = $c->exit_code;
        $paged = array();
        if ( $c->dispatches() ) {
            $exit = 0;
            try {
                if ( $part && $c->split ) {
                    
                    $ctx->out->error( sprintf( self::NESTED_SPLIT_RUN, $c->invocation->command ) );
                }
                $paged = self::dispatch( $c, $ctx, $tool_arguments );
            } catch ( Cli_Exit $e ) {
                $exit = $e->get_exit_code();
            }
        }
        return array( $exit, $paged );
    }

    
    public static function reset(): void {
        self::$cache         = array();
        self::$tool_resolver = null;
        self::$depth         = 0;
        self::$split_frames  = array();
        self::$part_depth    = 0;
        self::$part_handoff  = null;
    }

    





    public static function set_tool_resolver( ?callable $resolver ): void {
        self::$tool_resolver = $resolver;
    }

    
    
    

    
    private static function cache_key( string $command ): string {
        $user = function_exists( 'get_current_user_id' ) ? (int) \get_current_user_id() : 0;
        $blog = function_exists( 'get_current_blog_id' ) ? (int) \get_current_blog_id() : 0;
        return $user . ':' . $blog . ':' . $command;
    }

    




    private static function build( string $command ): Classification {
        $c          = new Classification( 'invalid' );
        $c->command = $command;
        $c->masked  = self::masked( $command );

        try {
            self::classify_into( $c, $command );
        } catch ( Cli_Exit $e ) {
            self::stopped( $c, $e );
        }
        return $c;
    }

    




    private static function classify_into( Classification $c, string $command ): void {
        $out     = $c->output;
        $parsed  = self::parse( $c, $command );
        $runtime = $parsed['runtime'];
        $positional = array();
        foreach ( $parsed['positional'] as $word ) {
            $positional[] = (string) $word;
        }

        self::apply_globals( $c, $runtime );

        $found = self::find_leaf( $c, $positional );
        if ( null === $found ) {
            return;
        }
        $leaf     = $found['leaf'];
        $name     = (string) $leaf['command'];
        $args     = $found['rest'];
        $route    = Routes::get( $name );
        $route_of = self::route_kind( $c, $name, $route );

        try {
            $valid = Synopsis_Validator::validate( $leaf, $args, $parsed['assoc'], $out, $runtime );
        } catch ( Cli_Exit $e ) {
            $c->reason = 'validation';
            throw $e;
        }

        if ( 'catalog' !== $route_of ) {
            self::enforce_params( $c, $leaf, $route, $args, $parsed['assoc'] );
        }

        
        
        self::route_call( $c, new Invocation( $name, $valid['args'], $valid['assoc'], $runtime, $leaf, $route, $command ), self::classify_context( $out ) );
    }

    






    private static function parse( Classification $c, string $command ): array {
        try {
            $parsed = Argv::parse( Shell_Lexer::split( $command ), Catalog::global_parameters() );
        } catch ( Cli_Parse_Error $e ) {
            self::stop( $c, 'invalid', 'parse:' . $e->get_reason(), $e->getMessage() );
        }
        $runtime = $parsed['runtime'];
        $c->output->set_quiet( array_key_exists( 'quiet', $runtime ) && (bool) $runtime['quiet'] );
        
        foreach ( $parsed['notices'] as $notice ) {
            $c->output->err( $notice . "\n" );
        }
        return $parsed;
    }

    








    private static function find_leaf( Classification $c, array $positional ): ?array {
        $own = Self_Protection::own_command( implode( ' ', $positional ) );
        if ( null !== $own ) {
            self::stop( $c, 'refuse', 'own_command', $own );
        }

        $found = Catalog::find( $positional );
        if ( null !== $found['leaf'] ) {
            return $found;
        }
        if ( null !== $found['composite'] && ! $found['rest'] ) {
            
            self::composite_usage( $found['composite'], $c->output );
            $c->kind   = 'catalog';
            $c->reason = 'usage';
            return null;
        }
        if ( ! $found['path'] ) {
            $c->notes[] = self::PLUGIN_COMMANDS_NOTE;
        }
        self::stop( $c, 'invalid', 'unknown', Catalog::not_found_message( $found ) );
        return null; 
    }

    







    private static function route_kind( Classification $c, string $name, ?array $route ): string {
        $route_of = is_array( $route ) && isset( $route['route'] ) ? $route['route'] : null;
        if ( ! self::runs_route( $route_of, $route ) ) {
            $reason = Catalog_Commands::refusal( $name );
            self::stop(
                $c,
                'unimplemented' === $route_of ? 'unimplemented' : 'refuse',
                'unimplemented' === $route_of ? 'unimplemented' : 'refused',
                "wp_cli_run refuses 'wp {$name}': " . ( null !== $reason ? $reason : 'Not available through wp_cli_run.' )
            );
        }
        return $route_of;
    }

    





    private static function runs_route( ?string $route_of, ?array $route ): bool {
        if ( ! in_array( $route_of, array( 'catalog', 'delegate', 'own' ), true ) ) {
            return false;
        }
        return 'catalog' !== $route_of || in_array( isset( $route['method'] ) ? $route['method'] : null, Catalog_Commands::ANSWERED, true );
    }

    



    private static function classify_context( Output $out ): Context {
        return new Context(
            $out,
            static function () {
                return null;
            },
            static function () {
                return false;
            }
        );
    }

    




    private static function route_call( Classification $c, Invocation $declared, Context $ctx ): void {
        
        
        $route_of = (string) $declared->route['route'];

        
        
        $parts = 'catalog' !== $route_of ? self::split_of( $c, $declared, $ctx ) : null;
        $inv   = $declared;
        if ( null !== $parts ) {
            $c->split = true;
            $c->plan  = $parts;
            $c->tools = array_values( array_map( 'strval', (array) $declared->route['split_tools'] ) );
        } else {
            $inv = self::route_single( $c, $declared, $ctx );
        }

        $c->write = ! empty( $declared->route['write'] );
        if ( $c->write && 'catalog' !== $route_of && ! empty( $declared->route['read_only_when'] ) && self::read_only( $c, $declared, $ctx ) ) {
            $c->write = false;
        }
        $c->kind       = $c->split ? 'delegate' : (string) $inv->route['route'];
        $c->invocation = $c->split ? $declared : $inv;
    }

    





    private static function route_single( Classification $c, Invocation $inv, Context $ctx ): Invocation {
        $route    = $inv->route;
        $route_of = (string) $route['route'];
        if ( 'own' === $route_of && ! empty( $route['delegate_when'] ) && self::delegates( $c, $inv, $ctx ) ) {
            $route    = self::delegated_route( $route );
            $route_of = 'delegate';
            $inv      = new Invocation( $inv->command, $inv->args, $inv->assoc, $inv->runtime, $inv->leaf, $route, $inv->raw );
        }
        if ( 'catalog' !== $route_of ) {
            self::enforce_bulk( $c, $inv, $ctx );
        }
        if ( 'own' === $route_of ) {
            self::require_route_caps( $c, $route, $ctx );
        }
        if ( 'catalog' !== $route_of && ! empty( $route['preflight'] ) ) {
            self::preflight( $c, $inv, $ctx );
        }
        if ( 'delegate' === $route_of ) {
            $c->tools = array_values( array_map( 'strval', isset( $route['tools'] ) ? (array) $route['tools'] : array() ) );
        }
        return $inv;
    }

    






    private static function require_route_caps( Classification $c, array $route, Context $ctx ): void {
        $c->reason = 'capability';
        $ctx->require_cap( isset( $route['cap'] ) && is_string( $route['cap'] ) && '' !== $route['cap'] ? $route['cap'] : 'manage_options' );
        if ( isset( $route['network'] ) && is_string( $route['network'] ) && '' !== $route['network'] ) {
            $ctx->require_network( $route['network'] );
        }
        $c->reason = null;
    }

    




    private static function preflight( Classification $c, Invocation $inv, Context $ctx ): void {
        $route     = $inv->route;
        $handler   = self::handler_object( (string) $route['route'], $route );
        $method    = (string) $route['preflight'];
        $c->reason = 'preflight';
        if ( null === $handler || ! is_callable( array( $handler, $method ) ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its route names no preflight method." );
        }
        self::guarded(
            static function () use ( $handler, $method, $inv, $ctx ) {
                $handler->$method( $inv, $ctx );
            },
            $ctx
        );
        $c->reason = null;
    }

    



    private static function stopped( Classification $c, Cli_Exit $e ): void {
        $c->exit_code = $e->get_exit_code();
        if ( 'invalid' === $c->kind && in_array( $c->reason, array( 'capability', 'preflight', 'bulk', 'param', 'split', 'split_depth', 'delegate_when', 'read_only_when' ), true ) ) {
            $c->kind = 'refuse';
        }
        if ( null === $c->reason ) {
            $c->reason = 'invalid';
        }
        $errors   = $c->output->errors();
        $c->error = $errors ? strtok( (string) $errors[0], "\n" ) : null;
    }

    




    private static function stop( Classification $c, string $kind, string $reason, string $message ): void {
        $c->kind   = $kind;
        $c->reason = $reason;
        $c->output->error( $message );
    }

    





    private static function apply_globals( Classification $c, array $runtime ): void {
        foreach ( array_keys( $runtime ) as $key ) {
            if ( isset( self::REFUSED_GLOBALS[ $key ] ) ) {
                self::stop( $c, 'refuse', 'global', sprintf( 'wp_cli_run does not accept the --%s global parameter: %s.', $key, self::REFUSED_GLOBALS[ $key ] ) );
            }
        }
        if ( array_key_exists( 'url', $runtime ) ) {
            self::check_url( $c, $runtime['url'] );
        }
        if ( array_key_exists( 'user', $runtime ) ) {
            self::check_user( $c, $runtime['user'] );
        }
    }

    












    private static function check_url( Classification $c, $url ): void {
        if ( true === $url ) {
            $c->output->warning( 'The --url parameter expects a value.' );
            return;
        }
        if ( ! is_string( $url ) || '' === $url ) {
            return;
        }
        $home = (string) \home_url();
        if ( ! self::url_names_this_site( $url, $home ) ) {
            self::stop( $c, 'refuse', 'url', sprintf( "wp_cli_run runs only on the site this MCP connection belongs to (%s); --url=%s names another site. Use that site's own MCP connection.", $home, $url ) );
        }
    }

    





    private static function url_names_this_site( string $url, string $home ): bool {
        $parts = self::parse_url( $url );
        if ( ! isset( $parts['scheme'] ) ) {
            $parts = self::parse_url( 'http://' . $url );
        }
        $host  = isset( $parts['host'] ) ? \Easy_MCP_AI\Site_Host::normalize( (string) $parts['host'] ) : '';
        $match = '' !== $host && ( $host === \Easy_MCP_AI\Site_Host::normalize( $home ) || \Easy_MCP_AI\Site_Host::matches( $host ) );
        if ( $match && function_exists( 'is_multisite' ) && \is_multisite() ) {
            $match = self::same_path( $parts, self::parse_url( $home ) );
        }
        return $match;
    }

    





    private static function same_path( array $parts, array $home_parts ): bool {
        return rtrim( isset( $parts['path'] ) ? (string) $parts['path'] : '', '/' ) === rtrim( isset( $home_parts['path'] ) ? (string) $home_parts['path'] : '', '/' );
    }

    








    private static function check_user( Classification $c, $arg ): void {
        $out = $c->output;
        if ( ! function_exists( 'get_user_by' ) ) {
            self::stop( $c, 'refuse', 'user', 'wp_cli_run cannot resolve --user outside WordPress.' );
        }
        $label = is_string( $arg ) ? $arg : ( true === $arg ? '1' : '' );
        $user  = Fetchers::user( $arg, $out );
        if ( ! $user || ! isset( $user->ID ) ) {
            self::stop( $c, 'invalid', 'user', sprintf( Fetchers::MESSAGES['user'], $label ) );
        }
        $caller = (int) \get_current_user_id();
        if ( (int) $user->ID !== $caller ) {
            self::stop( $c, 'refuse', 'user', sprintf( 'wp_cli_run never switches user: --user=%s is user %d, and this MCP connection runs as user %d.', $label, (int) $user->ID, $caller ) );
        }
    }

    











    private static function composite_usage( array $composite, Output $out ): void {
        $path = (string) $composite['command'];
        $subs = array();
        foreach ( $composite['subcommands'] as $name ) {
            $subs[ (string) $name ] = true;
        }
        ksort( $subs );
        $i = 0;
        foreach ( array_keys( $subs ) as $name ) {
            $prefix = ( 0 === $i ) ? 'usage: ' : '   or: ';
            ++$i;
            $leaf = Catalog::leaf( $path . ' ' . $name );
            $out->line( sprintf( '%s%s %s', $prefix, 'wp ' . $path . ' ' . $name, null !== $leaf ? (string) $leaf['synopsis'] : '<command>' ) );
        }
        $out->line();
        $out->line( "See 'wp help $path <command>' for more information on a specific command." );
    }

    











    private static function enforce_params( Classification $c, array $leaf, array $route, array $args, array $assoc ): void {
        $handled = isset( $route['params'] ) && is_array( $route['params'] ) ? array_map( 'strval', $route['params'] ) : array();
        $refused = isset( $route['refused_params'] ) && is_array( $route['refused_params'] ) ? $route['refused_params'] : array();

        list( $errors, $named ) = self::synopsis_param_errors( $leaf, $handled, $refused, $args, $assoc );
        
        
        if ( empty( $route['generic'] ) ) {
            $errors = array_merge( $errors, self::unknown_param_errors( $named, $refused, $assoc ) );
        }
        if ( $errors ) {
            $c->reason = 'param';
            $c->output->error( "Parameter errors:\n" . implode( "\n", $errors ) );
        }
    }

    












    private static function synopsis_param_errors( array $leaf, array $handled, array $refused, array $args, array $assoc ): array {
        $errors = array();
        $named  = array();
        $index  = 0;
        foreach ( isset( $leaf['params'] ) ? (array) $leaf['params'] : array() as $param ) {
            $name  = isset( $param['name'] ) ? (string) $param['name'] : '';
            $error = null;
            if ( 'positional' === $param['type'] ) {
                $error = self::positional_given( $param, $args, $index ) ? self::unhandled_param( ' <%s> is not available through wp_cli_run: %s', $name, $handled, $refused ) : null;
                ++$index;
            } elseif ( 'assoc' === $param['type'] || 'flag' === $param['type'] ) {
                $named[ $name ] = true;
                $error          = array_key_exists( $name, $assoc ) ? self::unhandled_param( ' --%s parameter is not available through wp_cli_run: %s', $name, $handled, $refused ) : null;
            }
            if ( null !== $error ) {
                $errors[] = $error;
            }
        }
        return array( $errors, $named );
    }

    






    private static function positional_given( array $param, array $args, int $index ): bool {
        return ! empty( $param['repeating'] ) ? count( $args ) > $index : array_key_exists( $index, $args );
    }

    






    private static function unhandled_param( string $format, string $name, array $handled, array $refused ): ?string {
        return in_array( $name, $handled, true ) ? null : sprintf( $format, $name, self::param_reason( $refused, $name ) );
    }

    







    private static function unknown_param_errors( array $named, array $refused, array $assoc ): array {
        $errors = array();
        foreach ( array_keys( $assoc ) as $key ) {
            if ( ! isset( $named[ (string) $key ] ) ) {
                $errors[] = sprintf( ' --%s parameter is not available through wp_cli_run: %s', $key, self::param_reason( $refused, (string) $key ) );
            }
        }
        return $errors;
    }

    




    private static function param_reason( array $refused, string $name ): string {
        return isset( $refused[ $name ] ) && is_string( $refused[ $name ] ) && '' !== $refused[ $name ] ? $refused[ $name ] : 'not supported yet';
    }

    





    private static function enforce_bulk( Classification $c, Invocation $inv, Context $ctx ): void {
        $route = $inv->route;
        $count = null;
        $cap   = 0;
        if ( 'delegate' === $route['route'] && ! empty( $route['plan'] ) ) {
            $adapter   = self::handler_object( 'delegate', $route );
            $method    = (string) $route['plan'];
            $c->reason = 'plan';
            if ( null === $adapter || ! is_callable( array( $adapter, $method ) ) ) {
                $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its route names no plan method." );
            }
            $plan = null;
            self::guarded(
                static function () use ( $adapter, $method, $inv, &$plan ) {
                    $plan = $adapter->$method( $inv );
                },
                $ctx
            );
            $c->plan = self::valid_plan( $plan, isset( $route['tools'] ) ? (array) $route['tools'] : array() );
            if ( null === $c->plan ) {
                $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its plan names a tool its route does not list." );
            }
            $c->reason = null;
            $count     = count( $c->plan );
            $cap       = isset( $route['bulk'] ) ? (int) $route['bulk'] : self::DEFAULT_BULK;
        } elseif ( isset( $route['bulk'] ) ) {
            $count = self::item_count( $inv );
            $cap   = (int) $route['bulk'];
        }
        if ( null !== $count ) {
            self::over_cap( $c, $inv, $ctx, $count, $cap );
        }
    }

    







    private static function valid_plan( $plan, array $tools ): ?array {
        if ( ! is_array( $plan ) ) {
            return null;
        }
        $valid = array();
        foreach ( $plan as $call ) {
            if ( ! is_array( $call ) || ! isset( $call['tool'] ) || ! is_string( $call['tool'] ) || ! in_array( $call['tool'], $tools, true ) ) {
                return null;
            }
            $valid[] = array(
                'tool'      => $call['tool'],
                'arguments' => isset( $call['arguments'] ) && is_array( $call['arguments'] ) ? $call['arguments'] : array(),
            );
        }
        return $valid;
    }

    
    private static function item_count( Invocation $inv ): int {
        $index = 0;
        foreach ( isset( $inv->leaf['params'] ) ? (array) $inv->leaf['params'] : array() as $param ) {
            if ( 'positional' !== $param['type'] ) {
                continue;
            }
            if ( ! empty( $param['repeating'] ) ) {
                return max( 0, count( $inv->args ) - $index );
            }
            ++$index;
        }
        return 1;
    }

    










    private static function split_of( Classification $c, Invocation $inv, Context $ctx ): ?array {
        $route = $inv->route;
        if ( empty( $route['split'] ) ) {
            return null;
        }
        $handler   = self::handler_object( (string) $route['route'], $route );
        $method    = (string) $route['split'];
        $c->reason = 'split';
        if ( null === $handler || ! is_callable( array( $handler, $method ) ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its route names no split method." );
        }
        $parts = null;
        self::guarded(
            static function () use ( $handler, $method, $inv, $ctx, &$parts ) {
                $parts = $handler->$method( $inv, $ctx );
            },
            $ctx
        );
        if ( null === $parts || array() === $parts ) {
            $c->reason = null;
            return null;
        }
        if ( self::$part_depth > 0 ) {
            
            
            $c->reason = 'split_depth';
            $ctx->out->error( sprintf( self::NESTED_SPLIT, $inv->command ) );
        }
        if ( isset( $route['bulk'] ) ) {
            self::over_cap( $c, $inv, $ctx, self::item_count( $inv ), (int) $route['bulk'] );
        }
        $c->reason = 'split';
        $valid     = self::valid_split( $inv, $ctx, $handler, $parts );
        self::over_cap( $c, $inv, $ctx, count( $valid ), self::DEFAULT_BULK );
        $c->reason = null;
        return $valid;
    }

    







    private static function valid_split( Invocation $inv, Context $ctx, Command_Handler $handler, $parts ): array {
        $tools = isset( $inv->route['split_tools'] ) ? array_map( 'strval', (array) $inv->route['split_tools'] ) : array();
        if ( ! is_array( $parts ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its split method returned no list of parts." );
        }
        $valid = array();
        foreach ( $parts as $part ) {
            $valid[] = self::valid_part( $inv, $ctx, $handler, $tools, $part );
        }
        return $valid;
    }

    










    private static function valid_part( Invocation $inv, Context $ctx, Command_Handler $handler, array $tools, $part ): array {
        if ( ! is_array( $part ) || ! isset( $part['tool'] ) || ! is_string( $part['tool'] ) || ! in_array( $part['tool'], $tools, true ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its split names a tool its route does not list." );
        }
        $tool      = $part['tool'];
        $arguments = isset( $part['arguments'] ) && is_array( $part['arguments'] ) ? $part['arguments'] : array();
        $entry     = array(
            'tool'      => $tool,
            'arguments' => $arguments,
        );
        $render    = self::part_render( $inv, $ctx, $handler, $part, $tool );
        if ( null !== $render ) {
            $entry['render'] = $render;
        }
        $label = self::part_label( $part );
        if ( null !== $label ) {
            $entry['label'] = $label;
        }
        if ( 'wp_cli_run' === $tool ) {
            $entry['arguments'] = array( 'command' => self::split_command( $inv, $ctx, $arguments ) );
        }
        return $entry;
    }

    






    private static function part_render( Invocation $inv, Context $ctx, Command_Handler $handler, array $part, string $tool ): ?string {
        if ( isset( $part['render'] ) ) {
            if ( ! is_string( $part['render'] ) || '' === $part['render'] || ! is_callable( array( $handler, $part['render'] ) ) ) {
                $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its split names a render method its route's class does not have." );
            }
            return $part['render'];
        }
        if ( 'wp_cli_run' !== $tool ) {
            
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its split hands a part to {$tool} without a render method." );
        }
        return null;
    }

    




    private static function part_label( array $part ): ?string {
        return isset( $part['label'] ) && is_string( $part['label'] ) && '' !== $part['label'] ? $part['label'] : null;
    }

    







    private static function split_command( Invocation $inv, Context $ctx, array $arguments ): string {
        $command = isset( $arguments['command'] ) && is_string( $arguments['command'] ) ? trim( $arguments['command'] ) : '';
        if ( '' === $command || array( 'command' ) !== array_keys( $arguments ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its split runs wp_cli_run with something other than one command." );
        }
        $part = self::classify_part( $command );
        if ( $ctx->out->is_quiet() && $part->dispatches() && empty( $part->invocation->runtime['quiet'] ) ) {
            $command = '--quiet ' . $command;
            $part    = self::classify_part( $command );
        }
        if ( ! $part->dispatches() ) {
            self::refuse_part( $inv, $ctx, $command, $part );
        }
        return $command;
    }

    





    private static function refuse_part( Invocation $inv, Context $ctx, string $command, Classification $part ): void {
        $errors = $part->output->errors();
        $ctx->out->error(
            $errors ? (string) $errors[0] : sprintf( "wp_cli_run cannot run 'wp %s': its part 'wp %s' runs nothing.", $inv->command, Redactor::mask_command( $command )[0] ),
            $part->exit_code > 0 ? $part->exit_code : 1
        );
    }

    
    private static function classify_part( string $command ): Classification {
        ++self::$part_depth;
        try {
            return self::build( $command );
        } finally {
            --self::$part_depth;
        }
    }

    







    private static function delegates( Classification $c, Invocation $inv, Context $ctx ): bool {
        $route     = $inv->route;
        $handler   = self::handler_object( 'own', $route );
        $method    = (string) $route['delegate_when'];
        $adapter   = self::handler_object( 'delegate', $route );
        $c->reason = 'delegate_when';
        if ( null === $handler || ! is_callable( array( $handler, $method ) ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its route names no delegate_when method." );
        }
        if ( null === $adapter || empty( $route['delegate_method'] ) || ! is_callable( array( $adapter, (string) $route['delegate_method'] ) ) || empty( $route['delegate_tools'] ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its route names no adapter method and tools to delegate to." );
        }
        $answer = false;
        self::guarded(
            static function () use ( $handler, $method, $inv, &$answer ) {
                $answer = true === $handler->$method( $inv );
            },
            $ctx
        );
        $c->reason = null;
        return $answer;
    }

    









    private static function delegated_route( array $route ): array {
        $effective = array_diff_key( $route, array_flip( array( 'handler', 'cap', 'network', 'describe', 'preflight', 'plan', 'not_captured', 'delegate_when', 'delegate_method', 'delegate_tools', 'delegate_plan', 'delegate_preflight', 'read_only_when', 'split', 'split_tools' ) ) );

        $effective['route']          = 'delegate';
        $effective['method']         = (string) $route['delegate_method'];
        $effective['tools']          = array_values( array_map( 'strval', (array) $route['delegate_tools'] ) );
        $effective['delegated_from'] = 'own';
        if ( ! empty( $route['delegate_plan'] ) ) {
            $effective['plan'] = $route['delegate_plan'];
        }
        if ( ! empty( $route['delegate_preflight'] ) ) {
            $effective['preflight'] = $route['delegate_preflight'];
        }
        return $effective;
    }

    







    private static function read_only( Classification $c, Invocation $inv, Context $ctx ): bool {
        $route     = $inv->route;
        $handler   = self::handler_object( (string) $route['route'], $route );
        $method    = (string) $route['read_only_when'];
        $c->reason = 'read_only_when';
        if ( null === $handler || ! is_callable( array( $handler, $method ) ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its route names no read_only_when method." );
        }
        $answer = false;
        self::guarded(
            static function () use ( $handler, $method, $inv, &$answer ) {
                $answer = true === $handler->$method( $inv );
            },
            $ctx
        );
        $c->reason = null;
        return $answer;
    }

    




    private static function over_cap( Classification $c, Invocation $inv, Context $ctx, int $count, int $cap ): void {
        if ( $count > $cap ) {
            $c->reason = 'bulk';
            $ctx->out->error( sprintf( "wp_cli_run runs at most %d items in one 'wp %s' command, and this one names %d. Run it in smaller groups.", $cap, $inv->command, $count ) );
        }
    }

    





    public static function in_split_frame(): bool {
        return array() !== self::$split_frames && end( self::$split_frames ) === self::$depth;
    }

    
    
    

    






    private static function dispatch( Classification $c, Context $ctx, array $tool_arguments ): array {
        $inv    = $c->invocation;
        $route  = $inv->route;
        $paged  = ! empty( $route['paged'] );
        $offset = self::output_offset( $tool_arguments, $ctx );
        if ( $offset > 0 && ! $paged ) {
            $ctx->out->error( 'output_offset applies only to `help` and `cli cmd-dump`, whose output can exceed 64 KB.' );
        }
        if ( $c->split ) {
            self::guarded(
                static function () use ( $c, $ctx ) {
                    self::run_split( $c, $ctx );
                },
                $ctx
            );
            return array();
        }
        $object = self::handler_object( $c->kind, $route );
        $method = isset( $route['method'] ) ? (string) $route['method'] : '';
        if ( null === $object || '' === $method || ! is_callable( array( $object, $method ) ) ) {
            $ctx->out->error( "wp_cli_run cannot run 'wp {$inv->command}': its route names no method." );
        }
        $call = static function () use ( $object, $method, $inv, $ctx ) {
            $object->$method( $inv, $ctx );
        };
        if ( ! $paged ) {
            self::guarded( $call, $ctx );
            return array();
        }

        
        
        
        $start      = $ctx->out;
        $page_out   = clone $start;
        $stdout_at  = strlen( $page_out->stdout() );
        $stderr_at  = strlen( $page_out->stderr() );
        $ctx->out   = $page_out;
        self::guarded( $call, $ctx );
        $full       = (string) substr( $page_out->stdout(), $stdout_at );
        $page       = Catalog_Commands::page( $full, $offset );
        $ctx->out   = clone $start;
        $ctx->out->out( $page['chunk'] );
        $ctx->out->err( (string) substr( $page_out->stderr(), $stderr_at ) );
        return array(
            'next_offset' => $page['next_offset'],
            'total_bytes' => strlen( $full ),
        );
    }

    





    private static function output_offset( array $tool_arguments, Context $ctx ): int {
        if ( ! array_key_exists( 'output_offset', $tool_arguments ) || null === $tool_arguments['output_offset'] ) {
            return 0;
        }
        $value = $tool_arguments['output_offset'];
        if ( is_int( $value ) && $value >= 0 ) {
            return $value;
        }
        if ( is_string( $value ) && preg_match( '/^[0-9]+\z/', $value ) ) {
            return (int) $value;
        }
        $ctx->out->error( 'output_offset must be a whole number of 0 or more, as the previous result\'s next_offset gave it.' );
        return 0;
    }

    





















    private static function run_split( Classification $c, Context $ctx ): void {
        $inv                  = $c->invocation;
        $handler              = self::handler_object( (string) $inv->route['route'], $inv->route );
        $parts                = array_values( (array) $c->plan );
        $state                = array();
        self::$split_frames[] = self::$depth;
        try {
            foreach ( $parts as $index => $part ) {
                $errors = count( $ctx->out->errors() );
                $result = self::delegate_part( $ctx, $parts, $index );
                if ( ! self::render_part( $inv, $ctx, $handler, $parts, $index, $result, $state ) ) {
                    self::fail_part( $ctx, $parts, $index, $result, $errors );
                }
            }
        } finally {
            array_pop( self::$split_frames );
        }
    }

    








    private static function delegate_part( Context $ctx, array $parts, int $index ): array {
        $part               = $parts[ $index ];
        self::$part_handoff = null;
        try {
            $result = $ctx->delegate( (string) $part['tool'], (array) $part['arguments'] );
        } catch ( Cli_Exit $e ) {
            
            self::$part_handoff = null;
            self::split_not_run( $ctx, $parts, $index );
            throw $e;
        }
        $handoff             = self::$part_handoff;
        self::$part_handoff  = null;
        $result['part']      = null !== $handoff ? $handoff['ctx'] : null;
        $result['exit_code'] = null !== $handoff ? (int) $handoff['exit'] : ( $result['ok'] ? 0 : 1 );
        return $result;
    }

    








    private static function render_part( Invocation $inv, Context $ctx, ?Command_Handler $handler, array $parts, int $index, array $result, array &$state ): bool {
        $part = $parts[ $index ];
        try {
            if ( isset( $part['render'] ) && null !== $handler ) {
                $method = (string) $part['render'];
                return true === $handler->$method( $inv, $ctx, $result, $state );
            }
            return self::merge_part_result( $ctx, $result );
        } catch ( Cli_Exit $e ) {
            self::split_not_run( $ctx, $parts, $index + 1 );
            throw $e;
        }
    }

    










    private static function fail_part( Context $ctx, array $parts, int $index, array $result, int $errors ): void {
        if ( count( $ctx->out->errors() ) === $errors && null === $ctx->missing_target() ) {
            $ctx->out->error_continue( sprintf( self::PART_FAILED, (string) $parts[ $index ]['tool'], null !== $result['error'] ? (string) $result['error'] : 'the call failed' ) );
        }
        self::split_not_run( $ctx, $parts, $index + 1 );
        $exit = (int) $result['exit_code'];
        throw new Cli_Exit( null !== $ctx->missing_target() && null === $ctx->pending_approval() && 0 === $exit ? 0 : max( 1, $exit ) );
    }

    





    private static function merge_part_result( Context $ctx, array $result ): bool {
        if ( $result['part'] instanceof Context ) {
            self::merge_part( $ctx, $result['part'] );
            return ! empty( $result['ok'] );
        }
        if ( ! empty( $result['ok'] ) ) {
            
            
            self::merge_envelope( $ctx, (array) $result['payload'] );
            return true;
        }
        return false;
    }

    






    public static function merge_part( Context $into, Context $part, ?string $stdout = null ): void {
        $into->out->out( null !== $stdout ? $stdout : $part->out->stdout() );
        self::merge_stderr( $into->out, $part->out->stderr(), $part->out->warnings(), $part->out->errors() );
        $into->merge_part( $part );
    }

    





    private static function merge_envelope( Context $ctx, array $envelope ): void {
        $ctx->out->out( isset( $envelope['output'] ) && is_string( $envelope['output'] ) ? $envelope['output'] : '' );
        $warnings = array();
        foreach ( isset( $envelope['warnings'] ) ? (array) $envelope['warnings'] : array() as $warning ) {
            $warnings[] = (string) $warning;
        }
        self::merge_stderr( $ctx->out, isset( $envelope['stderr'] ) && is_string( $envelope['stderr'] ) ? $envelope['stderr'] : '', $warnings, array() );
        self::merge_envelope_records( $ctx, $envelope );
    }

    





    private static function merge_envelope_records( Context $ctx, array $envelope ): void {
        foreach ( isset( $envelope['notes'] ) ? (array) $envelope['notes'] : array() as $note ) {
            $ctx->note( (string) $note );
        }
        foreach ( isset( $envelope['history']['not_captured'] ) ? (array) $envelope['history']['not_captured'] : array() as $phrase ) {
            $ctx->not_captured( (string) $phrase );
        }
        foreach ( isset( $envelope['redacted'] ) ? (array) $envelope['redacted'] : array() as $field ) {
            $ctx->redacted( (string) $field );
        }
        if ( ! empty( $envelope['changed'] ) ) {
            $ctx->mark_changed();
        }
    }

    








    private static function merge_stderr( Output $into, string $stderr, array $warnings, array $errors ): void {
        $w      = 0;
        $e      = 0;
        $at     = 0;
        $length = strlen( $stderr );
        while ( $at < $length ) {
            $taken = self::line_at( $stderr, $at, 'Warning: ', $warnings, $w );
            if ( $taken > 0 ) {
                $into->warning( $warnings[ $w ] );
                ++$w;
                $at += $taken;
                continue;
            }
            $taken = self::line_at( $stderr, $at, 'Error: ', $errors, $e );
            if ( $taken > 0 ) {
                $into->error_continue( $errors[ $e ] );
                ++$e;
                $at += $taken;
                continue;
            }
            $end = strpos( $stderr, "\n", $at );
            $end = false === $end ? $length : $end + 1;
            $into->err( (string) substr( $stderr, $at, $end - $at ) );
            $at = $end;
        }
        if ( $into->is_quiet() ) {
            for ( $count = count( $warnings ); $w < $count; ++$w ) {
                $into->warning( $warnings[ $w ] );
            }
        }
    }

    





    private static function line_at( string $stderr, int $at, string $prefix, array $messages, int $i ): int {
        if ( ! isset( $messages[ $i ] ) ) {
            return 0;
        }
        $line = $prefix . $messages[ $i ] . "\n";
        return substr( $stderr, $at, strlen( $line ) ) === $line ? strlen( $line ) : 0;
    }

    




    private static function split_not_run( Context $ctx, array $parts, int $from ): void {
        foreach ( array_slice( $parts, $from ) as $part ) {
            if ( isset( $part['label'] ) && is_string( $part['label'] ) ) {
                $ctx->not_run( $part['label'] );
            } elseif ( 'wp_cli_run' === $part['tool'] ) {
                $ctx->not_run( 'wp ' . self::masked( (string) $part['arguments']['command'] ) );
            } else {
                $ctx->not_run( (string) $part['tool'] );
            }
        }
    }

    



















    private static function guarded( callable $fn, Context $ctx ): void {
        $handler  = static function ( $message = '', $title = '', $args = array() ) use ( $ctx ) {
            $ctx->out->error_continue( self::wp_die_message( $message ) );
            throw new Cli_Exit( 1 );
        };
        $provider = static function () use ( $handler ) {
            return $handler;
        };
        $hooked = function_exists( 'add_filter' );
        if ( $hooked ) {
            foreach ( self::WP_DIE_FILTERS as $filter ) {
                \add_filter( $filter, $provider, PHP_INT_MAX );
            }
        }
        $level = ob_get_level();
        ob_start();
        try {
            $fn();
        } finally {
            $printed = '';
            while ( ob_get_level() > $level ) {
                $printed = (string) ob_get_clean() . $printed;
            }
            if ( $hooked && function_exists( 'remove_filter' ) ) {
                foreach ( self::WP_DIE_FILTERS as $filter ) {
                    \remove_filter( $filter, $provider, PHP_INT_MAX );
                }
            }
            if ( '' !== $printed ) {
                $ctx->note( self::printed_note( $printed ) );
            }
        }
    }

    
    private static function printed_note( string $printed ): string {
        $excerpt = Envelope::cut( $printed, self::PRINTED_NOTE_BYTES );
        return sprintf(
            'WordPress or another plugin printed %d bytes during this command, outside WP-CLI\'s output (it is not in output or stderr): %s%s',
            strlen( $printed ),
            \Easy_MCP_AI\Text_Redactor::redact( $excerpt ),
            strlen( $excerpt ) < strlen( $printed ) ? '...' : ''
        );
    }

    










    private static function wp_die_message( $message ): string {
        if ( $message instanceof \WP_Error ) {
            $text = $message->get_error_message();
            $data = $message->get_error_data( 'internal_server_error' );
            if ( ! empty( $data['error']['file'] )
                && false !== stripos( $data['error']['file'], 'themes/functions.php' ) ) {
                $text = 'An unexpected functions.php file in the themes directory may have caused this internal server error.';
            }
        } else {
            $text = $message;
        }
        return self::clean_error_message( is_scalar( $text ) ? (string) $text : '' );
    }

    
    private static function clean_error_message( string $message ): string {
        $original = trim( $message );
        $message  = $original;
        if ( preg_match( '|^\<h1>(.+?)</h1>|', $original, $matches ) ) {
            $message = $matches[1] . '.';
        }
        if ( preg_match( '|\<p>(.+?)</p>|', $original, $matches ) ) {
            $message .= ' ' . $matches[1];
        }
        $message = str_replace( array( '<code>', '</code>' ), array( '`', '`' ), $message );
        $message = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $message );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- the port of wp_strip_all_tags()'s string path.
        $message = trim( strip_tags( $message ) );
        return html_entity_decode( $message, ENT_COMPAT, 'UTF-8' );
    }

    






    private static function finish( Classification $c, Context $ctx, int $exit, array $envelope ): array {
        $missing = $ctx->missing_target();
        if ( null !== $missing ) {
            throw self::tool_error( $missing, false, $ctx, $exit, $envelope );
        }
        if ( 0 === $exit ) {
            return $envelope;
        }
        $route = null !== $c->invocation ? $c->invocation->route : array();
        if ( ! empty( $route['answer'] ) && ! $ctx->out->errors() && null === $ctx->pending_approval() ) {
            return $envelope;
        }
        throw self::tool_error( null, true, $ctx, $exit, $envelope );
    }

    














    private static function tool_error( ?string $message, bool $from_output, Context $ctx, int $exit, array $envelope ): \Easy_MCP_AI\MCP\Detailed_Tool_Error {
        $stdout = $ctx->out->stdout();
        $rest   = $ctx->out->stderr();
        if ( $from_output ) {
            list( $message, $rest ) = self::message_from_output( $ctx, $exit );
        }
        
        if ( null === $message || '' === trim( $message ) ) {
            $message = sprintf( 'The command exited with code %d.', $exit );
        }
        $details = (string) json_encode( self::failure_summary( $exit, $envelope ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
        if ( self::cut_by_server( (string) $message ) ) {
            $details .= "\nError: " . $message;
        }
        if ( '' !== trim( $rest ) ) {
            $details .= "\n" . rtrim( $rest, "\n" );
        }
        if ( '' !== trim( $stdout ) ) {
            $details .= "\n" . rtrim( $stdout, "\n" );
        }
        return new \Easy_MCP_AI\MCP\Detailed_Tool_Error( (string) $message, $details );
    }

    






    private static function message_from_output( Context $ctx, int $exit ): array {
        $stderr = $ctx->out->stderr();
        $errors = $ctx->out->errors();
        if ( $errors ) {
            $first = (string) strtok( (string) $errors[0], "\n" );
            $line  = 'Error: ' . $first . "\n";
            $at    = strpos( $stderr, $line );
            $rest  = false !== $at ? substr( $stderr, 0, $at ) . substr( $stderr, $at + strlen( $line ) ) : $stderr;
            return array( $first, $rest );
        }
        $message = self::first_line( $stderr );
        if ( '' === $message ) {
            $message = self::first_line( $ctx->out->stdout() );
        }
        if ( '' === $message ) {
            $message = sprintf( 'The command exited with code %d.', $exit );
        }
        return array( $message, $stderr );
    }

    







    private static function failure_summary( int $exit, array $envelope ): array {
        $summary = array(
            'exit_code'        => $exit,
            'changed'          => $envelope['changed'],
            'calls'            => $envelope['calls'],
            'pending_approval' => $envelope['pending_approval'],
            'not_run'          => $envelope['not_run'],
            'warnings'         => array_slice( $envelope['warnings'], 0, 5 ),
            'notes'            => array_slice( $envelope['notes'], 0, 5 ),
            'history'          => $envelope['history'],
        );
        if ( count( $summary['calls'] ) > 10 ) {
            $summary['calls_total'] = count( $summary['calls'] );
            $summary['calls']       = array_slice( $summary['calls'], -10 );
        }
        return $summary;
    }

    





    private static function cut_by_server( string $message ): bool {
        $max = \Easy_MCP_AI\MCP\Detailed_Tool_Error::MAX_MESSAGE;
        if ( strlen( trim( $message ) ) > $max ) {
            return true;
        }
        if ( class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) && is_callable( array( '\\Easy_MCP_AI\\MCP\\Server', 'sanitize_error_message' ) ) ) {
            try {
                return strlen( (string) \Easy_MCP_AI\MCP\Server::sanitize_error_message( $message, PHP_INT_MAX ) ) > $max;
            } catch ( \Throwable $e ) {
                return false;
            }
        }
        return false;
    }

    
    private static function first_line( string $text ): string {
        foreach ( explode( "\n", $text ) as $line ) {
            if ( '' !== trim( $line ) ) {
                return 0 === strpos( $line, 'Error: ' ) ? substr( $line, 7 ) : $line;
            }
        }
        return '';
    }

    
    
    

    






    private static function handler_object( string $kind, array $route ): ?Command_Handler {
        if ( 'catalog' === $kind ) {
            return new Catalog_Commands();
        }
        $key       = 'delegate' === $kind ? 'adapter' : 'handler';
        $namespace = 'delegate' === $kind ? 'Adapters' : 'Handlers';
        $name      = isset( $route[ $key ] ) && is_string( $route[ $key ] ) ? $route[ $key ] : '';
        if ( ! preg_match( '/^[A-Za-z0-9_]+\z/', $name ) ) {
            return null;
        }
        $class = __NAMESPACE__ . '\\' . $namespace . '\\' . $name;
        if ( ! class_exists( $class ) || ! is_subclass_of( $class, Command_Handler::class ) ) {
            return null;
        }
        return new $class();
    }

    
    private static function tool( string $name ): ?object {
        if ( null !== self::$tool_resolver ) {
            return call_user_func( self::$tool_resolver, $name );
        }
        if ( class_exists( '\\Easy_MCP_AI\\Plugin', false ) && method_exists( '\\Easy_MCP_AI\\Plugin', 'instance' ) ) {
            $registry = \Easy_MCP_AI\Plugin::instance()->get_tool_registry();
            return is_object( $registry ) && method_exists( $registry, 'get_tool' ) ? $registry->get_tool( $name ) : null;
        }
        return null;
    }

    
    private static function masked( string $command ): string {
        try {
            return Redactor::mask_command( $command )[0];
        } catch ( \Throwable $e ) {
            return Redactor::MASK;
        }
    }

    





    private static function parse_url( string $url ): array {
        $parts = function_exists( 'wp_parse_url' ) ? \wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- fallback.
        return is_array( $parts ) ? $parts : array();
    }
}
