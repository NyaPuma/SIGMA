<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $table = 'leave_types';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'days_per_year',
        'paid',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'days_per_year' => 'integer',
            'paid' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
