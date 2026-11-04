/**
 * Analytics KPI Renderer
 * Renders KPI cards for the analytics dashboard
 */

import { formatNumber, formatPercent } from '../../utils/locale.js';
import { formatMinutes as formatMinutesWith } from '../../utils/duration.js';

function getTranslations() {
    const configured = window.SGM_ANALYTICS_I18N || {};
    const body = document.body?.dataset || {};
    const value = (key, fallback) => configured[key] || body[`analytics${key.charAt(0).toUpperCase()}${key.slice(1)}`] || fallback;

    return {
        resolution: value('resolution', 'Average Resolution Time'),
        waiting: value('waiting', 'Average Waiting Time'),
        open: value('open', 'Open Tickets'),
        resolved: value('resolved', 'Resolved Tickets'),
        mttr: value('mttr', 'MTTR'),
        assignment: value('assignment', 'Time to assignment'),
        active: value('active', 'Active incidents'),
        completed: value('completed', 'Completed interventions'),
        minutes: value('minutes', 'min'),
        hours: value('hours', 'h'),
        days: value('days', 'd'),
    };
}

function formatMinutes(minutes) {
    return formatMinutesWith(minutes, getTranslations());
}

const KPI_ICON = (path) => `<svg class="h-7 w-7 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="${path}"/></svg>`;

export function renderKPIs(element, data) {
    if (!element) return;

    const translations = getTranslations();
    const cards = [
        { title: translations.resolution || 'Average Resolution Time', value: formatMinutes(data.average_resolution_minutes), icon: KPI_ICON('M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z'), color: "indigo", subtitle: translations.mttr || "MTTR" },
        { title: translations.waiting || 'Average Waiting Time', value: formatMinutes(data.average_waiting_minutes), icon: KPI_ICON('M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'), color: "blue", subtitle: translations.assignment || "Time to assignment" },
        { title: translations.open || 'Open Tickets', value: formatNumber(data.open_tickets || 0), icon: KPI_ICON('M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z'), color: "amber", subtitle: translations.active || "Active incidents" },
        { title: translations.resolved || 'Resolved Tickets', value: formatNumber(data.closed_tickets || 0), icon: KPI_ICON('M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'), color: "emerald", subtitle: translations.completed || "Completed interventions" }
    ];

    element.innerHTML = cards.map(card => {
        let colorClasses = '';
        if (card.color === 'indigo') {
            colorClasses = 'bg-primary/10 text-primary border-primary/20';
        } else if (card.color === 'blue') {
            colorClasses = 'bg-info/10 text-info border-info/20';
        } else if (card.color === 'amber') {
            colorClasses = 'bg-warning/10 text-warning border-warning/20';
        } else {
            colorClasses = 'bg-success/10 text-success border-success/20';
        }

        return `
            <article class="group overflow-hidden rounded-3xl border border-(--border) bg-(--surface) transition-all duration-350 hover:-translate-y-1 hover:shadow-xl p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-(--text-soft)">${card.title}</p>
                        <h3 class="mt-5 text-4xl font-black tracking-tight text-(--text)">${card.value}</h3>
                    </div>
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl ${colorClasses.split(' ').slice(0,2).join(' ')}">
                        ${card.icon}
                    </div>
                </div>
                <div class="mt-8">
                    <span class="rounded-full px-3 py-1.5 text-xs font-bold ${colorClasses}">
                        ${card.subtitle}
                    </span>
                </div>
            </article>
        `;
    }).join("");
}

export function renderOperationalMetrics(elements, data) {
    if (elements.metricMttr) {
        elements.metricMttr.textContent = formatMinutes(data.average_resolution_minutes);
    }
    if (elements.metricWaiting) {
        elements.metricWaiting.textContent = formatMinutes(data.average_waiting_minutes);
    }
    if (elements.metricSla) {
        elements.metricSla.textContent = formatPercent(data.sla_success);
    }
    if (elements.metricAvailability) {
        elements.metricAvailability.textContent = formatPercent(data.system_availability);
    }
}
