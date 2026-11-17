<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('notifications.mail_test_subject') }}</title>
    <style>
        /* Reset and Safe Rendering Rules */
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.5;
            color: #1b1b18;
            background-color: #fafafa;
            -webkit-font-smoothing: antialiased;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
        }
        td {
            vertical-align: top;
        }

        /* Fluid Container Structure */
        .wrapper {
            width: 100%;
            background-color: #fafafa;
            padding: 40px 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e3e3e0;
            border-radius: 8px;
            padding: 32px;
        }

        /* High-Precision Typography */
        .header-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #706f6c;
            margin-bottom: 8px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #1b1b18;
            margin: 0 0 16px 0;
        }
        .text-body {
            font-size: 13px;
            color: #1b1b18;
            margin: 0 0 16px 0;
        }
        .text-body--no-margin {
            margin-bottom: 0;
        }

        /* Telemetry and Diagnostics Block */
        .telemetry-box {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11px;
            color: #555450;
            line-height: 1.6;
            background-color: #fafafa;
            border: 1px solid #e3e3e0;
            border-radius: 6px;
            padding: 14px;
            margin: 24px 0;
        }
        .telemetry-label {
            color: #706f6c;
            font-weight: 600;
        }

        /* Disclaimer Footer */
        .footer {
            margin-top: 24px;
            border-top: 1px solid #f0f0ef;
            padding-top: 16px;
            font-size: 10px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: #a1a09a;
            text-align: center;
        }
        .footer-text {
            margin: 0;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <table role="presentation" class="container">
        <tr>
            <td>
                {{-- Context Identifier --}}
                <div class="header-badge">{{ __('messages.Verificação de Serviço') }}</div>
                <h1 class="title">{{ __('messages.Ligação Estabelecida com Sucesso') }}</h1>

                <p class="text-body">{{ __('notifications.greeting', ['name' => $recipientName]) }}</p>
                <p class="text-body text-body--no-margin">{{ __('messages.Este é um disparo de validação estrutural enviado para confirmar a correta integração, credenciação e entrega de mensagens na plataforma.') }}</p>

                {{-- System Diagnostics Metadata --}}
                <div class="telemetry-box">
                    <span class="telemetry-label">{{ __('messages.Canal de envio:') }}</span> {{ $mailerName }}<br>
                    <span class="telemetry-label">{{ __('common.Emitido em:') }}</span> {{ now()->format('Y-m-d H:i:s') }} UTC
                </div>

                {{-- System Footer --}}
                <div class="footer">
                    <p class="footer-text">{{ __('messages.Mensagem automática de sistema • Não responda a este endereço') }}</p>
                </div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
