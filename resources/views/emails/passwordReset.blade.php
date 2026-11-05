<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('notifications.mail_password_reset_subject') }}</title>
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

        /* Centered Container */
        .wrapper {
            width: 100%;
            background-color: #fafafa;
            padding: 40px 20px;
        }
        .container {
            max-width: 460px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e3e3e0;
            border-radius: 8px;
            padding: 32px;
        }

        /* Sober Editorial Typography */
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
            line-height: 1.6;
        }

        /* Solid Action Button Aligned with Theme */
        .btn-container {
            margin: 28px 0;
            text-align: center;
        }
        .btn-action {
            display: inline-block;
            background-color: #f59e0b;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 6px;
            letter-spacing: -0.01em;
        }

        /* Security Notes and Telemetry */
        .security-box {
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
        .security-label {
            color: #706f6c;
            font-weight: 600;
        }

        /* Raw URL Fallback for Restricted Clients */
        .link-fallback {
            font-size: 11px;
            color: #706f6c;
            line-height: 1.5;
            word-break: break-all;
            margin-top: 24px;
        }
        .link-fallback a {
            color: #f59e0b;
            text-decoration: underline;
        }

        /* Disclaimer Footer */
        .footer {
            margin-top: 32px;
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
                <div class="header-badge">{{ __('common.Segurança de Conta') }}</div>
                <h1 class="title">{{ __('auth.Pedido de Nova Password') }}</h1>

                <p class="text-body">{{ __('common.Olá,') }}</p>
                <p class="text-body">{{ __('stock.Recebemos uma solicitação para redefinir as credenciais de acesso associadas à sua conta. Para prosseguir com a alteração, utilize o botão de verificação abaixo:') }}</p>

                {{-- Secure Action --}}
                <div class="btn-container">
                    <a href="{{ $url ?? '#' }}" class="btn-action" target="_blank">
                        {{ __('auth.Redefinir Password') }}
                    </a>
                </div>

                {{-- Regulatory Security Block --}}
                <div class="security-box">
                    <span class="security-label">{{ __('common.VALIDADE DO LINK:') }}</span> {{ __('common.Este link expira em :minutes minutos.', ['minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire') ?? 60]) }}<br>
                    <span class="security-label">{{ __('messages.AVISO:') }}</span> {{ __('auth.Se não solicitou esta alteração, nenhuma ação adicional é necessária e a sua password atual permanecerá segura.') }}
                </div>

                {{-- Raw URL Fallback for Restricted Clients --}}
                <p class="link-fallback">
                    {{ __('ui.Se tiver problemas com o botão acima, copie e cole o URL seguinte no seu navegador:') }}<br>
                    <a href="{{ $url ?? '#' }}">{{ $url ?? '#' }}</a>
                </p>

                {{-- System Footer --}}
                <div class="footer">
                    <p class="footer-text">{{ __('common.Esta é uma notificação compulsória de segurança • Não responda') }}</p>
                </div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
