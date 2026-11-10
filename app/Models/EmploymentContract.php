<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentContract extends Model
{
    use SoftDeletes;

    protected $table = 'employment_contracts';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'salary_amount',
        'salary_currency',
        'work_hours_per_week',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'salary_amount' => 'decimal:2',
            'work_hours_per_week' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
