/**
 * Notifications Modal — JS controller.
 *
 * Manages open/close of the notifications modal with backdrop blur.
 * Follows the same pattern as the localization modal.
 */

import { escapeHtml } from '../utils/escape.js';

const translations = () => window.SGM_UI_I18N || {};

let modal, overlay, container;
let triggerEl = null;

const ICON = (path, className = 'h-4 w-4 shrink-0') =>
    `<svg class="${className}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="${path}"/></svg>`;

const ICONS = {
    checkSuccess: ICON('M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'h-4 w-4 shrink-0 text-success'),
    xDanger: ICON('M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'h-4 w-4 shrink-0 text-danger'),
    money: ICON('M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z', 'h-4 w-4 shrink-0 text-primary'),
    check: ICON('M4.5 12.75l6 6 9-13.5', 'h-4 w-4 shrink-0 text-success'),
    wrench: ICON('M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z', 'h-4 w-4 shrink-0 text-primary'),
    clipboard: ICON('M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z', 'h-4 w-4 shrink-0 text-[var(--text-soft)]'),
    warning: ICON('M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z', 'h-4 w-4 shrink-0 text-warning'),
    bell: ICON('M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0', 'h-4 w-4 shrink-0 text-[var(--text-soft)]'),
};

function getFocusable() {
    if (!container) return [];
    return [...container.querySelectorAll(
        'button:not([disabled]):not([hidden]), input:not([disabled]):not([hidden]), a[href], [tabindex]:not([tabindex="-1"])'
    )].filter(el => el.offsetParent !== null);
}

function openModal(trigger) {
    if (!modal) return;
    triggerEl = trigger || document.activeElement;

    modal.removeAttribute('hidden');
    void modal.offsetHeight;
    modal.classList.add('locale-modal--open');
    modal.classList.remove('locale-modal--closing');
    document.body.style.overflow = 'hidden';

    fetchNotifications();

    setTimeout(() => {
        const first = container.querySelector('a, button');
        if (first) first.focus();
    }, 80);
}

function closeModal() {
    if (!modal || !modal.classList.contains('locale-modal--open')) return;

    modal.classList.add('locale-modal--closing');
    modal.classList.remove('locale-modal--open');

    setTimeout(() => {
        modal.setAttribute('hidden', '');
        modal.classList.remove('locale-modal--closing');
        document.body.style.overflow = '';

        if (triggerEl && typeof triggerEl.focus === 'function') {
            triggerEl.focus();
        }
    }, 260);
}

async function fetchNotifications() {
    const list = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');
    const countLabel = document.getElementById('notifCountLabel');
    if (!list) return;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('/notifications', {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!res.ok) throw new Error('Failed');

        const data = await res.json();
        const notifications = toArray(data.notifications || data.data || data);

        if (!notifications.length) {
            renderEmpty(list, badge, countLabel);
            return;
        }

        const unreadCount = notifications.filter(n => !n.is_read && !n.read_at).length;

        if (badge) {
            if (unreadCount > 0) {
                badge.innerText = unreadCount > 99 ? '99+' : unreadCount;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
        if (countLabel) {
            countLabel.innerText = unreadCount + ' ' + (translations().unreadLabel || 'não lidas');
        }

        const items = notifications.slice(0, 30);
        list.innerHTML = items.map(renderItem).join('') + (notifications.length > 30 ? `
            <div class="text-center pt-2">
                <span class="text-xs font-medium text-(--text-muted)">+${notifications.length - 30} ${escapeHtml(translations().moreLabel || 'mais')}</span>
            </div>
        ` : '');

        list.querySelectorAll('[data-notif-link]').forEach(item => {
            item.addEventListener('click', () => {
                const link = item.dataset.notifLink;
                const id = Number(item.dataset.notifId);
                if (id) markNotifRead(id);
                if (link) {
                    closeModal();
                    window.location.href = link;
                }
            });
        });
    } catch (e) {
        console.warn('Error loading notifications:', e);
        if (list) {
            list.innerHTML = `
                <p class="text-xs text-center py-6 italic text-(--text-muted)">${escapeHtml(translations().loadListError || 'Erro ao carregar')}</p>
            `;
        }
    }
}

function toArray(value) {
    if (Array.isArray(value)) return value;
    if (value && Array.isArray(value.data)) return value.data;
    if (value && Array.isArray(value.notifications)) return value.notifications;
    return [];
}

function renderEmpty(list, badge, countLabel) {
    list.innerHTML = `
        <p class="text-xs text-center py-6 italic text-(--text-muted)">
            ${escapeHtml(translations().noNotifications || 'Sem notificações')}
        </p>
    `;
    if (badge) badge.classList.add('hidden');
    if (countLabel) countLabel.innerText = '0';
}

function notificationIcon(type) {
    if (type?.includes('approved')) return ICONS.checkSuccess;
    if (type?.includes('rejected')) return ICONS.xDanger;
    if (type?.includes('budget_request')) return ICONS.money;
    if (type?.includes('auto_approved')) return ICONS.check;
    if (type?.includes('closed')) return ICONS.wrench;
    if (type?.includes('budget_submitted')) return ICONS.clipboard;
    if (type?.includes('priority_override')) return ICONS.warning;
    return ICONS.bell;
}

function renderItem(n) {
    const isUnread = !n.is_read && !n.read_at;
    const icon = notificationIcon(n.type);
    return `
        <div class="notifications-modal__item ${isUnread ? 'notifications-modal__item--unread' : ''} ${n.link ? 'cursor-pointer' : ''}"
             ${n.link ? `data-notif-link="${escapeHtml(n.link)}" data-notif-id="${n.id}"` : ''}>
            <span class="text-base flex-shrink-0 mt-0.5 text-(--text-soft)">${icon}</span>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-(--text) leading-tight">${escapeHtml(n.title)}</p>
                <p class="text-xs mt-0.5 line-clamp-2 text-(--text-soft)">${escapeHtml(n.message || n.description)}</p>
                <p class="text-xs mt-1 text-(--text-muted)">${escapeHtml(n.created_at)}</p>
            </div>
            ${isUnread ? '<span class="w-2 h-2 rounded-full bg-primary flex-shrink-0 mt-1.5"></span>' : ''}
        </div>
    `;
}

async function markNotifRead(id) {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/notifications/' + id, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        fetchNotifications();
    } catch (e) {}
}

function handleKeydown(e) {
    if (!modal || !modal.classList.contains('locale-modal--open')) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        closeModal();
        return;
    }

    if (e.key === 'Tab') {
        const focusable = getFocusable();
        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
}

export function initNotificationsModal() {
    modal = document.getElementById('notificationsModal');
    if (!modal) return;

    overlay = modal.querySelector('.locale-modal__overlay');
    container = modal.querySelector('.locale-modal__container');

    modal.querySelectorAll('[data-close-notifications-modal]').forEach(el => {
        el.addEventListener('click', closeModal);
    });

    document.querySelectorAll('[data-action="open-notifications-modal"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(btn);
        });
    });

    document.addEventListener('keydown', handleKeydown);
}

export function startNotificationPolling() {
    setInterval(() => {
        if (modal && modal.classList.contains('locale-modal--open')) {
            fetchNotifications();
        }
    }, 30000);
}
