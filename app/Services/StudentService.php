<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentService
{
    public function __construct(
        protected UserRoleService $userRoleService,
        protected ProfilePhotoService $profilePhotoService,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Student::query()
            ->with(['user', 'parentUser', 'schoolClass', 'detailSiswa'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Student
    {
        /** @var UploadedFile|null $photo */
        $photo = $data['photo'] ?? null;
        $detailSiswaData = $this->extractDetailSiswaData($data);
        unset($data['photo'], $data['remove_photo']);

        $storedPhotoPath = null;

        try {
            return DB::transaction(function () use ($data, $photo, $detailSiswaData, &$storedPhotoPath): Student {
                if ($photo !== null) {
                    $storedPhotoPath = $this->profilePhotoService->store($photo, 'students');
                    $data['photo_path'] = $storedPhotoPath;
                }

                $student = Student::create($data);
                $this->syncDetailSiswa($student, $detailSiswaData);
                $this->syncParentUser($student);
                $student->refresh()->load('user');

                $this->userRoleService->syncStudentRole($student);

                return $student->load(['user', 'parentUser', 'schoolClass', 'detailSiswa']);
            });
        } catch (\Throwable $throwable) {
            $this->profilePhotoService->delete($storedPhotoPath);

            throw $throwable;
        }
    }

    public function update(Student $student, array $data): Student
    {
        /** @var UploadedFile|null $photo */
        $photo = $data['photo'] ?? null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        $detailSiswaData = $this->extractDetailSiswaData($data);
        unset($data['photo'], $data['remove_photo']);

        $oldPhotoPath = $student->photo_path;
        $newPhotoPath = $oldPhotoPath;
        $storedPhotoPath = null;

        try {
            if ($photo !== null) {
                $storedPhotoPath = $this->profilePhotoService->store($photo, 'students');
                $newPhotoPath = $storedPhotoPath;
            } elseif ($removePhoto) {
                $newPhotoPath = null;
            }

            $updatedStudent = DB::transaction(function () use ($student, $data, $detailSiswaData, $newPhotoPath): Student {
                $oldUserId = $student->user_id;

                $student->update([
                    ...$data,
                    'photo_path' => $newPhotoPath,
                ]);
                $this->syncDetailSiswa($student, $detailSiswaData);
                $this->syncParentUser($student);
                $student->refresh()->load('user');

                if ($oldUserId !== null && $oldUserId !== $student->user_id) {
                    $this->userRoleService->detachStudentRole(User::find($oldUserId));
                }

                $this->userRoleService->syncStudentRole($student);
                $this->syncLinkedUserPasswordFromBirthDate($student);

                return $student->load(['user', 'parentUser', 'schoolClass', 'detailSiswa']);
            });

            if ($storedPhotoPath !== null && $oldPhotoPath !== null && $oldPhotoPath !== $storedPhotoPath) {
                $this->profilePhotoService->delete($oldPhotoPath);
            }

            if ($removePhoto && $photo === null && $oldPhotoPath !== null) {
                $this->profilePhotoService->delete($oldPhotoPath);
            }

            return $updatedStudent;
        } catch (\Throwable $throwable) {
            if ($storedPhotoPath !== null) {
                $this->profilePhotoService->delete($storedPhotoPath);
            }

            throw $throwable;
        }
    }

    public function delete(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            $user = $student->user;
            $parentUser = $student->parentUser;
            $photoPath = $student->photo_path;

            $student->delete();

            if ($user !== null) {
                $this->userRoleService->detachStudentRole($user);
                $user->refresh();
                if (
                    empty($user->roles)
                    && ! $user->teacherProfile()->exists()
                    && ! $user->studentProfile()->exists()
                    && ! $user->parentStudents()->exists()
                ) {
                    $user->delete();
                }
            }

            if ($parentUser !== null) {
                if (! $parentUser->parentStudents()->exists()) {
                    $this->userRoleService->removeRoles($parentUser, [UserRole::ORANG_TUA]);
                    $parentUser->refresh();
                    if (
                        empty($parentUser->roles)
                        && ! $parentUser->teacherProfile()->exists()
                        && ! $parentUser->studentProfile()->exists()
                    ) {
                        $parentUser->delete();
                    }
                }
            }

            $this->profilePhotoService->delete($photoPath);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function extractDetailSiswaData(array &$data): ?array
    {
        if (! array_key_exists('detail_siswa', $data)) {
            return null;
        }

        $detailSiswa = is_array($data['detail_siswa']) ? $data['detail_siswa'] : [];
        unset($data['detail_siswa']);

        $fields = [
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

        $normalized = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $detailSiswa)) {
                continue;
            }

            $value = $detailSiswa[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            $normalized[$field] = $value === '' ? null : $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>|null $detailSiswaData
     */
    protected function syncDetailSiswa(Student $student, ?array $detailSiswaData): void
    {
        if ($detailSiswaData === null) {
            return;
        }

        $hasFilledValue = collect($detailSiswaData)->contains(static fn(mixed $value): bool => $value !== null);

        if (! $hasFilledValue) {
            $student->detailSiswa()->delete();

            return;
        }

        if ($student->detailSiswa()->exists()) {
            $student->detailSiswa()->update($detailSiswaData);

            return;
        }

        $student->detailSiswa()->create($detailSiswaData);
    }

    protected function syncLinkedUserPasswordFromBirthDate(Student $student): void
    {
        if ($student->user === null || $student->birth_date === null) {
            return;
        }

        $defaultPassword = $student->birth_date->format('dmY');

        if (Hash::check($defaultPassword, $student->user->password)) {
            return;
        }

        $student->user->forceFill([
            'password' => $defaultPassword,
        ])->save();
    }

    protected function syncParentUser(?Student $student): void
    {
        if ($student === null) {
            return;
        }

        $student->loadMissing(['detailSiswa', 'parentUser']);

        $parentUser = $student->parentUser;

        if ($parentUser === null) {
            $canonicalEmail = $this->canonicalParentEmail($student);
            $parentUser = User::query()->where('email', $canonicalEmail)->first();
        }

        if ($parentUser === null) {
            $parentUser = User::query()->create([
                'name' => $this->parentUserName($student),
                'email' => $this->uniqueGeneratedParentEmail($student),
                'password' => $this->defaultParentPassword($student),
                'roles' => [UserRole::ORANG_TUA->value],
            ]);

            $student->forceFill(['parent_user_id' => $parentUser->id])->save();
        } else {
            $this->userRoleService->ensureRoles($parentUser, [UserRole::ORANG_TUA]);
            $parentUser->forceFill([
                'name' => $parentUser->name ?: $this->parentUserName($student),
                'password' => $this->defaultParentPassword($student),
            ])->save();

            if ($student->parent_user_id !== $parentUser->id) {
                $student->forceFill(['parent_user_id' => $parentUser->id])->save();
            }
        }

        $student->setRelation('parentUser', $parentUser);
    }

    protected function canonicalParentEmail(Student $student): string
    {
        $parentName = $this->parentUserName($student);
        $parentPhone = trim((string) ($student->detailSiswa?->parent_phone ?? ''));

        $nameSlug = $this->emailSlug($parentName) ?: 'orangtua';
        $phoneSlug = $this->emailSlug($parentPhone);

        $local = 'orangtua.' . $nameSlug;
        if ($phoneSlug !== '') {
            $local .= '.' . $phoneSlug;
        }

        return $local . '@sch.id';
    }

    protected function parentUserName(Student $student): string
    {
        $motherName = trim((string) ($student->detailSiswa?->mother_name ?? ''));
        $fatherName = trim((string) ($student->detailSiswa?->father_name ?? ''));

        if ($motherName !== '') {
            return $motherName;
        }

        if ($fatherName !== '') {
            return $fatherName;
        }

        return 'Orang Tua ' . $student->name;
    }

    protected function uniqueGeneratedParentEmail(Student $student): string
    {
        $studentSlug = $this->emailSlug($student->name) ?: 'siswa';
        $parentSlug = $this->emailSlug((string) ($student->detailSiswa?->mother_name ?? '')) ?: 'orangtua';
        $base = $studentSlug . '.' . $parentSlug;
        $email = $base . '@sch.id';
        $counter = 2;

        while (User::query()->where('email', $email)->exists()) {
            $email = $base . $counter . '@sch.id';
            $counter++;
        }

        return $email;
    }

    protected function emailSlug(string $value): string
    {
        return trim((string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->replaceMatches('/\.+/', '.')
            ->trim('.'));
    }

    protected function defaultParentPassword(Student $student): string
    {
        return $student->birth_date?->format('dmY') ?: $student->nik;
    }
}
