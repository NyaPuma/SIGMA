<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ThemePresetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemePresetService $themePresets,
    ) {}

    public function customCss(Request $request): Response
    {
        $themeId = $this->effectiveThemeId($this->resolveCssUser($request));
        $values = $this->themePresets->valuesFor($themeId);
        $css = $this->buildCss($values);
        $etag = '"'.sha1($css).'"';

        if ($request->headers->get('if-none-match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'no-cache, must-revalidate');
        }

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'ETag' => $etag,
        ]);
    }

    /**
     * Switches to the equivalent preset (light <-> dark of the same family)
     * and persists it as the current user's personal theme — used by the
     * panel's mode button. Available to any authenticated user.
     */
    public function switchTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(array_keys($this->themePresets->all()))],
        ]);

        $preset = $this->themePresets->applyForUser($this->authenticatedUser($request), $validated['theme']);

        return response()->json([
            'ok' => true,
            'theme' => $validated['theme'],
            'mode' => $preset['mode'],
            'values' => $this->themePresets->valuesFor($validated['theme']),
        ]);
    }

    /**
     * Effective preset id for a given user (or guest default).
     */
    private function effectiveThemeId(?User $user): string
    {
        return $this->themePresets->effectiveThemeId($user?->theme);
    }

    /**
     * Resolves the authenticated user for the public CSS route.
     *
     * `/theme/custom.css` is intentionally public (the guest/auth pages link
     * it), so it bypasses `custom.auth`. To still serve the correct per-user
     * theme we resolve the user from the same token candidates without a
     * redirect: bearer/X-Auth-Token header, api/auth cookies, or session.
     */
    private function resolveCssUser(Request $request): ?User
    {
        $candidates = [];

        if ($request->header('X-Auth-Token')) {
            $candidates[] = $request->header('X-Auth-Token');
        }
        if ($request->bearerToken()) {
            $candidates[] = $request->bearerToken();
        }
        if ($request->cookie('api_token')) {
            $candidates[] = $request->cookie('api_token');
        }
        if ($request->cookie('auth_token')) {
            $candidates[] = $request->cookie('auth_token');
        }
        try {
            if ($request->session()->get('api_token')) {
                $candidates[] = (string) $request->session()->get('api_token');
            }
        } catch (\Throwable $e) {
            // session unavailable — continue with the other candidates
        }

        $uniqueTokens = array_unique(array_filter($candidates, static fn (mixed $token): bool => is_string($token)));

        if ($uniqueTokens === []) {
            return null;
        }

        $hashes = array_map(static fn (string $token): string => User::hashToken($token), $uniqueTokens);
        $hashSet = array_flip($hashes);

        $users = User::whereIn('api_token', $hashes)
            ->where('active', true)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('api_token');

        foreach ($hashSet as $hash => $_) {
            if (isset($users[$hash])) {
                return $users[$hash];
            }
        }

        return null;
    }

    /**
     * Cache-buster for the dynamic CSS link. Because the css is now per-user
     * (derived from `users.theme`), the busting value must be computed from
     * the effective theme id — pass the id resolved in the blade template.
     */
    public static function cacheBuster(?string $themeId = null): string
    {
        $service = app(ThemePresetService::class);
        $id = $service->effectiveThemeId($themeId);

        try {
            $raw = implode('', $service->valuesFor($id));
        } catch (\Throwable $e) {
            return 'default';
        }

        return substr(sha1($id.$raw), 0, 12);
    }

    private function buildCss(array $settings): string
    {
        if (isset($settings['--color-primary'])) {
            $rgb = $this->hexToRgb($settings['--color-primary']);
            $settings['--color-primary-light'] = sprintf('rgba(%d, %d, %d, 0.12)', $rgb[0], $rgb[1], $rgb[2]);
            $settings['--color-primary-hover'] = $this->darkenHex($settings['--color-primary'], 0.12);
            $settings['--color-on-primary'] = $this->readableOnColor($settings['--color-primary']);
        }

        $lines = [];

        foreach ($settings as $key => $value) {
            if (! str_starts_with($key, '--')) {
                continue;
            }

            $lines[] = sprintf('    %s: %s;', $key, $value);
        }

        return ":root {\n".implode("\n", $lines)."\n}\n";
    }

    /**
     * Picks readable text color (pure black or white) over a given color,
     * ensuring WCAG contrast >= 4.5:1 for any background.
     */
    private function readableOnColor(string $hex): string
    {
        $lum = $this->luminance($hex);
        $black = ($lum + 0.05) / 0.05;
        $white = 1.05 / ($lum + 0.05);

        return $white >= $black ? '#ffffff' : '#000000';
    }

    private function luminance(string $hex): float
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $convert = fn (int $channel): float => $channel / 255 <= 0.03928
            ? $channel / 255 / 12.92
            : pow((($channel / 255) + 0.055) / 1.055, 2.4);

        return 0.2126 * $convert($r) + 0.7152 * $convert($g) + 0.0722 * $convert($b);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function darkenHex(string $hex, float $amount): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $darken = fn (int $channel): int => max(0, min(255, (int) round($channel * (1 - $amount))));

        return sprintf('#%02x%02x%02x', $darken($r), $darken($g), $darken($b));
    }
}
