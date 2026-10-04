<?php

namespace App\Http\Requests\Admin;

use App\Models\TeachingAssignment;
use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'academic_year' => is_string($this->academic_year) ? trim($this->academic_year) : $this->academic_year,
            'room' => is_string($this->room) ? trim($this->room) : $this->room,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:50'],
            'substitute_teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var TeachingAssignment|null $teachingAssignment */
            $teachingAssignment = $this->route('teachingAssignment');

            $teacher = Teacher::query()
                ->with('subjects:id')
                ->find($this->integer('teacher_id'));

            if ($teacher !== null && $teacher->subjects->isNotEmpty() && !$teacher->subjects->contains('id', $this->integer('subject_id'))) {
                $validator->errors()->add('subject_id', 'Mata pelajaran tidak termasuk daftar mapel yang dapat diajarkan guru ini.');
                return;
            }

            $overlapQuery = TeachingAssignment::query()
                ->where('academic_year', $this->input('academic_year'))
                ->where('day_of_week', $this->integer('day_of_week'))
                ->where('start_time', '<', $this->input('end_time'))
                ->where('end_time', '>', $this->input('start_time'));

            if ($teachingAssignment !== null) {
                $overlapQuery->whereKeyNot($teachingAssignment->id);
            }

            $teacherId = $this->integer('teacher_id');
            $substituteTeacherId = $this->integer('substitute_teacher_id');

            $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
            $dayName = $dayNames[$this->integer('day_of_week')] ?? $this->integer('day_of_week');

            // Check if main teacher is busy (either teaching or substituting)
            $teacherConflictRecord = (clone $overlapQuery)
                ->where(function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId)
                          ->orWhere('substitute_teacher_id', $teacherId);
                })
                ->with(['subject', 'schoolClass'])
                ->first();

            if ($teacherConflictRecord) {
                $conflictSubject = $teacherConflictRecord->subject?->name ?? '-';
                $conflictClass = $teacherConflictRecord->schoolClass?->name ?? '-';
                $conflictTime = substr($teacherConflictRecord->start_time, 0, 5) . '-' . substr($teacherConflictRecord->end_time, 0, 5);
                $validator->errors()->add(
                    'teacher_id',
                    "⚠️ TABRAKAN JADWAL: Guru ini sudah mengajar mapel \"{$conflictSubject}\" di kelas {$conflictClass} pada hari {$dayName} jam {$conflictTime}. Satu guru tidak boleh memiliki 2 jadwal di waktu yang bertabrakan."
                );
            }

            // Check if substitute teacher is busy (either teaching or substituting)
            if ($substituteTeacherId) {
                if ($teacherId === $substituteTeacherId) {
                    $validator->errors()->add('substitute_teacher_id', 'Guru utama dan guru pengganti tidak boleh orang yang sama.');
                } else {
                    $substituteConflictRecord = (clone $overlapQuery)
                        ->where(function ($query) use ($substituteTeacherId) {
                            $query->where('teacher_id', $substituteTeacherId)
                                  ->orWhere('substitute_teacher_id', $substituteTeacherId);
                        })
                        ->with(['subject', 'schoolClass'])
                        ->first();

                    if ($substituteConflictRecord) {
                        $conflictSubject = $substituteConflictRecord->subject?->name ?? '-';
                        $conflictClass = $substituteConflictRecord->schoolClass?->name ?? '-';
                        $conflictTime = substr($substituteConflictRecord->start_time, 0, 5) . '-' . substr($substituteConflictRecord->end_time, 0, 5);
                        $validator->errors()->add(
                            'substitute_teacher_id',
                            "⚠️ TABRAKAN JADWAL: Guru pengganti sudah mengajar mapel \"{$conflictSubject}\" di kelas {$conflictClass} pada hari {$dayName} jam {$conflictTime}."
                        );
                    }
                }
            }

            $classConflictRecord = (clone $overlapQuery)
                ->where('school_class_id', $this->integer('school_class_id'))
                ->with(['subject', 'teacher'])
                ->first();

            if ($classConflictRecord) {
                $conflictSubject = $classConflictRecord->subject?->name ?? '-';
                $conflictTeacher = $classConflictRecord->teacher?->name ?? '-';
                $conflictTime = substr($classConflictRecord->start_time, 0, 5) . '-' . substr($classConflictRecord->end_time, 0, 5);
                $validator->errors()->add(
                    'school_class_id',
                    "⚠️ TABRAKAN KELAS: Kelas ini sudah memiliki jadwal mapel \"{$conflictSubject}\" oleh {$conflictTeacher} pada hari {$dayName} jam {$conflictTime}. Tidak boleh ada 2 mapel di kelas yang sama pada waktu bertabrakan."
                );
            }
        });
    }
}
