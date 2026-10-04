<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'level' => is_string($this->level) ? strtoupper(trim($this->level)) : $this->level,
            'academic_year' => is_string($this->academic_year) ? trim($this->academic_year) : $this->academic_year,
            'description' => is_string($this->description) ? trim($this->description) : $this->description,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SchoolClass|null $schoolClass */
        $schoolClass = $this->route('schoolClass');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('school_classes', 'name')
                    ->where(fn ($query) => $query->where('academic_year', $this->input('academic_year')))
                    ->ignore($schoolClass?->id),
            ],
            'level' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9 .-]+$/'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'homeroom_teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('teachers', 'id'),
                Rule::unique('school_classes', 'homeroom_teacher_id')
                    ->where(fn ($query) => $query->where('academic_year', $this->input('academic_year')))
                    ->ignore($schoolClass?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
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
