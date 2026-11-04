import { renderResultsCount as renderSharedResultsCount, renderResultsFeedback, renderSimplePagination, renderTableEmptyState, renderTableErrorState } from '../../components/listing/feedback.js';
import { getPagination, getResultsCount, getRoomsTableBody } from './dom.js';
import { escapeHtml } from '../../utils/escape.js';

function translations() {
    const data = document.body?.dataset || {};

    return {
        equipmentCount: window.SGM_ROOM_I18N?.equipmentCount || data.roomEquipmentCount || '',
        details: window.SGM_ROOM_I18N?.details || data.roomDetails || '',
        empty: window.SGM_ROOM_I18N?.empty || data.roomEmpty || '',
        roomName: window.SGM_ROOM_I18N?.roomName || '',
        location: window.SGM_ROOM_I18N?.location || '',
        equipment: window.SGM_ROOM_I18N?.equipment || '',
    };
}

function renderRoomRow(room) {
    const text = translations();
    const equipmentCount = room.equipments_count ?? room.equipments?.length ?? 0;
    const code = escapeHtml(room.code) || '—';
    const location = escapeHtml(room.location) || '—';

    return `<tr class="transition-colors duration-150 hover:bg-(--surface-2)/50">
        <td class="px-5 py-4" data-label="${escapeHtml(text.roomName)}">
            <div class="ui-listing-value">
                <div class="font-semibold text-(--text)">${escapeHtml(room.name)}</div>
                <div class="mt-0.5 font-mono text-xs text-(--text-soft)">${code}</div>
            </div>
        </td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="${escapeHtml(text.location)}">${location}</td>
        <td class="px-5 py-4" data-label="${escapeHtml(text.equipment)}">
            <span class="inline-flex items-center gap-1 rounded-lg bg-info/10 px-2.5 py-1 text-xs font-bold uppercase tracking-tight text-info">${equipmentCount} ${text.equipmentCount || ''}</span>
        </td>
        <td class="ui-listing-actions px-5 py-4 text-right">
            <div class="inline-flex items-center justify-end gap-1.5">
                <a href="/ui/rooms/${room.id}" class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-(--border) bg-(--surface) px-3 py-1.5 text-xs font-semibold text-(--text) shadow-sm transition-all hover:bg-(--surface-2)">${translations().details || ''}</a>
            </div>
        </td>
    </tr>`;
}

export function showFeedback(message, error = false) {
    renderResultsFeedback(getResultsCount(), message, error);
}

export function renderResultsCount(total) {
    renderSharedResultsCount(getResultsCount(), total);
}

export function renderEmptyState() {
    renderTableEmptyState({
        tbody: getRoomsTableBody(),
        colspan: 4,
        message: translations().empty || '',
    });

    const pagination = getPagination();
    if (pagination) pagination.innerHTML = '';
}

export function renderErrorState(message) {
    renderTableErrorState({
        tbody: getRoomsTableBody(),
        colspan: 4,
        message,
    });
}

export function renderRooms(rooms) {
    const tbody = getRoomsTableBody();
    if (!tbody) return;

    tbody.innerHTML = rooms.map(renderRoomRow).join('');
}

export function renderPagination(meta, currentPage) {
    renderSimplePagination({
        element: getPagination(),
        meta,
        currentPage,
    });
}
