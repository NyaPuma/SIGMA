<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('common.Notificação de Incidente') }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.5;
            color: #1b1b18;
            background-color: #fafafa;
            -webkit-font-smoothing: antialiased;
        }
        table { border-collapse: collapse; border-spacing: 0; }
        td { vertical-align: top; }
        .wrapper { width: 100%; background-color: #fafafa; padding: 40px 20px; }
        .container { max-width: 560px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e3e3e0; border-radius: 8px; padding: 32px; }
        .header-badge { font-family: ui-monospace, monospace; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #706f6c; margin-bottom: 8px; }
        .title { font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: #1b1b18; margin: 0 0 16px 0; }
        .greeting { font-size: 13px; color: #1b1b18; margin: 0 0 16px 0; }
        .greeting--last { margin-bottom: 0; }
        .details-table { width: 100%; margin: 24px 0; border-top: 1px solid #e3e3e0; }
        .data-row td { padding: 12px 0; border-bottom: 1px solid #f0f0ef; font-size: 12px; }
        .label { font-family: ui-monospace, monospace; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #706f6c; width: 120px; }
        .label--sub { margin-bottom: 2px; }
        .value { color: #1b1b18; font-weight: 500; }
        .value--title { font-weight: 600; color: #1b1b18; }
        .value--mono { font-family: ui-monospace, monospace; font-size: 11px; }
        .value--priority-high { color: #ef4444; font-weight: 700; }
        .value--priority-medium { color: #f59e0b; font-weight: 700; }
        .value--priority-default { color: #6b7280; font-weight: 700; }
        .description-box { font-size: 12px; color: #555450; line-height: 1.6; background-color: #fafafa; border: 1px solid #e3e3e0; border-radius: 6px; padding: 12px; margin-top: 6px; }
        .description-cell { padding: 16px 0 4px 0; }
        .btn-container { margin: 28px 0; text-align: center; }
        .btn-action { display: inline-block; background-color: #f59e0b; color: #ffffff !important; text-decoration: none; padding: 10px 20px; font-size: 12px; font-weight: 600; border-radius: 6px; letter-spacing: 0.02em; }
        .notice { font-size: 12px; color: #706f6c; margin: 20px 0 0 0; }
        .footer { margin-top: 32px; border-top: 1px solid #f0f0ef; padding-top: 16px; font-size: 10px; font-family: ui-monospace, monospace; color: #a1a09a; text-align: center; }
        .footer-text { margin: 0; text-transform: uppercase; }
    </style>
</head>
<body>

<div class="wrapper">
    <table role="presentation" class="container">
        <tr>
            <td>
                <div class="header-badge">{{ __('common.Notificação de Incidente') }}</div>
                <h1 class="title">{{ __('common.Nova Avaria Registada #:id', ['id' => $ticket->id]) }}</h1>

                <p class="greeting">{{ __('common.Olá,') }}</p>
                <p class="greeting greeting--last">{{ __('messages.Foi submetido um novo registo de avaria no sistema com os seguintes parâmetros operacionais:') }}</p>

                <table role="presentation" class="details-table">
                    <tr class="data-row">
                        <td class="label">{{ __('common.Título') }}</td>
                        <td class="value value--title">{{ $ticket->title }}</td>
                    </tr>
                    <tr class="data-row">
                        <td class="label">{{ __('equipment.Equipamento') }}</td>
                        <td class="value">{{ $ticket->equipment?->name ?? __('common.Não especificado') }}</td>
                    </tr>
                    <tr class="data-row">
                        <td class="label">{{ __('room.Sala / Espaço') }}</td>
                        <td class="value">{{ $ticket->room?->name ?? __('common.Não especificada') }}</td>
                    </tr>
                    <tr class="data-row">
                        <td class="label">{{ __('common.Prioridade') }}</td>
                        <td class="value value--mono {{ match(strtolower($ticket->priority)) { 'alta', 'critica', 'crítica' => 'value--priority-high', 'media', 'média' => 'value--priority-medium', default => 'value--priority-default' } }}">
                            [{{ strtoupper($ticket->priority) }}]
                        </td>
                    </tr>
                    <tr class="data-row">
                        <td class="label">{{ __('common.Registado Por') }}</td>
                        <td class="value">{{ $ticket->user?->name ?? 'Utilizador do Sistema' }}</td>
                    </tr>
                    <tr class="data-row">
                        <td class="label">{{ __('common.Data de Entrada') }}</td>
                        <td class="value value--mono">
                            ($ticket->created_at ?? now())
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="description-cell">
                            <div class="label label--sub">{{ __('common.Descrição do Sintoma') }}</div>
                            <div class="description-box">
                                {{ $ticket->description }}
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="btn-container">
                    <a href="{{ url('/tickets/' . $ticket->id) }}" class="btn-action">
                        {{ __('tickets.Ver Detalhes do Ticket') }}
                    </a>
                </div>

                <p class="notice">{{ __('tickets.O ciclo de vida, atribuição de técnicos e auditoria deste ticket podem ser geridos diretamente através do painel de controlo principal.') }}</p>

                <div class="footer">
                    <p class="footer-text">{{ __('messages.Mensagem automática de sistema • Não responda a este endereço') }}</p>
                </div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
