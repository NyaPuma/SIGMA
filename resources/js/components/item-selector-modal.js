import { authHeader } from '../utils/api.js';
import { formatCurrency } from '../utils/locale.js';
import { escapeHtml } from '../utils/escape.js';

function uiLabels() {
    return window.SGM_UI_I18N || {};
}

let abortController = null;

const modal = {
    overlay: null,
    items: [],
    selectedItems: new Map(),
    searchDebounce: null,
    itemType: 'part', // 'part' or 'equipment'
    multiSelect: true,
};

async function fetchItems(query = '', itemType = 'part', pageSize = 100) {
    if (abortController) abortController.abort();
    abortController = new AbortController();

    const params = new URLSearchParams({ per_page: String(pageSize) });
    if (query.trim()) params.set('q', query.trim());

    const endpoint = itemType === 'part' ? '/stock/parts' : '/equipments';
    
    const response = await fetch(`${endpoint}?${params.toString()}`, {
        headers: authHeader(),
        signal: abortController.signal,
    });
    
    if (!response.ok) return [];
    const data = await response.json().catch(() => ({}));

    const items = Array.isArray(data[itemType === 'part' ? 'parts' : 'equipments'])
        ? data[itemType === 'part' ? 'parts' : 'equipments']
        : Array.isArray(data[itemType === 'part' ? 'parts' : 'equipments']?.data) 
            ? data[itemType === 'part' ? 'parts' : 'equipments'].data 
            : [];

    return items.map((item) => item?.data ?? item);
}

function renderItemRow(item, itemType) {
    const id = String(item.id);
    const isSelected = modal.selectedItems.has(id);
    const selectedClass = isSelected ? 'border-primary bg-primary/10' : 'border-(--border) bg-(--surface-2)/60';
    
    if (itemType === 'part') {
        const price = formatCurrency(item.sale_price || item.price_with_vat || 0);
        const stock = item.current_stock ?? 0;
        const unit = item.unit_of_measure || 'un';
        return `
            <div data-is-item="${id}" class="flex items-center gap-3 p-2.5 rounded-xl border ${selectedClass} hover:bg-(--surface-2) transition cursor-pointer">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-semibold text-(--text)">${escapeHtml(item.name)}</span>
                    <span class="block truncate text-[11px] text-(--text-soft)">${escapeHtml(item.sku || item.code)}</span>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block font-mono text-xs font-bold text-(--text)">${price}</span>
                    <span class="block text-[11px] text-(--text-soft)">${stock} ${escapeHtml(unit)}</span>
                </span>
            </div>
        `;
    } else {
        // Equipment
        return `
            <div data-is-item="${id}" class="flex items-center gap-3 p-2.5 rounded-xl border ${selectedClass} hover:bg-(--surface-2) transition cursor-pointer">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-semibold text-(--text)">${escapeHtml(item.name)}</span>
                    <span class="block truncate text-[11px] text-(--text-soft)">${escapeHtml(item.code || item.serial_number)}</span>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block text-[11px] text-(--text-soft)">${escapeHtml(item.location || item.room?.name) || '—'}</span>
                </span>
            </div>
        `;
    }
}

function renderItemsList(items, itemType) {
    if (!items || items.length === 0) {
        const emptyText = itemType === 'part'
            ? (uiLabels().noMaterialInStock || 'No material in stock.')
            : (uiLabels().noEquipmentFound || 'No equipment found.');
        return `<div class="py-6 text-center text-xs text-(--text-soft)">${escapeHtml(emptyText)}</div>`;
    }

    return items.map((item) => renderItemRow(item, itemType)).join('');
}

function updateItemRowState() {
    const list = modal.overlay?.querySelector('[data-is-list]');
    if (!list) return;
    
    list.querySelectorAll('[data-is-item]').forEach((row) => {
        const id = row.dataset.isItem;
        const isSelected = modal.selectedItems.has(id);
        
        // Update visual state
        row.classList.remove('border-primary', 'bg-primary/10', 'border-(--border)', 'bg-(--surface-2)/60');
        if (isSelected) {
            row.classList.add('border-primary', 'bg-primary/10');
        } else {
            row.classList.add('border-(--border)', 'bg-(--surface-2)/60');
        }
    });
}

async function loadItemsIntoModal(query, setLoading = true) {
    const list = modal.overlay?.querySelector('[data-is-list]');
    if (!list) return;

    if (setLoading) {
        const loadingText = modal.itemType === 'part'
            ? (uiLabels().loadingMaterials || 'Loading materials...')
            : (uiLabels().loadingEquipment || 'Loading equipment...');
        list.innerHTML = `<div class="py-6 text-center text-xs text-(--text-soft)">${escapeHtml(loadingText)}</div>`;
    }

    try {
        modal.items = await fetchItems(query, modal.itemType);
    } catch (error) {
        if (error?.name === 'AbortError') return;
        modal.items = [];
        if (modal.overlay?.contains(list)) {
            const errorText = modal.itemType === 'part'
                ? (uiLabels().errorLoadingMaterials || 'Error loading materials.')
                : (uiLabels().errorLoadingEquipment || 'Error loading equipment.');
            list.innerHTML = `<div class="py-6 text-center text-xs text-danger">${escapeHtml(errorText)}</div>`;
            return;
        }
    }

    if (modal.overlay?.contains(list)) {
        list.innerHTML = renderItemsList(modal.items, modal.itemType);
        updateItemRowState();
    }
}

const onSearch = () => {
    const search = modal.overlay?.querySelector('[data-is-search]');
    if (!search) return;
    clearTimeout(modal.searchDebounce);
    modal.searchDebounce = setTimeout(() => loadItemsIntoModal(search.value.trim()), 250);
};

function updateCount() {
    const el = modal.overlay?.querySelector('[data-is-count]');
    if (!el) return;
    const total = modal.selectedItems.size;
    el.textContent = `${total} ${uiLabels().itemsSelected || 'item(s) selected'}`;
}

function updateInputDisplay() {
    const input = modal.triggerInput;
    if (!input) return;
    
    if (modal.selectedItems.size === 0) {
        input.value = '';
        return;
    }
    
    const firstItem = modal.selectedItems.values().next().value;
    if (firstItem) {
        input.value = firstItem.name || '';
    }
}

export function openItemSelectorModal(options = {}) {
    const {
        itemType = 'part',
        multiSelect = true,
        triggerInput = null,
        onConfirm = null,
        selectedIds = [],
    } = options;

    if (modal.overlay) closeItemSelectorModal();
    
    modal.itemType = itemType;
    modal.multiSelect = multiSelect;
    modal.triggerInput = triggerInput;
    modal.onConfirm = onConfirm;
    modal.selectedItems = new Map();
    modal.items = [];

    // Pre-select items if provided
    if (selectedIds && selectedIds.length > 0) {
        // Will be populated after loading items
    }

    renderItemSelectorModal();
    loadItemsIntoModal('');
}

function renderItemSelectorModal() {
    const existing = document.getElementById('itemSelectorModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'itemSelectorModal';
    overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4';
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeItemSelectorModal();
    });

    const labels = uiLabels();
    const isPart = modal.itemType === 'part';
    const title = escapeHtml(isPart ? (labels.selectMaterials || 'Select Materials (Stock)') : (labels.selectEquipment || 'Select Equipment'));
    const badgeText = escapeHtml(isPart ? (labels.materialsBadge || 'Materials (Stock)') : (labels.equipmentBadge || 'Equipment'));
    const placeholder = escapeHtml(isPart ? (labels.searchMaterial || 'Search material in stock...') : (labels.searchEquipment || 'Search equipment...'));

    overlay.innerHTML = `
        <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-(--border) bg-(--surface) shadow-2xl">
            <div class="flex items-center justify-between border-b border-(--border) px-4 py-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-(--text)">${title}</h3>
                <button type="button" data-is-close class="p-1 text-(--text-soft) transition-all hover:text-danger cursor-pointer">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto px-4 py-3 space-y-4">
                <div>
                    <div class="mb-2 flex items-center gap-2">
                        <span class="rounded-md bg-(--surface-2) px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-(--text-soft)">${badgeText}</span>
                    </div>
                    <input type="text" data-is-search class="w-full rounded-lg border border-(--border) bg-(--surface-2) px-3 py-2 text-xs text-(--text) outline-none transition-all focus:border-primary" placeholder="${placeholder}">
                    <div data-is-list class="mt-2 space-y-1 max-h-64 overflow-y-auto">
                        ${renderItemsList(modal.items, modal.itemType)}
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-(--border) px-4 py-3">
                <span data-is-count class="text-xs text-(--text-soft)">0 ${escapeHtml(labels.itemsSelected || 'item(s) selected')}</span>
                <div class="flex items-center gap-2">
                    <button type="button" data-is-cancel class="px-3 py-2 text-xs font-bold text-(--text-soft) border border-(--border) rounded-lg hover:bg-(--surface-2) transition cursor-pointer">${escapeHtml(labels.cancel || 'Cancel')}</button>
                    <button type="button" data-is-confirm class="px-4 py-2 text-xs font-bold text-[var(--on-primary)] bg-primary hover:bg-primary-hover rounded-lg shadow-sm transition cursor-pointer">${escapeHtml(labels.confirm || 'Confirm')}</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    modal.overlay = overlay;

    overlay.querySelector('[data-is-close]').addEventListener('click', closeItemSelectorModal);
    overlay.querySelector('[data-is-cancel]').addEventListener('click', closeItemSelectorModal);
    overlay.querySelector('[data-is-search]').addEventListener('input', onSearch);
    overlay.querySelector('[data-is-confirm]').addEventListener('click', confirmSelection);

    // Toggle item selection (click on row)
    overlay.querySelector('[data-is-list]').addEventListener('click', (e) => {
        const row = e.target.closest('[data-is-item]');
        if (!row) return;
        
        const item = modal.items.find((p) => String(p.id) === row.dataset.isItem);
        if (!item) return;
        
        // Single select: clear others and select this one
        modal.selectedItems.clear();
        modal.selectedItems.set(String(item.id), item);
        
        updateItemRowState();
        updateCount();
        updateInputDisplay();
    });

    updateCount();
}

function confirmSelection() {
    const selected = Array.from(modal.selectedItems.values());
    
    if (modal.onConfirm) {
        modal.onConfirm(selected);
    }
    
    closeItemSelectorModal();
}

function closeItemSelectorModal() {
    modal.overlay?.remove();
    modal.overlay = null;
    modal.selectedItems = new Map();
    modal.items = [];
    modal.triggerInput = null;
    modal.onConfirm = null;
}
