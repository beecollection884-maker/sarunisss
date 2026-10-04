<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {
    }

    public function portals(): JsonResponse
    {
        return response()->json([
            'data' => AuthService::portalMap(),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'login' => ['nullable', 'string', 'required_without:email'],
            'email' => ['nullable', 'string', 'required_without:login'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($payload['remember'] ?? false);
        unset($payload['remember']);

        $credentials = $this->resolveCredentials($payload);

        $loginData = $this->authService->login($request, $credentials, $remember);

        return response()->json([
            'message' => 'Login berhasil sebagai '.$loginData['logged_in_user']['name'].'.',
            'data' => $loginData,
        ]);
    }

    public function portalLogin(Request $request, string $portal): JsonResponse
    {
        abort_unless(array_key_exists($portal, AuthService::portalMap()), 404);

        $payload = $request->validate([
            'login' => ['nullable', 'string', 'required_without:email'],
            'email' => ['nullable', 'string', 'required_without:login'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($payload['remember'] ?? false);
        unset($payload['remember']);

        $credentials = $this->resolveCredentials($payload);
        $loginData = $this->authService->login($request, $credentials, $remember, $portal);

        return response()->json([
            'message' => 'Login portal berhasil sebagai '.$loginData['logged_in_user']['name'].'.',
            'data' => $loginData,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['teacherProfile', 'studentProfile']);

        return response()->json([
            'data' => $this->authService->buildPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $this->authService->logout($request);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('auth.page.login', ['logout' => 'success'])
                ->with('status', 'Logout berhasil.');
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * @param array{login?:string,email?:string,password:string} $payload
     * @return array{email:string,password:string}
     */
    protected function resolveCredentials(array $payload): array
    {
        $login = trim($payload['login'] ?? $payload['email'] ?? '');
        $normalizedLogin = Str::lower($login);
        $compactedLogin = $this->compactLogin($normalizedLogin);

        $user = User::query()
            ->get(['email'])
            ->first(function (User $user) use ($normalizedLogin, $compactedLogin): bool {
                $email = Str::lower($user->email);
                $emailLocal = Str::before($email, '@');

                return in_array($normalizedLogin, [$email, $emailLocal], true)
                    || $this->compactLogin($emailLocal) === $compactedLogin;
            });

        return [
            'email' => $user?->email ?? $login,
            'password' => $payload['password'],
        ];
    }

    protected function compactLogin(string $value): string
    {
        $ascii = Str::lower(Str::ascii(trim($value)));

        return (string) preg_replace('/[^a-z0-9]+/', '', $ascii);
    }
}
