import { authDelete, authPatch, authPost } from '../../utils/api.js';

function extractError(data) {
    let errorText = data.message || 'An error occurred.';
    if (data.errors) {
        errorText = Object.values(data.errors).flat().join(' ');
    }
    return errorText;
}

function showMessage(element, text, isError) {
    element.textContent = text;
    element.className = 'text-xs font-medium ' + (isError
        ? 'text-danger'
        : 'text-(--text-soft)');
}

function resetForm(form) {
    form.dataset.taxRateFormMode = 'create';
    form.dataset.taxRateId = '';
    document.getElementById('trName').value = '';
    document.getElementById('trPercent').value = '';
    document.getElementById('trDefault').checked = false;
    document.getElementById('trActive').checked = true;
    document.getElementById('taxRateFormTitle').textContent = window.SGM_UI_I18N?.newTaxRateTitle || 'New VAT rate';
    document.getElementById('trMessage').textContent = '';
}

async function submitHandler(e) {
    e.preventDefault();
    const form = e.target;
    const message = document.getElementById('trMessage');
    const submitBtn = document.getElementById('trSubmit');

    const mode = form.dataset.taxRateFormMode;
    const id = form.dataset.taxRateId;

    const payload = {
        name: document.getElementById('trName').value.trim(),
        percent: document.getElementById('trPercent').value,
        is_default: document.getElementById('trDefault').checked,
        active: document.getElementById('trActive').checked,
    };

    submitBtn.disabled = true;
    showMessage(message, (window.SGM_UI_I18N?.saving || 'Saving...'), false);

    try {
        const response = mode === 'edit' && id
            ? await authPatch(`/admin/tax-rates/${id}`, payload)
            : await authPost('/admin/tax-rates', payload);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) throw new Error(extractError(data));

        showMessage(message, data.message || (window.SGM_UI_I18N?.savedSuccess || 'Saved successfully!'));
        message.className = 'text-xs font-medium text-success';
        resetForm(form);
        window.location.reload();
    } catch (err) {
        showMessage(message, err.message, true);
    } finally {
        submitBtn.disabled = false;
    }
}

async function handleEdit(button) {
    document.getElementById('trName').value = button.dataset.name || '';
    document.getElementById('trPercent').value = button.dataset.percent || '';
    document.getElementById('trDefault').checked = button.dataset.default === '1';
    document.getElementById('trActive').checked = button.dataset.active === '1';

    const form = document.getElementById('taxRateForm');
    form.dataset.taxRateFormMode = 'edit';
    form.dataset.taxRateId = button.dataset.taxRateEdit;
    document.getElementById('taxRateFormTitle').textContent = window.SGM_UI_I18N?.editTaxRateTitle || 'Edit VAT rate';
    document.getElementById('trMessage').textContent = '';
}

async function handleDelete(id) {
    const message = document.getElementById('trMessage');
    showMessage(message, 'Deactivating rate...', false);

    try {
        const response = await authDelete(`/admin/tax-rates/${id}`);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) throw new Error(extractError(data));

        window.location.reload();
    } catch (err) {
        showMessage(message, err.message, true);
    }
}

function init() {
    const form = document.getElementById('taxRateForm');
    if (form) form.addEventListener('submit', submitHandler);

    document.getElementById('trReset')?.addEventListener('click', () => resetForm(form));

    document.querySelectorAll('[data-tax-rate-edit]').forEach((button) => {
        button.addEventListener('click', () => handleEdit(button));
    });

    document.querySelectorAll('[data-tax-rate-delete]').forEach((button) => {
        button.addEventListener('click', () => {
            if (window.confirm((window.SGM_UI_I18N?.confirmDeactivateTax || 'Are you sure you want to deactivate this VAT rate?'))) {
                handleDelete(button.dataset.taxRateDelete);
            }
        });
    });
}

export { init };
