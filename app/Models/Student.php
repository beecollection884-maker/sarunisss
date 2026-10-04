<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\ClearsDashboardCaches;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    use HasFactory, ClearsDashboardCaches;

    /**
     * @var array<int, string>
     */
    protected $appends = ['photo_url'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'parent_user_id',
        'school_class_id',
        'nik',
        'nisn',
        'name',
        'gender',
        'birth_date',
        'phone',
        'address',
        'photo_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subjectAttendances(): HasMany
    {
        return $this->hasMany(SubjectAttendance::class);
    }

    public function classAttendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class);
    }

    public function detailSiswa(): HasOne
    {
        return $this->hasOne(StudentDetail::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(StudentNote::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(StudentViolation::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }
}
