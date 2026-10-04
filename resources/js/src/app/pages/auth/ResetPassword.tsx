import React, { useState } from 'react';
import { Link, useLocation, useNavigate, Navigate } from 'react-router-dom';
import { Lock, Eye, EyeOff, AlertCircle, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '../../components/ui/utils';
import api from '../../services/api';
import { AuthShell } from './AuthShell';

const RECOVERY_EMAIL_KEY = 'sarunis:password-recovery:email';
const RECOVERY_TOKEN_KEY = 'sarunis:password-recovery:token';

export const ResetPassword = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const state = location.state as { email?: string; token?: string } | null;
    const email = state?.email || sessionStorage.getItem(RECOVERY_EMAIL_KEY) || '';
    const token = state?.token || sessionStorage.getItem(RECOVERY_TOKEN_KEY) || '';

    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [showPwd, setShowPwd] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    if (!email || !token) {
        return <Navigate to="/forgot-password" replace />;
    }

    const submit = async () => {
        if (password.length < 8) {
            setError('Kata sandi minimal 8 karakter.');
            return;
        }
        if (password !== passwordConfirmation) {
            setError('Konfirmasi kata sandi tidak cocok.');
            return;
        }

        setError('');
        setLoading(true);
        try {
            await api.post('/auth/reset-password', {
                email,
                token,
                password,
                password_confirmation: passwordConfirmation,
            });

            sessionStorage.removeItem(RECOVERY_EMAIL_KEY);
            sessionStorage.removeItem(RECOVERY_TOKEN_KEY);

            toast.success('Kata sandi berhasil diperbarui. Silakan masuk kembali.');
            navigate('/login');
        } catch (err: any) {
            const msg = err.response?.data?.errors?.password?.[0]
                || err.response?.data?.errors?.token?.[0]
                || err.response?.data?.errors?.code?.[0]
                || err.response?.data?.message
                || 'Gagal memperbarui kata sandi.';
            setError(msg);
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthShell title="Atur Ulang Kata Sandi" subtitle="Buat kata sandi baru untuk akun Anda.">
            <form onSubmit={(e) => { e.preventDefault(); submit(); }} className="space-y-4">
                {error && (
                    <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-center gap-2">
                        <AlertCircle className="w-4 h-4 flex-shrink-0" />
                        {error}
                    </div>
                )}

                <div className="flex flex-col gap-1">
                    <label className="text-sm font-medium text-slate-700">Kata Sandi Baru <span className="text-red-500">*</span></label>
                    <div className="relative">
                        <Lock className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type={showPwd ? 'text' : 'password'}
                            placeholder="Minimal 8 karakter"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className={cn(
                                'w-full h-9 rounded-lg border text-sm pl-9 pr-10 bg-white placeholder:text-slate-400 focus:outline-none focus:ring-2 transition-all',
                                error ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-slate-300 hover:border-slate-400 focus:border-blue-500 focus:ring-blue-100'
                            )}
                        />
                        <button type="button" onClick={() => setShowPwd(!showPwd)} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                            {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                    </div>
                </div>

                <div className="flex flex-col gap-1">
                    <label className="text-sm font-medium text-slate-700">Konfirmasi Kata Sandi <span className="text-red-500">*</span></label>
                    <div className="relative">
                        <Lock className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type={showPwd ? 'text' : 'password'}
                            placeholder="Ulangi kata sandi baru"
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                            className={cn(
                                'w-full h-9 rounded-lg border text-sm pl-9 pr-10 bg-white placeholder:text-slate-400 focus:outline-none focus:ring-2 transition-all',
                                error ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-slate-300 hover:border-slate-400 focus:border-blue-500 focus:ring-blue-100'
                            )}
                        />
                        <button type="button" onClick={() => setShowPwd(!showPwd)} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                            {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={loading}
                    className="w-full inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 bg-blue-600 text-white hover:bg-blue-700 text-sm px-5 py-2.5 h-11 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {loading ? 'Menyimpan...' : 'Simpan Kata Sandi Baru'}
                </button>

                <Link to="/login" className="flex items-center justify-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 mt-2">
                    <ArrowLeft className="w-4 h-4" />
                    Kembali ke halaman masuk
                </Link>
            </form>
        </AuthShell>
    );
};