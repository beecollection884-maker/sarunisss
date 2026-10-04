<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleGeneration extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'academic_year',
        'generated_by_user_id',
        'total_classes',
        'total_assignments',
        'successful_slots',
        'failed_slots',
        'conflicts_detected',
        'result_data',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_data' => 'json',
            'total_classes' => 'integer',
            'total_assignments' => 'integer',
            'successful_slots' => 'integer',
            'failed_slots' => 'integer',
            'conflicts_detected' => 'integer',
        ];
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * Get success rate
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_assignments === 0) {
            return 0;
        }

        return round(($this->successful_slots / $this->total_assignments) * 100, 2);
    }
}
