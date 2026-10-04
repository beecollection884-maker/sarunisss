import React, { useEffect, useMemo, useState } from 'react';
import { Megaphone, Plus, Edit2, Trash2, Save, X, RefreshCw, Users, CheckCircle2 } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

type RoleValue = 'admin' | 'guru_mapel' | 'siswa' | 'wakasek_kesiswaan' | 'guru_piket' | 'orang_tua';

interface Creator {
    id: number;
    name: string;
}

interface Announcement {
    id: number;
    title: string;
    content: string;
    target_roles: RoleValue[] | null;
    creator?: Creator | null;
    created_at: string;
    updated_at: string;
}

const roleOptions: Array<{ value: RoleValue; label: string; description: string }> = [
    { value: 'admin', label: 'Admin', description: 'Administrator sekolah' },
    { value: 'guru_mapel', label: 'Guru Mapel', description: 'Guru pengampu mata pelajaran' },
    { value: 'siswa', label: 'Siswa', description: 'Seluruh peserta didik' },
    { value: 'orang_tua', label: 'Orang Tua', description: 'Wali murid' },
    { value: 'wakasek_kesiswaan', label: 'Wakasek Kesiswaan', description: 'Wakil kepala bidang kesiswaan' },
    { value: 'guru_piket', label: 'Guru Piket', description: 'Petugas piket sekolah' },
];

const emptyForm = {
    title: '',
    content: '',
    target_roles: [] as RoleValue[],
    target_all: true,
};

const formatDate = (value: string) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const targetLabel = (roles: RoleValue[] | null) => {
    if (!roles || roles.length === 0) return 'Semua Role';

    return roles
        .map((role) => roleOptions.find((item) => item.value === role)?.label || role)
        .join(', ');
};

export const Pengumuman = () => {
    const [announcements, setAnnouncements] = useState<Announcement[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editing, setEditing] = useState<Announcement | null>(null);
    const [form, setForm] = useState(emptyForm);

    const totalAllRoles = useMemo(
        () => announcements.filter((announcement) => !announcement.target_roles || announcement.target_roles.length === 0).length,
        [announcements],
    );

    useEffect(() => {
        fetchAnnouncements();
    }, []);

    const fetchAnnouncements = async () => {
        setLoading(true);
        try {
            const response = await api.get('/admin/announcements');
            setAnnouncements(response.data.data || []);
        } catch (error) {
            toast.error('Gagal memuat pengumuman');
        } finally {
            setLoading(false);
        }
    };

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setIsFormOpen(true);
    };

    const openEdit = (announcement: Announcement) => {
        const roles = announcement.target_roles || [];
        setEditing(announcement);
        setForm({
            title: announcement.title,
            content: announcement.content,
            target_roles: roles,
            target_all: roles.length === 0,
        });
        setIsFormOpen(true);
    };

    const closeForm = () => {
        setIsFormOpen(false);
        setEditing(null);
        setForm(emptyForm);
    };

    const toggleRole = (role: RoleValue) => {
        setForm((current) => {
            const exists = current.target_roles.includes(role);
            const targetRoles = exists
                ? current.target_roles.filter((item) => item !== role)
                : [...current.target_roles, role];

            return {
                ...current,
                target_all: false,
                target_roles: targetRoles,
            };
        });
    };

    const setAllRoles = () => {
        setForm((current) => ({
            ...current,
            target_all: true,
            target_roles: [],
        }));
    };

    const handleSave = async (event: React.FormEvent) => {
        event.preventDefault();
        setSaving(true);

        const payload = {
            title: form.title,
            content: form.content,
            target_roles: form.target_all ? null : form.target_roles,
        };

        try {
            if (editing) {
                await api.put(`/admin/announcements/${editing.id}`, payload);
                toast.success('Pengumuman berhasil diperbarui');
            } else {
                await api.post('/admin/announcements', payload);
                toast.success('Pengumuman berhasil dibuat');
            }

            closeForm();
            fetchAnnouncements();
        } catch (error: any) {
            const errors = error.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            toast.error((firstError as string) || error.response?.data?.message || 'Gagal menyimpan pengumuman');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (announcement: Announcement) => {
        if (!confirm(`Hapus pengumuman "${announcement.title}"?`)) return;

        try {
            await api.delete(`/admin/announcements/${announcement.id}`);
            toast.success('Pengumuman berhasil dihapus');
            fetchAnnouncements();
        } catch (error) {
            toast.error('Gagal menghapus pengumuman');
        }
    };

    return (
        <div className="min-h-full bg-slate-50 p-4 sm:p-6">
            <div className="mx-auto max-w-7xl space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Pengumuman</h1>
                        <p className="mt-1 text-sm text-slate-500">Buat pengumuman untuk semua role atau role tertentu.</p>
                    </div>

                    <button onClick={openCreate} className="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <Plus className="h-4 w-4" />
                        Buat Pengumuman
                    </button>
                </div>

                <div className="grid gap-3 md:grid-cols-3">
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-xs font-semibold uppercase text-slate-400">Total Pengumuman</p>
                        <p className="mt-2 text-2xl font-bold text-slate-900">{loading ? '-' : announcements.length}</p>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-xs font-semibold uppercase text-slate-400">Untuk Semua Role</p>
                        <p className="mt-2 text-2xl font-bold text-slate-900">{loading ? '-' : totalAllRoles}</p>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-xs font-semibold uppercase text-slate-400">Role Tersedia</p>
                        <p className="mt-2 text-2xl font-bold text-slate-900">{roleOptions.length}</p>
                    </div>
                </div>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div className="flex items-center gap-2">
                            <Megaphone className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-semibold text-slate-900">Daftar Pengumuman</h2>
                        </div>
                        <button onClick={fetchAnnouncements} className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                            <RefreshCw className="h-3.5 w-3.5" />
                            Muat Ulang
                        </button>
                    </div>

                    {loading ? (
                        <div className="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
                            <RefreshCw className="h-4 w-4 animate-spin" />
                            Memuat pengumuman...
                        </div>
                    ) : announcements.length === 0 ? (
                        <div className="p-10 text-center text-sm text-slate-500">Belum ada pengumuman.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {announcements.map((announcement) => (
                                <article key={announcement.id} className="p-4 transition hover:bg-slate-50/70">
                                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="font-semibold text-slate-900">{announcement.title}</h3>
                                                <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                                    <Users className="h-3.5 w-3.5" />
                                                    {targetLabel(announcement.target_roles)}
                                                </span>
                                            </div>
                                            <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">{announcement.content}</p>
                                            <p className="mt-3 text-xs text-slate-400">
                                                Dibuat {announcement.creator?.name ? `oleh ${announcement.creator.name}` : ''} pada {formatDate(announcement.created_at)}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 justify-end gap-1.5">
                                            <button onClick={() => openEdit(announcement)} className="rounded-lg p-2 text-slate-500 transition hover:bg-white hover:text-slate-900">
                                                <Edit2 className="h-4 w-4" />
                                            </button>
                                            <button onClick={() => handleDelete(announcement)} className="rounded-lg p-2 text-slate-500 transition hover:bg-red-50 hover:text-red-600">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>

            {isFormOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                    <div className="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-4">
                            <h2 className="font-semibold text-slate-900">{editing ? 'Edit Pengumuman' : 'Buat Pengumuman'}</h2>
                            <button onClick={closeForm} className="rounded-lg p-1 text-slate-400 transition hover:bg-white hover:text-slate-700">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSave} className="space-y-4 overflow-y-auto p-5">
                            <label className="block">
                                <span className="mb-1.5 block text-sm font-semibold text-slate-700">Judul</span>
                                <input
                                    value={form.title}
                                    onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))}
                                    className="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    placeholder="Contoh: Informasi kegiatan sekolah"
                                    required
                                />
                            </label>

                            <label className="block">
                                <span className="mb-1.5 block text-sm font-semibold text-slate-700">Isi Pengumuman</span>
                                <textarea
                                    value={form.content}
                                    onChange={(event) => setForm((current) => ({ ...current, content: event.target.value }))}
                                    className="min-h-36 w-full resize-y rounded-lg border border-slate-200 px-3 py-2 text-sm leading-6 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    placeholder="Tulis detail pengumuman..."
                                    required
                                />
                            </label>

                            <div>
                                <span className="mb-2 block text-sm font-semibold text-slate-700">Target Penerima</span>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        onClick={setAllRoles}
                                        className={`flex items-start gap-3 rounded-lg border p-3 text-left transition ${form.target_all ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'}`}
                                    >
                                        <CheckCircle2 className={`mt-0.5 h-4 w-4 ${form.target_all ? 'text-blue-600' : 'text-slate-300'}`} />
                                        <span>
                                            <span className="block text-sm font-semibold text-slate-900">Semua Role</span>
                                            <span className="block text-xs text-slate-500">Pengumuman tampil untuk semua pengguna.</span>
                                        </span>
                                    </button>

                                    {roleOptions.map((role) => {
                                        const checked = !form.target_all && form.target_roles.includes(role.value);

                                        return (
                                            <button
                                                key={role.value}
                                                type="button"
                                                onClick={() => toggleRole(role.value)}
                                                className={`flex items-start gap-3 rounded-lg border p-3 text-left transition ${checked ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'}`}
                                            >
                                                <CheckCircle2 className={`mt-0.5 h-4 w-4 ${checked ? 'text-blue-600' : 'text-slate-300'}`} />
                                                <span>
                                                    <span className="block text-sm font-semibold text-slate-900">{role.label}</span>
                                                    <span className="block text-xs text-slate-500">{role.description}</span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

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
