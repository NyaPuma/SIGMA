<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class PasswordResetService
{
    /**
     * Creates and stores a secure password reset token for the provided email.
     */
    public function createResetToken(string $email): string
    {
        $normalizedEmail = strtolower(trim($email));
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $normalizedEmail],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        return $token;
    }

    /**
     * Validates the password reset token, checking expiration and match.
     */
    public function validateToken(string $email, string $token): ?User
    {
        $normalizedEmail = strtolower(trim($email));

        $record = DB::table('password_reset_tokens')
            ->where('email', $normalizedEmail)
            ->latest('created_at')
            ->first();

        if (! $record || ! Hash::check($token, is_string($record->token) ? $record->token : '')) {
            return null;
        }

        if ($record->created_at) {
            $createdAt = Carbon::parse(is_string($record->created_at) ? $record->created_at : null);

            if ($createdAt->diffInMinutes(now()) > 60) {
                DB::table('password_reset_tokens')->where('email', $normalizedEmail)->delete();

                return null;
            }
        }

        return User::where('email', $normalizedEmail)->first();
    }

    /**
     * Resets the user password, revokes associated API tokens, and removes the recovery record.
     */
    public function resetPassword(User $user, string $password): void
    {
        $user->password = Hash::make($password);
        $user->api_token = null;
        $user->save();

        DB::table('password_reset_tokens')->where('email', strtolower(trim($user->email)))->delete();
    }
}
