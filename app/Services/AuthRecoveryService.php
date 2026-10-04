<?php

namespace App\Services;

use App\Models\AuthVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthRecoveryService
{
    /**
     * Nilai kolom portal untuk alur reset global (tidak dibatasi per-portal).
     */
    protected const PORTAL = 'global';

    protected const PURPOSE = 'password_reset';

    protected const MAX_ATTEMPTS = 5;

    protected const CODE_TTL_MINUTES = 15;

    /**
     * Buat kode verifikasi 6 digit baru dan kirim ke email pengguna.
     */
    public function sendCode(string $email): void
    {
        $user = $this->userByEmail($email);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        AuthVerificationCode::query()
            ->where('email', $user->email)
            ->where('purpose', self::PURPOSE)
            ->delete();

        AuthVerificationCode::query()->create([
            'email' => $user->email,
            'portal' => self::PORTAL,
            'purpose' => self::PURPOSE,
            'code_hash' => Hash::make($code),
            'reset_token_hash' => null,
            'attempts' => 0,
            'verified_at' => null,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        Mail::send(
            'emails.auth-recovery-code',
            ['code' => $code, 'user' => $user, 'minutes' => self::CODE_TTL_MINUTES],
            static function ($message) use ($user): void {
                $message->to($user->email)->subject('Kode Verifikasi Reset Kata Sandi');
            },
        );
    }

    /**
     * Verifikasi kode. Jika benar, terbitkan token reset (dikembalikan ke pemanggil).
     */
    public function verifyCode(string $email, string $code): string
    {
        $user = $this->userByEmail($email);
        $verification = $this->activeVerification($user->email);

        if ($verification->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => ['Kode sudah terlalu sering dicoba. Kirim ulang kode baru.'],
            ]);
        }

        if (! Hash::check($code, $verification->code_hash)) {
            $verification->increment('attempts');

            throw ValidationException::withMessages([
                'code' => ['Kode verifikasi tidak valid.'],
            ]);
        }

        $token = Str::random(64);

        $verification->forceFill([
            'verified_at' => now(),
            'reset_token_hash' => Hash::make($token),
        ])->save();

        return $token;
    }

    /**
     * Setel ulang kata sandi setelah kode diverifikasi.
     */
    public function resetPassword(string $email, string $token, string $password): void
    {
        $user = $this->userByEmail($email);
        $verification = $this->activeVerification($user->email);

        if ($verification->verified_at === null || $verification->reset_token_hash === null) {
            throw ValidationException::withMessages([
                'code' => ['Kode belum diverifikasi. Ulangi proses dari awal.'],
            ]);
        }

        if (! Hash::check($token, $verification->reset_token_hash)) {
            throw ValidationException::withMessages([
                'token' => ['Sesi reset tidak valid. Ulangi proses dari awal.'],
            ]);
        }

        $user->forceFill([
            'password' => $password,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        AuthVerificationCode::query()
            ->where('email', $user->email)
            ->where('purpose', self::PURPOSE)
            ->delete();
    }

    protected function userByEmail(string $email): User
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['Email tidak terdaftar.'],
            ]);
        }

        return $user;
    }

    protected function activeVerification(string $email): AuthVerificationCode
    {
        $verification = AuthVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', self::PURPOSE)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($verification === null) {
            throw ValidationException::withMessages([
                'code' => ['Kode sudah kedaluwarsa atau belum dikirim. Kirim ulang kode baru.'],
            ]);
        }

        return $verification;
    }
}
