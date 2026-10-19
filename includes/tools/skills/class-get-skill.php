<?php
namespace Easy_MCP_AI\Tools\Skills;

use Easy_MCP_AI\MCP\Server;
use Easy_MCP_AI\MCP\Tool_Status;
use Easy_MCP_AI\Skills\Skill_Set;
use Easy_MCP_AI\Skills\Skills;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















class Get_Skill extends Base_Tool {

    public function get_name() {
        return 'wp_get_skill';
    }

    public function get_description() {
        return 'Get a skill: step-by-step instructions for a multi-step job on this WordPress site, such as reviewing the site\'s health, setting it up or restyling it. Call it before starting such a job and follow the steps it returns. With no arguments it lists the skills available here. With `name` it returns that skill, preceded by which of the tools it uses this connection can call and, for any it cannot, why. With `name` and `file` it returns a reference file the skill points to. Read-only.';
    }

    public function get_category() {
        return 'skills';
    }

    public function get_required_capability() {
        return 'read';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'name' => array(
                    'type'        => 'string',
                    'description' => 'The skill to get, e.g. "site-health-review". Omit to list the skills.',
                ),
                'file' => array(
                    'type'        => 'string',
                    'description' => 'A file of that skill other than SKILL.md, by its path inside the skill (e.g. "references/palettes.md").',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $served = Skills::served();
        $name   = isset( $arguments['name'] ) && is_string( $arguments['name'] ) ? trim( $arguments['name'] ) : '';
        if ( '' === $name ) {
            return self::catalog_text( $served );
        }

        $skill = $served->find( $name );
        if ( null === $skill ) {
            if ( $served->is_empty() ) {
                throw new \InvalidArgumentException( 'No skills are available on this site.' );
            }
            throw new \InvalidArgumentException( sprintf( 'No skill named "%s". Available: %s.', $name, implode( ', ', self::names( $served ) ) ) );
        }

        $file = isset( $arguments['file'] ) && is_string( $arguments['file'] ) ? trim( $arguments['file'], " \t/" ) : '';
        if ( '' !== $file && 'SKILL.md' !== $file ) {
            foreach ( $skill['files'] as $candidate ) {
                if ( $candidate['path'] === $file ) {
                    return $candidate['text'];
                }
            }
            $paths = array();
            foreach ( $skill['files'] as $candidate ) {
                $paths[] = $candidate['path'];
            }
            throw new \InvalidArgumentException( sprintf( 'The "%s" skill has no file "%s". Its files: %s.', $name, $file, implode( ', ', $paths ) ) );
        }

        $header = '--- SKILL.md: ' . $skill['name'] . self::version_label( $skill ) . " ---\n";
        $check   = self::availability( $skill );
        return ( '' !== $check ? $check . "\n" : '' ) . $header . $skill['files'][0]['text'];
    }

    


    private static function catalog_text( Skill_Set $served ): string {
        if ( $served->is_empty() ) {
            return 'No skills are available on this site right now.';
        }
        $lines = array( 'Skills on this site. Call this tool again with a skill\'s name to get it.' );
        foreach ( $served->skills() as $skill ) {
            $lines[] = '- ' . $skill['name'] . self::version_label( $skill ) . ': ' . Skill_Set::description( $skill );
        }
        return implode( "\n", $lines );
    }

    




    private static function availability( array $skill ): string {
        if ( empty( $skill['tools'] ) || ! class_exists( Server::class, false ) ) {
            return '';
        }
        $lines       = array( 'Tools this skill uses, checked for this connection:' );
        $unavailable = 0;
        foreach ( $skill['tools'] as $tool ) {
            $status = Server::current_call_tool_status( $tool );
            if ( null === $status ) {
                return '';
            }
            if ( 'available' === $status['status'] ) {
                $lines[] = '  ✓ ' . $tool;
                continue;
            }
            ++$unavailable;
            $why     = Tool_Status::predicate( $status );
            $lines[] = '  ✗ ' . $tool . ' ' . ( '' === $why ? 'is not available on this site.' : $why );
        }
        $lines[] = $unavailable
            ? 'For a step whose tool is unavailable, tell the user what would need to change, or how to do that step in wp-admin. Never say a step was done when it was not.'
            : 'All of them are available to this connection.';
        return implode( "\n", $lines ) . "\n";
    }

    




    private static function version_label( array $skill ): string {
        $version = Skill_Set::version( $skill );
        return null !== $version ? ' (version ' . $version . ')' : '';
    }

    
    private static function names( Skill_Set $served ): array {
        $names = array();
        foreach ( $served->skills() as $skill ) {
            $names[] = $skill['name'];
        }
        return $names;
    }
}
