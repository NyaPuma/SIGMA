{{--
|--------------------------------------------------------------------------
| Tone Badge Component
|--------------------------------------------------------------------------
| Status/priority/audit badge chip with the palette consolidated in a
| single shared source (this component) so pages never duplicate it.
| • Kinds: 'equipmentStatus', 'ticketStatus', 'priority', 'audit'.
| • Sizes: 'sm' (table rows), 'xs' (dense meta), 'badge' (card headers), 'md' (status bar).
| • 100% free of inline CSS or JS.
|--}}
@props([
    'kind' => null,
    'value' => null,
    'label' => null,
    'size' => 'sm',
])

@php
    $toneMaps = [
        'equipmentStatus' => [
            'operacional' => 'border border-success/25 bg-success/10 text-success',
            'manutenção' => 'border border-warning/25 bg-warning/10 text-warning',
            'avariado' => 'border border-danger/25 bg-danger/10 text-danger',
            'abatido' => 'border border-[var(--border)] bg-[var(--surface-2)] text-(--text-muted)',
        ],
        'ticketStatus' => [
            'aberta' => 'border border-info/25 bg-info/10 text-info',
            'em curso' => 'border border-warning/25 bg-warning/10 text-warning',
            'pendente orçamento' => 'border border-warning/25 bg-warning/10 text-warning',
            'fechada' => 'border border-success/25 bg-success/10 text-success',
            'cancelada' => 'border border-danger/25 bg-danger/10 text-danger',
            'recusada' => 'border border-danger/25 bg-danger/10 text-danger',
        ],
        'priority' => [
            'baixa' => 'border border-success/25 bg-success/10 text-success',
            'média' => 'border border-warning/25 bg-warning/10 text-warning',
            'alta' => 'border border-warning/25 bg-warning/10 text-warning',
            'crítica' => 'border border-purple-500/30 bg-purple-500/10 text-purple-500',
        ],
        'audit' => [
            'created' => 'border border-success/25 bg-success/10 text-success',
            'updated' => 'border border-warning/25 bg-warning/10 text-warning',
            'deleted' => 'border border-danger/25 bg-danger/10 text-danger',
        ],
    ];

    $labelMaps = [
        'audit' => [
            'created' => __('common.Criação'),
            'updated' => __('common.Alteração'),
            'deleted' => __('common.Eliminação'),
        ],
    ];

    $tone = $toneMaps[$kind][$value] ?? 'border border-[var(--border)] bg-[var(--surface-2)] text-[var(--text-soft)]';

    $sizeClasses = match ($size) {
        'xs' => 'rounded-md px-1.5 py-0.5',
        'badge' => 'rounded-lg px-2 py-0.5',
        'md' => 'rounded-xl px-3.5 py-2',
        default => 'rounded-lg px-2.5 py-1',
    };
@endphp

<span {{ $attributes->merge(['class' => trim('inline-flex items-center border text-xs font-bold uppercase tracking-tight ' . $sizeClasses . ' ' . $tone)]) }}>
    {{ $label ?? (isset($labelMaps[$kind][$value]) ? $labelMaps[$kind][$value] : $value) }}
</span>