import {
    LayoutDashboard, Users, BookOpen, ClipboardList, TrendingUp,
    DollarSign, BarChart2, Settings, Palette, GraduationCap, School,
    UserCheck, Home, Award, Shield
} from "lucide-react";
import { Role } from "../context/AuthContext";

export const ROLE_LABELS: Record<Role, string> = {
    administrator: "Administrator", "guru-mapel": "Guru Mapel", "wali-kelas": "Wali Kelas",
    siswa: "Siswa", "orang-tua": "Orang Tua", "guru-bk": "Guru BK", "wakil-kepala": "Wakil Kepala",
};

export const ROLES = [
    { id: "administrator" as Role,  label: "Administrator",   desc: "Pengelola sistem",   icon: Shield,       color: "bg-blue-100 text-blue-700"    },
    { id: "guru-mapel" as Role,     label: "Guru Mapel",      desc: "Pengajar",           icon: BookOpen,     color: "bg-emerald-100 text-emerald-700" },
    { id: "wali-kelas" as Role,     label: "Wali Kelas",      desc: "Wali kelas",         icon: Users,        color: "bg-violet-100 text-violet-700" },
    { id: "siswa" as Role,          label: "Siswa",           desc: "Peserta didik",      icon: GraduationCap,color: "bg-amber-100 text-amber-700"   },
    { id: "orang-tua" as Role,      label: "Orang Tua",       desc: "Wali murid",         icon: Home,         color: "bg-rose-100 text-rose-700"     },
    { id: "guru-bk" as Role,        label: "Guru BK",         desc: "Bimbingan",          icon: UserCheck,    color: "bg-cyan-100 text-cyan-700"     },
    { id: "wakil-kepala" as Role,   label: "Wakil Kepala",    desc: "Wakil kepala",       icon: Award,        color: "bg-orange-100 text-orange-700" },
];

import {
    LayoutDashboard, Users, BookOpen, ClipboardList, TrendingUp,
    Settings, GraduationCap, School, UserCheck, Home, Shield,
    Calendar, Clock, Database, FileText, AlertTriangle, Speaker,
    BarChart2, Key, List, Sliders, Archive, Download, CheckSquare,
    Activity, CalendarDays
} from "lucide-react";

export type NavItem = {
    id: string;
    label: string;
    icon?: any;
    path?: string;
    subItems?: Omit<NavItem, 'icon' | 'subItems'>[];
};

export type NavGroup = {
    group: string;
    items: NavItem[];
};

export type NavCapabilities = {
    hasTeachingSchedule?: boolean | null;
    hasHomeroomClass?: boolean | null;
};

export const ADMIN_NAV_GROUPS: NavGroup[] = [
    {
        group: "Utama",
        items: [
            { id: "admin-dashboard", label: "Dashboard", icon: LayoutDashboard, path: "/" }
        ]
    },
    {
        group: "Manajemen Utama",
        items: [
            {
                id: "akademik", label: "Akademik", icon: BookOpen,
                subItems: [
                    { id: "akademik-tahun", label: "Tahun Ajaran", path: "/akademik/tahun-ajaran" },
                    { id: "akademik-mapel", label: "Mata Pelajaran", path: "/akademik/mapel" },
                    { id: "akademik-kelas", label: "Kelas", path: "/akademik/kelas" },
                    { id: "akademik-kalender", label: "Kalender Akademik", path: "/akademik/kalender" },
                ]
            },
            {
                id: "data-master", label: "Data Master", icon: Database,
                subItems: [
                    { id: "master-siswa", label: "Siswa", path: "/master/siswa" },
                    { id: "master-guru", label: "Guru", path: "/master/guru" },
                    { id: "master-ortu", label: "Orang Tua", path: "/master/orang-tua" },
                ]
            },
        ]
    },
    {
        group: "Operasional",
        items: [
            {
                id: "absensi", label: "Absensi", icon: CheckSquare,
                subItems: [
                    { id: "absensi-rekap", label: "Rekap Absen Siswa", path: "/absensi/rekap" },
                ]
            },
            {
                id: "pengumuman", label: "Pengumuman", icon: Speaker, path: "/pengumuman"
            },
        ]
    },
    {
        group: "Sistem & Laporan",
        items: [
            {
                id: "laporan", label: "Laporan", icon: FileText,
                subItems: [
                    { id: "laporan-kehadiran", label: "Kehadiran", path: "/laporan/kehadiran" },
                    { id: "laporan-siswa", label: "Siswa", path: "/laporan/siswa" },
                    { id: "laporan-guru", label: "Guru", path: "/laporan/guru" },
                    { id: "laporan-jadwal", label: "Jadwal", path: "/laporan/jadwal" },
                    { id: "laporan-pelanggaran", label: "Pelanggaran", path: "/laporan/pelanggaran" },
                ]
            },
            {
                id: "manajemen-pengguna", label: "Pengguna", icon: Users,
                subItems: [
                    { id: "users-akun", label: "Semua Akun", path: "/pengguna/akun" },
                ]
            },
            {
                id: "pengaturan", label: "Pengaturan", icon: Settings,
                subItems: [
                    { id: "settings-profil-saya", label: "Profil Saya", path: "/admin/profil" },
                    { id: "settings-data-reset", label: "Reset Data", path: "/pengaturan/data-reset" },
                ]
            },
        ]
    }
];

export const TEACHER_NAV_GROUPS: NavGroup[] = [
    {
        group: "Utama",
        items: [
            { id: "teacher-dashboard", label: "Dashboard", icon: LayoutDashboard, path: "/guru-mapel/dashboard" }
        ]
    },
    {
        group: "Akademik",
        items: [
            { id: "teacher-jadwal", label: "Jadwal Mengajar", icon: CalendarDays, path: "/guru-mapel/jadwal" },
            { id: "teacher-kalender", label: "Kalender Akademik", icon: Calendar, path: "/guru-mapel/kalender" },
        ]
    },
    {
        group: "Kegiatan Belajar",
        items: [
            {
                id: "teacher-absensi", label: "Absensi", icon: CheckSquare,
                subItems: [
                    { id: "teacher-absensi-rekap-mapel", label: "Rekap Absensi", path: "/guru-mapel/absensi/rekap-mapel" },
                ]
            },
        ]
    },
    {
        group: "Pengaturan",
        items: [
            { id: "teacher-profil", label: "Profil Saya", icon: UserCheck, path: "/guru-mapel/profil" },
        ]
    }
];

const INHERITED_WALIKELAS_TEACHER_GROUPS: NavGroup[] = TEACHER_NAV_GROUPS
    .filter(g => g.group !== "Utama")
    .filter((group) => group.items.length > 0);

export const WALIKELAS_NAV_GROUPS: NavGroup[] = [
    {
        group: "Utama",
        items: [
            { id: "walikelas-dashboard", label: "Dashboard Wali Kelas", icon: LayoutDashboard, path: "/walikelas/dashboard" }
        ]
    },
    {
        group: "Tugas Wali Kelas",
        items: [
            {
                id: "walikelas-absensi", label: "Absensi Harian", icon: CheckSquare,
                subItems: [
                    { id: "walikelas-absensi-data-kelas", label: "Lihat Data Kelas", path: "/walikelas/absensi/data-kelas" },
                    { id: "walikelas-absensi-input", label: "Input Absen Kelas", path: "/walikelas/absensi/input" },
                    { id: "walikelas-absensi-rekap", label: "Rekap Absensi", path: "/walikelas/absensi/rekap" },
                ]
            },
        ]
    },
    ...INHERITED_WALIKELAS_TEACHER_GROUPS // Inherit teacher menus since they also teach
];

export const STUDENT_NAV_GROUPS: NavGroup[] = [
    {
        group: "Siswa",
        items: [
            { id: "student-dashboard", label: "Dashboard", icon: LayoutDashboard, path: "/siswa/dashboard" },
            { id: "student-schedule", label: "Jadwal Pelajaran", icon: CalendarDays, path: "/siswa/jadwal-pelajaran" },
            { id: "student-subject-attendance", label: "Absensi Mapel", icon: BookOpen, path: "/siswa/absensi-mapel" },
            { id: "student-class-attendance", label: "Absensi Kelas", icon: CheckSquare, path: "/siswa/absensi-kelas" },
            { id: "student-notes", label: "Catatan", icon: FileText, path: "/siswa/catatan" },
            { id: "student-profil", label: "Profil Saya", icon: UserCheck, path: "/siswa/profil" },
        ]
    }
];

export const PARENT_NAV_GROUPS: NavGroup[] = [
    {
        group: "Orang Tua",
        items: [
            { id: "parent-dashboard", label: "Ringkasan Anak", icon: LayoutDashboard, path: "/orang-tua/dashboard" },
            { id: "parent-subject-attendance", label: "Absensi Mapel", icon: BookOpen, path: "/orang-tua/absensi-mapel" },
            { id: "parent-class-attendance", label: "Absensi Kelas", icon: CheckSquare, path: "/orang-tua/absensi-kelas" },
            { id: "parent-notes", label: "Catatan Anak", icon: FileText, path: "/orang-tua/catatan" },
            { id: "parent-profil", label: "Profil Saya", icon: UserCheck, path: "/orang-tua/profil" },
        ]
    }
];

const filterByCapabilities = (groups: NavGroup[], capabilities?: NavCapabilities): NavGroup[] => {
    const hasTeachingSchedule = capabilities?.hasTeachingSchedule;
    const hasHomeroomClass = capabilities?.hasHomeroomClass;
    const teachingOnlySubItems = new Set([
        'teacher-absensi-riwayat-mapel',
        'teacher-absensi-rekap-mapel',
    ]);

    const filterSubItems = (subItems?: NavItem['subItems']) => {
        if (!subItems) return subItems;

        return subItems.filter((item) => {

            if (teachingOnlySubItems.has(item.id)) {
                return hasTeachingSchedule !== false;
            }

            return true;
        });
    };

    return groups
        .map((group) => ({
            ...group,
            items: group.items.map((item) => ({
                ...item,
                subItems: filterSubItems(item.subItems),
            })).filter((item) => {
                if (item.id === 'teacher-jadwal') {
                    return hasTeachingSchedule !== false;
                }

                if (item.id === 'teacher-absensi') {
                    return (hasTeachingSchedule !== false) && (item.subItems?.length || 0) > 0;
                }

                if (item.id === 'walikelas-absensi' || item.id === 'walikelas-siswa') {
                    return hasHomeroomClass !== false;
                }

                return true;
            }),
        }))
        .filter((group) => group.items.length > 0);
};

export const getNavGroups = (portal?: string, capabilities?: NavCapabilities): NavGroup[] => {
    const applyCapabilityFilter = (groups: NavGroup[]) => filterByCapabilities(groups, capabilities);

    let effectivePortal = portal;
    if (portal === 'guru-mapel' && capabilities?.hasHomeroomClass) {
        effectivePortal = 'walikelas';
    }

    switch (effectivePortal) {
        case 'walikelas':
            return applyCapabilityFilter(WALIKELAS_NAV_GROUPS);
        case 'guru-mapel':
            return applyCapabilityFilter(TEACHER_NAV_GROUPS);
        case 'siswa':
            return STUDENT_NAV_GROUPS;
        case 'orang-tua':
            return PARENT_NAV_GROUPS;
        case 'admin':
        default:
            return ADMIN_NAV_GROUPS;
    }
};

