import { authHeader } from '../utils/api.js';
import { formatMinutes } from '../utils/duration.js';
import { escapeHtml } from '../utils/escape.js';

const i18n = () => window.SGM_DASHBOARD_I18N || {};

async function fetchAnalyticsStats() {
    const endpoints = ['/api/analytics/stats', '/analytics', '/analytics/stats'];
    for (const ep of endpoints) {
        try {
            const res = await fetch(ep, { headers: authHeader() });
            if (res.ok) {
                return await res.json();
            }
        } catch (e) {}
    }
    throw new Error('Failed to communicate analytical data');
}

async function loadMetrics() {
    const userRole = document.querySelector('meta[name="user-role"]')?.content || '';
    const panel = document.getElementById('metricsPanel');

    if (!panel) {
        loadRecentTickets();
        return;
    }

    if (userRole !== 'admin') {
        panel.innerHTML = `
            <div class="rounded-xl border border-dashed border-(--border) bg-(--surface-2) p-5 col-span-full text-center">
                <p class="text-xs text-(--text-soft)">${i18n().metricsAdminOnly || 'Metrics available to Administrators only.'}</p>
            </div>
        `;
        loadRecentTickets();
        return;
    }

    panel.innerHTML = `
        <div class="col-span-full text-xs text-(--text-soft) animate-pulse" aria-live="polite">
            ${i18n().loadingMetrics || 'Reading real-time analytics indicators...'}
        </div>
    `;

    try {
        const data = await fetchAnalyticsStats();

        panel.innerHTML = `
            <div class="rounded-2xl border border-(--border) bg-(--surface) p-6 shadow-[0_1px_2px_rgba(0,0,0,0.01)] animate-[fadeIn_0.3s_ease-out] flex flex-col justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-(--text-soft)">${i18n().resolution || 'Average Resolution Time'}</p>
                <div class="mt-2 text-2xl font-black text-(--text)">${formatMinutes(data.average_resolution_minutes, i18n())}</div>
                <p class="mt-0.5 text-xs font-semibold text-(--text-soft)">${data.average_resolution_minutes ?? 0} ${i18n().minutes || 'min'}</p>
            </div>
            <div class="rounded-2xl border border-(--border) bg-(--surface) p-6 shadow-[0_1px_2px_rgba(0,0,0,0.01)] animate-[fadeIn_0.3s_ease-out] flex flex-col justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-(--text-soft)">${i18n().waiting || 'Average Waiting Time'}</p>
                <div class="mt-2 text-2xl font-black text-(--text)">${formatMinutes(data.average_waiting_minutes, i18n())}</div>
                <p class="mt-0.5 text-xs font-semibold text-(--text-soft)">${data.average_waiting_minutes ?? 0} ${i18n().minutes || 'min'}</p>
            </div>
            <div class="rounded-2xl border border-(--border) bg-(--surface) p-6 shadow-[0_1px_2px_rgba(0,0,0,0.01)] animate-[fadeIn_0.3s_ease-out] flex flex-col justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-(--text-soft)">${i18n().open || 'Open Tickets'}</p>
                <div class="mt-2 text-3xl font-black text-warning">${data.open_tickets ?? 0}</div>
            </div>
            <div class="rounded-2xl border border-(--border) bg-(--surface) p-6 shadow-[0_1px_2px_rgba(0,0,0,0.01)] animate-[fadeIn_0.3s_ease-out] flex flex-col justify-between">
                <p class="text-xs font-bold uppercase tracking-wider text-(--text-soft)">${i18n().closed || 'Closed Tickets'}</p>
                <div class="mt-2 text-3xl font-black text-success">${data.closed_tickets ?? 0}</div>
            </div>
        `;
    } catch (err) {
        panel.innerHTML = `
            <div class="rounded-xl border border-danger/20 bg-danger/5 p-4 col-span-full text-xs text-danger">
                ${i18n().loadError || 'Unable to load the analytical indicators from the server.'}
            </div>
        `;
    }

    loadRecentTickets();
}

async function loadRecentTickets() {
    const tableContainer = document.getElementById('recentTicketsTable');
    if (!tableContainer) return;

    const endpoints = ['/dashboard/tickets-active', '/tickets?page=1', '/admin/tickets?page=1', '/api/tickets?page=1'];
    let tickets = [];

    for (const ep of endpoints) {
        try {
            const res = await fetch(ep, { headers: authHeader() });
            if (res.ok) {
                const data = await res.json();
                tickets = data.tickets?.data || data.tickets || data || [];
                if (tickets.length > 0) break;
            }
        } catch (e) {}
    }

    if (tickets.length === 0) {
        tableContainer.innerHTML = `<p class="text-xs text-(--text-soft) py-2">${i18n().noRecent || 'No recent occurrences recorded.'}</p>`;
        return;
    }

    tableContainer.innerHTML = `
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="text-(--text-soft) border-b border-(--border) text-xs uppercase tracking-wider">
                    <th class="pb-2">ID</th>
                    <th class="pb-2">${i18n().title || 'Title'}</th>
                    <th class="pb-2">${i18n().priority || 'Priority'}</th>
                    <th class="pb-2 text-right">${i18n().action || 'Action'}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-(--border)/50 text-(--text)">
                ${tickets.map(t => `
                    <tr>
                        <td class="py-2.5 font-mono text-(--text-soft)">#${t.id}</td>
                        <td class="py-2.5 font-semibold truncate max-w-[180px]">${escapeHtml(t.title)}</td>
                        <td class="py-2.5">
                            <span class="px-2 py-0.5 rounded text-xs font-bold uppercase ${
                                t.priority === 'alta' ? 'bg-danger/10 text-danger' :
                                t.priority === 'média' ? 'bg-warning/10 text-warning' : 'bg-success/10 text-success'
                            }">${escapeHtml(t.priority_label || t.priority || 'média')}</span>
                        </td>
                        <td class="py-2.5 text-right">
                            <a href="/ui/tickets/${t.id}" class="text-primary hover:underline font-bold">${i18n().view || 'View'}</a>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function init() {
    /*
     * The dashboard module is loaded dynamically (via bootPageModules) *after*
     * `DOMContentLoaded` has already fired, so listening to that event here
     * would never run. The registry only calls `init()` once the DOM is ready.
     */
    loadMetrics();
    loadTechnicalPicket();
}

async function loadTechnicalPicket() {
    const container = document.getElementById('picketList');
    if (!container) return;

    try {
        const res = await fetch('/dashboard/picket', { headers: authHeader() });
        if (!res.ok) throw new Error();
        const data = await res.json();
        const picket = Array.isArray(data.picket) ? data.picket : [];
        const label = i18n().inProgress || 'em curso';

        if (picket.length === 0) {
            container.innerHTML = `<p class="text-xs text-(--text-soft) py-2">${i18n().noRecent || 'No active technicians.'}</p>`;
            return;
        }

        container.innerHTML = picket.map(t => {
            const count = Number(t.in_progress_tickets) || 0;
            const active = count > 0;
            return `
                <div class="flex items-center justify-between text-xs py-1.5 ${active ? 'border-b border-(--border)/50' : ''}">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full ${active ? 'bg-success' : 'bg-[var(--border)]'}"></span>
                        <span class="font-semibold ${active ? 'text-(--text)' : 'text-(--text-soft)'}">${escapeHtml(t.name)}</span>
                    </div>
                    ${active
                        ? `<span class="text-xs font-bold ${count >= 2 ? 'text-warning bg-warning/10' : 'text-success bg-success/10'} px-2 py-0.5 rounded-full">${count} ${label}</span>`
                        : `<span class="text-xs font-semibold text-(--text-soft)">${i18n().idle || 'Off-line'}</span>`}
                </div>
            `;
        }).join('');
    } catch (e) {
        container.innerHTML = `<p class="text-xs text-(--text-soft) py-2">${i18n().loadError || 'Unable to load the technical picket.'}</p>`;
    }
}

export { init };
