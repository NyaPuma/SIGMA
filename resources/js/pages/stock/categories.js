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
    form.dataset.categoryFormMode = 'create';
    form.dataset.categoryId = '';
    document.getElementById('catName').value = '';
    document.getElementById('catActive').checked = true;
    document.getElementById('categoryFormTitle').textContent = window.SGM_UI_I18N?.newCategoryTitle || 'New category';
    document.getElementById('catMessage').textContent = '';
}

async function submitHandler(e) {
    e.preventDefault();
    const form = e.target;
    const message = document.getElementById('catMessage');
    const submitBtn = document.getElementById('catSubmit');

    const mode = form.dataset.categoryFormMode;
    const id = form.dataset.categoryId;

    const payload = {
        name: document.getElementById('catName').value.trim(),
        active: document.getElementById('catActive').checked,
    };

    submitBtn.disabled = true;
    showMessage(message, (window.SGM_UI_I18N?.saving || 'Saving...'), false);

    try {
        const response = mode === 'edit' && id
            ? await authPatch(`/admin/part-categories/${id}`, payload)
            : await authPost('/admin/part-categories', payload);
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

function handleEdit(button) {
    document.getElementById('catName').value = button.dataset.name || '';
    document.getElementById('catActive').checked = button.dataset.active === '1';

    const form = document.getElementById('categoryForm');
    form.dataset.categoryFormMode = 'edit';
    form.dataset.categoryId = button.dataset.categoryEdit;
    document.getElementById('categoryFormTitle').textContent = window.SGM_UI_I18N?.editCategoryTitle || 'Edit category';
    document.getElementById('catMessage').textContent = '';
}

async function handleDelete(id) {
    const message = document.getElementById('catMessage');
    showMessage(message, 'Deactivating category...', false);

    try {
        const response = await authDelete(`/admin/part-categories/${id}`);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) throw new Error(extractError(data));

        window.location.reload();
    } catch (err) {
        showMessage(message, err.message, true);
    }
}

function init() {
    const form = document.getElementById('categoryForm');
    if (form) form.addEventListener('submit', submitHandler);

    document.getElementById('catReset')?.addEventListener('click', () => resetForm(form));

    document.querySelectorAll('[data-category-edit]').forEach((button) => {
        button.addEventListener('click', () => handleEdit(button));
    });

    document.querySelectorAll('[data-category-delete]').forEach((button) => {
        button.addEventListener('click', () => {
            if (window.confirm((window.SGM_UI_I18N?.confirmDeactivateCategory || 'Are you sure you want to deactivate this category?'))) {
                handleDelete(button.dataset.categoryDelete);
            }
        });
    });
}

export { init };
