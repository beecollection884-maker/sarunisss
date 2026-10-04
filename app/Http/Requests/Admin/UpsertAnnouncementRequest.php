<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $content = $this->input('content');
        $targetRoles = $this->input('target_roles');

        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'content' => is_string($content) ? trim($content) : $content,
            'target_roles' => $targetRoles !== null && $targetRoles !== '' ? (array) $targetRoles : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'content' => ['required', 'string', 'min:5'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:admin,guru_mapel,siswa,wakasek_kesiswaan,guru_piket,orang_tua'],
        ];
    }
}
