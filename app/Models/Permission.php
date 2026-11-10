<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'permissions';

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'module',
        'description',
    ];

    /** @return BelongsToMany<UserProfile, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(UserProfile::class, 'permission_role', 'permission_id', 'role_id')->withTimestamps();
    }
}
