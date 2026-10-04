<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TeacherService
{
    public function __construct(
        protected UserRoleService $userRoleService,
        protected ProfilePhotoService $profilePhotoService,
    ) {
    }

    public function paginate(int $perPage = 15, string $search = ''): LengthAwarePaginator
    {
        $search = trim($search);

        return Teacher::query()
            ->with(['user', 'homeroomClasses'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%")
                        ->orWhere('employment_status', 'like', "%{$search}%")
                        ->orWhere('last_education', 'like', "%{$search}%")
                        ->orWhere('major', 'like', "%{$search}%")
                        ->orWhere('university', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Teacher
    {
        /** @var UploadedFile|null $photo */
        $photo = $data['photo'] ?? null;
        unset($data['photo'], $data['remove_photo'], $data['is_subject_teacher']);

        $storedPhotoPath = null;

        try {
            return DB::transaction(function () use ($data, $photo, &$storedPhotoPath): Teacher {
                if ($photo !== null) {
                    $storedPhotoPath = $this->profilePhotoService->store($photo, 'teachers');
                    $data['photo_path'] = $storedPhotoPath;
                }

                $teacher = Teacher::create($data);
                $teacher->load('user');

                $this->userRoleService->syncTeacherRoles($teacher);

                return $teacher->load(['user', 'homeroomClasses']);
            });
        } catch (\Throwable $throwable) {
            $this->profilePhotoService->delete($storedPhotoPath);

            throw $throwable;
        }
    }

    public function update(Teacher $teacher, array $data): Teacher
    {
        /** @var UploadedFile|null $photo */
        $photo = $data['photo'] ?? null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        unset($data['photo'], $data['remove_photo'], $data['is_subject_teacher']);

        $oldPhotoPath = $teacher->photo_path;
        $newPhotoPath = $oldPhotoPath;
        $storedPhotoPath = null;

        try {
            if ($photo !== null) {
                $storedPhotoPath = $this->profilePhotoService->store($photo, 'teachers');
                $newPhotoPath = $storedPhotoPath;
            } elseif ($removePhoto) {
                $newPhotoPath = null;
            }

            $updatedTeacher = DB::transaction(function () use ($teacher, $data, $newPhotoPath): Teacher {
                $oldUserId = $teacher->user_id;

                $teacher->update([
                    ...$data,
                    'photo_path' => $newPhotoPath,
                ]);
                $teacher->load('user');

                if ($oldUserId !== null && $oldUserId !== $teacher->user_id) {
                    $this->userRoleService->detachTeacherRoles(User::find($oldUserId));
                }

                $this->userRoleService->syncTeacherRoles($teacher);

                return $teacher->load(['user', 'homeroomClasses']);
            });

            if ($storedPhotoPath !== null && $oldPhotoPath !== null && $oldPhotoPath !== $storedPhotoPath) {
                $this->profilePhotoService->delete($oldPhotoPath);
            }

            if ($removePhoto && $photo === null && $oldPhotoPath !== null) {
                $this->profilePhotoService->delete($oldPhotoPath);
            }

            return $updatedTeacher;
        } catch (\Throwable $throwable) {
            if ($storedPhotoPath !== null) {
                $this->profilePhotoService->delete($storedPhotoPath);
            }

            throw $throwable;
        }
    }

    public function delete(Teacher $teacher): void
    {
        DB::transaction(function () use ($teacher): void {
            $user = $teacher->user;
            $photoPath = $teacher->photo_path;
            $teacher->delete();

            $this->userRoleService->detachTeacherRoles($user);
            $this->profilePhotoService->delete($photoPath);
        });
    }
}
