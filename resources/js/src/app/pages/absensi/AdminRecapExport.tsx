import React, { useEffect, useMemo, useState, useCallback } from 'react';
import {
    AlertCircle, BarChart3, CalendarDays, Filter, RefreshCw, Search,
    Download, FileSpreadsheet, Users, BookOpen, GraduationCap, CheckCircle2,
    Clock, User, MapPin
} from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'alpha';
type RecapType = 'kelas' | 'mapel';
type ViewMode = 'ringkasan' | 'pertemuan';
type NotesFilter = 'all' | 'has-notes' | 'no-notes';

interface SchoolClass {
    id: number;
    name: string;
}

interface Subject {
    id: number;
    name: string;
    code?: string | null;
}

interface Student {
    id: number;
    nik?: string | null;
    nisn?: string | null;
    name: string;
}

interface Teacher {
    id: number;
    name: string;
}

interface TeachingAssignment {
    id: number;
    school_class_id: number;
    subject_id: number;
    subject?: Subject;
    school_class?: SchoolClass;
    teacher?: Teacher;
}

interface ClassAttendanceRecord {
    id: number;
    student_id: number;
    school_class_id: number;
    attendance_date: string;
    status: AttendanceStatus;
    notes?: string | null;
    student?: Student;
    school_class?: SchoolClass;
}

interface SubjectAttendanceRecord {
    id: number;
    student_id: number;
    teaching_assignment_id: number;
    attendance_date: string;
    status: AttendanceStatus;
    notes?: string | null;
    student?: Student;
    teaching_assignment?: TeachingAssignment;
}

const statusOptions: Array<{ value: AttendanceStatus; label: string; className: string }> = [
    { value: 'hadir', label: 'Hadir', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    { value: 'sakit', label: 'Sakit', className: 'border-amber-200 bg-amber-50 text-amber-700' },
    { value: 'izin', label: 'Izin', className: 'border-blue-200 bg-blue-50 text-blue-700' },
    { value: 'alpha', label: 'Alpha', className: 'border-rose-200 bg-rose-50 text-rose-700' },
];

const todayString = () => new Date().toISOString().slice(0, 10);

const monthStartString = () => {
    const date = new Date();
    date.setDate(1);
    return date.toISOString().slice(0, 10);
};

const cn = (...classes: any[]) => classes.filter(Boolean).join(' ');
const fieldClass = 'h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100';

export const AdminRecapExport = () => {
    const [recapType, setRecapType] = useState<RecapType>('kelas');
    const [viewMode, setViewMode] = useState<ViewMode>('ringkasan');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [query, setQuery] = useState('');

    // Filters
    const [dateFrom, setDateFrom] = useState(monthStartString());
    const [dateTo, setDateTo] = useState(todayString());
    const [selectedClassId, setSelectedClassId] = useState('');
    const [selectedSubjectId, setSelectedSubjectId] = useState('');
    const [selectedStudentId, setSelectedStudentId] = useState('');
    const [notesFilter, setNotesFilter] = useState<NotesFilter>('all');

    // Options dropdowns
    const [classes, setClasses] = useState<SchoolClass[]>([]);
    const [subjects, setSubjects] = useState<Subject[]>([]);
    const [students, setStudents] = useState<Student[]>([]);

    // Records loaded
    const [classRecords, setClassRecords] = useState<ClassAttendanceRecord[]>([]);
    const [subjectRecords, setSubjectRecords] = useState<SubjectAttendanceRecord[]>([]);

    // Load static option values
    useEffect(() => {
        const rowsFromResponse = <T,>(response: any): T[] => {
            const payload = response?.data?.data;
            return Array.isArray(payload) ? payload : [];
        };

        const loadMetadata = async () => {
            try {
                const [classRes, subjectRes, studentRes] = await Promise.all([
                    api.get('/admin/kelas', { params: { per_page: 500 } }),
                    api.get('/admin/mapel', { params: { per_page: 500 } }),
                    api.get('/admin/siswa', { params: { per_page: 500 } }),
                ]);

                setClasses(rowsFromResponse<SchoolClass>(classRes));
                setSubjects(rowsFromResponse<Subject>(subjectRes));
                setStudents(rowsFromResponse<Student>(studentRes));
            } catch (err) {
                console.error('Failed to load filter metadata:', err);
                toast.error('Gagal memuat pilihan filter.');
            }
        };
        loadMetadata();
    }, []);

    // Fetch records from database
    const fetchRecords = useCallback(async () => {
        setLoading(true);
        setLoadError('');
        try {
            const params: Record<string, string> = {
                date_from: dateFrom,
                date_to: dateTo,
            };
            if (selectedClassId) params.school_class_id = selectedClassId;
            if (selectedStudentId) params.student_id = selectedStudentId;

            if (recapType === 'kelas') {
                const res = await api.get('/admin/rekap-absensi-kelas', { params });
                setClassRecords(res.data.data || []);
            } else {
                if (selectedSubjectId) params.subject_id = selectedSubjectId;
                const res = await api.get('/admin/rekap-absensi-mapel', { params });
                setSubjectRecords(res.data.data || []);
            }
        } catch (err: any) {
            const message = err.response?.data?.message || 'Gagal memuat rekap absensi.';
            setLoadError(message);
            toast.error(message);
        } finally {
            setLoading(false);
        }
    }, [recapType, dateFrom, dateTo, selectedClassId, selectedSubjectId, selectedStudentId]);

    // Re-fetch when recap type is switched
    useEffect(() => {
        fetchRecords();
    }, [recapType]);

    // Reset filters
    const resetFilters = () => {
        setSelectedClassId('');
        setSelectedSubjectId('');
        setSelectedStudentId('');
        setNotesFilter('all');
        setDateFrom(monthStartString());
        setDateTo(todayString());
        setQuery('');
    };

    const applyNotesFilter = <T extends { notes?: string | null }>(records: T[]) => {
        if (notesFilter === 'all') {
            return records;
        }

        const hasNotes = (note?: string | null) => Boolean((note || '').trim());

        return records.filter((record) => {
            const shouldHaveNotes = notesFilter === 'has-notes';
            return shouldHaveNotes ? hasNotes(record.notes) : !hasNotes(record.notes);
        });
    };

    // Filtered records by search query
    const filteredClassRecords = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        const baseRecords = applyNotesFilter(classRecords);

        if (!keyword) return baseRecords;

        return baseRecords.filter(r => 
            (r.student?.name || '').toLowerCase().includes(keyword) ||
            (r.student?.nik || '').toLowerCase().includes(keyword) ||
            (r.school_class?.name || '').toLowerCase().includes(keyword) ||
            (r.notes || '').toLowerCase().includes(keyword) ||
            r.status.toLowerCase().includes(keyword)
        );
    }, [classRecords, query, notesFilter]);

    const filteredSubjectRecords = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        const baseRecords = applyNotesFilter(subjectRecords);

        if (!keyword) return baseRecords;

        return baseRecords.filter(r => 
            (r.student?.name || '').toLowerCase().includes(keyword) ||
            (r.student?.nik || '').toLowerCase().includes(keyword) ||
            (r.teaching_assignment?.subject?.name || '').toLowerCase().includes(keyword) ||
            (r.teaching_assignment?.school_class?.name || '').toLowerCase().includes(keyword) ||
            (r.notes || '').toLowerCase().includes(keyword) ||
            r.status.toLowerCase().includes(keyword)
        );
    }, [subjectRecords, query, notesFilter]);

    // Cumulative Summaries grouping
    const classRecapRows = useMemo(() => {
        const groups = new Map<number, ClassAttendanceRecord[]>();
        filteredClassRecords.forEach(r => {
            groups.set(r.student_id, [...(groups.get(r.student_id) || []), r]);
        });

        return Array.from(groups.values()).map(items => {
            const first = items[0];
            const hadir = items.filter(r => r.status === 'hadir').length;
            const total = items.length;

            return {
                student: first.student,
                className: first.school_class?.name || 'Kelas',
                total,
                presentRate: total > 0 ? Math.round((hadir / total) * 100) : 0,
                summary: statusOptions.map(opt => ({
                    ...opt,
                    total: items.filter(r => r.status === opt.value).length
                }))
            };
        }).sort((a, b) => (a.student?.name || '').localeCompare(b.student?.name || ''));
    }, [filteredClassRecords]);

    const subjectRecapRows = useMemo(() => {
        const groups = new Map<string, SubjectAttendanceRecord[]>();
        filteredSubjectRecords.forEach(r => {
            const key = `${r.teaching_assignment_id}|${r.student_id}`;
            groups.set(key, [...(groups.get(key) || []), r]);
        });

        return Array.from(groups.values()).map(items => {
            const first = items[0];
            const hadir = items.filter(r => r.status === 'hadir').length;
            const total = items.length;

            return {
                student: first.student,
                subjectName: first.teaching_assignment?.subject?.name || 'Mapel',
                className: first.teaching_assignment?.school_class?.name || 'Kelas',
                teacherName: first.teaching_assignment?.teacher?.name || 'Guru',
                total,
                presentRate: total > 0 ? Math.round((hadir / total) * 100) : 0,
                summary: statusOptions.map(opt => ({
                    ...opt,
                    total: items.filter(r => r.status === opt.value).length
                }))
            };
        }).sort((a, b) => {
            const mapelCompare = a.subjectName.localeCompare(b.subjectName);
            if (mapelCompare !== 0) return mapelCompare;
            return (a.student?.name || '').localeCompare(b.student?.name || '');
        });
    }, [filteredSubjectRecords]);

    // Handle export files
    const handleExport = (format: 'csv' | 'xls') => {
        const params = new URLSearchParams();
        params.set('format', format);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        if (selectedClassId) params.set('school_class_id', selectedClassId);
        if (selectedStudentId) params.set('student_id', selectedStudentId);

        let endpoint = '';
        if (recapType === 'kelas') {
            endpoint = `/api/v1/admin/export/absensi/${format}`;
            params.set('type', 'kelas');
        } else {
            endpoint = `/api/v1/admin/export/absensi/${format}`;
            params.set('type', 'mapel');
            if (selectedSubjectId) params.set('subject_id', selectedSubjectId);
        }

        const url = `${endpoint}?${params.toString()}`;
        window.open(url, '_blank', 'noopener,noreferrer');
    };

    return (
        <div className="min-h-full bg-slate-50 px-2 py-4 sm:p-6">
            <div className="mx-auto w-full max-w-7xl space-y-5">
                
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">Rekap Absensi (Admin)</h1>
                        <p className="mt-1 text-sm text-slate-500 font-medium">Rekapitulasi data kehadiran siswa per pertemuan & perorangan langsung dari database.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                        <button
                            onClick={fetchRecords}
                            disabled={loading}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60 cursor-pointer"
                        >
                            <RefreshCw className={cn("h-4 w-4", loading && "animate-spin")} />
                            Muat Ulang
                        </button>
                        <button
                            onClick={() => handleExport('csv')}
                            disabled={loading}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-transparent bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60 cursor-pointer"
                        >
                            <Download className="h-4 w-4" />
                            CSV
                        </button>
                        <button
                            onClick={() => handleExport('xls')}
                            disabled={loading}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-transparent bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60 cursor-pointer"
                        >
                            <FileSpreadsheet className="h-4 w-4" />
                            Excel (XLS)
                        </button>
                    </div>
                </div>

                {/* Main Tabs (Kelas vs Mapel) */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="flex rounded-lg bg-slate-200/60 p-1 w-fit border border-slate-200 shadow-inner">
                        <button
                            onClick={() => setRecapType('kelas')}
                            className={cn(
                                "rounded-md px-4 py-1.5 text-sm font-bold transition-all cursor-pointer",
                                recapType === 'kelas' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-950'
                            )}
                        >
                            Absensi Kelas
                        </button>
                        <button
                            onClick={() => setRecapType('mapel')}
                            className={cn(
                                "rounded-md px-4 py-1.5 text-sm font-bold transition-all cursor-pointer",
                                recapType === 'mapel' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-950'
                            )}
                        >
                            Absensi Mapel
                        </button>
                    </div>

                    {/* Inner Mode Tabs (Ringkasan vs Pertemuan) */}
                    <div className="flex items-center gap-2 bg-slate-100 p-1 rounded-lg border border-slate-200 w-fit">
                        <button
                            onClick={() => setViewMode('ringkasan')}
                            className={cn(
                                "px-3 py-1 rounded text-xs font-semibold transition-all cursor-pointer",
                                viewMode === 'ringkasan' ? "bg-white text-slate-800 shadow-sm" : "text-slate-500 hover:text-slate-800"
                            )}
                        >
                            Ringkasan Kumulatif
                        </button>
                        <button
                            onClick={() => setViewMode('pertemuan')}
                            className={cn(
                                "px-3 py-1 rounded text-xs font-semibold transition-all cursor-pointer",
                                viewMode === 'pertemuan' ? "bg-white text-slate-800 shadow-sm" : "text-slate-500 hover:text-slate-800"
                            )}
                        >
                            Detail Pertemuan
                        </button>
                    </div>
                </div>

                {/* Filter Panel */}
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <div className="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div className="flex items-center gap-2">
                            <Filter className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-bold text-slate-800">Filter Analisis</h2>
                        </div>
                        <button onClick={resetFilters} className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50 cursor-pointer">
                            Reset Filter
                        </button>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-500">Pilih Kelas</span>
                            <select value={selectedClassId} onChange={e => setSelectedClassId(e.target.value)} className={fieldClass}>
                                <option value="">Semua Kelas</option>
                                {classes.map(c => <option key={c.id} value={c.id}>Kelas {c.name}</option>)}
                            </select>
                        </label>

                        {recapType === 'mapel' && (
                            <label className="flex flex-col gap-1.5">
                                <span className="text-xs font-semibold text-slate-500">Mata Pelajaran</span>
                                <select value={selectedSubjectId} onChange={e => setSelectedSubjectId(e.target.value)} className={fieldClass}>
                                    <option value="">Semua Mapel</option>
                                    {subjects.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            </label>
                        )}

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-500">Siswa (Perorangan)</span>
                            <select value={selectedStudentId} onChange={e => setSelectedStudentId(e.target.value)} className={fieldClass}>
                                <option value="">Semua Siswa</option>
                                {students.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-500">Catatan Guru</span>
                            <select value={notesFilter} onChange={e => setNotesFilter(e.target.value as NotesFilter)} className={fieldClass}>
                                <option value="all">Semua Catatan</option>
                                <option value="has-notes">Ada Catatan</option>
                                <option value="no-notes">Tanpa Catatan</option>
                            </select>
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-500">Dari Tanggal</span>
                            <input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} className={fieldClass} />
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-500">Sampai Tanggal</span>
                            <input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} className={fieldClass} />
                        </label>
                    </div>

                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100">
                        <div className="relative w-full sm:max-w-md">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                placeholder="Cari nama, NIK, status, atau catatan guru..."
                                className={cn(fieldClass, "w-full pl-9")}
                            />
                        </div>

                        <button onClick={fetchRecords} disabled={loading} className="w-full sm:w-auto inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60 cursor-pointer">
                            Terapkan Filter
                        </button>
                    </div>
                </section>

                {/* Table Data View */}
                {loading ? (
                    <div className="flex flex-col items-center justify-center h-64 gap-3 bg-white border border-slate-200 rounded-xl shadow-sm">
                        <RefreshCw className="w-8 h-8 text-blue-500 animate-spin" />
                        <p className="text-sm text-slate-500 font-medium">Memuat data absensi...</p>
                    </div>
                ) : loadError ? (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-red-800 shadow-sm flex items-start gap-3">
                        <AlertCircle className="w-5 h-5 mt-0.5 flex-shrink-0" />
                        <div>
                            <h3 className="font-bold">Gagal memuat rekap absensi</h3>
                            <p className="text-sm mt-1">{loadError}</p>
                        </div>
                    </div>
                ) : (recapType === 'kelas' ? classRecords.length === 0 : subjectRecords.length === 0) ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                        <BarChart3 className="mx-auto h-10 w-10 text-slate-300" />
                        <h2 className="mt-3 font-semibold text-slate-900">Belum ada data absensi terekam</h2>
                        <p className="mt-1 text-sm text-slate-500">Coba ubah filter tanggal atau pilih filter kelas/siswa lainnya.</p>
                    </div>
                ) : (
                    <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            {viewMode === 'ringkasan' ? (
                                /* --- CUMULATIVE SUMMARY TABLE --- */
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-100">
                                        <tr>
                                            <th className="px-5 py-3">Siswa</th>
                                            <th className="px-5 py-3">{recapType === 'kelas' ? 'Kelas' : 'Detail Kelas & Mapel'}</th>
                                            <th className="px-4 py-3 text-center text-emerald-700">Hadir</th>
                                            <th className="px-4 py-3 text-center text-amber-700">Sakit</th>
                                            <th className="px-4 py-3 text-center text-blue-700">Izin</th>
                                            <th className="px-4 py-3 text-center text-rose-700">Alpha</th>
                                            <th className="px-4 py-3 text-center">Total Pertemuan</th>
                                            <th className="px-4 py-3 text-center">% Kehadiran</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {recapType === 'kelas' ? (
                                            classRecapRows.map((row, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50/50">
                                                    <td className="px-5 py-3.5">
                                                        <p className="font-bold text-slate-950">{row.student?.name}</p>
                                                        <p className="text-xs text-slate-400 font-medium">NIK: {row.student?.nik || '-'}</p>
                                                    </td>
                                                    <td className="px-5 py-3.5 text-slate-600 font-medium">{row.className}</td>
                                                    {row.summary.map(s => (
                                                        <td key={s.value} className="px-4 py-3 text-center">
                                                            <span className={cn(
                                                                "inline-block px-2.5 py-0.5 rounded-full text-xs font-bold",
                                                                s.value === 'hadir' ? 'bg-emerald-50 text-emerald-700' :
                                                                s.value === 'sakit' ? (s.total > 0 ? 'bg-amber-100 text-amber-800 font-extrabold ring-1 ring-amber-300' : 'text-slate-400') :
                                                                s.value === 'izin' ? (s.total > 0 ? 'bg-blue-100 text-blue-800 font-extrabold ring-1 ring-blue-300' : 'text-slate-400') :
                                                                (s.total > 0 ? 'bg-rose-100 text-rose-800 font-extrabold ring-1 ring-rose-300' : 'text-slate-400')
                                                            )}>
                                                                {s.total}
                                                            </span>
                                                        </td>
                                                    ))}
                                                    <td className="px-4 py-3 text-center font-bold text-slate-800">{row.total}</td>
                                                    <td className="px-4 py-3 text-center">
                                                        <span className={cn(
                                                            "px-2.5 py-0.5 rounded-full text-xs font-bold",
                                                            row.presentRate >= 90 ? "bg-emerald-50 text-emerald-700" : row.presentRate >= 75 ? "bg-amber-50 text-amber-700" : "bg-rose-50 text-rose-700"
                                                        )}>
                                                            {row.presentRate}%
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            subjectRecapRows.map((row, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50/50">
                                                    <td className="px-5 py-3.5">
                                                        <p className="font-bold text-slate-950">{row.student?.name}</p>
                                                        <p className="text-xs text-slate-400 font-medium">NIK: {row.student?.nik || '-'}</p>
                                                    </td>
                                                    <td className="px-5 py-3.5">
                                                        <p className="font-semibold text-slate-800">{row.subjectName} ({row.className})</p>
                                                        <p className="text-[11px] text-slate-400 font-medium">Guru: {row.teacherName}</p>
                                                    </td>
                                                    {row.summary.map(s => (
                                                        <td key={s.value} className="px-4 py-3 text-center">
                                                            <span className={cn(
                                                                "inline-block px-2.5 py-0.5 rounded-full text-xs font-bold",
                                                                s.value === 'hadir' ? 'bg-emerald-50 text-emerald-700' :
                                                                s.value === 'sakit' ? (s.total > 0 ? 'bg-amber-100 text-amber-800 font-extrabold ring-1 ring-amber-300' : 'text-slate-400') :
                                                                s.value === 'izin' ? (s.total > 0 ? 'bg-blue-100 text-blue-800 font-extrabold ring-1 ring-blue-300' : 'text-slate-400') :
                                                                (s.total > 0 ? 'bg-rose-100 text-rose-800 font-extrabold ring-1 ring-rose-300' : 'text-slate-400')
                                                            )}>
                                                                {s.total}
                                                            </span>
                                                        </td>
                                                    ))}
                                                    <td className="px-4 py-3 text-center font-bold text-slate-800">{row.total}</td>
                                                    <td className="px-4 py-3 text-center">
                                                        <span className={cn(
                                                            "px-2.5 py-0.5 rounded-full text-xs font-bold",
                                                            row.presentRate >= 90 ? "bg-emerald-50 text-emerald-700" : row.presentRate >= 75 ? "bg-amber-50 text-amber-700" : "bg-rose-50 text-rose-700"
                                                        )}>
                                                            {row.presentRate}%
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            ) : (
                                /* --- INDIVIDUAL MEETING-BY-MEETING TABLE --- */
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-100">
                                        <tr>
                                            <th className="px-5 py-3">Tanggal</th>
                                            <th className="px-5 py-3">Siswa</th>
                                            <th className="px-5 py-3">{recapType === 'kelas' ? 'Kelas' : 'Detail Pelajaran'}</th>
                                            <th className="px-5 py-3 text-center">Status</th>
                                            <th className="px-5 py-3">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {recapType === 'kelas' ? (
                                            filteredClassRecords.map((record, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50/50">
                                                    <td className="px-5 py-3 text-slate-700 font-mono text-xs">{record.attendance_date}</td>
                                                    <td className="px-5 py-3">
                                                        <p className="font-bold text-slate-900">{record.student?.name}</p>
                                                        <p className="text-[10px] text-slate-400 font-medium">NIK: {record.student?.nik || '-'}</p>
                                                    </td>
                                                    <td className="px-5 py-3 text-slate-600 font-medium">{record.school_class?.name}</td>
                                                    <td className="px-5 py-3 text-center">
                                                        <span className={cn(
                                                            "px-2.5 py-0.5 rounded-full text-xs font-bold capitalize",
                                                            record.status === 'hadir' ? "bg-emerald-50 text-emerald-700" : record.status === 'sakit' ? "bg-amber-50 text-amber-700" : record.status === 'izin' ? "bg-blue-50 text-blue-700" : "bg-rose-50 text-rose-700"
                                                        )}>
                                                            {record.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-5 py-3 text-xs text-slate-500 font-medium italic">{record.notes || '-'}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            filteredSubjectRecords.map((record, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50/50">
                                                    <td className="px-5 py-3 text-slate-700 font-mono text-xs">{record.attendance_date}</td>
                                                    <td className="px-5 py-3">
                                                        <p className="font-bold text-slate-900">{record.student?.name}</p>
                                                        <p className="text-[10px] text-slate-400 font-medium">NIK: {record.student?.nik || '-'}</p>
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        <p className="font-semibold text-slate-800">{record.teaching_assignment?.subject?.name} ({record.teaching_assignment?.school_class?.name})</p>
                                                        <p className="text-[10px] text-slate-400 font-medium">Guru: {record.teaching_assignment?.teacher?.name}</p>
                                                    </td>
                                                    <td className="px-5 py-3 text-center">
                                                        <span className={cn(
                                                            "px-2.5 py-0.5 rounded-full text-xs font-bold capitalize",
                                                            record.status === 'hadir' ? "bg-emerald-50 text-emerald-700" : record.status === 'sakit' ? "bg-amber-50 text-amber-700" : record.status === 'izin' ? "bg-blue-50 text-blue-700" : "bg-rose-50 text-rose-700"
                                                        )}>
                                                            {record.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-5 py-3 text-xs text-slate-500 font-medium italic">{record.notes || '-'}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};


