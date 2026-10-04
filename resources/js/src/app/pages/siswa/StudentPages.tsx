import React, { useEffect, useMemo, useState } from 'react';
import { AlertCircle, BarChart3, BookOpen, CalendarDays, FileText, RefreshCw, Search, ChevronDown, ChevronUp, Clock, User, MapPin } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

const cn = (...classes: any[]) => classes.filter(Boolean).join(' ');

type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'alpha';

interface Subject {
    id: number;
    name: string;
    code?: string | null;
}

interface Teacher {
    id: number;
    name: string;
}

interface SchoolClass {
    id: number;
    name: string;
}

interface StudentSummary {
    id: number;
    name: string;
    nik?: string | null;
    nisn?: string | null;
    school_class?: SchoolClass | null;
}

interface TeachingAssignment {
    id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    room?: string | null;
    subject?: Subject;
    teacher?: Teacher;
    school_class?: SchoolClass;
}

interface AttendanceRecord {
    id: number;
    attendance_date: string;
    status: AttendanceStatus;
    notes?: string | null;
    teaching_assignment?: TeachingAssignment;
    school_class?: SchoolClass;
    recorded_by_teacher?: Teacher;
}

interface StudentNote {
    id: number;
    title: string;
    category: string;
    note: string;
    follow_up_at?: string | null;
    resolved_at?: string | null;
    created_at?: string | null;
    teacher?: Teacher | null;
}

const DAY_NAMES = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const statusMeta: Record<AttendanceStatus, { label: string; className: string }> = {
    hadir: { label: 'Hadir', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    sakit: { label: 'Sakit', className: 'border-amber-200 bg-amber-50 text-amber-700' },
    izin: { label: 'Izin', className: 'border-blue-200 bg-blue-50 text-blue-700' },
    alpha: { label: 'Alpha', className: 'border-rose-200 bg-rose-50 text-rose-700' },
};

const timeLabel = (value?: string | null) => (value ? value.substring(0, 5) : '--:--');

const formatDate = (value?: string | null) => {
    if (!value) return '-';

    const rawValue = String(value);
    const date = rawValue.includes('T') ? new Date(rawValue) : new Date(`${rawValue}T00:00:00`);

    if (Number.isNaN(date.getTime())) return '-';

    return date.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
};

const endpointPrefix = (portal: 'siswa' | 'orang-tua') => portal === 'orang-tua' ? '/orang-tua' : '/siswa';

const useParentChildren = (portal: 'siswa' | 'orang-tua') => {
    const [children, setChildren] = useState<StudentSummary[]>([]);
    const [selectedStudentId, setSelectedStudentId] = useState('');

    useEffect(() => {
        if (portal !== 'orang-tua') return;

        const fetchChildren = async () => {
            try {
                const response = await api.get('/orang-tua/anak');
                const data = response.data.data || [];
                setChildren(data);
                if (data.length > 0) {
                    setSelectedStudentId(String(data[0].id));
                }
            } catch (error) {
                toast.error('Gagal memuat data anak.');
            }
        };

        fetchChildren();
    }, [portal]);

    return { children, selectedStudentId, setSelectedStudentId };
};

const StudentPicker = ({
    portal,
    childrenData,
    selectedStudentId,
    setSelectedStudentId,
}: {
    portal: 'siswa' | 'orang-tua';
    childrenData: StudentSummary[];
    selectedStudentId: string;
    setSelectedStudentId: (value: string) => void;
}) => {
    if (portal !== 'orang-tua' || childrenData.length <= 1) {
        return null;
    }

    return (
        <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <label className="flex max-w-md flex-col gap-1.5">
                <span className="text-xs font-semibold text-slate-600">Pilih Anak</span>
                <select
                    value={selectedStudentId}
                    onChange={(event) => setSelectedStudentId(event.target.value)}
                    className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    {childrenData.map((student) => (
                        <option key={student.id} value={student.id}>
                            {student.name} {student.school_class?.name ? `- ${student.school_class.name}` : ''}
                        </option>
                    ))}
                </select>
            </label>
        </section>
    );
};

const SummaryCards = ({ records }: { records: AttendanceRecord[] }) => {
    const summary = (Object.keys(statusMeta) as AttendanceStatus[]).map((status) => ({
        status,
        ...statusMeta[status],
        total: records.filter((record) => record.status === status).length,
    }));

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {summary.map((item) => (
                <div key={item.status} className={`rounded-lg border px-4 py-3 ${item.className}`}>
                    <p className="text-xs font-semibold">{item.label}</p>
                    <p className="mt-1 text-2xl font-bold">{item.total}</p>
                </div>
            ))}
        </div>
    );
};

export const StudentSchedule = ({ portal = 'siswa' }: { portal?: 'siswa' | 'orang-tua' }) => {
    const [schedules, setSchedules] = useState<TeachingAssignment[]>([]);
    const [loading, setLoading] = useState(true);
    // Active Day: JS getDay() (Minggu=0..Sabtu=6) -> index DAY_NAMES (Senin=0..Minggu=6)
    const [selectedDay, setSelectedDay] = useState<string>(DAY_NAMES[(new Date().getDay() + 6) % 7]);
    const { children, selectedStudentId, setSelectedStudentId } = useParentChildren(portal);

    const fetchSchedules = async () => {
        setLoading(true);
        try {
            const response = await api.get(`${endpointPrefix(portal)}/jadwal-sekolah`, {
                params: selectedStudentId ? { student_id: selectedStudentId } : {},
            });
            setSchedules(response.data.data || []);
        } catch (error) {
            console.error('Fetch schedule error:', error);
            setSchedules([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchSchedules();
    }, [portal, selectedStudentId]);

    const currentDaySchedules = useMemo(() => {
        const dayIndex = DAY_NAMES.indexOf(selectedDay);
        return schedules
            .filter((schedule) => schedule.day_of_week === dayIndex)
            .sort((a, b) => timeLabel(a.start_time).localeCompare(timeLabel(b.start_time)));
    }, [schedules, selectedDay]);

    return (
        <div className="min-h-full bg-slate-50 px-2 py-4 sm:p-6">
            <div className="mx-auto max-w-6xl space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">Jadwal Pelajaran</h1>
                        <p className="mt-1 text-sm text-slate-500 font-medium">Lihat jadwal mata pelajaran, guru, jam, dan ruang kelas Anda.</p>
                    </div>
                    <button onClick={fetchSchedules} disabled={loading} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60 transition-all cursor-pointer">
                        <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Muat Ulang
                    </button>
                </div>

                <StudentPicker portal={portal} childrenData={children} selectedStudentId={selectedStudentId} setSelectedStudentId={setSelectedStudentId} />

                {/* Horizontal Tab Days Selector */}
                <div className="flex flex-nowrap overflow-x-auto gap-2.5 pb-2 scrollbar-thin scrollbar-thumb-slate-200">
                    {DAY_NAMES.map((day) => {
                        const count = schedules.filter((s) => DAY_NAMES[s.day_of_week] === day).length;
                        const isActive = selectedDay === day;
                        return (
                            <button
                                key={day}
                                onClick={() => setSelectedDay(day)}
                                className={cn(
                                    "flex-shrink-0 px-4 py-3 rounded-2xl flex flex-col items-center min-w-[90px] sm:min-w-[110px] border transition-all duration-200 cursor-pointer shadow-sm",
                                    isActive
                                        ? "bg-gradient-to-br from-blue-600 to-indigo-700 border-blue-600 text-white font-bold scale-105"
                                        : "bg-white border-slate-200 text-slate-600 hover:bg-slate-50 font-semibold"
                                )}
                            >
                                <span className="text-xs uppercase tracking-wider opacity-85">{day}</span>
                                <span className={cn(
                                    "mt-1 text-[10px] px-2 py-0.5 rounded-full",
                                    isActive ? "bg-white/20 text-white" : "bg-slate-100 text-slate-500 font-bold"
                                )}>
                                    {count} Mapel
                                </span>
                            </button>
                        );
                    })}
                </div>

                {loading ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-white p-12 text-sm text-slate-500 shadow-sm">
                        <RefreshCw className="h-7 w-7 text-blue-500 animate-spin" />
                        <p className="font-semibold text-slate-500">Memuat jadwal pelajaran...</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {currentDaySchedules.length === 0 ? (
                            <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm max-w-xl mx-auto space-y-3.5">
                                <div className="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto">
                                    <CalendarDays className="h-8 w-8 text-slate-400" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-slate-800 text-base">Tidak ada jadwal pelajaran</h3>
                                    <p className="mt-1.5 text-xs text-slate-500 leading-relaxed font-medium">
                                        {selectedDay === 'Sabtu' || selectedDay === 'Minggu'
                                            ? "Selamat berlibur! Waktunya istirahat sejenak dan menikmati akhir pekan dengan santai. ☕✨"
                                            : "Jadwal untuk hari ini kosong atau belum terisi."}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                {currentDaySchedules.map((schedule, index) => {
                                    // Custom border colors for visual grouping
                                    const borderColors = [
                                        'border-l-blue-500',
                                        'border-l-emerald-500',
                                        'border-l-purple-500',
                                        'border-l-amber-500',
                                        'border-l-rose-500',
                                        'border-l-indigo-500',
                                    ];
                                    const colorClass = borderColors[index % borderColors.length];

                                    return (
                                        <div
                                            key={schedule.id}
                                            className={cn(
                                                "bg-white rounded-2xl border border-slate-200 shadow-sm p-5 border-l-4 hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5 flex flex-col justify-between gap-4",
                                                colorClass
                                            )}
                                        >
                                            <div className="space-y-2.5">
                                                {/* Header info */}
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                        <Clock className="w-3.5 h-3.5 text-slate-400" />
                                                        {timeLabel(schedule.start_time)} - {timeLabel(schedule.end_time)}
                                                    </span>
                                                    {schedule.subject?.code && (
                                                        <span className="px-2 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-wider">
                                                            {schedule.subject.code}
                                                        </span>
                                                    )}
                                                </div>

                                                {/* Title */}
                                                <div>
                                                    <h3 className="text-base font-extrabold text-slate-900 leading-tight">
                                                        {schedule.subject?.name || '-'}
                                                    </h3>
                                                </div>
                                            </div>

                                            {/* Details Info footer */}
                                            <div className="pt-3 border-t border-slate-100 grid grid-cols-2 gap-3 text-xs">
                                                <div className="flex items-center gap-2 text-slate-600 font-medium min-w-0">
                                                    <div className="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                                        <User className="w-4 h-4 text-slate-500" />
                                                    </div>
                                                    <span className="truncate" title={schedule.teacher?.name}>
                                                        {schedule.teacher?.name || 'Tidak ada guru'}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2 text-slate-600 font-medium min-w-0">
                                                    <div className="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                                        <MapPin className="w-4 h-4 text-slate-500" />
                                                    </div>
                                                    <span className="truncate" title={schedule.room}>
                                                        Ruang: {schedule.room || '-'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};

export const StudentSubjectAttendance = ({ portal = 'siswa' }: { portal?: 'siswa' | 'orang-tua' }) => {
    const [records, setRecords] = useState<AttendanceRecord[]>([]);
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(true);
    const [expanded, setExpanded] = useState<Record<number, boolean>>({});
    const { children, selectedStudentId, setSelectedStudentId } = useParentChildren(portal);

    const fetchRecords = async () => {
        setLoading(true);
        try {
            const response = await api.get(`${endpointPrefix(portal)}/absensi-mapel`, {
                params: selectedStudentId ? { student_id: selectedStudentId } : {},
            });
            setRecords(response.data.data || []);
        } catch (error) {
            console.error('Fetch subject attendance error:', error);
            setRecords([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRecords();
    }, [portal, selectedStudentId]);

    const filteredRecords = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        if (!keyword) return records;

        return records.filter((record) => [
            record.teaching_assignment?.subject?.name || '',
            record.teaching_assignment?.teacher?.name || '',
            record.attendance_date,
            statusMeta[record.status]?.label || record.status,
        ].some((value) => value.toLowerCase().includes(keyword)));
    }, [records, query]);

    const subjectGroups = useMemo(() => {
        const groups = new Map<number, {
            key: number;
            subjectName: string;
            subjectCode: string;
            teacherName: string;
            hadir: number;
            sakit: number;
            izin: number;
            alpha: number;
            total: number;
            percentage: number;
            records: AttendanceRecord[];
        }>();

        filteredRecords.forEach((record) => {
            const key = record.teaching_assignment?.id || 0;
            const subjectName = record.teaching_assignment?.subject?.name || 'Mata Pelajaran';
            const subjectCode = record.teaching_assignment?.subject?.code || '-';
            const teacherName = record.teaching_assignment?.teacher?.name || '-';

            if (!groups.has(key)) {
                groups.set(key, {
                    key,
                    subjectName,
                    subjectCode,
                    teacherName,
                    hadir: 0,
                    sakit: 0,
                    izin: 0,
                    alpha: 0,
                    total: 0,
                    percentage: 0,
                    records: [],
                });
            }

            const g = groups.get(key)!;
            g.records.push(record);
            g.total += 1;

            if (record.status === 'hadir') g.hadir += 1;
            else if (record.status === 'sakit') g.sakit += 1;
            else if (record.status === 'izin') g.izin += 1;
            else if (record.status === 'alpha') g.alpha += 1;
        });

        return Array.from(groups.values()).map((g) => {
            g.percentage = g.total > 0 ? Math.round((g.hadir / g.total) * 100) : 0;
            g.records.sort((a, b) => b.attendance_date.localeCompare(a.attendance_date));
            return g;
        });
    }, [filteredRecords]);

    const toggleExpand = (key: number) => {
        setExpanded((prev) => ({
            ...prev,
            [key]: !prev[key],
        }));
    };

    return (
        <AttendanceListPage
            title="Absensi Mapel"
            description="Lihat absensi di setiap pertemuan untuk masing-masing mata pelajaran."
            icon={BookOpen}
            loading={loading}
            query={query}
            setQuery={setQuery}
            refresh={fetchRecords}
            records={filteredRecords}
            portal={portal}
            childrenData={children}
            selectedStudentId={selectedStudentId}
            setSelectedStudentId={setSelectedStudentId}
        >
            <div className="space-y-4">
                {subjectGroups.map((group) => {
                    const isExpanded = !!expanded[group.key];
                    const percentColor = group.percentage >= 85
                        ? 'bg-emerald-500'
                        : group.percentage >= 75
                        ? 'bg-amber-500'
                        : 'bg-rose-500';

                    return (
                        <section key={group.key} className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:shadow-md">
                            {/* Subject Header / Summary Card */}
                            <div
                                onClick={() => toggleExpand(group.key)}
                                className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between cursor-pointer select-none bg-white hover:bg-slate-50/50 transition-colors"
                            >
                                <div className="space-y-1 min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <h2 className="font-bold text-slate-800 text-base">{group.subjectName}</h2>
                                        <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200">
                                            {group.subjectCode}
                                        </span>
                                    </div>
                                    <p className="text-xs text-slate-500 font-medium">Guru Pengampu: {group.teacherName}</p>
                                </div>

                                <div className="flex flex-wrap items-center gap-4">
                                    {/* Stats grid */}
                                    <div className="flex items-center gap-1.5 text-center">
                                        <div className="rounded-lg border border-emerald-100 bg-emerald-50/50 px-2.5 py-1 min-w-[45px]">
                                            <p className="text-[9px] font-bold text-emerald-600 uppercase">Hadir</p>
                                            <p className="text-xs font-bold text-emerald-700 mt-0.5">{group.hadir}</p>
                                        </div>
                                        <div className="rounded-lg border border-amber-100 bg-amber-50/50 px-2.5 py-1 min-w-[45px]">
                                            <p className="text-[9px] font-bold text-amber-600 uppercase">Sakit</p>
                                            <p className="text-xs font-bold text-amber-700 mt-0.5">{group.sakit}</p>
                                        </div>
                                        <div className="rounded-lg border border-blue-100 bg-blue-50/50 px-2.5 py-1 min-w-[45px]">
                                            <p className="text-[9px] font-bold text-blue-600 uppercase">Izin</p>
                                            <p className="text-xs font-bold text-blue-700 mt-0.5">{group.izin}</p>
                                        </div>
                                        <div className="rounded-lg border border-rose-100 bg-rose-50/50 px-2.5 py-1 min-w-[45px]">
                                            <p className="text-[9px] font-bold text-rose-600 uppercase">Alpha</p>
                                            <p className="text-xs font-bold text-rose-700 mt-0.5">{group.alpha}</p>
                                        </div>
                                    </div>

                                    {/* Percentage Gauge */}
                                    <div className="flex items-center gap-2.5 pl-2 sm:border-l sm:border-slate-100">
                                        <div className="text-right">
                                            <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Kehadiran</p>
                                            <p className="text-base font-extrabold text-slate-800">{group.percentage}%</p>
                                        </div>
                                        <div className="w-1.5 h-8 bg-slate-100 rounded-full overflow-hidden">
                                            <div className={`w-full rounded-full transition-all duration-300 ${percentColor}`} style={{ height: `${group.percentage}%` }} />
                                        </div>
                                    </div>

                                    {/* Chevron icon */}
                                    <div className="text-slate-400 group-hover:text-slate-600 transition-colors ml-1">
                                        {isExpanded ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                    </div>
                                </div>
                            </div>

                            {/* Meeting list detail dropdown */}
                            {isExpanded && (
                                <div className="border-t border-slate-100 bg-slate-50/30">
                                    <div className="px-5 py-2.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        <span>Tanggal Pertemuan</span>
                                        <span>Status Kehadiran</span>
                                    </div>
                                    <RecordRows records={group.records} />
                                </div>
                            )}
                        </section>
                    );
                })}
            </div>
        </AttendanceListPage>
    );
};

export const StudentClassAttendance = ({ portal = 'siswa' }: { portal?: 'siswa' | 'orang-tua' }) => {
    const [records, setRecords] = useState<AttendanceRecord[]>([]);
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(true);
    const [expanded, setExpanded] = useState<Record<number, boolean>>({});
    const { children, selectedStudentId, setSelectedStudentId } = useParentChildren(portal);

    const fetchRecords = async () => {
        setLoading(true);
        try {
            const response = await api.get(`${endpointPrefix(portal)}/absensi-kelas`, {
                params: selectedStudentId ? { student_id: selectedStudentId } : {},
            });
            setRecords(response.data.data || []);
        } catch (error) {
            console.error('Fetch class attendance error:', error);
            setRecords([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRecords();
    }, [portal, selectedStudentId]);

    const filteredRecords = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        if (!keyword) return records;

        return records.filter((record) => [
            record.school_class?.name || '',
            record.attendance_date,
            statusMeta[record.status]?.label || record.status,
            record.notes || '',
        ].some((value) => value.toLowerCase().includes(keyword)));
    }, [records, query]);

    const classGroups = useMemo(() => {
        const groups = new Map<number, {
            key: number;
            className: string;
            homeroomTeacher: string;
            hadir: number;
            sakit: number;
            izin: number;
            alpha: number;
            total: number;
            percentage: number;
            records: AttendanceRecord[];
        }>();

        filteredRecords.forEach((record) => {
            const key = record.school_class?.id || 0;
            const className = record.school_class?.name || 'Kelas';
            const teacherName = record.recorded_by_teacher?.name || '-';

            if (!groups.has(key)) {
                groups.set(key, {
                    key,
                    className,
                    homeroomTeacher: teacherName,
                    hadir: 0,
                    sakit: 0,
                    izin: 0,
                    alpha: 0,
                    total: 0,
                    percentage: 0,
                    records: [],
                });
            }

            const g = groups.get(key)!;
            g.records.push(record);
            g.total += 1;

            if (record.status === 'hadir') g.hadir += 1;
            else if (record.status === 'sakit') g.sakit += 1;
            else if (record.status === 'izin') g.izin += 1;
            else if (record.status === 'alpha') g.alpha += 1;
        });

        return Array.from(groups.values()).map((g) => {
            g.percentage = g.total > 0 ? Math.round((g.hadir / g.total) * 100) : 0;
            g.records.sort((a, b) => b.attendance_date.localeCompare(a.attendance_date));
            return g;
        });
    }, [filteredRecords]);

    const toggleExpand = (key: number) => {
        setExpanded((prev) => ({
            ...prev,
            [key]: !prev[key],
        }));
    };

    return (
        <AttendanceListPage
            title="Absensi Kelas"
            description="Lihat riwayat absensi harian kelas per pertemuan."
            icon={BarChart3}
            loading={loading}
            query={query}
            setQuery={setQuery}
            refresh={fetchRecords}
            records={filteredRecords}
            portal={portal}
            childrenData={children}
            selectedStudentId={selectedStudentId}
            setSelectedStudentId={setSelectedStudentId}
        >
            <div className="space-y-4">
                {classGroups.map((group) => {
                    const isExpanded = !!expanded[group.key];
                    const percentColor = group.percentage >= 85
                        ? 'bg-emerald-500'
                        : group.percentage >= 75
                        ? 'bg-amber-500'
                        : 'bg-rose-500';

                    return (
                        <section key={group.key} className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:shadow-md">
                            {/* Class Header / Summary Card */}
                            <div
                                onClick={() => toggleExpand(group.key)}
                                className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between cursor-pointer select-none bg-white hover:bg-slate-50/50 transition-colors"
                            >
                                <div className="space-y-1 min-w-0 flex-1">
                                    <h2 className="font-bold text-slate-800 text-base">{group.className}</h2>
                                    {group.homeroomTeacher !== '-' && (
                                        <p className="text-xs text-slate-500 font-medium">Dicatat Oleh / Wali Kelas: {group.homeroomTeacher}</p>
                                    )}
                                </div>

                                <div className="flex flex-wrap items-center gap-4">
                                    {/* Stats grid */}
                                    <div className="flex items-center gap-1.5 text-center">
                                        <div className="rounded-lg border border-emerald-100 bg-emerald-50/50 px-2.5 py-1 min-w-[45px]">
                                            <p className="text-[9px] font-bold text-emerald-600 uppercase">Hadir</p>
                                            <p className="text-xs font-bold text-emerald-700 mt-0.5">{group.hadir}</p>
                                        </div>
                                        <div className="rounded-lg border border-amber-100 bg-amber-50/50 px-2.5 py-1 min-w-[45px]">
                                            <p className="text-[9px] font-bold text-amber-600 uppercase">Sakit</p>
                                            <p className="text-xs font-bold text-amber-700 mt-0.5">{group.sakit}</p>
                                        </div>
                                        <div className="rounded-lg border border-blue-100 bg-blue-50/50 px-2.5 py-1 min-w-[45px]">
                                            <p className="text-[9px] font-bold text-blue-600 uppercase">Izin</p>
                                            <p className="text-xs font-bold text-blue-700 mt-0.5">{group.izin}</p>
                                        </div>
                                        <div className="rounded-lg border border-rose-100 bg-rose-50/50 px-2.5 py-1 min-w-[45px]">
                                            <p className="text-[9px] font-bold text-rose-600 uppercase">Alpha</p>
                                            <p className="text-xs font-bold text-rose-700 mt-0.5">{group.alpha}</p>
                                        </div>
                                    </div>

                                    {/* Percentage Gauge */}
                                    <div className="flex items-center gap-2.5 pl-2 sm:border-l sm:border-slate-100">
                                        <div className="text-right">
                                            <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Kehadiran</p>
                                            <p className="text-base font-extrabold text-slate-800">{group.percentage}%</p>
                                        </div>
                                        <div className="w-1.5 h-8 bg-slate-100 rounded-full overflow-hidden">
                                            <div className={`w-full rounded-full transition-all duration-300 ${percentColor}`} style={{ height: `${group.percentage}%` }} />
                                        </div>
                                    </div>

                                    {/* Chevron icon */}
                                    <div className="text-slate-400 group-hover:text-slate-600 transition-colors ml-1">
                                        {isExpanded ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                    </div>
                                </div>
                            </div>

                            {/* Meeting list detail dropdown */}
                            {isExpanded && (
                                <div className="border-t border-slate-100 bg-slate-50/30">
                                    <div className="px-5 py-2.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        <span>Tanggal Pertemuan</span>
                                        <span>Status Kehadiran</span>
                                    </div>
                                    <RecordRows records={group.records} showClass={false} />
                                </div>
                            )}
                        </section>
                    );
                })}
            </div>
        </AttendanceListPage>
    );
};

const AttendanceListPage = ({
    title,
    description,
    icon: Icon,
    loading,
    query,
    setQuery,
    refresh,
    records,
    portal = 'siswa',
    childrenData = [],
    selectedStudentId = '',
    setSelectedStudentId = () => undefined,
    children,
}: {
    title: string;
    description: string;
    icon: any;
    loading: boolean;
    query: string;
    setQuery: (value: string) => void;
    refresh: () => void;
    records: AttendanceRecord[];
    portal?: 'siswa' | 'orang-tua';
    childrenData?: StudentSummary[];
    selectedStudentId?: string;
    setSelectedStudentId?: (value: string) => void;
    children: React.ReactNode;
}) => (
    <div className="min-h-full bg-slate-50 px-2 py-4 sm:p-6">
        <div className="mx-auto max-w-6xl space-y-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">{title}</h1>
                    <p className="mt-1 text-sm text-slate-500">{description}</p>
                </div>
                <button onClick={refresh} disabled={loading} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60">
                    <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                    Muat Ulang
                </button>
            </div>

            <StudentPicker portal={portal} childrenData={childrenData} selectedStudentId={selectedStudentId} setSelectedStudentId={setSelectedStudentId} />

            <SummaryCards records={records} />

            <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Cari mapel, tanggal, status, atau catatan..."
                        className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    />
                </div>
            </section>

            {loading ? (
                <div className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white p-10 text-sm text-slate-500 shadow-sm">
                    <RefreshCw className="h-4 w-4 animate-spin" />
                    Memuat data absensi...
                </div>
            ) : records.length === 0 ? (
                <div className="rounded-lg border border-slate-200 bg-white p-10 text-center shadow-sm">
                    <Icon className="mx-auto h-10 w-10 text-slate-300" />
                    <h2 className="mt-3 font-semibold text-slate-900">Belum ada data / kelas belum terploting</h2>
                    <p className="mt-1 text-sm text-slate-500 max-w-md mx-auto">Data absensi belum tersedia atau kelas Anda belum di-ploting oleh admin. Silakan hubungi admin sekolah jika memerlukan bantuan.</p>
                </div>
            ) : (
                <div className="space-y-4">{children}</div>
            )}
        </div>
    </div>
);

const RecordRows = ({ records, showClass = false }: { records: AttendanceRecord[]; showClass?: boolean }) => (
    <div className="divide-y divide-slate-100">
        {records.map((record) => {
            const meta = statusMeta[record.status] || { label: record.status, className: 'border-slate-200 bg-white text-slate-600' };

            return (
                <div key={record.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="font-semibold text-slate-900">{formatDate(record.attendance_date)}</p>
                        {showClass && <p className="mt-0.5 text-xs text-slate-500">Kelas: {record.school_class?.name || '-'}</p>}
                        {record.notes && <p className="mt-1 text-xs text-slate-500">{record.notes}</p>}
                    </div>
                    <span className={`w-fit rounded border px-2.5 py-1 text-xs font-bold ${meta.className}`}>
                        {meta.label}
                    </span>
                </div>
            );
        })}
    </div>
);

export const StudentNotes = ({ portal = 'siswa' }: { portal?: 'siswa' | 'orang-tua' }) => {
    const [notes, setNotes] = useState<StudentNote[]>([]);
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(true);
    const { children, selectedStudentId, setSelectedStudentId } = useParentChildren(portal);

    const fetchNotes = async () => {
        setLoading(true);
        try {
            const response = await api.get(`${endpointPrefix(portal)}/catatan`, {
                params: selectedStudentId ? { student_id: selectedStudentId } : {},
            });
            setNotes(response.data.data || []);
        } catch (error) {
            toast.error('Gagal memuat catatan siswa.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchNotes();
    }, [portal, selectedStudentId]);

    const filteredNotes = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        if (!keyword) return notes;

        return notes.filter((note) => [
            note.title,
            note.category,
            note.note,
            note.teacher?.name || '',
        ].some((value) => value.toLowerCase().includes(keyword)));
    }, [notes, query]);

    return (
        <div className="min-h-full bg-slate-50 px-2 py-4 sm:p-6">
            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">Catatan Siswa</h1>
                        <p className="mt-1 text-sm text-slate-500">Lihat catatan dan tindak lanjut yang diberikan guru atau wali kelas.</p>
                    </div>
                    <button onClick={fetchNotes} disabled={loading} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60">
                        <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Muat Ulang
                    </button>
                </div>

                <StudentPicker portal={portal} childrenData={children} selectedStudentId={selectedStudentId} setSelectedStudentId={setSelectedStudentId} />

                <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Cari judul, kategori, guru, atau isi catatan..."
                            className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                    </div>
                </section>

                {loading ? (
                    <div className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white p-10 text-sm text-slate-500 shadow-sm">
                        <RefreshCw className="h-4 w-4 animate-spin" />
                        Memuat catatan...
                    </div>
                ) : filteredNotes.length === 0 ? (
                    <div className="rounded-lg border border-slate-200 bg-white p-10 text-center shadow-sm">
                        <FileText className="mx-auto h-10 w-10 text-slate-300" />
                        <h2 className="mt-3 font-semibold text-slate-900">Belum ada catatan</h2>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {filteredNotes.map((note) => (
                            <section key={note.id} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 className="font-semibold text-slate-900">{note.title}</h2>
                                        <p className="mt-1 text-xs text-slate-500">Kategori: {note.category} - Guru: {note.teacher?.name || '-'}</p>
                                    </div>
                                    <span className={`w-fit rounded border px-2.5 py-1 text-xs font-bold ${note.resolved_at ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'}`}>
                                        {note.resolved_at ? 'Selesai' : 'Terbuka'}
                                    </span>
                                </div>
                                <p className="mt-3 whitespace-pre-wrap text-sm text-slate-700">{note.note}</p>
                                {note.follow_up_at && <p className="mt-3 text-xs font-semibold text-slate-500">Tindak lanjut: {formatDate(note.follow_up_at)}</p>}
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};
