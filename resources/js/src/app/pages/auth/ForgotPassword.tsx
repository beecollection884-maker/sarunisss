import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Mail, AlertCircle, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '../../components/ui/utils';
import api from '../../services/api';
import { AuthShell } from './AuthShell';

const RECOVERY_EMAIL_KEY = 'sarunis:password-recovery:email';
const RECOVERY_TOKEN_KEY = 'sarunis:password-recovery:token';

export const ForgotPassword = () => {
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const submit = async () => {
        const normalizedEmail = email.trim().toLowerCase();

        if (!normalizedEmail) {
            setError('Email wajib diisi.');
            return;
        }

        setError('');
        setLoading(true);
        try {
            const response = await api.post('/auth/forgot-password', { email: normalizedEmail });
            const recoveryEmail = response.data?.data?.email || normalizedEmail;

            sessionStorage.setItem(RECOVERY_EMAIL_KEY, recoveryEmail);
            sessionStorage.removeItem(RECOVERY_TOKEN_KEY);

            toast.success('Kode verifikasi telah dikirim ke email Anda.');
            navigate('/verify-code', { state: { email: recoveryEmail } });
        } catch (err: any) {
            const msg = err.response?.data?.errors?.email?.[0]
                || err.response?.data?.message
                || 'Gagal mengirim kode verifikasi.';
            setError(msg);
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthShell title="Lupa Kata Sandi" subtitle="Masukkan email akun Anda. Kami akan mengirimkan kode verifikasi untuk mengatur ulang kata sandi.">
            <form onSubmit={(e) => { e.preventDefault(); submit(); }} className="space-y-4">
                {error && (
                    <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-center gap-2">
                        <AlertCircle className="w-4 h-4 flex-shrink-0" />
                        {error}
                    </div>
                )}
                <div className="flex flex-col gap-1">
                    <label className="text-sm font-medium text-slate-700">Email <span className="text-red-500">*</span></label>
                    <div className="relative">
                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4" />
                        <input
                            type="email"
                            placeholder="nama@smpipyakin.sch.id"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            className={cn(
                                'w-full h-9 rounded-lg border text-sm bg-white text-slate-900 placeholder:text-slate-400 transition-all focus:outline-none focus:ring-2 pl-9 pr-3',
                                error ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-slate-300 hover:border-slate-400 focus:border-blue-500 focus:ring-blue-100'
                            )}
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={loading}
                    className="w-full inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 bg-blue-600 text-white hover:bg-blue-700 text-sm px-5 py-2.5 h-11 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {loading ? 'Mengirim...' : 'Kirim Kode Verifikasi'}
                </button>

                <Link to="/login" className="flex items-center justify-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 mt-2">
                    <ArrowLeft className="w-4 h-4" />
                    Kembali ke halaman masuk
                </Link>
            </form>
        </AuthShell>
    );
};