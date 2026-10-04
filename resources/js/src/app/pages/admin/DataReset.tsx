import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    AlertTriangle, Trash2, RefreshCw, ShieldAlert, Lock, CheckCircle2,
    Database, ClipboardList, BookOpen, Users, GraduationCap, Calendar,
    Megaphone, FileText, WifiOff, LayoutGrid, X, Eye, EyeOff, Info
} from 'lucide-react';
import { cn } from '../../components/ui/utils';
import api from '../../services/api';
import { toast } from 'sonner';

// ─── UI Primitives ─────────────────────────────────────────────────────
function Crd({ children, className }: { children: React.ReactNode; className?: string }) {
    return <div className={cn("bg-white rounded-xl border border-slate-200 shadow-sm", className)}>{children}</div>;
}

function Bdg({ v = "default", children, className }: {
    v?: "default" | "success" | "warning" | "danger" | "critical" | "info"; children: React.ReactNode; className?: string
}) {
    const vs = {
        default: "bg-slate-100 text-slate-600",
        success: "bg-emerald-100 text-emerald-700",
        warning: "bg-amber-100 text-amber-700",
        danger: "bg-red-100 text-red-700",
        critical: "bg-red-200 text-red-800",
        info: "bg-blue-100 text-blue-700",
    };
    return <span className={cn("inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold", vs[v], className)}>{children}</span>;
}

// ─── Icon mapper ───────────────────────────────────────────────────────
const ICON_MAP: Record<string, any> = {
    'clipboard': ClipboardList,
    'book-open': BookOpen,
    'users': Users,
    'graduation-cap': GraduationCap,
    'calendar': Calendar,
    'calendar-days': Calendar,
    'megaphone': Megaphone,
    'file-text': FileText,
    'wifi-off': WifiOff,
    'alert-triangle': AlertTriangle,
    'layout': LayoutGrid,
    'lock': Lock,
};

const DANGER_BADGE: Record<string, { v: "default" | "warning" | "danger" | "critical"; label: string }> = {
    'low': { v: 'default', label: 'Ringan' },
    'medium': { v: 'warning', label: 'Sedang' },
    'high': { v: 'danger', label: 'Tinggi' },
    'critical': { v: 'critical', label: 'Kritis' },
};

// ─── Types ─────────────────────────────────────────────────────────────
interface TableInfo {
    name: string;
    row_count: number;
}

interface TableGroup {
    key: string;
    label: string;
    description: string;
    icon: string;
    danger_level: string;
    tables: TableInfo[];
    total_rows: number;
}

interface ResetSummaryItem {
    table: string;
    rows_deleted: number;
}

interface AppSetting {
    id: number;
    key: string;
    label: string;
    value: string | null;
    type: string;
    description?: string | null;
}

// ─── Confirmation Modal ────────────────────────────────────────────────
function ConfirmationModal({
    open, onClose, selectedGroups, onConfirm, loading
}: {
    open: boolean;
    onClose: () => void;
    selectedGroups: TableGroup[];
    onConfirm: (password: string) => void;
    loading: boolean;
}) {
    const [password, setPassword] = useState('');
    const [confirmText, setConfirmText] = useState('');
    const [showPwd, setShowPwd] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            setPassword('');
            setConfirmText('');
            setShowPwd(false);
            setTimeout(() => inputRef.current?.focus(), 100);
        }
    }, [open]);

    if (!open) return null;

    const totalRows = selectedGroups.reduce((acc, g) => acc + g.total_rows, 0);
    const confirmValid = confirmText === 'HAPUS DATA' && password.length >= 1;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />

            {/* Modal */}
            <div className="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
                {/* Header */}
                <div className="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5 text-white">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                                <ShieldAlert className="w-5 h-5" />
                            </div>
                            <div>
                                <h3 className="font-bold text-lg">Konfirmasi Penghapusan</h3>
                                <p className="text-red-100 text-xs">Tindakan ini tidak dapat dibatalkan</p>
                            </div>
                        </div>
                        <button onClick={onClose} className="text-white/80 hover:text-white transition-colors p-1">
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <div className="p-6 space-y-5">
                    {/* Summary */}
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                        <p className="text-sm font-semibold text-red-800 mb-2">Anda akan menghapus:</p>
                        <ul className="space-y-1.5">
                            {selectedGroups.map(g => (
                                <li key={g.key} className="flex items-center justify-between text-sm">
                                    <span className="text-red-700 font-medium">{g.label}</span>
                                    <span className="text-red-500 text-xs font-mono">{g.total_rows.toLocaleString('id-ID')} baris</span>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-3 pt-3 border-t border-red-200 flex justify-between">
                            <span className="text-sm font-bold text-red-900">Total data yang dihapus</span>
                            <span className="text-sm font-bold text-red-900 font-mono">{totalRows.toLocaleString('id-ID')} baris</span>
                        </div>
                    </div>

                    {/* Password */}
                    <div>
                        <label className="block text-sm font-semibold text-slate-700 mb-1.5">
                            <Lock className="w-3.5 h-3.5 inline mr-1 -mt-0.5" />
                            Masukkan Password Anda
                        </label>
                        <div className="relative">
                            <input
                                ref={inputRef}
                                type={showPwd ? "text" : "password"}
                                value={password}
                                onChange={e => setPassword(e.target.value)}
                                placeholder="Password akun Anda"
                                className="w-full h-10 px-3 pr-10 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-400 transition-all"
                            />
                            <button type="button" onClick={() => setShowPwd(!showPwd)} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                            </button>
                        </div>
                    </div>

                    {/* Confirmation text */}
                    <div>
                        <label className="block text-sm font-semibold text-slate-700 mb-1.5">
                            Ketik <code className="bg-red-100 text-red-700 px-1.5 py-0.5 rounded text-xs font-bold">HAPUS DATA</code> untuk mengonfirmasi
                        </label>
                        <input
                            type="text"
                            value={confirmText}
                            onChange={e => setConfirmText(e.target.value)}
                            placeholder="Ketik di sini..."
                            className={cn(
                                "w-full h-10 px-3 text-sm rounded-lg border focus:outline-none focus:ring-2 transition-all font-mono",
                                confirmText === 'HAPUS DATA'
                                    ? "border-red-400 focus:ring-red-200 focus:border-red-500 bg-red-50 text-red-700"
                                    : "border-slate-300 focus:ring-slate-200 focus:border-slate-400"
                            )}
                        />
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-3 pt-2">
                        <button
                            onClick={onClose}
                            disabled={loading}
                            className="flex-1 h-10 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors disabled:opacity-50"
                        >
                            Batalkan
                        </button>
                        <button
                            onClick={() => onConfirm(password)}
                            disabled={!confirmValid || loading}
                            className={cn(
                                "flex-1 h-10 text-sm font-bold rounded-lg transition-all flex items-center justify-center gap-2",
                                confirmValid && !loading
                                    ? "bg-red-600 text-white hover:bg-red-700 shadow-sm shadow-red-200"
                                    : "bg-slate-100 text-slate-400 cursor-not-allowed"
                            )}
                        >
                            {loading ? (
                                <>
                                    <RefreshCw className="w-4 h-4 animate-spin" />
                                    Menghapus...
                                </>
                            ) : (
                                <>
                                    <Trash2 className="w-4 h-4" />
                                    Hapus Permanen
                                </>
                            )}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Result Modal ──────────────────────────────────────────────────────
function ResultModal({
    open, onClose, message, summary, totalDeleted
}: {
    open: boolean;
    onClose: () => void;
    message: string;
    summary: ResetSummaryItem[];
    totalDeleted: number;
}) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
                <div className="bg-gradient-to-r from-emerald-600 to-emerald-700 px-6 py-5 text-white">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="font-bold text-lg">Penghapusan Berhasil</h3>
                            <p className="text-emerald-100 text-xs">{message}</p>
                        </div>
                    </div>
                </div>

                <div className="p-6 space-y-4">
                    <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                        <p className="text-sm font-semibold text-emerald-800 mb-2">Ringkasan:</p>
                        <div className="space-y-1.5">
                            {summary.map(item => (
                                <div key={item.table} className="flex items-center justify-between text-sm">
                                    <span className="text-slate-700 font-mono text-xs">{item.table}</span>
                                    <span className="text-emerald-700 font-semibold text-xs">{item.rows_deleted.toLocaleString('id-ID')} dihapus</span>
                                </div>
                            ))}
                        </div>
                        <div className="mt-3 pt-3 border-t border-emerald-200 flex justify-between">
                            <span className="text-sm font-bold text-emerald-900">Total dihapus</span>
                            <span className="text-sm font-bold text-emerald-900 font-mono">{totalDeleted.toLocaleString('id-ID')} baris</span>
                        </div>
                    </div>

                    <button
                        onClick={onClose}
                        className="w-full h-10 text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Main Page ─────────────────────────────────────────────────────────
export const DataReset = () => {
    const [loading, setLoading] = useState(true);
    const [groups, setGroups] = useState<TableGroup[]>([]);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [showConfirm, setShowConfirm] = useState(false);
    const [executing, setExecuting] = useState(false);
    const [result, setResult] = useState<{ message: string; summary: ResetSummaryItem[]; totalDeleted: number } | null>(null);
    const [attendanceTestModeSetting, setAttendanceTestModeSetting] = useState<AppSetting | null>(null);
    const [attendanceTestMode, setAttendanceTestMode] = useState(false);
    const [savingTestMode, setSavingTestMode] = useState(false);

    const fetchGroups = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/admin/data-reset');
            setGroups(res.data.groups || []);
            const setting = res.data.settings?.attendance_test_mode || null;
            setAttendanceTestModeSetting(setting);
            setAttendanceTestMode(setting?.value === '1' || setting?.value === 'true');
        } catch {
            toast.error('Gagal memuat data tabel.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchGroups();
    }, [fetchGroups]);

    const toggleGroup = (key: string) => {
        setSelected(prev => {
            const next = new Set(prev);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    };

    const toggleAll = () => {
        if (selected.size === groups.length) {
            setSelected(new Set());
        } else {
            setSelected(new Set(groups.map(g => g.key)));
        }
    };

    const selectedGroups = groups.filter(g => selected.has(g.key));
    const selectedTotalRows = selectedGroups.reduce((acc, g) => acc + g.total_rows, 0);

    const toggleAttendanceTestMode = async () => {
        if (!attendanceTestModeSetting) {
            toast.error('Pengaturan mode test belum tersedia. Muat ulang halaman.');
            return;
        }

        const nextValue = !attendanceTestMode;
        setAttendanceTestMode(nextValue);
        setSavingTestMode(true);

        try {
            const res = await api.patch(`/admin/setting/${attendanceTestModeSetting.id}/value`, {
                value: nextValue ? '1' : '0',
            });
            setAttendanceTestModeSetting(res.data.data);
            toast.success(nextValue ? 'Mode test absensi diaktifkan.' : 'Mode test absensi dinonaktifkan.');
        } catch (err: any) {
            setAttendanceTestMode(!nextValue);
            toast.error(err.response?.data?.message || 'Gagal mengubah mode test absensi.');
        } finally {
            setSavingTestMode(false);
        }
    };

    const handleConfirm = async (password: string) => {
        setExecuting(true);
        try {
            const res = await api.post('/admin/data-reset', {
                password,
                confirmation_text: 'HAPUS DATA',
                groups: Array.from(selected),
            });
            setShowConfirm(false);
            setSelected(new Set());
            setResult({
                message: res.data.message,
                summary: res.data.summary,
                totalDeleted: res.data.total_deleted,
            });
            // Refresh row counts
            fetchGroups();
        } catch (err: any) {
            const msg = err.response?.data?.message || 'Terjadi kesalahan.';
            toast.error(msg);
        } finally {
            setExecuting(false);
        }
    };

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center h-64 gap-3">
                <RefreshCw className="w-8 h-8 text-blue-500 animate-spin" />
                <p className="text-sm text-slate-500">Memuat data tabel...</p>
            </div>
        );
    }

    return (
        <div className="p-4 md:p-6 space-y-5 max-w-5xl mx-auto">
            {/* Header Warning Banner */}
            <div className="bg-gradient-to-r from-red-600 via-red-700 to-red-800 text-white rounded-2xl p-6 shadow-lg relative overflow-hidden">
                <div className="absolute right-0 top-0 bottom-0 w-1/3 opacity-10 flex items-center justify-center pointer-events-none">
                    <ShieldAlert className="w-48 h-48" />
                </div>
                <div className="relative z-10 space-y-2">
                    <div className="flex items-center gap-2">
                        <Bdg v="danger" className="bg-white/20 text-white border border-white/20">
                            <AlertTriangle className="w-3 h-3 mr-1" />
                            Area Berbahaya
                        </Bdg>
                    </div>
                    <h1 className="text-2xl font-bold">Reset Data Sistem</h1>
                    <p className="text-red-100 text-sm max-w-xl">
                        Pilih kelompok data yang ingin dihapus. Data yang dihapus <strong>tidak dapat dikembalikan</strong>.
                        Pastikan Anda sudah melakukan backup melalui menu Export sebelum melanjutkan.
                    </p>
                </div>
            </div>

            {/* Mode Test Absensi */}
            <Crd className={cn(
                "p-5 border-l-4",
                attendanceTestMode ? "border-l-amber-500 bg-amber-50/40" : "border-l-slate-300"
            )}>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className={cn(
                            "mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl",
                            attendanceTestMode ? "bg-amber-100 text-amber-700" : "bg-slate-100 text-slate-500"
                        )}>
                            <CheckCircle2 className="h-5 w-5" />
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-sm font-bold text-slate-900">Mode Test Absensi</h2>
                                <Bdg v={attendanceTestMode ? 'warning' : 'default'}>{attendanceTestMode ? 'Aktif' : 'Nonaktif'}</Bdg>
                            </div>
                            <p className="mt-1 max-w-2xl text-xs leading-5 text-slate-600">
                                Jika dicentang, guru dapat mengisi absensi untuk kebutuhan uji coba tanpa mengikuti hari atau jam jadwal. Matikan kembali saat sistem dipakai operasional.
                            </p>
                        </div>
                    </div>
                    <label className="inline-flex cursor-pointer select-none items-center gap-2 self-start rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm sm:self-center">
                        <input
                            type="checkbox"
                            checked={attendanceTestMode}
                            onChange={toggleAttendanceTestMode}
                            disabled={savingTestMode || !attendanceTestModeSetting}
                            className="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 disabled:opacity-50"
                        />
                        {savingTestMode ? 'Menyimpan...' : 'Aktifkan mode test'}
                    </label>
                </div>
            </Crd>

            {/* Info Banner */}
            <div className="bg-blue-50 border border-blue-200 rounded-xl px-5 py-3.5 flex items-start gap-3">
                <Info className="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" />
                <div className="text-sm text-blue-800">
                    <p className="font-semibold mb-0.5">Mekanisme Keamanan</p>
                    <p className="text-blue-600 text-xs">
                        Anda harus memasukkan password akun Anda dan mengetik teks konfirmasi sebelum data bisa dihapus.
                        Tabel <strong>akun pengguna</strong> dan <strong>pengaturan sistem</strong> tidak bisa dihapus melalui fitur ini.
                    </p>
                </div>
            </div>

            {/* Select All & Actions Bar */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-3.5">
                <div className="flex items-center gap-3">
                    <label className="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={selected.size === groups.length && groups.length > 0}
                            onChange={toggleAll}
                            className="w-4 h-4 rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer"
                        />
                        <span className="text-sm font-medium text-slate-700">Pilih Semua</span>
                    </label>
                    {selected.size > 0 && (
                        <Bdg v="danger">
                            {selected.size} kelompok dipilih · {selectedTotalRows.toLocaleString('id-ID')} baris
                        </Bdg>
                    )}
                </div>
                <button
                    onClick={() => setShowConfirm(true)}
                    disabled={selected.size === 0}
                    className={cn(
                        "inline-flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl transition-all",
                        selected.size > 0
                            ? "bg-red-600 text-white hover:bg-red-700 shadow-sm shadow-red-200"
                            : "bg-slate-100 text-slate-400 cursor-not-allowed"
                    )}
                >
                    <Trash2 className="w-4 h-4" />
                    Hapus Data Terpilih
                </button>
            </div>

            {/* Table Groups Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {groups.map(group => {
                    const Icon = ICON_MAP[group.icon] || Database;
                    const dangerInfo = DANGER_BADGE[group.danger_level] || DANGER_BADGE['low'];
                    const isSelected = selected.has(group.key);
                    const isEmpty = group.total_rows === 0;

                    return (
                        <Crd key={group.key} className={cn(
                            "p-4 transition-all duration-150 cursor-pointer hover:shadow-md group",
                            isSelected
                                ? "ring-2 ring-red-500 border-red-300 bg-red-50/30"
                                : "hover:border-slate-300",
                            isEmpty && "opacity-60"
                        )}>
                            <label className="flex items-start gap-3.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={isSelected}
                                    onChange={() => toggleGroup(group.key)}
                                    disabled={isEmpty}
                                    className="w-4 h-4 mt-1 rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer disabled:cursor-not-allowed"
                                />
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <div className={cn(
                                            "w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors",
                                            isSelected ? "bg-red-100" : "bg-slate-100 group-hover:bg-slate-200"
                                        )}>
                                            <Icon className={cn("w-4 h-4", isSelected ? "text-red-600" : "text-slate-500")} />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <h4 className="text-sm font-bold text-slate-800 truncate">{group.label}</h4>
                                                <Bdg v={dangerInfo.v}>{dangerInfo.label}</Bdg>
                                            </div>
                                        </div>
                                    </div>
                                    <p className="text-xs text-slate-500 mb-2.5 line-clamp-1">{group.description}</p>

                                    {/* Table detail rows */}
                                    <div className="space-y-1">
                                        {group.tables.map(table => (
                                            <div key={table.name} className="flex items-center justify-between">
                                                <span className="text-[11px] text-slate-400 font-mono truncate">{table.name}</span>
                                                <span className={cn(
                                                    "text-[11px] font-bold font-mono",
                                                    table.row_count > 0 ? "text-slate-700" : "text-slate-300"
                                                )}>
                                                    {table.row_count.toLocaleString('id-ID')} baris
                                                </span>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Total */}
                                    <div className="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                                        <span className="text-xs font-semibold text-slate-500">Total</span>
                                        <span className={cn(
                                            "text-xs font-bold font-mono",
                                            isEmpty ? "text-slate-300" : "text-slate-800"
                                        )}>
                                            {group.total_rows.toLocaleString('id-ID')} baris
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </Crd>
                    );
                })}
            </div>

            {/* Confirmation Modal */}
            <ConfirmationModal
                open={showConfirm}
                onClose={() => setShowConfirm(false)}
                selectedGroups={selectedGroups}
                onConfirm={handleConfirm}
                loading={executing}
            />

            {/* Result Modal */}
            <ResultModal
                open={result !== null}
                onClose={() => setResult(null)}
                message={result?.message || ''}
                summary={result?.summary || []}
                totalDeleted={result?.totalDeleted || 0}
            />
        </div>
    );
};
