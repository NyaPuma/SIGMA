import { escapeHtml } from '../../utils/escape.js';

const priorityColors = {
    baixa: 'border border-success/10 bg-success/5 text-success',
    média: 'border border-warning/15 bg-warning/5 text-warning',
    alta: 'border border-danger/15 bg-danger/5 text-danger',
    crítica: 'border border-purple-500/20 bg-purple-500/5 text-purple-600 dark:text-purple-400',
};

const translations = () => window.SGM_TICKET_DETAIL_I18N || {};

function resolveStatusBadge(statusClean, statusName) {
    if (statusClean === 'em curso') {
        return `<span class="inline-flex items-center gap-1.5 rounded-lg border border-success/20 bg-success/10 px-2 py-0.5 text-xs font-bold uppercase tracking-tight text-success"><svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/></svg>${translations().inProgress || ''}</span>`;
    }

    if (statusClean === 'fechada' || statusClean === 'fechado') {
        return `<span class="inline-block rounded-lg bg-(--text-soft)/10 px-2 py-0.5 text-xs font-bold uppercase tracking-tight text-(--text-soft)">${translations().closed || ''}</span>`;
    }

    return `<span class="inline-block rounded-lg bg-info/10 px-2 py-0.5 text-xs font-bold uppercase tracking-tight text-info">${translations().status?.[statusClean] || escapeHtml(statusName) || ''}</span>`;
}

export function renderTicketDetails(ticket) {
    const details = document.getElementById('ticketDetails');
    if (!details) return;

    const statusName = typeof ticket.status === 'object' && ticket.status !== null
        ? ticket.status.name
        : (typeof ticket.status === 'string' ? ticket.status : null);
    const statusClean = (statusName || '').toLowerCase();
    const priorityClass = priorityColors[ticket.priority] ?? 'border border-(--border) bg-(--surface-2) text-(--text-soft)';
    const statusBadge = resolveStatusBadge(statusClean, ticket.status_label);
    const priorityLabel = escapeHtml(ticket.priority_label) || translations().priority?.[ticket.priority] || '';
    const dashPlaceholder = '<span class="font-normal text-(--text-soft)">—</span>';
    const equipmentName = ticket.equipment ? escapeHtml(ticket.equipment.name) : dashPlaceholder;
    const roomName = ticket.room ? escapeHtml(ticket.room.name) : dashPlaceholder;
    const technicianName = ticket.technician
        ? escapeHtml(ticket.technician.name)
        : `<span class="font-normal italic text-danger">${translations().pendingAssignment || ''}</span>`;
    const reportedByName = ticket.reporter_name
        ? escapeHtml(ticket.reporter_name)
        : (ticket.user ? escapeHtml(ticket.user.name) : '<span class="font-normal italic text-(--text-soft)">—</span>');

    details.innerHTML = `
        <div class="mb-5 border-b border-(--border) pb-4">
            <div class="flex items-center justify-between gap-4">
                <span class="rounded-lg bg-(--surface-2) px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-(--text-soft) font-mono">${translations().incidentId || ''} #${ticket.id}</span>
                <div class="flex gap-1.5">${statusBadge}</div>
            </div>
            <h2 class="mt-3 text-base font-bold text-(--text)">${escapeHtml(ticket.title)}</h2>
        </div>

        <div class="space-y-5">
            <div>
                <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-(--text-soft)">${translations().description || ''}</span>
                <div class="rounded-xl border border-(--border) bg-(--surface-2) p-3.5 text-xs leading-relaxed text-(--text) whitespace-pre-wrap">${ticket.description ? escapeHtml(ticket.description) : (translations().noDescription || '')}</div>
            </div>

            <div class="grid grid-cols-2 gap-x-4 gap-y-4 pt-2">
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-(--text-soft)">${translations().priorityLevel || ''}</span>
                    <span class="mt-1 inline-block rounded-lg px-2 py-0.5 text-xs font-bold uppercase tracking-tight ${priorityClass}">${priorityLabel}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-(--text-soft)">${translations().equipment || ''}</span>
                    <p class="mt-1 text-xs font-semibold text-(--text)">${equipmentName}</p>
                </div>
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-(--text-soft)">${translations().room || ''}</span>
                    <p class="mt-1 text-xs font-semibold text-(--text)">${roomName}</p>
                </div>
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-(--text-soft)">${translations().technician || ''}</span>
                    <p class="mt-1 text-xs font-semibold text-(--text)">${technicianName}</p>
                </div>
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-(--text-soft)">${translations().reportedBy || ''}</span>
                    <p class="mt-1 text-xs font-semibold text-(--text)">${reportedByName}</p>
                    ${ticket.reporter_contact ? `<p class="mt-0.5 text-xs text-(--text-soft)">${escapeHtml(ticket.reporter_contact)}</p>` : ''}
                </div>
                <div>
                    <span class="block text-xs font-bold uppercase tracking-wider text-(--text-soft)">${translations().source || ''}</span>
                    <p class="mt-1 text-xs font-semibold text-(--text)">${ticket.source === 'qr' ? `<span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0z"/></svg>${translations().qrCode || ''}</span>` : `<span class="font-normal text-(--text-soft)">${translations().web || ''}</span>`}</p>
                </div>
            </div>
        </div>
    `;

    return {
        statusName,
        statusClean,
    };
}
