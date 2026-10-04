import React, { useState, useEffect } from 'react';
import { Save, RefreshCw, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface Setting {
    id: number;
    key: string;
    label: string;
    value: string;
    type: string;
    description: string;
}

const settingMeta: Record<string, { label: string; type: string; description: string }> = {
    academic_year: {
        label: 'Tahun Ajaran Aktif',
        type: 'text',
        description: 'Tahun ajaran default untuk data akademik.',
    },
    active_semester: {
        label: 'Semester Aktif',
        type: 'select',
        description: 'Semester aktif untuk kalender akademik dan agenda dashboard.',
    },
    school_name: {
        label: 'Nama Sekolah',
        type: 'text',
        description: 'Nama sekolah yang tampil di portal dan laporan.',
    },
    school_start_date: {
        label: 'Tanggal Awal Masuk Sekolah',
        type: 'date',
        description: 'Tanggal pertama awal masuk sekolah / mulai KBM semester aktif.',
    },
    school_end_date: {
        label: 'Tanggal Akhir Masuk Sekolah',
        type: 'date',
        description: 'Tanggal akhir masuk sekolah / akhir KBM semester aktif.',
    },
    school_saturday_enabled: {
        label: 'Sabtu Masuk',
        type: 'boolean',
        description: 'Tentukan apakah hari Sabtu dihitung sebagai pertemuan KBM.',
    },
};

export const PengaturanAkademik = () => {
    const [settings, setSettings] = useState<Setting[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const [academicYear, setAcademicYear] = useState('');
    const [activeSemester, setActiveSemester] = useState('');
    const [schoolName, setSchoolName] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [saturdayEnabled, setSaturdayEnabled] = useState(true);

    useEffect(() => {
        fetchSettings();
    }, []);

    const fetchSettings = async () => {
        try {
            const response = await api.get('/admin/setting');
            const data = response.data.data as Setting[];
            setSettings(data);

            const yearSetting = data.find(s => s.key === 'academic_year');
            const semSetting = data.find(s => s.key === 'active_semester');
            const nameSetting = data.find(s => s.key === 'school_name');
            const startSetting = data.find(s => s.key === 'school_start_date');
            const endSetting = data.find(s => s.key === 'school_end_date');
            const saturdaySetting = data.find(s => s.key === 'school_saturday_enabled');

            if (yearSetting) setAcademicYear(yearSetting.value);
            if (semSetting) setActiveSemester(semSetting.value);
            if (nameSetting) setSchoolName(nameSetting.value);
            if (startSetting) setStartDate(startSetting.value);
            if (endSetting) setEndDate(endSetting.value);
            if (saturdaySetting) setSaturdayEnabled(['1', 'true', 'yes', 'on'].includes(String(saturdaySetting.value).toLowerCase()));
        } catch (error) {
            toast.error('Gagal mengambil data pengaturan');
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();

        if (startDate && endDate && endDate < startDate) {
            toast.error('Akhir KBM tidak boleh lebih awal dari awal KBM.');
            return;
        }

        setSaving(true);

        try {
            const updates = [
                { key: 'academic_year', value: academicYear },
                { key: 'active_semester', value: activeSemester },
                { key: 'school_name', value: schoolName },
                { key: 'school_start_date', value: startDate },
                { key: 'school_end_date', value: endDate },
                { key: 'school_saturday_enabled', value: saturdayEnabled ? '1' : '0' },
            ];

            for (const item of updates) {
                const settingObj = settings.find(s => s.key === item.key);
                if (settingObj) {
                    await api.patch(`/admin/setting/${settingObj.id}/value`, {
                        value: item.value,
                    });
                } else if (item.value !== '') {
                    const meta = settingMeta[item.key];
                    await api.post('/admin/setting', {
                        key: item.key,
                        label: meta?.label ?? item.key,
                        value: item.value,
                        type: meta?.type ?? 'text',
                        description: meta?.description ?? '',
                    });
                }
            }

            toast.success('Pengaturan berhasil diperbarui!');
            fetchSettings();
        } catch (error: any) {
            const msg = error?.response?.data?.message ?? 'Gagal memperbarui pengaturan';
            toast.error(msg);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="p-6 flex items-center gap-2 text-slate-500">
                <RefreshCw className="w-5 h-5 animate-spin" />
                <span>Memuat pengaturan...</span>
            </div>
        );
    }

    return (
        <div className="p-6 max-w-3xl mx-auto">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-slate-800">Pengaturan Akademik</h1>
                <p className="text-sm text-slate-500 mt-1">Konfigurasi tahun ajaran, semester aktif, rentang KBM, dan informasi sekolah.</p>
            </div>

            <form onSubmit={handleSave} className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div className="p-6 space-y-6">
                    <div>
                        <label className="block text-sm font-semibold text-slate-700 mb-1.5">Nama Sekolah</label>
                        <input
                            type="text"
                            value={schoolName}
                            onChange={(e) => setSchoolName(e.target.value)}
                            className="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                            placeholder="SMP IP YAKIN"
                            required
                        />
                        <p className="text-xs text-slate-400 mt-1">Nama sekolah yang tampil di portal dan laporan.</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-1.5">Tahun Ajaran Aktif</label>
                            <input
                                type="text"
                                value={academicYear}
                                onChange={(e) => setAcademicYear(e.target.value)}
                                className="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                placeholder="2025/2026"
                                required
                            />
                            <p className="text-xs text-slate-400 mt-1">Format: YYYY/YYYY, misalnya 2025/2026.</p>
                        </div>

                        <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-1.5">Semester Aktif</label>
                            <select
                                value={activeSemester}
                                onChange={(e) => setActiveSemester(e.target.value)}
                                className="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                required
                            >
                                <option value="ganjil">Ganjil</option>
                                <option value="genap">Genap</option>
                            </select>
                            <p className="text-xs text-slate-400 mt-1">Ganjil/Genap untuk semester berjalan.</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-1.5">Mulai Masuk Sekolah (Awal KBM)</label>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                className="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                required
                            />
                            <p className="text-xs text-slate-400 mt-1">Tanggal ini dihitung sebagai Pertemuan 1 jika masuk hari efektif.</p>
                        </div>

                        <div>
                            <label className="block text-sm font-semibold text-slate-700 mb-1.5">Akhir Masuk Sekolah (Akhir KBM)</label>
                            <input
                                type="date"
                                value={endDate}
                                min={startDate || undefined}
                                onChange={(e) => setEndDate(e.target.value)}
                                className="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                required
                            />
                            <p className="text-xs text-slate-400 mt-1">Pertemuan dihitung sampai tanggal ini.</p>
                        </div>
                    </div>

                    <label className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 cursor-pointer hover:border-blue-200 hover:bg-blue-50/40 transition-colors">
                        <input
                            type="checkbox"
                            checked={saturdayEnabled}
                            onChange={(e) => setSaturdayEnabled(e.target.checked)}
                            className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span>
                            <span className="block text-sm font-semibold text-slate-800">Sabtu masuk?</span>
                            <span className="mt-1 block text-xs text-slate-500">Jika aktif, hari Sabtu ikut dihitung sebagai pertemuan. Jika tidak aktif, Sabtu dilewati seperti hari Minggu.</span>
                        </span>
                    </label>

                    <div className="bg-blue-50 border border-blue-100 rounded-lg p-4 flex gap-3 text-blue-800 text-sm">
                        <AlertTriangle className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                        <div>
                            <span className="font-semibold">Perhatian:</span> Mengubah rentang KBM akan mengubah daftar pertemuan yang muncul pada absensi kelas. Pastikan tanggal awal, tanggal akhir, dan aturan Sabtu sudah sesuai kalender sekolah.
                        </div>
                    </div>
                </div>

                <div className="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                    <button
                        type="submit"
                        disabled={saving}
                        className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors shadow-sm font-semibold text-sm disabled:opacity-75"
                    >
                        {saving ? 'Menyimpan...' : <><Save className="w-4 h-4" /> Simpan Pengaturan</>}
                    </button>
                </div>
            </form>
        </div>
    );
};
