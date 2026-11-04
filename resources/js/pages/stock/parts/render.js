import { renderResultsCount as renderSharedResultsCount, renderResultsFeedback, renderSimplePagination, renderTableEmptyState, renderTableErrorState } from '../../../components/listing/feedback.js';
import { getPartTableBody, getPagination, getResultsCount } from './dom.js';
import { isAdmin } from '../../../utils/api.js';
import { formatCurrency } from '../../../utils/locale.js';
import { escapeHtml } from '../../../utils/escape.js';

function translations() {
    const data = document.body?.dataset || {};

    return {
        min: window.SGM_STOCK_PART_I18N?.min || data.stockPartMin || '',
        out: window.SGM_STOCK_PART_I18N?.out || data.stockPartOut || '',
        low: window.SGM_STOCK_PART_I18N?.low || data.stockPartLow || '',
        ok: window.SGM_STOCK_PART_I18N?.ok || data.stockPartOk || '',
        details: window.SGM_STOCK_PART_I18N?.details || data.stockPartDetails || '',
        edit: window.SGM_STOCK_PART_I18N?.edit || data.stockPartEdit || '',
        empty: window.SGM_STOCK_PART_I18N?.empty || data.stockPartEmpty || '',
    };
}


function formatPrice(value) {
    return formatCurrency(value);
}

function renderStockBadge(part) {
    const stock = Number(part.current_stock) || 0;
    const min = Number(part.min_stock) || 0;

    if (stock <= 0) {
        return `<span class="inline-flex items-center gap-1 rounded-lg bg-danger/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-danger">${translations().out}</span>`;
    }

    if (stock <= min) {
        return `<span class="inline-flex items-center gap-1 rounded-lg bg-warning/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-warning">${translations().low}</span>`;
    }

    return `<span class="inline-flex items-center gap-1 rounded-lg bg-success/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-success">${translations().ok}</span>`;
}

function renderPartRow(part) {
    const stock = Number(part.current_stock) || 0;
    const min = Number(part.min_stock) || 0;
    const location = part.location ?? '—';
    const actions = isAdmin()
        ? `<a href="/ui/stock/parts/${part.id}/edit" class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-(--border) bg-(--surface) px-3 py-1.5 text-xs font-semibold text-(--text) shadow-sm transition-all hover:bg-(--surface-2)">${translations().edit}</a>`
        : '';

    return `<tr class="transition-colors duration-150 hover:bg-(--surface-2)/50">
        <td class="px-5 py-4 font-mono font-bold text-(--text-soft)" data-label="SKU">${escapeHtml(part.sku)}</td>
        <td class="px-5 py-4" data-label="Part">
            <div class="ui-listing-value">
                <div class="font-semibold text-(--text)">${escapeHtml(part.name)}</div>
                <div class="mt-0.5 text-xs uppercase tracking-wider text-(--text-soft)">${escapeHtml(part.brand ?? (part.category?.name ?? '—'))}</div>
            </div>
        </td>
        <td class="px-5 py-4 font-semibold text-(--text)" data-label="Stock">
            <span class="font-black">${stock}</span>
            <span class="text-xs text-(--text-soft)"> / ${translations().min} ${min}</span>
        </td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Price">${formatPrice(part.cost_price)}</td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Location">${escapeHtml(location)}</td>
        <td class="px-5 py-4" data-label="Status">${renderStockBadge(part)}</td>
        <td class="ui-listing-actions px-5 py-4 text-right">
            <div class="inline-flex items-center justify-end gap-1.5">
                ${actions}
                <a href="/ui/stock/parts/${part.id}" class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-(--border) bg-(--surface) px-3 py-1.5 text-xs font-semibold text-(--text) shadow-sm transition-all hover:bg-(--surface-2)">${translations().details}</a>
            </div>
        </td>
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
        tbody: getPartTableBody(),
        colspan: 7,
        message: translations().empty,
    });

    const pagination = getPagination();
    if (pagination) pagination.innerHTML = '';
}

export function renderErrorState(message) {
    renderTableErrorState({
        tbody: getPartTableBody(),
        colspan: 7,
        message,
    });
}

export function renderParts(parts) {
    const tbody = getPartTableBody();
    if (!tbody) return;

    tbody.innerHTML = parts.map(renderPartRow).join('');
}

export function renderPagination(pagination, currentPage) {
    renderSimplePagination({
        element: getPagination(),
        meta: pagination ?? {},
        currentPage,
    });
}
