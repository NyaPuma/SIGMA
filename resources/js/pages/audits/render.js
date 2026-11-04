import { renderResultsCount as renderSharedResultsCount, renderResultsFeedback, renderSimplePagination, renderTableEmptyState, renderTableErrorState } from '../../components/listing/feedback.js';
import { getAuditsTableBody, getEventSelect, getPagination, getResultsCount } from './dom.js';
import { formatDateTime } from '../../utils/locale.js';
import { escapeHtml } from '../../utils/escape.js';

function getEventBadge(event) {
    const value = String(event || '').toLowerCase().trim();
    const translations = getAuditTranslations();

    if (value.includes('create') || value.includes('criar') || value.includes('insert')) {
        return `<span class="inline-flex items-center gap-1 rounded-lg border border-success/20 bg-success/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-success">${translations.create || 'Created'}</span>`;
    }

    if (value.includes('update') || value.includes('editar') || value.includes('atualizar')) {
        return `<span class="inline-flex items-center gap-1 rounded-lg border border-warning/20 bg-warning/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-warning">${translations.update || 'Updated'}</span>`;
    }

    if (value.includes('delete') || value.includes('eliminar') || value.includes('remover')) {
        return `<span class="inline-flex items-center gap-1 rounded-lg border border-danger/20 bg-danger/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-danger">${translations.delete || 'Deleted'}</span>`;
    }

    const eventLabels = {
        created: translations.created,
        updated: translations.updated,
        deleted: translations.deleted,
    };
    return `<span class="inline-flex items-center gap-1 rounded-lg border border-(--border) bg-(--surface-2) px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-(--text-soft)">${eventLabels[value] || escapeHtml(event)}</span>`;
}

function getAuditTranslations() {
    const configured = window.SGM_AUDIT_I18N || {};
    const body = document.body?.dataset || {};

    return {
        allEvents: configured.allEvents || body.auditAllEvents || 'All events',
        create: configured.create || body.auditCreated || 'Created',
        update: configured.update || body.auditUpdated || 'Updated',
        delete: configured.delete || body.auditDeleted || 'Deleted',
        created: configured.created || body.auditCreated || 'Created',
        updated: configured.updated || body.auditUpdated || 'Updated',
        deleted: configured.deleted || body.auditDeleted || 'Deleted',
    };
}

function formatStateData(state) {
    if (!state) return '<span class="font-mono text-(--text-soft)">-</span>';

    if (typeof state === 'object') {
        return `<pre class="max-h-40 max-w-xs overflow-auto rounded-xl border border-(--border) bg-(--surface-2) p-2 text-xs leading-relaxed text-(--text-soft)">${escapeHtml(JSON.stringify(state, null, 2))}</pre>`;
    }

    try {
        const parsed = JSON.parse(state);
        return `<pre class="max-h-40 max-w-xs overflow-auto rounded-xl border border-(--border) bg-(--surface-2) p-2 text-xs leading-relaxed text-(--text-soft)">${escapeHtml(JSON.stringify(parsed, null, 2))}</pre>`;
    } catch {
        const value = escapeHtml(state);
        return `<span class="line-clamp-2 break-all font-mono text-xs text-(--text-soft)" title="${value}">${value}</span>`;
    }
}

export function populateEventFilter(audits) {
    const select = getEventSelect();
    if (!select) return;

    const currentValue = select.value;
    const uniqueEvents = [...new Set(audits.map((item) => String(item.event || '').trim()))].filter(Boolean);

    const translations = getAuditTranslations();
    select.innerHTML = `<option value="">${translations.allEvents || 'All events'}</option>`;

    uniqueEvents.forEach((eventName) => {
        const option = document.createElement('option');
        option.value = eventName.toLowerCase();
        const eventLabels = {
            created: translations.created,
            updated: translations.updated,
            deleted: translations.deleted,
        };
        option.textContent = eventLabels[eventName.toLowerCase()] || eventName.charAt(0).toUpperCase() + eventName.slice(1);
        select.appendChild(option);
    });

    select.value = currentValue;
}

export function showFeedback(message, error = false) {
    renderResultsFeedback(getResultsCount(), message, error);
}

export function renderResultsCount(total) {
    renderSharedResultsCount(getResultsCount(), total);
}

export function renderEmptyState() {
    renderTableEmptyState({
        tbody: getAuditsTableBody(),
        colspan: 8,
        message: (window.SGM_AUDIT_I18N?.empty || 'No audit records found matching the applied filters.'),
    });
}

export function renderErrorState(message) {
    renderTableErrorState({
        tbody: getAuditsTableBody(),
        colspan: 8,
        message,
    });
}

export function renderAudits(audits) {
    const tbody = getAuditsTableBody();
    if (!tbody) return;

    tbody.innerHTML = audits.map((audit) => {
        const logId = audit.id ? `#${audit.id}` : '-';
        const user = escapeHtml(audit.user?.name || audit.user || audit.username || audit.operator || 'System / Automatic');
        const entity = escapeHtml(audit.auditable_type || audit.entity || 'General');
        const referenceValue = audit.auditable_id || audit.reference;
        const reference = referenceValue ? `ID: ${referenceValue}` : '-';
        const dateFormatted = audit.created_at
            ? formatDateTime(audit.created_at)
            : '-';

        return `<tr class="transition-colors duration-150 hover:bg-(--surface-2)/50">
            <td class="px-5 py-4 font-mono text-xs font-bold text-(--text-soft)" data-label="Log ID">${logId}</td>
            <td class="px-5 py-4 font-semibold text-(--text)" data-label="User / Operator">${user}</td>
            <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Affected Element">${entity}</td>
            <td class="px-5 py-4 font-mono text-xs text-(--text-soft)" data-label="Reference">${reference}</td>
            <td class="px-5 py-4" data-label="Action Type">${getEventBadge(audit.event)}</td>
            <td class="px-5 py-4" data-label="Previous State">${formatStateData(audit.old_values || audit.old_state)}</td>
            <td class="px-5 py-4" data-label="New State">${formatStateData(audit.new_values || audit.new_state)}</td>
            <td class="px-5 py-4 text-right font-mono text-xs font-semibold text-(--text-soft)" data-label="Date and Time">${dateFormatted}</td>
        </tr>`;
    }).join('');
}

export function renderPagination(meta, currentPage) {
    renderSimplePagination({
        element: getPagination(),
        meta,
        currentPage,
    });
}
