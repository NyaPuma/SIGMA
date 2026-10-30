<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRoleEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property-read UserProfile|null $profile
 * @property-read int $tickets_ativos
 * @property-read int $active_tickets
 * @property-read int|null $in_progress_tickets
 * @property Carbon|null $token_created_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $locked_until
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'locale',
        'theme',
        'password',
        'profile_id',
        'active',
        'api_token',
        'token_created_at',
        'remember_token',
        'avatar_path',
        'avatar_disk',
        'email_verified_at',
        'password_changed_at',
        'last_login_at',
        'last_login_ip',
        'login_attempts',
        'locked_until',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'token_created_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'active' => 'boolean',
            'login_attempts' => 'integer',
            'password' => 'hashed',
        ];
    }

    // --- ROLE / PROFILE HELPERS ---

    public static function getAvailableRoles(): array
    {
        return array_column(UserRoleEnum::cases(), 'value');
    }

    public static function isValidProfile(string $name): bool
    {
        return UserRoleEnum::tryFrom($name) !== null;
    }

    public function isAdmin(): bool
    {
        return $this->profile?->name === UserRoleEnum::Admin->value;
    }

    public function isTechnician(): bool
    {
        return $this->profile?->name === UserRoleEnum::Technician->value;
    }

    public function isCommonUser(): bool
    {
        return $this->profile?->name === UserRoleEnum::User->value;
    }

    // --- RELATIONSHIPS ---

    /**
     * Tickets reported/created by this user.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    /**
     * Tickets assigned to this user (as technician).
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /**
     * Role profile associated with this user.
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'profile_id');
    }

    // --- SCOPES ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeTechnicians(Builder $query): Builder
    {
        return $query->whereHas('profile', static function (Builder $q): void {
            $q->where('name', UserRoleEnum::Technician->value);
        });
    }

    // --- SECURITY & TOKEN HELPERS ---

    /**
     * Generate HMAC SHA256 hash for API tokens.
     */
    public static function hashToken(string $token): string
    {
        $appKey = config('app.key');

        if (! is_string($appKey) || $appKey === '') {
            throw new \RuntimeException('Application key is not configured.');
        }

        return hash_hmac('sha256', $token, $appKey);
    }
}
