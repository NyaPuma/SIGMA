import { escapeHtml } from '../../utils/escape.js';

const filterIdsByParam = {
    q: 'filter_q',
    status: 'filter_status',
    priority: 'filter_priority',
    date_from: 'filter_date_from',
    date_to: 'filter_date_to',
};

/**
 * Deep links such as `/ui/tickets?priority=crítica` (the priority warning modal
 * sends the user there) carried the filter only in the URL, so the panel rendered
 * empty while the user believed it was applied. Assigning `.value` also feeds the
 * first request, because the fetch reads these same inputs.
 */
export function applyFiltersFromUrl() {
    const params = new URLSearchParams(window.location.search);

    Object.entries(filterIdsByParam).forEach(([param, id]) => {
        const value = params.get(param);
        const element = document.getElementById(id);

        if (element && value !== null && value !== '') {
            element.value = value;
        }
    });
}

export function getTicketFilters() {
    return {
        q: document.getElementById('filter_q')?.value.trim() || '',
        status: document.getElementById('filter_status')?.value || '',
        priority: document.getElementById('filter_priority')?.value || '',
        dateFrom: document.getElementById('filter_date_from')?.value || '',
        dateTo: document.getElementById('filter_date_to')?.value || '',
    };
}

export function clearTicketFilters() {
    ['filter_q', 'filter_status', 'filter_priority', 'filter_date_from', 'filter_date_to'].forEach((id) => {
        const element = document.getElementById(id);
        if (element) element.value = '';
    });
}

export function getTicketsBody() {
    return document.getElementById('ticketsBody');
}

export function getPagination() {
    return document.getElementById('pagination');
}

export function getResultsCount() {
    return document.getElementById('resultsCount');
}

export function renderLoadingState() {
    const tbody = getTicketsBody();
    if (!tbody) return;

    const label = window.SGM_TICKETS_I18N?.loading || 'A carregar...';

    tbody.innerHTML = `<tr><td colspan="8" class="px-5 py-12 text-center text-xs text-[var(--text-soft)]">${escapeHtml(label)}</td></tr>`;
}

export function bindPagination(handler) {
    getPagination()?.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-page]');
        if (!button || button.disabled) return;

        const page = parseInt(button.dataset.page || '', 10);
        if (!Number.isNaN(page)) handler(page);
    });
}
