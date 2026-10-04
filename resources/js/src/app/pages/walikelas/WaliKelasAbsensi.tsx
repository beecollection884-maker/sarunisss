import React, { useState, useEffect, useMemo } from 'react';
import { RefreshCw, Save, CheckSquare, AlertCircle, ArrowLeft, Search } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface SchoolClass {
    id: number;
    name: string;
}

interface Student {
    id: number;
    nik: string;
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

type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'alpha';

const statusOptions: Array<{ value: AttendanceStatus; label: string; short: string; className: string }> = [
    { value: 'hadir', label: 'Hadir', short: 'H', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    { value: 'sakit', label: 'Sakit', short: 'S', className: 'border-amber-200 bg-amber-50 text-amber-700' },
    { value: 'izin', label: 'Izin', short: 'I', className: 'border-blue-200 bg-blue-50 text-blue-700' },
    { value: 'alpha', label: 'Alpha', short: 'A', className: 'border-rose-200 bg-rose-50 text-rose-700' },
];

const normalizeStatus = (value: string): AttendanceStatus => {
    const legacyMap: Record<string, AttendanceStatus> = {
        H: 'hadir',
        S: 'sakit',
        I: 'izin',
        A: 'alpha',
    };

    return legacyMap[value] || (statusOptions.some((status) => status.value === value) ? value as AttendanceStatus : 'hadir');
};

const getMeetingStatus = (meetingDate: string) => {
    const todayStr = new Date().toISOString().slice(0, 10);
    if (meetingDate < todayStr) return 'sudah berlangsung';
    if (meetingDate > todayStr) return 'belum berlangsung';
    return 'sedang berlangsung';
};

interface WaliKelasAbsensiProps {
    pageTitle?: string;
    pageDescription?: string;
}

export const WaliKelasAbsensi = ({
    pageTitle = 'Absensi Harian (Wali Kelas)',
    pageDescription = 'Isi dan edit kehadiran harian siswa per pertemuan KBM.',
}: WaliKelasAbsensiProps = {}) => {
    const [classes, setClasses] = useState<SchoolClass[]>([]);
    const [selectedClassId, setSelectedClassId] = useState<number | null>(null);
    const [meetings, setMeetings] = useState<Meeting[]>([]);
    const [selectedMeeting, setSelectedMeeting] = useState<Meeting | null>(null);
    const [students, setStudents] = useState<Student[]>([]);
    const [attendances, setAttendances] = useState<Record<number, AttendanceRecord>>({});
    const [mobileStep, setMobileStep] = useState<'pertemuan' | 'absen'>('pertemuan');
    const [query, setQuery] = useState('');
    const [attendanceTestMode, setAttendanceTestMode] = useState(false);
    
    const [loadingClasses, setLoadingClasses] = useState(true);
    const [loadingMeetings, setLoadingMeetings] = useState(false);
    const [loadingStudents, setLoadingStudents] = useState(false);
    const [saving, setSaving] = useState(false);
    const [loadError, setLoadError] = useState('');

    const meetingStatus = useMemo(() => {
        if (!selectedMeeting) return 'belum berlangsung';
        return getMeetingStatus(selectedMeeting.date);
    }, [selectedMeeting]);

    const isReadOnly = !attendanceTestMode && meetingStatus === 'sudah berlangsung';
    const isNotStarted = !attendanceTestMode && meetingStatus === 'belum berlangsung';

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
            [student.name, student.nik].some((value) => value.toLowerCase().includes(keyword)),
        );
    }, [students, query]);

    useEffect(() => {
        fetchClasses();
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

    const fetchClasses = async () => {
        setLoadError('');
        try {
            const response = await api.get('/walikelas/kelas');
            const data = response.data.data || [];
            setClasses(data);
            if (data.length > 0) {
                setSelectedClassId(data[0].id);
                fetchMeetings(data[0].id);
            }
        } catch (error: any) {
            const message = error.response?.data?.message || 'Gagal mengambil data kelas perwalian.';
            setLoadError(message);
            toast.error(message);
        } finally {
            setLoadingClasses(false);
        }
    };

    const fetchMeetings = async (classId: number) => {
        setLoadingMeetings(true);
        try {
            const response = await api.get(`/kelas/${classId}/pertemuan`);
            const data: Meeting[] = response.data.data || [];

            // Sort: sedang berlangsung first, sudah berlangsung next, belum berlangsung last
            const sorted = [...data].sort((a, b) => {
                const weight: Record<string, number> = {
                    'sedang berlangsung': 0,
                    'sudah berlangsung': 1,
                    'belum berlangsung': 2,
                };
                const wA = weight[getMeetingStatus(a.date)] ?? 2;
                const wB = weight[getMeetingStatus(b.date)] ?? 2;
                if (wA !== wB) return wA - wB;
                return a.date.localeCompare(b.date);
            });

            setMeetings(sorted);

            // Auto-select ongoing meeting if available, otherwise first upcoming
            const initial = sorted.find(m => getMeetingStatus(m.date) === 'sedang berlangsung')
                || sorted.find(m => getMeetingStatus(m.date) === 'belum berlangsung')
                || sorted[0] || null;

            setSelectedMeeting(initial);
            if (initial) {
                fetchStudentsAndAttendance(classId, initial);
            }
        } catch (error) {
            toast.error('Gagal memuat jadwal pertemuan.');
        } finally {
            setLoadingMeetings(false);
        }
    };

    const fetchStudentsAndAttendance = async (classId: number, meeting: Meeting) => {
        setLoadingStudents(true);
        try {
            const studentResponse = await api.get('/walikelas/siswa', {
                params: { school_class_id: classId }
            });
            const studentsList = studentResponse.data.data || [];
            setStudents(studentsList);

            const attendanceResponse = await api.get('/walikelas/rekap-absensi-kelas', {
                params: {
                    school_class_id: classId,
                    attendance_date: meeting.date
                }
            });
            const existingRecords = attendanceResponse.data.data || [];
            const existingMap: Record<number, AttendanceRecord> = {};

            existingRecords.forEach((record: any) => {
                existingMap[record.student_id] = {
                    student_id: record.student_id,
                    status: normalizeStatus(record.status),
                    notes: record.notes || ''
                };
            });

            const initialAttendances: Record<number, AttendanceRecord> = {};
            studentsList.forEach((s: Student) => {
                initialAttendances[s.id] = existingMap[s.id] || {
                    student_id: s.id,
                    status: 'hadir',
                    notes: ''
                };
            });

            setAttendances(initialAttendances);
        } catch (error) {
            toast.error('Gagal mengambil data absensi.');
        } finally {
            setLoadingStudents(false);
        }
    };

    const handleClassChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const classId = parseInt(e.target.value);
        setSelectedClassId(classId);
        setMobileStep('pertemuan');
        fetchMeetings(classId);
    };

    const selectMeeting = (meeting: Meeting) => {
        setSelectedMeeting(meeting);
        setMobileStep('absen');
        if (selectedClassId) {
            fetchStudentsAndAttendance(selectedClassId, meeting);
        }
    };

    const handleStatusChange = (studentId: number, status: AttendanceStatus) => {
        setAttendances(prev => ({
            ...prev,
            [studentId]: {
                ...prev[studentId],
                status
            }
        }));
    };

    const handleNotesChange = (studentId: number, notes: string) => {
        setAttendances(prev => ({
            ...prev,
            [studentId]: {
                ...prev[studentId],
                notes
            }
        }));
    };

    const handleMarkAll = (status: AttendanceStatus) => {
        setAttendances(prev => {
            const updated = { ...prev };
            students.forEach((student) => {
                updated[student.id] = {
                    ...updated[student.id],
                    student_id: student.id,
                    status,
                };
            });
            return updated;
        });
        const label = statusOptions.find(s => s.value === status)?.label || status;
        toast.success(`Semua siswa ditandai sebagai ${label}`);
    };

    const handleSave = async () => {
        if (!selectedClassId || !selectedMeeting) return;
        setSaving(true);

        const payload = {
            school_class_id: selectedClassId,
            attendance_date: selectedMeeting.date,
            attendances: Object.values(attendances)
        };

        try {
            await api.post('/walikelas/absensi-kelas', payload);
            toast.success('Absensi harian kelas berhasil disimpan.');
            setMobileStep('pertemuan');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal menyimpan absensi.');
        } finally {
            setSaving(false);
        }
    };

    if (loadingClasses) {
        return (
            <div className="p-6 flex items-center gap-2 text-slate-500">
                <RefreshCw className="w-5 h-5 animate-spin" />
                <span>Memuat data kelas perwalian...</span>
            </div>
        );
    }

    if (loadError) {
        return (
            <div className="min-h-full bg-slate-50 p-6">
                <div className="mx-auto max-w-3xl rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-800 shadow-sm">
                    <div className="flex items-start gap-3">
                        <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <h1 className="font-semibold">Absensi kelas belum tersedia</h1>
                            <p className="mt-1 text-sm">{loadError}</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-full overflow-x-hidden bg-slate-50 px-2 py-4 sm:p-6">
            <div className="mx-auto w-full max-w-5xl space-y-5 overflow-x-hidden">
                {/* Header */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">{pageTitle}</h1>
                        <p className="mt-1 text-sm text-slate-500">{pageDescription}</p>
                    </div>
                    {classes.length > 1 && (
                        <div className="w-full sm:w-auto">
                            <select
                                value={selectedClassId || ''}
                                onChange={handleClassChange}
                                className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm sm:w-52"
                            >
                                {classes.map(c => <option key={c.id} value={c.id}>Kelas {c.name}</option>)}
                            </select>
                        </div>
                    )}
                </div>

                {/* Kelas info (shown when single class) */}
                {classes.length === 1 && (
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-xs font-semibold uppercase text-slate-400">Kelas Perwalian</p>
                        <h2 className="mt-1 text-xl font-bold text-slate-900">Kelas {classes[0]?.name}</h2>
                    </div>
                )}

                {/* ===== STEP 1: Pertemuan List ===== */}
                <div className={`${mobileStep !== 'pertemuan' ? 'hidden xl:block' : ''}`}>
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <CheckSquare className="h-4 w-4 text-blue-600" />
                                <h3 className="text-sm font-semibold text-slate-900">Pertemuan</h3>
                            </div>
                            {loadingMeetings && <RefreshCw className="h-4 w-4 animate-spin text-slate-400" />}
                        </div>

                        {meetings.length === 0 ? (
                            <div className="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-700">
                                <AlertCircle className="h-4 w-4" />
                                Tidak ada pertemuan tersedia.
                            </div>
                        ) : (
                            <div className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:flex xl:max-w-full xl:gap-2 xl:overflow-x-auto xl:pb-1">
                                {meetings.map((meeting) => {
                                    const active = selectedMeeting?.date === meeting.date;
                                    const mStatus = getMeetingStatus(meeting.date);

                                    let badge = null;
                                    if (mStatus === 'sedang berlangsung') {
                                        badge = <span className="text-[9px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 rounded px-1 shrink-0">Berlangsung</span>;
                                    } else if (mStatus === 'belum berlangsung') {
                                        badge = <span className="text-[9px] font-bold text-slate-400 bg-slate-50 border border-slate-100 rounded px-1 shrink-0">Belum mulai</span>;
                                    } else {
                                        badge = <span className="text-[9px] font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded px-1 shrink-0">Selesai</span>;
                                    }

                                    return (
                                        <button
                                            key={meeting.date}
                                            onClick={() => selectMeeting(meeting)}
                                            className={`w-full rounded-lg border px-3 py-2.5 text-left transition xl:w-[170px] xl:shrink-0 ${active ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'}`}
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
                </div>

                {/* ===== STEP 2: Absensi Form ===== */}
                <div className={`space-y-4 ${mobileStep !== 'absen' ? 'hidden xl:block' : ''}`}>
                    {/* Mobile back button */}
                    <button 
                        onClick={() => setMobileStep('pertemuan')} 
                        className="xl:hidden flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition"
                    >
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Pertemuan
                    </button>

                    {/* Status banners */}
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
                        {/* Absensi header + search */}
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

                        {/* Quick actions */}
                        {!isReadOnly && !isNotStarted && students.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 bg-slate-50 p-3 sm:px-4 border-b border-slate-100">
                                <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-2">Pilih Cepat:</span>
                                <button type="button" onClick={() => handleMarkAll('hadir')} className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition">
                                    Masuk Semua
                                </button>
                                <button type="button" onClick={() => handleMarkAll('izin')} className="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition">
                                    Izin Semua
                                </button>
                                <button type="button" onClick={() => handleMarkAll('sakit')} className="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-100 transition">
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
                            <div className="p-10 text-center text-sm text-slate-500">Belum ada siswa terdaftar di kelas ini.</div>
                        ) : (
                            <>
                                {/* Summary cards */}
                                <div className="grid grid-cols-2 gap-2 border-b border-slate-100 p-3 sm:grid-cols-4 sm:p-4">
                                    {selectedSummary.map((item) => (
                                        <div key={item.value} className={`rounded-lg border px-3 py-2 ${item.className}`}>
                                            <p className="text-xs font-semibold">{item.label}</p>
                                            <p className="mt-1 text-xl font-bold">{item.total}</p>
                                        </div>
                                    ))}
                                </div>

                                {/* Student list */}
                                <div className="divide-y divide-slate-100">
                                    {filteredStudents.map((student) => {
                                        const record = attendances[student.id] || { student_id: student.id, status: 'hadir' as AttendanceStatus, notes: '' };

                                        return (
                                            <div key={student.id} className="p-4">
                                                <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                                                    <div className="min-w-0">
                                                        <p className="font-semibold text-slate-900">{student.name}</p>
                                                        <p className="mt-0.5 break-words text-xs text-slate-400">NIK: {student.nik}</p>
                                                    </div>
                                                    <div className="grid min-w-0 grid-cols-4 gap-2 sm:flex sm:flex-wrap">
                                                        {statusOptions.map((status) => {
                                                            const active = record.status === status.value;

                                                            return (
                                                                <button
                                                                    key={status.value}
                                                                    onClick={() => handleStatusChange(student.id, status.value)}
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
                                                    onChange={(event) => handleNotesChange(student.id, event.target.value)}
                                                    disabled={isReadOnly || isNotStarted}
                                                    placeholder={isReadOnly || isNotStarted ? "Tidak dapat diubah" : "Catatan opsional"}
                                                    className="mt-3 h-9 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-50 disabled:text-slate-400"
                                                />
                                            </div>
                                        );
                                    })}
                                </div>

                                {/* Save button */}
                                {!isReadOnly && !isNotStarted && (
                                    <div className="sticky bottom-0 flex justify-end border-t border-slate-100 bg-slate-50/95 p-4 backdrop-blur">
                                        <button
                                            onClick={handleSave}
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
            </div>
        </div>
    );
};
