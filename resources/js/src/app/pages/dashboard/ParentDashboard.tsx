import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
    GraduationCap, Users, BookOpen, Calendar,
    RefreshCw, ClipboardList, CheckCircle2, AlertCircle, Speaker, CalendarDays, BarChart2,
    FileText, Activity
} from 'lucide-react';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer
} from 'recharts';
import { cn } from '../../components/ui/utils';
import api from '../../services/api';
import { toast } from 'sonner';

function Crd({ children, className, np }: { children: any; className?: string; np?: boolean }) {
    return <div className={cn("bg-white rounded-xl border border-slate-200 shadow-sm", !np && "p-5", className)}>{children}</div>;
}

function StatCard({
    title, value, sub, icon: Icon, bg, iconColor
}: {
    title: string; value: string | number; sub?: string;
    icon: any; bg: string; iconColor: string;
}) {
    return (
        <Crd>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-xs text-slate-500 mb-1 truncate font-medium">{title}</p>
                    <p className="text-2xl font-bold text-slate-900 leading-none tracking-tight">{value}</p>
                    {sub && <p className="text-[11px] text-slate-400 mt-1">{sub}</p>}
                </div>
                <div className={cn("w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0", bg)}>
                    <Icon className={cn("w-5 h-5", iconColor)} />
                </div>
            </div>
        </Crd>
    );
}

interface ChildData {
    id: number;
    name: string;
    class_name: string;
    homeroom_teacher: string;
    attendance: {
        hadir: number;
        sakit: number;
        izin: number;
        alpha: number;
    };
    schedules: {
        time: string;
        subject: string;
        teacher: string;
        room: string;
    }[];
    subjectStats: {
        subject_name: string;
        hadir: number;
        sakit: number;
        izin: number;
        alpha: number;
    }[];
    notes: {
        title: string;
        note: string;
    }[];
    violations: {
        type: string;
        points: number;
        description: string;
    }[];
}

interface ParentDashboardData {
    hero: { title: string; subtitle: string; badge: string; asset: string };
    children: ChildData[];
    checklist: string[];
}

export const ParentDashboard = () => {
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [data, setData] = useState<ParentDashboardData | null>(null);
    const [selectedChildId, setSelectedChildId] = useState<number | null>(null);

    const fetchData = useCallback(async (silent = false) => {
        if (!silent) setLoading(true);
        else setRefreshing(true);
        try {
            const res = await api.get('/orang-tua/dashboard');
            setData(res.data);
            
            const kids = res.data.children || [];
            if (kids.length > 0 && selectedChildId === null) {
                setSelectedChildId(kids[0].id);
            }
        } catch (err) {
            console.error('Parent dashboard fetch error:', err);
            if (!silent) toast.error('Gagal memuat data dashboard.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [selectedChildId]);

    useEffect(() => {
        fetchData();
    }, []);

    const selectedChild = useMemo(() => {
        if (!data?.children) return null;
        return data.children.find(kid => kid.id === selectedChildId) || data.children[0] || null;
    }, [data, selectedChildId]);

    const attendanceRate = useMemo(() => {
        if (!selectedChild?.attendance) return 0;
        const { hadir, sakit, izin, alpha } = selectedChild.attendance;
        const total = hadir + sakit + izin + alpha;
        return total > 0 ? Math.round((hadir / total) * 100) : 100;
    }, [selectedChild]);

    if (loading || !data) {
        return (
            <div className="flex flex-col items-center justify-center h-64 gap-3">
                <RefreshCw className="w-8 h-8 text-blue-500 animate-spin" />
                <p className="text-sm text-slate-500">Memuat dashboard orang tua...</p>
            </div>
        );
    }

    const { hero, children: kids = [], checklist } = data;

    return (
        <div className="p-4 md:p-6 space-y-5 max-w-7xl mx-auto">
            {/* Hero Card */}
            <div className="bg-gradient-to-r from-rose-600 to-pink-700 text-white rounded-2xl p-6 shadow-md relative overflow-hidden">
                <div className="absolute right-0 top-0 bottom-0 w-1/3 opacity-10 flex items-center justify-center pointer-events-none">
                    <GraduationCap className="w-64 h-64" />
                </div>
                <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="space-y-1.5">
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm">
                            {hero.badge || 'Portal Orang Tua'}
                        </span>
                        <h1 className="text-2xl font-bold">{hero.title}</h1>
                        <p className="text-rose-100 text-sm max-w-xl">{hero.subtitle}</p>
                    </div>
                    <button
                        onClick={() => fetchData(true)}
                        disabled={refreshing}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl bg-white text-rose-700 hover:bg-rose-50 transition-all disabled:opacity-50 flex-shrink-0 self-start md:self-center"
                    >
                        <RefreshCw className={cn("w-4 h-4", refreshing && "animate-spin")} />
                        Refresh Data
                    </button>
                </div>
            </div>

            {/* Child Selector Tabs (Show if parent has multiple kids) */}
            {kids.length > 1 && (
                <div className="flex flex-wrap items-center gap-2 p-1 bg-slate-200/50 border border-slate-200 rounded-xl w-fit">
                    {kids.map((kid) => (
                        <button
                            key={kid.id}
                            onClick={() => setSelectedChildId(kid.id)}
                            className={cn(
                                "px-4 py-2 rounded-lg text-xs font-bold transition-all",
                                selectedChildId === kid.id
                                    ? "bg-white text-rose-600 shadow-sm"
                                    : "text-slate-600 hover:text-slate-900"
                            )}
                        >
                            {kid.name} ({kid.class_name})
                        </button>
                    ))}
                </div>
            )}

            {selectedChild ? (
                <>
                    {/* Selected Child Status */}
                    <div className="flex items-center gap-2 border-b border-slate-200 pb-2">
                        <Users className="w-5 h-5 text-rose-500" />
                        <h2 className="text-base font-bold text-slate-800">
                            Analisis Akademik & Kehadiran: <span className="text-rose-600">{selectedChild.name}</span>
                        </h2>
                    </div>

                    {/* Metrics Grid */}
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                        <StatCard
                            title="Kelas Anak"
                            value={selectedChild.class_name}
                            sub={`Wali Kelas: ${selectedChild.homeroom_teacher}`}
                            icon={Users}
                            bg="bg-blue-50"
                            iconColor="text-blue-600"
                        />
                        <StatCard
                            title="Tingkat Kehadiran"
                            value={`${attendanceRate}%`}
                            sub="Persentase kehadiran harian"
                            icon={CheckCircle2}
                            bg="bg-emerald-50"
                            iconColor="text-emerald-600"
                        />
                        <StatCard
                            title="Total Hadir"
                            value={selectedChild.attendance.hadir}
                            sub={`${selectedChild.attendance.izin} kali Izin`}
                            icon={CheckCircle2}
                            bg="bg-emerald-50"
                            iconColor="text-emerald-600"
                        />
                        <StatCard
                            title="Sakit & Alpha"
                            value={selectedChild.attendance.sakit + selectedChild.attendance.alpha}
                            sub={`Sakit: ${selectedChild.attendance.sakit} | Alpha: ${selectedChild.attendance.alpha}`}
                            icon={AlertCircle}
                            bg="bg-red-50"
                            iconColor="text-red-600"
                        />
                    </div>

                    {/* Analysis Content Layout */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        
                        {/* Main Left Columns (2 cols) */}
                        <div className="lg:col-span-2 space-y-5">
                            
                            {/* Subject Attendance Stacked Bar Chart */}
                            <Crd>
                                <div className="mb-4">
                                    <h3 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <BarChart2 className="w-4 h-4 text-rose-600" />
                                        Grafik Analisis Kehadiran Mapel Anak
                                    </h3>
                                    <p className="text-xs text-slate-400 mt-0.5">Pantau tingkat kehadiran per-mata pelajaran yang diikuti anak</p>
                                </div>
                                <div className="w-full h-[280px]">
                                    {selectedChild.subjectStats.length === 0 ? (
                                        <div className="flex flex-col items-center justify-center h-full text-slate-400 text-sm">
                                            <ClipboardList className="w-8 h-8 opacity-30 mb-2" />
                                            Belum ada data kehadiran mata pelajaran anak yang terekam.
                                        </div>
                                    ) : (
                                        <ResponsiveContainer width="100%" height="100%">
                                            <BarChart
                                                data={selectedChild.subjectStats}
                                                margin={{ top: 10, right: 10, left: -20, bottom: 0 }}
                                            >
                                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                                <XAxis dataKey="subject_name" tick={{ fontSize: 11 }} />
                                                <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
                                                <Tooltip />
                                                <Legend wrapperStyle={{ fontSize: 11 }} />
                                                <Bar dataKey="hadir" stackId="a" fill="#10b981" name="Hadir" />
                                                <Bar dataKey="sakit" stackId="a" fill="#f59e0b" name="Sakit" />
                                                <Bar dataKey="izin" stackId="a" fill="#3b82f6" name="Izin" />
                                                <Bar dataKey="alpha" stackId="a" fill="#ef4444" name="Alpha" />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    )}
                                </div>
                            </Crd>

                            {/* Today's Schedule Timeline for Child */}
                            <Crd np className="flex flex-col">
                                <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                    <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                        <Calendar className="w-4 h-4 text-rose-500" />
                                        Jadwal Belajar Hari Ini
                                    </h3>
                                </div>
                                <div className="p-4">
                                    {selectedChild.schedules.length === 0 ? (
                                        <div className="py-8 text-center text-slate-400 text-sm">
                                            <CalendarDays className="w-8 h-8 mx-auto opacity-30 mb-2" />
                                            Tidak ada jadwal belajar anak hari ini.
                                        </div>
                                    ) : (
                                        <div className="space-y-3">
                                            {selectedChild.schedules.map((row, idx) => (
                                                <div key={idx} className="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-100 rounded-xl">
                                                    <div>
                                                        <h4 className="font-bold text-slate-800 text-sm">{row.subject}</h4>
                                                        <p className="text-xs text-slate-500 mt-0.5">Guru: {row.teacher} · Ruang {row.room || '-'}</p>
                                                    </div>
                                                    <span className="text-[11px] font-mono text-slate-600 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-sm font-semibold">{row.time}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </Crd>
                        </div>

                        {/* Right Area (1 col) */}
                        <div className="space-y-5">
                            
                            {/* Homeroom / Teacher Behavior Notes */}
                            <Crd np>
                                <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                    <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                        <FileText className="w-4 h-4 text-amber-600" />
                                        Catatan Wali Kelas / Guru
                                    </h3>
                                </div>
                                <div className="p-4 space-y-3 max-h-60 overflow-y-auto">
                                    {selectedChild.notes.length === 0 ? (
                                        <p className="text-xs text-slate-400 text-center py-6">Tidak ada catatan perkembangan khusus.</p>
                                    ) : selectedChild.notes.map((note, idx) => (
                                        <div key={idx} className="p-3 bg-amber-50/40 border border-amber-100 rounded-xl space-y-1">
                                            <h4 className="text-xs font-bold text-amber-800">{note.title}</h4>
                                            <p className="text-xs text-slate-600">{note.note}</p>
                                        </div>
                                    ))}
                                </div>
                            </Crd>

                            {/* Behavior Violations / BK points */}
                            <Crd np>
                                <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                    <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                        <Activity className="w-4 h-4 text-red-600" />
                                        Log Pelanggaran Siswa
                                    </h3>
                                </div>
                                <div className="p-4 space-y-3 max-h-60 overflow-y-auto">
                                    {selectedChild.violations.length === 0 ? (
                                        <p className="text-xs text-slate-400 text-center py-6">Bersih dari pelanggaran tata tertib.</p>
                                    ) : selectedChild.violations.map((v, idx) => (
                                        <div key={idx} className="p-3 bg-red-50/40 border border-red-100 rounded-xl flex items-start justify-between gap-2">
                                            <div>
                                                <h4 className="text-xs font-bold text-red-800">{v.type}</h4>
                                                <p className="text-xs text-slate-600 mt-0.5">{v.description}</p>
                                            </div>
                                            <span className="px-2 py-0.5 rounded bg-red-100 text-red-800 text-[10px] font-bold">-{v.points} Poin</span>
                                        </div>
                                    ))}
                                </div>
                            </Crd>

                            {/* Parent Guidelines */}
                            <Crd className="bg-rose-50/30 border-rose-100">
                                <h4 className="text-xs font-bold text-rose-800 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                    <CheckCircle2 className="w-4 h-4 text-rose-600" /> Panduan Orang Tua
                                </h4>
                                <ul className="space-y-2.5">
                                    {checklist.map((item, idx) => (
                                        <li key={idx} className="flex items-start gap-2.5 text-xs text-slate-700 font-medium">
                                            <CheckCircle2 className="w-4 h-4 text-rose-600 mt-0.5 flex-shrink-0" />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </Crd>
                        </div>
                    </div>
                </>
            ) : (
                <div className="text-center py-10 bg-white rounded-xl border border-slate-200">
                    <Users className="w-12 h-12 mx-auto text-slate-300 mb-2" />
                    <p className="text-sm text-slate-500">Belum ada data anak terhubung ke akun Anda.</p>
                </div>
            )}
        </div>
    );
};
