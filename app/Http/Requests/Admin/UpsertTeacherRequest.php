<?php

namespace App\Http\Requests\Admin;

use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => is_string($this->nik) ? trim($this->nik) : $this->nik,
            'nip' => is_string($this->nip) ? trim($this->nip) : $this->nip,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'birth_place' => is_string($this->birth_place) ? trim($this->birth_place) : $this->birth_place,
            'gender' => is_string($this->gender) ? strtoupper(trim($this->gender)) : $this->gender,
            'religion' => is_string($this->religion) ? trim($this->religion) : $this->religion,
            'employment_status' => is_string($this->employment_status) ? trim($this->employment_status) : $this->employment_status,
            'position' => is_string($this->position) ? trim($this->position) : $this->position,
            'last_education' => is_string($this->last_education) ? trim($this->last_education) : $this->last_education,
            'major' => is_string($this->major) ? trim($this->major) : $this->major,
            'university' => is_string($this->university) ? trim($this->university) : $this->university,
            'phone' => is_string($this->phone) ? trim($this->phone) : $this->phone,
            'address' => is_string($this->address) ? trim($this->address) : $this->address,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Teacher|null $teacher */
        $teacher = $this->route('teacher');

        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('teachers', 'user_id')->ignore($teacher?->id)],
            'nik' => ['nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9]+$/', Rule::unique('teachers', 'nik')->ignore($teacher?->id)],
            'nip' => ['required', 'string', 'min:6', 'max:30', 'regex:/^[0-9A-Za-z.\/-]+$/', Rule::unique('teachers', 'nip')->ignore($teacher?->id)],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'is_subject_teacher' => ['nullable', 'boolean'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'religion' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'join_date' => ['nullable', 'date', 'before_or_equal:today'],
            'last_education' => ['nullable', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'university' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'min:10', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }
}
