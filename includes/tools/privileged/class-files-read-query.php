<?php
namespace Easy_MCP_AI\Tools\Privileged;

use Easy_MCP_AI\Tools\Privileged_Tool;
use Easy_MCP_AI\Privileged\Files_Read\Action;
use Easy_MCP_AI\Privileged\Files_Read\List_Action;
use Easy_MCP_AI\Privileged\Files_Read\Outline_Action;
use Easy_MCP_AI\Privileged\Files_Read\Read_Action;
use Easy_MCP_AI\Privileged\Files_Read\Resolver;
use Easy_MCP_AI\Privileged\Files_Read\Search_Action;
use Easy_MCP_AI\Privileged\Files_Read\Searcher;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Files_Read_Query extends Privileged_Tool {

    const ACTIONS = array(
        'read'    => Read_Action::class,
        'list'    => List_Action::class,
        'search'  => Search_Action::class,
        'outline' => Outline_Action::class,
    );

    private $resolver;
    private $searcher;

    public function __construct( ?Resolver $resolver = null, ?Searcher $searcher = null ) {
        $this->resolver = $resolver;
        $this->searcher = $searcher;
    }

    public function get_name() {
        return 'wp_privileged_files_read_query';
    }

    public function get_privileged_group() {
        return 'files_read';
    }

    public function get_description() {
        return 'Read theme and plugin files under wp-content: "why is my footer broken", "which template renders this page", "where is this hook added". Read-only; never asks for approval. `action` picks what to do. '
            . '"outline" (`path` to a file, at most 1 MB): its structure with line numbers — PHP classes, methods, functions, hooks with a literal name and enqueues; CSS rules; JS functions; JSON keys; block names in a template. Start here. '
            . '"read" (`path` to a file, at most 2 MB): numbered lines "N<tab>text"; `offset` is the first line (default 1), `limit` the count (default 500, at most 2000); when `truncated` is true, call again with `offset` set to `next_offset`. Binary files return their size only. '
            . '"search" (`text`, optional `path` to a folder or file, default the active theme): matches with `path`, `line` and an `excerpt`, like grep -rn; `regex` true takes a PCRE pattern without delimiters; case-insensitive unless `case_sensitive`; `extensions` limits file types; `max_results` default 100, at most 500; stops after 20 seconds or 5,000 files with `budget_exhausted` true. '
            . '"list" (optional `path` to a folder, default the active theme; "" is wp-content): entries with `readable`; `recursive` with `max_depth` (default 5, at most 10); `glob` filters names. '
            . 'Arguments of another action are refused. Paths are relative to wp-content, with / separators. Never read: anything outside wp-content (WordPress core, wp-config.php), dot-files, uploads, mu-plugins, backup and cache folders, this plugin, wp-config copies, and files that are not source or text (logs, SQL dumps, archives, keys); vendor and node_modules are skipped by list and search unless `path` names them. Lines that look like credentials come back as [REDACTED], and lines are redacted before they are searched; that is a second line of defence, not a guarantee.';
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action'         => array(
                    'type'        => 'string',
                    'enum'        => array_keys( self::ACTIONS ),
                    'description' => 'outline, read, search or list.',
                ),
                'path'           => array(
                    'type'        => 'string',
                    'description' => 'Relative to wp-content, e.g. "themes/twentytwentyfive/functions.php". Required for read and outline; for search and list a folder, default the active theme.',
                ),
                'offset'         => array(
                    'type'        => 'integer',
                    'description' => 'read: first line, from 1. Default 1.',
                    'minimum'     => 1,
                ),
                'limit'          => array(
                    'type'        => 'integer',
                    'description' => 'read: lines to return, 1 to 2000. Default 500.',
                    'minimum'     => 1,
                    'maximum'     => Read_Action::MAX_LIMIT,
                ),
                'text'           => array(
                    'type'        => 'string',
                    'description' => 'search: what to find, plain text or (with regex) a PCRE pattern.',
                ),
                'regex'          => array(
                    'type'        => 'boolean',
                    'description' => 'search: treat text as a regular expression, without delimiters. Default false.',
                ),
                'case_sensitive' => array(
                    'type'        => 'boolean',
                    'description' => 'search: default false.',
                ),
                'extensions'     => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'search: only these file types, e.g. ["php"]. Default every readable type.',
                ),
                'max_results'    => array(
                    'type'        => 'integer',
                    'description' => 'search: matches to return, 1 to 500. Default 100.',
                    'minimum'     => 1,
                    'maximum'     => Search_Action::MAX_RESULTS,
                ),
                'recursive'      => array(
                    'type'        => 'boolean',
                    'description' => 'list: walk subfolders. Default false.',
                ),
                'max_depth'      => array(
                    'type'        => 'integer',
                    'description' => 'list, with recursive: folder levels, 1 to 10. Default 5.',
                    'minimum'     => 1,
                    'maximum'     => List_Action::MAX_DEPTH,
                ),
                'glob'           => array(
                    'type'        => 'string',
                    'description' => 'list: only names matching this pattern, e.g. "*.php", or with a / the path below `path`, e.g. "templates/*.html".',
                ),
            ),
            'required'   => array( 'action' ),
        );
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        );
    }

    protected function run( array $arguments ) {
        return $this->action( $arguments )->run( $arguments );
    }

    private function action( array $arguments ): Action {
        $name = $arguments['action'] ?? null;
        if ( ! is_string( $name ) || ! isset( self::ACTIONS[ $name ] ) ) {
            throw new \InvalidArgumentException( 'action is required: one of outline, read, search, list.' );
        }
        $class = self::ACTIONS[ $name ];
        self::refuse_foreign( $name, $class::ARGUMENTS, $arguments );
        if ( null === $this->resolver ) {
            $this->resolver = Resolver::for_site();
        }
        return 'search' === $name ? new Search_Action( $this->resolver, $this->searcher ) : new $class( $this->resolver );
    }

    private static function refuse_foreign( string $name, array $accepted, array $arguments ): void {
        foreach ( $arguments as $key => $value ) {
            if ( 'action' === $key || in_array( $key, $accepted, true ) || null === $value || '' === $value || array() === $value ) {
                continue;
            }
            throw new \InvalidArgumentException( sprintf( '%1$s is not an argument of action %2$s, which takes: %3$s.', $key, $name, implode( ', ', $accepted ) ) );
        }
    }

    protected function describe_payload( array $arguments ) {
        return $this->get_name() . ' ' . Action::json( $arguments );
    }
}
