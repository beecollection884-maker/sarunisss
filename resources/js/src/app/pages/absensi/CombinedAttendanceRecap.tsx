import React, { useEffect, useMemo, useState, useCallback } from 'react';
import { AlertCircle, BarChart3, CalendarDays, Filter, RefreshCw, Search, Download } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'alpha';
type RecapType = 'kelas' | 'mapel';

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

interface TeachingAssignment {
    id: number;
    school_class_id: number;
    subject_id: number;
    day_of_week?: number | null;
    start_time?: string | null;
    end_time?: string | null;
    room?: string | null;
    subject?: Subject;
    school_class?: SchoolClass;
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

const DAY_NAMES = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

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

const scheduleLabel = (schedule?: TeachingAssignment | null) => {
    if (!schedule) return 'Mapel tidak diketahui';
    const subject = schedule.subject?.name || `Mapel #${schedule.subject_id}`;
    const className = schedule.school_class?.name || `Kelas #${schedule.school_class_id}`;
    return `${subject} - ${className}`;
};

const scheduleMeta = (schedule?: TeachingAssignment | null) => {
    if (!schedule) return '-';
    const day = typeof schedule.day_of_week === 'number' ? DAY_NAMES[schedule.day_of_week] : null;
    const time = schedule.start_time || schedule.end_time ? `${schedule.start_time.substring(0, 5)}-${schedule.end_time.substring(0, 5)}` : null;
    const room = schedule.room ? `Ruang ${schedule.room}` : null;
    return [day, time, room].filter(Boolean).join(' | ') || '-';
};

export const CombinedAttendanceRecap = () => {
    const currentPath = window.location.pathname;
    const isGuruMapelRoute = currentPath.includes('/guru-mapel/');
    const isWalikelasRoute = currentPath.includes('/walikelas/');
    const [recapType, setRecapType] = useState<RecapType>('mapel');
    const [isWalikelas, setIsWalikelas] = useState(false);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [query, setQuery] = useState('');

    // Date Filters
    const [dateFrom, setDateFrom] = useState(monthStartString());
    const [dateTo, setDateTo] = useState(todayString());

    // Dropdown options
    const [classes, setClasses] = useState<SchoolClass[]>([]);
    const [schedules, setSchedules] = useState<TeachingAssignment[]>([]);

    // Selected options
    const [selectedClassId, setSelectedClassId] = useState('');
    const [selectedScheduleId, setSelectedScheduleId] = useState('');

    // Records
    const [classRecords, setClassRecords] = useState<ClassAttendanceRecord[]>([]);
    const [subjectRecords, setSubjectRecords] = useState<SubjectAttendanceRecord[]>([]);

    // Initial setup & check roles
    useEffect(() => {
        const checkRolesAndLoadOptions = async () => {
            setLoading(true);
            try {
                // Fetch homeroom classes to check if they are walikelas
                const classRes = await api.get('/walikelas/kelas');
                const classData = classRes.data.data || [];
                setClasses(classData);
                
                if (classData.length > 0) {
                    setIsWalikelas(true);
                    setSelectedClassId(String(classData[0].id));
                    if (!isGuruMapelRoute) {
                        setRecapType('kelas');
                    }
                }
            } catch (err) {
                // Not a walikelas or failed to load
                setIsWalikelas(false);
                if (!isWalikelasRoute) {
                    setRecapType('mapel');
                }
            }

            try {
                // Fetch subject teaching assignments
                const scheduleRes = await api.get('/guru-mapel/jadwal-ajar');
                const scheduleData = scheduleRes.data.data || [];
                setSchedules(scheduleData);
                if (scheduleData.length === 1) {
                    setSelectedScheduleId(String(scheduleData[0].id));
                }
            } catch (err) {
                console.error('Failed to load teaching schedules:', err);
            }
            setLoading(false);
        };

        checkRolesAndLoadOptions();
    }, [isGuruMapelRoute, isWalikelasRoute]);

    const fetchRecords = useCallback(async () => {
        setLoading(true);
        setLoadError('');
        try {
            const params: Record<string, string> = {
                date_from: dateFrom,
                date_to: dateTo,
            };

            if (recapType === 'kelas') {
                if (selectedClassId) params.school_class_id = selectedClassId;
                const response = await api.get('/walikelas/rekap-absensi-kelas', { params });
                setClassRecords(response.data.data || []);
            } else {
                if (selectedScheduleId) params.teaching_assignment_id = selectedScheduleId;
                const response = await api.get('/guru-mapel/rekap-absensi-mapel', { params });
                setSubjectRecords(response.data.data || []);
            }
        } catch (error: any) {
            const message = error.response?.data?.message || 'Gagal memuat data absensi.';
            setLoadError(message);
            toast.error(message);
        } finally {
            setLoading(false);
        }
    }, [recapType, selectedClassId, selectedScheduleId, dateFrom, dateTo]);

    const handleDownload = (format: 'csv' | 'xls') => {
        const params: Record<string, string> = {
            date_from: dateFrom,
            date_to: dateTo,
        };

        let url = '';
        let filename = '';

        if (recapType === 'kelas') {
            if (selectedClassId) params.school_class_id = selectedClassId;
            url = `/walikelas/absensi/export/${format}`;
            filename = `rekap-absensi-kelas-${dateFrom}-ke-${dateTo}.${format}`;
        } else {
            if (selectedScheduleId) params.teaching_assignment_id = selectedScheduleId;
            url = `/guru-mapel/absensi/export/${format}`;
            filename = `rekap-absensi-mapel-${dateFrom}-ke-${dateTo}.${format}`;
        }

        const link = document.createElement('a');
        link.href = api.getUri({ url, params });
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        link.remove();

        toast.success('Unduhan dimulai.', { id: 'download-toast' });
    };

    // Fetch records when tab or default filters load
    useEffect(() => {
        fetchRecords();
    }, [recapType]);

    // Grouping & Filtering Class Recap
    const filteredClassRecords = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        if (!keyword) return classRecords;

        return classRecords.filter((record) => {
            const student = record.student;
            return [
                student?.name || '',
                student?.nik || '',
                student?.nisn || '',
                record.school_class?.name || '',
                record.status,
            ].some((value) => value.toLowerCase().includes(keyword));
        });
    }, [classRecords, query]);

    const classRecapRows = useMemo(() => {
        const groups = new Map<number, ClassAttendanceRecord[]>();
        filteredClassRecords.forEach((record) => {
            groups.set(record.student_id, [...(groups.get(record.student_id) || []), record]);
        });

        return Array.from(groups.values()).map((items) => {
            const first = items[0];
            const hadir = items.filter((record) => record.status === 'hadir').length;
            const total = items.length;

            return {
                student: first.student,
                className: first.school_class?.name || `Kelas #${first.school_class_id}`,
                total,
                presentRate: total > 0 ? Math.round((hadir / total) * 100) : 0,
                summary: statusOptions.map((status) => ({
                    ...status,
                    total: items.filter((record) => record.status === status.value).length,
                })),
            };
        }).sort((a, b) => (a.student?.name || '').localeCompare(b.student?.name || ''));
    }, [filteredClassRecords]);

    // Grouping & Filtering Subject Recap
    const filteredSubjectRecords = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        if (!keyword) return subjectRecords;

        return subjectRecords.filter((record) => {
            const student = record.student;
            const schedule = record.teaching_assignment;
            return [
                student?.name || '',
                student?.nik || '',
                student?.nisn || '',
                scheduleLabel(schedule),
                scheduleMeta(schedule),
                record.status,
            ].some((value) => value.toLowerCase().includes(keyword));
        });
    }, [subjectRecords, query]);

    const subjectRecapRows = useMemo(() => {
        const groups = new Map<string, SubjectAttendanceRecord[]>();
        filteredSubjectRecords.forEach((record) => {
            const key = `${record.teaching_assignment_id}|${record.student_id}`;
            groups.set(key, [...(groups.get(key) || []), record]);
        });

        return Array.from(groups.values()).map((items) => {
            const first = items[0];
            const hadir = items.filter((record) => record.status === 'hadir').length;
            const total = items.length;

            return {
                student: first.student,
                schedule: first.teaching_assignment,
                total,
                presentRate: total > 0 ? Math.round((hadir / total) * 100) : 0,
                summary: statusOptions.map((status) => ({
                    ...status,
                    total: items.filter((record) => record.status === status.value).length,
                })),
            };
        }).sort((a, b) => {
            const scheduleCompare = scheduleLabel(a.schedule).localeCompare(scheduleLabel(b.schedule));
            if (scheduleCompare !== 0) return scheduleCompare;
            return (a.student?.name || '').localeCompare(b.student?.name || '');
        });
    }, [filteredSubjectRecords]);

    const overallSummary = useMemo(() => {
        const recordsList = recapType === 'kelas' ? filteredClassRecords : filteredSubjectRecords;
        return statusOptions.map((status) => ({
            ...status,
            total: recordsList.filter((record) => record.status === status.value).length,
        }));
    }, [recapType, filteredClassRecords, filteredSubjectRecords]);

    const resetFilters = () => {
        setSelectedClassId(classes.length === 1 ? String(classes[0].id) : '');
        setSelectedScheduleId(schedules.length === 1 ? String(schedules[0].id) : '');
        setDateFrom(monthStartString());
        setDateTo(todayString());
        setQuery('');
    };

    return (
        <div className="min-h-full bg-slate-50 px-2 py-4 sm:p-6">
            <div className="mx-auto w-full max-w-7xl space-y-5">
                
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">Rekap Absensi</h1>
                        <p className="mt-1 text-sm text-slate-500 font-medium">Ringkasan statistik kehadiran siswa untuk evaluasi berkala.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                        <button
                            onClick={fetchRecords}
                            disabled={loading}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60 cursor-pointer"
                        >
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            Muat Ulang
                        </button>
                        
                        <button
                            onClick={() => handleDownload('csv')}
                            disabled={loading}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-transparent bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60 cursor-pointer"
                        >
                            <Download className="h-4 w-4" />
                            Unduh CSV
                        </button>

                        <button
                            onClick={() => handleDownload('xls')}
                            disabled={loading}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-transparent bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60 cursor-pointer"
                        >
                            <Download className="h-4 w-4" />
                            Unduh Excel (XLS)
                        </button>
                    </div>
                </div>

                {/* Tabs Switch (Only show if user is a walikelas, meaning they have class recap option) */}
                {isWalikelas && (
                    <div className="flex rounded-lg bg-slate-200/60 p-1 w-fit border border-slate-200 shadow-inner">
                        <button
                            onClick={() => setRecapType('kelas')}
                            className={`rounded-md px-4 py-1.5 text-sm font-bold transition-all ${
                                recapType === 'kelas'
                                    ? 'bg-white text-blue-700 shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Rekap Absen Kelas
                        </button>
                        <button
                            onClick={() => setRecapType('mapel')}
                            className={`rounded-md px-4 py-1.5 text-sm font-bold transition-all ${
                                recapType === 'mapel'
                                    ? 'bg-white text-blue-700 shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Rekap Absen Mapel
                        </button>
                    </div>
                )}

                {/* Filter Section */}
                <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Filter className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-semibold text-slate-900">Filter</h2>
                        </div>
                        <button onClick={resetFilters} className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                            Reset
                        </button>
                    </div>

                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        {recapType === 'kelas' ? (
                            <label className="flex flex-col gap-1.5">
                                <span className="text-xs font-semibold text-slate-600">Kelas</span>
                                <select value={selectedClassId} onChange={(event) => setSelectedClassId(event.target.value)} className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="">Semua kelas</option>
                                    {classes.map((item) => (
                                        <option key={item.id} value={item.id}>Kelas {item.name}</option>
                                    ))}
                                </select>
                            </label>
                        ) : (
                            <label className="flex flex-col gap-1.5">
                                <span className="text-xs font-semibold text-slate-600">Mata Pelajaran</span>
                                <select value={selectedScheduleId} onChange={(event) => setSelectedScheduleId(event.target.value)} className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                    <option value="">Semua mapel</option>
                                    {schedules.map((schedule) => (
                                        <option key={schedule.id} value={schedule.id}>{scheduleLabel(schedule)}</option>
                                    ))}
                                </select>
                            </label>
                        )}

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Dari Tanggal</span>
                            <input value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} type="date" className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Sampai Tanggal</span>
                            <input value={dateTo} onChange={(event) => setDateTo(event.target.value)} type="date" className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Cari Siswa</span>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Nama, NIP/NISN..." className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                            </div>
                        </label>
                    </div>

                    <div className="mt-4 flex justify-end">
                        <button onClick={fetchRecords} disabled={loading} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60">
                            {loading ? <RefreshCw className="h-4 w-4 animate-spin" /> : <CalendarDays className="h-4 w-4" />}
                            Terapkan Filter
                        </button>
                    </div>
                </section>

                {/* Overall Summary Cards */}
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {overallSummary.map((item) => (
                        <div key={item.value} className={`rounded-lg border px-4 py-3 ${item.className}`}>
                            <p className="text-xs font-semibold">{item.label}</p>
                            <p className="mt-1 text-2xl font-bold">{item.total}</p>
                        </div>
                    ))}
                </div>

                {/* Results Table */}
                {loading ? (
                    <div className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white p-10 text-sm text-slate-500 shadow-sm">
                        <RefreshCw className="h-4 w-4 animate-spin" />
                        Memuat data rekap...
                    </div>
                ) : loadError && (recapType === 'kelas' ? classRecapRows.length === 0 : subjectRecapRows.length === 0) ? (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-800 shadow-sm">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <h1 className="font-semibold font-bold">Gagal memuat rekap</h1>
                                <p className="mt-1 text-sm">{loadError}</p>
                            </div>
                        </div>
                    </div>
                ) : (recapType === 'kelas' ? classRecapRows.length === 0 : subjectRecapRows.length === 0) ? (
                    <div className="rounded-lg border border-slate-200 bg-white p-10 text-center shadow-sm">
                        <BarChart3 className="mx-auto h-10 w-10 text-slate-300" />
                        <h2 className="mt-3 font-semibold text-slate-900">Belum ada data rekap</h2>
                        <p className="mt-1 text-sm text-slate-500">Coba ubah tanggal atau filter di atas.</p>
                    </div>
                ) : (
                    <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Siswa</th>
                                        <th className="px-4 py-3">{recapType === 'kelas' ? 'Kelas' : 'Mapel / Kelas'}</th>
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
                                        classRecapRows.map((row) => (
                                            <tr key={row.student?.id || row.student?.name} className="hover:bg-slate-50/60">
                                                <td className="px-4 py-3">
                                                    <p className="font-semibold text-slate-900">{row.student?.name || '-'}</p>
                                                    <p className="mt-0.5 text-xs text-slate-400">NIK: {row.student?.nik || '-'}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 font-medium">{row.className}</td>
                                                {row.summary.map((item) => (
                                                    <td key={item.value} className="px-4 py-3 text-center">
                                                        <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-bold ${
                                                            item.value === 'hadir' ? 'bg-emerald-50 text-emerald-700' :
                                                            item.value === 'sakit' ? (item.total > 0 ? 'bg-amber-100 text-amber-800 font-extrabold ring-1 ring-amber-300' : 'text-slate-400') :
                                                            item.value === 'izin' ? (item.total > 0 ? 'bg-blue-100 text-blue-800 font-extrabold ring-1 ring-blue-300' : 'text-slate-400') :
                                                            (item.total > 0 ? 'bg-rose-100 text-rose-800 font-extrabold ring-1 ring-rose-300' : 'text-slate-400')
                                                        }`}>
                                                            {item.total}
                                                        </span>
                                                    </td>
                                                ))}
                                                <td className="px-4 py-3 text-center font-bold text-slate-800">{row.total}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-bold ${row.presentRate >= 90 ? 'bg-emerald-50 text-emerald-700' : row.presentRate >= 75 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'}`}>
                                                        {row.presentRate}%
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        subjectRecapRows.map((row) => (
                                            <tr key={`${row.schedule?.id || scheduleLabel(row.schedule)}-${row.student?.id || row.student?.name}`} className="hover:bg-slate-50/60">
                                                <td className="px-4 py-3">
                                                    <p className="font-semibold text-slate-900">{row.student?.name || '-'}</p>
                                                    <p className="mt-0.5 text-xs text-slate-400">NIK: {row.student?.nik || '-'}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    <p className="font-semibold text-slate-800">{scheduleLabel(row.schedule)}</p>
                                                    <p className="mt-0.5 text-xs text-slate-400">{scheduleMeta(row.schedule)}</p>
                                                </td>
                                                {row.summary.map((item) => (
                                                    <td key={item.value} className="px-4 py-3 text-center">
                                                        <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-bold ${
                                                            item.value === 'hadir' ? 'bg-emerald-50 text-emerald-700' :
                                                            item.value === 'sakit' ? (item.total > 0 ? 'bg-amber-100 text-amber-800 font-extrabold ring-1 ring-amber-300' : 'text-slate-400') :
                                                            item.value === 'izin' ? (item.total > 0 ? 'bg-blue-100 text-blue-800 font-extrabold ring-1 ring-blue-300' : 'text-slate-400') :
                                                            (item.total > 0 ? 'bg-rose-100 text-rose-800 font-extrabold ring-1 ring-rose-300' : 'text-slate-400')
                                                        }`}>
                                                            {item.total}
                                                        </span>
                                                    </td>
                                                ))}
                                                <td className="px-4 py-3 text-center font-bold text-slate-800">{row.total}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-bold ${row.presentRate >= 90 ? 'bg-emerald-50 text-emerald-700' : row.presentRate >= 75 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'}`}>
                                                        {row.presentRate}%
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
        </div>
    );
};

