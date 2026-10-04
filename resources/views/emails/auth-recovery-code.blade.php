<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode Verifikasi Reset Kata Sandi</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background-color:#2563eb; padding:20px 32px; color:#ffffff;">
                            <p style="margin:0; font-size:14px; font-weight:bold;">SMP IP YAKIN</p>
                            <p style="margin:4px 0 0; font-size:12px; color:#bfdbfe;">Sistem Manajemen Sekolah</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:18px; color:#0f172a;">Reset Kata Sandi</h1>
                            <p style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#475569;">
                                Halo{{ isset($user->name) ? ' ' . $user->name : '' }}, gunakan kode verifikasi berikut untuk melanjutkan proses pengaturan ulang kata sandi Anda.
                            </p>
                            <div style="text-align:center; margin:24px 0;">
                                <span style="display:inline-block; font-size:32px; font-weight:bold; letter-spacing:8px; color:#2563eb; background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:16px 24px;">
                                    {{ $code }}
                                </span>
                            </div>
                            <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#475569;">
                                Kode ini berlaku selama <strong>{{ $minutes }} menit</strong>. Jangan bagikan kode ini kepada siapa pun.
                            </p>
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#94a3b8;">
                                Jika Anda tidak meminta reset kata sandi, abaikan email ini.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8fafc; padding:16px 32px; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; font-size:11px; color:#94a3b8;">&copy; 2026 SMP IP YAKIN. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
