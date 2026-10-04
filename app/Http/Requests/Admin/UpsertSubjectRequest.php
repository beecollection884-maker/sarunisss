<?php

namespace App\Http\Requests\Admin;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->code) ? strtoupper(trim($this->code)) : $this->code,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'lesson_hours' => is_numeric($this->lesson_hours) ? (int) $this->lesson_hours : null,
            'description' => is_string($this->description) ? trim($this->description) : $this->description,
            'day_of_week' => $this->filled('day_of_week') ? (int) $this->day_of_week : null,
            'start_time' => $this->filled('start_time') ? trim($this->start_time) : null,
            'end_time' => $this->filled('end_time') ? trim($this->end_time) : null,
            'school_class_id' => $this->filled('school_class_id') ? (int) $this->school_class_id : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Subject|null $subject */
        $subject = $this->route('subject');

        return [
            'code' => ['required', 'string', 'min:2', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('subjects', 'code')->ignore($subject?->id)],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'lesson_hours' => ['nullable', 'integer', 'min:1', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['integer', 'distinct', Rule::exists('teachers', 'id')],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'distinct', Rule::exists('school_classes', 'id')],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
        ];
    }
}
