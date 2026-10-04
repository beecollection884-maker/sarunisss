<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassPlottingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SchoolClass|null $schoolClass */
        $schoolClass = $this->route('schoolClass');

        return [
            'homeroom_teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('teachers', 'id'),
                Rule::unique('school_classes', 'homeroom_teacher_id')
                    ->where(fn ($query) => $query->where('academic_year', $schoolClass?->academic_year))
                    ->ignore($schoolClass?->id),
            ],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'distinct', Rule::exists('students', 'id')],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'distinct', Rule::exists('subjects', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'homeroom_teacher_id.unique' => 'Guru ini sudah menjadi wali kelas di kelas lain.',
        ];
    }
}
