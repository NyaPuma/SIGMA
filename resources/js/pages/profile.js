import { authHeaderJson } from '../utils/api.js';

const MESSAGE_TONES = {
    neutral: 'min-h-6 text-sm font-medium text-[var(--text-soft)]',
    success: 'min-h-6 text-sm font-medium text-success',
    error: 'min-h-6 text-sm font-medium text-danger',
};

function setMessage(element, message, tone = 'neutral') {
    if (!element) {
        return;
    }

    element.textContent = message;
    element.className = MESSAGE_TONES[tone] || MESSAGE_TONES.neutral;
}

function getMessages(form) {
    return {
        validation: form?.dataset.validationMessage || (window.SGM_UI_I18N?.validationNameRequired || 'Enter a name to continue.'),
        saving: form?.dataset.savingMessage || (window.SGM_UI_I18N?.saving || 'Saving changes...'),
        success: form?.dataset.successMessage || (window.SGM_UI_I18N?.updatedSuccess || 'Profile updated successfully.'),
        error: form?.dataset.errorMessage || (window.SGM_UI_I18N?.updateFailed || 'Unable to update the profile.'),
    };
}

function bindForm(config) {
    const form = document.getElementById(config.formId);

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();

        const messageElement = document.getElementById(config.messageId);
        const submitBtn = document.getElementById(config.submitId);
        const messages = getMessages(form);

        const payload = config.buildPayload();
        const validationError = config.validate ? config.validate(payload) : null;

        if (validationError) {
            setMessage(messageElement, validationError, 'error');
            return;
        }

        setMessage(messageElement, messages.saving, 'neutral');

        if (submitBtn) {
            submitBtn.disabled = true;
        }

        try {
            const res = await fetch('/profile/update', {
                method: 'POST',
                credentials: 'same-origin',
                headers: authHeaderJson(),
                body: JSON.stringify(payload),
                redirect: 'manual',
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(data.message || messages.error);
            }

            if (config.onSuccess) {
                config.onSuccess(data);
            }

            setMessage(messageElement, messages.success, 'success');
        } catch (error) {
            setMessage(messageElement, error.message || messages.error, 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    });
}

let initialized = false;

function init() {
    if (initialized) {
        return;
    }

    initialized = true;

    if (!document.getElementById('profileForm') && !document.getElementById('passwordForm')) {
        return;
    }

    bindForm({
        formId: 'profileForm',
        messageId: 'profileMessage',
        submitId: 'submitBtn',
        buildPayload: () => ({
            name: document.getElementById('profileName')?.value.trim() || '',
        }),
        validate: (payload) => (payload.name ? null : (window.SGM_UI_I18N?.validationNameRequired || 'Enter a name to continue.')),
        onSuccess: (data) => {
            if (data.user?.name) {
                localStorage.setItem('user_name', data.user.name);
                document.getElementById('displayUserName')?.replaceChildren(document.createTextNode(data.user.name));
                document.getElementById('displayUserAvatar')?.replaceChildren(document.createTextNode(data.user.name.charAt(0).toUpperCase()));
            }

            if (data.user?.profile?.name) {
                localStorage.setItem('user_role', data.user.profile.name);
            }
        },
    });

    bindForm({
        formId: 'passwordForm',
        messageId: 'passwordMessage',
        submitId: 'submitPasswordBtn',
        buildPayload: () => ({
            current_password: document.getElementById('currentPassword')?.value || '',
            password: document.getElementById('newPassword')?.value || '',
            password_confirmation: document.getElementById('newPasswordConfirmation')?.value || '',
        }),
        validate: (payload) => {
            if (!payload.password) {
                return null;
            }

            if (!payload.current_password) {
                return window.SGM_UI_I18N?.passwordCurrentRequired || 'The current password is required to change the password.';
            }

            if (payload.password !== payload.password_confirmation) {
                return window.SGM_UI_I18N?.passwordsDoNotMatch || 'The passwords do not match.';
            }

            return null;
        },
        onSuccess: () => {
            ['currentPassword', 'newPassword', 'newPasswordConfirmation'].forEach((id) => {
                const field = document.getElementById(id);

                if (field) {
                    field.value = '';
                }
            });

            document.getElementById('newPassword')?.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });
}

export { init };
