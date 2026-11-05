<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;

/**
 * System settings editable on the settings page.
 *
 * Only a curated list of options is exposed (never secrets or infrastructure
 * drivers). Each option corresponds to a real `config()` key; chosen values
 * are stored in `system_settings` and applied at runtime by `applyOverrides()`
 * (overrides `config/*.php` files without modifying them).
 *
 * @phpstan-type SettingField array{
 *     label: string,
 *     type: string,
 *     default: int|float|bool|string,
 *     min?: int|float,
 *     max?: int|float,
 *     step?: int|float,
 *     unit?: string,
 *     help: string,
 *     options?: array<string, string>
 * }
 * @phpstan-type SettingGroup array{
 *     label: string,
 *     description: string,
 *     fields: array<string, SettingField>
 * }
 */
final class SystemSettingsService
{
    /**
     * Configuration groups shown on the page, in order.
     * `type`: text | number | float | select | bool
     *
     * @return array<string, SettingGroup>
     */
    public function groups(): array
    {
        return [
            'app' => [
                'label' => __('common.Aplicação'),
                'description' => __('common.Identidade e preferências gerais da aplicação.'),
                'fields' => [
                    'app.name' => [
                        'label' => __('common.Nome da aplicação'),
                        'type' => 'text',
                        'default' => 'Gestão de Avarias',
                        'help' => __('common.Nome exibido no título e nas notificações por e-mail.'),
                    ],
                    'app.locale' => [
                        'label' => __('common.Idioma predefinido'),
                        'type' => 'select',
                        'default' => 'pt-PT',
                        'options' => $this->localeOptions(),
                        'help' => __('common.Idioma usado quando o utilizador não escolheu outro.'),
                    ],
                    'app.timezone' => [
                        'label' => __('common.Fuso horário'),
                        'type' => 'select',
                        'default' => 'UTC',
                        'options' => $this->timezones(),
                        'help' => __('messages.Fuso horário usado nas datas e horas do sistema.'),
                    ],
                ],
            ],
            'auth' => [
                'label' => __('auth.Autenticação'),
                'description' => __('auth.Limites de segurança do login e validade das sessões.'),
                'fields' => [
                    'services.custom.auth.max_attempts' => [
                        'label' => __('auth.Tentativas de login'),
                        'type' => 'number',
                        'min' => 1,
                        'max' => 20,
                        'default' => 5,
                        'help' => __('common.Número máximo de tentativas antes do bloqueio (1–20).'),
                    ],
                    'services.custom.auth.lockout_minutes' => [
                        'label' => __('common.Bloqueio (minutos)'),
                        'type' => 'number',
                        'min' => 1,
                        'max' => 120,
                        'unit' => 'min',
                        'default' => 15,
                        'help' => __('common.Tempo de bloqueio após exceder as tentativas (1–120).'),
                    ],
                    'services.custom.auth.token_expiry_days' => [
                        'label' => __('common.Validade do token (dias)'),
                        'type' => 'number',
                        'min' => 1,
                        'max' => 365,
                        'default' => 30,
                        'help' => __('auth.Dias de validade dos tokens de autenticação (1–365).'),
                    ],
                ],
            ],
            'budget' => [
                'label' => __('common.Orçamentos'),
                'description' => __('stock_part.Limiar a partir do qual um orçamento precisa de aprovação.'),
                'fields' => [
                    'services.custom.budget.threshold' => [
                        'label' => __('common.Limiar de aprovação (€)'),
                        'type' => 'float',
                        'min' => 0,
                        'max' => 100000,
                        'step' => 0.5,
                        'unit' => '€',
                        'default' => 50.0,
                        'help' => __('common.Orçamentos acima deste valor pedem autorização (0–100000).'),
                    ],
                ],
            ],
            'ai' => [
                'label' => __('common.Inteligência Artificial'),
                'description' => __('tickets.Modelo usado nas recomendações automáticas de tickets.'),
                'fields' => [
                    'services.custom.ai.model' => [
                        'label' => __('common.Modelo'),
                        'type' => 'text',
                        'default' => 'gpt-4o-mini',
                        'help' => __('common.Identificador do modelo (ex.: gpt-4o-mini, gpt-4o).'),
                    ],
                    'services.custom.ai.temperature' => [
                        'label' => __('common.Temperatura'),
                        'type' => 'float',
                        'min' => 0,
                        'max' => 2,
                        'step' => 0.1,
                        'default' => 0.1,
                        'help' => __('common.Criatividade das respostas — 0 é mais determinístico (0–2).'),
                    ],
                ],
            ],
            'analytics' => [
                'label' => __('common.Analytics'),
                'description' => __('dashboard.Parâmetros usados nos cálculos do painel de análise.'),
                'fields' => [
                    'services.custom.analytics.system_availability' => [
                        'label' => __('messages.Disponibilidade do sistema (%)'),
                        'type' => 'float',
                        'min' => 0,
                        'max' => 100,
                        'step' => 0.1,
                        'unit' => '%',
                        'default' => 99.9,
                        'help' => __('common.Meta de disponibilidade apresentada no relatório (0–100).'),
                    ],
                ],
            ],
            'pagination' => [
                'label' => __('common.Paginação'),
                'description' => __('auth.Número de registos por página em cada contexto.'),
                'fields' => [
                    'services.custom.pagination.default_per_page' => [
                        'label' => __('common.Padrão por página'),
                        'type' => 'number',
                        'min' => 5,
                        'max' => 100,
                        'default' => 15,
                        'help' => __('tickets.Listagens gerais (tickets, equipamentos) — 5 a 100.'),
                    ],
                    'services.custom.pagination.admin_per_page' => [
                        'label' => __('common.Administração por página'),
                        'type' => 'number',
                        'min' => 10,
                        'max' => 200,
                        'default' => 50,
                        'help' => __('common.Listagens de administração (auditoria, utilizadores) — 10 a 200.'),
                    ],
                ],
            ],
            'tokens' => [
                'label' => __('common.Tokens de API'),
                'description' => __('common.Comprimento dos tokens gerados para acesso à API.'),
                'fields' => [
                    'services.custom.tokens.length' => [
                        'label' => __('common.Comprimento do token'),
                        'type' => 'number',
                        'min' => 20,
                        'max' => 128,
                        'default' => 60,
                        'help' => __('common.Caracteres do token de API (20–128).'),
                    ],
                ],
            ],
            'notification' => [
                'label' => __('common.Notificações'),
                'description' => __('messages.Canal predefinido de envio de e-mails do sistema.'),
                'fields' => [
                    'services.custom.notification.mailer' => [
                        'label' => __('common.Transporte de e-mail'),
                        'type' => 'select',
                        'default' => 'mailgun_fallback',
                        'options' => $this->mailers(),
                        'help' => __('common.Transporte usado para e-mails automáticos.'),
                    ],
                ],
            ],
            'backup' => [
                'label' => __('ui.Backups'),
                'description' => __('common.Conservação e compressão das cópias de segurança da base de dados.'),
                'fields' => [
                    'backup.retention.days' => [
                        'label' => __('common.Dias de retenção'),
                        'type' => 'number',
                        'min' => 1,
                        'max' => 365,
                        'unit' => 'dias',
                        'default' => 30,
                        'help' => __('ui.Backups mais antigos que este número de dias são apagados (1–365).'),
                    ],
                    'backup.database.compression' => [
                        'label' => __('ui.Comprimir backups'),
                        'type' => 'bool',
                        'default' => true,
                        'help' => __('common.Compressão gzip das cópias de segurança.'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Effective value of a key: DB-stored override if exists,
     * otherwise the current config() value.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        /** @var array<string, mixed> $overrides */
        $overrides = SystemSetting::query()->pluck('value', 'key')->toArray();

        $values = [];

        foreach ($this->groups() as $group) {
            $values = array_merge($values, $this->resolveValues($group, $overrides));
        }

        return $values;
    }

    /**
     * Saves one or multiple fields (validation + cast) and returns the
     * normalized applied values.
     *
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    public function update(array $updates): array
    {
        $saved = [];

        foreach ($updates as $key => $value) {
            $field = $this->findField($key);

            if ($field === null) {
                continue;
            }

            $normalized = $this->normalize($field, $value);

            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $normalized]);
            config([$key => $normalized]);

            $saved[$key] = $normalized;
        }

        return $saved;
    }

    /**
     * Removes saved overrides from a group, restoring config file values.
     * Returns the effective values of the group.
     *
     * @return array<string, mixed>
     */
    public function reset(string $groupId): array
    {
        $group = $this->findGroup($groupId);

        if ($group === null) {
            return [];
        }

        foreach (array_keys($group['fields']) as $key) {
            SystemSetting::query()->where('key', $key)->delete();
        }

        /** @var array<string, mixed> $overrides */
        $overrides = SystemSetting::query()->pluck('value', 'key')->toArray();

        return $this->resolveValues($group, $overrides);
    }

    /**
     * @param  SettingGroup  $group
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function resolveValues(array $group, array $overrides): array
    {
        $values = [];

        foreach ($group['fields'] as $key => $field) {
            if (array_key_exists($key, $overrides)) {
                $values[$key] = $this->cast($field, $overrides[$key]);

                continue;
            }

            $current = config($key);

            $values[$key] = $current !== null ? $this->cast($field, $current) : $field['default'];
        }

        return $values;
    }

    /**
     * Applies saved overrides to the config() repository at runtime.
     * Called in AppServiceProvider boot.
     */
    public function applyOverrides(): void
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        try {
            /** @var array<string, mixed> $rows */
            $rows = SystemSetting::query()->pluck('value', 'key')->toArray();

            foreach ($rows as $key => $value) {
                $field = $this->findField($key);

                if ($field === null) {
                    continue;
                }

                config([$key => $this->cast($field, $value)]);
            }

            $timezone = config('app.timezone');

            if (is_string($timezone) && $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
                date_default_timezone_set($timezone);
            }
        } catch (\Throwable $e) {
            // database unavailable during boot (installation/maintenance)
        }
    }

    /**
     * @return SettingGroup|null
     */
    private function findGroup(string $groupId): ?array
    {
        return $this->groups()[$groupId] ?? null;
    }

    /**
     * @return SettingField|null
     */
    private function findField(string $key): ?array
    {
        foreach ($this->groups() as $group) {
            if (isset($group['fields'][$key])) {
                return $group['fields'][$key];
            }
        }

        return null;
    }

    /**
     * @param  SettingField  $field
     */
    private function cast(array $field, mixed $value): int|float|bool|string
    {
        return match ($field['type']) {
            'number' => is_numeric($value) ? (int) $value : 0,
            'float' => is_numeric($value) ? (float) $value : 0.0,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => is_scalar($value) ? (string) $value : '',
        };
    }

    /**
     * @param  SettingField  $field
     */
    private function normalize(array $field, mixed $value): int|float|bool|string
    {
        return match ($field['type']) {
            'number' => (int) max($field['min'] ?? 0, min($field['max'] ?? PHP_INT_MAX, is_numeric($value) ? (int) round((float) $value) : 0)),
            'float' => (float) max($field['min'] ?? 0, min($field['max'] ?? PHP_INT_MAX, is_numeric($value) ? (float) $value : 0.0)),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'select' => $this->normalizeSelect($field, $value),
            default => is_scalar($value) ? trim((string) $value) : '',
        };
    }

    /**
     * @param  SettingField  $field
     */
    private function normalizeSelect(array $field, mixed $value): string
    {
        $candidate = is_scalar($value) ? (string) $value : '';
        $options = $field['options'] ?? [];

        if (array_key_exists($candidate, $options)) {
            return $candidate;
        }

        if (in_array($candidate, array_values($options), true)) {
            return $candidate;
        }

        return (string) $field['default'];
    }

    /**
     * Locales supported by the system, derived from config('locales').
     *
     * @return array<string, string>
     */
    private function localeOptions(): array
    {
        $options = [];

        foreach (LocaleService::all() as $code => $meta) {
            $options[$code] = $meta['name'];
        }

        return $options;
    }

    /**
     * Available email options, derived from the configured mailers.
     *
     * @return array<string, string>
     */
    private function mailers(): array
    {
        $mailers = config('mail.mailers');
        $keys = is_array($mailers) ? array_keys($mailers) : [];

        $labels = [
            'log' => __('auth.Registo (log)'),
            'smtp' => 'SMTP',
            'sendmail' => 'Sendmail',
            'mailgun' => 'Mailgun',
            'sendgrid' => 'SendGrid',
            'ses' => 'Amazon SES',
            'postmark' => 'Postmark',
            'resend' => 'Resend',
            'mailgun_fallback' => __('ui.Mailgun → SendGrid (fallback)'),
            'failover' => __('common.Failover'),
            'roundrobin' => __('common.Round-robin'),
            'array' => __('common.Array (teste)'),
        ];

        $options = [];

        foreach ($keys as $key) {
            if (! is_string($key)) {
                continue;
            }

            $options[$key] = $labels[$key] ?? $key;
        }

        return $options;
    }

    /**
     * Most common timezones, with UTC first.
     *
     * @return array<string, string>
     */
    private function timezones(): array
    {
        $common = [
            'UTC',
            'Europe/Lisbon',
            'Atlantic/Azores',
            'Europe/London',
            'Europe/Paris',
            'Europe/Madrid',
            'Europe/Berlin',
            'Europe/Rome',
            'America/Sao_Paulo',
            'America/New_York',
            'America/Chicago',
            'America/Los_Angeles',
            'Africa/Luanda',
            'Africa/Maputo',
            'Africa/Cape_Town',
            'Africa/Lagos',
            'Asia/Tokyo',
            'Asia/Shanghai',
            'Asia/Dubai',
            'Australia/Sydney',
        ];

        $options = [];

        foreach ($common as $zone) {
            $options[$zone] = str_replace('_', ' ', $zone);
        }

        return $options;
    }
}
