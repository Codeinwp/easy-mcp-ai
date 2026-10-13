<?php


































if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-console-styles.php';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html__( 'Authorize Application', 'easy-mcp-ai' ); ?> &mdash; <?php echo esc_html( \Easy_MCP_AI\Console_Styles::site_label() ); ?></title>
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plugin-owned stylesheet; see Console_Styles::inline().
    echo \Easy_MCP_AI\Console_Styles::inline();
    ?>
</head>
<body class="emcp-page">

<div class="emcp-shell">

    <div class="emcp-topbar">
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin-owned markup; see Console_Styles::logo().
        echo \Easy_MCP_AI\Console_Styles::logo();
        ?>
        <h1 class="emcp-topbar__title"><?php echo esc_html__( 'Authorize Application', 'easy-mcp-ai' ); ?></h1>
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside Console_Styles::site_name().
        echo \Easy_MCP_AI\Console_Styles::site_name();
        ?>
    </div>

    <?php
    







    ?>
    <form method="post" action="<?php echo esc_url( $form_action ); ?>">
        <?php wp_nonce_field( $nonce_action ); ?>

        <?php
        





        foreach ( $hidden_fields as $easy_mcp_ai_hidden_name => $easy_mcp_ai_hidden_value ) :
            ?>
        <input type="hidden" name="<?php echo esc_attr( $easy_mcp_ai_hidden_name ); ?>" value="<?php echo esc_attr( $easy_mcp_ai_hidden_value ); ?>">
        <?php endforeach; ?>

    <div class="emcp-body">

        <?php if ( ! empty( $notice ) ) : ?>
        <div class="emcp-notice emcp-notice--warn"><?php echo esc_html( $notice ); ?></div>
        <?php endif; ?>

        <div class="emcp-card emcp-card--subtle">
            <div class="emcp-h2"><?php echo esc_html( $client_name ); ?></div>
            <p class="emcp-text emcp-text--small">
                <?php
                printf(
                    /* translators: 1: user display name, 2: comma-separated role list */
                    esc_html__( 'Will act on this site as %1$s (%2$s), and never beyond what that account can do.', 'easy-mcp-ai' ),
                    '<strong>' . esc_html( $user_display_name ) . '</strong>',
                    esc_html( $user_roles )
                );
                ?>
            </p>
            <p class="emcp-text emcp-text--small emcp-text--muted">
                <?php echo esc_html__( 'Client ID:', 'easy-mcp-ai' ); ?>
                <code class="emcp-mono"><?php echo esc_html( $client_id_prefix ); ?>&hellip;</code>
                &nbsp;&middot;&nbsp;
                <?php echo esc_html( $context_label ); ?>
                <code class="emcp-mono"><?php echo esc_html( $context_value ); ?></code>
            </p>
        </div>

        <?php
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables inside a view; not global scope.
        $levels = array(
            'read'   => array(
                __( 'Read-only', 'easy-mcp-ai' ),
                __( 'The AI can view content, settings and the user list. It cannot create, change or delete anything.', 'easy-mcp-ai' ),
            ),
            'full'   => array(
                __( 'Full access', 'easy-mcp-ai' ),
                __( 'All tools, current and future, including plugin integrations, external data connections and WordPress Abilities.', 'easy-mcp-ai' ),
            ),
            'custom' => array(
                __( 'Custom', 'easy-mcp-ai' ),
                __( 'Pick read and write access per category under Customize permissions.', 'easy-mcp-ai' ),
            ),
        );
        $levels = array( $default_level => $levels[ $default_level ] ) + $levels;
        ?>
        <fieldset class="emcp-choices">
            <legend class="emcp-eyebrow"><?php echo esc_html__( 'Access level', 'easy-mcp-ai' ); ?></legend>
            <div class="emcp-choices__list">
            <?php
            foreach ( $levels as $level => $copy ) :
                if ( 'custom' !== $level && empty( $level_scopes[ $level ] ) ) {
                    continue;
                }
                ?>
            <label class="emcp-choice">
                <input type="radio" name="access_level" value="<?php echo esc_attr( $level ); ?>" class="emcp-radio" <?php checked( $default_level, $level ); ?>>
                <span>
                    <span class="emcp-choice__name">
                        <?php echo esc_html( $copy[0] ); ?>
                    </span>
                    <span class="emcp-text emcp-text--small emcp-text--muted"><?php echo esc_html( $copy[1] ); ?></span>
                    <?php if ( 'full' === $level && ! empty( $privileged_rows ) ) : ?>
                    <span class="emcp-text emcp-text--small emcp-text--danger" data-privileged-notice>
                        <?php
                        /* translators: %s: comma-separated privileged actions, e.g. "Run PHP code" */
                        echo esc_html( sprintf( __( 'Includes privileged actions, which can run code, read files on the server or rewrite the database: %s.', 'easy-mcp-ai' ), implode( ', ', array_column( $privileged_rows, 'label' ) ) ) );
                        ?>
                    </span>
                    <?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
            </div>
        </fieldset>

        <?php if ( empty( $ceiling_all ) ) : ?>
        <p class="emcp-text emcp-text--small emcp-text--muted">
            <?php
            /* translators: %s: application name */
            echo esc_html( sprintf( __( '%s asked for access to part of this site only, so every option stays within what it asked for.', 'easy-mcp-ai' ), $client_name ) );
            ?>
        </p>
        <?php endif; ?>

        <details id="customize" class="emcp-details" <?php echo 'custom' === $default_level ? 'open' : ''; ?>>
            <summary><?php echo esc_html__( 'Customize permissions', 'easy-mcp-ai' ); ?></summary>

            <div class="emcp-stack">
            <p class="emcp-text emcp-text--small emcp-text--muted"><?php echo esc_html__( 'These boxes apply when Custom is selected.', 'easy-mcp-ai' ); ?></p>

            <table id="scope-table" class="emcp-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Category', 'easy-mcp-ai' ); ?></th>
                        <th class="emcp-table__toggle"><?php echo esc_html__( 'Read', 'easy-mcp-ai' ); ?></th>
                        <th class="emcp-table__toggle"><?php echo esc_html__( 'Write', 'easy-mcp-ai' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $has_ability_row = false;

                    foreach ( $categories as $category ) :
                        if ( ! empty( $category['plugin_required'] ) && ! \Easy_MCP_AI\OAuth\Scope_Map::is_plugin_category_active( $category['slug'] ) ) {
                            continue;
                        }
                        if ( ! empty( $category['is_ability'] ) ) {
                            $has_ability_row = true;
                        }
                        $label = isset( $category['label'] ) ? $category['label'] : '';
                        ?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <?php
                        foreach ( array( 'read', 'write' ) as $kind ) :
                            $box_scope = isset( $category[ $kind . '_scope' ] ) ? (string) $category[ $kind . '_scope' ] : '';
                            ?>
                        <td class="emcp-table__toggle">
                            <?php if ( '' !== $box_scope ) : ?>
                                <input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $box_scope ); ?>" class="scope-checkbox emcp-checkbox"
                                    <?php checked( $ceiling_all || in_array( $box_scope, $checked_scopes, true ) ); ?>
                                    <?php disabled( ! $ceiling_all && ! in_array( $box_scope, $ceiling, true ) ); ?>>
                            <?php else : ?>
                                <span class="emcp-none">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php
                    
                    
                    
                    foreach ( $privileged_rows as $privileged_row ) :
                        $box_scope = $privileged_row['scope'];
                        if ( ! $ceiling_all && ! in_array( $box_scope, $ceiling, true ) ) {
                            continue;
                        }
                        ?>
                    <tr data-privileged-row>
                        <td>
                            <?php echo esc_html( $privileged_row['label'] ); ?>
                            <span class="emcp-flag emcp-flag--danger"><?php echo esc_html__( 'Privileged', 'easy-mcp-ai' ); ?></span>
                        </td>
                        <td class="emcp-table__toggle" colspan="2">
                            <input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $box_scope ); ?>" class="scope-checkbox emcp-checkbox"
                                <?php checked( $ceiling_all || in_array( $box_scope, $checked_scopes, true ) ); ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
                </tbody>
            </table>

            <?php if ( ! empty( $has_ability_row ) ) : ?>
            <p class="emcp-text emcp-text--small emcp-text--muted">
                <?php echo esc_html__( 'Only abilities and plugins an administrator enabled under Easy MCP AI → Tools can be called.', 'easy-mcp-ai' ); ?>
            </p>
            <?php endif; ?>
            </div>
        </details>

        <p class="emcp-text emcp-text--small emcp-text--muted">
            <?php echo esc_html__( 'Approving lets this app act on your site within the access above. Check your AI client’s auto-run settings for each tool. Post and page edits can be undone via WordPress revisions.', 'easy-mcp-ai' ); ?>
        </p>

    </div>

        <div class="emcp-footer">
            <button type="submit" name="consent_action" value="deny" class="emcp-btn emcp-btn--secondary emcp-btn--lg">
                <?php echo esc_html__( 'Deny', 'easy-mcp-ai' ); ?>
            </button>
            <button type="submit" name="consent_action" value="approve" class="emcp-btn emcp-btn--accent emcp-btn--lg">
                <?php echo esc_html__( 'Approve &amp; Continue', 'easy-mcp-ai' ); ?>
            </button>
        </div>
    </form>
</div>

<p class="emcp-pagenote"><span class="emcp-dot"></span><?php echo esc_html__( 'Powered by Easy MCP AI', 'easy-mcp-ai' ); ?></p>

<script nonce="<?php echo esc_attr( $script_nonce ); ?>">
(function() {
    'use strict';
    // Convenience only: the radios and boxes submit themselves without it.
    var levels = <?php echo wp_json_encode( $level_scopes ); ?>;
    var form = document.querySelector('form');
    var details = document.getElementById('customize');
    var custom = form.querySelector('input[name="access_level"][value="custom"]');
    var boxes = form.querySelectorAll('.scope-checkbox');

    form.addEventListener('change', function(e) {
        var t = e.target;
        if (t.name === 'access_level') {
            if (t.value === 'custom') { details.open = true; return; }
            for (var i = 0; i < boxes.length; i++) {
                if (!boxes[i].disabled) boxes[i].checked = t.value === 'full' || levels[t.value].indexOf(boxes[i].value) !== -1;
            }
        } else if (t.classList.contains('scope-checkbox') && custom) {
            custom.checked = true;
        }
    });
})();
</script>

</body>
</html>
