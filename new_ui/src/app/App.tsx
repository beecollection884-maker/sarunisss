import { useState, useRef, useEffect } from "react";
import { motion, AnimatePresence } from "motion/react";
import {
  LayoutDashboard, Users, BookOpen, ClipboardList, TrendingUp,
  DollarSign, BarChart2, Settings, ChevronLeft, ChevronRight,
  Bell, Search, LogOut, User, Menu, Eye, EyeOff, Mail, Lock,
  ArrowRight, CheckCircle, AlertCircle, Plus, Download,
  GraduationCap, School, UserCheck, Phone, MapPin, Camera,
  Shield, Palette, Home, Award, Clock, ChevronDown, RefreshCw,
  AlertTriangle, Info, Filter, Edit2, Trash2, X
} from "lucide-react";
import {
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip,
  ResponsiveContainer, PieChart, Pie, Cell
} from "recharts";
import { toast, Toaster } from "sonner";
import { clsx } from "clsx";

// ─── Types ────────────────────────────────────────────────────────────────────
type Role = "administrator" | "guru-mapel" | "wali-kelas" | "siswa" | "orang-tua" | "guru-bk" | "wakil-kepala";
type Page = "auth" | "dashboard" | "design-system" | "profile" | "students" | "teachers" | "classes" | "attendance" | "grades" | "finance" | "reports" | "settings";
interface AppUser { name: string; role: Role; email: string; }

// ─── Utils ────────────────────────────────────────────────────────────────────
const cn = (...c: (string | undefined | null | false)[]) => c.filter(Boolean).join(" ");

// ─── Constants ────────────────────────────────────────────────────────────────
const ROLES: { id: Role; label: string; desc: string; icon: any; color: string }[] = [
  { id: "administrator", label: "Administrator", desc: "Pengelola sistem", icon: Shield, color: "bg-blue-100 text-blue-700" },
  { id: "guru-mapel", label: "Guru Mapel", desc: "Pengajar", icon: BookOpen, color: "bg-emerald-100 text-emerald-700" },
  { id: "wali-kelas", label: "Wali Kelas", desc: "Wali kelas", icon: Users, color: "bg-violet-100 text-violet-700" },
  { id: "siswa", label: "Siswa", desc: "Peserta didik", icon: GraduationCap, color: "bg-amber-100 text-amber-700" },
  { id: "orang-tua", label: "Orang Tua", desc: "Wali murid", icon: Home, color: "bg-rose-100 text-rose-700" },
  { id: "guru-bk", label: "Guru BK", desc: "Bimbingan", icon: UserCheck, color: "bg-cyan-100 text-cyan-700" },
  { id: "wakil-kepala", label: "Wakil Kepala", desc: "Wakil kepala", icon: Award, color: "bg-orange-100 text-orange-700" },
];

const ROLE_LABELS: Record<Role, string> = {
  administrator: "Administrator", "guru-mapel": "Guru Mapel", "wali-kelas": "Wali Kelas",
  siswa: "Siswa", "orang-tua": "Orang Tua", "guru-bk": "Guru BK", "wakil-kepala": "Wakil Kepala",
};

const NAV_GROUPS = [
  { group: "Utama", items: [{ id: "dashboard" as Page, label: "Dashboard", icon: LayoutDashboard }] },
  {
    group: "Akademik", items: [
      { id: "students" as Page, label: "Siswa", icon: GraduationCap },
      { id: "teachers" as Page, label: "Guru & Staf", icon: Users },
      { id: "classes" as Page, label: "Kelas", icon: School },
      { id: "attendance" as Page, label: "Kehadiran", icon: ClipboardList },
      { id: "grades" as Page, label: "Nilai", icon: TrendingUp },
    ]
  },
  {
    group: "Administrasi", items: [
      { id: "finance" as Page, label: "Keuangan", icon: DollarSign },
      { id: "reports" as Page, label: "Laporan", icon: BarChart2 },
    ]
  },
  { group: "Developer", items: [{ id: "design-system" as Page, label: "Design System", icon: Palette }] },
  { group: "Sistem", items: [{ id: "settings" as Page, label: "Pengaturan", icon: Settings }] },
];

// ─── Mock Data ────────────────────────────────────────────────────────────────
const ATTEND_CHART = [
  { month: "Jan", hadir: 94 }, { month: "Feb", hadir: 91 }, { month: "Mar", hadir: 96 },
  { month: "Apr", hadir: 93 }, { month: "Mei", hadir: 97 }, { month: "Jun", hadir: 89 },
];
const GRADE_PIE = [
  { name: "A (90-100)", value: 234, color: "#22C55E" },
  { name: "B (75-89)", value: 456, color: "#3B82F6" },
  { name: "C (60-74)", value: 312, color: "#F59E0B" },
  { name: "D (< 60)", value: 89, color: "#EF4444" },
];
const STUDENTS_DATA = [
  { id: 1, name: "Andi Pratama", nisn: "0051234567", cls: "9A", gender: "L", status: "Aktif", pct: 95, gpa: 88.5 },
  { id: 2, name: "Budi Santoso", nisn: "0051234568", cls: "8B", gender: "L", status: "Aktif", pct: 87, gpa: 76.2 },
  { id: 3, name: "Citra Dewi", nisn: "0051234569", cls: "7C", gender: "P", status: "Aktif", pct: 92, gpa: 91.3 },
  { id: 4, name: "Dina Marlina", nisn: "0051234570", cls: "9A", gender: "P", status: "Aktif", pct: 98, gpa: 94.7 },
  { id: 5, name: "Eko Prasetyo", nisn: "0051234571", cls: "8A", gender: "L", status: "Non-Aktif", pct: 72, gpa: 65.8 },
  { id: 6, name: "Fitri Handayani", nisn: "0051234572", cls: "7A", gender: "P", status: "Aktif", pct: 94, gpa: 83.4 },
  { id: 7, name: "Gilang Ramadhan", nisn: "0051234573", cls: "9B", gender: "L", status: "Aktif", pct: 89, gpa: 78.9 },
  { id: 8, name: "Hani Puspita", nisn: "0051234574", cls: "8C", gender: "P", status: "Aktif", pct: 96, gpa: 89.1 },
];
const RECENT_ACT = [
  { id: 1, student: "Andi Pratama", cls: "9A", action: "Masuk kelas", time: "08:05", st: "hadir" },
  { id: 2, student: "Budi Santoso", cls: "8B", action: "Sakit", time: "07:30", st: "sakit" },
  { id: 3, student: "Citra Dewi", cls: "7C", action: "Terlambat", time: "08:20", st: "terlambat" },
  { id: 4, student: "Dina Marlina", cls: "9A", action: "Masuk kelas", time: "07:45", st: "hadir" },
  { id: 5, student: "Eko Prasetyo", cls: "8A", action: "Tidak hadir", time: "—", st: "alpa" },
];

// ─── UI Primitives ────────────────────────────────────────────────────────────
function Btn({ v = "primary", sz = "md", loading, disabled, icon, iconR, children, onClick, className, type = "button", full }: {
  v?: "primary" | "secondary" | "outline" | "ghost" | "danger"; sz?: "sm" | "md" | "lg";
  loading?: boolean; disabled?: boolean; icon?: any; iconR?: any; children?: any;
  onClick?: () => void; className?: string; type?: "button" | "submit" | "reset"; full?: boolean;
}) {
  const base = "inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 select-none whitespace-nowrap";
  const vs = {
    primary: "bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-400 shadow-sm active:bg-blue-800",
    secondary: "bg-slate-100 text-slate-800 hover:bg-slate-200 focus:ring-slate-300",
    outline: "border border-slate-300 text-slate-700 hover:bg-slate-50 focus:ring-slate-300 bg-white",
    ghost: "text-slate-600 hover:bg-slate-100 focus:ring-slate-200",
    danger: "bg-red-600 text-white hover:bg-red-700 focus:ring-red-400 shadow-sm",
  };
  const ss = { sm: "text-xs px-3 py-1.5 h-7 gap-1.5", md: "text-sm px-4 py-2 h-9", lg: "text-sm px-5 py-2.5 h-11" };
  return (
    <button type={type} onClick={onClick} disabled={disabled || loading}
      className={cn(base, vs[v], ss[sz], full && "w-full", (disabled || loading) && "opacity-50 cursor-not-allowed", className)}>
      {loading ? <RefreshCw className="w-3.5 h-3.5 animate-spin" /> : icon}
      {children}
      {!loading && iconR}
    </button>
  );
}

type BadgeV = "default" | "success" | "warning" | "danger" | "info" | "outline";
function Bdg({ v = "default", children, className }: { v?: BadgeV; children: any; className?: string }) {
  const vs: Record<BadgeV, string> = {
    default: "bg-slate-100 text-slate-700", success: "bg-green-100 text-green-700",
    warning: "bg-amber-100 text-amber-700", danger: "bg-red-100 text-red-700",
    info: "bg-blue-100 text-blue-700", outline: "border border-slate-300 text-slate-600 bg-transparent",
  };
  return <span className={cn("inline-flex items-center px-2 py-0.5 rounded text-xs font-medium", vs[v], className)}>{children}</span>;
}

function Inp({ label, type = "text", ph, val, onChange, err, icon, iconR, disabled, req, hint, cls }: {
  label?: string; type?: string; ph?: string; val?: string; onChange?: (v: string) => void;
  err?: string; icon?: any; iconR?: any; disabled?: boolean; req?: boolean; hint?: string; cls?: string;
}) {
  return (
    <div className={cn("flex flex-col gap-1", cls)}>
      {label && <label className="text-sm font-medium text-slate-700">{label}{req && <span className="text-red-500 ml-0.5">*</span>}</label>}
      <div className="relative">
        {icon && <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">{icon}</span>}
        <input type={type} placeholder={ph} value={val} onChange={e => onChange?.(e.target.value)} disabled={disabled}
          className={cn(
            "w-full h-9 rounded-lg border text-sm bg-white text-slate-900 placeholder:text-slate-400 transition-all",
            "focus:outline-none focus:ring-2",
            icon ? "pl-9" : "pl-3", iconR ? "pr-9" : "pr-3",
            err ? "border-red-400 focus:border-red-500 focus:ring-red-100" : "border-slate-300 hover:border-slate-400 focus:border-blue-500 focus:ring-blue-100",
            disabled && "opacity-50 cursor-not-allowed bg-slate-50"
          )} />
        {iconR && <span className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">{iconR}</span>}
      </div>
      {err && <p className="text-xs text-red-600 flex items-center gap-1"><AlertCircle className="w-3 h-3" />{err}</p>}
      {hint && !err && <p className="text-xs text-slate-500">{hint}</p>}
    </div>
  );
}

function Crd({ children, className, np }: { children: any; className?: string; np?: boolean }) {
  return <div className={cn("bg-white rounded-xl border border-slate-200 shadow-sm", !np && "p-5", className)}>{children}</div>;
}

const AV_COLORS = ["bg-blue-600", "bg-violet-600", "bg-emerald-600", "bg-amber-600", "bg-rose-600", "bg-cyan-600", "bg-orange-600"];
function Av({ name, sz = "md", className }: { name: string; sz?: "xs" | "sm" | "md" | "lg" | "xl"; className?: string }) {
  const ss = { xs: "w-6 h-6 text-[9px]", sm: "w-8 h-8 text-xs", md: "w-9 h-9 text-sm", lg: "w-12 h-12 text-base", xl: "w-20 h-20 text-2xl" };
  const color = AV_COLORS[name.charCodeAt(0) % AV_COLORS.length];
  const init = name.split(" ").slice(0, 2).map(n => n[0]).join("").toUpperCase();
  return <div className={cn("rounded-full text-white font-semibold flex items-center justify-center flex-shrink-0", ss[sz], color, className)}>{init}</div>;
}

function StatCard({ title, value, change, icon: Icon, bg }: { title: string; value: string; change?: string; icon: any; bg: string }) {
  const pos = change?.startsWith("+");
  return (
    <Crd>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs text-slate-500 mb-1 truncate">{title}</p>
          <p className="text-2xl font-bold text-slate-900 leading-none">{value}</p>
          {change && (
            <p className={cn("text-xs mt-2 flex items-center gap-1 font-medium", pos ? "text-green-600" : "text-red-500")}>
              <TrendingUp className={cn("w-3 h-3", !pos && "rotate-180")} />
              {change} dari bulan lalu
            </p>
          )}
        </div>
        <div className={cn("w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0", bg)}>
          <Icon className="w-5 h-5" />
        </div>
      </div>
    </Crd>
  );
}

function StBdg({ st }: { st: string }) {
  const map: Record<string, { v: BadgeV; l: string }> = {
    hadir: { v: "success", l: "Hadir" },
    sakit: { v: "warning", l: "Sakit" },
    terlambat: { v: "info", l: "Terlambat" },
    alpa: { v: "danger", l: "Alpa" },
    izin: { v: "default", l: "Izin" },
    "Aktif": { v: "success", l: "Aktif" },
    "Non-Aktif": { v: "danger", l: "Non-Aktif" },
  };
  const item = map[st] || { v: "default" as BadgeV, l: st };
  return <Bdg v={item.v}>{item.l}</Bdg>;
}

// ─── Auth Flow ────────────────────────────────────────────────────────────────
function AuthFlow({ onLogin }: { onLogin: (u: AppUser) => void }) {
  const [step, setStep] = useState<"portal" | "login" | "fg-email" | "fg-otp" | "fg-reset" | "fg-done">("portal");
  const [role, setRole] = useState<Role | null>(null);
  const [email, setEmail] = useState("admin@smpipyakin.sch.id");
  const [password, setPassword] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [remember, setRemember] = useState(false);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<{ email?: string; pwd?: string }>({});
  const [otp, setOtp] = useState(["", "", "", "", "", ""]);
  const [timer, setTimer] = useState(60);
  const [newPwd, setNewPwd] = useState("");
  const [confirmPwd, setConfirmPwd] = useState("");
  const otpRefs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    if (step === "fg-otp" && timer > 0) {
      const t = setTimeout(() => setTimer(c => c - 1), 1000);
      return () => clearTimeout(t);
    }
  }, [step, timer]);

  const pwdStr = (p: string) => {
    let s = 0;
    if (p.length >= 8) s++; if (/[A-Z]/.test(p)) s++; if (/[0-9]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++;
    return s;
  };

  const doLogin = async () => {
    const e: typeof errors = {};
    if (!email) e.email = "Email wajib diisi";
    else if (!/\S+@\S+\.\S+/.test(email)) e.email = "Format email tidak valid";
    if (!password) e.pwd = "Password wajib diisi";
    setErrors(e);
    if (Object.keys(e).length) return;
    setLoading(true);
    await new Promise(r => setTimeout(r, 1200));
    setLoading(false);
    onLogin({ name: "Ahmad Fauzi, S.Pd", role: role!, email });
    toast.success("Berhasil masuk!", { description: `Selamat datang, ${ROLE_LABELS[role!]}` });
  };

  const doOtp = (i: number, v: string) => {
    if (!/^\d*$/.test(v)) return;
    const n = [...otp]; n[i] = v.slice(-1); setOtp(n);
    if (v && i < 5) otpRefs.current[i + 1]?.focus();
    if (!v && i > 0) otpRefs.current[i - 1]?.focus();
  };

  const rd = ROLES.find(r => r.id === role);
  const str = pwdStr(newPwd);
  const strColor = ["", "bg-red-500", "bg-amber-400", "bg-amber-500", "bg-green-500"][str] || "bg-green-600";
  const strLabel = ["", "Sangat Lemah", "Lemah", "Sedang", "Kuat"][str] || "Sangat Kuat";

  // Portal selector
  if (step === "portal") return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50/20 to-slate-100 flex flex-col">
      <header className="flex items-center gap-3 px-6 py-5 border-b border-slate-200/60 bg-white/80 backdrop-blur-sm">
        <div className="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center shadow-sm shadow-blue-200">
          <School className="w-4.5 h-4.5 text-white" />
        </div>
        <div>
          <p className="font-bold text-slate-900 text-sm leading-tight">SMP IP YAKIN</p>
          <p className="text-xs text-slate-500 leading-tight">Sistem Manajemen Sekolah</p>
        </div>
      </header>
      <main className="flex-1 flex flex-col items-center justify-center px-4 py-10">
        <div className="w-full max-w-[560px]">
          <div className="text-center mb-8">
            <div className="w-16 h-16 rounded-2xl bg-blue-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-200/60">
              <School className="w-8 h-8 text-white" />
            </div>
            <h1 className="text-2xl font-bold text-slate-900 mb-1">Selamat Datang</h1>
            <p className="text-slate-500 text-sm">Pilih portal sesuai peran Anda untuk melanjutkan</p>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-5">
            {ROLES.map(r => {
              const Ic = r.icon; const sel = role === r.id;
              return (
                <motion.button key={r.id} whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.97 }} onClick={() => setRole(r.id)}
                  className={cn("flex flex-col items-center gap-2 p-3.5 rounded-xl border-2 text-center transition-all",
                    sel ? "border-blue-600 bg-blue-50 shadow-sm" : "border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm")}>
                  <div className={cn("w-10 h-10 rounded-xl flex items-center justify-center", r.color)}>
                    <Ic className="w-5 h-5" />
                  </div>
                  <div>
                    <p className={cn("text-xs font-semibold leading-tight", sel ? "text-blue-700" : "text-slate-800")}>{r.label}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5">{r.desc}</p>
                  </div>
                  {sel && <div className="w-5 h-5 rounded-full bg-blue-600 flex items-center justify-center">
                    <CheckCircle className="w-3 h-3 text-white" />
                  </div>}
                </motion.button>
              );
            })}
          </div>

          <Btn v="primary" sz="lg" full disabled={!role} onClick={() => setStep("login")} iconR={<ArrowRight className="w-4 h-4" />}>
            Lanjutkan ke Login
          </Btn>
          <p className="text-xs text-center text-slate-400 mt-5">© 2025 SMP IP YAKIN — Jl. Pendidikan No. 1, Jakarta Selatan</p>
        </div>
      </main>
    </div>
  );

  // Login
  if (step === "login") {
    const Ic = rd?.icon || Shield;
    return (
      <div className="min-h-screen flex">
        <div className="hidden lg:flex flex-col w-[400px] bg-blue-600 text-white p-10 flex-shrink-0 relative overflow-hidden">
          <div className="absolute inset-0 opacity-10">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="absolute rounded-full border border-white/30" style={{ width: `${(i + 1) * 140}px`, height: `${(i + 1) * 140}px`, top: "50%", left: "50%", transform: "translate(-50%, -50%)" }} />
            ))}
          </div>
          <div className="relative flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center"><School className="w-4.5 h-4.5" /></div>
            <div><p className="font-bold text-sm">SMP IP YAKIN</p><p className="text-xs text-blue-200">Sistem Manajemen Sekolah</p></div>
          </div>
          <div className="relative my-auto">
            <h2 className="text-3xl font-bold mb-4 leading-tight">Platform<br />Pendidikan<br />Terintegrasi</h2>
            <p className="text-blue-200 text-sm leading-relaxed mb-8">Kelola seluruh aktivitas akademik, kehadiran, nilai, dan administrasi sekolah dalam satu platform.</p>
            <div className="grid grid-cols-2 gap-3">
              {[["1,247", "Siswa Aktif"], ["89", "Guru & Staf"], ["36", "Kelas"], ["94.7%", "Kehadiran"]].map(([v, l]) => (
                <div key={l} className="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                  <p className="text-2xl font-bold">{v}</p>
                  <p className="text-xs text-blue-200 mt-0.5">{l}</p>
                </div>
              ))}
            </div>
          </div>
          <p className="relative text-xs text-blue-300">© 2025 SMP IP YAKIN. All rights reserved.</p>
        </div>

        <div className="flex-1 flex items-center justify-center bg-slate-50 p-6">
          <div className="w-full max-w-md">
            <div className="flex items-center gap-3 mb-8 lg:hidden">
              <div className="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center"><School className="w-4 h-4 text-white" /></div>
              <p className="font-bold text-slate-900 text-sm">SMP IP YAKIN</p>
            </div>
            <button onClick={() => setStep("portal")} className="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 mb-6 transition-colors">
              <ChevronLeft className="w-4 h-4" /> Ganti Portal
            </button>
            <div className="flex items-center gap-2.5 mb-4">
              <div className={cn("w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0", rd?.color)}>
                <Ic className="w-4 h-4" />
              </div>
              <Bdg v="info">{rd?.label}</Bdg>
            </div>
            <h1 className="text-xl font-bold text-slate-900 mb-1">Masuk ke Akun</h1>
            <p className="text-sm text-slate-500 mb-6">Gunakan email atau username sekolah Anda</p>
            <div className="space-y-4">
              <Inp label="Email / Username" type="email" ph="nama@smpipyakin.sch.id" val={email} onChange={setEmail} err={errors.email} icon={<Mail className="w-4 h-4" />} req />
              <div className="flex flex-col gap-1">
                <label className="text-sm font-medium text-slate-700">Password <span className="text-red-500">*</span></label>
                <div className="relative">
                  <Lock className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input type={showPwd ? "text" : "password"} placeholder="Masukkan password" value={password} onChange={e => setPassword(e.target.value)}
                    className={cn("w-full h-9 rounded-lg border text-sm pl-9 pr-10 bg-white placeholder:text-slate-400 focus:outline-none focus:ring-2 transition-all",
                      errors.pwd ? "border-red-400 focus:border-red-500 focus:ring-red-100" : "border-slate-300 hover:border-slate-400 focus:border-blue-500 focus:ring-blue-100")} />
                  <button type="button" onClick={() => setShowPwd(!showPwd)} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                    {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
                {errors.pwd && <p className="text-xs text-red-600 flex items-center gap-1"><AlertCircle className="w-3 h-3" />{errors.pwd}</p>}
              </div>
              <div className="flex items-center justify-between">
                <label className="flex items-center gap-2 cursor-pointer select-none">
                  <input type="checkbox" checked={remember} onChange={e => setRemember(e.target.checked)} className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                  <span className="text-sm text-slate-600">Ingat saya</span>
                </label>
                <button type="button" onClick={() => setStep("fg-email")} className="text-sm text-blue-600 hover:text-blue-700 font-medium">Lupa password?</button>
              </div>
              <Btn v="primary" sz="lg" full loading={loading} onClick={doLogin}>{loading ? "Memverifikasi..." : "Masuk"}</Btn>
              <p className="text-xs text-center text-slate-400">Butuh bantuan? Hubungi <span className="text-blue-600 cursor-pointer hover:underline">IT Support</span></p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Forgot - email
  if (step === "fg-email") return (
    <div className="min-h-screen bg-slate-50 flex items-center justify-center p-6">
      <div className="w-full max-w-md">
        <button onClick={() => setStep("login")} className="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 mb-8 transition-colors">
          <ChevronLeft className="w-4 h-4" /> Kembali ke Login
        </button>
        <div className="text-center mb-6">
          <div className="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center mx-auto mb-4"><Mail className="w-6 h-6 text-blue-600" /></div>
          <h1 className="text-xl font-bold text-slate-900">Lupa Password?</h1>
          <p className="text-sm text-slate-500 mt-1.5">Masukkan email terdaftar untuk mendapatkan kode OTP</p>
        </div>
        <Inp label="Email Terdaftar" type="email" ph="nama@smpipyakin.sch.id" val={email} onChange={setEmail} icon={<Mail className="w-4 h-4" />} req />
        <Btn v="primary" sz="lg" full className="mt-4" onClick={() => { setStep("fg-otp"); setTimer(60); setOtp(["", "", "", "", "", ""]); }} iconR={<ArrowRight className="w-4 h-4" />}>
          Kirim Kode OTP
        </Btn>
      </div>
    </div>
  );

  // Forgot - OTP
  if (step === "fg-otp") return (
    <div className="min-h-screen bg-slate-50 flex items-center justify-center p-6">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <div className="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center mx-auto mb-4"><Shield className="w-6 h-6 text-blue-600" /></div>
          <h1 className="text-xl font-bold text-slate-900">Verifikasi OTP</h1>
          <p className="text-sm text-slate-500 mt-1.5">Kode OTP dikirim ke <strong className="text-slate-700">{email}</strong></p>
        </div>
        <div className="flex gap-2 justify-center mb-5">
          {otp.map((d, i) => (
            <input key={i} ref={el => { otpRefs.current[i] = el; }} type="text" inputMode="numeric" maxLength={1} value={d}
              onChange={e => doOtp(i, e.target.value)}
              onKeyDown={e => e.key === "Backspace" && !d && i > 0 && otpRefs.current[i - 1]?.focus()}
              className={cn("w-11 h-12 text-center text-lg font-bold rounded-xl border-2 transition-all focus:outline-none",
                d ? "border-blue-600 bg-blue-50 text-blue-700" : "border-slate-300 focus:border-blue-500")} />
          ))}
        </div>
        <div className="text-center mb-6">
          {timer > 0
            ? <p className="text-sm text-slate-500">Kirim ulang dalam <span className="font-semibold text-blue-600">{timer}s</span></p>
            : <button onClick={() => setTimer(60)} className="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1 mx-auto"><RefreshCw className="w-3 h-3" /> Kirim Ulang</button>}
        </div>
        <Btn v="primary" sz="lg" full disabled={otp.some(d => !d)} onClick={() => setStep("fg-reset")}>Verifikasi</Btn>
        <button onClick={() => setStep("fg-email")} className="w-full mt-2 text-sm text-slate-500 hover:text-slate-700 flex items-center justify-center gap-1 py-2">
          <ChevronLeft className="w-3.5 h-3.5" /> Kembali
        </button>
      </div>
    </div>
  );

  // Forgot - reset
  if (step === "fg-reset") return (
    <div className="min-h-screen bg-slate-50 flex items-center justify-center p-6">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <div className="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center mx-auto mb-4"><Lock className="w-6 h-6 text-blue-600" /></div>
          <h1 className="text-xl font-bold text-slate-900">Buat Password Baru</h1>
          <p className="text-sm text-slate-500 mt-1.5">Password minimal 8 karakter dengan kombinasi huruf dan angka</p>
        </div>
        <div className="space-y-4">
          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-slate-700">Password Baru <span className="text-red-500">*</span></label>
            <div className="relative">
              <Lock className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input type={showPwd ? "text" : "password"} placeholder="Min. 8 karakter" value={newPwd} onChange={e => setNewPwd(e.target.value)}
                className="w-full h-9 rounded-lg border border-slate-300 text-sm pl-9 pr-10 bg-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 hover:border-slate-400 transition-all" />
              <button type="button" onClick={() => setShowPwd(!showPwd)} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
                {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
            {newPwd && (
              <div className="mt-1">
                <div className="flex gap-1 mb-1">{[1, 2, 3, 4].map(i => <div key={i} className={cn("h-1 flex-1 rounded-full transition-all", i <= str ? strColor : "bg-slate-200")} />)}</div>
                <p className={cn("text-xs", str <= 2 ? "text-red-500" : str === 3 ? "text-amber-500" : "text-green-600")}>{strLabel}</p>
              </div>
            )}
          </div>
          <Inp label="Konfirmasi Password" type="password" ph="Ulangi password baru" val={confirmPwd} onChange={setConfirmPwd}
            icon={<Lock className="w-4 h-4" />} err={confirmPwd && confirmPwd !== newPwd ? "Password tidak cocok" : undefined} />
        </div>
        <Btn v="primary" sz="lg" full className="mt-6" disabled={!newPwd || newPwd !== confirmPwd} onClick={() => setStep("fg-done")}>
          Simpan Password Baru
        </Btn>
      </div>
    </div>
  );

  // Forgot - done
  return (
    <div className="min-h-screen bg-slate-50 flex items-center justify-center p-6">
      <div className="w-full max-w-sm text-center">
        <motion.div initial={{ scale: 0 }} animate={{ scale: 1 }} transition={{ type: "spring", stiffness: 200, damping: 15 }}
          className="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-5">
          <CheckCircle className="w-8 h-8 text-green-600" />
        </motion.div>
        <h1 className="text-xl font-bold text-slate-900 mb-2">Password Berhasil Diubah!</h1>
        <p className="text-slate-500 text-sm mb-6">Silakan masuk menggunakan password baru Anda.</p>
        <Btn v="primary" sz="lg" full onClick={() => setStep("login")}>Kembali ke Login</Btn>
      </div>
    </div>
  );
}

// ─── Sidebar ──────────────────────────────────────────────────────────────────
function Sidebar({ page, nav, coll, onToggle, user, onLogout, mobileOpen, onCloseMobile }: {
  page: Page; nav: (p: Page) => void; coll: boolean; onToggle: () => void;
  user: AppUser; onLogout: () => void; mobileOpen: boolean; onCloseMobile: () => void;
}) {
  const body = (
    <div className={cn("flex flex-col h-full bg-slate-900 transition-[width] duration-300 ease-in-out overflow-hidden", coll ? "w-16" : "w-60")}>
      {/* Logo */}
      <div className={cn("flex items-center gap-3 h-14 px-4 border-b border-white/5 flex-shrink-0", coll && "justify-center px-0")}>
        <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center flex-shrink-0 shadow-sm">
          <School className="w-4 h-4 text-white" />
        </div>
        {!coll && (
          <div className="min-w-0">
            <p className="font-bold text-white text-sm truncate leading-tight">SMP IP YAKIN</p>
            <p className="text-[11px] text-slate-500 truncate">Manajemen Sekolah</p>
          </div>
        )}
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto py-2">
        {NAV_GROUPS.map(({ group, items }) => (
          <div key={group} className="mb-1">
            {!coll && <p className="text-[10px] font-semibold text-slate-600 uppercase tracking-widest px-4 pt-4 pb-1.5">{group}</p>}
            {coll && group !== "Utama" && <div className="h-px bg-white/5 mx-3 my-2" />}
            {items.map(item => {
              const Ic = item.icon; const active = page === item.id;
              return (
                <button key={item.id} onClick={() => { nav(item.id); onCloseMobile(); }} title={coll ? item.label : undefined}
                  className={cn("w-full flex items-center gap-2.5 text-sm transition-all duration-100 relative group",
                    coll ? "justify-center py-3 px-0" : "px-4 py-2",
                    active ? "text-blue-400 bg-blue-500/10" : "text-slate-400 hover:text-white hover:bg-white/5")}>
                  {active && <span className="absolute left-0 top-0 bottom-0 w-0.5 bg-blue-500 rounded-r" />}
                  <Ic className={cn("w-4 h-4 flex-shrink-0", active && "text-blue-400")} />
                  {!coll && <span className="truncate text-sm">{item.label}</span>}
                </button>
              );
            })}
          </div>
        ))}
      </nav>

      {/* User */}
      <div className="border-t border-white/5 flex-shrink-0">
        <div className={cn("p-3 flex items-center gap-2.5", coll && "justify-center")}>
          <Av name={user.name} sz="sm" className="flex-shrink-0" />
          {!coll && (
            <>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold text-slate-200 truncate leading-tight">{user.name.split(",")[0]}</p>
                <p className="text-[11px] text-slate-500 truncate">{ROLE_LABELS[user.role]}</p>
              </div>
              <button onClick={onLogout} className="text-slate-500 hover:text-red-400 transition-colors flex-shrink-0 p-1">
                <LogOut className="w-3.5 h-3.5" />
              </button>
            </>
          )}
        </div>
        <button onClick={onToggle}
          className="hidden lg:flex w-full items-center justify-center h-7 border-t border-white/5 text-slate-600 hover:text-slate-300 hover:bg-white/5 transition-colors text-xs gap-1">
          {coll ? <ChevronRight className="w-3.5 h-3.5" /> : <><ChevronLeft className="w-3 h-3" /><span>Collapse</span></>}
        </button>
      </div>
    </div>
  );

  return (
    <>
      <div className="hidden lg:flex flex-shrink-0">{body}</div>
      <AnimatePresence>
        {mobileOpen && (
          <>
            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
              onClick={onCloseMobile} className="fixed inset-0 bg-black/60 z-40 lg:hidden" />
            <motion.div initial={{ x: -256 }} animate={{ x: 0 }} exit={{ x: -256 }}
              transition={{ type: "spring", damping: 28, stiffness: 280 }}
              className="fixed left-0 top-0 bottom-0 z-50 lg:hidden">
              {body}
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </>
  );
}

// ─── Topbar ───────────────────────────────────────────────────────────────────
function Topbar({ user, page, onOpenMobile, nav, onLogout }: {
  user: AppUser; page: Page; onOpenMobile: () => void; nav: (p: Page) => void; onLogout: () => void;
}) {
  const [notifOpen, setNotifOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [search, setSearch] = useState("");

  const currentLabel = NAV_GROUPS.flatMap(g => g.items).find(n => n.id === page)?.label || "Dashboard";
  const notifs = [
    { id: 1, title: "Pengumuman Rapat", msg: "Rapat dewan guru besok pukul 09.00", time: "5m lalu", read: false },
    { id: 2, title: "Absensi Belum", msg: "3 siswa belum absen hari ini", time: "1j lalu", read: false },
    { id: 3, title: "Input Nilai", msg: "Batas input nilai UTS besok", time: "3j lalu", read: true },
  ];
  const unread = notifs.filter(n => !n.read).length;

  const close = () => { setNotifOpen(false); setProfileOpen(false); };

  return (
    <header className="h-14 bg-white border-b border-slate-200 flex items-center px-4 gap-3 sticky top-0 z-30 flex-shrink-0">
      <button onClick={onOpenMobile} className="lg:hidden w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-600 transition-colors">
        <Menu className="w-5 h-5" />
      </button>
      <div className="hidden md:flex items-center gap-1.5 text-sm">
        <span className="text-slate-400 text-xs">SMS</span>
        <ChevronRight className="w-3 h-3 text-slate-300" />
        <span className="text-slate-700 font-medium text-sm">{currentLabel}</span>
      </div>
      <div className="flex-1" />
      {/* Search */}
      <div className="relative hidden md:block">
        <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input type="text" placeholder="Cari siswa, guru..." value={search} onChange={e => setSearch(e.target.value)}
          className="w-52 h-8 pl-8 pr-3 text-sm rounded-lg border border-slate-200 bg-slate-50 hover:border-slate-300 focus:outline-none focus:border-blue-500 focus:bg-white transition-all" />
      </div>
      {/* Notif */}
      <div className="relative">
        <button onClick={() => { setNotifOpen(!notifOpen); setProfileOpen(false); }}
          className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-600 transition-colors relative">
          <Bell className="w-4 h-4" />
          {unread > 0 && <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border border-white" />}
        </button>
        <AnimatePresence>
          {notifOpen && (
            <motion.div initial={{ opacity: 0, y: 6, scale: 0.97 }} animate={{ opacity: 1, y: 0, scale: 1 }} exit={{ opacity: 0, y: 6, scale: 0.97 }} transition={{ duration: 0.12 }}
              className="absolute right-0 top-10 w-72 bg-white rounded-xl border border-slate-200 shadow-xl z-50 overflow-hidden">
              <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                <p className="text-sm font-semibold text-slate-900">Notifikasi</p>
                <Bdg v="danger">{unread} baru</Bdg>
              </div>
              {notifs.map(n => (
                <div key={n.id} className={cn("flex items-start gap-3 px-4 py-3 border-b border-slate-50 hover:bg-slate-50 transition-colors cursor-pointer", !n.read && "bg-blue-50/40")}>
                  <div className={cn("w-2 h-2 rounded-full mt-1.5 flex-shrink-0", n.read ? "bg-slate-200" : "bg-blue-500")} />
                  <div>
                    <p className="text-xs font-semibold text-slate-800">{n.title}</p>
                    <p className="text-xs text-slate-500 mt-0.5">{n.msg}</p>
                    <p className="text-xs text-slate-400 mt-1">{n.time}</p>
                  </div>
                </div>
              ))}
              <button className="w-full text-xs text-blue-600 hover:text-blue-700 py-3 font-medium">Lihat semua notifikasi</button>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
      {/* Profile */}
      <div className="relative">
        <button onClick={() => { setProfileOpen(!profileOpen); setNotifOpen(false); }}
          className="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-100 transition-colors">
          <Av name={user.name} sz="sm" />
          <div className="hidden sm:block text-left">
            <p className="text-xs font-semibold text-slate-800 leading-tight">{user.name.split(",")[0].split(" ").slice(0, 2).join(" ")}</p>
            <p className="text-[11px] text-slate-400 leading-tight">{ROLE_LABELS[user.role]}</p>
          </div>
          <ChevronDown className="w-3 h-3 text-slate-400 hidden sm:block" />
        </button>
        <AnimatePresence>
          {profileOpen && (
            <motion.div initial={{ opacity: 0, y: 6, scale: 0.97 }} animate={{ opacity: 1, y: 0, scale: 1 }} exit={{ opacity: 0, y: 6, scale: 0.97 }} transition={{ duration: 0.12 }}
              className="absolute right-0 top-10 w-52 bg-white rounded-xl border border-slate-200 shadow-xl z-50 overflow-hidden">
              <div className="px-4 py-3 border-b border-slate-100">
                <p className="text-sm font-semibold text-slate-800 truncate">{user.name}</p>
                <p className="text-xs text-slate-500 truncate mt-0.5">{user.email}</p>
                <Bdg v="info" className="mt-2">{ROLE_LABELS[user.role]}</Bdg>
              </div>
              <div className="py-1">
                {[{ l: "Profil Saya", i: User, p: "profile" as Page }, { l: "Pengaturan", i: Settings, p: "settings" as Page }].map(it => (
                  <button key={it.l} onClick={() => { nav(it.p); close(); }}
                    className="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                    <it.i className="w-4 h-4 text-slate-400" />{it.l}
                  </button>
                ))}
              </div>
              <div className="border-t border-slate-100 py-1">
                <button onClick={onLogout} className="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                  <LogOut className="w-4 h-4" />Keluar
                </button>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </header>
  );
}

// ─── Bottom Nav ───────────────────────────────────────────────────────────────
function BottomNav({ page, nav }: { page: Page; nav: (p: Page) => void }) {
  const items = [
    { id: "dashboard" as Page, label: "Beranda", icon: LayoutDashboard },
    { id: "students" as Page, label: "Siswa", icon: GraduationCap },
    { id: "attendance" as Page, label: "Absensi", icon: ClipboardList },
    { id: "reports" as Page, label: "Laporan", icon: BarChart2 },
    { id: "profile" as Page, label: "Profil", icon: User },
  ];
  return (
    <nav className="lg:hidden fixed bottom-0 left-0 right-0 z-30 bg-white border-t border-slate-200 flex safe-area-inset-bottom">
      {items.map(it => {
        const Ic = it.icon; const active = page === it.id;
        return (
          <button key={it.id} onClick={() => nav(it.id)}
            className={cn("flex-1 flex flex-col items-center justify-center py-2 gap-0.5 transition-colors",
              active ? "text-blue-600" : "text-slate-400 hover:text-slate-600")}>
            <Ic className="w-5 h-5" />
            <span className="text-[10px] font-medium">{it.label}</span>
          </button>
        );
      })}
    </nav>
  );
}

// ─── Page: Dashboard ──────────────────────────────────────────────────────────
function DashboardPage() {
  return (
    <div className="p-4 md:p-6 space-y-5 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-lg font-bold text-slate-900">Dashboard</h1>
          <p className="text-sm text-slate-500 mt-0.5">Ringkasan aktivitas sekolah — Senin, 13 Juli 2026</p>
        </div>
        <div className="flex gap-2">
          <Btn v="outline" sz="sm" icon={<Download className="w-3.5 h-3.5" />}>Export</Btn>
          <Btn v="primary" sz="sm" icon={<Plus className="w-3.5 h-3.5" />}>Tambah Data</Btn>
        </div>
      </div>

      <div className="grid grid-cols-2 xl:grid-cols-4 gap-3 md:gap-4">
        <StatCard title="Total Siswa" value="1,247" change="+3.2%" icon={GraduationCap} bg="bg-blue-100 text-blue-600" />
        <StatCard title="Guru & Staf" value="89" change="+1.1%" icon={Users} bg="bg-emerald-100 text-emerald-600" />
        <StatCard title="Kelas Aktif" value="36" icon={BookOpen} bg="bg-violet-100 text-violet-600" />
        <StatCard title="Kehadiran" value="94.7%" change="+0.5%" icon={ClipboardList} bg="bg-amber-100 text-amber-600" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <Crd className="lg:col-span-2" np>
          <div className="px-5 pt-5 pb-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 className="text-sm font-semibold text-slate-900">Tren Kehadiran</h3>
              <p className="text-xs text-slate-500 mt-0.5">6 bulan terakhir (%)</p>
            </div>
            <Bdg v="success">Rata-rata 93.3%</Bdg>
          </div>
          <div className="p-5">
            <ResponsiveContainer width="100%" height={180}>
              <AreaChart data={ATTEND_CHART} margin={{ top: 5, right: 5, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id="gb" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#3B82F6" stopOpacity={0.15} />
                    <stop offset="95%" stopColor="#3B82F6" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" vertical={false} />
                <XAxis dataKey="month" tick={{ fontSize: 11, fill: "#94a3b8" }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11, fill: "#94a3b8" }} axisLine={false} tickLine={false} domain={[85, 100]} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8, border: "1px solid #e2e8f0", boxShadow: "0 4px 6px -1px rgb(0 0 0/0.08)" }} />
                <Area type="monotone" dataKey="hadir" stroke="#3B82F6" strokeWidth={2} fill="url(#gb)" name="Kehadiran %"
                  dot={{ fill: "#3B82F6", strokeWidth: 0, r: 3 }} activeDot={{ r: 5, strokeWidth: 0 }} />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </Crd>

      </div>

      {/* Quick stats row */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {[
          { label: "Pengumuman Aktif", value: "4", sub: "3 belum dibaca", icon: Bell, color: "bg-blue-50 text-blue-600" },
          { label: "Tugas Tertunda", value: "12", sub: "Deadline minggu ini", icon: Clock, color: "bg-amber-50 text-amber-600" },
          { label: "Laporan Bulan Ini", value: "7", sub: "3 menunggu approval", icon: Filter, color: "bg-violet-50 text-violet-600" },
        ].map(s => {
          const Ic = s.icon;
          return (
            <Crd key={s.label}>
              <div className="flex items-center gap-3">
                <div className={cn("w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0", s.color)}><Ic className="w-5 h-5" /></div>
                <div>
                  <p className="text-xs text-slate-500">{s.label}</p>
                  <p className="text-lg font-bold text-slate-900 leading-tight">{s.value}</p>
                  <p className="text-xs text-slate-400 mt-0.5">{s.sub}</p>
                </div>
              </div>
            </Crd>
          );
        })}
      </div>

      {/* Activity table */}
      <Crd np>
        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h3 className="text-sm font-semibold text-slate-900">Aktivitas Kehadiran Hari Ini</h3>
            <p className="text-xs text-slate-500 mt-0.5">Update terakhir 08:45 WIB</p>
          </div>
          <Btn v="ghost" sz="sm">Lihat Semua</Btn>
        </div>
        <div className="hidden md:block overflow-x-auto">
          <table className="w-full">
            <thead className="bg-slate-50/60">
              <tr>{["Siswa", "Kelas", "Aktivitas", "Waktu", "Status"].map(h => (
                <th key={h} className="text-left text-xs font-semibold text-slate-500 px-5 py-3">{h}</th>
              ))}</tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {RECENT_ACT.map(row => (
                <tr key={row.id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="px-5 py-3"><div className="flex items-center gap-2.5"><Av name={row.student} sz="sm" /><span className="text-sm font-medium text-slate-800">{row.student}</span></div></td>
                  <td className="px-5 py-3"><Bdg v="outline">{row.cls}</Bdg></td>
                  <td className="px-5 py-3"><span className="text-sm text-slate-600">{row.action}</span></td>
                  <td className="px-5 py-3"><span className="text-sm text-slate-500">{row.time}</span></td>
                  <td className="px-5 py-3"><StBdg st={row.st} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="md:hidden divide-y divide-slate-100">
          {RECENT_ACT.map(row => (
            <div key={row.id} className="px-4 py-3 flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <Av name={row.student} sz="sm" />
                <div><p className="text-sm font-medium text-slate-800">{row.student}</p><p className="text-xs text-slate-500">{row.cls} · {row.time}</p></div>
              </div>
              <StBdg st={row.st} />
            </div>
          ))}
        </div>
      </Crd>
    </div>
  );
}

// ─── Page: Students ───────────────────────────────────────────────────────────
function StudentsPage() {
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState("all");
  const filtered = STUDENTS_DATA.filter(s =>
    (filter === "all" || s.cls.startsWith(filter)) &&
    (s.name.toLowerCase().includes(search.toLowerCase()) || s.nisn.includes(search))
  );

  return (
    <div className="p-4 md:p-6 space-y-5 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-lg font-bold text-slate-900">Data Siswa</h1>
          <p className="text-sm text-slate-500 mt-0.5">{STUDENTS_DATA.length} siswa terdaftar</p>
        </div>
        <div className="flex gap-2">
          <Btn v="outline" sz="sm" icon={<Download className="w-3.5 h-3.5" />}>Export</Btn>
          <Btn v="primary" sz="sm" icon={<Plus className="w-3.5 h-3.5" />}>Tambah Siswa</Btn>
        </div>
      </div>

      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
          <input type="text" placeholder="Cari nama atau NISN..." value={search} onChange={e => setSearch(e.target.value)}
            className="w-full h-9 pl-8 pr-3 text-sm rounded-lg border border-slate-300 bg-white hover:border-slate-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all" />
        </div>
        <div className="flex gap-1.5">
          {["all", "7", "8", "9"].map(f => (
            <button key={f} onClick={() => setFilter(f)}
              className={cn("px-3 py-1.5 text-xs font-medium rounded-lg border transition-all",
                filter === f ? "bg-blue-600 border-blue-600 text-white shadow-sm" : "border-slate-300 text-slate-600 hover:border-slate-400 bg-white")}>
              {f === "all" ? "Semua" : `Kelas ${f}`}
            </button>
          ))}
        </div>
      </div>

      <Crd np className="hidden md:block">
        <table className="w-full">
          <thead className="bg-slate-50 border-b border-slate-200">
            <tr>{["#", "Nama Siswa", "NISN", "Kelas", "Gender", "Kehadiran", "NR", "Status", "Aksi"].map(h => (
              <th key={h} className="text-left text-xs font-semibold text-slate-500 px-4 py-3">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {filtered.map((s, i) => (
              <tr key={s.id} className="hover:bg-slate-50/60 transition-colors">
                <td className="px-4 py-3 text-sm text-slate-400">{i + 1}</td>
                <td className="px-4 py-3"><div className="flex items-center gap-2.5"><Av name={s.name} sz="sm" /><span className="text-sm font-medium text-slate-800">{s.name}</span></div></td>
                <td className="px-4 py-3"><span className="text-sm text-slate-500 font-mono text-xs">{s.nisn}</span></td>
                <td className="px-4 py-3"><Bdg v="outline">{s.cls}</Bdg></td>
                <td className="px-4 py-3"><span className="text-sm text-slate-600">{s.gender === "L" ? "L" : "P"}</span></td>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-2">
                    <div className="w-14 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                      <div className={cn("h-full rounded-full", s.pct >= 90 ? "bg-green-500" : s.pct >= 75 ? "bg-amber-500" : "bg-red-500")} style={{ width: `${s.pct}%` }} />
                    </div>
                    <span className="text-xs text-slate-600">{s.pct}%</span>
                  </div>
                </td>
                <td className="px-4 py-3"><span className={cn("text-sm font-semibold", s.gpa >= 85 ? "text-green-600" : s.gpa >= 70 ? "text-amber-600" : "text-red-600")}>{s.gpa}</span></td>
                <td className="px-4 py-3"><StBdg st={s.status} /></td>
                <td className="px-4 py-3">
                  <div className="flex gap-0.5">
                    <button className="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors"><Edit2 className="w-3.5 h-3.5" /></button>
                    <button className="w-7 h-7 flex items-center justify-center rounded hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors"><Trash2 className="w-3.5 h-3.5" /></button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && (
          <div className="text-center py-14">
            <GraduationCap className="w-10 h-10 text-slate-300 mx-auto mb-3" />
            <p className="text-slate-400 text-sm">Tidak ada siswa yang ditemukan</p>
          </div>
        )}
      </Crd>

      <div className="md:hidden space-y-3">
        {filtered.map(s => (
          <Crd key={s.id}>
            <div className="flex items-start justify-between gap-3">
              <div className="flex items-center gap-3">
                <Av name={s.name} sz="md" />
                <div>
                  <p className="text-sm font-semibold text-slate-900">{s.name}</p>
                  <p className="text-xs text-slate-500 font-mono mt-0.5">{s.nisn}</p>
                  <div className="flex gap-1.5 mt-1.5"><Bdg v="outline">{s.cls}</Bdg><StBdg st={s.status} /></div>
                </div>
              </div>
              <div className="text-right flex-shrink-0">
                <p className={cn("text-xl font-bold", s.gpa >= 85 ? "text-green-600" : s.gpa >= 70 ? "text-amber-600" : "text-red-600")}>{s.gpa}</p>
                <p className="text-xs text-slate-400">nilai rata</p>
              </div>
            </div>
            <div className="mt-3 pt-3 border-t border-slate-100">
              <div className="flex items-center gap-2">
                <span className="text-xs text-slate-500">Kehadiran</span>
                <div className="flex-1 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                  <div className={cn("h-full rounded-full", s.pct >= 90 ? "bg-green-500" : s.pct >= 75 ? "bg-amber-500" : "bg-red-500")} style={{ width: `${s.pct}%` }} />
                </div>
                <span className="text-xs font-medium text-slate-700">{s.pct}%</span>
              </div>
            </div>
          </Crd>
        ))}
      </div>

      <div className="flex items-center justify-between pb-20 lg:pb-0">
        <p className="text-xs text-slate-500">Menampilkan {filtered.length} dari {STUDENTS_DATA.length} siswa</p>
        <div className="flex gap-1">
          {[1, 2, 3].map(p => (
            <button key={p} className={cn("w-7 h-7 text-xs rounded-lg transition-all",
              p === 1 ? "bg-blue-600 text-white shadow-sm" : "border border-slate-300 text-slate-600 hover:bg-slate-50 bg-white")}>
              {p}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

// ─── Page: Attendance ─────────────────────────────────────────────────────────
function AttendancePage() {
  const data = [
    { name: "Andi Pratama", cls: "9A", date: "13/07/2026", st: "hadir" },
    { name: "Budi Santoso", cls: "8B", date: "13/07/2026", st: "sakit" },
    { name: "Citra Dewi", cls: "7C", date: "13/07/2026", st: "hadir" },
    { name: "Dina Marlina", cls: "9A", date: "13/07/2026", st: "terlambat" },
    { name: "Eko Prasetyo", cls: "8A", date: "13/07/2026", st: "alpa" },
    { name: "Fitri Handayani", cls: "7A", date: "13/07/2026", st: "izin" },
  ];
  const summary = [
    { l: "Hadir", n: 1031, pct: "82.7%", dot: "bg-green-500", bd: "bg-green-50 border-green-200" },
    { l: "Sakit", n: 89, pct: "7.1%", dot: "bg-amber-500", bd: "bg-amber-50 border-amber-200" },
    { l: "Izin", n: 62, pct: "4.9%", dot: "bg-blue-500", bd: "bg-blue-50 border-blue-200" },
    { l: "Alpa", n: 65, pct: "5.2%", dot: "bg-red-500", bd: "bg-red-50 border-red-200" },
  ];

  return (
    <div className="p-4 md:p-6 space-y-5 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-lg font-bold text-slate-900">Kehadiran</h1>
          <p className="text-sm text-slate-500 mt-0.5">Senin, 13 Juli 2026</p>
        </div>
        <div className="flex gap-2">
          <Btn v="outline" sz="sm" icon={<Download className="w-3.5 h-3.5" />}>Export</Btn>
          <Btn v="primary" sz="sm" icon={<Plus className="w-3.5 h-3.5" />}>Input Absen</Btn>
        </div>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        {summary.map(s => (
          <div key={s.l} className={cn("rounded-xl border p-4", s.bd)}>
            <div className="flex items-center gap-2 mb-2"><div className={cn("w-2 h-2 rounded-full", s.dot)} /><p className="text-xs text-slate-600 font-medium">{s.l}</p></div>
            <p className="text-2xl font-bold text-slate-900">{s.n}</p>
            <p className="text-xs text-slate-500 mt-0.5">{s.pct} dari total</p>
          </div>
        ))}
      </div>

      <Crd np>
        <div className="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row gap-3 sm:items-center">
          <div className="relative flex-1">
            <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input placeholder="Cari siswa..." className="w-full h-8 pl-8 pr-3 text-sm rounded-lg border border-slate-300 bg-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
          </div>
          <div className="flex gap-2">
            {["Semua Kelas", "Kelas 7", "Kelas 8", "Kelas 9"].map(o => (
              <button key={o} className={cn("px-2.5 py-1.5 text-xs rounded-lg border transition-all",
                o === "Semua Kelas" ? "bg-blue-600 border-blue-600 text-white" : "border-slate-300 text-slate-600 bg-white hover:bg-slate-50")}>
                {o.replace("Semua ", "Semua").replace("Kelas ", "K")}
              </button>
            ))}
          </div>
        </div>
        <div className="hidden md:block overflow-x-auto">
          <table className="w-full">
            <thead className="bg-slate-50 border-b border-slate-200">
              <tr>{["Nama Siswa", "Kelas", "Tanggal", "Status", "Keterangan", "Aksi"].map(h => (
                <th key={h} className="text-left text-xs font-semibold text-slate-500 px-5 py-3">{h}</th>
              ))}</tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.map((row, i) => (
                <tr key={i} className="hover:bg-slate-50/60">
                  <td className="px-5 py-3"><div className="flex items-center gap-2.5"><Av name={row.name} sz="sm" /><span className="text-sm font-medium text-slate-800">{row.name}</span></div></td>
                  <td className="px-5 py-3"><Bdg v="outline">{row.cls}</Bdg></td>
                  <td className="px-5 py-3"><span className="text-sm text-slate-600">{row.date}</span></td>
                  <td className="px-5 py-3"><StBdg st={row.st} /></td>
                  <td className="px-5 py-3"><span className="text-sm text-slate-400">—</span></td>
                  <td className="px-5 py-3"><button className="text-xs text-blue-600 hover:text-blue-700 font-medium">Edit</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="md:hidden divide-y divide-slate-100">
          {data.map((row, i) => (
            <div key={i} className="px-4 py-3 flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <Av name={row.name} sz="sm" />
                <div><p className="text-sm font-medium text-slate-800">{row.name}</p><p className="text-xs text-slate-500">{row.cls} · {row.date}</p></div>
              </div>
              <StBdg st={row.st} />
            </div>
          ))}
        </div>
      </Crd>
    </div>
  );
}

// ─── Page: Grades ─────────────────────────────────────────────────────────────
function GradesPage() {
  const rows = [
    { name: "Andi Pratama", cls: "9A", subj: "Matematika", uh1: 85, uh2: 88, uts: 90, uas: 87 },
    { name: "Budi Santoso", cls: "8B", subj: "B. Indonesia", uh1: 72, uh2: 75, uts: 78, uas: 74 },
    { name: "Citra Dewi", cls: "7C", subj: "IPA", uh1: 92, uh2: 90, uts: 95, uas: 91 },
    { name: "Dina Marlina", cls: "9A", subj: "B. Inggris", uh1: 95, uh2: 93, uts: 97, uas: 96 },
    { name: "Eko Prasetyo", cls: "8A", subj: "IPS", uh1: 65, uh2: 68, uts: 62, uas: 67 },
    { name: "Fitri Handayani", cls: "7A", subj: "Matematika", uh1: 80, uh2: 83, uts: 85, uas: 82 },
  ];
  const avg = (r: typeof rows[0]) => ((r.uh1 + r.uh2 + r.uts + r.uas) / 4).toFixed(1);
  const gl = (v: number) => v >= 90 ? "A" : v >= 75 ? "B" : v >= 60 ? "C" : "D";
  const gc = (v: number) => v >= 90 ? "text-green-600" : v >= 75 ? "text-blue-600" : v >= 60 ? "text-amber-600" : "text-red-600";
  const gbv = (v: number): BadgeV => v >= 90 ? "success" : v >= 75 ? "info" : v >= 60 ? "warning" : "danger";

  return (
    <div className="p-4 md:p-6 space-y-5 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-lg font-bold text-slate-900">Nilai Siswa</h1>
          <p className="text-sm text-slate-500 mt-0.5">Semester Genap 2025/2026</p>
        </div>
        <div className="flex gap-2">
          <Btn v="outline" sz="sm" icon={<Download className="w-3.5 h-3.5" />}>Export</Btn>
          <Btn v="primary" sz="sm" icon={<Plus className="w-3.5 h-3.5" />}>Input Nilai</Btn>
        </div>
      </div>

      <div className="flex gap-1.5 flex-wrap">
        {["Semua", "Kelas 7", "Kelas 8", "Kelas 9", "Matematika", "IPA", "IPS"].map((f, i) => (
          <button key={f} className={cn("px-3 py-1.5 text-xs font-medium rounded-lg border transition-all",
            i === 0 ? "bg-blue-600 border-blue-600 text-white" : "border-slate-300 text-slate-600 hover:bg-slate-50 bg-white")}>
            {f}
          </button>
        ))}
      </div>

      <Crd np className="hidden md:block">
        <table className="w-full">
          <thead className="bg-slate-50 border-b border-slate-200">
            <tr>{["Nama Siswa", "Kelas", "Mata Pelajaran", "UH1", "UH2", "UTS", "UAS", "Rata-rata", "Grade"].map(h => (
              <th key={h} className="text-left text-xs font-semibold text-slate-500 px-4 py-3">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {rows.map((g, i) => {
              const a = parseFloat(avg(g));
              return (
                <tr key={i} className="hover:bg-slate-50/60">
                  <td className="px-4 py-3"><div className="flex items-center gap-2.5"><Av name={g.name} sz="sm" /><span className="text-sm font-medium text-slate-800">{g.name}</span></div></td>
                  <td className="px-4 py-3"><Bdg v="outline">{g.cls}</Bdg></td>
                  <td className="px-4 py-3"><span className="text-sm text-slate-600">{g.subj}</span></td>
                  {[g.uh1, g.uh2, g.uts, g.uas].map((v, j) => <td key={j} className="px-4 py-3"><span className={cn("text-sm font-medium", gc(v))}>{v}</span></td>)}
                  <td className="px-4 py-3"><span className={cn("text-sm font-bold", gc(a))}>{a}</span></td>
                  <td className="px-4 py-3"><Bdg v={gbv(a)}>{gl(a)}</Bdg></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </Crd>

      <div className="md:hidden space-y-3 pb-20">
        {rows.map((g, i) => {
          const a = parseFloat(avg(g));
          return (
            <Crd key={i}>
              <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                  <Av name={g.name} sz="md" />
                  <div>
                    <p className="text-sm font-semibold text-slate-900">{g.name}</p>
                    <p className="text-xs text-slate-500 mt-0.5">{g.subj}</p>
                    <Bdg v="outline" className="mt-1.5">{g.cls}</Bdg>
                  </div>
                </div>
                <div className="text-right flex-shrink-0">
                  <p className={cn("text-2xl font-bold", gc(a))}>{gl(a)}</p>
                  <p className={cn("text-sm font-semibold", gc(a))}>{a}</p>
                </div>
              </div>
              <div className="grid grid-cols-4 gap-2 mt-4 pt-4 border-t border-slate-100">
                {[["UH1", g.uh1], ["UH2", g.uh2], ["UTS", g.uts], ["UAS", g.uas]].map(([l, v]) => (
                  <div key={l as string} className="text-center">
                    <p className="text-xs text-slate-400">{l}</p>
                    <p className={cn("text-sm font-semibold mt-0.5", gc(v as number))}>{v}</p>
                  </div>
                ))}
              </div>
            </Crd>
          );
        })}
      </div>
    </div>
  );
}

// ─── Page: Profile ────────────────────────────────────────────────────────────
function ProfilePage({ user }: { user: AppUser }) {
  const [form, setForm] = useState({ name: user.name, nip: "19850312201402001", email: user.email, phone: "081234567890", birth: "1985-03-12", gender: "L", address: "Jl. Merdeka No. 45 RT 03/04", city: "Jakarta Selatan", province: "DKI Jakarta" });
  const [pwd, setPwd] = useState({ cur: "", newp: "", conf: "" });
  const [saving, setSaving] = useState(false);

  const save = async () => {
    setSaving(true);
    await new Promise(r => setTimeout(r, 1000));
    setSaving(false);
    toast.success("Profil berhasil disimpan!", { description: "Perubahan data telah tersimpan." });
  };

  const sp = (p: string) => {
    let s = 0; if (p.length >= 8) s++; if (/[A-Z]/.test(p)) s++; if (/[0-9]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++;
    return s;
  };
  const str = sp(pwd.newp);

  return (
    <div className="p-4 md:p-6 space-y-5 max-w-3xl mx-auto pb-24 lg:pb-6">
      <div>
        <h1 className="text-lg font-bold text-slate-900">Profil Saya</h1>
        <p className="text-sm text-slate-500 mt-0.5">Kelola informasi pribadi dan keamanan akun</p>
      </div>

      <Crd>
        <div className="flex flex-col sm:flex-row items-center sm:items-start gap-5">
          <div className="relative flex-shrink-0">
            <Av name={user.name} sz="xl" />
            <button className="absolute bottom-0 right-0 w-7 h-7 bg-blue-600 rounded-full flex items-center justify-center shadow-md hover:bg-blue-700 transition-colors">
              <Camera className="w-3.5 h-3.5 text-white" />
            </button>
          </div>
          <div className="text-center sm:text-left flex-1">
            <h2 className="text-base font-semibold text-slate-900">{user.name}</h2>
            <p className="text-sm text-slate-500 mt-0.5">{ROLE_LABELS[user.role]}</p>
            <p className="text-xs text-slate-400 mt-0.5">SMP IP YAKIN · Jakarta Selatan</p>
            <div className="flex gap-2 mt-3 justify-center sm:justify-start">
              <Btn v="outline" sz="sm" icon={<Camera className="w-3.5 h-3.5" />}>Ubah Foto</Btn>
              <Btn v="ghost" sz="sm">Hapus</Btn>
            </div>
            <p className="text-xs text-slate-400 mt-2">JPG, PNG, GIF. Maks. 2MB</p>
          </div>
        </div>
      </Crd>

      <Crd>
        <h3 className="text-sm font-semibold text-slate-800 mb-4">Informasi Pribadi</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Inp label="Nama Lengkap" val={form.name} onChange={v => setForm({ ...form, name: v })} req />
          <Inp label="NIP / NISN" val={form.nip} onChange={v => setForm({ ...form, nip: v })} />
          <Inp label="Tanggal Lahir" type="date" val={form.birth} onChange={v => setForm({ ...form, birth: v })} />
          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-slate-700">Jenis Kelamin</label>
            <select value={form.gender} onChange={e => setForm({ ...form, gender: e.target.value })}
              className="h-9 px-3 text-sm rounded-lg border border-slate-300 bg-white hover:border-slate-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 text-slate-900 transition-all">
              <option value="L">Laki-laki</option><option value="P">Perempuan</option>
            </select>
          </div>
        </div>
      </Crd>

      <Crd>
        <h3 className="text-sm font-semibold text-slate-800 mb-4">Informasi Kontak</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Inp label="Email" type="email" val={form.email} onChange={v => setForm({ ...form, email: v })} icon={<Mail className="w-4 h-4" />} req />
          <Inp label="No. Telepon" val={form.phone} onChange={v => setForm({ ...form, phone: v })} icon={<Phone className="w-4 h-4" />} />
          <Inp cls="sm:col-span-2" label="Alamat" val={form.address} onChange={v => setForm({ ...form, address: v })} icon={<MapPin className="w-4 h-4" />} />
          <Inp label="Kota" val={form.city} onChange={v => setForm({ ...form, city: v })} />
          <Inp label="Provinsi" val={form.province} onChange={v => setForm({ ...form, province: v })} />
        </div>
      </Crd>

      <Crd>
        <h3 className="text-sm font-semibold text-slate-800 mb-4">Ubah Password</h3>
        <div className="space-y-4 max-w-sm">
          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-slate-700">Password Saat Ini</label>
            <div className="relative">
              <Lock className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input type="password" placeholder="••••••••" value={pwd.cur} onChange={e => setPwd({ ...pwd, cur: e.target.value })}
                className="w-full h-9 pl-9 pr-3 text-sm rounded-lg border border-slate-300 bg-white hover:border-slate-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all" />
            </div>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-slate-700">Password Baru</label>
            <div className="relative">
              <Lock className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input type="password" placeholder="Min. 8 karakter" value={pwd.newp} onChange={e => setPwd({ ...pwd, newp: e.target.value })}
                className="w-full h-9 pl-9 pr-3 text-sm rounded-lg border border-slate-300 bg-white hover:border-slate-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all" />
            </div>
            {pwd.newp && (
              <div className="mt-0.5">
                <div className="flex gap-1 mb-1">{[1, 2, 3, 4].map(i => <div key={i} className={cn("h-1 flex-1 rounded-full", i <= str ? (str <= 2 ? "bg-red-400" : str === 3 ? "bg-amber-400" : "bg-green-500") : "bg-slate-200")} />)}</div>
                <p className={cn("text-xs", str <= 2 ? "text-red-500" : str === 3 ? "text-amber-500" : "text-green-600")}>
                  {["", "Sangat Lemah", "Lemah", "Sedang", "Kuat"][str] || "Sangat Kuat"}
                </p>
              </div>
            )}
          </div>
          <Inp label="Konfirmasi Password" type="password" ph="Ulangi password baru" val={pwd.conf} onChange={v => setPwd({ ...pwd, conf: v })}
            icon={<Lock className="w-4 h-4" />} err={pwd.conf && pwd.conf !== pwd.newp ? "Password tidak cocok" : undefined} />
        </div>
      </Crd>

      <div className="flex items-center justify-end gap-3">
        <Btn v="outline">Batal</Btn>
        <Btn v="primary" loading={saving} onClick={save}>Simpan Perubahan</Btn>
      </div>
    </div>
  );
}

// ─── Page: Design System ──────────────────────────────────────────────────────
function DesignSystemPage() {
  const [tab, setTab] = useState<"colors" | "typography" | "components" | "spacing">("colors");

  return (
    <div className="p-4 md:p-6 space-y-5 max-w-5xl mx-auto pb-24 lg:pb-6">
      <div>
        <h1 className="text-lg font-bold text-slate-900">Design System</h1>
        <p className="text-sm text-slate-500 mt-0.5">Token warna, tipografi, komponen, dan spacing — SMP IP YAKIN SMS v1.0</p>
      </div>

      <div className="flex gap-1 p-1 bg-slate-100 rounded-xl w-fit">
        {(["colors", "typography", "components", "spacing"] as const).map(t => (
          <button key={t} onClick={() => setTab(t)}
            className={cn("px-4 py-1.5 text-xs font-medium rounded-lg capitalize transition-all",
              tab === t ? "bg-white text-slate-900 shadow-sm" : "text-slate-500 hover:text-slate-700")}>
            {t === "colors" ? "Warna" : t === "typography" ? "Tipografi" : t === "components" ? "Komponen" : "Spacing"}
          </button>
        ))}
      </div>

      {tab === "colors" && (
        <div className="space-y-8">
          {[
            { name: "Primary — Blue", shades: [["50", "#EFF6FF"], ["100", "#DBEAFE"], ["200", "#BFDBFE"], ["300", "#93C5FD"], ["400", "#60A5FA"], ["500", "#3B82F6"], ["600", "#2563EB"], ["700", "#1D4ED8"], ["800", "#1E40AF"], ["900", "#1E3A8A"]] },
            { name: "Success — Green", shades: [["50", "#F0FDF4"], ["100", "#DCFCE7"], ["200", "#BBF7D0"], ["400", "#4ADE80"], ["500", "#22C55E"], ["600", "#16A34A"], ["700", "#15803D"], ["800", "#166534"], ["900", "#14532D"]] },
            { name: "Warning — Amber", shades: [["50", "#FFFBEB"], ["100", "#FEF3C7"], ["200", "#FDE68A"], ["400", "#FBBF24"], ["500", "#F59E0B"], ["600", "#D97706"], ["700", "#B45309"], ["800", "#92400E"], ["900", "#78350F"]] },
            { name: "Danger — Red", shades: [["50", "#FEF2F2"], ["100", "#FEE2E2"], ["200", "#FECACA"], ["400", "#F87171"], ["500", "#EF4444"], ["600", "#DC2626"], ["700", "#B91C1C"], ["800", "#991B1B"], ["900", "#7F1D1D"]] },
            { name: "Neutral — Slate", shades: [["50", "#F8FAFC"], ["100", "#F1F5F9"], ["200", "#E2E8F0"], ["300", "#CBD5E1"], ["400", "#94A3B8"], ["500", "#64748B"], ["600", "#475569"], ["700", "#334155"], ["800", "#1E293B"], ["900", "#0F172A"]] },
          ].map(group => (
            <div key={group.name}>
              <p className="text-xs font-semibold text-slate-600 mb-3">{group.name}</p>
              <div className="flex gap-1.5 flex-wrap">
                {group.shades.map(([shade, hex]) => (
                  <div key={shade} className="flex flex-col items-center gap-1">
                    <div className="w-11 h-11 rounded-lg border border-black/5 shadow-sm" style={{ backgroundColor: hex }} />
                    <p className="text-[10px] text-slate-500 font-semibold">{shade}</p>
                    <p className="text-[10px] text-slate-400 font-mono">{hex.slice(1)}</p>
                  </div>
                ))}
              </div>
            </div>
          ))}

          <div>
            <p className="text-xs font-semibold text-slate-600 mb-3">Semantic Design Tokens</p>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              {[
                { l: "Background", c: "#F8FAFC", u: "--background" },
                { l: "Card", c: "#FFFFFF", u: "--card" },
                { l: "Primary", c: "#2563EB", u: "--primary" },
                { l: "Foreground", c: "#0F172A", u: "--foreground" },
                { l: "Muted", c: "#F1F5F9", u: "--muted" },
                { l: "Border", c: "rgba(0,0,0,0.09)", u: "--border" },
                { l: "Success", c: "#16A34A", u: "green-600" },
                { l: "Danger", c: "#DC2626", u: "red-600" },
              ].map(tok => (
                <div key={tok.l} className="flex items-center gap-2.5 p-3 bg-slate-50 rounded-lg border border-slate-200">
                  <div className="w-8 h-8 rounded-lg flex-shrink-0 border border-black/10 shadow-sm" style={{ backgroundColor: tok.c }} />
                  <div className="min-w-0">
                    <p className="text-xs font-semibold text-slate-800">{tok.l}</p>
                    <p className="text-[10px] text-slate-400 font-mono truncate">{tok.u}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {tab === "typography" && (
        <Crd>
          <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-5">Type Scale — Inter</h3>
          <div className="space-y-1 divide-y divide-slate-100">
            {[
              { n: "Display XL", cls: "text-4xl font-bold", sz: "36px · 700", s: "Sistem Manajemen Sekolah" },
              { n: "Display L", cls: "text-3xl font-bold", sz: "30px · 700", s: "Dashboard Akademik" },
              { n: "Heading 1", cls: "text-2xl font-semibold", sz: "24px · 600", s: "Data Siswa & Guru" },
              { n: "Heading 2", cls: "text-xl font-semibold", sz: "20px · 600", s: "Laporan Kehadiran" },
              { n: "Heading 3", cls: "text-lg font-semibold", sz: "18px · 600", s: "Distribusi Nilai Siswa" },
              { n: "Heading 4", cls: "text-base font-semibold", sz: "16px · 600", s: "Rekap Bulanan" },
              { n: "Body L", cls: "text-base font-normal", sz: "16px · 400", s: "Platform pendidikan modern untuk SMP IP YAKIN" },
              { n: "Body", cls: "text-sm font-normal", sz: "14px · 400", s: "Kelola aktivitas akademik, kehadiran, dan nilai siswa" },
              { n: "Small", cls: "text-xs font-normal", sz: "12px · 400", s: "Informasi tambahan, keterangan tabel dan data" },
              { n: "Label", cls: "text-[10px] font-semibold uppercase tracking-widest", sz: "10px · 700", s: "STATUS AKTIF — KELAS 9A" },
              { n: "Mono", cls: "text-sm font-mono", sz: "14px · mono", s: "0051234567 — NIP: 19850312" },
            ].map(t => (
              <div key={t.n} className="flex items-baseline gap-4 py-4 first:pt-0">
                <div className="w-24 flex-shrink-0">
                  <p className="text-xs font-semibold text-slate-700">{t.n}</p>
                  <p className="text-[10px] text-slate-400 mt-0.5">{t.sz}</p>
                </div>
                <p className={cn("text-slate-900 flex-1 min-w-0 truncate", t.cls)}>{t.s}</p>
              </div>
            ))}
          </div>
        </Crd>
      )}

      {tab === "components" && (
        <div className="space-y-5">
          <Crd>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Buttons</h3>
            <div className="flex flex-wrap gap-2 mb-3">
              <Btn v="primary">Primary</Btn><Btn v="secondary">Secondary</Btn>
              <Btn v="outline">Outline</Btn><Btn v="ghost">Ghost</Btn><Btn v="danger">Danger</Btn>
            </div>
            <div className="flex flex-wrap gap-2 mb-3">
              <Btn v="primary" sz="sm">Small</Btn><Btn v="primary">Medium</Btn><Btn v="primary" sz="lg">Large</Btn>
            </div>
            <div className="flex flex-wrap gap-2">
              <Btn v="primary" loading>Loading</Btn>
              <Btn v="primary" disabled>Disabled</Btn>
              <Btn v="primary" icon={<Plus className="w-4 h-4" />}>With Icon</Btn>
              <Btn v="outline" iconR={<ArrowRight className="w-4 h-4" />}>Icon Right</Btn>
            </div>
          </Crd>

          <Crd>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Badges</h3>
            <div className="flex flex-wrap gap-2">
              <Bdg>Default</Bdg><Bdg v="success">Success</Bdg><Bdg v="warning">Warning</Bdg>
              <Bdg v="danger">Danger</Bdg><Bdg v="info">Info</Bdg><Bdg v="outline">Outline</Bdg>
            </div>
          </Crd>

          <Crd>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Inputs</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Inp label="Text Input" ph="Masukkan teks..." />
              <Inp label="With Icon" ph="Cari..." icon={<Search className="w-4 h-4" />} />
              <Inp label="Email" type="email" ph="nama@email.com" icon={<Mail className="w-4 h-4" />} />
              <Inp label="Password" type="password" ph="••••••••" icon={<Lock className="w-4 h-4" />} />
              <Inp label="With Error" ph="Masukkan nilai..." err="Nilai tidak valid" />
              <Inp label="Disabled" ph="Tidak dapat diedit" disabled />
            </div>
          </Crd>

          <Crd>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Alerts</h3>
            <div className="space-y-3">
              {[
                { icon: CheckCircle, t: "Berhasil", m: "Data siswa berhasil disimpan ke sistem.", cls: "bg-green-50 border-green-200", ic: "text-green-600", tc: "text-green-800", mc: "text-green-700" },
                { icon: AlertCircle, t: "Gagal", m: "Terjadi kesalahan saat memproses data.", cls: "bg-red-50 border-red-200", ic: "text-red-600", tc: "text-red-800", mc: "text-red-700" },
                { icon: AlertTriangle, t: "Perhatian", m: "Batas waktu input nilai akan berakhir.", cls: "bg-amber-50 border-amber-200", ic: "text-amber-600", tc: "text-amber-800", mc: "text-amber-700" },
                { icon: Info, t: "Informasi", m: "Semester baru dimulai 15 Juli 2026.", cls: "bg-blue-50 border-blue-200", ic: "text-blue-600", tc: "text-blue-800", mc: "text-blue-700" },
              ].map(a => {
                const Ic = a.icon;
                return (
                  <div key={a.t} className={cn("flex items-start gap-3 p-3 rounded-lg border", a.cls)}>
                    <Ic className={cn("w-4 h-4 mt-0.5 flex-shrink-0", a.ic)} />
                    <div><p className={cn("text-sm font-semibold", a.tc)}>{a.t}</p><p className={cn("text-xs mt-0.5", a.mc)}>{a.m}</p></div>
                  </div>
                );
              })}
            </div>
          </Crd>

          <Crd>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Avatars</h3>
            <div className="flex items-center gap-4 flex-wrap">
              {(["xs", "sm", "md", "lg", "xl"] as const).map(sz => (
                <div key={sz} className="flex flex-col items-center gap-2">
                  <Av name="Ahmad Fauzi" sz={sz} />
                  <span className="text-[10px] text-slate-400 font-mono">{sz}</span>
                </div>
              ))}
            </div>
            <div className="flex items-center gap-2 mt-4 flex-wrap">
              {["Ahmad", "Budi", "Citra", "Dina", "Eko", "Fitri", "Gilang"].map(n => <Av key={n} name={n + " S"} sz="sm" />)}
            </div>
          </Crd>

          <Crd>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Cards</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="p-4 rounded-xl bg-white border border-slate-200 shadow-sm">
                <p className="text-xs text-slate-500 mb-1">Card Default</p>
                <p className="text-xl font-bold text-slate-900">1,247</p>
                <p className="text-xs text-slate-400 mt-1">Siswa terdaftar aktif</p>
              </div>
              <div className="p-4 rounded-xl bg-blue-600 text-white">
                <p className="text-xs text-blue-200 mb-1">Card Primary</p>
                <p className="text-xl font-bold">94.7%</p>
                <p className="text-xs text-blue-200 mt-1">Tingkat kehadiran</p>
              </div>
              <div className="p-4 rounded-xl bg-green-50 border border-green-200">
                <p className="text-xs text-green-600 mb-1">Card Success</p>
                <p className="text-xl font-bold text-green-800">A+</p>
                <p className="text-xs text-green-600 mt-1">Nilai rata-rata kelas 9A</p>
              </div>
              <div className="p-4 rounded-xl bg-slate-900 text-white">
                <p className="text-xs text-slate-400 mb-1">Card Dark</p>
                <p className="text-xl font-bold">SMP IP YAKIN</p>
                <p className="text-xs text-slate-400 mt-1">Tahun Ajaran 2025/2026</p>
              </div>
            </div>
          </Crd>
        </div>
      )}

      {tab === "spacing" && (
        <div className="space-y-6">
          <Crd>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-5">Spacing System — 8pt Grid</h3>
            <div className="flex items-end gap-3 flex-wrap mb-6">
              {[[4, "1"], [8, "2"], [12, "3"], [16, "4"], [20, "5"], [24, "6"], [32, "8"], [40, "10"], [48, "12"], [64, "16"]].map(([px, t]) => (
                <div key={px} className="flex flex-col items-center gap-2">
                  <div className="bg-blue-500 rounded-sm flex-shrink-0" style={{ width: `${px}px`, height: `${px}px` }} />
                  <p className="text-[10px] text-slate-500 font-mono">{px}px</p>
                  <p className="text-[10px] text-slate-400">t-{t}</p>
                </div>
              ))}
            </div>

            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Border Radius</h3>
            <div className="flex items-center gap-4 flex-wrap mb-6">
              {[[4, "sm"], [6, "md"], [8, "lg"], [12, "xl"], [16, "2xl"], [9999, "full"]].map(([r, l]) => (
                <div key={l} className="flex flex-col items-center gap-2">
                  <div className="w-12 h-12 bg-blue-500" style={{ borderRadius: `${r}px` }} />
                  <p className="text-[10px] text-slate-400">{l}</p>
                </div>
              ))}
            </div>

            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Shadows</h3>
            <div className="flex flex-wrap gap-4">
              {[["shadow-sm", "Subtle"], ["shadow", "Default"], ["shadow-md", "Medium"], ["shadow-lg", "Large"], ["shadow-xl", "Extra Large"]].map(([cls, l]) => (
                <div key={cls} className="flex flex-col items-center gap-2">
                  <div className={cn("w-16 h-16 bg-white rounded-xl", cls)} />
                  <p className="text-[10px] text-slate-400">{l}</p>
                </div>
              ))}
            </div>
          </Crd>

          <Crd>
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Breakpoints</h3>
            <div className="space-y-3">
              {[["sm", "640px", "Smartphone landscape / Small tablet"], ["md", "768px", "Tablet"], ["lg", "1024px", "Desktop / Large tablet"], ["xl", "1280px", "Large desktop"], ["2xl", "1536px", "Wide screen"]].map(([k, v, d]) => (
                <div key={k} className="flex items-center gap-4 p-3 bg-slate-50 rounded-lg">
                  <code className="text-sm font-bold text-blue-600 w-8">{k}</code>
                  <code className="text-sm font-mono text-slate-600 w-20">{v}</code>
                  <p className="text-xs text-slate-500">{d}</p>
                </div>
              ))}
            </div>
          </Crd>
        </div>
      )}
    </div>
  );
}

// ─── Page: Settings ───────────────────────────────────────────────────────────
function SettingsPage() {
  return (
    <div className="p-4 md:p-6 space-y-5 max-w-3xl mx-auto pb-24 lg:pb-6">
      <div>
        <h1 className="text-lg font-bold text-slate-900">Pengaturan</h1>
        <p className="text-sm text-slate-500 mt-0.5">Kelola preferensi sistem dan aplikasi</p>
      </div>
      {[
        { t: "Informasi Sekolah", items: [["Nama Sekolah", "SMP IP YAKIN"], ["NPSN", "20104851"], ["Tahun Ajaran", "2025/2026"], ["Semester Aktif", "Genap"], ["Alamat", "Jl. Pendidikan No. 1, Jakarta Selatan"]] },
        { t: "Notifikasi", items: [["Email Notifikasi", "Aktif"], ["Notifikasi Kehadiran", "Aktif"], ["Notifikasi Nilai", "Aktif"], ["Notifikasi Pengumuman", "Aktif"]] },
        { t: "Keamanan", items: [["Verifikasi 2 Langkah", "Nonaktif"], ["Sesi Aktif", "1 perangkat"], ["Login Terakhir", "13 Jul 2026, 07:30"]] },
      ].map(s => (
        <Crd key={s.t}>
          <h3 className="text-sm font-semibold text-slate-800 mb-4">{s.t}</h3>
          <div className="divide-y divide-slate-100">
            {s.items.map(([l, v]) => (
              <div key={l} className="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                <p className="text-sm text-slate-600">{l}</p>
                <p className="text-sm font-medium text-slate-900">{v}</p>
              </div>
            ))}
          </div>
          <div className="mt-4"><Btn v="outline" sz="sm">Edit {s.t}</Btn></div>
        </Crd>
      ))}
    </div>
  );
}

function PlaceholderPage({ title, desc, icon: Icon }: { title: string; desc: string; icon: any }) {
  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] p-8 text-center">
      <div className="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-5 shadow-sm">
        <Icon className="w-8 h-8 text-slate-400" />
      </div>
      <h2 className="text-base font-semibold text-slate-800 mb-2">{title}</h2>
      <p className="text-sm text-slate-400 max-w-xs leading-relaxed">{desc}</p>
      <Btn v="outline" className="mt-5" sz="sm">Segera Hadir</Btn>
    </div>
  );
}

// ─── App Layout ───────────────────────────────────────────────────────────────
function AppLayout({ user, page, nav, onLogout }: { user: AppUser; page: Page; nav: (p: Page) => void; onLogout: () => void }) {
  const [coll, setColl] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  const renderPage = () => {
    switch (page) {
      case "dashboard": return <DashboardPage />;
      case "students": return <StudentsPage />;
      case "attendance": return <AttendancePage />;
      case "grades": return <GradesPage />;
      case "profile": return <ProfilePage user={user} />;
      case "design-system": return <DesignSystemPage />;
      case "settings": return <SettingsPage />;
      case "teachers": return <PlaceholderPage title="Guru & Staf" desc="Halaman manajemen data guru dan staf akan segera tersedia." icon={Users} />;
      case "classes": return <PlaceholderPage title="Data Kelas" desc="Halaman manajemen kelas dan rombongan belajar akan segera tersedia." icon={School} />;
      case "finance": return <PlaceholderPage title="Keuangan" desc="Halaman manajemen keuangan dan tagihan akan segera tersedia." icon={DollarSign} />;
      case "reports": return <PlaceholderPage title="Laporan & Analitik" desc="Halaman laporan komprehensif dan analitik data akan segera tersedia." icon={BarChart2} />;
      default: return <DashboardPage />;
    }
  };

  return (
    <div className="flex h-screen bg-slate-50 overflow-hidden">
      <Sidebar page={page} nav={nav} coll={coll} onToggle={() => setColl(!coll)}
        user={user} onLogout={onLogout} mobileOpen={mobileOpen} onCloseMobile={() => setMobileOpen(false)} />
      <div className="flex-1 flex flex-col overflow-hidden min-w-0">
        <Topbar user={user} page={page} onOpenMobile={() => setMobileOpen(true)} nav={nav} onLogout={onLogout} />
        <main className="flex-1 overflow-y-auto">
          <AnimatePresence mode="wait">
            <motion.div key={page} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -4 }} transition={{ duration: 0.15 }}>
              {renderPage()}
            </motion.div>
          </AnimatePresence>
        </main>
      </div>
      <BottomNav page={page} nav={nav} />
    </div>
  );
}

// ─── Root ─────────────────────────────────────────────────────────────────────
export default function App() {
  const [user, setUser] = useState<AppUser | null>(null);
  const [page, setPage] = useState<Page>("dashboard");

  return (
    <>
      <Toaster position="top-right" richColors closeButton expand={false} />
      {!user
        ? <AuthFlow onLogin={u => { setUser(u); setPage("dashboard"); }} />
        : <AppLayout user={user} page={page} nav={setPage} onLogout={() => { setUser(null); setPage("dashboard"); }} />
      }
    </>
  );
}
