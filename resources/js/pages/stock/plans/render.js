import { renderResultsCount as renderSharedResultsCount, renderResultsFeedback, renderSimplePagination, renderTableEmptyState, renderTableErrorState } from '../../../components/listing/feedback.js';
import { getPlanTableBody, getPagination, getResultsCount } from './dom.js';
import { escapeHtml } from '../../../utils/escape.js';

function translations() {
    const data = document.body?.dataset || {};
    const configured = window.SGM_MAINTENANCE_PLAN_I18N || {};

    return {
        days: configured.days || data.planDays || '',
        usageHours: configured.usageHours || data.planUsageHours || '',
        cycles: configured.cycles || data.planCycles || '',
        active: configured.active || data.planActive || '',
        inactive: configured.inactive || data.planInactive || '',
        parts: configured.parts || data.planParts || '',
        edit: configured.edit || data.planEdit || '',
        delete: configured.delete || data.planDelete || '',
        empty: configured.empty || data.planEmpty || '',
    };
}


function renderIntervalLabel(plan) {
    const text = translations();
    const labels = { days: text.days, usage_hours: text.usageHours, cycles: text.cycles };
    const label = labels[plan.interval_type] ?? plan.interval_type_label ?? plan.interval_type;

    return `${plan.interval_value} ${label}`;
}

function renderPlanRow(plan) {
    const partsCount = Array.isArray(plan.parts) ? plan.parts.length : 0;

    return `<tr class="transition-colors duration-150 hover:bg-(--surface-2)/50">
        <td class="px-5 py-4" data-label="Plan">
            <div class="ui-listing-value">
                <div class="font-semibold text-(--text)">${escapeHtml(plan.name)}</div>
                <div class="mt-0.5 text-xs uppercase tracking-wider text-(--text-soft)">${plan.active ? translations().active : translations().inactive}</div>
            </div>
        </td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Equipment">${escapeHtml(plan.equipment?.name ?? `#${plan.equipment_id}`)}</td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Frequency">${escapeHtml(renderIntervalLabel(plan))}</td>
        <td class="px-5 py-4 font-semibold text-(--text-soft)" data-label="Parts">${partsCount} ${translations().parts}</td>
        <td class="ui-listing-actions px-5 py-4 text-right">
            <div class="inline-flex items-center justify-end gap-1.5">
                <button type="button" data-plan-edit="${plan.id}" class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-(--border) bg-(--surface) px-3 py-1.5 text-xs font-semibold text-(--text) shadow-sm transition-all hover:bg-(--surface-2)">${translations().edit}</button>
                <button type="button" data-plan-delete="${plan.id}" class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-danger/25 bg-danger/10 px-3 py-1.5 text-xs font-semibold text-danger shadow-sm transition-all hover:bg-danger/20">${translations().delete}</button>
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
        tbody: getPlanTableBody(),
        colspan: 5,
        message: translations().empty,
    });

    const pagination = getPagination();
    if (pagination) pagination.innerHTML = '';
}

export function renderErrorState(message) {
    renderTableErrorState({
        tbody: getPlanTableBody(),
        colspan: 5,
        message,
    });
}

export function renderPlans(plans) {
    const tbody = getPlanTableBody();
    if (!tbody) return;

    tbody.innerHTML = plans.map(renderPlanRow).join('');
}

export function renderPagination(pagination, currentPage) {
    renderSimplePagination({
        element: getPagination(),
        meta: pagination ?? {},
        currentPage,
    });
}
