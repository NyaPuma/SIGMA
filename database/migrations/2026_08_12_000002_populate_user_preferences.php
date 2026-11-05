<?php

declare(strict_types=1);

use App\Models\UserPreference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Populates preferences for existing users.
     * Sets default values: language=pt, currency=EUR, date_format=d/m/Y
     */
    public function up(): void
    {
        // Get all users who don't have preferences defined
        $usersWithoutPrefs = DB::table('users')
            ->leftJoin('user_preferences', 'users.id', '=', 'user_preferences.user_id')
            ->whereNull('user_preferences.user_id')
            ->select('users.id')
            ->get();

        $now = now();

        foreach ($usersWithoutPrefs as $user) {
            UserPreference::create([
                'user_id' => $user->id,
                'language' => 'pt',
                'currency' => 'EUR',
                'date_format' => 'd/m/Y',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Intentionally does nothing.
     *
     * The rows this migration created are indistinguishable from preferences a user
     * later chose by hand — pt/EUR/d/m/Y are the values the preferences screen also
     * saves — so the previous match-on-defaults delete dropped live user settings on
     * every rollback. A column-only rollback is safe; deleting data is not.
     */
    public function down(): void
    {
        //
    }
};
