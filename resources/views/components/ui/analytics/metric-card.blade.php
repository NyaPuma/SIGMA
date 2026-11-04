{{--
|--------------------------------------------------------------------------
| Metric Card Component
|--------------------------------------------------------------------------
|
| Statistical card for displaying metrics with title, value, icon and description.
| • 100% free of inline CSS or JS.
| • CSS variable syntax corrected and safe for Tailwind.
| • Global attribute support via $attributes.
| • Defensive checks for optional elements.
|
--}}

@props([
    'eyebrow' => null,
    'value_id' => null,
    'default_value' => '--',
    'description' => null,
    'icon_bg_class' => 'bg-primary/10',
])

<article {{ $attributes->class(['rounded-3xl border border-[var(--border)] bg-[var(--surface)] p-7 shadow-xs']) }}>
    <div class="flex items-center justify-between">
        <div>
            @if($eyebrow)
                <x-ui.text.eyebrow as="p">{{ $eyebrow }}</x-ui.text.eyebrow>
            @endif

            <h3
                @if($value_id) id="{{ $value_id }}" @endif
                class="mt-4 text-4xl font-black text-[var(--text)]"
            >
                {{ $default_value }}
            </h3>
        </div>

        @if(! $slot->isEmpty())
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $icon_bg_class }}" aria-hidden="true">
                {{ $slot }}
            </div>
        @endif
    </div>

    @if($description)
        <p class="mt-6 text-sm text-[var(--text-soft)]">{{ $description }}</p>
    @endif
</article>
