<?php

namespace App\Http\Controllers;

use App\Services\AuthRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthRecoveryController extends Controller
{
    public function __construct(
        protected AuthRecoveryService $authRecoveryService,
    ) {
    }

    public function sendCode(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $payload = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->authRecoveryService->sendCode($payload['email']);

        return response()->json([
            'message' => 'Kode verifikasi telah dikirim ke email Anda.',
            'data' => ['email' => $payload['email']],
        ]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $payload = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required'],
        ]);

        $code = is_array($payload['code'])
            ? implode('', $payload['code'])
            : (string) $payload['code'];
        $code = trim($code);

        if (! preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::withMessages([
                'code' => ['Masukkan 6 digit kode verifikasi.'],
            ]);
        }

        $token = $this->authRecoveryService->verifyCode(
            $payload['email'],
            $code,
        );

        return response()->json([
            'message' => 'Kode berhasil diverifikasi.',
            'data' => ['reset_token' => $token],
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $payload = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->authRecoveryService->resetPassword(
            $payload['email'],
            $payload['token'],
            $payload['password'],
        );

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.',
        ]);
    }

    protected function normalizeEmail(Request $request): void
    {
        if ($request->has('email')) {
            $request->merge([
                'email' => Str::lower(trim((string) $request->input('email'))),
            ]);
        }
    }
}
