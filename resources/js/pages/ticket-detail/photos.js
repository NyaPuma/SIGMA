import { authHeader } from '../../utils/api.js';
import { escapeHtml } from '../../utils/escape.js';
import { state } from './state.js';
import { showMessage } from './ui.js';

function translations() {
    const section = document.getElementById('photosSection');
    const data = section?.dataset || {};

    return {
        empty: window.SGM_TICKET_MEDIA_I18N?.empty || data.photoEmpty || '',
        removePhoto: window.SGM_TICKET_MEDIA_I18N?.removePhoto || data.photoRemove || '',
        removeFile: window.SGM_TICKET_MEDIA_I18N?.removeFile || data.fileRemove || '',
        loadError: window.SGM_TICKET_MEDIA_I18N?.loadError || data.photoLoadError || '',
        confirmRemove: window.SGM_TICKET_MEDIA_I18N?.confirmRemove || data.photoConfirmRemove || '',
        removeError: window.SGM_TICKET_MEDIA_I18N?.removeError || data.photoRemoveError || '',
        removed: window.SGM_TICKET_MEDIA_I18N?.removed || data.photoRemoved || '',
        sent: window.SGM_TICKET_MEDIA_I18N?.sent || data.photoSent || '',
        file: window.SGM_TICKET_MEDIA_I18N?.file || data.fileLabel || '',
    };
}

export async function fetchPhotos() {
    const section = document.getElementById('photosSection');
    if (!section) return;

    try {
        const response = await fetch(`/tickets/${state.ticketId}/photos`, { headers: authHeader() });
        if (!response.ok) return;

        const data = await response.json();
        const attachments = data.attachments || data;

        if (!attachments || attachments.length === 0) {
            section.innerHTML = `<p class="italic text-[var(--text-soft)]">${translations().empty || ''}</p>`;
            return;
        }

        section.innerHTML = `<div class="grid grid-cols-2 gap-3">${attachments.map((attachment) => {
            const isImage = attachment.mime_type?.startsWith('image/');
            const fileName = escapeHtml(attachment.file_name);
            const imageUrl = escapeHtml(`/storage/${attachment.path}`);
            const mimeType = escapeHtml(attachment.mime_type);

            if (isImage) {
                return `<div class="group relative overflow-hidden rounded-xl border border-[var(--border)] bg-[var(--surface-2)] shadow-sm">
                    <a href="${imageUrl}" target="_blank" title="${fileName}">
                        <img src="${imageUrl}" alt="${fileName}" class="h-24 w-full object-cover transition-opacity duration-150 group-hover:opacity-85">
                    </a>
                    <button data-action="delete-photo" data-photo-id="${attachment.id}" type="button" class="absolute right-1 top-1 z-10 rounded-lg bg-danger/80 p-1 text-white shadow-sm transition-all hover:bg-danger cursor-pointer" title="${translations().removePhoto || ''}">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"></path></svg>
                    </button>
                    <div class="border-t border-[var(--border)] p-1.5">
                        <p class="truncate text-xs font-semibold text-[var(--text-soft)]">${fileName}</p>
                    </div>
                </div>`;
            }

            return `<div class="relative flex min-h-[96px] flex-col justify-between rounded-xl border border-[var(--border)] bg-[var(--surface-2)] p-2.5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="line-clamp-2 text-xs font-bold text-[var(--text)]">${fileName}</p>
                    <button data-action="delete-photo" data-photo-id="${attachment.id}" type="button" class="flex-shrink-0 rounded-lg bg-danger/80 p-1 text-white shadow-sm transition-all hover:bg-danger cursor-pointer" title="${translations().removeFile || ''}">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"></path></svg>
                    </button>
                </div>
                <p class="mt-2 text-xs font-mono uppercase tracking-wider text-[var(--text-soft)]">${mimeType || translations().file || ''}</p>
            </div>`;
        }).join('')}</div>`;
    } catch {
        section.innerHTML = `<p class="italic text-danger">${translations().loadError || ''}</p>`;
    }
}

export async function deletePhoto(photoId) {
    if (!confirm(translations().confirmRemove || '')) return;

    const response = await fetch(`/tickets/${state.ticketId}/photos/${photoId}`, {
        method: 'DELETE',
        headers: authHeader(),
    });

    const data = await response.json();

    if (!response.ok) {
        showMessage(data.message || translations().removeError || '', true);
        return;
    }

    await fetchPhotos();
    showMessage(translations().removed || '');
}

export function bindPhotoForm() {
    document.getElementById('photoForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const fileInput = document.getElementById('photoInput');
        if (!fileInput?.files.length) return;

        const formData = new FormData();
        formData.append('photo', fileInput.files[0]);

        const headers = authHeader();
        delete headers['Content-Type'];

        const response = await fetch(`/tickets/${state.ticketId}/photos`, {
            method: 'POST',
            headers,
            body: formData,
        });

        if (!response.ok) return;

        fileInput.value = '';
        await fetchPhotos();
        showMessage(translations().sent || '');
    });
}
