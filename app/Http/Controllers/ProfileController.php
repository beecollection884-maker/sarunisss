<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user()->load(['teacherProfile', 'studentProfile']);
        
        $profile = null;
        $role = 'user';
        
        if ($user->hasRole('administrator')) {
            $role = 'admin';
        } elseif ($user->hasRole('guru_mapel')) {
            $role = 'teacher';
            $profile = $user->teacherProfile;
        } elseif ($user->hasRole('siswa')) {
            $role = 'student';
            if ($user->studentProfile) {
                $user->studentProfile->load(['schoolClass', 'detailSiswa']);
                $profile = $user->studentProfile;
            }
        } elseif ($user->hasRole('orang_tua')) {
            $role = 'parent';
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles,
                ],
                'role' => $role,
                'profile' => $profile,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Determine user role
        $role = 'user';
        if ($user->hasRole('administrator')) {
            $role = 'admin';
        } elseif ($user->hasRole('guru_mapel')) {
            $role = 'teacher';
        } elseif ($user->hasRole('siswa')) {
            $role = 'student';
        } elseif ($user->hasRole('orang_tua')) {
            $role = 'parent';
        }

        $rules = [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];

        if ($role === 'admin' || $role === 'parent') {
            $rules['name'] = ['required', 'string', 'max:255'];
        } elseif ($role === 'teacher') {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['phone'] = ['nullable', 'string', 'max:255'];
            $rules['address'] = ['nullable', 'string', 'max:1000'];
            $rules['religion'] = ['nullable', 'string', 'max:255'];
            $rules['last_education'] = ['nullable', 'string', 'max:255'];
            $rules['major'] = ['nullable', 'string', 'max:255'];
            $rules['university'] = ['nullable', 'string', 'max:255'];
        } elseif ($role === 'student') {
            // Student CANNOT update name! Name comes from master data.
            $rules['phone'] = ['nullable', 'string', 'max:255'];
            $rules['address'] = ['nullable', 'string', 'max:1000'];
        }

        $validated = $request->validate($rules);

        // Update User fields
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            // Tidak perlu Hash::make() karena User model sudah menggunakan cast 'hashed'
            $user->password = $validated['password'];
        }

        $user->save();

        // Update profile fields
        if ($role === 'teacher' && $user->teacherProfile) {
            $user->teacherProfile->update([
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'last_education' => $validated['last_education'] ?? null,
                'major' => $validated['major'] ?? null,
                'university' => $validated['university'] ?? null,
            ]);
        } elseif ($role === 'student' && $user->studentProfile) {
            $user->studentProfile->update([
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);
        }

        // Reload user and associations
        $user->load(['teacherProfile', 'studentProfile']);
        $profile = null;
        if ($user->teacherProfile) {
            $profile = $user->teacherProfile;
        } elseif ($user->studentProfile) {
            $user->studentProfile->load(['schoolClass', 'detailSiswa']);
            $profile = $user->studentProfile;
        }

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles,
                ],
                'profile' => $profile,
            ],
        ]);
    }
}
