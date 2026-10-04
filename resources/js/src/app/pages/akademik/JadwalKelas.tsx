import React, { useState, useEffect } from 'react';
import { Plus, Calendar, Clock, BookOpen, Users, Save, X, AlertCircle } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface Option {
    id: number;
    name: string;
    subjects?: { id: number; name: string }[];
}

interface Period {
    period: number;
    start_time: string;
    end_time: string;
    label: string;
}

interface FormData {
    classes: Option[];
    teachers: Option[];
    subjects: Option[];
    days: { value: number; label: string }[];
    periods: Period[];
    active_year: string;
}

export const JadwalKelas = () => {
    const [formData, setFormData] = useState<FormData | null>(null);
    const [loading, setLoading] = useState(true);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    // Form states
    const [selectedClass, setSelectedClass] = useState('');
    const [selectedTeacher, setSelectedTeacher] = useState('');
    const [selectedSubject, setSelectedSubject] = useState('');
    const [selectedDay, setSelectedDay] = useState('');
    const [selectedPeriod, setSelectedPeriod] = useState('');
    const [academicYear, setAcademicYear] = useState('');

    useEffect(() => {
        fetchFormData();
    }, []);

    const fetchFormData = async () => {
        try {
            const response = await api.get('/admin/schedule/form-data');
            setFormData(response.data.data);
            setAcademicYear(response.data.data.active_year);
        } catch (error) {
            toast.error('Gagal mengambil data form');
        } finally {
            setLoading(false);
        }
    };

    const handleOpenModal = () => {
        setErrorMsg(null);
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setSelectedClass('');
        setSelectedTeacher('');
        setSelectedSubject('');
        setSelectedDay('');
        setSelectedPeriod('');
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        setErrorMsg(null);
        
        if (!selectedClass || !selectedTeacher || !selectedSubject || !selectedDay || !selectedPeriod) {
            setErrorMsg('Harap lengkapi semua field yang wajib diisi.');
            return;
        }

        const periodData = formData?.periods.find(p => p.period.toString() === selectedPeriod);
        if (!periodData) return;

        setSaving(true);
        try {
            await api.post('/admin/schedule/assignments', {
                academic_year: academicYear,
                school_class_id: selectedClass,
                teacher_id: selectedTeacher,
                subject_id: selectedSubject,
                day_of_week: selectedDay,
                start_time: periodData.start_time,
                end_time: periodData.end_time,
            });
            toast.success('Jadwal berhasil disimpan!');
            handleCloseModal();
            // TODO: Refresh daftar jadwal kelas jika nanti ada tabel jadwal di bawahnya
        } catch (error: any) {
            if (error.response?.data?.message) {
                setErrorMsg(error.response.data.message);
            } else {
                toast.error('Gagal menyimpan jadwal');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div className="p-6">Memuat data...</div>;
    if (!formData) return <div className="p-6 text-red-500">Gagal memuat form jadwal.</div>;

    // Filter subjects based on selected teacher if the backend provides relationships
    // Since we asked, we display all if no specific restriction, but prioritize if available.
    const selectedTeacherData = formData.teachers.find(t => t.id.toString() === selectedTeacher);
    const availableSubjects = selectedTeacherData?.subjects && selectedTeacherData.subjects.length > 0
        ? selectedTeacherData.subjects
        : formData.subjects;

    return (
        <div className="p-6 max-w-6xl mx-auto">
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Jadwal Kelas</h1>
                    <p className="text-sm text-slate-500 mt-1">Atur dan kelola jadwal pelajaran untuk setiap kelas.</p>
                </div>
                <button onClick={handleOpenModal} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors shadow-sm font-medium text-sm">
                    <Plus className="w-4 h-4" />
                    Atur Jadwal Baru
                </button>
            </div>

            {/* Placeholder for Schedule List (Grid/Table) */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm p-8 text-center">
                <Calendar className="w-12 h-12 text-slate-300 mx-auto mb-4" />
                <h3 className="text-lg font-semibold text-slate-700">Belum ada jadwal yang ditampilkan</h3>
                <p className="text-slate-500 mt-2 text-sm max-w-md mx-auto">Silakan gunakan tombol "Atur Jadwal Baru" di atas untuk menambahkan jadwal secara manual, atau pilih kelas untuk melihat daftar jadwalnya.</p>
            </div>

            {/* Modal Form Jadwal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 className="font-semibold text-slate-800 flex items-center gap-2">
                                <Calendar className="w-4 h-4 text-blue-600" />
                                Tambah Jadwal Pelajaran
                            </h2>
                            <button onClick={handleCloseModal} className="text-slate-400 hover:text-slate-600 transition-colors p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="p-5 overflow-y-auto">
                            {errorMsg && (
                                <div className="mb-4 bg-red-50 border border-red-100 rounded-lg p-3 flex gap-3 text-red-700 text-sm">
                                    <AlertCircle className="w-5 h-5 flex-shrink-0" />
                                    <p>{errorMsg}</p>
                                </div>
                            )}

                            <form id="jadwal-form" onSubmit={handleSave} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Tahun Ajaran</label>
                                    <input 
                                        type="text" 
                                        value={academicYear}
                                        onChange={(e) => setAcademicYear(e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Kelas</label>
                                    <div className="relative">
                                        <Users className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                                        <select 
                                            value={selectedClass} onChange={(e) => setSelectedClass(e.target.value)} required
                                            className="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-sm appearance-none bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                        >
                                            <option value="">Pilih Kelas...</option>
                                            {formData.classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Guru Pengampu</label>
                                    <select 
                                        value={selectedTeacher} onChange={(e) => setSelectedTeacher(e.target.value)} required
                                        className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                    >
                                        <option value="">Pilih Guru...</option>
                                        {formData.teachers.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Mata Pelajaran</label>
                                    <div className="relative">
                                        <BookOpen className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                                        <select 
                                            value={selectedSubject} onChange={(e) => setSelectedSubject(e.target.value)} required
                                            className="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-sm appearance-none bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                        >
                                            <option value="">Pilih Mata Pelajaran...</option>
                                            {availableSubjects.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                        </select>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-slate-700 mb-1.5">Hari</label>
                                        <select 
                                            value={selectedDay} onChange={(e) => setSelectedDay(e.target.value)} required
                                            className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                        >
                                            <option value="">Pilih Hari...</option>
                                            {formData.days.map(d => <option key={d.value} value={d.value}>{d.label}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-slate-700 mb-1.5">Jam Ke-</label>
                                        <div className="relative">
                                            <Clock className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                                            <select 
                                                value={selectedPeriod} onChange={(e) => setSelectedPeriod(e.target.value)} required
                                                className="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-sm appearance-none bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                            >
                                                <option value="">Pilih Jam...</option>
                                                {formData.periods.map(p => <option key={p.period} value={p.period}>{p.label}</option>)}
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div className="px-5 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                            <button type="button" onClick={handleCloseModal} disabled={saving} className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition-colors">
                                Batal
                            </button>
                            <button type="submit" form="jadwal-form" disabled={saving} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors shadow-sm font-medium text-sm disabled:opacity-70">
                                {saving ? 'Menyimpan...' : <><Save className="w-4 h-4" /> Simpan Jadwal</>}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
