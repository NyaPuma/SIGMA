import { escapeHtml } from '../../utils/escape.js';

export function renderResultsFeedback(element, message, error = false) {
    if (!element) return;

    element.textContent = message;
    element.className = `text-xs font-semibold ${error ? 'text-danger' : 'text-(--text-soft)'}`;
}

export function renderResultsCount(element, total) {
    const translations = window.SGM_TICKETS_I18N || {};
    renderResultsFeedback(
        element,
        total > 0 ? `${total} ${translations.resultsCount || ''}` : (translations.noResults || '')
    );
}

export function renderTableEmptyState({ tbody, colspan, message }) {
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-5 py-12 text-center text-xs text-(--text-soft)"><div class="mx-auto max-w-sm rounded-2xl border border-dashed border-(--border) bg-(--surface-2) p-5">${escapeHtml(message)}</div></td></tr>`;
}

export function renderTableErrorState({ tbody, colspan, message }) {
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="${colspan}" class="px-5 py-12 text-center text-xs font-medium text-danger">${escapeHtml(message)}</td></tr>`;
}

export function getPaginationTranslations() {
    const data = document.body?.dataset || {};
    const configured = window.SGM_PAGINATION_I18N || {};

    return {
        previous: configured.previous || data.paginationPrevious || '',
        next: configured.next || data.paginationNext || '',
        page: configured.page || data.paginationPage || '',
        of: configured.of || data.paginationOf || '',
    };
}

export function renderSimplePagination({ element, meta, currentPage }) {
    if (!element) return;

    const metaInfo = meta.meta ?? meta;
    const lastPage = metaInfo.last_page ?? 1;
    const page = metaInfo.current_page ?? currentPage;

    if (lastPage <= 1) {
        element.innerHTML = '';
        return;
    }

    const translations = getPaginationTranslations();

    element.innerHTML = `
        <button data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}
            class="ui-button ui-button--primary inline-flex min-h-[36px] items-center justify-center rounded-xl px-3.5 py-2 text-xs font-bold text-(--on-primary) shadow-sm transition-all hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">← ${translations.previous}</button>
        <span class="font-bold text-(--text-soft)">${translations.page} ${page} ${translations.of} ${lastPage}</span>
        <button data-page="${page + 1}" ${page >= lastPage ? 'disabled' : ''}
            class="ui-button ui-button--primary inline-flex min-h-[36px] items-center justify-center rounded-xl px-3.5 py-2 text-xs font-bold text-(--on-primary) shadow-sm transition-all hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">${translations.next} →</button>
    `;
}
