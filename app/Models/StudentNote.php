<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\ClearsDashboardCaches;

class StudentNote extends Model
{
    use ClearsDashboardCaches;

    protected $fillable = [
        'student_id',
        'teacher_id',
        'user_id',
        'title',
        'category',
        'note',
        'follow_up_at',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'follow_up_at' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
