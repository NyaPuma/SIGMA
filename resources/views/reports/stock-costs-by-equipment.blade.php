<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ __('equipment.Custo de Peças por Equipamento') }}</title>
    <style>
        @page { size: A4; margin: 36px 34px 48px; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            font-size: 11px; color: #0f172a; margin: 0; padding: 0; line-height: 1.45;
        }

        .brand-bar {
            height: 6px;
            background: linear-gradient(90deg, #ea580c 0%, #ea580c 40%, #14213d 100%);
            border-radius: 3px 3px 0 0;
            margin-bottom: 22px;
        }
        .report-header { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .report-header td { padding: 0; border: none; vertical-align: middle; }
        .report-eyebrow {
            font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em;
            color: #ea580c; margin-bottom: 4px;
        }
        .report-title { font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: #14213d; margin: 0; }
        .report-subtitle { font-size: 10px; color: #64748b; margin-top: 4px; }
        .report-meta { text-align: right; font-size: 9px; color: #475569; line-height: 1.8; }
        .report-meta strong { color: #14213d; }

        table.data-table { width: 100%; border-collapse: collapse; }
        table.data-table thead th {
            background: #14213d; color: #ffffff;
            padding: 9px 12px; font-size: 9px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em; text-align: left;
            border-bottom: 2px solid #ea580c;
        }
        table.data-table tbody td {
            padding: 9px 12px; border-bottom: 1px solid #e2e8f0; font-size: 10.5px;
        }
        table.data-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right !important; }
        .col-rank { width: 40px; }
        .equipment-name { font-weight: 600; color: #0f172a; }
        .font-mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 10px; color: #475569; }

        .rank {
            display: inline-block; min-width: 20px; text-align: center;
            padding: 2px 6px; border-radius: 6px; font-size: 9px; font-weight: 700;
        }
        .rank-1 { background: #ea580c; color: #ffffff; }
        .rank-2 { background: #14213d; color: #ffffff; }
        .rank-3 { background: #64748b; color: #ffffff; }
        .rank-rest { background: #e2e8f0; color: #475569; }

        .empty-state {
            padding: 40px 20px; text-align: center; color: #94a3b8;
            border: 1px dashed #cbd5e1; border-radius: 10px; font-size: 11px;
        }

        .footer-total {
            width: 100%; margin-top: 24px; border-collapse: collapse;
        }
        .footer-total td {
            padding: 14px 16px; background: #14213d; color: #ffffff;
        }
        .footer-total td:first-child { border-radius: 8px 0 0 8px; }
        .footer-total td:last-child { border-radius: 0 8px 8px 0; text-align: right; }
        .footer-total .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.8; }
        .footer-total .value { font-size: 18px; font-weight: 700; }

        .page-footer {
            position: fixed; bottom: -36px; left: 34px; right: 34px;
            border-top: 1px solid #e2e8f0; padding-top: 6px;
            font-size: 8px; color: #94a3b8;
        }
        .page-footer table { width: 100%; border-collapse: collapse; }
        .page-footer td { padding: 0; border: none; vertical-align: middle; }
        .page-footer .brand { color: #14213d; font-weight: 700; letter-spacing: 0.05em; text-align: left; }
        .page-footer .page-num-cell { text-align: right; }
        .page-footer .page-num::after { content: counter(page); }
    </style>
</head>
<body>

    <div class="brand-bar"></div>

    <table class="report-header">
        <tr>
            <td>
                <div class="report-eyebrow">{{ __('stock.Relatório de Stock') }}</div>
                <h1 class="report-title">{{ __('equipment.Custo de Peças por Equipamento') }}</h1>
                <div class="report-subtitle">
                    {{ __('stock.Consumo de peças e respetivo valor nas intervenções de cada equipamento.') }}
                </div>
            </td>
            <td class="report-meta">
                <div><strong>{{ __('common.Período:') }}</strong>
                    @if($from)
                        {{ app(\App\Services\LocalizationService::class)->formatDate($from) }} {{ __('common.até') }} {{ $to ? app(\App\Services\LocalizationService::class)->formatDate($to) : app(\App\Services\LocalizationService::class)->formatDate(now()) }}
                    @else
                        {{ __('common.Histórico completo') }}
                    @endif
                </div>
                <div><strong>{{ __('common.Emitido em:') }}</strong> {{ app(\App\Services\LocalizationService::class)->formatDateTime(now()) }}</div>
                <div><strong>{{ __('common.Equipamentos:') }}</strong> {{ $items->count() }}</div>
            </td>
        </tr>
    </table>

    @if($items->isEmpty())
        <div class="empty-state">
            {{ __('stock.Não existem saídas de peças associadas a equipamentos no período selecionado.') }}
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-rank">#</th>
                    <th>{{ __('equipment.Equipamento') }}</th>
                    <th class="right">{{ __('common.Qtd. consumida') }}</th>
                    <th class="right">{{ __('common.Custo') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $item)
                    <tr>
                        <td>
                            <span class="rank rank-{{ $i < 3 ? $i + 1 : 'rest' }}">{{ $i + 1 }}</span>
                        </td>
                        <td class="equipment-name">{{ $item['equipment_name'] ?? __('common.Sem equipamento') }}</td>
                        <td class="right font-mono">
                            {{ (int) $item['total_quantity'] }}
                        </td>
                        <td class="right font-mono">
                            {{ app(\App\Services\LocalizationService::class)->formatCurrency((float) $item['total_value']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="footer-total">
            <tr>
                <td><span class="label">{{ __('stock.Total de custos em peças') }}</span></td>
                <td><span class="value">{{ app(\App\Services\LocalizationService::class)->formatCurrency((float) $total) }}</span></td>
            </tr>
        </table>
    @endif

    <div class="page-footer">
        <table>
            <tr>
                <td class="brand">{{ config('app.name') }}</td>
                <td class="page-num-cell">{{ __('common.Página') }} <span class="page-num"></span></td>
            </tr>
        </table>
    </div>

</body>
</html>
