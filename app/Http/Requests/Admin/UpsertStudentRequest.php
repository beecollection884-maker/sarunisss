<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpsertStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trimOrNull = function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        };

        $payload = [
            'nik' => $trimOrNull($this->nik) ?? $this->nik,
            'nisn' => $trimOrNull($this->nisn),
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'gender' => is_string($this->gender) ? strtoupper(trim($this->gender)) : $this->gender,
            'phone' => $trimOrNull($this->phone),
            'address' => $trimOrNull($this->address),
        ];

        if (Arr::has($this->all(), 'detail_siswa')) {
            $detailSiswa = $this->input('detail_siswa', []);

            if (! is_array($detailSiswa)) {
                $detailSiswa = [];
            }

            $detailFields = [
                'religion',
                'birth_place',
                'address_street',
                'address_village',
                'address_district',
                'address_province',
                'address_city',
                'father_name',
                'father_education',
                'father_occupation',
                'mother_name',
                'mother_education',
                'mother_occupation',
                'parent_address',
                'parent_province',
                'parent_city',
                'postal_code',
                'parent_phone',
                'previous_school',
            ];

            $normalizedDetailSiswa = [];

            foreach ($detailFields as $field) {
                $normalizedDetailSiswa[$field] = $trimOrNull($detailSiswa[$field] ?? null);
            }

            $payload['detail_siswa'] = $normalizedDetailSiswa;
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Student|null $student */
        $student = $this->route('student');

        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('students', 'user_id')->ignore($student?->id)],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'nik' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[0-9A-Za-z]+$/', Rule::unique('students', 'nik')->ignore($student?->id)],
            'nisn' => ['nullable', 'string', 'regex:/^[0-9]{1,30}$/', Rule::unique('students', 'nisn')->ignore($student?->id)],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'min:10', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'detail_siswa' => ['sometimes', 'array'],
            'detail_siswa.religion' => ['nullable', 'string', 'max:100'],
            'detail_siswa.birth_place' => ['nullable', 'string', 'max:255'],
            'detail_siswa.address_street' => ['nullable', 'string', 'max:1000'],
            'detail_siswa.address_village' => ['nullable', 'string', 'max:255'],
            'detail_siswa.address_district' => ['nullable', 'string', 'max:255'],
            'detail_siswa.address_province' => ['nullable', 'string', 'max:255'],
            'detail_siswa.address_city' => ['nullable', 'string', 'max:255'],
            'detail_siswa.father_name' => ['nullable', 'string', 'max:255'],
            'detail_siswa.father_education' => ['nullable', 'string', 'max:255'],
            'detail_siswa.father_occupation' => ['nullable', 'string', 'max:255'],
            'detail_siswa.mother_name' => ['nullable', 'string', 'max:255'],
            'detail_siswa.mother_education' => ['nullable', 'string', 'max:255'],
            'detail_siswa.mother_occupation' => ['nullable', 'string', 'max:255'],
            'detail_siswa.parent_address' => ['nullable', 'string', 'max:1000'],
            'detail_siswa.parent_province' => ['nullable', 'string', 'max:255'],
            'detail_siswa.parent_city' => ['nullable', 'string', 'max:255'],
            'detail_siswa.postal_code' => ['nullable', 'string', 'regex:/^[0-9]{4,10}$/'],
            'detail_siswa.parent_phone' => ['nullable', 'string', 'min:10', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'detail_siswa.previous_school' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }
}
