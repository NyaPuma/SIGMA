import { renderResultsCount as renderSharedResultsCount, renderResultsFeedback, renderSimplePagination, renderTableEmptyState, renderTableErrorState } from '../../../components/listing/feedback.js';
import { getSupplierTableBody, getPagination, getResultsCount } from './dom.js';
import { isAdmin } from '../../../utils/api.js';
import { escapeHtml } from '../../../utils/escape.js';


function translations() {
    const data = document.body?.dataset || {};

    return {
        edit: window.SGM_STOCK_SUPPLIER_I18N?.edit || data.stockSupplierEdit || 'Edit',
        delete: window.SGM_STOCK_SUPPLIER_I18N?.delete || data.stockSupplierDelete || 'Delete',
    };
}

function renderSupplierRow(supplier) {
    const actions = isAdmin()
        ? `<a href="/ui/stock/suppliers/${supplier.id}/edit" class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-(--border) bg-(--surface) px-3 py-1.5 text-xs font-semibold text-(--text) shadow-sm transition-all hover:bg-(--surface-2)">${translations().edit}</a>
           <button type="button" data-supplier-delete="${supplier.id}" data-name="${escapeHtml(supplier.name)}" class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-danger/25 bg-danger/10 px-3 py-1.5 text-xs font-semibold text-danger shadow-sm transition-all hover:bg-danger/20">${translations().delete}</button>`
        : '';

    return `<tr class="transition-colors duration-150 hover:bg-(--surface-2)/50">
        <td class="px-5 py-4" data-label="Supplier">
            <div class="ui-listing-value">
                <div class="font-semibold text-(--text)">${escapeHtml(supplier.name)}</div>
                <div class="mt-0.5 text-xs uppercase tracking-wider text-(--text-soft)">${escapeHtml(supplier.address ?? '—')}</div>
            </div>
        </td>
        <td class="px-5 py-4 font-mono font-bold text-(--text-soft)" data-label="${escapeHtml(window.SGM_LOCALE?.tax_id || '')}">${escapeHtml(supplier.nif ?? '—')}</td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Contact">
            <div>${escapeHtml(supplier.email ?? '—')}</div>
            <div class="text-xs text-(--text-soft)">${escapeHtml(supplier.contact ?? '')}</div>
        </td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Avg. lead time">${supplier.avg_lead_time_days != null ? `${supplier.avg_lead_time_days} days` : '—'}</td>
        <td class="ui-listing-actions px-5 py-4 text-right">
            <div class="inline-flex items-center justify-end gap-1.5">${actions}</div>
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
        tbody: getSupplierTableBody(),
        colspan: 5,
        message: (window.SGM_STOCK_SUPPLIER_I18N?.empty || 'No supplier found matching the applied filters.'),
    });

    const pagination = getPagination();
    if (pagination) pagination.innerHTML = '';
}

export function renderErrorState(message) {
    renderTableErrorState({
        tbody: getSupplierTableBody(),
        colspan: 5,
        message,
    });
}

export function renderSuppliers(suppliers) {
    const tbody = getSupplierTableBody();
    if (!tbody) return;

    tbody.innerHTML = suppliers.map(renderSupplierRow).join('');
}

export function renderPagination(pagination, currentPage) {
    renderSimplePagination({
        element: getPagination(),
        meta: pagination ?? {},
        currentPage,
    });
}
