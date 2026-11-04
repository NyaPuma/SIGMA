<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendResetLinkRequest;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

final class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    /**
     * Sends the password recovery link/token to the provided email.
     */
    public function sendResetLink(SendResetLinkRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $token = $this->passwordResetService->createResetToken($email);

        // Look the user up with the same normalized email used for the token
        // so case differences (e.g. "RESET@example.com") still resolve.
        $user = User::where('email', strtolower(trim($email)))->first();

        if ($user) {
            Mail::to($user)->send(new PasswordResetMail($token));
        }

        return response()->json([
            'message' => __('messages.Email de recuperação enviado com sucesso.'),
            // A reset token is a credential: returning it everywhere the app is not the
            // developer's own machine lets anyone who can reach this endpoint take over
            // the account, so only `local` and `testing` may see it. Environments such as
            // staging or qa are not production and would otherwise leak it.
            'token' => app()->environment(['local', 'testing']) ? $token : null,
        ]);
    }

    /**
     * Performs the password change based on a valid token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = $this->passwordResetService->validateToken(
            $request->string('email')->toString(),
            $request->string('token')->toString()
        );

        if (! $user) {
            return response()->json([
                'message' => __('validation.Token inválido ou expirado.'),
            ], 422);
        }

        $this->passwordResetService->resetPassword($user, $request->string('password')->toString());

        return response()->json([
            'message' => __('messages.Password reposta com sucesso. Faça login.'),
        ]);
    }
}
