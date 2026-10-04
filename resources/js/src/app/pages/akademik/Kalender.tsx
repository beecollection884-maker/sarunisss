import React, { useState, useEffect } from 'react';
import { Plus, Edit2, Trash2, Calendar as CalendarIcon, RefreshCw, X, Save, Clock, Info } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface Event {
    id: number;
    title: string;
    category: string;
    type: string;
    start_date: string;
    end_date: string;
    description: string | null;
    is_holiday: boolean;
    is_active: boolean;
    academic_year: string;
    semester: string;
}

const EVENT_TYPES: Record<string, string> = {
    'hari_efektif': 'Hari Efektif',
    'libur_nasional': 'Libur Nasional',
    'libur_sekolah': 'Libur Sekolah',
    'ujian': 'Ujian',
    'event_sekolah': 'Event Sekolah'
};

export const Kalender = () => {
    const [events, setEvents] = useState<Event[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    
    // Config states
    const [academicYear, setAcademicYear] = useState('2025/2026');
    const [semester, setSemester] = useState('ganjil');

    // Modals
    const [isOpen, setIsOpen] = useState(false);
    const [editingEvent, setEditingEvent] = useState<Event | null>(null);

    // Form fields
    const [title, setTitle] = useState('');
    const [category, setCategory] = useState('');
    const [type, setType] = useState('event_sekolah');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [description, setDescription] = useState('');
    const [isHoliday, setIsHoliday] = useState(false);
    const [isActive, setIsActive] = useState(true);

    useEffect(() => {
        fetchActiveSettingsAndEvents();
    }, [academicYear, semester]);

    const fetchActiveSettingsAndEvents = async () => {
        setLoading(true);
        try {
            // First load active settings to default our filtering if not manually selected
            const settingRes = await api.get('/admin/setting');
            const data = settingRes.data.data || [];
            
            const yearSetting = data.find((s: any) => s.key === 'academic_year');
            const semSetting = data.find((s: any) => s.key === 'active_semester');
            
            if (yearSetting) setAcademicYear(yearSetting.value);
            if (semSetting) setSemester(semSetting.value);

            // Fetch events based on current active year & semester
            const response = await api.get('/admin/kalender-akademik-data', {
                params: {
                    academic_year: yearSetting?.value || academicYear,
                    semester: semSetting?.value || semester
                }
            });
            setEvents(response.data.data || []);
        } catch (error) {
            toast.error('Gagal mengambil data kalender akademik.');
        } finally {
            setLoading(false);
        }
    };

    const fetchEvents = async () => {
        setLoading(true);
        try {
            const response = await api.get('/admin/kalender-akademik-data', {
                params: {
                    academic_year: academicYear,
                    semester: semester
                }
            });
            setEvents(response.data.data || []);
        } catch (error) {
            toast.error('Gagal memuat agenda.');
        } finally {
            setLoading(false);
        }
    };

    const handleOpenCreate = () => {
        setEditingEvent(null);
        setTitle('');
        setCategory('');
        setType('event_sekolah');
        setStartDate('');
        setEndDate('');
        setDescription('');
        setIsHoliday(false);
        setIsActive(true);
        setIsOpen(true);
    };

    const handleOpenEdit = (event: Event) => {
        setEditingEvent(event);
        setTitle(event.title);
        setCategory(event.category);
        setType(event.type);
        setStartDate(event.start_date.substring(0, 10));
        setEndDate(event.end_date.substring(0, 10));
        setDescription(event.description || '');
        setIsHoliday(event.is_holiday);
        setIsActive(event.is_active);
        setIsOpen(true);
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        const payload = {
            academic_year: academicYear,
            semester,
            title,
            category: category || EVENT_TYPES[type],
            type,
            start_date: startDate,
            end_date: endDate,
            description: description || null,
            is_holiday: isHoliday,
            is_active: isActive,
        };

        try {
            if (editingEvent) {
                await api.put(`/admin/kalender-akademik-data/${editingEvent.id}`, payload);
                toast.success('Agenda kalender akademik berhasil diperbarui!');
            } else {
                await api.post('/admin/kalender-akademik-data', payload);
                toast.success('Agenda baru berhasil ditambahkan!');
            }
            setIsOpen(false);
            fetchEvents();
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal menyimpan agenda.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Apakah Anda yakin ingin menghapus agenda ini?')) return;
        try {
            await api.delete(`/admin/kalender-akademik-data/${id}`);
            toast.success('Agenda berhasil dihapus.');
            fetchEvents();
        } catch (error) {
            toast.error('Gagal menghapus agenda.');
        }
    };

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'hari_efektif': return 'bg-emerald-50 text-emerald-700 border-emerald-100';
            case 'libur_nasional': return 'bg-rose-50 text-rose-700 border-rose-100';
            case 'libur_sekolah': return 'bg-amber-50 text-amber-700 border-amber-100';
            case 'ujian': return 'bg-indigo-50 text-indigo-700 border-indigo-100';
            default: return 'bg-blue-50 text-blue-700 border-blue-100';
        }
    };

    return (
        <div className="p-6 max-w-7xl mx-auto">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Kalender Akademik</h1>
                    <p className="text-sm text-slate-500 mt-1">Kelola agenda sekolah, libur nasional, libur sekolah, dan masa ujian.</p>
                </div>
                <button onClick={handleOpenCreate} className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm self-start">
                    <Plus className="w-4 h-4" />
                    Tambah Agenda
                </button>
            </div>

            {/* Filter */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6 flex flex-wrap gap-4 items-center">
                <div className="flex items-center gap-2">
                    <label className="text-sm text-slate-500 font-medium">Tahun Ajaran:</label>
                    <input
                        type="text"
                        value={academicYear}
                        onChange={(e) => setAcademicYear(e.target.value)}
                        className="px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 max-w-[120px] text-center"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <label className="text-sm text-slate-500 font-medium">Semester:</label>
                    <select
                        value={semester}
                        onChange={(e) => setSemester(e.target.value)}
                        className="px-3 py-1.5 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="ganjil">Ganjil</option>
                        <option value="genap">Genap</option>
                    </select>
                </div>
                <button onClick={fetchEvents} className="flex items-center gap-1.5 text-xs text-blue-600 font-semibold hover:underline ml-auto">
                    <RefreshCw className="w-3.5 h-3.5" /> Refresh Data
                </button>
            </div>

            {/* List Agenda */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                {loading ? (
                    <div className="p-8 text-center text-slate-500 flex items-center justify-center gap-2">
                        <RefreshCw className="w-5 h-5 animate-spin" /> Memuat agenda kalender...
                    </div>
                ) : events.length === 0 ? (
                    <div className="p-12 text-center text-slate-400">
                        <CalendarIcon className="w-12 h-12 text-slate-300 mx-auto mb-3" />
                        <p className="font-semibold text-slate-600">Belum ada agenda terdaftar</p>
                        <p className="text-sm text-slate-400 mt-1">Silakan tambahkan agenda untuk Tahun Ajaran & Semester ini.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                    <th className="py-3 px-6">Tanggal</th>
                                    <th className="py-3 px-6">Judul Agenda</th>
                                    <th className="py-3 px-4">Tipe</th>
                                    <th className="py-3 px-4">Kategori</th>
                                    <th className="py-3 px-4">Keterangan</th>
                                    <th className="py-3 px-4">Status</th>
                                    <th className="py-3 px-6 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                {events.map((event) => (
                                    <tr key={event.id} className="hover:bg-slate-50/50 transition-colors">
                                        <td className="py-3.5 px-6 font-medium text-slate-900 whitespace-nowrap">
                                            <div className="flex items-center gap-2">
                                                <Clock className="w-3.5 h-3.5 text-slate-400" />
                                                <span>
                                                    {event.start_date.substring(0, 10)}
                                                    {event.start_date !== event.end_date && ` s.d ${event.end_date.substring(0, 10)}`}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="py-3.5 px-6 font-semibold text-slate-900">{event.title}</td>
                                        <td className="py-3.5 px-4">
                                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold border ${getTypeColor(event.type)}`}>
                                                {EVENT_TYPES[event.type] || event.type}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-xs text-slate-500">{event.category}</td>
                                        <td className="py-3.5 px-4 text-xs text-slate-500 max-w-xs truncate">{event.description || '-'}</td>
                                        <td className="py-3.5 px-4">
                                            {event.is_holiday ? (
                                                <span className="bg-rose-50 text-rose-600 px-2 py-0.5 border border-rose-100 rounded-full text-xs font-medium">Libur KBM</span>
                                            ) : (
                                                <span className="bg-emerald-50 text-emerald-600 px-2 py-0.5 border border-emerald-100 rounded-full text-xs font-medium">Aktif KBM</span>
                                            )}
                                        </td>
                                        <td className="py-3.5 px-6 text-right">
                                            <div className="flex justify-end gap-1.5">
                                                <button onClick={() => handleOpenEdit(event)} className="p-1.5 hover:bg-slate-100 text-slate-600 hover:text-slate-900 rounded-md transition-colors">
                                                    <Edit2 className="w-4 h-4" />
                                                </button>
                                                <button onClick={() => handleDelete(event.id)} className="p-1.5 hover:bg-red-50 text-slate-600 hover:text-red-600 rounded-md transition-colors">
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Modal Event Form */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 className="font-semibold text-slate-800">
                                {editingEvent ? 'Edit Agenda KBM' : 'Tambah Agenda Kalender'}
                            </h2>
                            <button onClick={() => setIsOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSave} className="p-5 space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Judul Agenda (Wajib)</label>
                                <input type="text" value={title} onChange={(e) => setTitle(e.target.value)} required placeholder="Misal: Ujian Tengah Semester" className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Tipe Agenda</label>
                                    <select value={type} onChange={(e) => setType(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                                        {Object.entries(EVENT_TYPES).map(([k, v]) => (
                                            <option key={k} value={k}>{v}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Kategori/Label</label>
                                    <input type="text" value={category} onChange={(e) => setCategory(e.target.value)} placeholder="Misal: UTS / Libur Semester" className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Mulai</label>
                                    <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} required className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Selesai</label>
                                    <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} required className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Keterangan / KBM Detail</label>
                                <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} placeholder="Detail keterangan agenda..." className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                            </div>

                            <div className="flex flex-col gap-2 bg-slate-50 border border-slate-100 rounded-lg p-3 text-sm">
                                <label className="flex items-center gap-2.5 font-medium text-slate-700 cursor-pointer">
                                    <input type="checkbox" checked={isHoliday} onChange={(e) => setIsHoliday(e.target.checked)} className="rounded text-blue-600" />
                                    Tandai Sebagai Libur KBM (Libur Sekolah/Nasional)
                                </label>
                                <label className="flex items-center gap-2.5 font-medium text-slate-700 cursor-pointer">
                                    <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} className="rounded text-blue-600" />
                                    Aktifkan Agenda Ini
                                </label>
                            </div>

                            <div className="pt-2 border-t border-slate-100 flex justify-end gap-2.5">
                                <button type="button" onClick={() => setIsOpen(false)} className="px-4 py-2 text-sm font-medium text-slate-600">Batal</button>
                                <button type="submit" disabled={saving} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm">
                                    {saving ? 'Menyimpan...' : 'Simpan Agenda'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};
