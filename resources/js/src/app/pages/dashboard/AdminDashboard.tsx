import React, { useState, useEffect, useCallback } from 'react';
import {
    GraduationCap, Users, BookOpen, ClipboardList, TrendingUp, TrendingDown,
    RefreshCw, Calendar, CheckCircle2, XCircle, Clock, AlertCircle
} from 'lucide-react';
import {
    AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    BarChart, Bar, Cell
} from 'recharts';
import { cn } from '../../components/ui/utils';
import api from '../../services/api';
import { toast } from 'sonner';

// ─── UI Primitives ─────────────────────────────────────────────────────
function Crd({ children, className, np }: { children: any; className?: string; np?: boolean }) {
    return <div className={cn("bg-white rounded-xl border border-slate-200 shadow-sm", !np && "p-5", className)}>{children}</div>;
}

function StatCard({
    title, value, sub, icon: Icon, bg, iconColor, trend
}: {
    title: string; value: string | number; sub?: string;
    icon: any; bg: string; iconColor: string; trend?: { value: number; label: string }
}) {
    const up = (trend?.value ?? 0) >= 0;
    return (
        <Crd>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-xs text-slate-500 mb-1 truncate font-medium">{title}</p>
                    <p className="text-2xl font-bold text-slate-900 leading-none tracking-tight">{value}</p>
                    {sub && <p className="text-[11px] text-slate-400 mt-1">{sub}</p>}
                    {trend && (
                        <p className={cn("text-xs mt-2 flex items-center gap-1 font-medium", up ? "text-emerald-600" : "text-red-500")}>
                            {up ? <TrendingUp className="w-3 h-3" /> : <TrendingDown className="w-3 h-3" />}
                            {trend.label}
                        </p>
                    )}
                </div>
                <div className={cn("w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0", bg)}>
                    <Icon className={cn("w-5 h-5", iconColor)} />
                </div>
            </div>
        </Crd>
    );
}

function Bdg({ v = "default", children }: { v?: "default" | "success" | "warning" | "danger" | "info"; children: any }) {
    const vs = {
        default: "bg-slate-100 text-slate-600",
        success: "bg-emerald-100 text-emerald-700",
        warning: "bg-amber-100 text-amber-700",
        danger:  "bg-red-100 text-red-700",
        info:    "bg-blue-100 text-blue-700",
    };
    return <span className={cn("inline-flex items-center px-2 py-0.5 rounded text-xs font-medium", vs[v])}>{children}</span>;
}

function StBdg({ st }: { st: string }) {
    const map: Record<string, { v: "default" | "success" | "warning" | "danger" | "info"; l: string }> = {
        hadir:     { v: "success", l: "Hadir"     },
        sakit:     { v: "warning", l: "Sakit"     },
        alpha:     { v: "danger",  l: "Alpha"     },
        izin:      { v: "default", l: "Izin"      },
    };
    const item = map[st] || { v: "default", l: st };
    return <Bdg v={item.v}>{item.l}</Bdg>;
}

// ─── Types ─────────────────────────────────────────────────────────────
interface DashboardData {
    stats: {
        total_siswa: number;
        total_guru: number;
        kelas_aktif: number;
        siswa_no_kelas: number;
        kehadiran_hari_ini: number;
        kehadiran_bulan_ini: number;
        rekap_hari_ini: { hadir: number; sakit: number; izin: number; alpha: number };
    };
    charts: { tren_kehadiran: { month: string; hadir: number; total: number }[] };
    recent: {
        aktivitas: { id: number; student: string; cls: string; st: string; time: string; tanggal: string }[];
        performa:  { id: number; name: string; cls: string; pct: number; total_hadir: number; total_pertemuan: number }[];
        agenda_mendatang: { id: number; title: string; start_date: string; is_holiday: boolean }[];
    };
    generated_at: string;
}

// ─── Main Component ────────────────────────────────────────────────────
export const AdminDashboard = () => {
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [data, setData] = useState<DashboardData | null>(null);
    const [lastUpdated, setLastUpdated] = useState<string>('');

    const fetchData = useCallback(async (silent = false) => {
        if (!silent) setLoading(true);
        else setRefreshing(true);
        try {
            const res = await api.get('/admin/dashboard/stats');
            setData(res.data);
            setLastUpdated(res.data.generated_at);
        } catch (err) {
            console.error('Dashboard fetch error:', err);
            if (!silent) toast.error('Gagal memuat data dashboard.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, []);

    // Fetch on mount, auto-refresh every 60 seconds
    useEffect(() => {
        fetchData();
        const interval = setInterval(() => fetchData(true), 60_000);
        return () => clearInterval(interval);
    }, [fetchData]);

    if (loading || !data) {
        return (
            <div className="flex flex-col items-center justify-center h-64 gap-3">
                <RefreshCw className="w-8 h-8 text-blue-500 animate-spin" />
                <p className="text-sm text-slate-500">Memuat data dashboard...</p>
            </div>
        );
    }

    const { stats, charts, recent } = data;
    const avgKehadiran = charts.tren_kehadiran.length > 0
        ? Math.round(charts.tren_kehadiran.reduce((a, c) => a + c.hadir, 0) / charts.tren_kehadiran.length)
        : 0;

    const rekapBar = [
        { name: 'Hadir',     value: stats.rekap_hari_ini.hadir,     color: '#10B981' },
        { name: 'Sakit',     value: stats.rekap_hari_ini.sakit,     color: '#F59E0B' },
        { name: 'Izin',      value: stats.rekap_hari_ini.izin,      color: '#6366F1' },
        { name: 'Alpha',     value: stats.rekap_hari_ini.alpha,     color: '#EF4444' },
    ];
    const totalHariIni = Object.values(stats.rekap_hari_ini).reduce((a, b) => a + b, 0);

    return (
        <div className="p-4 md:p-6 space-y-5 max-w-7xl mx-auto">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 className="text-lg font-bold text-slate-900">Dashboard</h1>
                    <p className="text-sm text-slate-500 mt-0.5">
                        {new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {lastUpdated && (
                        <span className="text-[11px] text-slate-400 hidden sm:inline">Diperbarui: {lastUpdated}</span>
                    )}
                    <button
                        onClick={() => fetchData(true)}
                        disabled={refreshing}
                        className="inline-flex items-center gap-1.5 text-sm font-medium px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition-colors disabled:opacity-50"
                    >
                        <RefreshCw className={cn("w-3.5 h-3.5", refreshing && "animate-spin")} />
                        Refresh
                    </button>
                </div>
            </div>

            {/* Stat Cards Row 1 */}
            <div className="grid grid-cols-2 xl:grid-cols-4 gap-3 md:gap-4">
                <StatCard
                    title="Total Siswa"
                    value={stats.total_siswa.toLocaleString('id-ID')}
                    sub={stats.siswa_no_kelas > 0 ? `${stats.siswa_no_kelas} belum ada kelas` : 'Semua sudah di kelas'}
                    icon={GraduationCap}
                    bg="bg-blue-50"
                    iconColor="text-blue-600"
                />
                <StatCard
                    title="Guru & Staf"
                    value={stats.total_guru.toLocaleString('id-ID')}
                    sub="Tenaga pengajar aktif"
                    icon={Users}
                    bg="bg-emerald-50"
                    iconColor="text-emerald-600"
                />
                <StatCard
                    title="Kelas Aktif"
                    value={stats.kelas_aktif.toLocaleString('id-ID')}
                    sub="Total rombel belajar"
                    icon={BookOpen}
                    bg="bg-violet-50"
                    iconColor="text-violet-600"
                />
                <StatCard
                    title="Kehadiran Hari Ini"
                    value={`${stats.kehadiran_hari_ini}%`}
                    sub={totalHariIni > 0 ? `${stats.rekap_hari_ini.hadir} dari ${totalHariIni} siswa` : 'Belum ada absensi'}
                    icon={ClipboardList}
                    bg="bg-amber-50"
                    iconColor="text-amber-600"
                />
            </div>

            {/* Charts Row */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {/* Tren Kehadiran */}
                <Crd className="lg:col-span-2" np>
                    <div className="px-5 pt-5 pb-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-semibold text-slate-900">Tren Kehadiran</h3>
                            <p className="text-xs text-slate-500 mt-0.5">6 bulan terakhir (%)</p>
                        </div>
                        <Bdg v="success">Rata-rata {avgKehadiran}%</Bdg>
                    </div>
                    <div className="p-5">
                        {charts.tren_kehadiran.every(d => d.hadir === 0) ? (
                            <div className="h-44 flex flex-col items-center justify-center gap-2 text-slate-400">
                                <TrendingUp className="w-8 h-8 opacity-30" />
                                <p className="text-xs">Belum ada data kehadiran untuk ditampilkan</p>
                            </div>
                        ) : (
                            <ResponsiveContainer width="100%" height={180}>
                                <AreaChart data={charts.tren_kehadiran} margin={{ top: 5, right: 5, left: -20, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="gbGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%"  stopColor="#3B82F6" stopOpacity={0.2} />
                                            <stop offset="95%" stopColor="#3B82F6" stopOpacity={0} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E2E8F0" />
                                    <XAxis dataKey="month" axisLine={false} tickLine={false} tick={{ fontSize: 12, fill: '#64748B' }} dy={10} />
                                    <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 12, fill: '#64748B' }} domain={[0, 100]} tickFormatter={v => `${v}%`} />
                                    <Tooltip
                                        contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: 12 }}
                                        formatter={(val: any) => [`${val}%`, 'Kehadiran']}
                                    />
                                    <Area type="monotone" dataKey="hadir" name="Kehadiran" stroke="#2563EB" strokeWidth={3} fillOpacity={1} fill="url(#gbGrad)" dot={{ r: 3, fill: '#2563EB' }} />
                                </AreaChart>
                            </ResponsiveContainer>
                        )}
                    </div>
                </Crd>

                {/* Rekap Absensi Hari Ini */}
                <Crd np>
                    <div className="px-5 pt-5 pb-4 border-b border-slate-100">
                        <h3 className="text-sm font-semibold text-slate-900">Rekap Absensi Hari Ini</h3>
                        <p className="text-xs text-slate-500 mt-0.5">{totalHariIni > 0 ? `${totalHariIni} catatan absensi` : 'Belum ada data'}</p>
                    </div>
                    <div className="p-4 space-y-2.5">
                        {rekapBar.map(item => (
                            <div key={item.name} className="flex items-center gap-3">
                                <div className="w-16 text-xs font-medium text-slate-600 flex-shrink-0">{item.name}</div>
                                <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        className="h-full rounded-full transition-all duration-500"
                                        style={{
                                            width: totalHariIni > 0 ? `${Math.round((item.value / totalHariIni) * 100)}%` : '0%',
                                            backgroundColor: item.color
                                        }}
                                    />
                                </div>
                                <span className="text-xs font-bold text-slate-700 w-6 text-right">{item.value}</span>
                            </div>
                        ))}
                        {totalHariIni === 0 && (
                            <div className="flex flex-col items-center justify-center py-6 gap-2 text-slate-400">
                                <ClipboardList className="w-8 h-8 opacity-30" />
                                <p className="text-xs">Belum ada absensi hari ini</p>
                            </div>
                        )}
                        <div className="pt-2 border-t border-slate-100 mt-3">
                            <div className="flex justify-between text-xs">
                                <span className="text-slate-500">Kehadiran bulan ini</span>
                                <span className="font-bold text-slate-800">{stats.kehadiran_bulan_ini}%</span>
                            </div>
                        </div>
                    </div>
                </Crd>
            </div>

            {/* Tables Row */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {/* Aktivitas Terbaru */}
                <Crd np className="flex flex-col lg:col-span-2">
                    <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-slate-900">Aktivitas Absensi Terbaru</h3>
                        <Bdg v="info">Live</Bdg>
                    </div>
                    <div className="flex-1 overflow-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-slate-500 text-xs uppercase sticky top-0">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">Siswa</th>
                                    <th className="px-4 py-2.5 font-medium">Kelas</th>
                                    <th className="px-4 py-2.5 font-medium">Status</th>
                                    <th className="px-4 py-2.5 font-medium text-right">Waktu</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recent.aktivitas.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-slate-400 text-xs">
                                            <ClipboardList className="w-6 h-6 opacity-30 mx-auto mb-1" />
                                            Belum ada aktivitas absensi hari ini
                                        </td>
                                    </tr>
                                ) : recent.aktivitas.map(a => (
                                    <tr key={a.id} className="hover:bg-slate-50/50 transition-colors">
                                        <td className="px-4 py-3"><p className="font-medium text-slate-800 text-sm">{a.student}</p></td>
                                        <td className="px-4 py-3 text-xs text-slate-500">{a.cls}</td>
                                        <td className="px-4 py-3"><StBdg st={a.st} /></td>
                                        <td className="px-4 py-3 text-right">
                                            <p className="text-xs font-medium text-slate-700">{a.time}</p>
                                            <p className="text-[10px] text-slate-400">{a.tanggal}</p>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Crd>

                {/* Sidebar kanan: Performa + Agenda */}
                <div className="flex flex-col gap-4">
                    {/* Top 5 Kehadiran */}
                    <Crd np className="flex flex-col">
                        <div className="px-4 py-3.5 border-b border-slate-100">
                            <h3 className="text-sm font-semibold text-slate-900">Top 5 Kehadiran Siswa</h3>
                        </div>
                        <div className="p-4 space-y-3">
                            {recent.performa.length === 0 ? (
                                <p className="text-xs text-slate-400 text-center py-4">Belum ada data performa</p>
                            ) : recent.performa.map((s, idx) => (
                                <div key={s.id} className="flex items-center gap-3">
                                    <span className={cn(
                                        "w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center flex-shrink-0",
                                        idx === 0 ? "bg-amber-400 text-white" : "bg-slate-100 text-slate-500"
                                    )}>{idx + 1}</span>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-xs font-semibold text-slate-800 truncate">{s.name}</p>
                                        <p className="text-[10px] text-slate-400">{s.cls} · {s.total_hadir}/{s.total_pertemuan} pertemuan</p>
                                    </div>
                                    <span className={cn(
                                        "text-xs font-bold",
                                        s.pct >= 90 ? "text-emerald-600" : s.pct >= 75 ? "text-amber-600" : "text-red-500"
                                    )}>{s.pct}%</span>
                                </div>
                            ))}
                        </div>
                    </Crd>

                    {/* Agenda Mendatang */}
                    <Crd np className="flex flex-col">
                        <div className="px-4 py-3.5 border-b border-slate-100">
                            <h3 className="text-sm font-semibold text-slate-900">Agenda Mendatang</h3>
                        </div>
                        <div className="p-4 space-y-3">
                            {recent.agenda_mendatang.length === 0 ? (
                                <p className="text-xs text-slate-400 text-center py-4">Tidak ada agenda mendatang</p>
                            ) : recent.agenda_mendatang.map(a => (
                                <div key={a.id} className="flex items-start gap-3">
                                    <div className={cn(
                                        "w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0",
                                        a.is_holiday ? "bg-red-50" : "bg-blue-50"
                                    )}>
                                        <Calendar className={cn("w-4 h-4", a.is_holiday ? "text-red-500" : "text-blue-500")} />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-xs font-semibold text-slate-800 truncate">{a.title}</p>
                                        <p className="text-[10px] text-slate-400">{a.start_date} {a.is_holiday && '· Libur'}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Crd>
                </div>
            </div>
        </div>
    );
};
