<?php












namespace Easy_MCP_AI\Admin;

use Easy_MCP_AI\Ahrefs\Ahrefs_Client;
use Easy_MCP_AI\Config;
use Easy_MCP_AI\DFS\DataforSEO_Client;
use Easy_MCP_AI\GA\GA_Client;
use Easy_MCP_AI\GSC\GSC_Client;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\Tools\Tool_Registry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class External_Data_Service {

    
    const PROVIDERS = array( 'ga', 'gsc', 'dfs', 'semrush', 'seranking', 'ahrefs' );

    
    const CACHE_PROVIDERS = array( 'ga', 'gsc' );

    
    const BALANCE_PROVIDERS = array( 'dfs', 'semrush', 'seranking' );

    




    const OPTION_BALANCES = 'easy_mcp_ai_external_data_balances';

    
    private $gateway;

    
    private $definitions;

    
    private $definition_cache = array();

    



    public function __construct( $gateway = null, $definitions = null ) {
        if ( ! class_exists( '\\Easy_MCP_AI\\Admin\\External_Data_Admin' ) ) {
            require_once __DIR__ . '/class-external-data-admin.php';
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\Admin\\External_Data_Gateway' ) ) {
            require_once __DIR__ . '/class-external-data-gateway.php';
        }
        $this->gateway     = $gateway ? $gateway : new External_Data_Gateway();
        $this->definitions = $definitions ? $definitions : array( $this, 'load_definitions' );
    }

    
    public static function is_provider( $key ) {
        return in_array( $key, self::PROVIDERS, true );
    }

    
    const LABELS = array(
        'ga'        => 'Google Analytics',
        'gsc'       => 'Google Search Console',
        'dfs'       => 'DataForSEO',
        'semrush'   => 'Semrush',
        'seranking' => 'SE Ranking',
        'ahrefs'    => 'Ahrefs (DR)',
    );

    
    public static function label( $key ) {
        return self::LABELS[ $key ] ?? $key;
    }

    
    public static function weak_salts() {
        return ! defined( 'SECURE_AUTH_KEY' )
            || ! defined( 'SECURE_AUTH_SALT' )
            || strlen( SECURE_AUTH_KEY . SECURE_AUTH_SALT ) < 64
            || false !== strpos( SECURE_AUTH_KEY . SECURE_AUTH_SALT, 'put your unique phrase here' );
    }

    

    
    public function describe_all() {
        $providers = array();
        foreach ( self::PROVIDERS as $key ) {
            $providers[] = $this->describe( $key );
        }
        return array(
            'weakSalts' => self::weak_salts(),
            'locked'    => Config::is_locked( 'easy_mcp_ai_disabled_tools' ),
            'providers' => $providers,
        );
    }

    





    public function describe( $key ) {
        $configured  = $this->is_configured( $key );
        $credentials = array();
        $settings    = array();
        switch ( $key ) {
            case 'ga':
                $credentials['serviceAccountJson'] = array( 'configured' => $configured, 'masked' => $this->google_email( GA_Client::class ) );
                $settings['propertyId']            = (string) \get_option( GA_Client::OPTION_PROPERTY_ID, '' );
                $settings['properties']            = self::property_choices( (array) \get_option( External_Data_Admin::OPTION_GA_PROPS_CACHE, array() ) );
                break;
            case 'gsc':
                $credentials['serviceAccountJson'] = array( 'configured' => $configured, 'masked' => $this->google_email( GSC_Client::class ) );
                $settings['siteUrl']               = (string) \get_option( GSC_Client::OPTION_SITE_URL, '' );
                $settings['sites']                 = array_values( array_filter( array_map( 'strval', (array) \get_option( External_Data_Admin::OPTION_GSC_SITES_CACHE, array() ) ) ) );
                break;
            case 'dfs':
                $credentials['login']       = array( 'configured' => $configured, 'masked' => $configured ? self::head( self::decrypt_option( DataforSEO_Client::class, DataforSEO_Client::OPTION_LOGIN ) ) : null );
                $credentials['apiPassword'] = array( 'configured' => $configured, 'masked' => null );
                break;
            case 'semrush':
                $credentials['apiKey'] = array( 'configured' => $configured, 'masked' => $configured ? self::tail( self::decrypt_option( Semrush_Client::class, Semrush_Client::OPTION_API_KEY ) ) : null );
                break;
            case 'seranking':
                $credentials['apiKey'] = array( 'configured' => $configured, 'masked' => $configured ? self::tail( self::decrypt_option( SeRanking_Client::class, SeRanking_Client::OPTION_API_KEY ) ) : null );
                break;
            case 'ahrefs':
                $credentials['apiKey'] = array( 'configured' => $configured, 'masked' => $configured ? self::tail( self::decrypt_option( Ahrefs_Client::class, Ahrefs_Client::OPTION_API_KEY ) ) : null );
                break;
        }
        $disabled = $this->bucket( $key );
        $items    = $this->items( $key );
        return array(
            'key'           => $key,
            'label'         => self::label( $key ),
            'configured'    => $configured,
            'enabled'       => $this->is_enabled( $key, $items ),
            'credentials'   => $credentials,
            'settings'      => $settings,
            'balance'       => $this->stored_balance( $key ),
            'disabledTools' => $disabled,
            'items'         => $items,
        );
    }

    
    public function is_configured( $key ) {
        switch ( $key ) {
            case 'ga':
                return '' !== (string) \get_option( GA_Client::OPTION_JSON, '' );
            case 'gsc':
                return '' !== (string) \get_option( GSC_Client::OPTION_JSON, '' );
            case 'dfs':
                return '' !== (string) \get_option( DataforSEO_Client::OPTION_LOGIN, '' )
                    && '' !== (string) \get_option( DataforSEO_Client::OPTION_API_PASSWORD, '' );
            case 'semrush':
                return '' !== (string) \get_option( Semrush_Client::OPTION_API_KEY, '' );
            case 'seranking':
                return '' !== (string) \get_option( SeRanking_Client::OPTION_API_KEY, '' );
            case 'ahrefs':
                return '' !== (string) \get_option( Ahrefs_Client::OPTION_API_KEY, '' );
        }
        return false;
    }

    



    private function is_enabled( $key, array $items ) {
        if ( ! $this->is_configured( $key ) ) {
            return false;
        }
        if ( 'ahrefs' === $key && ! (bool) \get_option( 'easy_mcp_ai_ahrefs_enabled', false ) ) {
            return false;
        }
        foreach ( $items as $item ) {
            if ( $item['enabled'] ) {
                return true;
            }
        }
        return false;
    }

    
    public function tool_names( $key ) {
        return array_column( $this->definitions_for( $key ), 'name' );
    }

    
    private function bucket( $key ) {
        return array_values( array_map( 'strval', (array) Config::get( 'easy_mcp_ai_disabled_' . $key . '_tools', array() ) ) );
    }

    
    private function items( $key ) {
        $disabled = (array) Config::get( 'easy_mcp_ai_disabled_tools', array() );
        $items    = array();
        foreach ( $this->definitions_for( $key ) as $definition ) {
            $name    = (string) $definition['name'];
            $items[] = array(
                'name'               => $name,
                'description'        => (string) ( $definition['description'] ?? '' ),
                'access'             => ! empty( $definition['annotations']['readOnlyHint'] ) ? 'read' : 'write',
                'requiredCapability' => (string) ( $definition['required_capability'] ?? '' ),
                'provenance'         => 'external',
                'enabled'            => ! in_array( $name, $disabled, true ),
                'pluginRestApi'      => false,
            );
        }
        return $items;
    }

    private function definitions_for( $key ) {
        if ( ! isset( $this->definition_cache[ $key ] ) ) {
            $this->definition_cache[ $key ] = (array) call_user_func( $this->definitions, $key );
        }
        return $this->definition_cache[ $key ];
    }

    







    public function load_definitions( $key ) {
        $dir = EASY_MCP_AI_PLUGIN_DIR . 'includes/tools/' . $key . '/';
        if ( ! is_dir( $dir ) ) {
            return array();
        }
        foreach ( array(
            'includes/dfs/class-dataforseo-client.php',
            'includes/semrush/class-semrush-client.php',
            'includes/semrush/class-semrush-validators.php',
            'includes/seranking/class-seranking-client.php',
            'includes/seranking/class-seranking-validators.php',
        ) as $dependency ) {
            if ( file_exists( EASY_MCP_AI_PLUGIN_DIR . $dependency ) ) {
                require_once EASY_MCP_AI_PLUGIN_DIR . $dependency;
            }
        }
        foreach ( (array) glob( $dir . 'class-*.php' ) as $file ) {
            require_once $file;
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\Tools\\Tool_Registry' ) ) {
            return array();
        }
        $registry = new Tool_Registry();
        $registry->auto_discover( true );
        $rows = array();
        foreach ( (array) ( $registry->get_tools_by_category()[ $key ] ?? array() ) as $definition ) {
            $tool = $registry->get_tool( $definition['name'] );
            $cap  = $tool && method_exists( $tool, 'get_required_capability' ) ? (string) $tool->get_required_capability() : '';
            if ( class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) ) {
                $cap = (string) \Easy_MCP_AI\MCP\Server::effective_required_capability( $key, $cap );
            }
            $definition['required_capability'] = $cap;
            $rows[]                            = $definition;
        }
        return $rows;
    }

    
    private function google_email( $client ) {
        $stored = (string) \get_option( $client::OPTION_JSON, '' );
        if ( '' === $stored ) {
            return null;
        }
        try {
            $json = $client::decrypt( $stored );
        } catch ( \RuntimeException $e ) {
            return null;
        }
        $decoded = is_string( $json ) ? json_decode( $json, true ) : null;
        return is_array( $decoded ) && ! empty( $decoded['client_email'] ) ? (string) $decoded['client_email'] : null;
    }

    
    private static function decrypt_option( $client, $option ) {
        $stored = (string) \get_option( $option, '' );
        if ( '' === $stored ) {
            return null;
        }
        try {
            $plain = $client::decrypt( $stored );
        } catch ( \RuntimeException $e ) {
            return null;
        }
        return is_string( $plain ) && '' !== $plain ? $plain : null;
    }

    
    private static function tail( $value ) {
        if ( null === $value ) {
            return null;
        }
        return mb_substr( $value, -4 );
    }

    
    private static function head( $value ) {
        if ( null === $value ) {
            return null;
        }
        return mb_substr( $value, 0, 3 );
    }

    
    private static function property_choices( array $cached ) {
        $choices = array();
        foreach ( $cached as $row ) {
            if ( is_array( $row ) && isset( $row['id'] ) ) {
                $choices[] = array( 'id' => (string) $row['id'], 'label' => (string) ( $row['label'] ?? $row['id'] ) );
            }
        }
        return $choices;
    }

    
    private function stored_balance( $key ) {
        $all = \get_option( self::OPTION_BALANCES, array() );
        $row = is_array( $all ) && isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : null;
        if ( ! $row || ! isset( $row['value'] ) || ! is_numeric( $row['value'] ) || empty( $row['unit'] ) ) {
            return null;
        }
        return array(
            'value'     => $row['value'] + 0,
            'unit'      => (string) $row['unit'],
            'fetchedAt' => (string) ( $row['fetchedAt'] ?? '' ),
        );
    }

    private function store_balance( $key, $value, $unit ) {
        $all = \get_option( self::OPTION_BALANCES, array() );
        $all = is_array( $all ) ? $all : array();
        $all[ $key ] = array( 'value' => $value, 'unit' => $unit, 'fetchedAt' => gmdate( 'c' ) );
        \update_option( self::OPTION_BALANCES, $all, false );
    }

    private function forget_balance( $key ) {
        $all = \get_option( self::OPTION_BALANCES, array() );
        if ( is_array( $all ) && isset( $all[ $key ] ) ) {
            unset( $all[ $key ] );
            \update_option( self::OPTION_BALANCES, $all, false );
        }
    }

    

    
    private static function invalid( $field, $message, $code = 'easy_mcp_ai_invalid_param' ) {
        return new \WP_Error( $code, $message, array( 'status' => 400, 'field' => $field ) );
    }

    private static function weak_salts_error() {
        return new \WP_Error(
            'easy_mcp_ai_weak_salts',
            \__( 'Credentials cannot be saved: the WordPress security salts are missing or still placeholders. Set unique SECURE_AUTH_KEY and SECURE_AUTH_SALT values in wp-config.php.', 'easy-mcp-ai' ),
            array( 'status' => 400, 'field' => '' )
        );
    }

    
    private static function text( array $input, $field ) {
        if ( ! array_key_exists( $field, $input ) ) {
            return '';
        }
        if ( null === $input[ $field ] ) {
            return null;
        }
        if ( ! is_string( $input[ $field ] ) ) {
            return self::invalid( $field, \__( 'Expected a string.', 'easy-mcp-ai' ) );
        }
        return trim( $input[ $field ] );
    }

    









    public function save( $key, array $input ) {
        switch ( $key ) {
            case 'ga':
                return $this->save_google( 'ga', GA_Client::class, $input );
            case 'gsc':
                return $this->save_google( 'gsc', GSC_Client::class, $input );
            case 'dfs':
                return $this->save_dfs( $input );
            case 'semrush':
                return $this->save_key( 'semrush', Semrush_Client::class, $input );
            case 'seranking':
                return $this->save_key( 'seranking', SeRanking_Client::class, $input );
            case 'ahrefs':
                return $this->save_key( 'ahrefs', Ahrefs_Client::class, $input );
        }
        return new \WP_Error( 'easy_mcp_ai_not_found', \__( 'Unknown provider.', 'easy-mcp-ai' ), array( 'status' => 404 ) );
    }

    private function save_google( $key, $client, array $input ) {
        $was_configured = $this->is_configured( $key );
        $json           = self::text( $input, 'serviceAccountJson' );
        if ( \is_wp_error( $json ) ) {
            return $json;
        }
        $json = (string) $json;
        if ( '' !== $json ) {
            $checked = self::check_service_account_json( $json );
            if ( \is_wp_error( $checked ) ) {
                return $checked;
            }
        }

        $setting_field = 'ga' === $key ? 'propertyId' : 'siteUrl';
        $setting_value = self::text( $input, $setting_field );
        if ( \is_wp_error( $setting_value ) ) {
            return $setting_value;
        }
        $normalized = '';
        if ( is_string( $setting_value ) && '' !== $setting_value ) {
            try {
                $normalized = 'ga' === $key
                    ? GA_Client::normalize_property( $setting_value )
                    : \sanitize_text_field( GSC_Client::validate_site_url( $setting_value ) );
            } catch ( \Exception $e ) {
                return self::invalid(
                    $setting_field,
                    'ga' === $key
                        ? \__( 'Enter a numeric GA4 property ID (for example 123456789) or the full "properties/123456789" form.', 'easy-mcp-ai' )
                        : \__( 'Use a full URL (https://example.com/) or a domain property (sc-domain:example.com).', 'easy-mcp-ai' )
                );
            }
        }

        if ( '' !== $json ) {
            if ( self::weak_salts() ) {
                return self::weak_salts_error();
            }
            \update_option( $client::OPTION_JSON, $client::encrypt( $json ), false );
            External_Data_Admin::purge_transients_by_prefix( 'easy_mcp_ai_' . $key . '_' );
        }
        $setting_option = 'ga' === $key ? GA_Client::OPTION_PROPERTY_ID : GSC_Client::OPTION_SITE_URL;
        if ( null === $setting_value ) {
            \delete_option( $setting_option );
        } elseif ( '' !== $normalized ) {
            \update_option( $setting_option, $normalized );
        }
        if ( $this->is_configured( $key ) ) {
            $this->refresh_google_cache( $key );
        }
        if ( '' !== $json && ! $was_configured ) {
            $this->enable_all( $key );
        }
        return true;
    }

    
    private static function check_service_account_json( $json ) {
        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            return self::invalid( 'serviceAccountJson', \__( 'That is not valid JSON. Paste the service account key from Google Cloud Console again.', 'easy-mcp-ai' ) );
        }
        if ( ( $decoded['type'] ?? '' ) !== 'service_account' ) {
            return self::invalid( 'serviceAccountJson', \__( 'The pasted JSON is not a service account key (its type must be "service_account"). Download the service account key from Google Cloud Console again.', 'easy-mcp-ai' ) );
        }
        foreach ( array( 'private_key', 'client_email', 'token_uri' ) as $field ) {
            if ( empty( $decoded[ $field ] ) ) {
                return self::invalid( 'serviceAccountJson', \__( 'The service account JSON is missing one of the required fields (private_key, client_email, token_uri).', 'easy-mcp-ai' ) );
            }
        }
        return true;
    }

    
    private function refresh_google_cache( $key ) {
        try {
            if ( 'ga' === $key ) {
                External_Data_Admin::write_ga_properties_cache( $this->gateway->ga_account_summaries() );
            } else {
                External_Data_Admin::write_gsc_sites_cache( $this->gateway->gsc_sites() );
            }
        } catch ( \Throwable $e ) {
            
        }
    }

    private function save_dfs( array $input ) {
        $login = self::text( $input, 'login' );
        if ( \is_wp_error( $login ) ) {
            return $login;
        }
        $password = self::text( $input, 'apiPassword' );
        if ( \is_wp_error( $password ) ) {
            return $password;
        }
        $login    = (string) $login;
        $password = (string) $password;
        if ( '' === $login && '' === $password ) {
            return true;
        }
        if ( '' === $login || '' === $password ) {
            return self::invalid( '' === $login ? 'login' : 'apiPassword', \__( 'Save the login and the API password together.', 'easy-mcp-ai' ) );
        }
        if ( self::weak_salts() ) {
            return self::weak_salts_error();
        }
        $was_configured  = $this->is_configured( 'dfs' );
        $previous_login  = (string) \get_option( DataforSEO_Client::OPTION_LOGIN, '' );
        $previous_secret = (string) \get_option( DataforSEO_Client::OPTION_API_PASSWORD, '' );
        try {
            \update_option( DataforSEO_Client::OPTION_LOGIN, DataforSEO_Client::encrypt( $login ), false );
            \update_option( DataforSEO_Client::OPTION_API_PASSWORD, DataforSEO_Client::encrypt( $password ), false );
        } catch ( \RuntimeException $e ) {
            return self::weak_salts_error();
        }
        External_Data_Admin::purge_transients_by_prefix( DataforSEO_Client::TRANSIENT_BALANCE_PREFIX );
        try {
            $balance = $this->gateway->dfs_balance();
        } catch ( \RuntimeException $e ) {
            $this->restore_or_delete( array( DataforSEO_Client::OPTION_LOGIN => $previous_login, DataforSEO_Client::OPTION_API_PASSWORD => $previous_secret ), $was_configured );
            return self::rejected( 'login', \__( 'DataForSEO rejected these credentials, so they were not saved.', 'easy-mcp-ai' ), $e );
        }
        $this->store_balance( 'dfs', (float) $balance['balance'], 'USD' );
        if ( ! $was_configured ) {
            $this->enable_all( 'dfs' );
        }
        return true;
    }

    private function save_key( $key, $client, array $input ) {
        $api_key = self::text( $input, 'apiKey' );
        if ( \is_wp_error( $api_key ) ) {
            return $api_key;
        }
        $api_key = (string) $api_key;
        if ( '' === $api_key ) {
            return true;
        }
        if ( self::weak_salts() ) {
            return self::weak_salts_error();
        }
        $was_configured = $this->is_configured( $key );
        $previous       = (string) \get_option( $client::OPTION_API_KEY, '' );
        try {
            \update_option( $client::OPTION_API_KEY, $client::encrypt( $api_key ), false );
        } catch ( \RuntimeException $e ) {
            return self::weak_salts_error();
        }
        try {
            switch ( $key ) {
                case 'semrush':
                    $this->store_balance( 'semrush', (int) $this->gateway->semrush_balance()['balance'], 'API units' );
                    break;
                case 'seranking':
                    $this->store_balance( 'seranking', (int) $this->gateway->seranking_balance()['units_left'], 'credits' );
                    break;
                default:
                    $this->gateway->ahrefs_verify();
            }
        } catch ( \RuntimeException $e ) {
            $this->restore_or_delete( array( $client::OPTION_API_KEY => $previous ), $was_configured );
            if ( 'ahrefs' === $key && ! $was_configured ) {
                \update_option( 'easy_mcp_ai_ahrefs_enabled', false );
            }
            return self::rejected(
                'apiKey',
                sprintf(
                    /* translators: %s: provider name */
                    \__( '%s rejected this API key, so it was not saved.', 'easy-mcp-ai' ),
                    self::label( $key )
                ),
                $e
            );
        }
        if ( ! $was_configured ) {
            $this->enable_all( $key );
        }
        return true;
    }

    





    private function restore_or_delete( array $previous, $was_configured ) {
        foreach ( $previous as $option => $value ) {
            if ( $was_configured && '' !== $value ) {
                \update_option( $option, $value, false );
            } else {
                \delete_option( $option );
            }
        }
    }

    
    private static function rejected( $field, $message, \RuntimeException $e ) {
        return new \WP_Error(
            'easy_mcp_ai_invalid_credentials',
            $message,
            array( 'status' => 400, 'field' => $field, 'detail' => Config::brand( $e->getMessage() ) )
        );
    }

    




    private function enable_all( $key ) {
        if ( Config::is_locked( 'disabled_tools' ) ) {
            return;
        }
        Config::update( 'easy_mcp_ai_disabled_' . $key . '_tools', array() );
        if ( 'ahrefs' === $key ) {
            \update_option( 'easy_mcp_ai_ahrefs_enabled', ! empty( $this->tool_names( 'ahrefs' ) ) );
        }
        $this->sync_global_disabled( $key );
    }

    



    public function sync_global_disabled( $key ) {
        $global = (array) Config::get( 'easy_mcp_ai_disabled_tools', array() );
        $other  = array_values( array_diff( $global, $this->tool_names( $key ) ) );
        Config::update( 'easy_mcp_ai_disabled_tools', External_Data_Admin::merge_disabled_tool_buckets( $other ) );
    }

    







    public function apply_enabled( $key, array $checked ) {
        $disabled = External_Data_Admin::compute_disabled_tools( $this->tool_names( $key ), $checked );
        Config::update( 'easy_mcp_ai_disabled_' . $key . '_tools', $disabled );
        if ( 'ahrefs' === $key ) {
            \update_option( 'easy_mcp_ai_ahrefs_enabled', ! empty( $this->tool_names( 'ahrefs' ) ) && empty( $disabled ) );
        }
        $this->sync_global_disabled( $key );
    }

    






    public function test( $key ) {
        if ( ! $this->is_configured( $key ) ) {
            return array( 'ok' => false, 'message' => \__( 'Save credentials for this provider first.', 'easy-mcp-ai' ) );
        }
        try {
            switch ( $key ) {
                case 'ga':
                    $data     = $this->gateway->ga_account_summaries();
                    $accounts = (array) ( $data['accountSummaries'] ?? array() );
                    $props    = 0;
                    foreach ( $accounts as $account ) {
                        $props += count( (array) ( $account['propertySummaries'] ?? array() ) );
                    }
                    External_Data_Admin::write_ga_properties_cache( $data );
                    return array(
                        'ok'      => true,
                        'message' => sprintf(
                            /* translators: 1: account count, 2: property count */
                            \_n( 'Connected. Found %1$d account, %2$d properties.', 'Connected. Found %1$d accounts, %2$d properties.', count( $accounts ), 'easy-mcp-ai' ),
                            count( $accounts ),
                            $props
                        ),
                    );
                case 'gsc':
                    $data  = $this->gateway->gsc_sites();
                    $count = count( (array) ( $data['siteEntry'] ?? array() ) );
                    External_Data_Admin::write_gsc_sites_cache( $data );
                    return array(
                        'ok'      => true,
                        'message' => sprintf(
                            /* translators: %d: property count */
                            \_n( 'Connected. Found %d property.', 'Connected. Found %d properties.', $count, 'easy-mcp-ai' ),
                            $count
                        ),
                    );
                case 'dfs':
                    $balance = $this->gateway->dfs_balance();
                    $this->store_balance( 'dfs', (float) $balance['balance'], 'USD' );
                    return array(
                        'ok'      => true,
                        'message' => sprintf(
                            /* translators: %s: balance in USD */
                            \__( 'Connected. Balance: $%s USD.', 'easy-mcp-ai' ),
                            number_format( (float) $balance['balance'], 2 )
                        ),
                    );
                case 'semrush':
                    $units = (int) $this->gateway->semrush_balance()['balance'];
                    $this->store_balance( 'semrush', $units, 'API units' );
                    return array(
                        'ok'      => true,
                        'message' => sprintf(
                            /* translators: %d: API units */
                            \__( 'Connected. Balance: %d API units.', 'easy-mcp-ai' ),
                            $units
                        ),
                    );
                case 'seranking':
                    $units = (int) $this->gateway->seranking_balance()['units_left'];
                    $this->store_balance( 'seranking', $units, 'credits' );
                    return array(
                        'ok'      => true,
                        'message' => sprintf(
                            /* translators: %d: credits */
                            \__( 'Connected. Balance: %d credits.', 'easy-mcp-ai' ),
                            $units
                        ),
                    );
                case 'ahrefs':
                    $this->gateway->ahrefs_verify();
                    return array( 'ok' => true, 'message' => \__( 'Connected. Ahrefs accepted the key.', 'easy-mcp-ai' ) );
            }
        } catch ( \RuntimeException $e ) {
            return array( 'ok' => false, 'message' => Config::brand( $e->getMessage() ) );
        }
        return array( 'ok' => false, 'message' => \__( 'Unknown provider.', 'easy-mcp-ai' ) );
    }

    







    public function remove_key( $key ) {
        switch ( $key ) {
            case 'ga':
                \delete_option( GA_Client::OPTION_JSON );
                \delete_option( GA_Client::OPTION_PROPERTY_ID );
                \delete_option( External_Data_Admin::OPTION_GA_PROPS_CACHE );
                External_Data_Admin::purge_transients_by_prefix( 'easy_mcp_ai_ga_' );
                break;
            case 'gsc':
                \delete_option( GSC_Client::OPTION_JSON );
                \delete_option( GSC_Client::OPTION_SITE_URL );
                \delete_option( External_Data_Admin::OPTION_GSC_SITES_CACHE );
                External_Data_Admin::purge_transients_by_prefix( 'easy_mcp_ai_gsc_' );
                break;
            case 'dfs':
                \delete_option( DataforSEO_Client::OPTION_LOGIN );
                \delete_option( DataforSEO_Client::OPTION_API_PASSWORD );
                External_Data_Admin::purge_transients_by_prefix( DataforSEO_Client::TRANSIENT_BALANCE_PREFIX );
                break;
            case 'semrush':
                \delete_option( Semrush_Client::OPTION_API_KEY );
                break;
            case 'seranking':
                \delete_option( SeRanking_Client::OPTION_API_KEY );
                break;
            case 'ahrefs':
                \delete_option( Ahrefs_Client::OPTION_API_KEY );
                \update_option( 'easy_mcp_ai_ahrefs_enabled', false );
                break;
        }
        Config::delete( 'easy_mcp_ai_disabled_' . $key . '_tools' );
        $this->forget_balance( $key );
        $global = (array) Config::get( 'easy_mcp_ai_disabled_tools', array() );
        Config::update( 'easy_mcp_ai_disabled_tools', array_values( array_diff( $global, $this->tool_names( $key ) ) ) );
    }

    






    public function clear_cache( $key ) {
        if ( ! in_array( $key, self::CACHE_PROVIDERS, true ) ) {
            return new \WP_Error( 'easy_mcp_ai_unsupported', \__( 'This provider keeps no cache.', 'easy-mcp-ai' ), array( 'status' => 400 ) );
        }
        External_Data_Admin::purge_transients_by_prefix( 'easy_mcp_ai_' . $key . '_' );
        if ( $this->is_configured( $key ) ) {
            $this->refresh_google_cache( $key );
        }
        return true;
    }

    





    public function refresh_balance( $key ) {
        if ( ! in_array( $key, self::BALANCE_PROVIDERS, true ) ) {
            return new \WP_Error( 'easy_mcp_ai_unsupported', \__( 'This provider has no balance to refresh.', 'easy-mcp-ai' ), array( 'status' => 400 ) );
        }
        if ( ! $this->is_configured( $key ) ) {
            return array( 'ok' => false, 'message' => \__( 'Save credentials for this provider first.', 'easy-mcp-ai' ) );
        }
        try {
            switch ( $key ) {
                case 'dfs':
                    External_Data_Admin::purge_transients_by_prefix( DataforSEO_Client::TRANSIENT_BALANCE_PREFIX );
                    $this->store_balance( 'dfs', (float) $this->gateway->dfs_balance()['balance'], 'USD' );
                    break;
                case 'semrush':
                    $this->store_balance( 'semrush', (int) $this->gateway->semrush_balance()['balance'], 'API units' );
                    break;
                default:
                    $this->store_balance( 'seranking', (int) $this->gateway->seranking_balance()['units_left'], 'credits' );
            }
        } catch ( \RuntimeException $e ) {
            return array( 'ok' => false, 'message' => Config::brand( $e->getMessage() ) );
        }
        return array( 'ok' => true, 'balance' => $this->stored_balance( $key ) );
    }
}
