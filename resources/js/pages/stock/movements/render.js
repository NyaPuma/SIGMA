import { renderResultsCount as renderSharedResultsCount, renderResultsFeedback, renderSimplePagination, renderTableEmptyState, renderTableErrorState } from '../../../components/listing/feedback.js';
import { getMovementTableBody, getPagination, getResultsCount } from './dom.js';
import { formatDateTime } from '../../../utils/locale.js';
import { escapeHtml } from '../../../utils/escape.js';


function renderMovementRow(movement) {
    const typeLabels = window.SGM_ENUM_I18N?.movement || {};
    const typeColors = {
        in: 'text-success',
        out: 'text-danger',
        adjust: 'text-warning',
        return: 'text-info',
    };
    const delta = Number(movement.delta) || Number(movement.quantity) || 0;
    const sign = delta > 0 ? '+' : '';
    const deltaColor = delta > 0 ? 'text-success' : 'text-danger';

    const date = movement.created_at
        ? formatDateTime(movement.created_at)
        : '—';

    return `<tr class="transition-colors duration-150 hover:bg-(--surface-2)/50">
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Date">${escapeHtml(date)}</td>
        <td class="px-5 py-4" data-label="Part">
            <div class="ui-listing-value">
                <div class="font-semibold text-(--text)">${escapeHtml(movement.part?.name ?? `Part #${movement.part_id}`)}</div>
                <div class="mt-0.5 font-mono text-xs uppercase tracking-wider text-(--text-soft)">${escapeHtml(movement.part?.sku ?? '')}</div>
            </div>
        </td>
        <td class="px-5 py-4" data-label="Type">
            <span class="inline-flex items-center gap-1 rounded-lg bg-(--surface-2) px-2.5 py-1 text-xs font-bold uppercase tracking-tight ${typeColors[movement.movement_type] ?? ''}">
                ${escapeHtml(typeLabels[movement.movement_type] ?? movement.movement_type)}
            </span>
        </td>
        <td class="px-5 py-4 font-black ${deltaColor}" data-label="Variation">
            ${sign}${delta}
            ${movement.stock_after != null ? `<span class="ml-1 text-xs font-semibold text-(--text-soft)">→ ${movement.stock_after}</span>` : ''}
        </td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Reason">${escapeHtml(movement.reason ?? '—')}</td>
    </tr>`;
}

export function showFeedback(message, error = false) {
    renderResultsFeedback(getResultsCount(), message, error);
}

export function renderResultsCount(total) {
    renderSharedResultsCount(getResultsCount(), total);
}

export function renderEmptyState() {
    renderTableEmptyState({
        tbody: getMovementTableBody(),
        colspan: 5,
        message: 'No movement found matching the applied filters.',
    });

    const pagination = getPagination();
    if (pagination) pagination.innerHTML = '';
}

export function renderErrorState(message) {
    renderTableErrorState({
        tbody: getMovementTableBody(),
        colspan: 5,
        message,
    });
}

export function renderMovements(movements) {
    const tbody = getMovementTableBody();
    if (!tbody) return;

    tbody.innerHTML = movements.map(renderMovementRow).join('');
}

export function renderPagination(pagination, currentPage) {
    renderSimplePagination({
        element: getPagination(),
        meta: pagination ?? {},
        currentPage,
    });
}
