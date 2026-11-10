<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $display_name
 * @property string|null $description
 * @property string|null $area
 * @property bool $active
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class UserProfile extends Model
{
    /** @use HasFactory<UserProfileFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'user_profiles';

    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'description',
        'area',
        'active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    // --- RELATIONSHIPS ---

    /**
     * Users associated with this profile/role.
     */
    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'profile_id');
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_id', 'permission_id')->withTimestamps();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->name === 'admin' || $this->slug === 'admin') {
            return true;
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }
}
