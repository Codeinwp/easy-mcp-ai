(function () {
    'use strict';

    var section = document.getElementById('easy-mcp-key-create');
    if (!section) {
        return;
    }

    var expiry = document.getElementById('easy-mcp-key-expiry');
    var custom = section.querySelector('.easy-mcp-key-custom-expiry');
    var date = document.getElementById('easy-mcp-key-custom-date');
    function syncExpiry() {
        var selected = expiry.value === 'custom';
        custom.hidden = !selected;
        date.disabled = !selected;
        date.required = selected;
    }
    expiry.addEventListener('change', syncExpiry);
    syncExpiry();

    var controls = section.querySelector('.easy-mcp-key-selection');
    var tools = section.querySelectorAll('input[name="key_tools[]"]');
    if (!controls || !tools.length) {
        return;
    }

    controls.hidden = false;
    controls.addEventListener('click', function (event) {
        var button = event.target.closest('[data-select-tools]');
        if (!button || !controls.contains(button)) {
            return;
        }
        tools.forEach(function (tool) {
            if (!tool.disabled) {
                tool.checked = button.dataset.selectTools === 'all';
                tool.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
}());
