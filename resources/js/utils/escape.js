const entities = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

/**
 * Escapes a value for safe interpolation into innerHTML templates.
 */
export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => entities[char]);
}
