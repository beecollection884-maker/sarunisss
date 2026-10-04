import React, { useEffect, useMemo, useState } from 'react';
import {
    AlertCircle,
    ArrowLeft,
    BarChart3,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    Clock,
    DoorOpen,
    RefreshCw,
    Save,
    Search,
    Users,
} from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'alpha';
type ViewMode = 'absen' | 'rekap';

interface Subject {
    id: number;
    name: string;
    code?: string | null;
}

interface SchoolClass {
    id: number;
    name: string;
}

interface Schedule {
    id: number;
    school_class_id: number;
    subject_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    room?: string | null;
    subject?: Subject;
    school_class?: SchoolClass;
}

interface Student {
    id: number;
    nik: string;
    nisn?: string | null;
    name: string;
}

interface Meeting {
    number: number;
    date: string;
    formatted_date: string;
    day_name: string;
}

interface AttendanceRecord {
    student_id: number;
    status: AttendanceStatus;
    notes: string;
}

interface RecapRecord {
    id: number;
    student_id: number;
    attendance_date: string;
    status: AttendanceStatus;
    notes?: string | null;
    student?: Student;
}

const DAY_NAMES = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const statusOptions: Array<{ value: AttendanceStatus; label: string; short: string; className: string }> = [
    { value: 'hadir', label: 'Hadir', short: 'H', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    { value: 'sakit', label: 'Sakit', short: 'S', className: 'border-amber-200 bg-amber-50 text-amber-700' },
    { value: 'izin', label: 'Izin', short: 'I', className: 'border-blue-200 bg-blue-50 text-blue-700' },
    { value: 'alpha', label: 'Alpha', short: 'A', className: 'border-rose-200 bg-rose-50 text-rose-700' },
];

const statusMeta = statusOptions.reduce<Record<AttendanceStatus, (typeof statusOptions)[number]>>((carry, item) => {
    carry[item.value] = item;
    return carry;
}, {} as Record<AttendanceStatus, (typeof statusOptions)[number]>);

const timeLabel = (value?: string | null) => (value ? value.substring(0, 5) : '--:--');

const dateDayOfWeek = (date: string) => {
    const jsDay = new Date(`${date}T00:00:00`).getDay();
    return jsDay === 0 ? 6 : jsDay - 1;
};

const timeToMinutes = (timeStr?: string | null) => {
    if (!timeStr) return 0;
    const parts = timeStr.split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const minutes = parseInt(parts[1], 10) || 0;
    return hours * 60 + minutes;
};

const getScheduleStatus = (schedule: Schedule) => {
    const now = new Date();
    const jsDay = now.getDay();
    const currentDayOfWeek = jsDay === 0 ? 6 : jsDay - 1;

    if (schedule.day_of_week < currentDayOfWeek) {
        return 'sudah berlangsung';
    }
    if (schedule.day_of_week > currentDayOfWeek) {
        return 'belum berlangsung';
    }
    
    // It is today, check times
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const startMinutes = timeToMinutes(schedule.start_time);
    const endMinutes = timeToMinutes(schedule.end_time);

    if (currentMinutes < startMinutes) {
        return 'belum berlangsung';
    } else if (currentMinutes >= startMinutes && currentMinutes <= endMinutes) {
        return 'sedang berlangsung';
    } else {
        return 'sudah berlangsung';
    }
};

const getMeetingStatus = (meetingDate: string, schedule: Schedule) => {
    const todayStr = new Date().toISOString().slice(0, 10);
    
    if (meetingDate < todayStr) {
        return 'sudah berlangsung';
    }
    if (meetingDate > todayStr) {
        return 'belum berlangsung';
    }
    
    // Meeting is today, check schedule time
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const startMinutes = timeToMinutes(schedule.start_time);
    const endMinutes = timeToMinutes(schedule.end_time);
    
    if (currentMinutes < startMinutes) {
        return 'belum berlangsung';
    } else if (currentMinutes >= startMinutes && currentMinutes <= endMinutes) {
        return 'sedang berlangsung';
    } else {
        return 'sudah berlangsung';
    }
};

const pickInitialMeeting = (meetings: Meeting[]) => {
    const today = new Date().toISOString().slice(0, 10);
    return meetings.find((meeting) => meeting.date >= today) || meetings[meetings.length - 1] || null;
};

export const GuruMapelJadwal = () => {
    const [schedules, setSchedules] = useState<Schedule[]>([]);
    const [selectedSchedule, setSelectedSchedule] = useState<Schedule | null>(null);
    const [meetings, setMeetings] = useState<Meeting[]>([]);
    const [selectedMeeting, setSelectedMeeting] = useState<Meeting | null>(null);
    const [students, setStudents] = useState<Student[]>([]);
    const [attendances, setAttendances] = useState<Record<number, AttendanceRecord>>({});
    const [recapRecords, setRecapRecords] = useState<RecapRecord[]>([]);
    const [viewMode, setViewMode] = useState<ViewMode>('absen');
    const [mobileStep, setMobileStep] = useState<'mapel' | 'pertemuan' | 'absen'>('mapel');
    const [query, setQuery] = useState('');
    const [attendanceTestMode, setAttendanceTestMode] = useState(false);

    const [loadingSchedules, setLoadingSchedules] = useState(true);
    const [loadingMeetings, setLoadingMeetings] = useState(false);
    const [loadingStudents, setLoadingStudents] = useState(false);
    const [loadingRecap, setLoadingRecap] = useState(false);
    const [saving, setSaving] = useState(false);

    const meetingStatus = useMemo(() => {
        if (!selectedMeeting || !selectedSchedule) return 'belum berlangsung';
        return getMeetingStatus(selectedMeeting.date, selectedSchedule);
    }, [selectedMeeting, selectedSchedule]);

    const isReadOnly = !attendanceTestMode && meetingStatus === 'sudah berlangsung';
    const isNotStarted = !attendanceTestMode && meetingStatus === 'belum berlangsung';

    useEffect(() => {
        fetchSchedules();
        fetchAttendanceTestMode();
    }, []);

    const fetchAttendanceTestMode = async () => {
        try {
            const response = await api.get('/attendance-test-mode');
            setAttendanceTestMode(Boolean(response.data.enabled));
        } catch {
            setAttendanceTestMode(false);
        }
    };

    const selectedSummary = useMemo(() => {
        const values = Object.values(attendances);
        return statusOptions.map((status) => ({
            ...status,
            total: values.filter((record) => record.status === status.value).length,
        }));
    }, [attendances]);

    const filteredStudents = useMemo(() => {
        const keyword = query.trim().toLowerCase();
        if (!keyword) return students;

        return students.filter((student) =>
            [student.name, student.nik, student.nisn || ''].some((value) => value.toLowerCase().includes(keyword)),
        );
    }, [students, query]);

    const groupedRecap = useMemo(() => {
        const groups = new Map<string, RecapRecord[]>();
        recapRecords.forEach((record) => {
            const date = record.attendance_date;
            groups.set(date, [...(groups.get(date) || []), record]);
        });

        return Array.from(groups.entries()).map(([date, records]) => ({
            date,
            records,
            summary: statusOptions.map((status) => ({
                ...status,
                total: records.filter((record) => record.status === status.value).length,
            })),
        }));
    }, [recapRecords]);

    const fetchSchedules = async () => {
        setLoadingSchedules(true);
        try {
            const response = await api.get('/guru-mapel/jadwal-ajar');
            const data = response.data.data || [];
            
            // Sort data: ongoing first, not yet started next, finished last
            const sortedData = [...data].sort((a, b) => {
                const statusA = getScheduleStatus(a);
                const statusB = getScheduleStatus(b);
                const weight = {
                    'sedang berlangsung': 0,
                    'belum berlangsung': 1,
                    'sudah berlangsung': 2
                };
                if (weight[statusA] !== weight[statusB]) {
                    return weight[statusA] - weight[statusB];
                }
                if (a.day_of_week !== b.day_of_week) {
                    return a.day_of_week - b.day_of_week;
                }
                return timeToMinutes(a.start_time) - timeToMinutes(b.start_time);
            });

            setSchedules(sortedData);

            if (sortedData.length > 0) {
                await selectSchedule(sortedData[0]);
            }
        } catch (error) {
            toast.error('Gagal memuat jadwal mengajar.');
        } finally {
            setLoadingSchedules(false);
        }
    };

    const selectSchedule = async (schedule: Schedule) => {
        setSelectedSchedule(schedule);
        setViewMode('absen');
        setMobileStep('pertemuan');
        setQuery('');
        setMeetings([]);
        setSelectedMeeting(null);
        setStudents([]);
        setAttendances({});
        setRecapRecords([]);

        await fetchMeetingsForSchedule(schedule);
    };

    const fetchMeetingsForSchedule = async (schedule: Schedule) => {
        setLoadingMeetings(true);
        try {
            const response = await api.get(`/kelas/${schedule.school_class_id}/pertemuan`);
            const allMeetings: Meeting[] = response.data.data || [];
            const filtered = allMeetings
                .filter((meeting) => dateDayOfWeek(meeting.date) === schedule.day_of_week)
                .map((meeting, index) => ({ ...meeting, number: index + 1 }));

            // Sort filtered meetings: ongoing first (0), finished next (1), not started last (2)
            const sortedMeetings = [...filtered].sort((a, b) => {
                const statusA = getMeetingStatus(a.date, schedule);
                const statusB = getMeetingStatus(b.date, schedule);
                
                const weight = {
                    'sedang berlangsung': 0,
                    'sudah berlangsung': 1,
                    'belum berlangsung': 2
                };
                
                if (weight[statusA] !== weight[statusB]) {
                    return weight[statusA] - weight[statusB];
                }
                
                return a.date.localeCompare(b.date);
            });

            setMeetings(sortedMeetings);
            
            // Prioritize picking the ongoing meeting if available
            const initialMeeting = sortedMeetings.find(m => getMeetingStatus(m.date, schedule) === 'sedang berlangsung') 
                || pickInitialMeeting(sortedMeetings);
            
            setSelectedMeeting(initialMeeting);

            if (initialMeeting) {
                await fetchStudentsAndAttendance(schedule, initialMeeting);
            }
        } catch (error) {
            toast.error('Gagal memuat daftar pertemuan.');
        } finally {
            setLoadingMeetings(false);
        }
    };

    const fetchStudentsAndAttendance = async (schedule: Schedule, meeting: Meeting) => {
        setLoadingStudents(true);
        try {
            const [studentResponse, attendanceResponse] = await Promise.all([
                api.get('/guru-mapel/siswa', { params: { school_class_id: schedule.school_class_id } }),
                api.get('/guru-mapel/rekap-absensi-mapel', {
                    params: {
                        teaching_assignment_id: schedule.id,
                        attendance_date: meeting.date,
                    },
                }),
            ]);

            const studentsList: Student[] = studentResponse.data.data || [];
            const existingRecords: RecapRecord[] = attendanceResponse.data.data || [];
            const existingMap: Record<number, AttendanceRecord> = {};

            existingRecords.forEach((record) => {
                existingMap[record.student_id] = {
                    student_id: record.student_id,
                    status: record.status,
                    notes: record.notes || '',
                };
            });

            const initialAttendances: Record<number, AttendanceRecord> = {};
            studentsList.forEach((student) => {
                initialAttendances[student.id] = existingMap[student.id] || {
                    student_id: student.id,
                    status: 'hadir',
                    notes: '',
                };
            });

            setStudents(studentsList);
            setAttendances(initialAttendances);
        } catch (error) {
            toast.error('Gagal memuat data absensi.');
        } finally {
            setLoadingStudents(false);
        }
    };

    const fetchRecap = async (schedule = selectedSchedule) => {
        if (!schedule) return;

        setLoadingRecap(true);
        setViewMode('rekap');
        setMobileStep('absen');
        try {
            const response = await api.get('/guru-mapel/rekap-absensi-mapel', {
                params: { teaching_assignment_id: schedule.id },
            });
            setRecapRecords(response.data.data || []);
        } catch (error) {
            toast.error('Gagal memuat rekap absensi mapel.');
        } finally {
            setLoadingRecap(false);
        }
    };

    const selectMeeting = async (meeting: Meeting) => {
        setSelectedMeeting(meeting);
        setMobileStep('absen');
        if (selectedSchedule) {
            await fetchStudentsAndAttendance(selectedSchedule, meeting);
        }
    };

    const changeStatus = (studentId: number, status: AttendanceStatus) => {
        setAttendances((current) => ({
            ...current,
            [studentId]: {
                ...current[studentId],
                student_id: studentId,
                status,
            },
        }));
    };

    const changeNotes = (studentId: number, notes: string) => {
        setAttendances((current) => ({
            ...current,
            [studentId]: {
                ...current[studentId],
                student_id: studentId,
                notes,
            },
        }));
    };

    const handleMarkAll = (status: AttendanceStatus) => {
        setAttendances((current) => {
            const updated = { ...current };
            students.forEach((student) => {
                updated[student.id] = {
                    ...updated[student.id],
                    student_id: student.id,
                    status,
                };
            });
            return updated;
        });
        const label = status === 'hadir' ? 'Hadir' : status === 'izin' ? 'Izin' : 'Sakit';
        toast.success(`Semua siswa ditandai sebagai ${label}`);
    };

    const saveAttendance = async () => {
        if (!selectedSchedule || !selectedMeeting) return;

        setSaving(true);
        try {
            await api.post('/guru-mapel/absensi-mapel', {
                teaching_assignment_id: selectedSchedule.id,
                attendance_date: selectedMeeting.date,
                attendances: Object.values(attendances),
            });
            toast.success('Absensi pertemuan berhasil disimpan.');
            setMobileStep('mapel');
            fetchStudentsAndAttendance(selectedSchedule, selectedMeeting);
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal menyimpan absensi.');
        } finally {
            setSaving(false);
        }
    };

    if (loadingSchedules) {
        return (
            <div className="flex min-h-full items-center gap-2 p-6 text-slate-500">
                <RefreshCw className="h-5 w-5 animate-spin" />
                <span>Memuat jadwal mengajar...</span>
            </div>
        );
    }

    return (
        <div className="min-h-full overflow-x-hidden bg-slate-50 px-2 py-4 sm:p-6">
            <div className="mx-auto w-full max-w-7xl space-y-5 overflow-x-hidden">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">Jadwal Mengajar</h1>
                        <p className="mt-1 text-sm text-slate-500">Pilih jadwal mapel, isi absensi per pertemuan, dan lihat rekap masing-masing mapel.</p>
                    </div>
                    <button onClick={fetchSchedules} className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto">
                        <RefreshCw className="h-4 w-4" />
                        Muat Ulang
                    </button>
                </div>

                {schedules.length === 0 ? (
                    <div className="rounded-lg border border-slate-200 bg-white p-10 text-center shadow-sm">
                        <CalendarDays className="mx-auto h-10 w-10 text-slate-300" />
                        <h2 className="mt-3 font-semibold text-slate-900">Belum ada jadwal mengajar</h2>
                        <p className="mt-1 text-sm text-slate-500">Jadwal akan muncul setelah admin mengatur mata jadwal untuk akun guru ini.</p>
                    </div>
                ) : (
                    <div className="grid min-w-0 gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
                        <aside className={`min-w-0 space-y-3 ${mobileStep !== 'mapel' ? 'hidden xl:block' : ''}`}>
                            <div className="min-w-0 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                                <div className="flex items-center gap-2">
                                    <CalendarDays className="h-4 w-4 text-blue-600" />
                                    <h2 className="text-sm font-semibold text-slate-900">Mata Jadwal</h2>
                                </div>
                                <div className="mt-3 min-w-0 space-y-2">
                                    {schedules.map((schedule) => {
                                        const active = selectedSchedule?.id === schedule.id;
                                        const status = getScheduleStatus(schedule);
                                        let statusBadge = null;
                                        if (status === 'sedang berlangsung') {
                                            statusBadge = (
                                                <span className="mt-1 block rounded border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 text-center shrink-0">
                                                    Berlangsung
                                                </span>
                                            );
                                        } else if (status === 'belum berlangsung') {
                                            statusBadge = (
                                                <span className="mt-1 block rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 text-center shrink-0">
                                                    Belum berlangsung
                                                </span>
                                            );
                                        } else {
                                            statusBadge = (
                                                <span className="mt-1 block rounded border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-700 text-center shrink-0">
                                                    Selesai
                                                </span>
                                            );
                                        }

                                        return (
                                            <button
                                                key={schedule.id}
                                                onClick={() => selectSchedule(schedule)}
                                                className={`block w-full min-w-0 rounded-lg border p-3 text-left transition ${active ? 'border-blue-500 bg-blue-50 shadow-sm' : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-slate-50'}`}
                                            >
                                                <div className="flex min-w-0 items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-semibold text-slate-900">{schedule.subject?.name || 'Mata Pelajaran'}</p>
                                                        <p className="mt-1 text-xs text-slate-500">Kelas {schedule.school_class?.name || '-'}</p>
                                                    </div>
                                                    <div className="flex shrink-0 flex-col items-end">
                                                        <span className="rounded bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">{DAY_NAMES[schedule.day_of_week]}</span>
                                                        {statusBadge}
                                                    </div>
                                                </div>
                                                <div className="mt-3 grid min-w-0 gap-1.5 text-xs text-slate-500 min-[380px]:flex min-[380px]:flex-wrap min-[380px]:gap-2">
                                                    <span className="inline-flex min-w-0 items-center gap-1">
                                                        <Clock className="h-3.5 w-3.5 shrink-0" />
                                                        {timeLabel(schedule.start_time)}-{timeLabel(schedule.end_time)}
                                                    </span>
                                                    <span className="inline-flex min-w-0 items-center gap-1">
                                                        <DoorOpen className="h-3.5 w-3.5 shrink-0" />
                                                        <span className="truncate">{schedule.room || 'Ruang belum diatur'}</span>
                                                    </span>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </aside>

                        <main className={`min-w-0 space-y-4 ${mobileStep === 'mapel' ? 'hidden xl:block' : ''}`}>
                            {selectedSchedule && (
                                <section className={`rounded-lg border border-slate-200 bg-white p-4 shadow-sm ${mobileStep !== 'pertemuan' ? 'hidden xl:block' : ''}`}>
                                    <button 
                                        onClick={() => setMobileStep('mapel')} 
                                        className="xl:hidden mb-4 flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition"
                                    >
                                        <ArrowLeft className="h-4 w-4" /> Kembali ke Mapel
                                    </button>
                                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div className="min-w-0">
                                            <p className="text-xs font-semibold uppercase text-slate-400">{DAY_NAMES[selectedSchedule.day_of_week]}, {timeLabel(selectedSchedule.start_time)}-{timeLabel(selectedSchedule.end_time)}</p>
                                            <h2 className="mt-1 text-xl font-bold text-slate-900">{selectedSchedule.subject?.name || 'Mata Pelajaran'}</h2>
                                            <p className="mt-1 text-sm text-slate-500">Kelas {selectedSchedule.school_class?.name || '-'} - {selectedSchedule.room || 'Ruang belum diatur'}</p>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                                            <button
                                                onClick={() => setViewMode('absen')}
                                                className={`inline-flex h-9 items-center justify-center gap-2 rounded-lg px-3 text-sm font-semibold transition ${viewMode === 'absen' ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}
                                            >
                                                <ClipboardCheck className="h-4 w-4" />
                                                Absen
                                            </button>
                                            <button
                                                onClick={() => fetchRecap(selectedSchedule)}
                                                className={`inline-flex h-9 items-center justify-center gap-2 rounded-lg px-3 text-sm font-semibold transition ${viewMode === 'rekap' ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}
                                            >
                                                <BarChart3 className="h-4 w-4" />
                                                Rekap Mapel
                                            </button>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {viewMode === 'absen' ? (
                                <section className="space-y-4">
                                    <div className={`rounded-lg border border-slate-200 bg-white p-4 shadow-sm ${mobileStep !== 'pertemuan' ? 'hidden xl:block' : ''}`}>
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <CheckCircle2 className="h-4 w-4 text-blue-600" />
                                                <h3 className="text-sm font-semibold text-slate-900">Pertemuan</h3>
                                            </div>
                                            {loadingMeetings && <RefreshCw className="h-4 w-4 animate-spin text-slate-400" />}
                                        </div>

                                        {meetings.length === 0 ? (
                                            <div className="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-700">
                                                <AlertCircle className="h-4 w-4" />
                                                Tidak ada pertemuan untuk hari jadwal ini.
                                            </div>
                                        ) : (
                                            <div className="-mx-3 mt-4 flex max-w-full gap-2 overflow-x-auto px-3 pb-1 sm:mx-0 sm:px-0">
                                                {meetings.map((meeting) => {
                                                    const active = selectedMeeting?.date === meeting.date;
                                                    const mStatus = getMeetingStatus(meeting.date, selectedSchedule!);

                                                    let badge = null;
                                                    if (mStatus === 'sedang berlangsung') {
                                                        badge = <span className="ml-1 text-[9px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 rounded px-1 shrink-0">Berlangsung</span>;
                                                    } else if (mStatus === 'belum berlangsung') {
                                                        badge = <span className="ml-1 text-[9px] font-bold text-slate-400 bg-slate-50 border border-slate-100 rounded px-1 shrink-0">Belum mulai</span>;
                                                    } else {
                                                        badge = <span className="ml-1 text-[9px] font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded px-1 shrink-0">Selesai</span>;
                                                    }

                                                    return (
                                                        <button
                                                            key={meeting.date}
                                                            onClick={() => selectMeeting(meeting)}
                                                            className={`w-[142px] shrink-0 rounded-lg border px-3 py-2 text-left transition sm:w-[170px] ${active ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'}`}
                                                        >
                                                            <div className="flex items-center justify-between gap-1">
                                                                <span className="block text-xs font-semibold">Pertemuan {meeting.number}</span>
                                                                {badge}
                                                            </div>
                                                            <span className="mt-1 block text-xs">{meeting.formatted_date}</span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>

                                    <div className={`space-y-4 ${mobileStep !== 'absen' ? 'hidden xl:block' : ''}`}>
                                        <button 
                                            onClick={() => setMobileStep('pertemuan')} 
                                            className="xl:hidden flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition"
                                        >
                                            <ArrowLeft className="h-4 w-4" /> Kembali ke Pertemuan
                                        </button>

                                        {attendanceTestMode && (
                                        <div className="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-700 font-medium shadow-sm">
                                            <AlertCircle className="h-5 w-5 shrink-0 text-amber-500" />
                                            <span>Mode test aktif. Absensi dapat diisi tanpa mengikuti waktu jadwal.</span>
                                        </div>
                                    )}

                                        {isReadOnly && (
                                        <div className="flex items-center gap-2 rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-700 font-medium shadow-sm">
                                            <AlertCircle className="h-5 w-5 shrink-0 text-blue-600" />
                                            <span>Pertemuan ini sudah selesai. Absensi bersifat arsip dan tidak dapat diubah lagi.</span>
                                        </div>
                                    )}

                                    {isNotStarted && (
                                        <div className="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-700 font-medium shadow-sm">
                                            <AlertCircle className="h-5 w-5 shrink-0 text-amber-500" />
                                            <span>Pertemuan belum berlangsung. Absensi baru bisa diisi setelah waktu jadwal dimulai.</span>
                                        </div>
                                    )}

                                    <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
                                        <div className="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
                                            <div>
                                                <h3 className="text-sm font-semibold text-slate-900">Absensi Siswa</h3>
                                                <p className="mt-1 text-xs text-slate-500">{selectedMeeting ? `${selectedMeeting.formatted_date} - ${students.length} siswa` : 'Pilih pertemuan'}</p>
                                            </div>
                                            <div className="relative w-full lg:w-72">
                                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                                <input
                                                    value={query}
                                                    onChange={(event) => setQuery(event.target.value)}
                                                    placeholder="Cari siswa..."
                                                    className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                                />
                                            </div>
                                        </div>

                                        {!isReadOnly && !isNotStarted && students.length > 0 && (
                                            <div className="flex flex-wrap items-center gap-2 bg-slate-50 p-3 sm:px-4 border-b border-slate-100">
                                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-2">Pilih Cepat:</span>
                                                <button
                                                    type="button"
                                                    onClick={() => handleMarkAll('hadir')}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition"
                                                >
                                                    Masuk Semua
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => handleMarkAll('izin')}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition"
                                                >
                                                    Izin Semua
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => handleMarkAll('sakit')}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-100 transition"
                                                >
                                                    Sakit Semua
                                                </button>
                                            </div>
                                        )}

                                        {loadingStudents ? (
                                            <div className="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
                                                <RefreshCw className="h-4 w-4 animate-spin" />
                                                Memuat daftar siswa...
                                            </div>
                                        ) : students.length === 0 ? (
                                            <div className="p-10 text-center text-sm text-slate-500">Belum ada siswa untuk jadwal ini.</div>
                                        ) : (
                                            <>
                                                <div className="grid grid-cols-2 gap-2 border-b border-slate-100 p-3 sm:grid-cols-4 sm:p-4">
                                                    {selectedSummary.map((item) => (
                                                        <div key={item.value} className={`rounded-lg border px-3 py-2 ${item.className}`}>
                                                            <p className="text-xs font-semibold">{item.label}</p>
                                                            <p className="mt-1 text-xl font-bold">{item.total}</p>
                                                        </div>
                                                    ))}
                                                </div>

                                                <div className="divide-y divide-slate-100">
                                                    {filteredStudents.map((student) => {
                                                        const record = attendances[student.id] || { student_id: student.id, status: 'hadir', notes: '' };

                                                        return (
                                                            <div key={student.id} className="p-4">
                                                                <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                                                                    <div className="min-w-0">
                                                                        <p className="font-semibold text-slate-900">{student.name}</p>
                                                                        <p className="mt-0.5 break-words text-xs text-slate-400">NIK: {student.nik}{student.nisn ? ` - NISN: ${student.nisn}` : ''}</p>
                                                                    </div>
                                                                    <div className="grid min-w-0 grid-cols-4 gap-2 sm:flex sm:flex-wrap">
                                                                        {statusOptions.map((status) => {
                                                                            const active = record.status === status.value;

                                                                            return (
                                                                                <button
                                                                                    key={status.value}
                                                                                    onClick={() => changeStatus(student.id, status.value)}
                                                                                    disabled={isReadOnly || isNotStarted}
                                                                                    className={`h-9 min-w-0 rounded-lg border px-2 text-xs font-bold transition sm:px-3 ${active ? status.className : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'} ${(isReadOnly || isNotStarted) ? 'cursor-not-allowed opacity-60' : ''}`}
                                                                                    title={status.label}
                                                                                >
                                                                                    <span className="sm:hidden">{status.short}</span>
                                                                                    <span className="hidden sm:inline">{status.label}</span>
                                                                                </button>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                </div>
                                                                <input
                                                                    value={record.notes}
                                                                    onChange={(event) => changeNotes(student.id, event.target.value)}
                                                                    disabled={isReadOnly || isNotStarted}
                                                                    placeholder={isReadOnly || isNotStarted ? "Tidak dapat diubah" : "Catatan opsional"}
                                                                    className="mt-3 h-9 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-50 disabled:text-slate-400"
                                                                />
                                                            </div>
                                                        );
                                                    })}
                                                </div>

                                                {!isReadOnly && !isNotStarted && (
                                                    <div className="sticky bottom-0 flex justify-end border-t border-slate-100 bg-slate-50/95 p-4 backdrop-blur">
                                                        <button
                                                            onClick={saveAttendance}
                                                            disabled={saving || !selectedMeeting}
                                                            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
                                                        >
                                                            {saving ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                                                            {saving ? 'Menyimpan...' : 'Simpan Absensi'}
                                                        </button>
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>
                                    </div>
                                </section>
                            ) : (
                                <section className={`rounded-lg border border-slate-200 bg-white shadow-sm ${mobileStep !== 'absen' ? 'hidden xl:block' : ''}`}>
                                    <div className="xl:hidden border-b border-slate-200 p-4 pb-0">
                                        <button 
                                            onClick={() => setMobileStep('pertemuan')} 
                                            className="flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition"
                                        >
                                            <ArrowLeft className="h-4 w-4" /> Kembali ke Pertemuan
                                        </button>
                                    </div>
                                    <div className="flex items-center justify-between border-b border-slate-200 p-4">
                                        <div>
                                            <h3 className="text-sm font-semibold text-slate-900">Rekap Absensi Mapel</h3>
                                            <p className="mt-1 text-xs text-slate-500">{selectedSchedule?.subject?.name || 'Mapel'} - {selectedSchedule?.school_class?.name || 'Kelas'}</p>
                                        </div>
                                        <button onClick={() => fetchRecap()} className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                                            <RefreshCw className="h-3.5 w-3.5" />
                                            Refresh
                                        </button>
                                    </div>

                                    {loadingRecap ? (
                                        <div className="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
                                            <RefreshCw className="h-4 w-4 animate-spin" />
                                            Memuat rekap...
                                        </div>
                                    ) : groupedRecap.length === 0 ? (
                                        <div className="p-10 text-center text-sm text-slate-500">Belum ada absensi tersimpan untuk mapel ini.</div>
                                    ) : (
                                        <div className="divide-y divide-slate-100">
                                            {groupedRecap.map((group) => (
                                                <div key={group.date} className="p-4">
                                                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                                        <div>
                                                            <p className="font-semibold text-slate-900">{group.date}</p>
                                                            <p className="mt-1 text-xs text-slate-500">{group.records.length} catatan absensi</p>
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            {group.summary.map((item) => (
                                                                <span key={item.value} className={`rounded border px-2.5 py-1 text-xs font-semibold ${item.className}`}>
                                                                    {item.label}: {item.total}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                    <div className="mt-3 grid gap-2 md:grid-cols-2">
                                                        {group.records.map((record) => (
                                                            <div key={record.id} className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <p className="truncate text-sm font-semibold text-slate-800">{record.student?.name || `Siswa #${record.student_id}`}</p>
                                                                    <span className={`shrink-0 rounded border px-2 py-0.5 text-[11px] font-bold ${statusMeta[record.status]?.className || 'border-slate-200 bg-white text-slate-600'}`}>
                                                                        {statusMeta[record.status]?.label || record.status}
                                                                    </span>
                                                                </div>
                                                                {record.notes && <p className="mt-1 text-xs text-slate-500">{record.notes}</p>}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </section>
                            )}
                        </main>
                    </div>
                )}
            </div>
        </div>
    );
};
