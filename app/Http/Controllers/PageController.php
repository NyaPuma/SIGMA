<?php

namespace App\Http\Controllers;

use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Controller for static and utility pages
 * that previously used closures in routes.
 *
 * Consolidating here allows php artisan route:cache to work.
 */
final class PageController extends Controller
{
    /**
     * Home page (landing page).
     */
    public function home(): View
    {
        return view('main');
    }

    /**
     * Switch application language (legacy route).
     *
     * Kept for compatibility with the current topbar selector; the preference
     * is normalized to supported locales. The modern route is
     * POST /locale (LocaleController).
     */
    public function switchLang(Request $request, string $locale): RedirectResponse
    {
        $locale = LocaleService::sanitize($locale);

        // Store the language in session and set a permanent cookie
        session(['locale' => $locale]);
        $cookie = cookie()->forever('locale', $locale);

        // Check if the user is authenticated via session cookies
        $authToken = $request->cookie('api_token') ?: $request->cookie('auth_token');

        if ($authToken) {
            // Authenticated user — redirect to dashboard
            return redirect()->route('ui.index')->withCookie($cookie);
        }

        // Not authenticated — redirect to login page
        return redirect()->route('ui.login')->withCookie($cookie);
    }

    /**
     * Login view (authentication form).
     */
    public function login(): View
    {
        return view('ui.auth');
    }

    /**
     * Email test route (non-production environments only).
     */
    public function testEmail(): string
    {
        if (app()->environment('production')) {
            abort(404);
        }

        Mail::raw('Teste de comunicação com Mailtrap!', function (Message $message) {
            $message->to('teste@exemplo.com')
                ->subject('Teste do Sistema de Avarias');
        });

        return __('messages.E-mail enviado com sucesso!');
    }

    /**
     * Password reset form (API).
     */
    public function passwordResetForm(string $token): View
    {
        return view('ui.auth-reset', ['token' => $token]);
    }
}
