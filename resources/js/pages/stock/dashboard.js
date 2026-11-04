import { authHeader } from '../../utils/api.js';
import { formatCurrency } from '../../utils/locale.js';
import { escapeHtml } from '../../utils/escape.js';

async function fetchJson(url) {
    const response = await fetch(url, { headers: authHeader() });

    if (response.status === 401) {
        window.location = '/ui/login';
        return null;
    }

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || translations().loadError);
    }

    return response.json().catch(() => ({}));
}


function formatEur(value) {
    return formatCurrency(value);
}

function translations() {
    const body = document.body?.dataset || {};
    const configured = window.SGM_STOCK_DASHBOARD_I18N || {};

    return {
        currentStock: configured.currentStock || body.stockDashboardCurrentStock || 'Current stock',
        minimumStock: configured.minimumStock || body.stockDashboardMinimumStock || 'Minimum stock',
        inStock: configured.inStock || body.stockDashboardInStock || 'in stock',
        month: configured.month || body.stockDashboardMonth || 'month',
        months: configured.months || body.stockDashboardMonths || 'months',
        consumption: configured.consumption || body.stockDashboardConsumption || 'consumption',
        noLowStock: configured.noLowStock || body.stockDashboardNoLowStock || 'No parts on low stock alert.',
        noConsumption: configured.noConsumption || body.stockDashboardNoConsumption || 'No consumption recorded.',
        noRunout: configured.noRunout || body.stockDashboardNoRunout || 'No stock-out forecasts in the period.',
        loadError: configured.loadError || body.stockDashboardLoadError || 'Unable to load the data at the moment.',
    };
}

function renderAlertRow(part) {
    const stock = Number(part.current_stock) || 0;
    const min = Number(part.min_stock) || 0;

    return `<a href="/ui/stock/parts/${part.id}" class="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-(--surface-2)">
        <div class="min-w-0">
            <p class="truncate text-xs font-bold text-(--text)">${escapeHtml(part.name)}</p>
            <p class="mt-0.5 font-mono text-xs text-(--text-soft)">${escapeHtml(part.sku ?? '')} · ${escapeHtml(part.category?.name ?? '—')}</p>
        </div>
        <div class="shrink-0 text-right">
            <p class="text-xs font-black ${stock <= 0 ? 'text-danger' : 'text-warning'}">${stock} / ${min}</p>
            <p class="text-xs text-(--text-soft)">${translations().currentStock} / ${translations().minimumStock}</p>
        </div>
    </a>`;
}

function renderEmpty(message) {
    return `<p class="px-5 py-8 text-center text-xs text-(--text-soft)">${message}</p>`;
}

async function loadSummary() {
    const data = await fetchJson('/stock/dashboard/summary');
    if (!data) return;

    const totalValue = document.getElementById('metricTotalValue');
    const totalParts = document.getElementById('metricTotalParts');
    const lowStock = document.getElementById('metricLowStock');

    if (totalValue) totalValue.textContent = formatEur(data.total_stock_value);
    if (totalParts) totalParts.textContent = data.total_parts ?? '—';
    if (lowStock) {
        lowStock.textContent = data.low_stock_count ?? '—';
        lowStock.className = 'mt-2 text-2xl font-black ' + ((Number(data.low_stock_count) || 0) > 0 ? 'text-warning' : 'text-(--text)');
    }

    const lowStockList = document.getElementById('lowStockList');
    if (lowStockList) {
        const parts = data.parts_in_alert ?? [];
        lowStockList.innerHTML = parts.length
            ? parts.map(renderAlertRow).join('')
            : renderEmpty(translations().noLowStock);
    }
}

async function loadTopConsumed() {
    const list = document.getElementById('topConsumedList');
    if (!list) return;

    try {
        const data = await fetchJson('/stock/dashboard/top-consumed');
        if (!data) return;

        const items = data.items ?? [];
        list.innerHTML = items.length
            ? items.map((item) => `
                <a href="/ui/stock/parts/${item.part_id}" class="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-(--surface-2)">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-bold text-(--text)">${escapeHtml(item.part_name)}</p>
                        <p class="mt-0.5 font-mono text-xs text-(--text-soft)">${escapeHtml(item.sku ?? '')}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-xs font-black text-(--text)">${item.total_quantity}</p>
                        <p class="text-xs text-(--text-soft)">${formatEur(item.total_value)}</p>
                    </div>
                </a>`).join('')
            : renderEmpty(translations().noConsumption);
    } catch (e) {
        list.innerHTML = renderEmpty(e.message);
    }
}

async function loadRunout() {
    const list = document.getElementById('runoutList');
    if (!list) return;

    try {
        const data = await fetchJson('/stock/dashboard/runout-forecast');
        if (!data) return;

        const items = data.items ?? [];
        list.innerHTML = items.length
            ? items.slice(0, 10).map((item) => `
                <a href="/ui/stock/parts/${item.part_id}" class="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-(--surface-2)">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-bold text-(--text)">${escapeHtml(item.part_name)}</p>
                        <p class="mt-0.5 font-mono text-xs text-(--text-soft)">${escapeHtml(item.sku ?? '')} · ${item.current_stock} ${translations().inStock}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-xs font-black ${item.est_months_of_stock < 1 ? 'text-danger' : 'text-warning'}">${item.est_months_of_stock} ${Number(item.est_months_of_stock) === 1 ? translations().month : translations().months}</p>
                        <p class="text-xs text-(--text-soft)">${translations().consumption} ${item.avg_monthly_usage}/${translations().month}</p>
                    </div>
                </a>`).join('')
            : renderEmpty(translations().noRunout);
    } catch (e) {
        list.innerHTML = renderEmpty(e.message);
    }
}

function init() {
    loadSummary().catch(() => {});
    loadTopConsumed();
    loadRunout();
}

export { init };
