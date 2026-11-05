import { createPlan, deletePlan, fetchPlan, fetchPlans, updatePlan } from './plans/api.js';
import { bindPagination, clearPlanFilters, renderLoadingState } from './plans/dom.js';
import { renderEmptyState, renderErrorState, renderPagination, renderPlans, renderResultsCount, showFeedback } from './plans/render.js';
import { plansState, setCurrentPage } from './plans/state.js';
import { openItemSelectorModal } from '../../components/item-selector-modal.js';

const intervalLabels = { days: 'Days', usage_hours: 'Usage hours', cycles: 'Cycles' };

let partRowPickers = [];

function firstPartRow() {
    const container = document.getElementById('plPartsContainer');
    if (!container) return null;
    
    // Create a template row for parts
    const template = document.createElement('div');
    template.dataset.partRow = '';
    template.className = 'flex items-center gap-2';
    template.innerHTML = `
        <input type="text" data-part-search class="flex-1 min-w-0 rounded-lg border border-(--border) bg-(--surface-2) px-2.5 py-1.5 text-xs text-(--text) outline-none transition-all focus:border-primary cursor-pointer" placeholder="Select part...">
        <input type="hidden" data-part-id value="">
        <input type="number" data-expected-qty class="w-20 rounded-lg border border-(--border) bg-(--surface-2) px-2 py-1.5 text-xs font-mono text-(--text) outline-none transition-all focus:border-primary" placeholder="Qty" min="1" value="1">
        <button type="button" data-part-row-remove class="p-1 text-danger/80 hover:text-danger transition cursor-pointer">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;
    
    // If container is empty, add the template as the first row
    if (container.children.length === 0 || container.querySelector('#plNoParts')) {
        container.innerHTML = '';
        container.appendChild(template);
    }
    
    return template;
}

async function loadPlans(page = 1) {
    setCurrentPage(page);
    renderLoadingState();

    try {
        const data = await fetchPlans(page);
        if (!data) return;

        const plans = data.plans?.data ?? data.plans ?? [];
        const pagination = data.pagination ?? {};
        const total = pagination.total ?? plans.length;

        renderResultsCount(total);

        if (!plans.length) {
            renderEmptyState();
            return;
        }

        renderPlans(plans);
        renderPagination(pagination, page);
    } catch (error) {
        showFeedback(error.message, true);
        renderErrorState(error.message);
    }
}

function initPartRowPicker(row) {
    const searchInput = row.querySelector('[data-part-search]');
    const hiddenInput = row.querySelector('[data-part-id]');
    
    if (!searchInput || !hiddenInput) return null;

    searchInput.readOnly = true;
    searchInput.style.cursor = 'pointer';

    searchInput.addEventListener('click', () => {
        openItemSelectorModal({
            itemType: 'part',
            multiSelect: false,
            triggerInput: searchInput,
            onConfirm: (selected) => {
                if (selected && selected.length > 0) {
                    const part = selected[0];
                    hiddenInput.value = part.id;
                    searchInput.value = part.name || '';
                }
            },
        });
    });

    return { clear: () => {
        searchInput.value = '';
        hiddenInput.value = '';
    }};
}

function initPartRow(row, part = null) {
    row.querySelector('[data-part-search]').value = part ? part.name : '';
    row.querySelector('[data-part-id]').value = part ? part.id : '';
    row.querySelector('[data-expected-qty]').value = part ? part.expected_quantity : '';

    const picker = initPartRowPicker(row);
    if (picker) {
        partRowPickers.push(picker);
    }

    row.querySelector('[data-part-row-remove]').addEventListener('click', () => {
        const idx = partRowPickers.indexOf(picker);
        if (idx !== -1) partRowPickers.splice(idx, 1);
        if (picker && picker.clear) picker.clear();
        row.remove();
    });

    return picker;
}

function addPartRow(part = null) {
    const container = document.getElementById('plPartsContainer');
    const template = firstPartRow();
    if (!container || !template) return;
    const row = template.cloneNode(true);
    container.appendChild(row);
    initPartRow(row, part);
}

function buildPartsPayload() {
    return Array.from(document.querySelectorAll('#plPartsContainer [data-part-row]'))
        .map((row) => ({
            part_id: Number(row.querySelector('[data-part-id]').value || 0),
            expected_quantity: Number(row.querySelector('[data-expected-qty]').value || 1),
        }))
        .filter((entry) => entry.part_id > 0);
}

function resetForm() {
    const form = document.getElementById('planForm');
    form.dataset.planFormMode = 'create';
    form.dataset.planId = '';

    document.getElementById('plName').value = '';
    document.getElementById('plEquipmentSearch').value = '';
    document.getElementById('plEquipment').value = '';
    document.getElementById('plIntervalType').value = 'days';
    document.getElementById('plIntervalValue').value = '';
    document.getElementById('plDescription').value = '';
    document.getElementById('plActive').checked = true;

    document.querySelectorAll('#plPartsContainer [data-part-row]').forEach((row, index) => {
        if (index > 0) row.remove();
    });
    partRowPickers.forEach(picker => picker?.clear?.());
    partRowPickers = [];
    const firstRow = firstPartRow();
    initPartRow(firstRow, null);

    document.getElementById('planFormTitle').textContent = window.SGM_UI_I18N?.newPlanTitle || 'New maintenance plan';
    document.getElementById('plMessage').textContent = '';
}

function showMessage(text, isError) {
    const message = document.getElementById('plMessage');
    message.textContent = text;
    message.className = 'text-xs font-medium ' + (isError
        ? 'text-danger'
        : 'text-success');
}

function bindForm() {
    const form = document.getElementById('planForm');
    const submitBtn = document.getElementById('plSubmit');
    const equipmentSearchInput = document.getElementById('plEquipmentSearch');
    const equipmentHiddenInput = document.getElementById('plEquipment');

    if (!form) return;

    // Initialize equipment selector modal
    if (equipmentSearchInput && equipmentHiddenInput) {
        equipmentSearchInput.readOnly = true;
        equipmentSearchInput.style.cursor = 'pointer';

        equipmentSearchInput.addEventListener('click', () => {
            openItemSelectorModal({
                itemType: 'equipment',
                multiSelect: false,
                triggerInput: equipmentSearchInput,
                onConfirm: (selected) => {
                    if (selected && selected.length > 0) {
                        const equipment = selected[0];
                        equipmentHiddenInput.value = equipment.id;
                        equipmentSearchInput.value = equipment.name || '';
                    }
                },
            });
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const mode = form.dataset.planFormMode;
        const id = form.dataset.planId;

        const payload = {
            name: document.getElementById('plName').value.trim(),
            equipment_id: Number(document.getElementById('plEquipment').value),
            interval_type: document.getElementById('plIntervalType').value,
            interval_value: Number(document.getElementById('plIntervalValue').value),
            description: document.getElementById('plDescription').value.trim() || null,
            active: document.getElementById('plActive').checked,
            parts: buildPartsPayload(),
        };

        submitBtn.disabled = true;
        showMessage((window.SGM_UI_I18N?.saving || 'Saving plan...'), false);

        try {
            const data = mode === 'edit' && id
                ? await updatePlan(id, payload)
                : await createPlan(payload);

            showMessage(data.message || (window.SGM_UI_I18N?.savedSuccess || 'Plan saved successfully!'), false);
            resetForm();
            loadPlans(plansState.currentPage);
        } catch (error) {
            showMessage(error.message, true);
        } finally {
            submitBtn.disabled = false;
        }
    });

    document.getElementById('plReset')?.addEventListener('click', resetForm);
    document.getElementById('plAddPart')?.addEventListener('click', () => addPartRow());
}

async function handleEdit(id) {
    const message = document.getElementById('plMessage');

    try {
        const data = await fetchPlan(id);
        const plan = data.plan ?? {};

        document.getElementById('plName').value = plan.name || '';
        document.getElementById('plEquipmentSearch').value = plan.equipment?.name || '';
        document.getElementById('plEquipment').value = plan.equipment_id || '';
        document.getElementById('plIntervalType').value = plan.interval_type || 'days';
        document.getElementById('plIntervalValue').value = plan.interval_value || '';
        document.getElementById('plDescription').value = plan.description || '';
        document.getElementById('plActive').checked = plan.active !== false;

        partRowPickers.forEach(picker => picker?.clear?.());
        partRowPickers = [];
        document.querySelectorAll('#plPartsContainer [data-part-row]').forEach((row, index) => {
            if (index > 0) row.remove();
        });
        const firstRow = firstPartRow();

        (plan.parts ?? []).forEach((part, index) => {
            if (index === 0) {
                initPartRow(firstRow, part);
            } else {
                addPartRow(part);
            }
        });

        const form = document.getElementById('planForm');
        form.dataset.planFormMode = 'edit';
        form.dataset.planId = String(id);
        document.getElementById('planFormTitle').textContent = window.SGM_UI_I18N?.editPlanTitle || 'Edit maintenance plan';
        message.textContent = '';
    } catch (error) {
        showMessage(error.message, true);
    }
}

async function handleDelete(id) {
    const message = document.getElementById('plMessage');

    try {
        const data = await deletePlan(id);
        showMessage(data.message || (window.SGM_UI_I18N?.deletedSuccess || 'Plan deleted successfully!'), false);
        loadPlans(plansState.currentPage);
    } catch (error) {
        showMessage(error.message, true);
    }
}

function bindTableActions() {
    const tbody = document.getElementById('plansTableBody');
    if (!tbody) return;

    tbody.addEventListener('click', (event) => {
        const editButton = event.target.closest('[data-plan-edit]');
        if (editButton) {
            handleEdit(editButton.dataset.planEdit);
            return;
        }

        const deleteButton = event.target.closest('[data-plan-delete]');
        if (deleteButton && window.confirm((window.SGM_UI_I18N?.confirmDeletePlan || 'Are you sure you want to delete this maintenance plan?'))) {
            handleDelete(deleteButton.dataset.planDelete);
        }
    });
}

function bindFilters() {
    document.getElementById('filter_equipment')?.addEventListener('change', () => loadPlans(1));
}

function init() {
    bindForm();
    bindTableActions();
    bindFilters();
    bindPagination(loadPlans);
    loadPlans(plansState.currentPage);
}

export { init };
