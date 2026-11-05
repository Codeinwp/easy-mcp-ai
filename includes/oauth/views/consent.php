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
            'full'   => array(
                __( 'Full access', 'easy-mcp-ai' ),
                __( 'All tools, current and future, including plugin integrations, external data connections and WordPress Abilities.', 'easy-mcp-ai' ),
            ),
            'role'   => array(
                __( 'Match a role', 'easy-mcp-ai' ),
                __( 'All tools a user with this role is normally allowed to use, and nothing that role cannot do.', 'easy-mcp-ai' ),
            ),
            'read'   => array(
                __( 'Read-only', 'easy-mcp-ai' ),
                __( 'The AI can view content, settings and the user list. It cannot create, change or delete anything.', 'easy-mcp-ai' ),
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
                if ( 'role' === $level ) :
                    ?>
            <div class="emcp-choice emcp-choice--role" data-role-level>
                <input type="radio" id="emcp-level-role" name="access_level" value="role" class="emcp-radio" <?php checked( $default_level, 'role' ); ?>>
                <span>
                    <label for="emcp-level-role" class="emcp-choice__label">
                        <span class="emcp-choice__name"><?php echo esc_html( $copy[0] ); ?></span>
                        <span class="emcp-text emcp-text--small emcp-text--muted"><?php echo esc_html( $copy[1] ); ?></span>
                    </label>
                    <?php if ( count( $role_choices ) <= 3 ) : ?>
                    <span class="emcp-segmented" role="radiogroup" aria-label="<?php echo esc_attr__( 'Role to match', 'easy-mcp-ai' ); ?>">
                        <?php foreach ( $role_choices as $role_choice ) : ?>
                        <label class="emcp-segmented__item">
                            <input type="radio" name="role_limit" value="<?php echo esc_attr( $role_choice['value'] ); ?>" <?php checked( $role_selected, $role_choice['value'] ); ?>>
                            <span><?php echo esc_html( $role_choice['label'] ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </span>
                    <?php else : ?>
                    <select name="role_limit" class="emcp-input emcp-select" aria-label="<?php echo esc_attr__( 'Role to match', 'easy-mcp-ai' ); ?>">
                        <?php foreach ( $role_choices as $role_choice ) : ?>
                        <option value="<?php echo esc_attr( $role_choice['value'] ); ?>" <?php selected( $role_selected, $role_choice['value'] ); ?>><?php echo esc_html( $role_choice['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </span>
            </div>
                    <?php
                    continue;
                endif;
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
                        echo esc_html( sprintf( __( 'Also includes these privileged actions: %s.', 'easy-mcp-ai' ), implode( ', ', array_column( $privileged_rows, 'label' ) ) ) );
                        ?>
                    </span>
                    <?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
            </div>
        </fieldset>

        <?php
        $off_count    = count( $switched_off );
        $higher_count = count( $higher_role );
        if ( $off_count && $higher_count ) {
            /* translators: 1: "N tools are switched off on this site", 2: "N need a higher role". */
            $off_line = sprintf( __( '%1$s and %2$s. They stay off whatever you pick.', 'easy-mcp-ai' ),
                /* translators: %d: number of tools. */
                sprintf( _n( '%d tool is switched off on this site', '%d tools are switched off on this site', $off_count, 'easy-mcp-ai' ), $off_count ),
                /* translators: %d: number of tools. */
                sprintf( _n( '%d needs a higher role', '%d need a higher role', $higher_count, 'easy-mcp-ai' ), $higher_count )
            );
        } elseif ( $off_count ) {
            /* translators: %d: number of tools. */
            $off_line = sprintf( _n( '%d tool is switched off on this site. It stays off whatever you pick.', '%d tools are switched off on this site. They stay off whatever you pick.', $off_count, 'easy-mcp-ai' ), $off_count );
        } else {
            /* translators: %d: number of tools. */
            $off_line = sprintf( _n( '%d tool needs a higher role. It stays off whatever you pick.', '%d tools need a higher role. They stay off whatever you pick.', $higher_count, 'easy-mcp-ai' ), $higher_count );
        }
        ?>
        <?php if ( $off_count || $higher_count ) : ?>
        <details class="emcp-switched-off" data-switched-off>
            <summary class="emcp-text emcp-text--small emcp-text--muted">
                <?php echo esc_html( $off_line ); ?>
                <span class="emcp-switched-off__toggle" data-when="closed"><?php echo esc_html__( 'Show', 'easy-mcp-ai' ); ?></span>
                <span class="emcp-switched-off__toggle" data-when="open"><?php echo esc_html__( 'Hide', 'easy-mcp-ai' ); ?></span>
            </summary>
            <?php if ( $off_count ) : ?>
            <div data-switched-off-site>
                <?php if ( $higher_count ) : ?>
                <p class="emcp-switched-off__heading emcp-text emcp-text--small"><?php echo esc_html__( 'Switched off on this site', 'easy-mcp-ai' ); ?></p>
                <?php endif; ?>
                <ul class="emcp-switched-off__list emcp-text emcp-text--small emcp-text--muted">
                    <?php foreach ( $switched_off as $off_tool ) : ?>
                    <li><?php echo esc_html( $off_tool ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="emcp-text emcp-text--small emcp-text--muted"><?php echo esc_html__( 'An administrator switches them on under Easy MCP AI → Tools.', 'easy-mcp-ai' ); ?></p>
            </div>
            <?php endif; ?>
            <?php if ( $higher_count ) : ?>
            <div data-switched-off-role>
                <?php if ( $off_count ) : ?>
                <p class="emcp-switched-off__heading emcp-text emcp-text--small"><?php echo esc_html__( 'Need a higher role', 'easy-mcp-ai' ); ?></p>
                <?php endif; ?>
                <ul class="emcp-switched-off__list emcp-text emcp-text--small emcp-text--muted">
                    <?php foreach ( $higher_role as $role_tool ) : ?>
                    <li><?php echo esc_html( $role_tool ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="emcp-text emcp-text--small emcp-text--muted"><?php echo esc_html__( 'WordPress refuses these to your account. The rows under Customize permissions name the roles that can use them.', 'easy-mcp-ai' ); ?></p>
            </div>
            <?php endif; ?>
        </details>
        <?php endif; ?>

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
            <p class="emcp-text emcp-text--small emcp-text--muted" data-custom-hint><?php echo esc_html__( 'These boxes apply when Custom is selected.', 'easy-mcp-ai' ); ?></p>
            <?php if ( array() !== $role_choices ) : ?>
            <p class="emcp-text emcp-text--small emcp-text--muted" data-role-hint hidden><?php echo esc_html__( 'Ticked: what the chosen role can use. Pick Custom to choose the boxes yourself.', 'easy-mcp-ai' ); ?></p>
            <?php endif; ?>

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
                    $has_ability_row  = false;
                    $unavailable_rows = array();

                    $included_mark = static function () {
                        ?>
                        <span class="emcp-included" title="<?php echo esc_attr__( 'Included with Write', 'easy-mcp-ai' ); ?>"><span aria-hidden="true">&#10003;</span><span class="emcp-sr"><?php echo esc_html__( 'Included with Write', 'easy-mcp-ai' ); ?></span></span>
                        <?php
                    };

                    $render_category_row = static function ( array $category, string $note ) use ( $tick_all, $checked_scopes, $ceiling_all, $ceiling, $new_scopes, $included_mark ) {
                        $label   = isset( $category['label'] ) ? $category['label'] : '';
                        $row_new = in_array( isset( $category['read_scope'] ) ? (string) $category['read_scope'] : '', $new_scopes, true )
                            || in_array( isset( $category['write_scope'] ) ? (string) $category['write_scope'] : '', $new_scopes, true );
                        ?>
                    <tr<?php echo $row_new ? ' class="emcp-table__row--new"' : ''; ?>>
                        <td>
                            <?php echo esc_html( $label ); ?>
                            <?php if ( '' !== $note ) : ?>
                            <span class="emcp-text emcp-text--small emcp-text--muted emcp-row-note" data-availability-note><?php echo esc_html( $note ); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php
                        $ability = ! empty( $category['is_ability'] );
                        $cells   = array(
                            'read'  => ! $ability && isset( $category['read_scope'] ) ? (string) $category['read_scope'] : '',
                            'write' => (string) ( $ability ? $category['read_scope'] : ( isset( $category['write_scope'] ) ? $category['write_scope'] : '' ) ),
                        );
                        foreach ( $cells as $kind => $box_scope ) :
                            ?>
                        <td class="emcp-table__toggle">
                            <?php if ( '' !== $box_scope ) : ?>
                                <input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $box_scope ); ?>" class="scope-checkbox emcp-checkbox" data-kind="<?php echo esc_attr( $kind ); ?>"
                                    <?php checked( $tick_all || in_array( $box_scope, $checked_scopes, true ) ); ?>
                                    <?php disabled( ! $ceiling_all && ! in_array( $box_scope, $ceiling, true ) ); ?>>
                                <?php if ( in_array( $box_scope, $new_scopes, true ) ) : ?>
                                <span class="emcp-flag emcp-flag--new" data-new-scope="<?php echo esc_attr( $box_scope ); ?>"><?php echo esc_html__( 'New', 'easy-mcp-ai' ); ?></span>
                                <?php endif; ?>
                            <?php elseif ( $ability ) : ?>
                                <?php $included_mark(); ?>
                            <?php else : ?>
                                <span class="emcp-none">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                        <?php
                    };

                    foreach ( $categories as $category ) :
                        if ( ! empty( $category['plugin_required'] ) && ! \Easy_MCP_AI\OAuth\Scope_Map::is_plugin_category_active( $category['slug'] ) ) {
                            continue;
                        }
                        if ( ! empty( $category['is_ability'] ) ) {
                            $has_ability_row = true;
                        }
                        $row_availability = isset( $availability[ $category['slug'] ] ) ? $availability[ $category['slug'] ] : null;
                        if ( null !== $row_availability && \Easy_MCP_AI\OAuth\Scope_Availability::NONE === $row_availability['state'] ) {
                            $unavailable_rows[] = $category;
                            continue;
                        }
                        $render_category_row(
                            $category,
                            null === $row_availability ? '' : sprintf(
                                /* translators: %s: comma-separated role names, e.g. "Administrator, Shop manager". */
                                __( 'Partly available to your account. The rest needs: %s.', 'easy-mcp-ai' ),
                                implode( ', ', $row_availability['roles'] )
                            )
                        );
                    endforeach;
                    ?>
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
                        <?php
                        $cells = empty( $privileged_row['read_only'] ) ? array( 'included', 'box' ) : array( 'box', 'none' );
                        foreach ( $cells as $cell ) :
                            ?>
                        <td class="emcp-table__toggle">
                            <?php if ( 'box' === $cell ) : ?>
                            <input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $box_scope ); ?>" class="scope-checkbox emcp-checkbox"
                                <?php checked( $tick_all || in_array( $box_scope, $checked_scopes, true ) ); ?>>
                                <?php if ( in_array( $box_scope, $new_scopes, true ) ) : ?>
                            <span class="emcp-flag emcp-flag--new" data-new-scope="<?php echo esc_attr( $box_scope ); ?>"><?php echo esc_html__( 'New', 'easy-mcp-ai' ); ?></span>
                                <?php endif; ?>
                            <?php elseif ( 'included' === $cell ) : ?>
                                <?php $included_mark(); ?>
                            <?php else : ?>
                            <span class="emcp-none">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php
                    foreach ( $carried_rows as $carried_row ) :
                        if ( 'mcp:abilities' === $carried_row['write'] ) {
                            $has_ability_row = true;
                        }
                        ?>
                    <tr data-carried-row>
                        <td>
                            <?php echo esc_html( $carried_row['label'] ); ?>
                            <?php if ( '' !== $carried_row['flag'] ) : ?>
                            <span class="emcp-flag"><?php echo esc_html( $carried_row['flag'] ); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php
                        foreach ( array( 'read', 'write' ) as $kind ) :
                            $box_scope = $carried_row[ $kind ];
                            ?>
                        <td class="emcp-table__toggle">
                            <?php if ( '' !== $box_scope ) : ?>
                                <input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $box_scope ); ?>" class="scope-checkbox emcp-checkbox" data-kind="<?php echo esc_attr( $kind ); ?>" data-carried-scope
                                    <?php checked( $tick_all || in_array( $box_scope, $checked_scopes, true ) ); ?>>
                            <?php elseif ( $carried_row['read_included'] ) : ?>
                                <?php $included_mark(); ?>
                            <?php else : ?>
                                <span class="emcp-none">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( array() !== $unavailable_rows ) : ?>
            <details class="emcp-details" data-unavailable>
                <summary>
                    <?php
                    /* translators: %d: number of permission categories. */
                    echo esc_html( sprintf( __( 'Not available to your account (%d)', 'easy-mcp-ai' ), count( $unavailable_rows ) ) );
                    ?>
                </summary>
                <p class="emcp-text emcp-text--small emcp-text--muted"><?php echo esc_html__( 'WordPress refuses these to your account, whatever is ticked. Each row names the roles that can use it.', 'easy-mcp-ai' ); ?></p>
                <table class="emcp-table emcp-table--columns">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Category', 'easy-mcp-ai' ); ?></th>
                            <th class="emcp-table__toggle"><?php echo esc_html__( 'Read', 'easy-mcp-ai' ); ?></th>
                            <th class="emcp-table__toggle"><?php echo esc_html__( 'Write', 'easy-mcp-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ( $unavailable_rows as $category ) {
                            $render_category_row(
                                $category,
                                sprintf(
                                    /* translators: %s: comma-separated role names, e.g. "Administrator, Shop manager". */
                                    __( 'Needs: %s.', 'easy-mcp-ai' ),
                                    implode( ', ', $availability[ $category['slug'] ]['roles'] )
                                )
                            );
                        }
                        ?>
                    </tbody>
                </table>
            </details>
            <?php endif; ?>
            <?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>

            <?php if ( ! empty( $carried_rows ) ) : ?>
            <p class="emcp-text emcp-text--small emcp-text--muted" data-carried-note>
                <?php echo esc_html__( 'The last rows are access you already gave this app that the categories above do not cover. Untick one to remove it.', 'easy-mcp-ai' ); ?>
            </p>
            <?php endif; ?>

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
    var roleRefused = <?php echo wp_json_encode( (object) $role_refused ); ?>;
    var form = document.querySelector('form');
    var details = document.getElementById('customize');
    var custom = form.querySelector('input[name="access_level"][value="custom"]');
    var roleLevel = form.querySelector('input[name="access_level"][value="role"]');
    var boxes = form.querySelectorAll('.scope-checkbox');
    var columns = form.querySelectorAll('[data-column]');
    var bulkRow = form.querySelector('[data-bulk-row]');
    var customHint = form.querySelector('[data-custom-hint]');
    var roleHint = form.querySelector('[data-role-hint]');
    // The boxes the request cannot grant, which no level ticks.
    var outside = [];
    for (var o = 0; o < boxes.length; o++) outside.push(boxes[o].disabled);

    // The boxes this request lets the user change: one column ('read' or
    // 'write'), the category boxes of both ('category'), or those plus the
    // privileged rows ('every'). Select all and the column boxes never tick
    // a privileged row (GitHub #94); Clear unticks it. An ability row's box
    // is a Write box: its scope writes too, so the Read column never ticks
    // it (GitHub #710).
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

    function selectedRole() {
        var picked = form.querySelector('select[name=role_limit], input[name=role_limit]:checked');
        return picked ? picked.value : '';
    }

    // GitHub #196: under Match a role the boxes show what the chosen role can
    // use and the role decides, so they cannot be changed there and the bulk
    // controls, which could change nothing, are hidden; the server reads no
    // box at that level.
    function lockBoxes(on) {
        for (var i = 0; i < boxes.length; i++) if (!outside[i]) boxes[i].disabled = on;
        if (bulkRow) bulkRow.hidden = on;
        for (var c = 0; c < columns.length; c++) columns[c].hidden = on;
        if (roleHint) { roleHint.hidden = !on; customHint.hidden = on; }
    }

    // A level ticks the boxes it grants: every box for 'mcp'. Match a role
    // leaves unticked what the chosen role could not use.
    function applyLevel(level) {
        lockBoxes(level === 'role');
        if (level === 'custom') {
            details.open = true;
        } else {
            var grants = levels[level] || [];
            var all = grants.indexOf('mcp') !== -1;
            var role = level === 'role' ? selectedRole() : '';
            var refused = Object.prototype.hasOwnProperty.call(roleRefused, role) ? roleRefused[role] : [];
            for (var i = 0; i < boxes.length; i++) {
                if (!outside[i]) boxes[i].checked = (all || grants.indexOf(boxes[i].value) !== -1) && refused.indexOf(boxes[i].value) === -1;
            }
        }
        syncColumns();
    }

    form.addEventListener('change', function(e) {
        var t = e.target;
        if (t.name === 'access_level') {
            applyLevel(t.value);
        } else if (t.name === 'role_limit') {
            // Choosing a role chooses Match a role and ticks what it can use (GitHub #196).
            if (roleLevel) { roleLevel.checked = true; applyLevel('role'); }
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
    if (roleLevel && roleLevel.checked) applyLevel('role');
    syncColumns();
})();
</script>

</body>
</html>
