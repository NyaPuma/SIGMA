<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type', 50)->default('permanent'); // permanent, fixed_term, temporary, intern, contractor
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('salary_amount', 12, 2)->nullable();
            $table->string('salary_currency', 10)->default('EUR');
            $table->decimal('work_hours_per_week', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['employee_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');
    }
};
