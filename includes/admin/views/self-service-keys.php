<?php












if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$max_keys        = (int) self::limit( 'self_service_max_keys' );
$headings        = array(
    __( 'Name', 'easy-mcp-ai' ),
    __( 'Prefix', 'easy-mcp-ai' ),
    __( 'Last used (UTC)', 'easy-mcp-ai' ),
    __( 'Expiry (UTC)', 'easy-mcp-ai' ),
    __( 'Status', 'easy-mcp-ai' ),
    __( 'Actions', 'easy-mcp-ai' ),
);
?>
<h2 id="easy-mcp-keys">
    <?php
    printf(
        /* translators: %s: configured plugin brand name. */
        esc_html__( '%s API keys', 'easy-mcp-ai' ),
        esc_html( \Easy_MCP_AI\Config::get( 'brand_name' ) )
    );
    ?>
</h2>
<p><?php esc_html_e( 'Create a key for an automation or script. Keep it private. To change its tools or expiry, revoke it and create another.', 'easy-mcp-ai' ); ?></p>

<?php if ( $raw_token ) : ?>
<div class="notice notice-success inline">
    <p><?php esc_html_e( 'Copy this key now. It will not be shown again.', 'easy-mcp-ai' ); ?></p>
    <p><code class="easy-mcp-new-key"><?php echo esc_html( $raw_token ); ?></code></p>
</div>
<?php endif; ?>

<details id="easy-mcp-key-create" class="easy-mcp-key-create">
    <summary class="button button-secondary"><?php esc_html_e( 'Create API key', 'easy-mcp-ai' ); ?></summary>
    <div class="easy-mcp-key-create-panel">
        <p class="description">
            <?php
            printf(
                /* translators: %d: maximum active keys per user. */
                esc_html__( 'You can have up to %d active, unexpired keys, including keys created by an administrator.', 'easy-mcp-ai' ),
                $max_keys
            );
            ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="easy-mcp-key-name"><?php esc_html_e( 'Key name', 'easy-mcp-ai' ); ?></label></th>
                <td>
                    <input id="easy-mcp-key-name" name="key_name" form="easy-mcp-self-create" class="regular-text" maxlength="255" required>
                </td>
            </tr>
            <tr>
                <th><label for="easy-mcp-key-expiry"><?php esc_html_e( 'Expires after', 'easy-mcp-ai' ); ?></label></th>
                <td>
                    <select id="easy-mcp-key-expiry" name="expiry_days" form="easy-mcp-self-create">
                        <?php foreach ( self::expiry_presets() as $days ) : ?>
                        <option value="<?php echo esc_attr( $days ); ?>" <?php selected( (string) $days, \Easy_MCP_AI\Admin\Admin_Page::TOKEN_EXPIRY_DEFAULT_PRESET ); ?>>
                            <?php
                            printf(
                                /* translators: %d: key lifetime in days. */
                                esc_html( _n( '%d day', '%d days', $days, 'easy-mcp-ai' ) ),
                                (int) $days
                            );
                            ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="custom"><?php esc_html_e( 'Custom', 'easy-mcp-ai' ); ?></option>
                        <option value="never"><?php esc_html_e( 'No expiration', 'easy-mcp-ai' ); ?></option>
                    </select>
                    <div class="easy-mcp-key-custom-expiry">
                        <p><label for="easy-mcp-key-custom-date"><?php esc_html_e( 'Custom expiry date (UTC)', 'easy-mcp-ai' ); ?></label></p>
                        <input type="date" id="easy-mcp-key-custom-date" name="expiry_custom" form="easy-mcp-self-create"
                               min="<?php echo esc_attr( \Easy_MCP_AI\Admin\Admin_Page::token_expiry_preset_date( 1 ) ); ?>">
                    </div>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Allowed tools', 'easy-mcp-ai' ); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><?php esc_html_e( 'Select at least one tool', 'easy-mcp-ai' ); ?></legend>
                        <p class="easy-mcp-key-selection" hidden>
                            <button type="button" class="button" data-select-tools="all"><?php esc_html_e( 'Select all', 'easy-mcp-ai' ); ?></button>
                            <button type="button" class="button" data-select-tools="none"><?php esc_html_e( 'Clear selection', 'easy-mcp-ai' ); ?></button>
                        </p>
                        <?php ?>
                        <div class="easy-mcp-key-tools wp-mcp-tool-list">
                            <?php
                            foreach ( $tools as $tool ) :
                                
                                
                                $short_description = '';
                                if ( ! empty( $tool['description'] ) ) {
                                    $full_description  = (string) $tool['description'];
                                    $short_description = preg_match( '/^(.+?[.!?])(\s|$)/u', $full_description, $sentence )
                                        ? $sentence[1]
                                        : $full_description;
                                    if ( mb_strlen( $short_description ) > 160 ) {
                                        $short_description = rtrim( mb_substr( $short_description, 0, 157 ) ) . '…';
                                    }
                                }
                                ?>
                            <div class="wp-mcp-tool-row" data-tool-name="<?php echo esc_attr( $tool['name'] ); ?>">
                                <label class="wp-mcp-tool-label">
                                    <input type="checkbox" name="key_tools[]" value="<?php echo esc_attr( $tool['name'] ); ?>" form="easy-mcp-self-create">
                                    <span class="wp-mcp-tool-info">
                                        <code class="wp-mcp-tool-name"><?php echo esc_html( $tool['name'] ); ?></code>
                                        <?php if ( '' !== $short_description ) : ?>
                                        <span class="wp-mcp-tool-description"><?php echo esc_html( $short_description ); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php if ( ! $tools ) : ?>
                            <p class="wp-mcp-tool-row"><?php esc_html_e( 'No tools are available for your account.', 'easy-mcp-ai' ); ?></p>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                </td>
            </tr>
        </table>
        <p>
            <button type="submit" class="button button-secondary" form="easy-mcp-self-create" <?php disabled( ! $tools ); ?>><?php esc_html_e( 'Generate key', 'easy-mcp-ai' ); ?></button>
        </p>
    </div>
</details>

<table class="widefat striped">
    <thead>
        <tr>
            <?php foreach ( $headings as $heading ) : ?>
            <th scope="col"><?php echo esc_html( $heading ); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ( $tokens as $token ) :
            $form_id = 'easy-mcp-self-revoke-' . (int) $token['id'];
            
            $this->forms[ $form_id ] = array(
                'action' => 'easy_mcp_ai_self_revoke_key',
                'nonce'  => 'easy_mcp_ai_self_revoke_key_' . $token['id'],
                'key_id' => $token['id'],
            );
            $expired = ! empty( $token['expires_at'] ) && strtotime( $token['expires_at'] . ' UTC' ) < time();
            if ( ! $token['is_active'] ) {
                $status = __( 'Revoked', 'easy-mcp-ai' );
            } elseif ( $expired ) {
                $status = __( 'Expired', 'easy-mcp-ai' );
            } else {
                $status = __( 'Active', 'easy-mcp-ai' );
            }
            
            
            $bound_elsewhere = \Easy_MCP_AI\Auth\Token_Manager::is_bound_elsewhere( $token );
            ?>
        <tr>
            <td><?php echo esc_html( $token['name'] ); ?></td>
            <td><code><?php echo esc_html( $token['token_prefix'] ); ?></code></td>
            <td><?php echo esc_html( $token['last_used_at'] ? $token['last_used_at'] : '—' ); ?></td>
            <td><?php echo esc_html( $token['expires_at'] ? $token['expires_at'] : __( 'Never', 'easy-mcp-ai' ) ); ?></td>
            <td>
                <?php echo esc_html( $status ); ?>
                <?php if ( $bound_elsewhere ) : ?>
                <br><span class="description easy-mcp-ai-token-foreign"><?php esc_html_e( 'Issued on another site — ask an administrator to re-bind it.', 'easy-mcp-ai' ); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ( $token['is_active'] ) : ?>
                <button type="submit" class="button" form="<?php echo esc_attr( $form_id ); ?>"><?php esc_html_e( 'Revoke', 'easy-mcp-ai' ); ?></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if ( ! $tokens ) : ?>
        <tr>
            <td colspan="6"><?php esc_html_e( 'You have no API keys.', 'easy-mcp-ai' ); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<p>
    <?php if ( $page > 1 ) : ?>
    <a href="<?php echo esc_url( admin_url( 'profile.php?easy_mcp_keys_page=' . ( $page - 1 ) . '#easy-mcp-keys' ) ); ?>"><?php esc_html_e( 'Previous keys', 'easy-mcp-ai' ); ?></a>
    <?php endif; ?>
    <?php if ( $more ) : ?>
    <a href="<?php echo esc_url( admin_url( 'profile.php?easy_mcp_keys_page=' . ( $page + 1 ) . '#easy-mcp-keys' ) ); ?>"><?php esc_html_e( 'Next keys', 'easy-mcp-ai' ); ?></a>
    <?php endif; ?>
</p>
