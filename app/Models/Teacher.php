<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\ClearsDashboardCaches;
use Illuminate\Support\Facades\Storage;

class Teacher extends Model
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
        'nik',
        'nip',
        'name',
        'is_subject_teacher',
        'birth_place',
        'birth_date',
        'gender',
        'religion',
        'employment_status',
        'position',
        'join_date',
        'last_education',
        'major',
        'university',
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
            'is_subject_teacher' => 'boolean',
            'birth_date' => 'date',
            'join_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class)->withTimestamps();
    }

    public function recordedSubjectAttendances(): HasMany
    {
        return $this->hasMany(SubjectAttendance::class, 'recorded_by_teacher_id');
    }

    public function recordedClassAttendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class, 'recorded_by_teacher_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    public function hasSubjectRole(): bool
    {
        if (array_key_exists('subjects_count', $this->attributes) || array_key_exists('teaching_assignments_count', $this->attributes)) {
            return max(
                (int) ($this->attributes['subjects_count'] ?? 0),
                (int) ($this->attributes['teaching_assignments_count'] ?? 0),
            ) > 0;
        }

        if ($this->relationLoaded('subjects') && $this->subjects->isNotEmpty()) {
            return true;
        }

        if ($this->relationLoaded('teachingAssignments') && $this->teachingAssignments->isNotEmpty()) {
            return true;
        }

        return $this->subjects()->exists() || $this->teachingAssignments()->exists();
    }

    public function hasHomeroomRole(): bool
    {
        if (array_key_exists('homeroom_classes_count', $this->attributes)) {
            return (int) $this->attributes['homeroom_classes_count'] > 0;
        }

        if ($this->relationLoaded('homeroomClasses')) {
            return $this->homeroomClasses->isNotEmpty();
        }

        return $this->homeroomClasses()->exists();
    }

    /**
     * @return array{key:string,label:string,status:string}
     */
    public function roleMeta(): array
    {
        $hasSubjectRole = $this->hasSubjectRole();
        $hasHomeroomRole = $this->hasHomeroomRole();

        if ($hasSubjectRole && $hasHomeroomRole) {
            return [
                'key' => 'guru-mapel-walikelas',
                'label' => 'Guru Mapel + Walikelas',
                'status' => 'Guru Mapel + Walikelas',
            ];
        }

        if ($hasSubjectRole) {
            return [
                'key' => 'guru-mapel',
                'label' => 'Guru Mapel',
                'status' => 'Guru Mapel',
            ];
        }

        if ($hasHomeroomRole) {
            return [
                'key' => 'walikelas',
                'label' => 'Walikelas',
                'status' => 'Walikelas',
            ];
        }

        return [
            'key' => 'guru',
            'label' => 'Guru',
            'status' => 'Guru',
        ];
    }
}
