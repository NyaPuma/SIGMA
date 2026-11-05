import { openItemSelectorModal } from '../../components/item-selector-modal.js';

export async function initAutocomplete() {
    const searchInput = document.getElementById('equipmentSearchInput');
    const hiddenIdInput = document.getElementById('selectedEquipmentId');

    if (!searchInput || !hiddenIdInput) return;

    // The field is a modal trigger: it is never typed into, so the placeholder
    // (owned by the Blade view) has to describe a click, not typing.
    searchInput.readOnly = true;
    searchInput.style.cursor = 'pointer';

    searchInput.addEventListener('click', () => {
        openItemSelectorModal({
            itemType: 'equipment',
            multiSelect: false,
            triggerInput: searchInput,
            onConfirm: (selected) => {
                if (selected && selected.length > 0) {
                    const equipment = selected[0];
                    hiddenIdInput.value = equipment.id;
                    searchInput.value = equipment.name || '';
                }
            },
        });
    });
}
