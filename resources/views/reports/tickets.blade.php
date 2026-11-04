<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ __('tickets.Relatório Consolidado de Tickets') }}</title>
    <style>
        @page { size: A4 landscape; margin: 36px 32px 48px; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            color: #0f172a;
            line-height: 1.45;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }

        /* ---- Brand / header ---- */
        .brand-bar {
            height: 6px;
            background: linear-gradient(90deg, #ea580c 0%, #ea580c 40%, #14213d 100%);
            border-radius: 3px 3px 0 0;
            margin-bottom: 20px;
        }
        .report-header { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .report-header td { padding: 0; border: none; vertical-align: middle; }
        .report-eyebrow {
            font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em;
            color: #ea580c; margin-bottom: 4px;
        }
        .report-title { font-size: 21px; font-weight: 700; letter-spacing: -0.02em; color: #14213d; margin: 0; }
        .report-subtitle { font-size: 10px; color: #64748b; margin-top: 4px; }
        .report-meta {
            text-align: right; font-size: 9px; color: #475569; line-height: 1.8;
        }
        .report-meta strong { color: #14213d; }
        .report-warning { color: #b45309; font-weight: 600; }

        /* ---- Summary cards ---- */
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .summary td {
            width: 20%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px;
            text-align: center; background: #f8fafc;
        }
        .summary td + td { border-left: none; }
        .summary .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; }
        .summary .value { font-size: 16px; font-weight: 700; color: #14213d; margin-top: 3px; }

        /* ---- Table ---- */
        table.data-table { width: 100%; border-collapse: collapse; }
        table.data-table thead th {
            background: #14213d; color: #ffffff;
            padding: 8px 8px; font-size: 8px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em; text-align: left;
            border-bottom: 2px solid #ea580c;
        }
        table.data-table tbody td {
            padding: 7px 8px; border-bottom: 1px solid #e2e8f0;
            vertical-align: middle; font-size: 9px;
        }
        table.data-table tbody tr:nth-child(even) { background: #f8fafc; }
        table.data-table tfoot td {
            padding: 10px 8px; border-top: 2px solid #14213d;
            background: #f1f5f9; font-weight: 700; color: #14213d; font-size: 9px;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .text-muted { color: #94a3b8; }
        .font-mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5px; color: #475569; }
        .title-cell { font-weight: 600; color: #0f172a; }
        .unit-min { font-size: 9px; color: #64748b; }
        .empty-row { padding: 28px; }

        /* ---- Badges ---- */
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px;
            font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
        }
        .badge-aberta { background: #dbeafe; color: #1e40af; }
        .badge-em-curso { background: #fed7aa; color: #9a3412; }
        .badge-pendente-orcamento { background: #fef9c3; color: #854d0e; }
        .badge-fechada { background: #bbf7d0; color: #166534; }
        .badge-cancelada { background: #fee2e2; color: #991b1b; }
        .badge-recusada { background: #fecaca; color: #7f1d1d; }
        .badge-default { background: #e2e8f0; color: #334155; }

        .badge-pending { background: #fef9c3; color: #854d0e; }
        .badge-approved { background: #bbf7d0; color: #166534; }
        .badge-rejected { background: #fecaca; color: #7f1d1d; }

        .badge-pri { display: inline-block; padding: 1px 7px; border-radius: 6px; font-size: 8px; font-weight: 700; }
        .pri-baixa { background: #e2e8f0; color: #475569; }
        .pri-media { background: #dbeafe; color: #1e40af; }
        .pri-alta { background: #fed7aa; color: #9a3412; }
        .pri-critica { background: #fecaca; color: #991b1b; }

        .badge-urgent { color: #dc2626; font-weight: 800; font-size: 9px; }

        /* ---- Footer ---- */
        .page-footer {
            position: fixed; bottom: -36px; left: 32px; right: 32px;
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
                <div class="report-eyebrow">{{ __('common.Relatório Analítico') }}</div>
                <h1 class="report-title">{{ __('tickets.Relatório Consolidado de Tickets') }}</h1>
                <div class="report-subtitle">
                    {{ __('tickets.Análise dos estados operacionais, tempos de resolução e custos.') }}
                </div>
            </td>
            <td class="report-meta">
                <div><strong>{{ __('tickets.Emitido em:') }}</strong> {{ app(\App\Services\LocalizationService::class)->formatDateTime(now()) }}</div>
                <div><strong>{{ __('tickets.Registos:') }}</strong> {{ $tickets->count() }}</div>
                @if ($truncated ?? false)
                    <div class="report-warning">{{ __('tickets.Relatório limitado aos :total registos mais recentes.', ['total' => $maxRecords]) }}</div>
                @endif
                <div><strong>{{ __('tickets.Gerado automaticamente') }}</strong></div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <div class="label">{{ __('tickets.Total Tickets') }}</div>
                <div class="value">{{ $tickets->count() }}</div>
            </td>
            <td>
                <div class="label">{{ __('common.Duração Total') }}</div>
                <div class="value">{{ $tickets->sum('minutes_spent') }}<span class="unit-min"> min</span></div>
            </td>
            <td>
                <div class="label">{{ __('common.Custo Total') }}</div>
                <div class="value">{{ app(\App\Services\LocalizationService::class)->formatCurrency((float) $tickets->sum('actual_cost')) }}</div>
            </td>
            <td>
                <div class="label">{{ __('common.Orçamento Total') }}</div>
                <div class="value">{{ app(\App\Services\LocalizationService::class)->formatCurrency((float) $tickets->sum('budget_amount')) }}</div>
            </td>
            <td>
                <div class="label">{{ __('common.Fechados') }}</div>
                <div class="value">{{ $tickets->where('status.name', \App\Enums\TicketStatusEnum::Closed->value)->count() }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('common.ID') }}</th>
                <th>{{ __('common.Título do Incidente') }}</th>
                <th>{{ __('common.Estado') }}</th>
                <th>{{ __('common.Prioridade') }}</th>
                <th class="text-center">{{ __('common.Urg.') }}</th>
                <th>{{ __('common.Abertura') }}</th>
                <th>{{ __('common.Em Curso') }}</th>
                <th>{{ __('common.Fecho') }}</th>
                <th class="text-right">{{ __('common.Duração') }}</th>
                <th class="text-right">{{ __('common.Custo') }}</th>
                <th>{{ __('common.Orçam. Est.') }}</th>
                <th class="text-right">{{ __('common.Orçam. Valor') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $t)
                <tr>
                    <td class="font-mono">#{{ $t->id }}</td>
                    <td class="title-cell">{{ $t->title }}</td>
                    <td>
                        <span class="badge badge-{{ str_replace([' ', 'ç', 'á'], ['-', 'c', 'a'], strtolower((string) ($t->status?->name ?? 'default'))) }}">
                            {{ \App\Enums\TicketStatusEnum::tryFrom((string) ($t->status?->name ?? ''))?->label() ?? ucfirst((string) ($t->status?->name ?? '—')) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge-pri pri-{{ str_replace('í', 'i', (string) ($t->priority ?? 'baixa')) }}">
                            {{ \App\Enums\TicketPriorityEnum::tryFrom((string) ($t->priority ?? ''))?->label() ?? ucfirst((string) ($t->priority ?? '—')) }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if($t->urgent)
                            <span class="badge-urgent">●</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="font-mono">{{ app(\App\Services\LocalizationService::class)->formatDateTime($t->opened_at) ?: '—' }}</td>
                    <td class="font-mono">{{ app(\App\Services\LocalizationService::class)->formatDateTime($t->in_progress_at) ?: '—' }}</td>
                    <td class="font-mono">{{ app(\App\Services\LocalizationService::class)->formatDateTime($t->closed_at) ?: '—' }}</td>
                    <td class="text-right font-mono">
                        @if($t->minutes_spent)
                            {{ $t->minutes_spent }} <span class="text-muted">m</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">
                        {{ app(\App\Services\LocalizationService::class)->formatCurrency((float) $t->actual_cost) }}
                    </td>
                    <td>
                        <span class="badge badge-{{ (string) ($t->budget_status ?? 'default') }}">
                            {{ \App\Enums\BudgetStatusEnum::tryFrom((string) ($t->budget_status ?? ''))?->label() ?? '—' }}
                        </span>
                    </td>
                    <td class="text-right font-mono">
                        @if($t->budget_amount !== null)
                            {{ app(\App\Services\LocalizationService::class)->formatCurrency((float) $t->budget_amount) }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center text-muted empty-row">
                        {{ __('tickets.Sem registos de tickets para os parâmetros selecionados.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if($tickets->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="8">{{ __('common.Total Consolidado') }}</td>
                    <td class="text-right">{{ $tickets->sum('minutes_spent') }} m</td>
                    <td class="text-right">{{ app(\App\Services\LocalizationService::class)->formatCurrency((float) $tickets->sum('actual_cost')) }}</td>
                    <td></td>
                    <td class="text-right">{{ app(\App\Services\LocalizationService::class)->formatCurrency((float) $tickets->sum('budget_amount')) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

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
