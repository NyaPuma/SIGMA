<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\UserProfile;
use Illuminate\Database\Seeder;

class UserProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            ['name' => 'admin', 'slug' => 'admin', 'display_name' => 'Administrador', 'area' => 'admin', 'is_system' => true],
            ['name' => 'technician', 'slug' => 'technician', 'display_name' => 'Técnico', 'area' => 'operations', 'is_system' => true],
            ['name' => 'user', 'slug' => 'user', 'display_name' => 'Utilizador', 'area' => 'general', 'is_system' => true],
        ];

        foreach ($profiles as $profile) {
            UserProfile::query()->updateOrCreate(
                ['name' => $profile['name']],
                array_merge($profile, ['active' => true])
            );
        }
    }
}
