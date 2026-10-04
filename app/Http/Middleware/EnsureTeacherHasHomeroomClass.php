<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacherHasHomeroomClass
{
    /**
     * Handle an incoming request.
     *
     * Checks if the authenticated user is a teacher (guru_mapel) AND has been assigned
     * as a homeroom teacher (walikelas) to at least one class.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Silakan login terlebih dahulu.');
        }

        // Admin always has access
        if ($user->hasRole(UserRole::ADMIN)) {
            return $next($request);
        }

        // Must have guru_mapel role
        if (! $user->hasRole(UserRole::GURU_MAPEL)) {
            abort(Response::HTTP_FORBIDDEN, 'Anda harus memiliki role guru mapel untuk mengakses area ini.');
        }

        // Check if user has a teacher profile with homeroom classes
        $teacher = $user->teacherProfile;

        if ($teacher === null) {
            abort(Response::HTTP_FORBIDDEN, 'Anda harus menjadi guru untuk mengakses area ini.');
        }

        // Check if teacher has at least one homeroom class
        $hasHomeroomClass = $teacher->homeroomClasses()->exists();

        if (! $hasHomeroomClass) {
            abort(Response::HTTP_FORBIDDEN, 'Anda harus ditunjuk sebagai wali kelas untuk mengakses area ini.');
        }

        return $next($request);
    }
}
