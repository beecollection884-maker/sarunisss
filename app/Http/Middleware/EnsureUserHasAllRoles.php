<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasAllRoles
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Silakan login terlebih dahulu.');
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return $next($request);
        }

        $requiredRoles = $this->normalizeRoles($roles);

        if ($requiredRoles !== [] && ! $user->hasAllRoles($requiredRoles)) {
            abort(Response::HTTP_FORBIDDEN, 'Akses ini membutuhkan gabungan role yang sesuai.');
        }

        return $next($request);
    }

    /**
     * @param array<int, string> $roles
     * @return array<int, string>
     */
    protected function normalizeRoles(array $roles): array
    {
        return collect($roles)
            ->flatMap(static fn (string $role): array => explode(',', $role))
            ->map(static fn (string $role): string => trim($role))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
