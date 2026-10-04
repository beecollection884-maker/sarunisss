import React, { useState, useEffect, useCallback } from 'react';
import {
    GraduationCap, Users, BookOpen, Calendar,
    RefreshCw, ClipboardList, CheckCircle2, AlertCircle, Speaker, CalendarDays
} from 'lucide-react';
import { cn } from '../../components/ui/utils';
import api from '../../services/api';
import { toast } from 'sonner';

function Crd({ children, className, np }: { children: any; className?: string; np?: boolean }) {
    return <div className={cn("bg-white rounded-xl border border-slate-200 shadow-sm", !np && "p-5", className)}>{children}</div>;
}

function StatCard({
    title, value, icon: Icon, bg, iconColor
}: {
    title: string; value: string | number;
    icon: any; bg: string; iconColor: string;
}) {
    return (
        <Crd>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-xs text-slate-500 mb-1 truncate font-medium">{title}</p>
                    <p className="text-2xl font-bold text-slate-900 leading-none tracking-tight">{value}</p>
                </div>
                <div className={cn("w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0", bg)}>
                    <Icon className={cn("w-5 h-5", iconColor)} />
                </div>
            </div>
        </Crd>
    );
}

interface TeacherDashboardData {
    hero: { title: string; subtitle: string; badge: string; asset: string };
    todayLabel: string;
    summary: { label: string; value: number }[];
    scheduleRows: {
        id: number;
        lesson_period: number;
        time: string;
        subject: string;
        class_name: string;
        room: string;
        status: string;
    }[];
    attendanceSummary: {
        hadir: number;
        sakit: number;
        izin: number;
        alpha: number;
        total: number;
        present_rate: number;
    };
    checklist: string[];
    announcements: { id: number; title: string; content: string; created_at: string; author_name?: string }[];
    activeAcademicYear: string;
    activeSemester: string;
}

export const TeacherDashboard = () => {
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [data, setData] = useState<TeacherDashboardData | null>(null);

    const fetchData = useCallback(async (silent = false) => {
        if (!silent) setLoading(true);
        else setRefreshing(true);
        try {
            const res = await api.get('/guru-mapel/dashboard');
            setData(res.data);
        } catch (err) {
            console.error('Teacher dashboard fetch error:', err);
            if (!silent) toast.error('Gagal memuat data dashboard.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    if (loading || !data) {
        return (
            <div className="flex flex-col items-center justify-center h-64 gap-3">
                <RefreshCw className="w-8 h-8 text-blue-500 animate-spin" />
                <p className="text-sm text-slate-500">Memuat dashboard guru...</p>
            </div>
        );
    }

    const { hero, todayLabel, summary, scheduleRows, attendanceSummary, checklist, announcements } = data;

    const attendPie = [
        { name: 'Hadir', value: attendanceSummary.hadir, color: 'bg-emerald-500' },
        { name: 'Sakit', value: attendanceSummary.sakit, color: 'bg-amber-500' },
        { name: 'Izin', value: attendanceSummary.izin, color: 'bg-blue-500' },
        { name: 'Alpha', value: attendanceSummary.alpha, color: 'bg-red-500' },
    ];

    return (
        <div className="p-4 md:p-6 space-y-5 max-w-7xl mx-auto">
            {/* Hero Card */}
            <div className="bg-gradient-to-r from-blue-700 to-indigo-800 text-white rounded-2xl p-6 shadow-md relative overflow-hidden">
                <div className="absolute right-0 top-0 bottom-0 w-1/3 opacity-10 flex items-center justify-center pointer-events-none">
                    <BookOpen className="w-64 h-64" />
                </div>
                <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="space-y-1.5">
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm">
                            {hero.badge || todayLabel}
                        </span>
                        <h1 className="text-2xl font-bold">{hero.title}</h1>
                        <p className="text-blue-100 text-sm max-w-xl">{hero.subtitle}</p>
                    </div>
                    <button
                        onClick={() => fetchData(true)}
                        disabled={refreshing}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl bg-white text-blue-700 hover:bg-blue-50 transition-all disabled:opacity-50 flex-shrink-0 self-start md:self-center"
                    >
                        <RefreshCw className={cn("w-4 h-4", refreshing && "animate-spin")} />
                        Refresh Data
                    </button>
                </div>
            </div>

            {/* Metrics */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {summary.map((item) => {
                    const iconMap: Record<string, any> = {
                        'Kelas Diajar': Users,
                        'Jadwal Aktif': CalendarDays,
                        'Siswa Terkait': GraduationCap,
                    };
                    const bgMap: Record<string, string> = {
                        'Kelas Diajar': 'bg-blue-50',
                        'Jadwal Mengajar': 'bg-emerald-50',
                        'Siswa Terkait': 'bg-violet-50',
                    };
                    const colorMap: Record<string, string> = {
                        'Kelas Diajar': 'text-blue-600',
                        'Jadwal Mengajar': 'text-emerald-600',
                        'Siswa Terkait': 'text-violet-600',
                    };
                    const Icon = iconMap[item.label] || BookOpen;
                    return (
                        <StatCard
                            key={item.label}
                            title={item.label}
                            value={item.value}
                            icon={Icon}
                            bg={bgMap[item.label] || 'bg-slate-50'}
                            iconColor={colorMap[item.label] || 'text-slate-600'}
                        />
                    );
                })}
            </div>

            {/* Main Content Split */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* Schedule Table */}
                <div className="lg:col-span-2 space-y-4">
                    <Crd np className="flex flex-col">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h3 className="text-sm font-bold text-slate-900">Jadwal Mengajar Hari Ini</h3>
                            <span className="text-xs text-slate-500 font-medium">{todayLabel}</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-slate-500 text-xs font-semibold uppercase">
                                    <tr>
                                        <th className="px-5 py-3">Period</th>
                                        <th className="px-5 py-3">Waktu</th>
                                        <th className="px-5 py-3">Mata Pelajaran</th>
                                        <th className="px-5 py-3">Kelas</th>
                                        <th className="px-5 py-3">Ruang</th>
                                        <th className="px-5 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {scheduleRows.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-5 py-8 text-center text-slate-400">
                                                <CalendarDays className="w-8 h-8 mx-auto opacity-30 mb-2" />
                                                Tidak ada jadwal mengajar hari ini.
                                            </td>
                                        </tr>
                                    ) : scheduleRows.map((row) => (
                                        <tr key={row.id} className="hover:bg-slate-50/50 transition-colors">
                                            <td className="px-5 py-3.5 font-medium text-slate-800">Ke-{row.lesson_period}</td>
                                            <td className="px-5 py-3.5 text-slate-600 font-mono text-xs">{row.time}</td>
                                            <td className="px-5 py-3.5 font-semibold text-slate-900">{row.subject}</td>
                                            <td className="px-5 py-3.5"><span className="inline-flex px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs font-semibold">{row.class_name}</span></td>
                                            <td className="px-5 py-3.5 text-slate-500 text-xs font-medium">{row.room}</td>
                                            <td className="px-5 py-3.5">
                                                <span className={cn(
                                                    "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold",
                                                    row.status === 'selesai' ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"
                                                )}>
                                                    <span className={cn("w-1.5 h-1.5 rounded-full", row.status === 'selesai' ? "bg-emerald-500" : "bg-amber-500")} />
                                                    {row.status === 'selesai' ? 'Selesai' : 'Belum Mulai'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Crd>

                    {/* Checklist / Notes */}
                    <Crd className="bg-amber-50/50 border-amber-200">
                        <h4 className="text-xs font-bold text-amber-800 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                            <AlertCircle className="w-4 h-4 text-amber-700" /> Checklist Hari Ini
                        </h4>
                        <ul className="space-y-2">
                            {checklist.map((item, idx) => (
                                <li key={idx} className="flex items-start gap-2 text-sm text-slate-700">
                                    <CheckCircle2 className="w-4 h-4 text-emerald-600 mt-0.5 flex-shrink-0" />
                                    <span>{item}</span>
                                </li>
                            ))}
                        </ul>
                    </Crd>
                </div>

                {/* Sidebar widgets */}
                <div className="space-y-4">
                    {/* Ringkasan Absensi Mapel */}
                    <Crd np>
                        <div className="px-5 py-4 border-b border-slate-100">
                            <h3 className="text-sm font-bold text-slate-900">Statistik Absensi Mapel</h3>
                            <p className="text-xs text-slate-400 mt-0.5">Kumulatif kehadiran semester aktif</p>
                        </div>
                        <div className="p-4 space-y-3.5">
                            {attendanceSummary.total === 0 ? (
                                <div className="text-center py-8 text-slate-400">
                                    <ClipboardList className="w-8 h-8 mx-auto opacity-30 mb-2" />
                                    Belum ada data kehadiran terekam.
                                </div>
                            ) : (
                                <>
                                    {attendPie.map((item) => (
                                        <div key={item.name} className="space-y-1">
                                            <div className="flex justify-between text-xs font-medium text-slate-600">
                                                <span>{item.name}</span>
                                                <span className="font-bold text-slate-800">{item.value} ({Math.round((item.value / (attendanceSummary.total || 1)) * 100)}%)</span>
                                            </div>
                                            <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                                <div className={cn("h-full rounded-full", item.color)} style={{ width: `${(item.value / attendanceSummary.total) * 100}%` }} />
                                            </div>
                                        </div>
                                    ))}
                                    <div className="pt-3 border-t border-slate-100 flex justify-between items-center text-xs">
                                        <span className="text-slate-500 font-medium">Persentase Kehadiran</span>
                                        <span className="font-extrabold text-emerald-600 text-sm">{attendanceSummary.present_rate}%</span>
                                    </div>
                                </>
                            )}
                        </div>
                    </Crd>

                    {/* Announcements */}
                    <Crd np>
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h3 className="text-sm font-bold text-slate-900">Pengumuman Sekolah</h3>
                            <Speaker className="w-4 h-4 text-blue-500" />
                        </div>
                        <div className="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                            {announcements.length === 0 ? (
                                <div className="p-5 text-center text-slate-400 text-xs">
                                    Tidak ada pengumuman terbaru.
                                </div>
                            ) : announcements.map((ann) => (
                                <div key={ann.id} className="p-4 hover:bg-slate-50 transition-colors">
                                    <h4 className="text-xs font-bold text-slate-800 line-clamp-1">{ann.title}</h4>
                                    <p className="text-xs text-slate-500 mt-1 line-clamp-2">{ann.content}</p>
                                    <p className="text-[10px] text-slate-400 mt-2 font-medium">
                                        {ann.author_name ? `${ann.author_name} · ` : ''}{ann.created_at}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </Crd>
                </div>
            </div>
        </div>
    );
};
