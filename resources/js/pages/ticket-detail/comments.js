import { authHeader } from '../../utils/api.js';
import { formatDateTime } from '../../utils/locale.js';
import { escapeHtml } from '../../utils/escape.js';
import { state } from './state.js';
import { showMessage } from './ui.js';

function translations() {
    const section = document.getElementById('commentsSection');
    const data = section?.dataset || {};

    return {
        noComments: window.SGM_TICKET_DETAIL_I18N?.noComments || data.noComments || '',
        commentsError: window.SGM_TICKET_DETAIL_I18N?.commentsError || data.commentsError || '',
        messageSent: window.SGM_TICKET_DETAIL_I18N?.messageSent || data.messageSent || '',
        system: window.SGM_TICKET_DETAIL_I18N?.system || '',
    };
}

export async function fetchComments() {
    const section = document.getElementById('commentsSection');
    if (!section) return;

    try {
        const response = await fetch(`/tickets/${state.ticketId}/comments`, { headers: authHeader() });
        if (!response.ok) return;

        const data = await response.json();
        const comments = data.comments || data;

        if (!comments || comments.length === 0) {
            section.innerHTML = `<p class="py-1 italic text-(--text-soft)">${translations().noComments}</p>`;
            return;
        }

        section.innerHTML = comments.map((comment) => `
            <div class="space-y-1 border-b border-(--border)/50 py-2">
                <div class="flex justify-between font-bold text-(--text)">
                    <span>${comment.user ? escapeHtml(comment.user.name) : translations().system}</span>
                    <span class="font-mono text-xs text-(--text-soft)">${comment.created_at ? formatDateTime(comment.created_at) : ''}</span>
                </div>
                <p class="text-(--text-soft)">${escapeHtml(comment.comment || comment.message || '')}</p>
            </div>
        `).join('');
    } catch {
        section.innerHTML = `<p class="py-1 italic text-danger">${translations().commentsError}</p>`;
    }
}

export function bindCommentForm() {
    document.getElementById('commentForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const text = document.getElementById('commentText')?.value.trim();
        if (!text) return;

        const response = await fetch(`/tickets/${state.ticketId}/comments`, {
            method: 'POST',
            headers: { ...authHeader(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment: text }),
        });

        if (!response.ok) return;

        document.getElementById('commentText').value = '';
        await fetchComments();
        showMessage(translations().messageSent);
    });
}
