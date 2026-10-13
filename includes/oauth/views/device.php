<?php



















if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-console-styles.php';

$easy_mcp_ai_stage = isset( $stage ) ? (string) $stage : 'enter';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html__( 'Connect a Device', 'easy-mcp-ai' ); ?> &mdash; <?php echo esc_html( \Easy_MCP_AI\Console_Styles::site_label() ); ?></title>
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plugin-owned stylesheet; see Console_Styles::inline().
    echo \Easy_MCP_AI\Console_Styles::inline();
    ?>
</head>
<body class="emcp-page">

<div class="emcp-shell emcp-shell--narrow">

    <div class="emcp-topbar">
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin-owned markup; see Console_Styles::logo().
        echo \Easy_MCP_AI\Console_Styles::logo();
        ?>
        <h1 class="emcp-topbar__title"><?php echo esc_html__( 'Connect a Device', 'easy-mcp-ai' ); ?></h1>
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside Console_Styles::site_name().
        echo \Easy_MCP_AI\Console_Styles::site_name();
        ?>
    </div>

    <div class="emcp-body">
    <?php if ( 'done' === $easy_mcp_ai_stage ) : ?>

        <div class="emcp-notice <?php echo ! empty( $approved ) ? 'emcp-notice--ok' : 'emcp-notice--error'; ?>">
            <p class="emcp-notice__title"><?php echo esc_html( isset( $title ) ? $title : '' ); ?></p>
            <p><?php echo esc_html( isset( $message ) ? $message : '' ); ?></p>
        </div>

    <?php else : ?>

        <?php if ( ! empty( $error ) ) : ?>
        <div class="emcp-notice emcp-notice--error"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <p class="emcp-text">
            <?php echo esc_html__( 'An application running in a terminal or on another machine is waiting to connect to this site. Enter the code it displayed.', 'easy-mcp-ai' ); ?>
        </p>

        <div class="emcp-notice emcp-notice--warn">
            <?php echo esc_html__( 'Only enter a code you started yourself, on a device you control. A code someone else sent you would connect their application to your account.', 'easy-mcp-ai' ); ?>
        </div>

        <form method="post" action="<?php echo esc_url( $form_action ); ?>" class="emcp-field">
            <?php wp_nonce_field( 'easy_mcp_ai_oauth_device_lookup' ); ?>
            <input type="hidden" name="device_action" value="lookup">

            <label class="emcp-label" for="easy-mcp-ai-user-code"><?php echo esc_html__( 'Code', 'easy-mcp-ai' ); ?></label>
            <input id="easy-mcp-ai-user-code" class="emcp-input emcp-input--code" type="text" name="user_code" value="" autocomplete="off" autocapitalize="characters" spellcheck="false" autofocus required maxlength="<?php echo esc_attr( (int) $code_length + 3 ); ?>" placeholder="XXXX-XXXX">

            <div class="emcp-btn-row emcp-btn-row--end">
                <button type="submit" class="emcp-btn emcp-btn--accent emcp-btn--lg"><?php echo esc_html__( 'Continue', 'easy-mcp-ai' ); ?></button>
            </div>
        </form>

        <?php
        



















        ?>
        <script nonce="<?php echo esc_attr( isset( $script_nonce ) ? $script_nonce : '' ); ?>">
(function () {
    'use strict';
    var field = document.getElementById('easy-mcp-ai-user-code');
    if (!field) { return; }
    var LETTERS = <?php echo (int) $code_length; ?>;
    var HALF = LETTERS / 2;
    function letters(value) { return value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, LETTERS); }
    function format(value) {
        var s = letters(value);
        return s.length > HALF ? s.slice(0, HALF) + '-' + s.slice(HALF) : s;
    }
    function caretAfter(count, formatted) {
        var pos = 0, seen = 0;
        while (pos < formatted.length && seen < count) {
            if (formatted.charAt(pos) !== '-') { seen++; }
            pos++;
        }
        if (pos < formatted.length && formatted.charAt(pos) === '-') { pos++; }
        return pos;
    }
    function reformat() {
        var before = field.value;
        var caret = field.selectionStart;
        var lettersBefore = letters(before.slice(0, caret)).length;
        var next = format(before);
        if (next !== before) { field.value = next; }
        var at = caretAfter(lettersBefore, next);
        try { field.setSelectionRange(at, at); } catch (e) {}
    }
    field.addEventListener('keydown', function (e) {
        if (e.key !== 'Backspace') { return; }
        var start = field.selectionStart, end = field.selectionEnd;
        if (start !== end || start < 1 || field.value.charAt(start - 1) !== '-') { return; }
        e.preventDefault();
        var v = field.value;
        field.value = v.slice(0, start - 2) + v.slice(start);
        var at = start - 2;
        try { field.setSelectionRange(at, at); } catch (err) {}
        reformat();
    });
    field.addEventListener('input', reformat);
    if (field.value) { reformat(); }
})();
        </script>

    <?php endif; ?>
    </div>
</div>

<p class="emcp-pagenote"><span class="emcp-dot"></span><?php echo esc_html__( 'Powered by Easy MCP AI', 'easy-mcp-ai' ); ?></p>

</body>
</html>
