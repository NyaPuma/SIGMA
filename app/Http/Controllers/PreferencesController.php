<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthUserResolver;
use App\Services\LocaleService;
use App\Services\PreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller to manage user preferences.
 *
 * Allows the user to configure independently:
 * - Language
 * - Currency
 * - Date format
 * - Time format
 * - Number format
 */
final class PreferencesController extends Controller
{
    /**
     * Shows the preferences form.
     */
    public function edit(Request $request): View
    {
        $prefs = PreferencesService::current($request);

        return view('preferences.edit', [
            'currentLanguage' => $prefs['language'],
            'currentCurrency' => $prefs['currency'],
            'currentDateFormat' => $prefs['date_format'],
            'currentTimeFormat' => $prefs['time_format'],
            'currentNumberFormat' => $prefs['number_format'],
            'supportedLocales' => LocaleService::all(),
            'supportedCurrencies' => PreferencesService::supportedCurrencies(),
            'supportedDateFormats' => PreferencesService::supportedDateFormats(),
            'supportedTimeFormats' => PreferencesService::supportedTimeFormats(),
            'supportedNumberFormats' => PreferencesService::supportedNumberFormats(),
        ]);
    }

    /**
     * Resolves the authenticated user (web or token guard).
     *
     * Preference routes do not belong to the `custom.auth` group, so the
     * 'web' guard is not populated; the user is resolved from tokens
     * present in the request when needed.
     */
    private function resolveUser(Request $request): ?User
    {
        return $request->user() ?? AuthUserResolver::fromRequest($request);
    }

    /**
     * Source preferences (DB user or session), to preserve fields
     * not being updated in this request.
     *
     * @return array<string, string>
     */
    private function currentPreferences(Request $request, ?User $user): array
    {
        return $user
            ? PreferencesService::forUser($user)
            : PreferencesService::fromSession($request);
    }

    /**
     * Persists preferences (DB for authenticated users, session otherwise)
     * and syncs locale in the session.
     *
     * @param  array<string, mixed>  $preferences
     */
    private function persist(Request $request, ?User $user, array $preferences, string $locale): void
    {
        if ($user) {
            PreferencesService::saveForUser($user, $preferences);

            // Also update user locale column for SetLocaleMiddleware compatibility
            $user->forceFill(['locale' => $locale])->save();
        } else {
            PreferencesService::saveToSession($request, $preferences);
        }

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }
    }

    /**
     * Updates user language.
     */
    public function updateLanguage(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{language: string} $validated */
        $validated = $request->validate([
            'language' => 'required|string',
        ]);

        $locale = LocaleService::sanitize($validated['language']);

        $user = $this->resolveUser($request);
        $current = $this->currentPreferences($request, $user);

        $preferences = [
            'language' => $locale,
            'currency' => $current['currency'],
            'date_format' => $current['date_format'],
            'time_format' => $current['time_format'],
            'number_format' => $current['number_format'],
        ];

        $this->persist($request, $user, $preferences, $locale);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'language' => $locale,
                'message' => __('preferences.Idioma atualizado com sucesso.'),
            ]);
        }

        return back()->with('success', __('preferences.Idioma atualizado com sucesso.'));
    }

    /**
     * Updates user currency.
     */
    public function updateCurrency(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{currency: string} $validated */
        $validated = $request->validate([
            'currency' => 'required|string|size:3',
        ]);

        $user = $this->resolveUser($request);
        $current = $this->currentPreferences($request, $user);

        $preferences = [
            'language' => $current['language'],
            'currency' => $validated['currency'],
            'date_format' => $current['date_format'],
            'time_format' => $current['time_format'],
            'number_format' => $current['number_format'],
        ];

        $this->persist($request, $user, $preferences, $current['language']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'currency' => $validated['currency'],
                'message' => __('preferences.Moeda atualizada com sucesso.'),
            ]);
        }

        return back()->with('success', __('preferences.Moeda atualizada com sucesso.'));
    }

    /**
     * Updates user date format.
     */
    public function updateDateFormat(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{date_format: string} $validated */
        $validated = $request->validate([
            'date_format' => 'required|string',
        ]);

        $user = $this->resolveUser($request);
        $current = $this->currentPreferences($request, $user);

        $preferences = [
            'language' => $current['language'],
            'currency' => $current['currency'],
            'date_format' => $validated['date_format'],
            'time_format' => $current['time_format'],
            'number_format' => $current['number_format'],
        ];

        $this->persist($request, $user, $preferences, $current['language']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'date_format' => $validated['date_format'],
                'message' => __('preferences.Formato de data atualizado com sucesso.'),
            ]);
        }

        return back()->with('success', __('preferences.Formato de data atualizado com sucesso.'));
    }

    /**
     * Updates user time format.
     */
    public function updateTimeFormat(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{time_format: string} $validated */
        $validated = $request->validate([
            'time_format' => 'required|string',
        ]);

        $user = $this->resolveUser($request);
        $current = $this->currentPreferences($request, $user);

        $preferences = [
            'language' => $current['language'],
            'currency' => $current['currency'],
            'date_format' => $current['date_format'],
            'time_format' => $validated['time_format'],
            'number_format' => $current['number_format'],
        ];

        $this->persist($request, $user, $preferences, $current['language']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'time_format' => $validated['time_format'],
                'message' => __('preferences.Formato de hora atualizado com sucesso.'),
            ]);
        }

        return back()->with('success', __('preferences.Formato de hora atualizado com sucesso.'));
    }

    /**
     * Updates user number format.
     */
    public function updateNumberFormat(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{number_format: string} $validated */
        $validated = $request->validate([
            'number_format' => 'required|string',
        ]);

        $user = $this->resolveUser($request);
        $current = $this->currentPreferences($request, $user);

        $preferences = [
            'language' => $current['language'],
            'currency' => $current['currency'],
            'date_format' => $current['date_format'],
            'time_format' => $current['time_format'],
            'number_format' => $validated['number_format'],
        ];

        $this->persist($request, $user, $preferences, $current['language']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'number_format' => $validated['number_format'],
                'message' => __('preferences.Formato de números atualizado com sucesso.'),
            ]);
        }

        return back()->with('success', __('preferences.Formato de números atualizado com sucesso.'));
    }

    /**
     * Updates all preferences at once.
     */
    public function updateAll(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{language: string, currency: string, date_format: string, time_format: string, number_format: string|null} $validated */
        $validated = $request->validate([
            'language' => 'required|string',
            'currency' => 'required|string|size:3',
            'date_format' => 'required|string',
            'time_format' => 'required|string',
            'number_format' => 'nullable|string',
        ]);

        $user = $this->resolveUser($request);

        $this->persist($request, $user, $validated, $validated['language']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'preferences' => $validated,
                'message' => __('preferences.Preferências atualizadas com sucesso.'),
            ]);
        }

        return back()->with('success', __('preferences.Preferências atualizadas com sucesso.'));
    }
}
