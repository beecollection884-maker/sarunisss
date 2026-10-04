import React, { useState, useEffect } from 'react';
import { User, Phone, MapPin, Key, BookOpen, GraduationCap, Building2, Calendar, UserCheck, Shield, HelpCircle, Save, Loader2, Home, Eye, EyeOff, Lock } from 'lucide-react';
import api from '../../services/api';
import { toast } from 'sonner';
import { cn } from '../../components/ui/utils';

interface ProfileData {
    user: {
        id: number;
        name: string;
        email: string;
        roles: string[];
    };
    role: 'admin' | 'teacher' | 'student' | 'parent' | 'user';
    profile: any | null;
}

export const UserProfile = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [data, setData] = useState<ProfileData | null>(null);

    // Form states
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [address, setAddress] = useState('');
    const [religion, setReligion] = useState('');
    const [lastEducation, setLastEducation] = useState('');
    const [major, setMajor] = useState('');
    const [university, setUniversity] = useState('');

    // Password change states
    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const fetchProfile = async () => {
        setLoading(true);
        try {
            const res = await api.get('/profile');
            const pData = res.data.data;
            setData(pData);
            
            // Set fields
            setName(pData.user.name || '');
            setEmail(pData.user.email || '');
            
            if (pData.profile) {
                setPhone(pData.profile.phone || '');
                setAddress(pData.profile.address || '');
                setReligion(pData.profile.religion || '');
                setLastEducation(pData.profile.last_education || '');
                setMajor(pData.profile.major || '');
                setUniversity(pData.profile.university || '');
            }
        } catch (err) {
            console.error('Error fetching profile:', err);
            toast.error('Gagal memuat profil saya.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchProfile();
    }, []);

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload: any = { email };

            // Rules based on role
            if (data?.role === 'admin' || data?.role === 'parent' || data?.role === 'teacher') {
                payload.name = name;
            }

            if (data?.role === 'teacher') {
                payload.phone = phone;
                payload.address = address;
                payload.religion = religion;
                payload.last_education = lastEducation;
                payload.major = major;
                payload.university = university;
            }

            if (data?.role === 'student') {
                payload.phone = phone;
                payload.address = address;
            }

            if (password) {
                payload.current_password = currentPassword;
                payload.password = password;
                payload.password_confirmation = passwordConfirmation;
            }

            await api.put('/profile', payload);
            toast.success('Profil berhasil diperbarui.');
            
            // Reset password fields
            setCurrentPassword('');
            setPassword('');
            setPasswordConfirmation('');

            fetchProfile();
        } catch (err: any) {
            console.error('Error updating profile:', err);
            const msg = err.response?.data?.message || 'Gagal memperbarui profil.';
            toast.error(msg);
        } finally {
            setSaving(false);
        }
    };

    if (loading || !data) {
        return (
            <div className="flex flex-col items-center justify-center h-96 gap-3">
                <Loader2 className="w-8 h-8 text-blue-500 animate-spin" />
                <p className="text-sm text-slate-500 font-medium">Memuat profil...</p>
            </div>
        );
    }

    const { user, role, profile } = data;

    // Helper role indicators
    const roleIconMap: Record<string, any> = {
        admin: Shield,
        teacher: UserCheck,
        student: GraduationCap,
        parent: Home,
    };
    const RoleIcon = roleIconMap[role] || User;

    const roleNameMap: Record<string, string> = {
        admin: 'Administrator',
        teacher: 'Guru / Tenaga Pendidik',
        student: 'Siswa / Peserta Didik',
        parent: 'Orang Tua / Wali Siswa',
    };

    return (
        <div className="p-4 md:p-6 space-y-6 max-w-4xl mx-auto">
            {/* Header / Profile Summary card */}
            <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col md:flex-row items-center gap-5">
                <div className="w-20 h-20 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-200/80 shadow-inner flex-shrink-0">
                    <RoleIcon className="w-10 h-10 text-slate-600" />
                </div>
                <div className="text-center md:text-left min-w-0 flex-1 space-y-1">
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                        <RoleIcon className="w-3 h-3" />
                        {roleNameMap[role] || 'Pengguna'}
                    </span>
                    <h2 className="text-xl font-bold text-slate-900 leading-tight">{user.name}</h2>
                    <p className="text-sm text-slate-500 font-medium truncate">{user.email}</p>
                </div>
                {role === 'student' && profile && (
                    <div className="text-center md:text-right border-t md:border-t-0 md:border-l border-slate-200 pt-4 md:pt-0 md:pl-5 flex-shrink-0">
                        <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Kelas Aktif</p>
                        <p className="text-lg font-bold text-blue-600">{profile.school_class?.name || '-'}</p>
                        <p className="text-xs text-slate-500 mt-0.5">NISN: {profile.nisn || '-'}</p>
                    </div>
                )}
                {role === 'teacher' && profile && (
                    <div className="text-center md:text-right border-t md:border-t-0 md:border-l border-slate-200 pt-4 md:pt-0 md:pl-5 flex-shrink-0">
                        <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Nomor Induk Pegawai</p>
                        <p className="text-base font-bold text-slate-800">{profile.nip || '-'}</p>
                        <p className="text-xs text-slate-500 mt-0.5">{profile.position || 'Staff Pengajar'}</p>
                    </div>
                )}
            </div>

            {/* Form */}
            <form onSubmit={handleSave} className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Left side: Profile Data */}
                <div className="md:col-span-2 space-y-6">
                    {/* General Information */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                            <h3 className="text-sm font-bold text-slate-900">Informasi Umum</h3>
                        </div>
                        <div className="p-5 space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-bold text-slate-600">Nama Lengkap</label>
                                    <input
                                        type="text"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        disabled={role === 'student'}
                                        required
                                        className={cn(
                                            "w-full text-sm px-3.5 py-2.5 rounded-xl border outline-none transition-all",
                                            role === 'student'
                                                ? "bg-slate-50 text-slate-500 border-slate-200 cursor-not-allowed"
                                                : "bg-white border-slate-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        )}
                                    />
                                    {role === 'student' && (
                                        <p className="text-[10px] text-slate-400 font-medium leading-normal flex items-start gap-1">
                                            <HelpCircle className="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
                                            Siswa tidak diperbolehkan mengubah nama mandiri. Hubungi Tata Usaha (TU).
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-xs font-bold text-slate-600">Email Utama</label>
                                    <input
                                        type="email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        required
                                        className="w-full text-sm bg-white border border-slate-300 px-3.5 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                    />
                                </div>
                            </div>

                            {/* Contact Details */}
                            {(role === 'teacher' || role === 'student') && (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-bold text-slate-600">Nomor Telepon</label>
                                        <div className="relative">
                                            <Phone className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                            <input
                                                type="text"
                                                value={phone}
                                                onChange={(e) => setPhone(e.target.value)}
                                                className="w-full text-sm bg-white border border-slate-300 pl-10 pr-3.5 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                                placeholder="081xxxxxxxxx"
                                            />
                                        </div>
                                    </div>
                                    {role === 'teacher' && (
                                        <div className="space-y-1.5">
                                            <label className="text-xs font-bold text-slate-600">Agama</label>
                                            <input
                                                type="text"
                                                value={religion}
                                                onChange={(e) => setReligion(e.target.value)}
                                                className="w-full text-sm bg-white border border-slate-300 px-3.5 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                            />
                                        </div>
                                    )}
                                </div>
                            )}

                            {(role === 'teacher' || role === 'student') && (
                                <div className="space-y-1.5 pt-2">
                                    <label className="text-xs font-bold text-slate-600">Alamat Tempat Tinggal</label>
                                    <div className="relative">
                                        <MapPin className="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" />
                                        <textarea
                                            value={address}
                                            onChange={(e) => setAddress(e.target.value)}
                                            rows={3}
                                            className="w-full text-sm bg-white border border-slate-300 pl-10 pr-3.5 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all resize-none"
                                            placeholder="Tulis alamat lengkap Anda..."
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Teacher Specific Profile details */}
                    {role === 'teacher' && profile && (
                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                            <div className="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                                <h3 className="text-sm font-bold text-slate-900">Riwayat Pendidikan & Kepegawaian</h3>
                            </div>
                            <div className="p-5 space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-bold text-slate-600">Pendidikan Terakhir</label>
                                        <input
                                            type="text"
                                            value={lastEducation}
                                            onChange={(e) => setLastEducation(e.target.value)}
                                            className="w-full text-sm bg-white border border-slate-300 px-3.5 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                            placeholder="Contoh: S1"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-bold text-slate-600">Jurusan / Program Studi</label>
                                        <input
                                            type="text"
                                            value={major}
                                            onChange={(e) => setMajor(e.target.value)}
                                            className="w-full text-sm bg-white border border-slate-300 px-3.5 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                            placeholder="Contoh: Pendidikan Fisika"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-bold text-slate-600">Universitas / Lembaga</label>
                                        <input
                                            type="text"
                                            value={university}
                                            onChange={(e) => setUniversity(e.target.value)}
                                            className="w-full text-sm bg-white border border-slate-300 px-3.5 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                            placeholder="Contoh: Universitas Gadjah Mada"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-bold text-slate-600">Status Kepegawaian</label>
                                        <input
                                            type="text"
                                            value={profile.employment_status || '-'}
                                            disabled
                                            className="w-full text-sm bg-slate-50 text-slate-500 border border-slate-200 px-3.5 py-2.5 rounded-xl cursor-not-allowed"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Student Locked Metadata Section (View-only for Student) */}
                    {role === 'student' && profile && (
                        <div className="bg-slate-50 rounded-2xl border border-slate-200 p-5 space-y-4">
                            <div>
                                <h3 className="text-sm font-bold text-slate-900">Informasi Rapor & Master Data</h3>
                                <p className="text-xs text-slate-500">Berikut adalah data yang terdaftar di sistem dapodik sekolah. Data ini tidak dapat diubah oleh siswa.</p>
                            </div>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
                                <div className="space-y-0.5">
                                    <p className="text-[10px] text-slate-400 font-bold uppercase">NIK</p>
                                    <p className="text-sm font-semibold text-slate-700">{profile.nik || '-'}</p>
                                </div>
                                <div className="space-y-0.5">
                                    <p className="text-[10px] text-slate-400 font-bold uppercase">NISN</p>
                                    <p className="text-sm font-semibold text-slate-700">{profile.nisn || '-'}</p>
                                </div>
                                <div className="space-y-0.5">
                                    <p className="text-[10px] text-slate-400 font-bold uppercase">Jenis Kelamin</p>
                                    <p className="text-sm font-semibold text-slate-700">{profile.gender === 'L' ? 'Laki-laki' : 'Perempuan'}</p>
                                </div>
                                <div className="space-y-0.5">
                                    <p className="text-[10px] text-slate-400 font-bold uppercase">Tempat Lahir</p>
                                    <p className="text-sm font-semibold text-slate-700">{profile.detail_siswa?.birth_place || '-'}</p>
                                </div>
                            </div>
                            {profile.detail_siswa && (
                                <div className="border-t border-slate-200 pt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div className="space-y-0.5">
                                        <p className="text-[10px] text-slate-400 font-bold uppercase">Nama Ayah</p>
                                        <p className="text-sm font-semibold text-slate-700">{profile.detail_siswa.father_name || '-'}</p>
                                    </div>
                                    <div className="space-y-0.5">
                                        <p className="text-[10px] text-slate-400 font-bold uppercase">Nama Ibu</p>
                                        <p className="text-sm font-semibold text-slate-700">{profile.detail_siswa.mother_name || '-'}</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Right side: Security / Save button */}
                <div className="space-y-6">
                    {/* Password Change */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                                <Key className="w-4 h-4 text-slate-500" /> Keamanan & Sandi
                            </h3>
                        </div>
                        <div className="p-5 space-y-4">
                            <div className="space-y-1.5">
                                <label className="text-xs font-bold text-slate-600">Kata Sandi Saat Ini</label>
                                <div className="relative">
                                    <Lock className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        type={showCurrentPassword ? 'text' : 'password'}
                                        value={currentPassword}
                                        onChange={(e) => setCurrentPassword(e.target.value)}
                                        placeholder="Masukkan sandi lama"
                                        className="w-full text-sm bg-white border border-slate-300 pl-10 pr-10 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                    />
                                    <button type="button" onClick={() => setShowCurrentPassword(!showCurrentPassword)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                                        {showCurrentPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <label className="text-xs font-bold text-slate-600">Kata Sandi Baru</label>
                                <div className="relative">
                                    <Lock className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        placeholder="Minimal 8 karakter"
                                        className="w-full text-sm bg-white border border-slate-300 pl-10 pr-10 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                    />
                                    <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                                        {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <label className="text-xs font-bold text-slate-600">Konfirmasi Kata Sandi</label>
                                <div className="relative">
                                    <Lock className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    <input
                                        type={showConfirmPassword ? 'text' : 'password'}
                                        value={passwordConfirmation}
                                        onChange={(e) => setPasswordConfirmation(e.target.value)}
                                        placeholder="Ulangi sandi baru"
                                        className="w-full text-sm bg-white border border-slate-300 pl-10 pr-10 py-2.5 rounded-xl outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all"
                                    />
                                    <button type="button" onClick={() => setShowConfirmPassword(!showConfirmPassword)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                                        {showConfirmPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Submit Actions */}
                    <button
                        type="submit"
                        disabled={saving}
                        className="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 transition-all disabled:opacity-50 cursor-pointer shadow-sm border border-blue-700"
                    >
                        {saving ? (
                            <>
                                <Loader2 className="w-4 h-4 animate-spin" />
                                Menyimpan...
                            </>
                        ) : (
                            <>
                                <Save className="w-4 h-4" />
                                Simpan Perubahan
                            </>
                        )}
                    </button>
                </div>
            </form>
        </div>
    );
};
