import React, { useEffect, useMemo, useState } from 'react';
import { AlertCircle, BarChart3, CalendarDays, Filter, RefreshCw, Search } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'alpha';
type PageMode = 'history' | 'recap';

interface SchoolClass {
    id: number;
    name: string;
}

interface Student {
    id: number;
    nik?: string | null;
    nisn?: string | null;
    name: string;
}

interface AttendanceRecord {
    id: number;
    student_id: number;
    school_class_id: number;
    attendance_date: string;
    status: AttendanceStatus;
    notes?: string | null;
    student?: Student;
    school_class?: SchoolClass;
}

const statusOptions: Array<{ value: AttendanceStatus; label: string; className: string }> = [
    { value: 'hadir', label: 'Hadir', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    { value: 'sakit', label: 'Sakit', className: 'border-amber-200 bg-amber-50 text-amber-700' },
    { value: 'izin', label: 'Izin', className: 'border-blue-200 bg-blue-50 text-blue-700' },
    { value: 'alpha', label: 'Alpha', className: 'border-rose-200 bg-rose-50 text-rose-700' },
];

const statusMeta = statusOptions.reduce<Record<AttendanceStatus, (typeof statusOptions)[number]>>((carry, item) => {
    carry[item.value] = item;
    return carry;
}, {} as Record<AttendanceStatus, (typeof statusOptions)[number]>);

const todayString = () => new Date().toISOString().slice(0, 10);

const monthStartString = () => {
    const date = new Date();
    date.setDate(1);
    return date.toISOString().slice(0, 10);
};

const formatDate = (date: string) => {
    if (!date) return '-';
    return new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
};

const classNameForRecord = (record: AttendanceRecord) => record.school_class?.name || `Kelas #${record.school_class_id}`;

export const ClassAttendanceRecap = ({
    mode,
    pageTitle,
    pageDescription,
}: {
    mode: PageMode;
    pageTitle?: string;
    pageDescription?: string;
}) => {
    const [classes, setClasses] = useState<SchoolClass[]>([]);
    const [selectedClassId, setSelectedClassId] = useState('');
    const [attendanceDate, setAttendanceDate] = useState(mode === 'history' ? todayString() : '');
    const [dateFrom, setDateFrom] = useState(mode === 'recap' ? monthStartString() : '');
    const [dateTo, setDateTo] = useState(mode === 'recap' ? todayString() : '');
    const [query, setQuery] = useState('');
    const [records, setRecords] = useState<AttendanceRecord[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');

    const title = pageTitle || (mode === 'history' ? 'Riwayat Absensi Kelas' : 'Rekap Absensi Kelas');
    const description = pageDescription || (mode === 'history'
        ? 'Lihat catatan absensi kelas per tanggal dan per pertemuan.'
        : 'Ringkas kehadiran siswa kelas perwalian berdasarkan periode.');

    const fetchClasses = async () => {
        try {
            const response = await api.get('/walikelas/kelas');
            const data = response.data.data || [];
            setClasses(data);
            if (data.length === 1) {
                setSelectedClassId(String(data[0].id));
            }
        } catch (error: any) {
            const message = error.response?.data?.message || 'Gagal memuat kelas perwalian.';
            setLoadError(message);
            toast.error(message);
        }
    };

    const fetchRecords = async () => {
        setLoading(true);
        setLoadError('');
        try {
            const params: Record<string, string> = {};
            if (selectedClassId) params.school_class_id = selectedClassId;
            if (attendanceDate) params.attendance_date = attendanceDate;
            if (!attendanceDate && dateFrom) params.date_from = dateFrom;
            if (!attendanceDate && dateTo) params.date_to = dateTo;

            const response = await api.get('/walikelas/rekap-absensi-kelas', { params });
            setRecords(response.data.data || []);
        } catch (error: any) {
            const message = error.response?.data?.message || 'Gagal memuat data absensi kelas.';
            setLoadError(message);
            toast.error(message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchClasses();
    }, []);

    useEffect(() => {
        fetchRecords();
    }, []);

    const filteredRecords = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        if (!keyword) return records;

        return records.filter((record) => {
            const student = record.student;
            return [
                student?.name || '',
                student?.nik || '',
                student?.nisn || '',
                classNameForRecord(record),
                record.attendance_date,
                record.status,
            ].some((value) => value.toLowerCase().includes(keyword));
        });
    }, [records, query]);

    const overallSummary = useMemo(() => {
        return statusOptions.map((status) => ({
            ...status,
            total: filteredRecords.filter((record) => record.status === status.value).length,
        }));
    }, [filteredRecords]);

    const historyGroups = useMemo(() => {
        const groups = new Map<string, AttendanceRecord[]>();
        filteredRecords.forEach((record) => {
            const key = `${record.attendance_date}|${record.school_class_id}`;
            groups.set(key, [...(groups.get(key) || []), record]);
        });

        return Array.from(groups.values()).map((items) => {
            const first = items[0];
            return {
                key: `${first.attendance_date}-${first.school_class_id}`,
                date: first.attendance_date,
                className: classNameForRecord(first),
                records: items,
                summary: statusOptions.map((status) => ({
                    ...status,
                    total: items.filter((record) => record.status === status.value).length,
                })),
            };
        });
    }, [filteredRecords]);

    const recapRows = useMemo(() => {
        const groups = new Map<number, AttendanceRecord[]>();
        filteredRecords.forEach((record) => {
            groups.set(record.student_id, [...(groups.get(record.student_id) || []), record]);
        });

        return Array.from(groups.values()).map((items) => {
            const first = items[0];
            const hadir = items.filter((record) => record.status === 'hadir').length;
            const total = items.length;

            return {
                student: first.student,
                className: classNameForRecord(first),
                total,
                presentRate: total > 0 ? Math.round((hadir / total) * 100) : 0,
                summary: statusOptions.map((status) => ({
                    ...status,
                    total: items.filter((record) => record.status === status.value).length,
                })),
                latestDate: items[0]?.attendance_date || '-',
            };
        }).sort((a, b) => (a.student?.name || '').localeCompare(b.student?.name || ''));
    }, [filteredRecords]);

    const resetFilters = () => {
        setSelectedClassId(classes.length === 1 ? String(classes[0].id) : '');
        setAttendanceDate(mode === 'history' ? todayString() : '');
        setDateFrom(mode === 'recap' ? monthStartString() : '');
        setDateTo(mode === 'recap' ? todayString() : '');
        setQuery('');
    };

    if (loadError && records.length === 0 && !loading) {
        return (
            <div className="min-h-full bg-slate-50 p-6">
                <div className="mx-auto max-w-3xl rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-800 shadow-sm">
                    <div className="flex items-start gap-3">
                        <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <h1 className="font-semibold">Data absensi belum tersedia</h1>
                            <p className="mt-1 text-sm">{loadError}</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-full bg-slate-50 px-2 py-4 sm:p-6">
            <div className="mx-auto w-full max-w-7xl space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">{title}</h1>
                        <p className="mt-1 text-sm text-slate-500">{description}</p>
                    </div>
                    <button
                        onClick={fetchRecords}
                        disabled={loading}
                        className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60 sm:w-auto"
                    >
                        <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Muat Ulang
                    </button>
                </div>

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

                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Kelas</span>
                            <select value={selectedClassId} onChange={(event) => setSelectedClassId(event.target.value)} className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                <option value="">Semua kelas</option>
                                {classes.map((item) => (
                                    <option key={item.id} value={item.id}>Kelas {item.name}</option>
                                ))}
                            </select>
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Tanggal Tepat</span>
                            <input value={attendanceDate} onChange={(event) => setAttendanceDate(event.target.value)} type="date" className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Dari Tanggal</span>
                            <input value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} disabled={Boolean(attendanceDate)} type="date" className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-50 disabled:text-slate-400" />
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Sampai Tanggal</span>
                            <input value={dateTo} onChange={(event) => setDateTo(event.target.value)} disabled={Boolean(attendanceDate)} type="date" className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-50 disabled:text-slate-400" />
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Cari</span>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Nama, NIK, kelas..." className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
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

                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {overallSummary.map((item) => (
                        <div key={item.value} className={`rounded-lg border px-4 py-3 ${item.className}`}>
                            <p className="text-xs font-semibold">{item.label}</p>
                            <p className="mt-1 text-2xl font-bold">{item.total}</p>
                        </div>
                    ))}
                </div>

                {loading ? (
                    <div className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white p-10 text-sm text-slate-500 shadow-sm">
                        <RefreshCw className="h-4 w-4 animate-spin" />
                        Memuat data absensi...
                    </div>
                ) : filteredRecords.length === 0 ? (
                    <div className="rounded-lg border border-slate-200 bg-white p-10 text-center shadow-sm">
                        <BarChart3 className="mx-auto h-10 w-10 text-slate-300" />
                        <h2 className="mt-3 font-semibold text-slate-900">Belum ada data absensi</h2>
                        <p className="mt-1 text-sm text-slate-500">Coba ubah filter tanggal atau kelas.</p>
                    </div>
                ) : mode === 'history' ? (
                    <div className="space-y-4">
                        {historyGroups.map((group) => (
                            <section key={group.key} className="rounded-lg border border-slate-200 bg-white shadow-sm">
                                <div className="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <h2 className="text-sm font-semibold text-slate-900">{group.className}</h2>
                                        <p className="mt-1 text-xs text-slate-500">{formatDate(group.date)} - {group.records.length} catatan</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {group.summary.map((item) => (
                                            <span key={item.value} className={`rounded border px-2.5 py-1 text-xs font-semibold ${item.className}`}>
                                                {item.label}: {item.total}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                                <div className="divide-y divide-slate-100">
                                    {group.records.map((record) => (
                                        <div key={record.id} className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p className="font-semibold text-slate-900">{record.student?.name || `Siswa #${record.student_id}`}</p>
                                                <p className="mt-0.5 text-xs text-slate-400">NIK: {record.student?.nik || '-'}</p>
                                                {record.notes && <p className="mt-1 text-xs text-slate-500">{record.notes}</p>}
                                            </div>
                                            <span className={`w-fit rounded border px-2.5 py-1 text-xs font-bold ${statusMeta[record.status]?.className || 'border-slate-200 bg-white text-slate-600'}`}>
                                                {statusMeta[record.status]?.label || record.status}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                ) : (
                    <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Siswa</th>
                                        <th className="px-4 py-3">Kelas</th>
                                        <th className="px-4 py-3 text-center text-emerald-700">Hadir</th>
                                        <th className="px-4 py-3 text-center text-amber-700">Sakit</th>
                                        <th className="px-4 py-3 text-center text-blue-700">Izin</th>
                                        <th className="px-4 py-3 text-center text-rose-700">Alpha</th>
                                        <th className="px-4 py-3 text-center">Total Pertemuan</th>
                                        <th className="px-4 py-3 text-center">% Kehadiran</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recapRows.map((row) => (
                                        <tr key={`${row.student?.id || row.student?.name}-${row.className}`} className="hover:bg-slate-50/60">
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
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
        </div>
    );
};
