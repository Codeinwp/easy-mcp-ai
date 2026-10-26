<?php







































if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        <?php
        




        wp_nonce_field( $nonce_action, '_wpnonce', false );
        ?>

        <?php
        





        foreach ( $hidden_fields as $easy_mcp_ai_hidden_name => $easy_mcp_ai_hidden_value ) :
            ?>
        <input type="hidden" name="<?php echo esc_attr( $easy_mcp_ai_hidden_name ); ?>" value="<?php echo esc_attr( $easy_mcp_ai_hidden_value ); ?>">
        <?php endforeach; ?>

        <?php
        
        
        foreach ( $carried_scopes as $easy_mcp_ai_carried_scope ) :
            ?>
        <input type="hidden" name="scopes[]" value="<?php echo esc_attr( $easy_mcp_ai_carried_scope ); ?>" data-carried-scope>
        <?php endforeach; ?>

    <div class="emcp-body">

        <?php if ( ! empty( $notice ) ) : ?>
        <div class="emcp-notice emcp-notice--warn"><?php echo esc_html( $notice ); ?></div>
        <?php endif; ?>

        <?php if ( ! empty( $step_up_notice ) ) : ?>
        <div class="emcp-notice emcp-notice--info" data-step-up-notice><?php echo esc_html( $step_up_notice ); ?></div>
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
                <?php if ( '' !== $client_label ) : ?>
                <code class="emcp-mono"><?php echo esc_html( $client_label ); ?></code>
                <?php else : ?>
                <code class="emcp-mono"><?php echo esc_html( $client_id_prefix ); ?>&hellip;</code>
                <?php endif; ?>
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

            <?php
            




            ?>
            <div class="emcp-btn-row" data-bulk-row hidden>
                <button type="button" class="emcp-btn emcp-btn--secondary emcp-btn--sm" data-bulk="all"><?php echo esc_html__( 'Select all', 'easy-mcp-ai' ); ?></button>
                <button type="button" class="emcp-btn emcp-btn--secondary emcp-btn--sm" data-bulk="none"><?php echo esc_html__( 'Clear', 'easy-mcp-ai' ); ?></button>
            </div>

            <table id="scope-table" class="emcp-table emcp-table--columns">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Category', 'easy-mcp-ai' ); ?></th>
                        <th class="emcp-table__toggle">
                            <label class="emcp-table__column">
                                <?php echo esc_html__( 'Read', 'easy-mcp-ai' ); ?>
                                <input type="checkbox" class="emcp-checkbox" data-column="read" aria-label="<?php echo esc_attr__( 'Tick every Read box', 'easy-mcp-ai' ); ?>" hidden>
                            </label>
                        </th>
                        <th class="emcp-table__toggle">
                            <label class="emcp-table__column">
                                <?php echo esc_html__( 'Write', 'easy-mcp-ai' ); ?>
                                <input type="checkbox" class="emcp-checkbox" data-column="write" aria-label="<?php echo esc_attr__( 'Tick every Write box', 'easy-mcp-ai' ); ?>" hidden>
                            </label>
                        </th>
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
                        $label   = isset( $category['label'] ) ? $category['label'] : '';
                        $row_new = in_array( isset( $category['read_scope'] ) ? (string) $category['read_scope'] : '', $new_scopes, true )
                            || in_array( isset( $category['write_scope'] ) ? (string) $category['write_scope'] : '', $new_scopes, true );
                        ?>
                    <tr<?php echo $row_new ? ' class="emcp-table__row--new"' : ''; ?>>
                        <td><?php echo esc_html( $label ); ?></td>
                        <?php
                        foreach ( array( 'read', 'write' ) as $kind ) :
                            $box_scope = isset( $category[ $kind . '_scope' ] ) ? (string) $category[ $kind . '_scope' ] : '';
                            ?>
                        <td class="emcp-table__toggle">
                            <?php if ( '' !== $box_scope ) : ?>
                                <input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $box_scope ); ?>" class="scope-checkbox emcp-checkbox" data-kind="<?php echo esc_attr( $kind ); ?>"
                                    <?php checked( $tick_all || in_array( $box_scope, $checked_scopes, true ) ); ?>
                                    <?php disabled( ! $ceiling_all && ! in_array( $box_scope, $ceiling, true ) ); ?>>
                                <?php if ( in_array( $box_scope, $new_scopes, true ) ) : ?>
                                <span class="emcp-flag emcp-flag--new" data-new-scope="<?php echo esc_attr( $box_scope ); ?>"><?php echo esc_html__( 'New', 'easy-mcp-ai' ); ?></span>
                                <?php endif; ?>
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
                    <tr data-privileged-row<?php echo in_array( $box_scope, $new_scopes, true ) ? ' class="emcp-table__row--new"' : ''; ?>>
                        <td>
                            <?php echo esc_html( $privileged_row['label'] ); ?>
                            <span class="emcp-flag emcp-flag--danger"><?php echo esc_html__( 'Privileged', 'easy-mcp-ai' ); ?></span>
                        </td>
                        <td class="emcp-table__toggle" colspan="2">
                            <input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $box_scope ); ?>" class="scope-checkbox emcp-checkbox"
                                <?php checked( $tick_all || in_array( $box_scope, $checked_scopes, true ) ); ?>>
                            <?php if ( in_array( $box_scope, $new_scopes, true ) ) : ?>
                            <span class="emcp-flag emcp-flag--new" data-new-scope="<?php echo esc_attr( $box_scope ); ?>"><?php echo esc_html__( 'New', 'easy-mcp-ai' ); ?></span>
                            <?php endif; ?>
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
    var columns = form.querySelectorAll('[data-column]');
    var bulkRow = form.querySelector('[data-bulk-row]');

    // The boxes this request lets the user change: one column ('read' or
    // 'write'), the category boxes of both ('category'), or those plus the
    // privileged rows ('every'). Select all and the column boxes never tick
    // a privileged row (GitHub #94); Clear unticks it.
    function usable(which) {
        var out = [];
        for (var i = 0; i < boxes.length; i++) {
            var kind = boxes[i].getAttribute('data-kind'); // null on a privileged row
            if (boxes[i].disabled) continue;
            if (which === 'every' || (which === 'category' ? kind !== null : kind === which)) out.push(boxes[i]);
        }
        return out;
    }

    // A column box is ticked when every box under it is, mixed when some are.
    function syncColumns() {
        for (var i = 0; i < columns.length; i++) {
            var list = usable(columns[i].getAttribute('data-column'));
            var on = 0;
            for (var j = 0; j < list.length; j++) if (list[j].checked) on++;
            columns[i].disabled = list.length === 0;
            columns[i].checked = list.length > 0 && on === list.length;
            columns[i].indeterminate = on > 0 && on < list.length;
        }
    }

    // A bulk change is a Custom choice, like ticking a single box.
    function setBoxes(list, on) {
        for (var i = 0; i < list.length; i++) list[i].checked = on;
        if (custom) custom.checked = true;
        syncColumns();
    }

    form.addEventListener('change', function(e) {
        var t = e.target;
        if (t.name === 'access_level') {
            if (t.value === 'custom') { details.open = true; return; }
            for (var i = 0; i < boxes.length; i++) {
                if (!boxes[i].disabled) boxes[i].checked = t.value === 'full' || levels[t.value].indexOf(boxes[i].value) !== -1;
            }
            syncColumns();
        } else if (t.hasAttribute('data-column')) {
            setBoxes(usable(t.getAttribute('data-column')), t.checked);
        } else if (t.classList.contains('scope-checkbox')) {
            if (custom) custom.checked = true;
            syncColumns();
        }
    });

    form.addEventListener('click', function(e) {
        var action = e.target.getAttribute && e.target.getAttribute('data-bulk');
        if (action) setBoxes(usable(action === 'all' ? 'category' : 'every'), action === 'all');
    });

    if (bulkRow) bulkRow.hidden = false;
    for (var c = 0; c < columns.length; c++) columns[c].hidden = false;
    syncColumns();
})();
</script>

</body>
</html>
