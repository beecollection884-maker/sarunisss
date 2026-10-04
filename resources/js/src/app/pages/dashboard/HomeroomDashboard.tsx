import React, { useState, useEffect, useCallback } from 'react';
import {
    GraduationCap, Users, BookOpen, Calendar,
    RefreshCw, ClipboardList, CheckCircle2, AlertCircle, Speaker, CalendarDays, FileText
} from 'lucide-react';
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

interface HomeroomDashboardData {
    hero: { title: string; subtitle: string; badge: string; asset: string };
    summary: { label: string; value: number | string; meta?: string }[];
    classes: {
        id: number;
        name: string;
        students_count: number;
        level: string;
    }[];
    homeroomStudents: {
        id: number;
        name: string;
        nik: string;
        school_class_id: number;
        class_name: string;
    }[];
    noteBox: string[];
    studentNoteRows: string[];
    checklist: string[];
    announcements: { id: number; title: string; content: string; created_at: string; author_name?: string }[];
    activeAcademicYear: string;
    activeSemester: string;
    teacherSubjectAttendanceHadir?: {
        id: number;
        subject_name: string;
        class_name: string;
        total_hadir: number;
    }[];
}

const AnimatedSubjectAttendanceCard = ({ data = [] }: { data?: any[] }) => {
    const [index, setIndex] = useState(0);
    const [fade, setFade] = useState(true);

    useEffect(() => {
        if (data.length <= 1) return;

        const interval = setInterval(() => {
            setFade(false);
            setTimeout(() => {
                setIndex((prevIndex) => (prevIndex + 1) % data.length);
                setFade(true);
            }, 300); // match transition timeout
        }, 4000); // rotate every 4 seconds

        return () => clearInterval(interval);
    }, [data]);

    const current = data[index] || { subject_name: 'Belum mengajar', class_name: '-', total_hadir: 0 };

    return (
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-xs text-slate-500 mb-1 truncate font-medium">Hadir Mapel</p>
                    <div className={`transition-all duration-300 transform ${fade ? 'opacity-100 translate-y-0 scale-100' : 'opacity-0 -translate-y-1 scale-95'}`}>
                        <p className="text-2xl font-bold text-slate-900 leading-none tracking-tight">
                            {current.total_hadir}
                        </p>
                        <p className="text-[11px] text-slate-400 mt-1.5 truncate font-medium">
                            {current.subject_name} {current.class_name !== '-' ? `(${current.class_name})` : ''}
                        </p>
                    </div>
                </div>
                <div className="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 bg-amber-50">
                    <BookOpen className="w-5 h-5 text-amber-600" />
                </div>
            </div>
        </div>
    );
};

export const HomeroomDashboard = () => {
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [data, setData] = useState<HomeroomDashboardData | null>(null);

    const fetchData = useCallback(async (silent = false) => {
        if (!silent) setLoading(true);
        else setRefreshing(true);
        try {
            const res = await api.get('/walikelas/dashboard');
            setData(res.data);
        } catch (err) {
            console.error('Homeroom dashboard fetch error:', err);
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
                <p className="text-sm text-slate-500">Memuat dashboard wali kelas...</p>
            </div>
        );
    }

    const { hero, summary, classes, noteBox, studentNoteRows, checklist, announcements } = data;

    return (
        <div className="p-4 md:p-6 space-y-5 max-w-7xl mx-auto">
            {/* Hero Card */}
            <div className="bg-gradient-to-r from-purple-700 to-indigo-800 text-white rounded-2xl p-6 shadow-md relative overflow-hidden">
                <div className="absolute right-0 top-0 bottom-0 w-1/3 opacity-10 flex items-center justify-center pointer-events-none">
                    <Users className="w-64 h-64" />
                </div>
                <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="space-y-1.5">
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm">
                            {hero.badge}
                        </span>
                        <h1 className="text-2xl font-bold">{hero.title}</h1>
                        <p className="text-purple-100 text-sm max-w-xl">{hero.subtitle}</p>
                    </div>
                    <button
                        onClick={() => fetchData(true)}
                        disabled={refreshing}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl bg-white text-purple-700 hover:bg-purple-50 transition-all disabled:opacity-50 flex-shrink-0 self-start md:self-center"
                    >
                        <RefreshCw className={cn("w-4 h-4", refreshing && "animate-spin")} />
                        Refresh Data
                    </button>
                </div>
            </div>

            {/* Metrics */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                {summary.slice(0, 3).map((item) => {
                    const iconMap: Record<string, any> = {
                        'Total Siswa': GraduationCap,
                        'Hadir': CheckCircle2,
                        'Tidak Hadir': AlertCircle,
                    };
                    const bgMap: Record<string, string> = {
                        'Total Siswa': 'bg-blue-50',
                        'Hadir': 'bg-emerald-50',
                        'Tidak Hadir': 'bg-red-50',
                    };
                    const colorMap: Record<string, string> = {
                        'Total Siswa': 'text-blue-600',
                        'Hadir': 'text-emerald-600',
                        'Tidak Hadir': 'text-red-600',
                    };
                    const Icon = iconMap[item.label] || BookOpen;
                    return (
                        <StatCard
                            key={item.label}
                            title={item.label}
                            value={item.value}
                            sub={item.meta}
                            icon={Icon}
                            bg={bgMap[item.label] || 'bg-slate-50'}
                            iconColor={colorMap[item.label] || 'text-slate-600'}
                        />
                    );
                })}
                <AnimatedSubjectAttendanceCard data={data.teacherSubjectAttendanceHadir || []} />
            </div>

            {/* Split Layout */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* Left Columns */}
                <div className="lg:col-span-2 space-y-4">
                    {/* Classes summary */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {classes.map((cls) => (
                            <Crd key={cls.id} className="hover:border-purple-300 transition-colors cursor-pointer">
                                <p className="text-xs font-semibold text-purple-600 uppercase">Kelas Perwalian</p>
                                <h4 className="text-xl font-bold text-slate-800 mt-1">{cls.name}</h4>
                                <div className="mt-3 flex justify-between text-xs text-slate-500 font-medium">
                                    <span>Total Rombel: {cls.students_count} Siswa</span>
                                    <span>Tingkat: {cls.level}</span>
                                </div>
                            </Crd>
                        ))}
                    </div>

                    {/* Alerts / Info */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {/* Note Box */}
                        <Crd className="border-red-100 bg-red-50/20">
                            <h4 className="text-xs font-bold text-red-800 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                <AlertCircle className="w-4 h-4 text-red-600" /> Ketidakhadiran Hari Ini
                            </h4>
                            {noteBox.length === 0 ? (
                                <p className="text-xs text-slate-500">Semua siswa perwalian hadir hari ini.</p>
                            ) : (
                                <ul className="space-y-1.5 text-xs text-slate-700">
                                    {noteBox.map((note, idx) => (
                                        <li key={idx} className="list-disc list-inside">{note}</li>
                                    ))}
                                </ul>
                            )}
                        </Crd>

                        {/* Open Student Notes */}
                        <Crd className="border-amber-100 bg-amber-50/20">
                            <h4 className="text-xs font-bold text-amber-800 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                <FileText className="w-4 h-4 text-amber-700" /> Catatan Siswa Terbaru
                            </h4>
                            {studentNoteRows.length === 0 ? (
                                <p className="text-xs text-slate-500">Tidak ada catatan perilaku/akademik terbuka.</p>
                            ) : (
                                <ul className="space-y-1.5 text-xs text-slate-700">
                                    {studentNoteRows.map((note, idx) => (
                                        <li key={idx} className="list-disc list-inside">{note}</li>
                                    ))}
                                </ul>
                            )}
                        </Crd>
                    </div>
                </div>

                {/* Right Column: Widgets */}
                <div className="space-y-4">
                    {/* Announcements */}
                    <Crd np>
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h3 className="text-sm font-bold text-slate-900">Pengumuman Sekolah</h3>
                            <Speaker className="w-4 h-4 text-purple-500" />
                        </div>
                        <div className="divide-y divide-slate-100 max-h-80 overflow-y-auto">
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
