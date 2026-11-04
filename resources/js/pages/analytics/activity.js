/**
 * Analytics Activity and Lists Renderer
 * Renders activity timeline and summary lists
 */

import { escapeHtml } from '../../utils/escape.js';

const translations = () => window.SGM_ANALYTICS_I18N || {};

export function renderActivity(element, data) {
    if (!element) return;
    const activities = data.recent_activity || [];
    if (activities.length === 0) {
        element.innerHTML = `
            <div class="p-6 text-center text-xs text-(--text-soft)">${escapeHtml(translations().noActivity || '')}</div>
        `;
        return;
    }
    element.innerHTML = activities.map(act => `
        <div class="flex items-start gap-5 p-6 hover:bg-(--surface-2)/30 transition-colors duration-150">
            <div class="mt-1 flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold text-sm text-(--text)">${escapeHtml(act.title)}</h3>
                    <span class="text-xs text-(--text-soft)">${escapeHtml(act.time)}</span>
                </div>
                <p class="mt-1 text-xs text-(--text-soft)">${escapeHtml(act.description)}</p>
            </div>
        </div>
    `).join("");
}

export function renderList(element, items) {
    if (!element) return;
    if (!items || items.length === 0) {
        element.innerHTML = `<div class="p-5 text-center text-xs text-(--text-soft)">${escapeHtml(translations().noData || '')}</div>`;
        return;
    }
    element.innerHTML = items.map((item, idx) => `
        <div class="flex items-center justify-between p-4 hover:bg-(--surface-2)/30 transition-colors duration-150">
            <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-(--surface-2) text-xs font-bold text-(--text-soft)">${idx + 1}</span>
                <span class="text-xs font-semibold text-(--text)">${escapeHtml(item.name)}</span>
            </div>
            <span class="text-xs font-bold text-primary">${item.total} <span class="font-medium text-(--text-soft)">${escapeHtml(item.subtitle)}</span></span>
        </div>
    `).join("");
}

export function renderSummary(elements, data) {
    renderList(elements.topEquipments, data.top_equipments);
    renderList(elements.topRooms, data.top_rooms);
    renderList(elements.topTechnicians, data.top_technicians);
}
