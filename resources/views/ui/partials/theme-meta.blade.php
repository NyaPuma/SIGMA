{{--
|--------------------------------------------------------------------------
| Theme meta (mode, active theme, presets)
|--------------------------------------------------------------------------
| Provides JS (early-theme.js / core/theme.js) with the authoritative
| light/dark mode (from saved theme) and the list of presets for the
| toggle button to switch to the equivalent in the same family.
| --}}
@php
    $themePresets = app(\App\Services\ThemePresetService::class);
    $themeUser = $user ?? auth()->user();
    $themeRole = $themeUser?->profile?->name ?? null;
    $themeActiveId = $themePresets->effectiveThemeId($themeUser?->theme);
    $themeMode = 'light';

    try {
        $themeMode = $themePresets->mode($themeUser?->theme);
    } catch (\Throwable $e) {
        // theme preset data not yet available (e.g. migrations pending)
    }
@endphp
<meta name="theme-mode" content="{{ $themeMode }}">
<meta name="active-theme" content="{{ $themeActiveId }}">
@if($themeRole)
    <meta name="user-role" content="{{ $themeRole }}">
@endif
<script id="themePresetsData" type="application/json">@json($themePresets->all())</script>
