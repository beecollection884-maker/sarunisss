import React, { useState } from 'react';
import { Link, useLocation, useNavigate, Navigate } from 'react-router-dom';
import { AlertCircle, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';
import { AuthShell } from './AuthShell';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '../../components/ui/input-otp';

const RECOVERY_EMAIL_KEY = 'sarunis:password-recovery:email';
const RECOVERY_TOKEN_KEY = 'sarunis:password-recovery:token';
const slotClass = 'h-11 w-11 text-base font-semibold border-slate-300 bg-white text-slate-900 data-[active=true]:border-blue-500 data-[active=true]:ring-blue-100 rounded-md';

export const VerifyCode = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const stateEmail = (location.state as { email?: string } | null)?.email;
    const email = stateEmail || sessionStorage.getItem(RECOVERY_EMAIL_KEY) || '';

    const [code, setCode] = useState('');
    const [loading, setLoading] = useState(false);
    const [resending, setResending] = useState(false);
    const [error, setError] = useState('');

    if (!email) {
        return <Navigate to="/forgot-password" replace />;
    }

    const submit = async () => {
        if (!/^\d{6}$/.test(code)) {
            setError('Masukkan 6 digit kode verifikasi.');
            return;
        }

        setError('');
        setLoading(true);
        try {
            const response = await api.post('/auth/verify-code', { email, code });
            const token = response.data?.data?.reset_token;

            if (!token) {
                setError('Sesi reset tidak valid. Kirim ulang kode verifikasi.');
                return;
            }

            sessionStorage.setItem(RECOVERY_EMAIL_KEY, email);
            sessionStorage.setItem(RECOVERY_TOKEN_KEY, token);

            toast.success('Kode berhasil diverifikasi.');
            navigate('/reset-password', { state: { email, token } });
        } catch (err: any) {
            const msg = err.response?.data?.errors?.code?.[0]
                || err.response?.data?.message
                || 'Kode verifikasi tidak valid.';
            setError(msg);
        } finally {
            setLoading(false);
        }
    };

    const resend = async () => {
        setError('');
        setResending(true);
        try {
            await api.post('/auth/forgot-password', { email });
            setCode('');
            sessionStorage.setItem(RECOVERY_EMAIL_KEY, email);
            sessionStorage.removeItem(RECOVERY_TOKEN_KEY);
            toast.success('Kode verifikasi baru telah dikirim.');
        } catch (err: any) {
            const msg = err.response?.data?.errors?.email?.[0]
                || err.response?.data?.message
                || 'Gagal mengirim ulang kode.';
            setError(msg);
        } finally {
            setResending(false);
        }
    };

    return (
        <AuthShell title="Verifikasi Kode" subtitle={`Masukkan 6 digit kode yang kami kirim ke ${email}.`}>
            <form onSubmit={(e) => { e.preventDefault(); submit(); }} className="space-y-4">
                {error && (
                    <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-center gap-2">
                        <AlertCircle className="w-4 h-4 flex-shrink-0" />
                        {error}
                    </div>
                )}

                <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-slate-700">Kode Verifikasi <span className="text-red-500">*</span></label>
                    <InputOTP
                        maxLength={6}
                        value={code}
                        onChange={(value) => setCode(value)}
                        containerClassName="justify-center sm:justify-start"
                    >
                        <InputOTPGroup className="gap-2">
                            {[0, 1, 2, 3, 4, 5].map((i) => (
                                <InputOTPSlot key={i} index={i} className={slotClass} />
                            ))}
                        </InputOTPGroup>
                    </InputOTP>
                </div>

                <button
                    type="submit"
                    disabled={loading}
                    className="w-full inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 bg-blue-600 text-white hover:bg-blue-700 text-sm px-5 py-2.5 h-11 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {loading ? 'Memverifikasi...' : 'Verifikasi'}
                </button>

                <div className="text-center text-sm text-slate-500">
                    Tidak menerima kode?{' '}
                    <button type="button" onClick={resend} disabled={resending} className="text-blue-600 hover:text-blue-700 font-medium disabled:opacity-50">
                        {resending ? 'Mengirim...' : 'Kirim ulang'}
                    </button>
                </div>

                <Link to="/login" className="flex items-center justify-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 mt-2">
                    <ArrowLeft className="w-4 h-4" />
                    Kembali ke halaman masuk
                </Link>
            </form>
        </AuthShell>
    );
};