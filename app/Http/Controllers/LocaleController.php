<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthUserResolver;
use App\Services\LocaleService;
use App\Services\PreferencesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController extends Controller
{
    /**
     * Saves the user's language preference.
     *
     * Writes to session and a permanent cookie; if the user is
     * authenticated, also persists to the `users.locale` column and
     * `user_preferences` table so the preferences middleware does not revert
     * the locale. Redirects back to the originating page.
     */
    public function switch(Request $request): RedirectResponse
    {
        $requestedLocaleRaw = $request->input('locale');
        $requestedLocale = is_string($requestedLocaleRaw) ? $requestedLocaleRaw : '';

        if (! LocaleService::isSupported($requestedLocale)) {
            return back()->withErrors(['locale' => __('common.Idioma não suportado.')]);
        }

        $locale = LocaleService::sanitize($requestedLocale);

        $request->session()->put('locale', $locale);
        $cookie = cookie()->forever('locale', $locale);

        $user = $request->user() ?? $request->user('api') ?? AuthUserResolver::fromRequest($request);

        if ($user) {
            $user->forceFill(['locale' => $locale])->save();

            PreferencesService::saveForUser($user, [
                'language' => $locale,
                'currency' => PreferencesService::getCurrency($request),
                'date_format' => PreferencesService::getDateFormat($request),
                'time_format' => PreferencesService::getTimeFormat($request),
                'number_format' => PreferencesService::getNumberFormat($request),
            ]);
        }

        return back()->withCookie($cookie);
    }
}
