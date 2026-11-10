<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Models\UserProfile;

final readonly class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        $this->ensureValidProfile($user);
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        $this->ensureValidProfile($user);
    }

    /**
     * Ensures the user has a valid profile, assigning the default profile if missing.
     */
    private function ensureValidProfile(User $user): void
    {
        if ($user->profile_id) {
            $profileExists = UserProfile::query()->whereKey($user->profile_id)->exists();

            if ($profileExists) {
                return;
            }
        }

        // Ensure the default profile exists and assign its ID to the user
        $defaultProfile = UserProfile::firstOrCreate([
            'name' => UserRoleEnum::User->value,
        ]);

        $user->profile_id = $defaultProfile->id;
    }
}
