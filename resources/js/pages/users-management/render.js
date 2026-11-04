import { getPagination, getResultsCount, getUsersTableBody } from './dom.js';
import { getPaginationTranslations } from '../../components/listing/feedback.js';
import { escapeHtml } from '../../utils/escape.js';

function isUserActive(user) {
    return user.active === true || user.active === 1 || user.active === '1' || String(user.active).toLowerCase() === 'true';
}

function getUserRole(user) {
    const role = user.profile?.name || user.role || user.profile || '';
    const translations = window.SGM_USER_MANAGEMENT_I18N || {};

    return role === 'admin' ? (translations.admin || 'Administrator') : role === 'technician'
        ? (translations.technician || 'Technician') : role === 'user'
            ? (translations.user || 'User') : role;
}

function renderStatusBadge(user) {
    const translations = window.SGM_USER_MANAGEMENT_I18N || {};

    if (isUserActive(user)) {
        return `<span class="inline-flex items-center gap-1.5 rounded-lg bg-success/10 px-2 py-0.5 text-xs font-bold uppercase tracking-tight text-success"><span class="h-1.5 w-1.5 rounded-full bg-success"></span>${translations.active || 'Active'}</span>`;
    }

    return `<span class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--text-soft)]/10 px-2 py-0.5 text-xs font-bold uppercase tracking-tight text-[var(--text-soft)]"><span class="h-1.5 w-1.5 rounded-full bg-[var(--text-soft)]"></span>${translations.inactive || 'Inactive'}</span>`;
}


function renderUserRow(user) {
    const translations = window.SGM_USER_MANAGEMENT_I18N || {};

    return `<tr class="transition-colors duration-150 hover:bg-[var(--surface-2)]/50">
        <td class="px-6 py-4 font-mono font-bold text-[var(--text-soft)]" data-label="ID">#${user.id}</td>
        <td class="px-6 py-4 font-semibold text-[var(--text)]" data-label="Name">${escapeHtml(user.name)}</td>
        <td class="px-6 py-4 font-semibold text-[var(--text-soft)]" data-label="Email">${escapeHtml(user.email)}</td>
        <td class="px-6 py-4" data-label="Profile">
            <span class="rounded-lg border border-[var(--border)] bg-[var(--surface-2)] px-2 py-0.5 text-xs font-bold uppercase tracking-tight text-[var(--text)] shadow-sm">${escapeHtml(getUserRole(user))}</span>
        </td>
        <td class="px-6 py-4" data-label="Status">${renderStatusBadge(user)}</td>
        <td class="ui-listing-actions px-6 py-4 text-right whitespace-nowrap">
            <div class="inline-flex items-center justify-end gap-1.5">
                <a href="/ui/users/${user.id}" class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-semibold text-[var(--text)] shadow-sm transition-all hover:bg-[var(--surface-2)]">${translations.details || 'View details'}</a>
            </div>
        </td>
    </tr>`;
}

export function showFeedback(message, error = false) {
    const element = getResultsCount();
    if (!element) return;

    element.textContent = message;
    element.className = `text-xs font-semibold ${error ? 'text-danger' : 'text-[var(--text-soft)]'}`;
}

export function renderResultsCount(total) {
    const translations = window.SGM_TICKETS_I18N || {};
    showFeedback(total > 0 ? `${total} ${translations.resultsCount || ''}` : (translations.noResults || ''));
}

export function renderEmptyState() {
    const tbody = getUsersTableBody();
    const pagination = getPagination();

    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-xs italic text-[var(--text-soft)]">' + (window.SGM_USER_MANAGEMENT_I18N?.noUsersFound || 'No users found matching the selected filters.') + '</td></tr>';
    }

    if (pagination) pagination.innerHTML = '';
}

export function renderErrorState() {
    const tbody = getUsersTableBody();
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-xs font-medium text-danger">' + (window.SGM_UI_I18N?.loadError || 'Unable to load the data at the moment.') + '</td></tr>';
}

export function renderUsers(users) {
    const tbody = getUsersTableBody();
    if (!tbody) return;

    tbody.innerHTML = users.map(renderUserRow).join('');
}

export function renderPagination(meta, currentPage) {
    const pagination = getPagination();
    if (!pagination) return;

    const metaInfo = meta.meta ?? meta;
    const lastPage = metaInfo.last_page ?? 1;
    const page = metaInfo.current_page ?? currentPage;

    if (lastPage <= 1) {
        pagination.innerHTML = '';
        return;
    }

    const translations = getPaginationTranslations();
    pagination.innerHTML = `
        <button data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}
            class="ui-button ui-button--primary inline-flex min-h-[36px] items-center justify-center rounded-xl px-3.5 py-2 text-xs font-bold text-[var(--on-primary)] shadow-sm transition-all hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">← ${translations.previous}</button>
        <span class="font-bold text-[var(--text-soft)]">${translations.page} ${page} ${translations.of} ${lastPage}</span>
        <button data-page="${page + 1}" ${page >= lastPage ? 'disabled' : ''}
            class="ui-button ui-button--primary inline-flex min-h-[36px] items-center justify-center rounded-xl px-3.5 py-2 text-xs font-bold text-[var(--on-primary)] shadow-sm transition-all hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">${translations.next} →</button>
    `;
}
