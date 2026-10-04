import React, { useState, useEffect } from 'react';
import { Plus, Search, Edit2, Trash2, Download, Upload, Save, X, RefreshCw, AlertCircle, Calendar, BookOpen, AlertTriangle, CheckCircle, XCircle, ChevronDown, ChevronUp } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface Subject {
    id: number;
    code: string;
    name: string;
    description: string | null;
}

interface Teacher {
    id: number;
    name: string;
    nip: string;
}

interface SchoolClass {
    id: number;
    name: string;
}

interface TeachingAssignment {
    id: number;
    teacher_id: number;
    subject_id: number;
    school_class_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    room: string | null;
    teacher?: Teacher;
    subject?: Subject;
    school_class?: SchoolClass;
}

interface ImportError {
    row: number;
    messages: string[];
}

interface ImportResult {
    created: number;
    updated: number;
    failed: number;
    errors: ImportError[];
}

const DAY_NAMES = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

export const Mapel = () => {
    const [activeTab, setActiveTab] = useState<'jadwal' | 'master'>('jadwal');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [importing, setImporting] = useState(false);

    // Data lists
    const [assignments, setAssignments] = useState<TeachingAssignment[]>([]);
    const [subjects, setSubjects] = useState<Subject[]>([]);
    const [teachers, setTeachers] = useState<Teacher[]>([]);
    const [classes, setClasses] = useState<SchoolClass[]>([]);

    // Modals
    const [isJadwalFormOpen, setIsJadwalFormOpen] = useState(false);
    const [isMasterFormOpen, setIsMasterFormOpen] = useState(false);
    const [isImportOpen, setIsImportOpen] = useState(false);
    const [isImportResultOpen, setIsImportResultOpen] = useState(false);

    // Editing states
    const [editingAssignment, setEditingAssignment] = useState<TeachingAssignment | null>(null);
    const [editingSubject, setEditingSubject] = useState<Subject | null>(null);

    // Form fields - Jadwal
    const [selectedTeacher, setSelectedTeacher] = useState('');
    const [selectedSubject, setSelectedSubject] = useState('');
    const [selectedClass, setSelectedClass] = useState('');
    const [selectedDay, setSelectedDay] = useState('0');
    const [startTime, setStartTime] = useState('');
    const [endTime, setEndTime] = useState('');
    const [room, setRoom] = useState('');
    const [academicYear, setAcademicYear] = useState('2025/2026');

    // Form fields - Master Mapel
    const [mapelCode, setMapelCode] = useState('');
    const [mapelName, setMapelName] = useState('');
    const [mapelDesc, setMapelDesc] = useState('');

    // Import file
    const [importFile, setImportFile] = useState<File | null>(null);

    // Error & Conflict states
    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
    const [importResult, setImportResult] = useState<ImportResult | null>(null);

    // Filter / search
    const [searchQuery, setSearchQuery] = useState('');
    const [filterDay, setFilterDay] = useState<string>('');

    useEffect(() => {
        fetchInitialData();
    }, []);

    const fetchInitialData = async () => {
        setLoading(true);
        try {
            const listParams = { per_page: 500 };
            const [assignRes, subjRes, teachRes, classRes] = await Promise.all([
                api.get('/admin/jadwal-ajar', { params: listParams }),
                api.get('/admin/mapel', { params: listParams }),
                api.get('/admin/guru', { params: listParams }),
                api.get('/admin/kelas', { params: listParams })
            ]);

            setAssignments(assignRes.data.data || []);
            setSubjects(subjRes.data.data || []);
            setTeachers(teachRes.data.data || []);
            setClasses(classRes.data.data || []);
        } catch (error) {
            toast.error('Gagal memuat data');
        } finally {
            setLoading(false);
        }
    };

    const handleOpenJadwalCreate = () => {
        setEditingAssignment(null);
        setSelectedTeacher('');
        setSelectedSubject('');
        setSelectedClass('');
        setSelectedDay('0');
        setStartTime('');
        setEndTime('');
        setRoom('');
        setFormErrors({});
        setIsJadwalFormOpen(true);
    };

    const handleOpenJadwalEdit = (assignment: TeachingAssignment) => {
        setEditingAssignment(assignment);
        setSelectedTeacher(assignment.teacher_id.toString());
        setSelectedSubject(assignment.subject_id.toString());
        setSelectedClass(assignment.school_class_id.toString());
        setSelectedDay(assignment.day_of_week.toString());
        setStartTime(assignment.start_time.substring(0, 5));
        setEndTime(assignment.end_time.substring(0, 5));
        setRoom(assignment.room || '');
        setFormErrors({});
        setIsJadwalFormOpen(true);
    };

    const handleSaveJadwal = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setFormErrors({});

        const payload = {
            academic_year: academicYear,
            teacher_id: parseInt(selectedTeacher),
            subject_id: parseInt(selectedSubject),
            school_class_id: parseInt(selectedClass),
            day_of_week: parseInt(selectedDay),
            start_time: startTime,
            end_time: endTime,
            room: room || null,
        };

        try {
            if (editingAssignment) {
                await api.put(`/admin/jadwal-ajar/${editingAssignment.id}`, payload);
                toast.success('Jadwal berhasil diperbarui!');
            } else {
                await api.post('/admin/jadwal-ajar', payload);
                toast.success('Jadwal berhasil disimpan!');
            }
            setIsJadwalFormOpen(false);
            fetchInitialData();
        } catch (error: any) {
            if (error.response?.status === 422 && error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
                // Show the first conflict error as toast for immediate visibility
                const allErrors = Object.values(error.response.data.errors).flat() as string[];
                const conflictError = allErrors.find((msg: string) => msg.includes('TABRAKAN'));
                if (conflictError) {
                    toast.error(conflictError, { duration: 8000 });
                } else {
                    toast.error(allErrors[0] || 'Gagal menyimpan jadwal');
                }
            } else {
                const msg = error.response?.data?.message || 'Gagal menyimpan jadwal';
                toast.error(msg);
            }
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteJadwal = async (id: number) => {
        if (!confirm('Apakah Anda yakin ingin menghapus jadwal ini?')) return;
        try {
            await api.delete(`/admin/jadwal-ajar/${id}`);
            toast.success('Jadwal berhasil dihapus');
            fetchInitialData();
        } catch (error) {
            toast.error('Gagal menghapus jadwal');
        }
    };

    // Master Subject CRUD
    const handleOpenMasterCreate = () => {
        setEditingSubject(null);
        setMapelCode('');
        setMapelName('');
        setMapelDesc('');
        setIsMasterFormOpen(true);
    };

    const handleOpenMasterEdit = (subj: Subject) => {
        setEditingSubject(subj);
        setMapelCode(subj.code);
        setMapelName(subj.name);
        setMapelDesc(subj.description || '');
        setIsMasterFormOpen(true);
    };

    const handleSaveMaster = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        const payload = {
            code: mapelCode,
            name: mapelName,
            description: mapelDesc || null,
        };

        try {
            if (editingSubject) {
                await api.put(`/admin/mapel/${editingSubject.id}`, payload);
                toast.success('Mata pelajaran berhasil diperbarui!');
            } else {
                await api.post('/admin/mapel', payload);
                toast.success('Mata pelajaran baru berhasil ditambahkan!');
            }
            setIsMasterFormOpen(false);
            fetchInitialData();
        } catch (error: any) {
            const msg = error.response?.data?.message || 'Gagal menyimpan data';
            toast.error(msg);
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteMaster = async (id: number) => {
        if (!confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini? (Ini dapat menghapus jadwal terkait)')) return;
        try {
            await api.delete(`/admin/mapel/${id}`);
            toast.success('Mata pelajaran berhasil dihapus');
            fetchInitialData();
        } catch (error) {
            toast.error('Gagal menghapus mata pelajaran');
        }
    };

    // Import
    const handleImport = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!importFile) return;

        setImporting(true);
        const formData = new FormData();
        formData.append('file', importFile);

        try {
            const response = await api.post('/admin/import/jadwal', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            const result = response.data as ImportResult;
            setImportResult(result);
            setIsImportOpen(false);
            setImportFile(null);
            setIsImportResultOpen(true);
            fetchInitialData();

            if (result.failed === 0) {
                toast.success(`Import berhasil! ${result.created} dibuat, ${result.updated} diperbarui.`);
            } else {
                toast.warning(`Import selesai dengan ${result.failed} error. ${result.created} dibuat, ${result.updated} diperbarui.`);
            }
        } catch (error: any) {
            const data = error.response?.data;
            if (data?.created !== undefined || data?.failed !== undefined) {
                // Server returned import result with errors
                setImportResult(data as ImportResult);
                setIsImportOpen(false);
                setImportFile(null);
                setIsImportResultOpen(true);
                fetchInitialData();
                toast.error(`Import selesai dengan ${data.failed} error.`);
            } else {
                toast.error(data?.message || 'Gagal mengimport data');
            }
        } finally {
            setImporting(false);
        }
    };

    const downloadTemplate = () => {
        window.open('/api/v1/admin/import/template/jadwal', '_blank');
    };

    // Filter assignments
    const filteredAssignments = assignments.filter((assign) => {
        const matchSearch = searchQuery === '' ||
            (assign.teacher?.name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (assign.subject?.name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (assign.school_class?.name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (assign.room || '').toLowerCase().includes(searchQuery.toLowerCase());
        const matchDay = filterDay === '' || assign.day_of_week.toString() === filterDay;
        return matchSearch && matchDay;
    });

    // Group by day
    const groupedByDay = filteredAssignments.reduce((acc, assign) => {
        const day = assign.day_of_week;
        if (!acc[day]) acc[day] = [];
        acc[day].push(assign);
        return acc;
    }, {} as Record<number, TeachingAssignment[]>);

    // Sort groups by time
    Object.values(groupedByDay).forEach(group => {
        group.sort((a, b) => a.start_time.localeCompare(b.start_time));
    });

    // Check for any conflict in a given assignment row against all other assignments
    const hasConflict = (target: TeachingAssignment): { teacherConflict: boolean; classConflict: boolean } => {
        let teacherConflict = false;
        let classConflict = false;
        for (const other of assignments) {
            if (other.id === target.id) continue;
            if (other.day_of_week !== target.day_of_week) continue;
            const overlaps = target.start_time < other.end_time && target.end_time > other.start_time;
            if (!overlaps) continue;
            if (other.teacher_id === target.teacher_id) teacherConflict = true;
            if (other.school_class_id === target.school_class_id) classConflict = true;
        }
        return { teacherConflict, classConflict };
    };

    const renderFormError = (field: string) => {
        const errors = formErrors[field];
        if (!errors || errors.length === 0) return null;
        return (
            <div className="mt-1.5 space-y-1">
                {errors.map((err, i) => (
                    <div key={i} className={`flex items-start gap-1.5 text-xs ${err.includes('TABRAKAN') ? 'text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2' : 'text-red-600'}`}>
                        {err.includes('TABRAKAN') ? (
                            <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5 text-red-500" />
                        ) : (
                            <XCircle className="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                        )}
                        <span>{err}</span>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <div className="p-6 max-w-7xl mx-auto">
            {/* Tab Switching */}
            <div className="flex border-b border-slate-200 mb-6">
                <button
                    onClick={() => setActiveTab('jadwal')}
                    className={`py-3 px-6 text-sm font-semibold border-b-2 transition-colors ${activeTab === 'jadwal' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                >
                    Jadwal Pelajaran
                </button>
                <button
                    onClick={() => setActiveTab('master')}
                    className={`py-3 px-6 text-sm font-semibold border-b-2 transition-colors ${activeTab === 'master' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                >
                    Master Mata Pelajaran
                </button>
            </div>

            {activeTab === 'jadwal' ? (
                // TAB 1: JADWAL PELAJARAN
                <div>
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-slate-800">Jadwal Pelajaran</h1>
                            <p className="text-sm text-slate-500 mt-1">Konfigurasi hari, jam, kelas, mata pelajaran, dan guru pengampu. Sistem akan otomatis mendeteksi tabrakan jadwal.</p>
                        </div>
                        <div className="flex gap-2.5">
                            <button onClick={downloadTemplate} className="flex items-center gap-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                                <Download className="w-4 h-4" />
                                Template CSV
                            </button>
                            <button onClick={() => setIsImportOpen(true)} className="flex items-center gap-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                                <Upload className="w-4 h-4" />
                                Import Jadwal
                            </button>
                            <button onClick={handleOpenJadwalCreate} className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                                <Plus className="w-4 h-4" />
                                Tambah Jadwal
                            </button>
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="flex flex-col sm:flex-row gap-3 mb-4">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <input
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari guru, mapel, kelas, ruangan..."
                                className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                        <select
                            value={filterDay}
                            onChange={(e) => setFilterDay(e.target.value)}
                            className="h-10 px-3 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">Semua Hari</option>
                            {DAY_NAMES.map((name, idx) => <option key={idx} value={idx}>{name}</option>)}
                        </select>
                    </div>

                    {/* Schedule Table */}
                    <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        {loading ? (
                            <div className="p-8 text-center text-slate-500 flex items-center justify-center gap-2">
                                <RefreshCw className="w-5 h-5 animate-spin" /> Memuat data...
                            </div>
                        ) : filteredAssignments.length === 0 ? (
                            <div className="p-12 text-center text-slate-400">
                                <Calendar className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                                <p className="font-semibold text-slate-600">Belum ada jadwal KBM</p>
                                <p className="text-sm text-slate-400 mt-1">Silakan tambahkan jadwal secara manual atau lakukan import.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                            <th className="py-3 px-4">Hari / Jam</th>
                                            <th className="py-3 px-4">Kelas</th>
                                            <th className="py-3 px-4">Mata Pelajaran</th>
                                            <th className="py-3 px-4">Guru Pengampu</th>
                                            <th className="py-3 px-4">Ruangan</th>
                                            <th className="py-3 px-4">Status</th>
                                            <th className="py-3 px-4 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                        {Object.entries(groupedByDay)
                                            .sort(([a], [b]) => Number(a) - Number(b))
                                            .map(([day, dayAssignments]) => (
                                            <React.Fragment key={day}>
                                                <tr className="bg-slate-50/70">
                                                    <td colSpan={7} className="py-2 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                        📅 {DAY_NAMES[Number(day)] || 'Hari Tidak Dikenal'} ({dayAssignments.length} jadwal)
                                                    </td>
                                                </tr>
                                                {dayAssignments.map((assign) => {
                                                    const conflict = hasConflict(assign);
                                                    const isConflict = conflict.teacherConflict || conflict.classConflict;
                                                    return (
                                                        <tr key={assign.id} className={`transition-colors ${isConflict ? 'bg-red-50/60 hover:bg-red-50' : 'hover:bg-slate-50/50'}`}>
                                                            <td className="py-3.5 px-4 font-semibold text-slate-900">
                                                                <span>{DAY_NAMES[assign.day_of_week] || '-'}</span>
                                                                <span className="block text-xs font-normal text-slate-500 mt-0.5">
                                                                    {assign.start_time.substring(0, 5)} - {assign.end_time.substring(0, 5)}
                                                                </span>
                                                            </td>
                                                            <td className="py-3.5 px-4">
                                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium border ${conflict.classConflict ? 'bg-red-50 text-red-700 border-red-200' : 'bg-blue-50 text-blue-700 border-blue-100'}`}>
                                                                    {assign.school_class?.name || '-'}
                                                                </span>
                                                            </td>
                                                            <td className="py-3.5 px-4 font-medium">{assign.subject?.name || '-'}</td>
                                                            <td className={`py-3.5 px-4 ${conflict.teacherConflict ? 'text-red-700 font-semibold' : 'text-slate-900'}`}>
                                                                {assign.teacher?.name || '-'}
                                                                {conflict.teacherConflict && (
                                                                    <span className="block text-xs text-red-500 font-normal mt-0.5">⚠️ Guru bentrok di jam ini</span>
                                                                )}
                                                            </td>
                                                            <td className="py-3.5 px-4 text-xs text-slate-500">{assign.room || 'Utama'}</td>
                                                            <td className="py-3.5 px-4">
                                                                {isConflict ? (
                                                                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-red-600 bg-red-50 px-2 py-1 rounded-full border border-red-200">
                                                                        <AlertTriangle className="w-3 h-3" /> Tabrakan
                                                                    </span>
                                                                ) : (
                                                                    <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full border border-emerald-200">
                                                                        <CheckCircle className="w-3 h-3" /> OK
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="py-3.5 px-4 text-right">
                                                                <div className="flex justify-end gap-1.5">
                                                                    <button onClick={() => handleOpenJadwalEdit(assign)} className="p-1.5 hover:bg-slate-100 text-slate-600 hover:text-slate-900 rounded-md transition-colors">
                                                                        <Edit2 className="w-4 h-4" />
                                                                    </button>
                                                                    <button onClick={() => handleDeleteJadwal(assign.id)} className="p-1.5 hover:bg-red-50 text-slate-600 hover:text-red-600 rounded-md transition-colors">
                                                                        <Trash2 className="w-4 h-4" />
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </React.Fragment>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Summary stats */}
                    {!loading && assignments.length > 0 && (
                        <div className="mt-4 flex flex-wrap gap-3 text-xs text-slate-500">
                            <span className="bg-slate-100 px-3 py-1.5 rounded-full">Total: {assignments.length} jadwal</span>
                            <span className="bg-slate-100 px-3 py-1.5 rounded-full">{teachers.length} guru</span>
                            <span className="bg-slate-100 px-3 py-1.5 rounded-full">{subjects.length} mapel</span>
                            <span className="bg-slate-100 px-3 py-1.5 rounded-full">{classes.length} kelas</span>
                            {(() => {
                                const conflictCount = assignments.filter(a => {
                                    const c = hasConflict(a);
                                    return c.teacherConflict || c.classConflict;
                                }).length;
                                return conflictCount > 0 ? (
                                    <span className="bg-red-100 text-red-700 px-3 py-1.5 rounded-full font-semibold">
                                        ⚠️ {conflictCount} tabrakan terdeteksi
                                    </span>
                                ) : (
                                    <span className="bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-full font-medium">
                                        ✅ Tidak ada tabrakan
                                    </span>
                                );
                            })()}
                        </div>
                    )}
                </div>
            ) : (
                // TAB 2: MASTER SUBJECTS
                <div>
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-slate-800">Master Mata Pelajaran</h1>
                            <p className="text-sm text-slate-500 mt-1">Daftar semua mata pelajaran yang tersedia di kurikulum sekolah.</p>
                        </div>
                        <button onClick={handleOpenMasterCreate} className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                            <Plus className="w-4 h-4" />
                            Tambah Mata Pelajaran
                        </button>
                    </div>

                    <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        {loading ? (
                            <div className="p-8 text-center text-slate-500 flex items-center justify-center gap-2">
                                <RefreshCw className="w-5 h-5 animate-spin" /> Memuat data...
                            </div>
                        ) : subjects.length === 0 ? (
                            <div className="p-12 text-center text-slate-400">
                                <BookOpen className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                                <p className="font-semibold text-slate-600">Belum ada mata pelajaran</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                            <th className="py-3 px-4">Kode Pelajaran</th>
                                            <th className="py-3 px-4">Nama Pelajaran</th>
                                            <th className="py-3 px-4">Deskripsi</th>
                                            <th className="py-3 px-4 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                        {subjects.map((subj) => (
                                            <tr key={subj.id} className="hover:bg-slate-50/50 transition-colors">
                                                <td className="py-3.5 px-4 font-mono font-semibold text-blue-600">{subj.code}</td>
                                                <td className="py-3.5 px-4 font-medium">{subj.name}</td>
                                                <td className="py-3.5 px-4 text-xs text-slate-500 max-w-sm truncate">{subj.description || '-'}</td>
                                                <td className="py-3.5 px-4 text-right">
                                                    <div className="flex justify-end gap-1.5">
                                                        <button onClick={() => handleOpenMasterEdit(subj)} className="p-1.5 hover:bg-slate-100 text-slate-600 hover:text-slate-900 rounded-md transition-colors">
                                                            <Edit2 className="w-4 h-4" />
                                                        </button>
                                                        <button onClick={() => handleDeleteMaster(subj.id)} className="p-1.5 hover:bg-red-50 text-slate-600 hover:text-red-600 rounded-md transition-colors">
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
                </div>
            )}

            {/* Modal Form Jadwal */}
            {isJadwalFormOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 className="font-semibold text-slate-800">
                                {editingAssignment ? 'Edit Jadwal Pelajaran' : 'Tambah Jadwal Baru'}
                            </h2>
                            <button onClick={() => setIsJadwalFormOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Conflict warning banner */}
                        {Object.keys(formErrors).length > 0 && Object.values(formErrors).flat().some(e => e.includes('TABRAKAN')) && (
                            <div className="mx-5 mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div className="flex items-center gap-2 text-red-700 font-semibold text-sm mb-1">
                                    <AlertTriangle className="w-4 h-4" />
                                    Terdeteksi Tabrakan Jadwal!
                                </div>
                                <p className="text-xs text-red-600">
                                    Sistem mendeteksi konflik jadwal. Periksa detail error di bawah pada masing-masing field. Ubah hari/jam atau pilih guru/kelas lain untuk menghindari tabrakan.
                                </p>
                            </div>
                        )}

                        <form onSubmit={handleSaveJadwal} className="p-5 space-y-4 overflow-y-auto">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Tahun Ajaran</label>
                                <input type="text" value={academicYear} onChange={(e) => setAcademicYear(e.target.value)} required className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-50 focus:outline-none" />
                                {renderFormError('academic_year')}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Kelas (Wajib)</label>
                                <select value={selectedClass} onChange={(e) => setSelectedClass(e.target.value)} required className={`w-full px-3 py-2 border rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 ${formErrors['school_class_id'] ? 'border-red-300 bg-red-50/30' : 'border-slate-200'}`}>
                                    <option value="">Pilih Kelas...</option>
                                    {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                {renderFormError('school_class_id')}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Guru Pengampu (Wajib)</label>
                                <select value={selectedTeacher} onChange={(e) => setSelectedTeacher(e.target.value)} required className={`w-full px-3 py-2 border rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 ${formErrors['teacher_id'] ? 'border-red-300 bg-red-50/30' : 'border-slate-200'}`}>
                                    <option value="">Pilih Guru...</option>
                                    {teachers.map(t => <option key={t.id} value={t.id}>{t.name} (NIP: {t.nip})</option>)}
                                </select>
                                {renderFormError('teacher_id')}
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Mata Pelajaran (Wajib)</label>
                                <select value={selectedSubject} onChange={(e) => setSelectedSubject(e.target.value)} required className={`w-full px-3 py-2 border rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 ${formErrors['subject_id'] ? 'border-red-300 bg-red-50/30' : 'border-slate-200'}`}>
                                    <option value="">Pilih Mata Pelajaran...</option>
                                    {subjects.map(s => <option key={s.id} value={s.id}>{s.name} ({s.code})</option>)}
                                </select>
                                {renderFormError('subject_id')}
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Hari</label>
                                    <select value={selectedDay} onChange={(e) => setSelectedDay(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none">
                                        {DAY_NAMES.map((name, idx) => <option key={idx} value={idx}>{name}</option>)}
                                    </select>
                                    {renderFormError('day_of_week')}
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Mulai</label>
                                    <input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} required className={`w-full px-3 py-2 border rounded-lg text-sm ${formErrors['start_time'] ? 'border-red-300' : 'border-slate-200'}`} />
                                    {renderFormError('start_time')}
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Selesai</label>
                                    <input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} required className={`w-full px-3 py-2 border rounded-lg text-sm ${formErrors['end_time'] ? 'border-red-300' : 'border-slate-200'}`} />
                                    {renderFormError('end_time')}
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Ruangan</label>
                                <input type="text" value={room} onChange={(e) => setRoom(e.target.value)} placeholder="Misal: Lab Komputer" className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                            </div>

                            {/* Info box */}
                            <div className="flex gap-2 text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded-lg p-3">
                                <AlertCircle className="w-5 h-5 text-blue-500 flex-shrink-0" />
                                <p>Sistem akan otomatis memeriksa apakah guru atau kelas yang dipilih sudah memiliki jadwal lain di waktu yang bertabrakan. Jika ada tabrakan, jadwal tidak akan tersimpan.</p>
                            </div>

                            <div className="pt-2 border-t border-slate-100 flex justify-end gap-2.5">
                                <button type="button" onClick={() => setIsJadwalFormOpen(false)} className="px-4 py-2 text-sm font-medium text-slate-600">Batal</button>
                                <button type="submit" disabled={saving} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-75">
                                    {saving ? 'Menyimpan...' : 'Simpan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Form Master Mapel */}
            {isMasterFormOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 className="font-semibold text-slate-800">
                                {editingSubject ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran'}
                            </h2>
                            <button onClick={() => setIsMasterFormOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSaveMaster} className="p-5 space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Kode Pelajaran (Wajib)</label>
                                <input type="text" value={mapelCode} onChange={(e) => setMapelCode(e.target.value)} required className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Nama Mata Pelajaran (Wajib)</label>
                                <input type="text" value={mapelName} onChange={(e) => setMapelName(e.target.value)} required className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Deskripsi</label>
                                <textarea value={mapelDesc} onChange={(e) => setMapelDesc(e.target.value)} rows={3} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                            </div>

                            <div className="pt-2 border-t border-slate-100 flex justify-end gap-2.5">
                                <button type="button" onClick={() => setIsMasterFormOpen(false)} className="px-4 py-2 text-sm font-medium text-slate-600">Batal</button>
                                <button type="submit" disabled={saving} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Import */}
            {isImportOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 className="font-semibold text-slate-800">Import Jadwal Pelajaran</h2>
                            <button onClick={() => setIsImportOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleImport} className="p-5 space-y-4">
                            <div className="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:border-blue-500 transition-colors">
                                <Upload className="w-10 h-10 text-slate-400 mx-auto mb-2" />
                                <span className="text-xs text-slate-500 block mb-3">Format file yang diperbolehkan: .csv atau .txt dari Excel</span>
                                <input
                                    type="file"
                                    accept=".csv,.txt,text/csv,text/plain"
                                    onChange={(e) => setImportFile(e.target.files?.[0] || null)}
                                    className="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 file:cursor-pointer hover:file:bg-blue-100"
                                    required
                                />
                            </div>

                            <div className="flex gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3">
                                <AlertTriangle className="w-5 h-5 text-amber-600 flex-shrink-0" />
                                <div>
                                    <p className="font-semibold mb-1">Validasi Otomatis Tabrakan Jadwal</p>
                                    <p>Saat import, sistem akan otomatis memeriksa setiap baris:</p>
                                    <ul className="list-disc ml-4 mt-1 space-y-0.5">
                                        <li><strong>Tabrakan Guru:</strong> Guru yang sama tidak boleh mengajar 2 kelas di waktu yang sama</li>
                                        <li><strong>Tabrakan Kelas:</strong> Satu kelas tidak boleh punya 2 mapel di waktu yang sama</li>
                                    </ul>
                                    <p className="mt-1.5">Baris dengan tabrakan akan di-<em>skip</em> dan dilaporkan sebagai error.</p>
                                </div>
                            </div>

                            <div className="flex gap-2 text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-lg p-3">
                                <AlertCircle className="w-5 h-5 text-slate-400 flex-shrink-0" />
                                <p>Format CSV disarankan memakai kolom: <strong>nip_guru, nama_mapel, nama_kelas, hari, jam_mulai, jam_selesai, ruangan</strong>. Kolom umum seperti nip, kode_mapel, kelas, mulai, selesai, ruang juga diterima.</p>
                            </div>

                            <div className="flex justify-end gap-2">
                                <button type="button" onClick={() => setIsImportOpen(false)} className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800">
                                    Batal
                                </button>
                                <button type="submit" disabled={importing} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-75">
                                    {importing ? 'Mengimport...' : 'Mulai Import'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Import Result */}
            {isImportResultOpen && importResult && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh]">
                        <div className={`px-5 py-4 border-b flex items-center justify-between ${importResult.failed > 0 ? 'bg-amber-50 border-amber-100' : 'bg-emerald-50 border-emerald-100'}`}>
                            <div className="flex items-center gap-2">
                                {importResult.failed > 0 ? (
                                    <AlertTriangle className="w-5 h-5 text-amber-600" />
                                ) : (
                                    <CheckCircle className="w-5 h-5 text-emerald-600" />
                                )}
                                <h2 className="font-semibold text-slate-800">
                                    Hasil Import Jadwal
                                </h2>
                            </div>
                            <button onClick={() => setIsImportResultOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="p-5 overflow-y-auto space-y-4">
                            {/* Summary */}
                            <div className="grid grid-cols-3 gap-3">
                                <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-center">
                                    <div className="text-2xl font-bold text-emerald-600">{importResult.created}</div>
                                    <div className="text-xs text-emerald-700 font-medium">Dibuat</div>
                                </div>
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                                    <div className="text-2xl font-bold text-blue-600">{importResult.updated}</div>
                                    <div className="text-xs text-blue-700 font-medium">Diperbarui</div>
                                </div>
                                <div className={`border rounded-lg p-3 text-center ${importResult.failed > 0 ? 'bg-red-50 border-red-200' : 'bg-slate-50 border-slate-200'}`}>
                                    <div className={`text-2xl font-bold ${importResult.failed > 0 ? 'text-red-600' : 'text-slate-400'}`}>{importResult.failed}</div>
                                    <div className={`text-xs font-medium ${importResult.failed > 0 ? 'text-red-700' : 'text-slate-500'}`}>Gagal</div>
                                </div>
                            </div>

                            {/* Error details */}
                            {importResult.errors.length > 0 && (
                                <div>
                                    <h3 className="text-sm font-semibold text-red-700 mb-2 flex items-center gap-1.5">
                                        <XCircle className="w-4 h-4" />
                                        Detail Error ({importResult.errors.length} baris gagal)
                                    </h3>
                                    <div className="space-y-2 max-h-[40vh] overflow-y-auto pr-1">
                                        {importResult.errors.map((err, idx) => (
                                            <div key={idx} className="bg-red-50 border border-red-200 rounded-lg p-3">
                                                <div className="flex items-center gap-2 mb-1.5">
                                                    <span className="text-xs font-bold text-red-800 bg-red-200 px-2 py-0.5 rounded-full">
                                                        Baris {err.row}
                                                    </span>
                                                </div>
                                                <ul className="text-xs text-red-700 space-y-1">
                                                    {err.messages.map((msg, msgIdx) => (
                                                        <li key={msgIdx} className="flex items-start gap-1.5">
                                                            {msg.includes('TABRAKAN') ? (
                                                                <AlertTriangle className="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-red-500" />
                                                            ) : (
                                                                <XCircle className="w-3 h-3 flex-shrink-0 mt-0.5" />
                                                            )}
                                                            <span>{msg}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {importResult.failed === 0 && (
                                <div className="text-center py-4">
                                    <CheckCircle className="w-12 h-12 text-emerald-400 mx-auto mb-2" />
                                    <p className="text-sm font-semibold text-emerald-700">Semua data berhasil diimport tanpa error!</p>
                                    <p className="text-xs text-slate-500 mt-1">Tidak ada tabrakan jadwal yang terdeteksi.</p>
                                </div>
                            )}
                        </div>

                        <div className="px-5 py-3 border-t border-slate-100 flex justify-end">
                            <button onClick={() => setIsImportResultOpen(false)} className="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
