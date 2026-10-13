<?php
namespace Easy_MCP_AI\Tools\Site_Health;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Text_Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































class Get_Site_Health extends Base_Tool {

    





    const INFO_LIMIT = 65536;

    
    const RESPONSE_JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

    public function get_name() {
        return 'wp_get_site_health';
    }

    public function get_description() {
        return 'Get the WordPress Site Health report: runs core\'s Site Health status tests and returns the Site Health Info data. Optional: `include_async` (default false; also runs the slower tests that contact WordPress.org, make loopback requests and check HTTPS and page cache — can take several seconds), `include_info` (default true). Returns { counts: { good, recommended, critical }, tests: [{ test, label, status (good | recommended | critical | error), badge, description, actions }], skipped: [{ test, label, reason }], info: { <section>: { label, fields: { <name>: { label, value } } } }, info_truncated, info_omitted }. Text is plain (HTML stripped). Info leaves out every field core marks private — the same set core\'s "Copy site info to clipboard" leaves out (database credentials, table prefix, home/site URLs, ABSPATH) — and redacts secret-shaped values; directory sizes are not computed. Requires the view_site_health_checks capability (administrators; on multisite, super admins only).';
    }

    public function get_category() {
        return 'site_health';
    }

    public function get_required_capability() {
        return 'view_site_health_checks';
    }

    public function get_annotations() {
        
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'include_async' => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Also run the slower tests (WordPress.org communication, loopback, background updates, HTTPS, page cache).',
                ),
                'include_info'  => array(
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Include the Site Health Info data (versions, server, database, constants, plugins, themes).',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $include_async = Site_Health_Guard::flag( $arguments, 'include_async', false );
        $include_info  = Site_Health_Guard::flag( $arguments, 'include_info', true );

        $this->load_core( $include_info );

        $run    = $this->run_tests( $this->get_tests(), $include_async );
        $result = array(
            'counts'  => $run['counts'],
            'tests'   => $run['tests'],
            'skipped' => $run['skipped'],
        );

        if ( $include_info ) {
            $info                     = $this->build_info( $this->debug_data() );
            $result['info']           = $info['info'];
            $result['info_truncated'] = $info['truncated'];
            $result['info_omitted']   = $info['omitted'];
            $result                   = self::fit_info( $result, $info['order'] );
        }

        return $result;
    }

    
    const REASON_BROWSER_SESSION = 'Needs a logged-in browser session; core skips it outside the Site Health screen too.';

    








    protected function run_tests( array $tests, bool $include_async ): array {
        $out     = array();
        $skipped = array();
        $this->run_direct( isset( $tests['direct'] ) && is_array( $tests['direct'] ) ? $tests['direct'] : array(), $out, $skipped );
        $this->run_async( isset( $tests['async'] ) && is_array( $tests['async'] ) ? $tests['async'] : array(), (bool) $include_async, $out, $skipped );

        return array( 'counts' => self::count_statuses( $out ), 'tests' => $out, 'skipped' => $skipped );
    }

    




    private function run_direct( array $direct, array &$out, array &$skipped ): void {
        foreach ( $direct as $name => $test ) {
            $label = isset( $test['label'] ) ? (string) $test['label'] : (string) $name;
            if ( ! empty( $test['skip_cron'] ) ) {
                $skipped[] = self::skipped_row( $name, $label, self::REASON_BROWSER_SESSION );
                continue;
            }
            $callback = $this->direct_callback( $test );
            if ( null === $callback ) {
                $skipped[] = self::skipped_row( $name, $label, 'The test has no callable runner.' );
                continue;
            }
            $out[] = $this->perform( (string) $name, $label, $callback );
        }
    }

    






    private function direct_callback( array $test ): ?callable {
        $spec = isset( $test['test'] ) ? $test['test'] : null;
        if ( is_string( $spec ) ) {
            $inst   = $this->site_health_instance();
            $method = 'get_test_' . $spec;
            if ( is_object( $inst ) && method_exists( $inst, $method ) && is_callable( array( $inst, $method ) ) ) {
                return array( $inst, $method );
            }
        }
        return is_callable( $spec ) ? $spec : null;
    }

    





    private function run_async( array $async, bool $include_async, array &$out, array &$skipped ): void {
        foreach ( $async as $name => $test ) {
            $label  = isset( $test['label'] ) ? (string) $test['label'] : (string) $name;
            $reason = self::async_skip_reason( $test, $include_async );
            if ( null !== $reason ) {
                $skipped[] = self::skipped_row( $name, $label, $reason );
                continue;
            }
            $out[] = $this->perform( (string) $name, $label, $test['async_direct_test'] );
        }
    }

    






    private static function async_skip_reason( array $test, bool $include_async ): ?string {
        if ( ! $include_async ) {
            return 'Slow test; pass include_async=true to run it.';
        }
        if ( ! empty( $test['skip_cron'] ) ) {
            return self::REASON_BROWSER_SESSION;
        }
        if ( empty( $test['async_direct_test'] ) || ! is_callable( $test['async_direct_test'] ) ) {
            return 'This test only runs from the Site Health screen in a browser.';
        }
        return null;
    }

    





    private static function skipped_row( $name, string $label, string $reason ): array {
        return array( 'test' => (string) $name, 'label' => $label, 'reason' => $reason );
    }

    






    private static function count_statuses( array $rows ): array {
        $counts = array( 'good' => 0, 'recommended' => 0, 'critical' => 0 );
        foreach ( $rows as $row ) {
            if ( isset( $counts[ $row['status'] ] ) ) {
                ++$counts[ $row['status'] ];
            }
        }
        return $counts;
    }

    







    private function perform( string $name, string $label, callable $callback ): array {
        try {
            $r = \apply_filters( 'site_status_test_result', call_user_func( $callback ) );
        } catch ( \Throwable $e ) {
            return array(
                'test'        => $name,
                'label'       => self::text( $label ),
                'status'      => 'error',
                'badge'       => null,
                'description' => 'The test could not run: ' . self::text( Text_Redactor::redact( $e->getMessage() ) ),
                'actions'     => '',
            );
        }
        if ( ! is_array( $r ) ) {
            $r = array();
        }
        $status = isset( $r['status'] ) && in_array( $r['status'], array( 'good', 'recommended', 'critical' ), true ) ? $r['status'] : 'error';
        return array(
            'test'        => isset( $r['test'] ) && is_string( $r['test'] ) ? $r['test'] : $name,
            'label'       => self::text( isset( $r['label'] ) ? $r['label'] : $label ),
            'status'      => $status,
            'badge'       => isset( $r['badge']['label'] ) ? self::text( $r['badge']['label'] ) : null,
            'description' => self::text( isset( $r['description'] ) ? $r['description'] : '' ),
            'actions'     => self::text( isset( $r['actions'] ) ? $r['actions'] : '' ),
        );
    }

    















    protected function build_info( $data ): array {
        $info    = array();
        $omitted = array();
        $order   = array();
        $used    = 0;
        $root    = rtrim( str_replace( '\\', '/', (string) ABSPATH ), '/' );

        foreach ( is_array( $data ) ? $data : array() as $section => $details ) {
            if ( ! is_array( $details ) || empty( $details['fields'] ) || ! empty( $details['private'] ) ) {
                continue;
            }
            $fields = array();
            foreach ( (array) $details['fields'] as $field_name => $field ) {
                if ( ! is_array( $field ) || ( isset( $field['private'] ) && true === $field['private'] ) ) {
                    continue;
                }
                $value = array_key_exists( 'debug', $field ) ? $field['debug'] : ( isset( $field['value'] ) ? $field['value'] : '' );
                if ( 'loading...' === $value ) {
                    continue;
                }
                $fields[ (string) $field_name ] = array(
                    'label' => self::text( isset( $field['label'] ) ? $field['label'] : $field_name ),
                    'value' => self::info_value( $value, (string) $field_name, $root ),
                );
            }
            if ( empty( $fields ) ) {
                continue;
            }
            $order[] = (string) $section;
            $block   = array(
                'label'  => self::text( isset( $details['label'] ) ? $details['label'] : $section ),
                'fields' => $fields,
            );
            $size = strlen( (string) wp_json_encode( $block ) );
            if ( $used + $size > self::INFO_LIMIT ) {
                $omitted[] = (string) $section;
                continue;
            }
            $used                       += $size;
            $info[ (string) $section ] = $block;
        }

        return array( 'info' => $info, 'truncated' => ! empty( $omitted ), 'omitted' => $omitted, 'order' => $order );
    }

    








    private static function info_value( $value, string $key, string $root ) {
        if ( is_array( $value ) ) {
            $out = array();
            foreach ( $value as $k => $v ) {
                $out[ (string) $k ] = self::info_value( is_array( $v ) ? wp_json_encode( $v ) : $v, (string) $k, $root );
            }
            return $out;
        }
        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
            return $value;
        }
        $redacted = Text_Redactor::redact_value( self::text( $value ), $key );
        if ( is_string( $redacted ) && '' !== $root && '/' !== $root ) {
            $redacted = str_replace( $root, 'ABSPATH', str_replace( '\\', '/', $redacted ) );
        }
        return $redacted;
    }

    

















    private static function fit_info( array $result, array $order ): array {
        $json = \wp_json_encode( $result, self::RESPONSE_JSON_FLAGS );
        while ( false !== $json && strlen( $json ) > self::INFO_LIMIT && ! empty( $result['info'] ) ) {
            end( $result['info'] );
            $last = (string) key( $result['info'] );
            unset( $result['info'][ $last ] );
            
            
            $omitted                  = array_merge( $result['info_omitted'], array( $last ) );
            $result['info_omitted']   = array_values( array_intersect( $order, $omitted ) );
            $result['info_truncated'] = true;
            $json                     = \wp_json_encode( $result, self::RESPONSE_JSON_FLAGS );
        }
        return $result;
    }

    





    private static function text( $html ): string {
        $s = \wp_strip_all_tags( (string) $html );
        $s = html_entity_decode( $s, ENT_QUOTES, 'UTF-8' );
        return trim( preg_replace( '/\s+/u', ' ', $s ) );
    }

    

    









    protected function load_core( $include_info ) {
        require_once ABSPATH . 'wp-admin/includes/admin.php';
        if ( ! class_exists( 'WP_Site_Health' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
        }
        if ( $include_info && ! class_exists( 'WP_Debug_Data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
        }
        if ( ! \is_admin() && function_exists( 'determine_locale' ) ) {
            $locale = \determine_locale();
            \load_textdomain( 'default', WP_LANG_DIR . "/admin-$locale.mo", $locale );
        }
    }

    
    protected function get_tests() {
        $tests = \WP_Site_Health::get_tests();
        
        if ( \WP_Site_Health::get_instance()->is_development_environment() ) {
            unset( $tests['async']['https_status'] );
        }
        return $tests;
    }

    




    protected function site_health_instance() {
        return \WP_Site_Health::get_instance();
    }

    
    protected function debug_data() {
        return \WP_Debug_Data::debug_data();
    }
}
