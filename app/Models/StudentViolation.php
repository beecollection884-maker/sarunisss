<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\ClearsDashboardCaches;

class StudentViolation extends Model
{
    use HasFactory, ClearsDashboardCaches;

    protected $fillable = [
        'student_id',
        'reported_by_id',
        'violation_date',
        'violation_type',
        'description',
        'points',
        'action_taken',
    ];

    protected $casts = [
        'violation_date' => 'date',
        'points' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }
}
