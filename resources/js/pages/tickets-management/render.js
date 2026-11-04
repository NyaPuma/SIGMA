import { getPagination, getResultsCount, getTicketsBody } from './dom.js';
import { getPaginationTranslations } from '../../components/listing/feedback.js';
import { escapeHtml } from '../../utils/escape.js';

const priorityColors = {
    baixa: 'border border-success/20 bg-success/10 text-success',
    média: 'border border-warning/20 bg-warning/10 text-warning',
    alta: 'border border-danger/20 bg-danger/10 text-danger',
    crítica: 'border border-purple-500/25 bg-purple-500/10 text-purple-800 dark:text-purple-400',
};

const translations = () => window.SGM_TICKETS_I18N || {};

export function showFeedback(message, error = false) {
    const element = getResultsCount();
    if (!element) return;

    element.textContent = message;
    element.className = `text-xs font-semibold ${error ? 'text-danger' : 'text-[var(--text-soft)]'}`;
}

export function renderResultsCount(total) {
    const text = translations();
    showFeedback(total > 0 ? `${total} ${text.resultsCount || ''}` : (text.noResults || ''));
}

function renderStatusBadge(statusName, statusLabel = null) {
    const statusKey = (statusName || '').toLowerCase();
    const label = escapeHtml(statusLabel);

    if (statusKey === 'aberta' || statusKey === 'aberto') {
        return `<span class="inline-flex items-center gap-1 rounded-lg bg-info/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-info">${label || translations().status?.aberta || ''}</span>`;
    }

    if (statusKey === 'em curso') {
        return `<span class="inline-flex items-center gap-1 rounded-lg bg-warning/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-warning">${label || translations().status?.['em curso'] || ''}</span>`;
    }

        return `<span class="inline-flex items-center gap-1 rounded-lg bg-[var(--text-soft)]/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-[var(--text-soft)]">${label || translations().status?.fechada || ''}</span>`;
}

function renderTicketRow(ticket) {
    const priorityKey = (ticket.priority || '').toLowerCase();
    const priorityClass = priorityColors[priorityKey] ?? 'border border-[var(--border)] bg-[var(--surface-2)] text-[var(--text-soft)]';
    const priorityLabel = escapeHtml(ticket.priority_label) || translations().priority?.[priorityKey] || '';
    const statusName = ticket.status?.name ?? ticket.status ?? 'N/A';
    const equipmentName = ticket.equipment ? escapeHtml(ticket.equipment.name) : '—';
    const roomName = ticket.room ? escapeHtml(ticket.room.name) : '—';
    const technicianName = ticket.technician
        ? escapeHtml(ticket.technician.name)
        : '<span class="font-normal italic text-[var(--text-soft)]">—</span>';

    return `<tr class="transition-colors duration-150 hover:bg-[var(--surface-2)]/50">
        <td class="px-5 py-4 font-mono font-bold text-[var(--text-soft)]" data-label="${translations().id || ''}">#${ticket.id}</td>
        <td class="max-w-xs truncate px-5 py-4 font-semibold text-[var(--text)]" data-label="${translations().title || ''}">${escapeHtml(ticket.title)}</td>
        <td class="px-5 py-4" data-label="${translations().priorityTitle || ''}">
            <span class="inline-block rounded-lg px-2.5 py-1 text-xs font-bold uppercase tracking-tight ${priorityClass}">${priorityLabel}</span>
        </td>
        <td class="px-5 py-4" data-label="${translations().statusTitle || ''}">${renderStatusBadge(statusName, ticket.status_label)}</td>
        <td class="px-5 py-4 font-semibold text-[var(--text-soft)]" data-label="${translations().equipment || ''}">${equipmentName}</td>
        <td class="px-5 py-4 font-semibold text-[var(--text-soft)]" data-label="${translations().room || ''}">${roomName}</td>
        <td class="px-5 py-4 text-xs font-semibold text-[var(--text)]" data-label="${translations().technician || ''}">${technicianName}</td>
        <td class="ui-listing-actions px-5 py-4 text-right">
            <a href="/ui/tickets/${ticket.id}" class="inline-flex min-h-[28px] min-w-[48px] items-center justify-center rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-semibold text-[var(--text)] shadow-sm transition-all hover:bg-[var(--surface-2)]">${translations().view || ''}</a>
        </td>
    </tr>`;
}

export function renderEmptyState() {
    const tbody = getTicketsBody();
    const pagination = getPagination();

    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-5 py-12 text-center text-xs text-[var(--text-soft)]"><div class="mx-auto max-w-sm rounded-2xl border border-dashed border-[var(--border)] bg-[var(--surface-2)] p-5">${translations().empty || ''}</div></td></tr>`;
    }

    if (pagination) pagination.innerHTML = '';
}

export function renderErrorState(message) {
    const tbody = getTicketsBody();
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="8" class="px-5 py-12 text-center text-xs font-medium text-danger">${escapeHtml(message)}</td></tr>`;
}

export function renderTickets(tickets) {
    const tbody = getTicketsBody();
    if (!tbody) return;

    tbody.innerHTML = tickets.map(renderTicketRow).join('');
}

export function renderPagination(meta, currentPage) {
    const pagination = getPagination();
    if (!pagination) return;

    const lastPage = meta.last_page ?? 1;
    const page = meta.current_page ?? currentPage;

    const translations = getPaginationTranslations();
    pagination.innerHTML = `
        <button data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}
            class="ui-button ui-button--primary inline-flex min-h-[36px] items-center justify-center rounded-xl px-3.5 py-2 text-xs font-bold text-[var(--on-primary)] shadow-sm transition-all hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">← ${translations.previous}</button>
        <span class="font-bold text-[var(--text-soft)]">${translations.page} ${page} ${translations.of} ${lastPage}</span>
        <button data-page="${page + 1}" ${page >= lastPage ? 'disabled' : ''}
            class="ui-button ui-button--primary inline-flex min-h-[36px] items-center justify-center rounded-xl px-3.5 py-2 text-xs font-bold text-[var(--on-primary)] shadow-sm transition-all hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">${translations.next} →</button>
    `;
}
