function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function setSaveStatus(form, state, message = '') {
    const status = form.querySelector('[data-save-status]');
    if (!status) {
        return;
    }

    const messages = {
        saving: (window.SGM_UI_I18N?.saving || 'Saving...'),
        saved: (window.SGM_UI_I18N?.savedShort || 'Saved'),
        dirty: (window.SGM_UI_I18N?.unsavedChanges || 'Unsaved changes'),
        error: message || (window.SGM_UI_I18N?.saveError || 'Error saving — please try again.'),
        idle: '',
    };

    status.textContent = messages[state] || '';
    status.setAttribute('data-state', state);
}

function collectGroupPayload(form) {
    const payload = {};
    form.querySelectorAll('[name]').forEach((field) => {
        if (field.name === '_token') {
            return;
        }

        payload[field.name] = field.type === 'checkbox' ? field.checked : field.value;
    });

    return payload;
}

function applySavedValues(form, values) {
    form.querySelectorAll('[name]').forEach((field) => {
        if (field.name === '_token' || !(field.name in values)) {
            return;
        }

        if (field.type === 'checkbox') {
            field.checked = Boolean(values[field.name]);
        } else {
            field.value = values[field.name];
        }

        if (field.dataset.autoSave !== undefined && field.type === 'checkbox') {
            const wrapper = field.closest('.system-settings__field');
            const label = wrapper ? wrapper.querySelector('[data-switch-label]') : null;
            if (label) {
                label.textContent = field.checked
                    ? (window.SGM_UI_I18N?.stateActive || 'Active')
                    : (window.SGM_UI_I18N?.stateInactive || 'Inactive');
            }
        }
    });
}

function postForm(form, body) {
    setSaveStatus(form, 'saving');

    return fetch(form.getAttribute('action'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(body),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('save-failed');
            }
            return response.json();
        })
        .then((data) => {
            if (data && data.values) {
                applySavedValues(form, data.values);
            }
            setSaveStatus(form, 'saved');
        })
        .catch(() => {
            setSaveStatus(form, 'error');
        });
}

function debounce(fn, wait) {
    let timer = null;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

export function init() {
    const groups = document.querySelectorAll('[data-group-form]');
    if (!groups.length) {
        return;
    }

    groups.forEach((form) => {
        const groupId = form.dataset.groupForm;

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            postForm(form, { updates: collectGroupPayload(form) });
        });

        form.querySelectorAll('[data-auto-save]').forEach((field) => {
            const save = debounce(() => {
                postForm(form, { updates: { [field.name]: field.type === 'checkbox' ? field.checked : field.value } });
            }, 300);

            field.addEventListener('change', save);
        });

        const hasSubmit = Boolean(form.querySelector('button[type="submit"]'));
        form.querySelectorAll('[name]:not([data-auto-save])').forEach((field) => {
            if (field.name === '_token' || !hasSubmit) {
                return;
            }

            field.addEventListener('input', () => {
                setSaveStatus(form, 'dirty');
            });
        });

        const resetButton = form.querySelector('[data-reset]');
        if (resetButton) {
            resetButton.addEventListener('click', () => {
                postForm(form, { reset: groupId });
            });
        }
    });
}
