import { fetchMovements, createMovement } from './movements/api.js';
import { bindPagination, clearMovementFilters, renderLoadingState } from './movements/dom.js';
import { renderEmptyState, renderErrorState, renderMovements, renderPagination, renderResultsCount, showFeedback } from './movements/render.js';
import { movementsState, setCurrentPage } from './movements/state.js';
import { openItemSelectorModal } from '../../components/item-selector-modal.js';

async function loadMovements(page = 1) {
    setCurrentPage(page);
    renderLoadingState();

    try {
        const data = await fetchMovements(page);
        if (!data) return;

        const movements = data.movements?.data ?? data.movements ?? [];
        const pagination = data.pagination ?? {};
        const total = pagination.total ?? movements.length;

        renderResultsCount(total);

        if (!movements.length) {
            renderEmptyState();
            return;
        }

        renderMovements(movements);
        renderPagination(pagination, page);
    } catch (error) {
        showFeedback(error.message, true);
        renderErrorState(error.message);
    }
}

function bindForm() {
    const form = document.getElementById('movementForm');
    const message = document.getElementById('mvMessage');
    const submitBtn = document.getElementById('mvSubmit');
    const partSearchInput = document.getElementById('mvPartSearch');
    const partHiddenInput = document.getElementById('mvPart');

    if (!form) return;

    // Initialize part selector modal
    if (partSearchInput && partHiddenInput) {
        partSearchInput.readOnly = true;
        partSearchInput.style.cursor = 'pointer';

        partSearchInput.addEventListener('click', () => {
            openItemSelectorModal({
                itemType: 'part',
                multiSelect: false,
                triggerInput: partSearchInput,
                onConfirm: (selected) => {
                    if (selected && selected.length > 0) {
                        const part = selected[0];
                        partHiddenInput.value = part.id;
                        partSearchInput.value = part.name || '';
                    }
                },
            });
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = {
            part_id: document.getElementById('mvPart').value,
            movement_type: document.getElementById('mvType').value,
            quantity: document.getElementById('mvQty').value,
            reason: document.getElementById('mvReason').value.trim() || null,
        };

        submitBtn.disabled = true;
        message.textContent = window.SGM_UI_I18N?.recordingMovement || 'Recording movement...';
        message.className = 'text-xs font-medium text-(--text-soft)';

        try {
            const data = await createMovement(payload);

            message.textContent = data.message || 'Movimento registado!';
            message.className = 'text-xs font-medium text-success';

            form.reset();
            loadMovements(movementsState.currentPage);
        } catch (error) {
            message.textContent = error.message;
            message.className = 'text-xs font-medium text-danger';
        } finally {
            submitBtn.disabled = false;
        }
    });
}

function bindFilters() {
    document.getElementById('btnSearch')?.addEventListener('click', () => loadMovements(1));

    document.getElementById('btnClear')?.addEventListener('click', () => {
        clearMovementFilters();
        loadMovements(1);
    });

    document.getElementById('filter_part')?.addEventListener('change', () => loadMovements(1));
    document.getElementById('filter_type')?.addEventListener('change', () => loadMovements(1));
}

function init() {
    bindForm();
    bindFilters();
    bindPagination(loadMovements);
    loadMovements(movementsState.currentPage);
}

export { init };
