<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















class Replace_In_Post extends Base_Tool {

    const MAX_SUBJECT_BYTES   = 204800; 
    const MAX_PATTERN_LENGTH  = 500;
    const PCRE_BACKTRACK_LIMIT = 100000;

    public function get_name() {
        return 'wp_replace_in_post';
    }

    public function get_description() {
        return 'Performs search-and-replace inside ONE field of a post without re-uploading the full content. Required: `id`, `field` (post_content|post_excerpt|post_title), `search`, `replace`. Optional: `regex` (default false), `max_replacements` (-1 = unlimited). Limits: subject 200KB, pattern 500 chars. Regex mode bounded by pcre.backtrack_limit + nested-quantifier heuristic (heuristic is advisory only — the backtrack limit is the real ReDoS defence). Site owners can disable regex entirely via the `easy_mcp_ai_replace_in_post_allow_regex` filter. Returns { id, field, replacements_count, content_length_before, content_length_after }. Major token-saver for small edits to large posts.';
    }

    public function get_category() {
        return 'posts';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id'               => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post to modify.',
                ),
                'field'            => array(
                    'type'        => 'string',
                    'description' => 'Which field to operate on.',
                    'enum'        => array( 'post_content', 'post_excerpt', 'post_title' ),
                ),
                'search'           => array(
                    'type'        => 'string',
                    'description' => 'String (or regex pattern body) to find.',
                ),
                'replace'          => array(
                    'type'        => 'string',
                    'description' => 'Replacement string. Pass an empty string to delete each match.',
                ),
                'regex'            => array(
                    'type'        => 'boolean',
                    'description' => 'Treat `search` as a PCRE pattern body (delimiter-less; / will be used). Default false.',
                    'default'     => false,
                ),
                'max_replacements' => array(
                    'type'        => 'integer',
                    'description' => 'Cap on number of replacements; -1 = unlimited. Default -1.',
                    'default'     => -1,
                ),
            ),
            'required'   => array( 'id', 'field', 'search', 'replace' ),
        );
    }

    public function execute( array $arguments ) {
        
        
        
        
        
        $this->validate_required( $arguments, array( 'id', 'field', 'search' ) );
        if ( ! array_key_exists( 'replace', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameters: replace' );
        }
        $post_id = $this->parse_required_id( $arguments['id'], 'id' );

        $field = (string) $arguments['field'];
        if ( ! in_array( $field, array( 'post_content', 'post_excerpt', 'post_title' ), true ) ) {
            throw new \InvalidArgumentException( 'field must be one of: post_content, post_excerpt, post_title' );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            throw new \InvalidArgumentException( 'Post not found.' );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( 'You do not have permission to edit this post.' );
        }

        $subject = (string) $post->{$field};
        $before_len = strlen( $subject );
        if ( $before_len > self::MAX_SUBJECT_BYTES ) {
            throw new \InvalidArgumentException(
                sprintf( 'Field is too large for in-place replacement (%d bytes; max %d). Use wp_update_post instead.', $before_len, self::MAX_SUBJECT_BYTES ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $search = (string) $arguments['search'];
        if ( strlen( $search ) > self::MAX_PATTERN_LENGTH ) {
            throw new \InvalidArgumentException(
                sprintf( 'Search pattern is too long (max %d chars).', self::MAX_PATTERN_LENGTH ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        $replace = (string) $arguments['replace'];
        $regex   = ! empty( $arguments['regex'] );

        $max = isset( $arguments['max_replacements'] ) ? (int) $arguments['max_replacements'] : -1;
        if ( 0 === $max ) {
            
            throw new \InvalidArgumentException( 'max_replacements must be -1 (unlimited) or a positive integer.' );
        }

        if ( $regex ) {
            $allow_regex = (bool) apply_filters( 'easy_mcp_ai_replace_in_post_allow_regex', true );
            if ( ! $allow_regex ) {
                throw new \InvalidArgumentException( 'Regex replacement is disabled on this site (filter: easy_mcp_ai_replace_in_post_allow_regex).' );
            }

            
            if ( preg_match( '/\([^)]*[+*?][^)]*\)[+*?]/', $search ) ) {
                throw new \InvalidArgumentException( 'Search pattern contains nested quantifiers which can cause catastrophic backtracking. Simplify the pattern or use literal mode.' );
            }

            $pattern = '/' . str_replace( '/', '\\/', $search ) . '/u';

            
            $compile_check = @preg_match( $pattern, '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( false === $compile_check ) {
                throw new \InvalidArgumentException( 'Invalid regex pattern. Provide the pattern body only — do not include delimiters or trailing modifiers (e.g. send `foo` not `/foo/i`; use inline modifiers like `(?i)foo`).' );
            }

            $previous_backtrack = ini_get( 'pcre.backtrack_limit' );
            
            
            
            
            $lowered = @ini_set( 'pcre.backtrack_limit', (string) self::PCRE_BACKTRACK_LIMIT ); // phpcs:ignore WordPress.PHP.IniSet.Risky, Squiz.PHP.DiscouragedFunctions.Discouraged -- Scoped lowering of PCRE backtrack limit for ReDoS mitigation; restored in finally.
            if ( false === $lowered && strlen( $search ) > (int) ( self::MAX_PATTERN_LENGTH / 2 ) ) {
                throw new \RuntimeException( 'Cannot lower pcre.backtrack_limit on this host; regex patterns longer than 250 chars are not permitted here for ReDoS safety.' );
            }

            $limit  = $max > 0 ? $max : -1;
            $count  = 0;
            try {
                $result = preg_replace( $pattern, $replace, $subject, $limit, $count );
            } finally {
                
                
                
                if ( false !== $previous_backtrack ) {
                    @ini_set( 'pcre.backtrack_limit', (string) $previous_backtrack ); // phpcs:ignore WordPress.PHP.IniSet.Risky, Squiz.PHP.DiscouragedFunctions.Discouraged -- Restoring previous PCRE backtrack limit.
                }
            }

            if ( null === $result ) {
                $err = preg_last_error();
                if ( PREG_BACKTRACK_LIMIT_ERROR === $err ) {
                    throw new \RuntimeException( 'Regex hit the backtracking limit; simplify the pattern.' );
                }
                if ( defined( 'PREG_BAD_UTF8_ERROR' ) && PREG_BAD_UTF8_ERROR === $err ) {
                    throw new \RuntimeException( 'Post field contains invalid UTF-8 sequences; use literal mode or repair the post content first.' );
                }
                throw new \RuntimeException( 'Regex replacement failed (preg_last_error=' . (int) $err . ').' );
            }
            $new_value = $result;
            $replacements_count = $count;
        } else {
            
            if ( $max > 0 ) {
                $new_value = $this->str_replace_limited( $search, $replace, $subject, $max, $replacements_count );
            } else {
                $count     = 0;
                $new_value = str_replace( $search, $replace, $subject, $count );
                $replacements_count = $count;
            }
        }

        if ( 0 === $replacements_count ) {
            
            return array(
                'id'                    => $post_id,
                'field'                 => $field,
                'replacements_count'    => 0,
                'content_length_before' => $before_len,
                'content_length_after'  => $before_len,
            );
        }

        $update = array(
            'ID'   => $post_id,
            $field => $new_value,
        );
        
        
        
        
        $updated = wp_update_post( wp_slash( $update ), true );
        if ( is_wp_error( $updated ) ) {
            throw new \RuntimeException( $updated->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $this->invalidate_post_cache( $post_id );

        
        
        
        
        
        
        $after_post = get_post( $post_id );
        $after_len  = $after_post ? strlen( (string) $after_post->{$field} ) : strlen( $new_value );

        return array(
            'id'                    => $post_id,
            'field'                 => $field,
            'replacements_count'    => $replacements_count,
            'content_length_before' => $before_len,
            'content_length_after'  => $after_len,
        );
    }

    










    private function str_replace_limited( $needle, $replacement, $haystack, $max, &$count ) {
        $count = 0;
        if ( '' === $needle ) {
            return $haystack;
        }
        $needle_len = strlen( $needle );
        $offset     = 0;
        $result     = '';
        while ( $count < $max ) {
            $pos = strpos( $haystack, $needle, $offset );
            if ( false === $pos ) {
                break;
            }
            $result .= substr( $haystack, $offset, $pos - $offset ) . $replacement;
            $offset  = $pos + $needle_len;
            ++$count;
        }
        $result .= substr( $haystack, $offset );
        return $result;
    }
}
