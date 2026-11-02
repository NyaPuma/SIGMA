<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string|null $code */
final class TicketStatus extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function booted(): void
    {
        self::creating(function (TicketStatus $status) {
            if ($status->code === null) {
                $slug = preg_replace('/[^a-zA-Z0-9]/', '_', $status->name ?? 'status') ?? '';
                $status->code = strtoupper(substr($slug, 0, 20));
            }
        });
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'notes',
        'active',
        'type_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    // --- RELATIONSHIPS ---

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'status_id');
    }
}
