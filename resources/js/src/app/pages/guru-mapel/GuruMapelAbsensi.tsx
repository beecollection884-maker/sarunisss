import React, { useState, useEffect } from 'react';
import { RefreshCw, Save, Calendar, CheckSquare, HelpCircle, Check, AlertCircle } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface Subject {
    id: number;
    name: string;
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
    subject?: Subject;
    school_class?: SchoolClass;
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

type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'alpha';

interface AttendanceRecord {
    student_id: number;
    status: AttendanceStatus;
    notes: string;
}

const statusOptions: Array<{ value: AttendanceStatus; label: string; className: string }> = [
    { value: 'hadir', label: 'Hadir', className: 'text-blue-600 focus:ring-blue-500/20' },
    { value: 'sakit', label: 'Sakit', className: 'text-amber-500 focus:ring-amber-500/20' },
    { value: 'izin', label: 'Izin', className: 'text-emerald-500 focus:ring-emerald-500/20' },
    { value: 'alpha', label: 'Alpha', className: 'text-rose-500 focus:ring-rose-500/20' },
];

const DAY_NAMES = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

export const GuruMapelAbsensi = () => {
    const [schedules, setSchedules] = useState<Schedule[]>([]);
    const [selectedSchedule, setSelectedSchedule] = useState<Schedule | null>(null);
    const [meetings, setMeetings] = useState<Meeting[]>([]);
    const [selectedMeeting, setSelectedMeeting] = useState<Meeting | null>(null);
    const [students, setStudents] = useState<Student[]>([]);
    const [attendances, setAttendances] = useState<Record<number, AttendanceRecord>>({});
    
    const [loadingSchedules, setLoadingSchedules] = useState(true);
    const [loadingMeetings, setLoadingMeetings] = useState(false);
    const [loadingStudents, setLoadingStudents] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        fetchSchedules();
    }, []);

    const fetchSchedules = async () => {
        try {
            const response = await api.get('/guru-mapel/jadwal-ajar');
            const data = response.data.data || [];
            setSchedules(data);
            if (data.length > 0) {
                setSelectedSchedule(data[0]);
                fetchMeetingsForSchedule(data[0]);
            }
        } catch (error) {
            toast.error('Gagal memuat jadwal mengajar.');
        } finally {
            setLoadingSchedules(false);
        }
    };

    const fetchMeetingsForSchedule = async (schedule: Schedule) => {
        setLoadingMeetings(true);
        try {
            const response = await api.get(`/kelas/${schedule.school_class_id}/pertemuan`);
            const allMeetings = response.data.data || [];

            // Filter meetings that match the day of week of this teaching assignment
            // In our system: 0 = Senin, 1 = Selasa ... 6 = Minggu
            const filtered = allMeetings.filter((m: Meeting) => {
                const dateObj = new Date(m.date);
                const jsDay = dateObj.getDay(); // 0 is Sun, 1 is Mon... 6 is Sat
                const ourDay = jsDay === 0 ? 6 : jsDay - 1;
                return ourDay === schedule.day_of_week;
            });

            // Re-number filtered meetings for user-friendly display (Pertemuan 1, 2, 3...)
            const mappedMeetings = filtered.map((m: Meeting, index: number) => ({
                ...m,
                number: index + 1
            }));

            setMeetings(mappedMeetings);
            if (mappedMeetings.length > 0) {
                setSelectedMeeting(mappedMeetings[0]);
                fetchStudentsAndAttendance(schedule, mappedMeetings[0]);
            } else {
                setSelectedMeeting(null);
                setStudents([]);
                setAttendances({});
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
            // Get all students in the class
            const studentResponse = await api.get('/guru-mapel/siswa', {
                params: { school_class_id: schedule.school_class_id }
            });
            const studentsList = studentResponse.data.data || [];
            setStudents(studentsList);

            // Fetch existing subject attendance
            const attendanceResponse = await api.get('/guru-mapel/rekap-absensi-mapel', {
                params: {
                    teaching_assignment_id: schedule.id,
                    attendance_date: meeting.date
                }
            });
            const existingRecords = attendanceResponse.data.data || [];
            const existingMap: Record<number, AttendanceRecord> = {};

            existingRecords.forEach((record: any) => {
                existingMap[record.student_id] = {
                    student_id: record.student_id,
                    status: record.status,
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
            toast.error('Gagal memuat absensi.');
        } finally {
            setLoadingStudents(false);
        }
    };

    const handleScheduleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const scheduleId = parseInt(e.target.value);
        const sched = schedules.find(s => s.id === scheduleId) || null;
        setSelectedSchedule(sched);
        if (sched) {
            fetchMeetingsForSchedule(sched);
        }
    };

    const handleMeetingChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const meetingIndex = parseInt(e.target.value);
        const meeting = meetings[meetingIndex];
        setSelectedMeeting(meeting);
        if (selectedSchedule && meeting) {
            fetchStudentsAndAttendance(selectedSchedule, meeting);
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

    const handleSave = async () => {
        if (!selectedSchedule || !selectedMeeting) return;
        setSaving(true);

        const payload = {
            teaching_assignment_id: selectedSchedule.id,
            attendance_date: selectedMeeting.date,
            attendances: Object.values(attendances)
        };

        try {
            await api.post('/guru-mapel/absensi-mapel', payload);
            toast.success('Absensi mata pelajaran berhasil disimpan.');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal menyimpan absensi.');
        } finally {
            setSaving(false);
        }
    };

    if (loadingSchedules) {
        return (
            <div className="p-6 flex items-center gap-2 text-slate-500">
                <RefreshCw className="w-5 h-5 animate-spin" />
                <span>Memuat jadwal mengajar Anda...</span>
            </div>
        );
    }

    return (
        <div className="p-6 max-w-5xl mx-auto">
            <div className="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Absensi Mata Pelajaran</h1>
                    <p className="text-sm text-slate-500 mt-1">Input kehadiran siswa untuk mata pelajaran yang Anda ampu.</p>
                </div>
            </div>

            {/* Filter Selector */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm p-5 mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-semibold text-slate-700 mb-1.5">Pilih Jadwal Ajar</label>
                    <select
                        value={selectedSchedule?.id || ''}
                        onChange={handleScheduleChange}
                        className="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm bg-white"
                    >
                        {schedules.map(s => (
                            <option key={s.id} value={s.id}>
                                Kelas {s.school_class?.name} - {s.subject?.name} ({DAY_NAMES[s.day_of_week]}, {s.start_time.substring(0, 5)}-{s.end_time.substring(0, 5)})
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-semibold text-slate-700 mb-1.5">Pilih Pertemuan</label>
                    {loadingMeetings ? (
                        <div className="text-sm text-slate-400 py-2.5 flex items-center gap-1.5">
                            <RefreshCw className="w-4 h-4 animate-spin" /> Memuat pertemuan...
                        </div>
                    ) : meetings.length === 0 ? (
                        <div className="text-sm text-amber-600 py-2.5 flex items-center gap-1.5">
                            <AlertCircle className="w-4 h-4" /> Tidak ada pertemuan di hari tersebut.
                        </div>
                    ) : (
                        <select
                            value={meetings.findIndex(m => m.date === selectedMeeting?.date)}
                            onChange={handleMeetingChange}
                            className="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm bg-white"
                        >
                            {meetings.map((m, idx) => (
                                <option key={idx} value={idx}>
                                    Pertemuan {m.number} ({m.formatted_date})
                                </option>
                            ))}
                        </select>
                    )}
                </div>
            </div>

            {/* Attendance Form */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                {loadingStudents ? (
                    <div className="p-12 text-center text-slate-500 flex items-center justify-center gap-2">
                        <RefreshCw className="w-5 h-5 animate-spin" />
                        Memuat daftar siswa...
                    </div>
                ) : students.length === 0 ? (
                    <div className="p-12 text-center text-slate-400">
                        <AlertCircle className="w-12 h-12 text-slate-300 mx-auto mb-2" />
                        Pilih jadwal & pertemuan untuk menampilkan siswa.
                    </div>
                ) : (
                    <div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                        <th className="py-3 px-6">Nama Siswa</th>
                                        {statusOptions.map((status) => (
                                            <th key={status.value} className="py-3 px-4 text-center">{status.label}</th>
                                        ))}
                                        <th className="py-3 px-6">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                    {students.map((student) => {
                                        const record = attendances[student.id] || { student_id: student.id, status: 'hadir' as AttendanceStatus, notes: '' };
                                        return (
                                            <tr key={student.id} className="hover:bg-slate-50/50 transition-colors">
                                                <td className="py-3.5 px-6 font-medium text-slate-950">
                                                    {student.name}
                                                    <span className="block text-xs font-normal text-slate-400 mt-0.5">NIK: {student.nik}</span>
                                                </td>
                                                {statusOptions.map((status) => (
                                                    <td key={status.value} className="py-3.5 px-4 text-center">
                                                        <input
                                                            type="radio"
                                                            name={`status-${student.id}`}
                                                            checked={record.status === status.value}
                                                            onChange={() => handleStatusChange(student.id, status.value)}
                                                            className={`w-4 h-4 border-slate-300 ${status.className}`}
                                                        />
                                                    </td>
                                                ))}
                                                <td className="py-3.5 px-6">
                                                    <input
                                                        type="text"
                                                        value={record.notes}
                                                        onChange={(e) => handleNotesChange(student.id, e.target.value)}
                                                        placeholder="Keterangan sakit/izin/terlambat..."
                                                        className="w-full px-3 py-1 border border-slate-200 rounded-lg text-xs"
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                            <button
                                onClick={handleSave}
                                disabled={saving}
                                className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm transition-colors disabled:opacity-70"
                            >
                                <Save className="w-4 h-4" />
                                {saving ? 'Menyimpan...' : 'Simpan Absensi'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};
