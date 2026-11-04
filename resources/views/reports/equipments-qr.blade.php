<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ __('equipment.QR Codes de Equipamentos') }}</title>
    <style>
        @page { size: A4; margin: 32px 28px 48px; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            color: #0f172a; line-height: 1.4; font-size: 10px; margin: 0; padding: 0;
        }

        .brand-bar {
            height: 6px;
            background: linear-gradient(90deg, #ea580c 0%, #ea580c 40%, #14213d 100%);
            border-radius: 3px 3px 0 0;
            margin-bottom: 18px;
        }
        .report-header { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .report-header td { padding: 0; border: none; vertical-align: middle; }
        .report-eyebrow {
            font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em;
            color: #ea580c; margin-bottom: 4px;
        }
        .report-title { font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: #14213d; margin: 0; }
        .report-subtitle { font-size: 10px; color: #64748b; margin-top: 4px; }
        .report-meta { text-align: right; font-size: 9px; color: #475569; line-height: 1.8; }
        .report-meta strong { color: #14213d; }

        .qr-grid { width: 100%; border-collapse: collapse; }
        .qr-grid td {
            width: 25%; padding: 14px 12px; border: 1px solid #e2e8f0;
            text-align: center; vertical-align: top;
            border-radius: 8px;
        }
        .qr-grid td:nth-child(even) { background: #fafbfc; }
        .qr-card { display: block; }
        .qr-image {
            width: 78px; height: 78px; margin: 0 auto 10px;
            border: 3px solid #ffffff; border-radius: 8px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
        }
        .qr-name { font-weight: 700; color: #14213d; font-size: 10.5px; margin-bottom: 3px; }
        .qr-meta {
            font-size: 8.5px; color: #64748b;
            font-family: 'DejaVu Sans Mono', monospace; margin-top: 2px;
        }
        .qr-tag {
            display: inline-block; margin-top: 6px; padding: 2px 8px;
            background: #14213d; color: #ffffff; border-radius: 999px;
            font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
        }
        .empty-state {
            padding: 40px 20px; text-align: center; color: #94a3b8;
            border: 1px dashed #cbd5e1; border-radius: 10px; font-size: 11px;
        }

        .page-footer {
            position: fixed; bottom: -36px; left: 28px; right: 28px;
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
                <div class="report-eyebrow">{{ __('equipment.Etiquetas de Equipamento') }}</div>
                <h1 class="report-title">{{ __('equipment.QR Codes de Equipamentos') }}</h1>
                <div class="report-subtitle">
                    {{ __('equipment.Códigos para reportar avarias — afixar junto de cada equipamento.') }}
                </div>
            </td>
            <td class="report-meta">
                <div><strong>{{ __('common.Emitido em:') }}</strong> {{ app(\App\Services\LocalizationService::class)->formatDateTime(now()) }}</div>
                <div><strong>{{ __('common.Equipamentos:') }}</strong> {{ $items->count() }}</div>
            </td>
        </tr>
    </table>

    @if($items->isEmpty())
        <div class="empty-state">{{ __('equipment.Nenhum equipamento ativo encontrado.') }}</div>
    @else
        <table class="qr-grid">
            @foreach($items->chunk(4) as $chunk)
                <tr>
                    @foreach($chunk as $item)
                        <td>
                            <div class="qr-card">
                                <img class="qr-image" src="{{ $item['qrDataUri'] }}" alt="QR {{ $item['equipment']->name }}">
                                <div class="qr-name">{{ $item['equipment']->name }}</div>
                                <div class="qr-meta">{{ $item['equipment']->asset_tag ?? '#' . $item['equipment']->id }}</div>
                                <div class="qr-meta">{{ $item['equipment']->room?->name ?? '—' }}</div>
                                <div class="qr-tag">{{ __('common.Afixar') }}</div>
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
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
