<?php

namespace App\Services;

use App\Models\ThemeSetting;
use App\Models\User;

/**
 * Single source of truth for preset themes (14 families × light/dark = 28 themes).
 *
 * Each theme defines the complete set of colors stored in `theme_settings`.
 * Each family has a light/dark pair with the same hue — the light/dark toggle
 * button switches to the equivalent and saves it.
 */
final class ThemePresetService
{
    /**
     * Default theme applied to guests and to users without a saved preference.
     */
    public const DEFAULT_THEME = 'claro-laranja';

    /**
     * Color keys stored in theme_settings (form field names).
     */
    private const COLOR_KEYS = [
        'primary', 'text', 'text_soft', 'surface', 'surface_alt', 'border',
        'ticket_open', 'ticket_in_progress', 'ticket_resolved', 'ticket_urgent',
    ];

    /**
     * Field name -> CSS token mapping.
     */
    private const TOKENS = [
        'primary' => '--color-primary',
        'text' => '--color-text',
        'text_soft' => '--color-text-soft',
        'surface' => '--color-surface',
        'surface_alt' => '--color-surface-alt',
        'border' => '--color-border',
        'ticket_open' => '--color-ticket-open',
        'ticket_in_progress' => '--color-ticket-in-progress',
        'ticket_resolved' => '--color-ticket-resolved',
        'ticket_urgent' => '--color-ticket-urgent',
    ];

    /**
     * @return array<string, array<string, string>> id do tema -> preset
     */
    public function all(): array
    {
        return [
            'claro-laranja' => [
                'label' => __('common.Laranja Industrial'),
                'mode' => 'light',
                'family' => 'laranja',
                'primary' => '#ea580c',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-laranja' => [
                'label' => __('common.Laranja Noturno'),
                'mode' => 'dark',
                'family' => 'laranja',
                'primary' => '#f97316',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-azul' => [
                'label' => __('common.Azul Profissional'),
                'mode' => 'light',
                'family' => 'azul',
                'primary' => '#1d4ed8',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-azul' => [
                'label' => __('common.Azul Noturno'),
                'mode' => 'dark',
                'family' => 'azul',
                'primary' => '#60a5fa',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-verde' => [
                'label' => __('common.Verde Saúde'),
                'mode' => 'light',
                'family' => 'verde',
                'primary' => '#047857',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-verde' => [
                'label' => __('common.Verde Noturno'),
                'mode' => 'dark',
                'family' => 'verde',
                'primary' => '#34d399',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-vinho' => [
                'label' => __('common.Vinho Formal'),
                'mode' => 'light',
                'family' => 'vinho',
                'primary' => '#991b1b',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-vinho' => [
                'label' => __('common.Vinho Noturno'),
                'mode' => 'dark',
                'family' => 'vinho',
                'primary' => '#f87171',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-roxo' => [
                'label' => __('equipment.Roxo Criativo'),
                'mode' => 'light',
                'family' => 'roxo',
                'primary' => '#6d28d9',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-roxo' => [
                'label' => __('common.Roxo Noturno'),
                'mode' => 'dark',
                'family' => 'roxo',
                'primary' => '#a78bfa',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-teal' => [
                'label' => __('common.Turquesa Técnica'),
                'mode' => 'light',
                'family' => 'teal',
                'primary' => '#0f766e',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-teal' => [
                'label' => __('common.Turquesa Noturno'),
                'mode' => 'dark',
                'family' => 'teal',
                'primary' => '#2dd4bf',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-dourado' => [
                'label' => __('common.Dourado Clássico'),
                'mode' => 'light',
                'family' => 'dourado',
                'primary' => '#92400e',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-dourado' => [
                'label' => __('common.Dourado Noturno'),
                'mode' => 'dark',
                'family' => 'dourado',
                'primary' => '#fbbf24',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-grafite' => [
                'label' => __('common.Grafite Urbano'),
                'mode' => 'light',
                'family' => 'grafite',
                'primary' => '#334155',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-grafite' => [
                'label' => __('common.Grafite Noturno'),
                'mode' => 'dark',
                'family' => 'grafite',
                'primary' => '#94a3b8',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-rosa' => [
                'label' => __('common.Rosa Contemporâneo'),
                'mode' => 'light',
                'family' => 'rosa',
                'primary' => '#be185d',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-rosa' => [
                'label' => __('common.Rosa Noturno'),
                'mode' => 'dark',
                'family' => 'rosa',
                'primary' => '#f472b6',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-limao' => [
                'label' => __('common.Limão Energia'),
                'mode' => 'light',
                'family' => 'limao',
                'primary' => '#a16207',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-limao' => [
                'label' => __('common.Limão Noturno'),
                'mode' => 'dark',
                'family' => 'limao',
                'primary' => '#facc15',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-indigo' => [
                'label' => __('common.Índigo Foco'),
                'mode' => 'light',
                'family' => 'indigo',
                'primary' => '#4338ca',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-indigo' => [
                'label' => __('common.Índigo Noturno'),
                'mode' => 'dark',
                'family' => 'indigo',
                'primary' => '#818cf8',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-ciano' => [
                'label' => __('common.Ciano Técnico'),
                'mode' => 'light',
                'family' => 'ciano',
                'primary' => '#0e7490',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-ciano' => [
                'label' => __('common.Ciano Noturno'),
                'mode' => 'dark',
                'family' => 'ciano',
                'primary' => '#22d3ee',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-fuchsia' => [
                'label' => __('common.Fúcsia Vibrante'),
                'mode' => 'light',
                'family' => 'fuchsia',
                'primary' => '#a21caf',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-fuchsia' => [
                'label' => __('common.Fúcsia Noturno'),
                'mode' => 'dark',
                'family' => 'fuchsia',
                'primary' => '#e879f9',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
            'claro-castanho' => [
                'label' => __('common.Castanho Clássico'),
                'mode' => 'light',
                'family' => 'castanho',
                'primary' => '#7c2d12',
                'text' => '#0f172a',
                'text_soft' => '#475569',
                'surface' => '#ffffff',
                'surface_alt' => '#e2e8f0',
                'border' => '#cbd5e1',
                'ticket_open' => '#2563eb',
                'ticket_in_progress' => '#d97706',
                'ticket_resolved' => '#059669',
                'ticket_urgent' => '#dc2626',
            ],
            'escuro-castanho' => [
                'label' => __('common.Castanho Noturno'),
                'mode' => 'dark',
                'family' => 'castanho',
                'primary' => '#d6a35a',
                'text' => '#f8fafc',
                'text_soft' => '#94a3b8',
                'surface' => '#0f172a',
                'surface_alt' => '#1e293b',
                'border' => '#334155',
                'ticket_open' => '#3b82f6',
                'ticket_in_progress' => '#fbbf24',
                'ticket_resolved' => '#34d399',
                'ticket_urgent' => '#f87171',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $preset = $this->all()[$id] ?? null;

        if ($preset === null) {
            return null;
        }

        $preset['id'] = $id;

        return $preset;
    }

    /**
     * Family pair with opposite mode (light <-> dark).
     */
    /** @return array<string, mixed>|null */
    public function paired(string $id): ?array
    {
        $preset = $this->find($id);

        if ($preset === null) {
            return null;
        }

        foreach ($this->all() as $candidateId => $candidate) {
            if ($candidate['family'] === $preset['family'] && $candidate['mode'] !== $preset['mode']) {
                return $this->find($candidateId);
            }
        }

        return null;
    }

    /**
     * Preset color values mapped to CSS tokens.
     *
     * @return array<string, string>
     */
    public function valuesFor(string $id): array
    {
        $preset = $this->find($id);

        if ($preset === null) {
            return [];
        }

        $values = [];

        foreach (self::TOKENS as $field => $token) {
            $values[$token] = is_string($preset[$field] ?? null) ? (string) $preset[$field] : '';
        }

        return $values;
    }

    /**
     * Identifies which preset matches stored values
     * (applies to pre-corrected colors, normalizing case).
     *
     * @param  array<string, string>  $values  field name -> color
     */
    public function findByValues(array $values): ?string
    {
        $normalized = [];

        foreach (self::COLOR_KEYS as $key) {
            if (isset($values[$key])) {
                $normalized[$key] = strtolower(ltrim($values[$key], '#'));
            }
        }

        foreach ($this->all() as $id => $preset) {
            $matches = true;

            foreach ($normalized as $key => $color) {
                if (strtolower(ltrim($preset[$key] ?? '', '#')) !== $color) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Resolves the effective theme id for a given user preference.
     *
     * Guests and users without a saved (or invalid) preference always fall
     * back to the default preset (Laranja Industrial).
     */
    public function effectiveThemeId(?string $preference = null): string
    {
        if ($preference !== null && $this->find($preference) !== null) {
            return $preference;
        }

        return self::DEFAULT_THEME;
    }

    /**
     * Active preset for the active user (per-user) or the default for guests.
     *
     * @param  string|null  $preference  the user's stored `users.theme` value
     * @return array<string, string>
     */
    public function active(?string $preference = null): array
    {
        $result = $this->find($this->effectiveThemeId($preference))
            ?? $this->find(self::DEFAULT_THEME)
            ?? [];

        /** @var array<string, string> $result */
        return $result;
    }

    /**
     * Light/dark mode of the active theme.
     */
    public function mode(?string $preference = null): string
    {
        $active = $this->active($preference);

        return ($active['mode'] ?? 'light') === 'dark' ? 'dark' : 'light';
    }

    /**
     * Applies a preset: saves colors and theme name to theme_settings.
     *
     * @return array<string, string> the applied preset
     */
    public function apply(string $id): array
    {
        $preset = $this->find($id);

        if ($preset === null) {
            throw new \InvalidArgumentException("Tema desconhecido: {$id}");
        }

        foreach (self::TOKENS as $field => $token) {
            ThemeSetting::updateOrCreate(
                ['key' => $token],
                ['value' => $preset[$field]]
            );
        }

        ThemeSetting::updateOrCreate(['key' => 'theme_name'], ['value' => $id]);

        /** @var array<string, string> $preset */
        return $preset;
    }

    /**
     * Persists a preset as the user's personal theme.
     *
     * @return array<string, string> the applied preset
     */
    public function applyForUser(User $user, string $id): array
    {
        $preset = $this->find($id);

        if ($preset === null) {
            throw new \InvalidArgumentException("Tema desconhecido: {$id}");
        }

        $user->theme = $id;
        $user->save();

        /** @var array<string, string> $preset */
        return $preset;
    }

    /** @return array<int, string> */
    /** @return array<int, string> */
    public function colorKeys(): array
    {
        return self::COLOR_KEYS;
    }
}
