import React, { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Check, Edit2, Eye, EyeOff, Lock, Plus, RefreshCw, Save, Search, Trash2, UserCog, X } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

const roleOptions = [
    { value: 'admin', label: 'Admin', description: 'Mengelola sistem dan data sekolah' },
    { value: 'guru_mapel', label: 'Guru Mapel', description: 'Akses pengajaran dan absensi mapel' },
    { value: 'siswa', label: 'Siswa', description: 'Akses jadwal dan catatan siswa' },
    { value: 'orang_tua', label: 'Orang Tua', description: 'Akses ringkasan dan catatan anak' },
    { value: 'wakasek_kesiswaan', label: 'Wakasek Kesiswaan', description: 'Akses kesiswaan dan pelanggaran' },
    { value: 'guru_piket', label: 'Guru Piket', description: 'Akses operasional piket' },
] as const;

type RoleValue = typeof roleOptions[number]['value'];

type Profile = {
    id: number;
    name?: string;
    nik?: string | null;
    nip?: string | null;
    nisn?: string | null;
    phone?: string | null;
    school_class?: { id: number; name: string } | null;
};

type Account = {
    id: number;
    name: string;
    email: string;
    roles: RoleValue[];
    email_verified_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    teacher_profile?: Profile | null;
    student_profile?: Profile | null;
};

type AccountForm = {
    name: string;
    email: string;
    password: string;
    roles: RoleValue[];
    email_verified: boolean;
    teacher_id: number | null;
    student_id: number | null;
};

const emptyForm: AccountForm = {
    name: '',
    email: '',
    password: '',
    roles: [],
    email_verified: true,
    teacher_id: null,
    student_id: null,
};

const formatDate = (value?: string | null) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const roleLabel = (role: string) => roleOptions.find((item) => item.value === role)?.label || role;

const profileLabel = (account: Account) => {
    const teacherProfile = account.teacher_profile || (account as any).teacherProfile;
    if (teacherProfile) {
        return `Guru${teacherProfile.nip ? ` - NIP ${teacherProfile.nip}` : ''}`;
    }

    const studentProfile = account.student_profile || (account as any).studentProfile;
    if (studentProfile) {
        return `Siswa${studentProfile.nisn ? ` - NISN ${studentProfile.nisn}` : ''}`;
    }

    return 'Belum terhubung profil';
};

const initialFromAccount = (account: Account): AccountForm => {
    const teacherProfile = account.teacher_profile || (account as any).teacherProfile;
    const studentProfile = account.student_profile || (account as any).studentProfile;

    return {
        name: account.name,
        email: account.email,
        password: '',
        roles: account.roles || [],
        email_verified: Boolean(account.email_verified_at),
        teacher_id: teacherProfile?.id ?? null,
        student_id: studentProfile?.id ?? null,
    };
};

export const UserAccounts = () => {
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [query, setQuery] = useState('');
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);
    const [form, setForm] = useState<AccountForm>(emptyForm);
    const [showPwd, setShowPwd] = useState(false);

    useEffect(() => {
        fetchAccounts();
    }, []);

    const fetchAccounts = async () => {
        setLoading(true);
        try {
            const response = await api.get('/admin/pengguna', { params: { per_page: 500 } });
            setAccounts(response.data.data || []);
        } catch (error) {
            toast.error('Gagal memuat data pengguna');
        } finally {
            setLoading(false);
        }
    };

    const filteredAccounts = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        if (!keyword) return accounts;

        return accounts.filter((account) => {
            const haystack = [
                account.name,
                account.email,
                ...(account.roles || []).map(roleLabel),
                profileLabel(account),
                account.teacher_profile?.name,
                account.student_profile?.name,
                account.teacher_profile?.nip,
                account.student_profile?.nisn,
                account.student_profile?.nik,
            ].filter(Boolean).join(' ').toLowerCase();

            return haystack.includes(keyword);
        });
    }, [accounts, query]);

    const roleCounts = useMemo(() => {
        return roleOptions.map((role) => ({
            ...role,
            count: accounts.filter((account) => account.roles?.includes(role.value)).length,
        }));
    }, [accounts]);

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setShowPwd(false);
        setIsFormOpen(true);
    };

    const openEdit = (account: Account) => {
        setEditing(account);
        setForm(initialFromAccount(account));
        setShowPwd(false);
        setIsFormOpen(true);
    };

    const closeForm = () => {
        setIsFormOpen(false);
        setEditing(null);
        setForm(emptyForm);
        setShowPwd(false);
    };

    const toggleRole = (role: RoleValue) => {
        setForm((current) => {
            const exists = current.roles.includes(role);
            return {
                ...current,
                roles: exists ? current.roles.filter((item) => item !== role) : [...current.roles, role],
            };
        });
    };

    const firstValidationError = (error: any) => {
        const errors = error.response?.data?.errors;
        if (!errors) return error.response?.data?.message || 'Gagal menyimpan data pengguna';

        return (Object.values(errors).flat()[0] as string) || 'Gagal menyimpan data pengguna';
    };

    const handleSave = async (event: React.FormEvent) => {
        event.preventDefault();
        setSaving(true);

        const payload: Record<string, unknown> = {
            name: form.name,
            email: form.email,
            roles: form.roles,
            email_verified: form.email_verified,
            teacher_id: form.teacher_id,
            student_id: form.student_id,
        };

        if (!editing || form.password.trim()) {
            payload.password = form.password;
            payload.password_confirmation = form.password;
        }

        try {
            if (editing) {
                await api.put(`/admin/pengguna/${editing.id}`, payload);
                toast.success('Akun pengguna berhasil diperbarui');
            } else {
                await api.post('/admin/pengguna', payload);
                toast.success('Akun pengguna berhasil dibuat');
            }

            closeForm();
            fetchAccounts();
        } catch (error: any) {
            toast.error(firstValidationError(error));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (account: Account) => {
        if (!confirm(`Hapus akun ${account.name}?`)) return;

        try {
            await api.delete(`/admin/pengguna/${account.id}`);
            toast.success('Akun pengguna berhasil dihapus');
            fetchAccounts();
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal menghapus akun pengguna');
        }
    };

    return (
        <div className="min-h-full bg-slate-50 p-4 sm:p-6">
            <div className="mx-auto max-w-7xl space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Semua Akun</h1>
                        <p className="mt-1 text-sm text-slate-500">Kelola seluruh akun pengguna, data profil terkait, status email, dan role akses.</p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button onClick={fetchAccounts} className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            <RefreshCw className="h-4 w-4" />
                            Muat Ulang
                        </button>
                        <button onClick={openCreate} className="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            <Plus className="h-4 w-4" />
                            Tambah Akun
                        </button>
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                    {roleCounts.map((role) => (
                        <div key={role.value} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="truncate text-xs font-semibold uppercase text-slate-400">{role.label}</p>
                            <p className="mt-2 text-2xl font-bold text-slate-900">{loading ? '-' : role.count}</p>
                        </div>
                    ))}
                </div>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-center gap-2">
                            <UserCog className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-semibold text-slate-900">Daftar Pengguna</h2>
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">{filteredAccounts.length} akun</span>
                        </div>

                        <div className="relative w-full lg:w-80">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Cari nama, email, role, profil..."
                                className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                    </div>

                    {loading ? (
                        <div className="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
                            <RefreshCw className="h-4 w-4 animate-spin" />
                            Memuat data pengguna...
                        </div>
                    ) : filteredAccounts.length === 0 ? (
                        <div className="p-10 text-center text-sm text-slate-500">Tidak ada akun pengguna yang cocok.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1120px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">Pengguna</th>
                                        <th className="px-4 py-3 font-semibold">Role</th>
                                        <th className="px-4 py-3 font-semibold">Profil Terkait</th>
                                        <th className="px-4 py-3 font-semibold">Verifikasi</th>
                                        <th className="px-4 py-3 font-semibold">Dibuat</th>
                                        <th className="px-4 py-3 text-right font-semibold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredAccounts.map((account) => (
                                        <tr key={account.id} className="transition hover:bg-slate-50/70">
                                            <td className="px-4 py-4 align-top">
                                                <p className="font-semibold text-slate-900">{account.name}</p>
                                                <p className="mt-1 text-xs text-slate-500">{account.email}</p>
                                                <p className="mt-1 text-[11px] text-slate-400">ID #{account.id}</p>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="flex max-w-sm flex-wrap gap-1.5">
                                                    {(account.roles || []).length > 0 ? account.roles.map((role) => (
                                                        <span key={role} className="rounded-full border border-blue-100 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                                            {roleLabel(role)}
                                                        </span>
                                                    )) : (
                                                        <span className="rounded-full border border-amber-100 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Belum ada role</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <p className="font-medium text-slate-700">{profileLabel(account)}</p>
                                                {account.teacher_profile?.name && <p className="mt-1 text-xs text-slate-500">{account.teacher_profile.name}</p>}
                                                {account.student_profile?.name && <p className="mt-1 text-xs text-slate-500">{account.student_profile.name}</p>}
                                                {account.student_profile?.school_class?.name && <p className="mt-1 text-xs text-slate-400">Kelas {account.student_profile.school_class.name}</p>}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                {account.email_verified_at ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                        <Check className="h-3.5 w-3.5" /> Terverifikasi
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                                                        <AlertCircle className="h-3.5 w-3.5" /> Belum
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-4 align-top text-xs text-slate-500">
                                                <p>{formatDate(account.created_at)}</p>
                                                <p className="mt-1 text-slate-400">Update {formatDate(account.updated_at)}</p>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="flex justify-end gap-1.5">
                                                    <button onClick={() => openEdit(account)} className="rounded-lg p-2 text-slate-500 transition hover:bg-white hover:text-slate-900" title="Edit akun">
                                                        <Edit2 className="h-4 w-4" />
                                                    </button>
                                                    <button onClick={() => handleDelete(account)} className="rounded-lg p-2 text-slate-500 transition hover:bg-red-50 hover:text-red-600" title="Hapus akun">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>

            {isFormOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                    <div className="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-4">
                            <h2 className="font-semibold text-slate-900">{editing ? 'Edit Akun Pengguna' : 'Tambah Akun Pengguna'}</h2>
                            <button onClick={closeForm} className="rounded-lg p-1 text-slate-400 transition hover:bg-white hover:text-slate-700">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSave} className="space-y-4 overflow-y-auto p-5">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="block">
                                    <span className="mb-1.5 block text-sm font-semibold text-slate-700">Nama</span>
                                    <input
                                        value={form.name}
                                        onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                                        className="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        required
                                    />
                                </label>
                                <label className="block">
                                    <span className="mb-1.5 block text-sm font-semibold text-slate-700">Email</span>
                                    <input
                                        type="email"
                                        value={form.email}
                                        onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))}
                                        className="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        required
                                    />
                                </label>
                            </div>

                            <label className="block">
                                <span className="mb-1.5 block text-sm font-semibold text-slate-700">Password {editing ? 'Baru' : ''}</span>
                                <div className="relative">
                                    <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        type={showPwd ? 'text' : 'password'}
                                        value={form.password}
                                        onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))}
                                        placeholder={editing ? 'Kosongkan jika tidak ingin mengganti password' : 'Minimal 8 karakter, huruf dan angka'}
                                        className="h-10 w-full rounded-lg border border-slate-200 pl-9 pr-10 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        required={!editing}
                                    />
                                    <button type="button" onClick={() => setShowPwd(!showPwd)} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                                        {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                            </label>

                            <div>
                                <span className="mb-2 block text-sm font-semibold text-slate-700">Role Akun</span>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {roleOptions.map((role) => {
                                        const checked = form.roles.includes(role.value);

                                        return (
                                            <button
                                                key={role.value}
                                                type="button"
                                                onClick={() => toggleRole(role.value)}
                                                className={`flex items-start gap-3 rounded-lg border p-3 text-left transition ${checked ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'}`}
                                            >
                                                <Check className={`mt-0.5 h-4 w-4 ${checked ? 'text-blue-600' : 'text-slate-300'}`} />
                                                <span>
                                                    <span className="block text-sm font-semibold text-slate-900">{role.label}</span>
                                                    <span className="block text-xs text-slate-500">{role.description}</span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <label className="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                                <input
                                    type="checkbox"
                                    checked={form.email_verified}
                                    onChange={(event) => setForm((current) => ({ ...current, email_verified: event.target.checked }))}
                                    className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span>
                                    <span className="block text-sm font-semibold text-slate-900">Tandai email terverifikasi</span>
                                    <span className="block text-xs text-slate-500">Matikan jika akun belum boleh dianggap terverifikasi.</span>
                                </span>
                            </label>

                            <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                                <button type="button" onClick={closeForm} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Batal
                                </button>
                                <button disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70">
                                    {saving ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                                    {saving ? 'Menyimpan...' : 'Simpan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};