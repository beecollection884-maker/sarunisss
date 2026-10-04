import React from 'react';
import { School } from 'lucide-react';

interface AuthShellProps {
    title: string;
    subtitle: string;
    children: React.ReactNode;
}

/**
 * Layout dua kolom bersama untuk halaman autentikasi (login, lupa password, dsb).
 * Panel biru branding di kiri + kartu form terpusat di kanan.
 */
export const AuthShell = ({ title, subtitle, children }: AuthShellProps) => {
    return (
        <div className="min-h-screen flex">
            <div className="hidden lg:flex flex-col w-[400px] bg-blue-600 text-white p-10 flex-shrink-0 relative overflow-hidden">
                <div className="absolute inset-0 opacity-10">
                    {[...Array(5)].map((_, i) => (
                        <div key={i} className="absolute rounded-full border border-white/30" style={{ width: `${(i + 1) * 140}px`, height: `${(i + 1) * 140}px`, top: '50%', left: '50%', transform: 'translate(-50%, -50%)' }} />
                    ))}
                </div>
                <div className="relative flex items-center gap-3">
                    <div className="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center"><School className="w-4.5 h-4.5" /></div>
                    <div><p className="font-bold text-sm">SMP IP YAKIN</p><p className="text-xs text-blue-200">Sistem Manajemen Sekolah</p></div>
                </div>
                <div className="relative my-auto">
                    <h2 className="text-3xl font-bold mb-4 leading-tight">Platform<br />Pendidikan<br />Terintegrasi</h2>
                    <p className="text-blue-200 text-sm leading-relaxed mb-8">Kelola seluruh aktivitas akademik, kehadiran, nilai, dan administrasi sekolah dalam satu platform.</p>
                    <div className="grid grid-cols-2 gap-3">
                        {[['1,247', 'Siswa Aktif'], ['89', 'Guru & Staf'], ['36', 'Kelas'], ['94.7%', 'Kehadiran']].map(([v, l]) => (
                            <div key={l} className="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                                <p className="text-2xl font-bold">{v}</p>
                                <p className="text-xs text-blue-200 mt-0.5">{l}</p>
                            </div>
                        ))}
                    </div>
                </div>
                <p className="relative text-xs text-blue-300">© 2026 SMP IP YAKIN. All rights reserved.</p>
            </div>

            <div className="flex-1 flex items-center justify-center bg-slate-50 p-6">
                <div className="w-full max-w-md">
                    <div className="flex items-center gap-3 mb-8 lg:hidden">
                        <div className="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center"><School className="w-4 h-4 text-white" /></div>
                        <p className="font-bold text-slate-900 text-sm">SMP IP YAKIN</p>
                    </div>

                    <h1 className="text-xl font-bold text-slate-900 mb-1">{title}</h1>
                    <p className="text-sm text-slate-500 mb-6">{subtitle}</p>
                    {children}
                </div>
            </div>
        </div>
    );
};
