<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('slug', 100)->nullable()->after('name');
            $table->string('display_name', 150)->nullable()->after('slug');
            $table->string('area', 50)->nullable()->after('description'); // hr, finance, operations, admin, etc.
            $table->boolean('is_system')->default(false)->after('active');
        });

        // Backfill slug/display_name from existing name
        $profiles = DB::table('user_profiles')->get();
        foreach ($profiles as $profile) {
            $slug = $profile->name;
            $display = match ($slug) {
                'admin' => 'Administrador',
                'technician' => 'Técnico',
                'user' => 'Utilizador',
                default => ucfirst(str_replace(['_', '-'], ' ', $slug)),
            };
            DB::table('user_profiles')->where('id', $profile->id)->update([
                'slug' => $slug,
                'display_name' => $display,
                'is_system' => in_array($slug, ['admin', 'technician', 'user'], true),
                'area' => match ($slug) {
                    'admin' => 'admin',
                    'technician' => 'operations',
                    'user' => 'general',
                    default => null,
                },
            ]);
        }

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'display_name', 'area', 'is_system']);
        });
    }
};
