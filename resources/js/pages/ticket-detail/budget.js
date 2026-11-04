import { nextBudgetItemIndex } from './state.js';
import { formatCurrency } from '../../utils/locale.js';
import { escapeHtml } from '../../utils/escape.js';
import { authHeader } from '../../utils/api.js';

let partsAbortController = null;

function budgetLabels() {
    const i18n = window.SGM_TICKET_DETAIL_I18N || {};

    return {
        labor: i18n.budgetLabor || 'Labor',
        material: i18n.budgetMaterial || 'Material',
        perHour: i18n.budgetPerHour || '€/Hour',
        unitPrice: i18n.budgetUnitPrice || 'Unit Price',
        hours: i18n.budgetHours || 'Hours',
        qty: i18n.budgetQty || 'Qty',
        removeItem: i18n.budgetRemoveItem || 'Remove item',
        jobDescription: i18n.budgetJobDescription || 'Job description',
        item: i18n.budgetItem || 'Item',
        addItems: i18n.budgetAddItems || 'Add items',
        materialsBadge: window.SGM_UI_I18N?.materialsBadge || 'Materials (Stock)',
        description: i18n.budgetDescription || 'Description',
        searchMaterial: window.SGM_UI_I18N?.searchMaterial || 'Search material in stock...',
    };
}

async function fetchParts(query = '', pageSize = 100) {
    if (partsAbortController) partsAbortController.abort();
    partsAbortController = new AbortController();

    const params = new URLSearchParams({ per_page: String(pageSize) });
    if (query.trim()) params.set('q', query.trim());

    const response = await fetch(`/stock/parts?${params.toString()}`, {
        headers: authHeader(),
        signal: partsAbortController.signal,
    });
    if (!response.ok) return [];
    const data = await response.json().catch(() => ({}));

    // The web route nests the collection under `parts`. Laravel serialises an
    // embedded `PartResource::collection()` as a flat array (no `data` wrapper),
    // but be defensive and unwrap either shape.
    const parts = Array.isArray(data.parts)
        ? data.parts
        : Array.isArray(data.parts?.data) ? data.parts.data : [];

    return parts.map((part) => part?.data ?? part);
}

function partUnitPrice(part) {
    const price = Number(part.sale_price);
    if (price > 0) return price;
    const vat = Number(part.price_with_vat);
    return vat > 0 ? vat : 0;
}

/* ---------------------------------------------------------------------------
   Budget item row
--------------------------------------------------------------------------- */
export function addBudgetItem(description = '', quantity = 1, price = 0, type = 'material') {
    const container = document.getElementById('budgetItemsContainer');
    if (!container) return;

    const index = nextBudgetItemIndex();
    const labels = budgetLabels();
    const pricePlaceholder = type === 'labor' ? labels.perHour : labels.unitPrice;
    const typeBadge = type === 'labor' ? labels.labor : labels.material;
    const qtyPlaceholder = type === 'labor' ? labels.hours : labels.qty;
    const item = document.createElement('div');

    item.className = 'budget-item';
    item.dataset.index = index;
    item.dataset.type = type;
    item.innerHTML = `
        <div class="flex items-center gap-2">
            <span class="item-type-badge rounded-md px-1.5 py-1 text-[10px] font-bold uppercase tracking-wider ${type === 'labor' ? 'bg-primary/15 text-primary' : 'bg-(--surface-2) text-(--text-soft)'}">${escapeHtml(typeBadge)}</span>
            <input type="text" class="item-desc flex-1 min-w-0 rounded-lg border border-(--border) bg-(--surface-2) px-2.5 py-1.5 text-xs text-(--text) outline-none transition-all focus:border-(--text)" placeholder="${escapeHtml(labels.description)}" value="${escapeHtml(description)}">
            <input type="number" class="item-qty w-16 rounded-lg border border-(--border) bg-(--surface-2) px-2 py-1.5 text-xs font-mono text-(--text) outline-none transition-all focus:border-(--text)" placeholder="${escapeHtml(qtyPlaceholder)}" min="1" value="${quantity}">
            <input type="number" step="0.01" class="item-price w-24 rounded-lg border border-(--border) bg-(--surface-2) px-2 py-1.5 text-xs font-mono text-(--text) outline-none transition-all focus:border-(--text)" placeholder="${escapeHtml(pricePlaceholder)}" min="0" value="${price}">
            <button type="button" data-action="remove-budget-item" class="btn-remove-item p-1 text-danger/80 transition-all hover:text-danger cursor-pointer" title="${escapeHtml(labels.removeItem)}">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    `;

    container.appendChild(item);
    recalcBudgetTotal();
}

/* ---------------------------------------------------------------------------
   "Adicionar Item" modal — pick parts already in stock (and optional labour).
   The budget list stays empty until the technician confirms a selection, which
   is exactly what the user requested.
--------------------------------------------------------------------------- */
const modal = {
    overlay: null,
    parts: [],
    selectedParts: new Map(),
    searchDebounce: null,
};

export async function openBudgetItemModal() {
    // Render the modal first so the click gives immediate feedback, then load
    // the parts from stock asynchronously.
    if (modal.overlay) closeBudgetItemModal();
    modal.selectedParts = new Map();
    modal.parts = [];
    renderBudgetItemModal();
    loadPartsIntoModal('');
}

function renderBudgetItemModal() {
    const labels = budgetLabels();
    const existing = document.getElementById('budgetItemModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'budgetItemModal';
    overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4';
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeBudgetItemModal();
    });

    overlay.innerHTML = `
        <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-(--border) bg-(--surface) shadow-2xl">
            <div class="flex items-center justify-between border-b border-(--border) px-4 py-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-(--text)">${escapeHtml(labels.addItems)}</h3>
                <button type="button" data-bm-close class="p-1 text-(--text-soft) transition-all hover:text-danger cursor-pointer">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto px-4 py-3 space-y-4">
                <div>
                    <div class="mb-2 flex items-center gap-2">
                        <span class="rounded-md bg-(--surface-2) px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-(--text-soft)">${escapeHtml(labels.materialsBadge)}</span>
                    </div>
                    <input type="text" data-bm-search class="w-full rounded-lg border border-(--border) bg-(--surface-2) px-3 py-2 text-xs text-(--text) outline-none transition-all focus:border-primary" placeholder="${escapeHtml(labels.searchMaterial)}">
                    <div data-bm-list class="mt-2 space-y-1 max-h-64 overflow-y-auto">
                        ${renderPartsList(modal.parts)}
                    </div>
                </div>

                <div class="border-t border-(--border) pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">Labor</span>
                        <button type="button" data-bm-add-labor class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-bold text-primary bg-primary/10 border border-primary/30 rounded-lg hover:bg-primary/20 transition cursor-pointer">+ Add</button>
                    </div>
                    <div data-bm-labor class="space-y-2"></div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-(--border) px-4 py-3">
                <span data-bm-count class="text-xs text-(--text-soft)">0 items selected</span>
                <div class="flex items-center gap-2">
                    <button type="button" data-bm-cancel class="px-3 py-2 text-xs font-bold text-(--text-soft) border border-(--border) rounded-lg hover:bg-(--surface-2) transition cursor-pointer">Cancel</button>
                    <button type="button" data-bm-confirm class="px-4 py-2 text-xs font-bold text-[var(--on-primary)] bg-primary hover:bg-primary-hover rounded-lg shadow-sm transition cursor-pointer">Confirm</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    modal.overlay = overlay;

    overlay.querySelector('[data-bm-close]').addEventListener('click', closeBudgetItemModal);
    overlay.querySelector('[data-bm-cancel]').addEventListener('click', closeBudgetItemModal);
    overlay.querySelector('[data-bm-add-labor]').addEventListener('click', addLaborRow);
    overlay.querySelector('[data-bm-search]').addEventListener('input', onSearch);
    overlay.querySelector('[data-bm-confirm]').addEventListener('click', confirmSelection);

    // Toggle part selection (event delegation on the list)
    overlay.querySelector('[data-bm-list]').addEventListener('change', (e) => {
        const input = e.target.closest('[data-bm-part]');
        if (!input) return;
        const part = modal.parts.find((p) => String(p.id) === input.dataset.bmPart);
        if (!part) return;
        if (input.checked) modal.selectedParts.set(String(part.id), part);
        else modal.selectedParts.delete(String(part.id));
        updateCount();
    });

    updateCount();
}

function renderPartsList(parts) {
    if (!parts || parts.length === 0) {
        return '<div class="py-6 text-center text-xs text-(--text-soft)">No material in stock.</div>';
    }

    return parts.map((part) => {
        const price = formatCurrency(partUnitPrice(part));
        const stock = part.current_stock ?? 0;
        const unit = part.unit_of_measure || 'un';
        const id = String(part.id);
        const checked = modal.selectedParts.has(id) ? 'checked' : '';
        return `
            <label class="flex items-center gap-3 p-2.5 rounded-xl border border-(--border) bg-(--surface-2)/60 hover:bg-(--surface-2) transition cursor-pointer">
                <input type="checkbox" data-bm-part="${id}" ${checked} class="h-4 w-4 accent-primary shrink-0 cursor-pointer">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-semibold text-(--text)">${escapeHtml(part.name)}</span>
                    <span class="block truncate text-[11px] text-(--text-soft)">${escapeHtml(part.sku)}</span>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block font-mono text-xs font-bold text-(--text)">${price}</span>
                    <span class="block text-[11px] text-(--text-soft)">${stock} ${escapeHtml(unit)}</span>
                </span>
            </label>
        `;
    }).join('');
}

function updatePartRowState() {
    const list = modal.overlay?.querySelector('[data-bm-list]');
    if (!list) return;
    list.querySelectorAll('[data-bm-part]').forEach((cb) => {
        cb.checked = modal.selectedParts.has(cb.dataset.bmPart);
    });
}

async function loadPartsIntoModal(query, setLoading = true) {
    const list = modal.overlay?.querySelector('[data-bm-list]');
    if (!list) return;

    if (setLoading) {
        list.innerHTML = '<div class="py-6 text-center text-xs text-(--text-soft)">Loading materials...</div>';
    }

    try {
        modal.parts = await fetchParts(query);
    } catch (error) {
        // A newer search aborted this request — do nothing, the new one is loading.
        if (error?.name === 'AbortError') return;
        modal.parts = [];
        if (modal.overlay?.contains(list)) {
            list.innerHTML = '<div class="py-6 text-center text-xs text-danger">Error loading materials.</div>';
            return;
        }
    }

    if (modal.overlay?.contains(list)) {
        list.innerHTML = renderPartsList(modal.parts);
        updatePartRowState();
    }
}

const onSearch = () => {
    const search = modal.overlay?.querySelector('[data-bm-search]');
    if (!search) return;
    clearTimeout(modal.searchDebounce);
    modal.searchDebounce = setTimeout(() => loadPartsIntoModal(search.value.trim()), 250);
};

function updateCount() {
    const el = modal.overlay?.querySelector('[data-bm-count]');
    if (!el) return;
    const texts = modal.overlay.querySelectorAll('[data-bm-labor-row]');
    const laborCount = Array.from(texts).filter((row) => row.dataset.valid === '1').length;
    const total = modal.selectedParts.size + laborCount;
    el.textContent = `${total} ${total === 1 ? 'item selected' : 'items selected'}`;
}

function addLaborRow() {
    const labels = budgetLabels();
    const container = modal.overlay?.querySelector('[data-bm-labor]');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'flex items-center gap-2';
    row.dataset.laborRow = '';

    row.innerHTML = `
        <input type="text" data-labor-desc class="flex-1 min-w-0 rounded-lg border border-(--border) bg-(--surface-2) px-2.5 py-1.5 text-xs text-(--text) outline-none focus:border-primary" placeholder="${escapeHtml(labels.jobDescription)}">
        <input type="number" data-labor-hours class="w-16 rounded-lg border border-(--border) bg-(--surface-2) px-2 py-1.5 text-xs font-mono text-(--text) outline-none focus:border-primary" placeholder="${escapeHtml(labels.hours)}" min="0.5" step="0.5" value="1">
        <input type="number" step="0.01" data-labor-rate class="w-24 rounded-lg border border-(--border) bg-(--surface-2) px-2 py-1.5 text-xs font-mono text-(--text) outline-none focus:border-primary" placeholder="${escapeHtml(labels.perHour)}" min="0" value="0">
        <button type="button" data-labor-remove class="p-1 text-danger/80 hover:text-danger transition cursor-pointer">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;

    container.appendChild(row);

    row.querySelector('[data-labor-remove]').addEventListener('click', () => {
        row.remove();
        updateCount();
    });
    row.querySelectorAll('input').forEach((input) => {
        input.addEventListener('input', () => {
            const desc = row.querySelector('[data-labor-desc]').value.trim();
            const hours = parseFloat(row.querySelector('[data-labor-hours]').value) || 0;
            const rate = parseFloat(row.querySelector('[data-labor-rate]').value) || 0;
            row.dataset.valid = desc && hours > 0 && rate > 0 ? '1' : '0';
            updateCount();
        });
    });

    updateCount();
}

function confirmSelection() {
    // Materials — from the selectedParts map (persists across searches)
    modal.selectedParts.forEach((part) => addBudgetItem(part.name, 1, partUnitPrice(part), 'material'));

    // Labour rows
    modal.overlay?.querySelectorAll('[data-labor-row]').forEach((row) => {
        const desc = row.querySelector('[data-labor-desc]')?.value.trim();
        const hours = parseFloat(row.querySelector('[data-labor-hours]')?.value) || 0;
        const rate = parseFloat(row.querySelector('[data-labor-rate]')?.value) || 0;
        if (desc && hours > 0 && rate > 0) {
            addBudgetItem(desc, hours, rate, 'labor');
        }
    });

    closeBudgetItemModal();
}

function closeBudgetItemModal() {
    modal.overlay?.remove();
    modal.overlay = null;
    modal.selectedParts = new Map();
    modal.parts = [];
}

/* ---------------------------------------------------------------------------
   Totals + serialisation
--------------------------------------------------------------------------- */
export function recalcBudgetTotal() {
    let total = 0;

    document.querySelectorAll('.budget-item').forEach((item) => {
        const quantity = parseFloat(item.querySelector('.item-qty')?.value) || 0;
        const price = parseFloat(item.querySelector('.item-price')?.value) || 0;
        const subtotal = quantity * price;
        total += subtotal;
        const subtotalEl = item.querySelector('.item-subtotal');
        if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
    });

    const totalDisplay = document.getElementById('techTotalEstimatedDisplay');
    const hiddenInput = document.getElementById('techEstimatedCostInput');
    if (totalDisplay) totalDisplay.textContent = formatCurrency(total);
    if (hiddenInput) hiddenInput.value = String(total);
}

export function getBudgetDetails() {
    const items = [];

    document.querySelectorAll('.budget-item').forEach((item) => {
        const type = item.dataset.type || 'material';
        const description = item.querySelector('.item-desc')?.value.trim();
        const quantity = parseFloat(item.querySelector('.item-qty')?.value) || 0;
        const unitPrice = parseFloat(item.querySelector('.item-price')?.value) || 0;

        if (!description) return;

        if (type === 'labor') {
            items.push({
                type: 'labor',
                description,
                hours: quantity,
                hourly_rate: unitPrice,
            });
            return;
        }

        items.push({
            type: 'material',
            description,
            quantity,
            unit_price: unitPrice,
        });
    });

    return items;
}

export function renderBudgetDetailsForAdmin(details) {
    const container = document.getElementById('budgetDetailsContainer');
    const list = document.getElementById('budgetDetailsList');
    const total = document.getElementById('budgetDetailsTotal');

    if (!container || !list) return;

    if (!details || !Array.isArray(details) || details.length === 0) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');

    const labels = budgetLabels();

    let totalAmount = 0;
    let materialTotal = 0;
    let laborTotal = 0;

    list.innerHTML = details.map((item, index) => {
        const type = item.type || 'material';
        let subtotal = 0;
        let detail = '';

        if (type === 'labor') {
            const hours = item.hours || 0;
            const rate = item.hourly_rate || 0;
            subtotal = hours * rate;
            laborTotal += subtotal;
            detail = `${hours}h × ${formatCurrency(rate)}/h`;
        } else {
            const quantity = item.quantity || 0;
            const unitPrice = item.unit_price || 0;
            subtotal = quantity * unitPrice;
            materialTotal += subtotal;
            detail = `${quantity} × ${formatCurrency(unitPrice)}`;
        }

        totalAmount += subtotal;

        const icon = type === 'labor'
            ? '<svg class="h-3.5 w-3.5 inline-block align-[-2px] text-(--text-soft)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>'
            : '<svg class="h-3.5 w-3.5 inline-block align-[-2px] text-(--text-soft)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>';

        return `
            <div class="flex items-center justify-between py-1 text-xs ${index > 0 ? 'border-t border-(--border)/50' : ''}">
                <span class="mr-2 flex-1 truncate text-(--text)">${icon} ${escapeHtml(item.description) || escapeHtml(labels.item)}</span>
                <span class="mx-2 whitespace-nowrap text-xs text-(--text-soft)">${detail}</span>
                <span class="whitespace-nowrap font-bold font-mono text-(--text)">${formatCurrency(subtotal)}</span>
            </div>
        `;
    }).join('');

    if (materialTotal > 0 || laborTotal > 0) {
        list.innerHTML += `
            <div class="mt-2 space-y-1 border-t-2 border-(--border) pt-2">
                ${materialTotal > 0 ? `<div class="flex items-center justify-between text-xs"><span class="inline-flex items-center gap-1.5 font-medium text-(--text-soft)"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>Total Materials</span><span class="font-bold font-mono text-(--text)">${formatCurrency(materialTotal)}</span></div>` : ''}
                ${laborTotal > 0 ? `<div class="flex items-center justify-between text-xs"><span class="inline-flex items-center gap-1.5 font-medium text-(--text-soft)"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>Total Labor</span><span class="font-bold font-mono text-(--text)">${formatCurrency(laborTotal)}</span></div>` : ''}
            </div>
        `;
    }

    if (total) total.textContent = formatCurrency(totalAmount);
}
