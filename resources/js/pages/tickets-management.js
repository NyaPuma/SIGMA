import { fetchTickets } from './tickets-management/api.js';
import { applyFiltersFromUrl, bindPagination, clearTicketFilters, renderLoadingState } from './tickets-management/dom.js';
import { renderEmptyState, renderErrorState, renderPagination, renderResultsCount, renderTickets, showFeedback } from './tickets-management/render.js';
import { setCurrentPage, ticketsState } from './tickets-management/state.js';

async function loadTickets(page = 1) {
    setCurrentPage(page);
    renderLoadingState();

    try {
        const data = await fetchTickets(page);
        if (!data) return;

        const tickets = data.tickets?.data ?? data.tickets ?? [];
        const meta = data.meta ?? data.tickets?.meta ?? {};
        const total = meta.total ?? tickets.length;

        renderResultsCount(total);

        if (!tickets.length) {
            renderEmptyState();
            return;
        }

        renderTickets(tickets);
        renderPagination(meta, page);
    } catch (error) {
        showFeedback(error.message, true);
        renderErrorState(error.message);
    }
}

function bindFilters() {
    document.getElementById('btnSearch')?.addEventListener('click', () => loadTickets(1));

    document.getElementById('btnClear')?.addEventListener('click', () => {
        clearTicketFilters();
        loadTickets(1);
    });

    document.getElementById('filter_q')?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') loadTickets(1);
    });

    ['filter_status', 'filter_priority', 'filter_date_from', 'filter_date_to'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => loadTickets(1));
    });
}

function init() {
    applyFiltersFromUrl();
    bindFilters();
    bindPagination(loadTickets);
    loadTickets(ticketsState.currentPage);
}

export { init };
