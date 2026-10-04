import React, { useEffect, useState } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { School, ChevronLeft, ChevronRight, LogOut, Menu, Search, Bell, ChevronDown, ChevronUp, User, Settings, Circle } from 'lucide-react';
import { cn } from '../components/ui/utils'; // Assuming this utility is present
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { getNavGroups, ROLE_LABELS, NavCapabilities } from '../utils/constants';

const AV_COLORS = ["bg-blue-600","bg-violet-600","bg-emerald-600","bg-amber-600","bg-rose-600","bg-cyan-600","bg-orange-600"];
function Av({ name, sz = "md", className }: { name: string; sz?: "xs"|"sm"|"md"|"lg"|"xl"; className?: string }) {
  const ss = { xs: "w-6 h-6 text-[9px]", sm: "w-8 h-8 text-xs", md: "w-9 h-9 text-sm", lg: "w-12 h-12 text-base", xl: "w-20 h-20 text-2xl" };
  const safeName = name || "User";
  const charCode = safeName.charCodeAt(0) || 0;
  const color = AV_COLORS[charCode % AV_COLORS.length];
  const init = safeName.split(" ").slice(0,2).map(n => n[0]).join("").toUpperCase();
  return <div className={cn("rounded-full text-white font-semibold flex items-center justify-center flex-shrink-0", ss[sz], color, className)}>{init}</div>;
}

function Bdg({ v = "default", children, className }: { v?: "default"|"success"|"warning"|"danger"|"info"|"outline"; children: React.ReactNode; className?: string }) {
  const vs = {
    default: "bg-slate-100 text-slate-700", success: "bg-green-100 text-green-700",
    warning: "bg-amber-100 text-amber-700",  danger:  "bg-red-100 text-red-700",
    info:    "bg-blue-100 text-blue-700",    outline: "border border-slate-300 text-slate-600 bg-transparent",
  };
  return <span className={cn("inline-flex items-center px-2 py-0.5 rounded text-xs font-medium", vs[v], className)}>{children}</span>;
}

export const MainLayout = () => {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [coll, setColl] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [openMenus, setOpenMenus] = useState<Record<string, boolean>>({});
    const [navCapabilities, setNavCapabilities] = useState<NavCapabilities>({
        hasTeachingSchedule: null,
        hasHomeroomClass: null,
    });

    const toggleMenu = (id: string) => {
        if (coll) setColl(false); // expand sidebar if trying to open a menu
        setOpenMenus(prev => ({ ...prev, [id]: !prev[id] }));
    };

    useEffect(() => {
        let active = true;
        if (!user) {
            setNavCapabilities({ hasTeachingSchedule: null, hasHomeroomClass: null });
            return;
        }

        const shouldResolveTeacherMenus = user.activePortal === 'guru-mapel' || user.activePortal === 'walikelas';

        if (!shouldResolveTeacherMenus) {
            setNavCapabilities({ hasTeachingSchedule: null, hasHomeroomClass: null });
            return;
        }

        setNavCapabilities({ hasTeachingSchedule: null, hasHomeroomClass: null });

        const resolveCapabilities = async () => {
            const [scheduleResult, homeroomResult] = await Promise.allSettled([
                api.get('/guru-mapel/jadwal-ajar'),
                api.get('/walikelas/kelas'),
            ]);

            if (!active) return;

            const schedules = scheduleResult.status === 'fulfilled'
                ? (scheduleResult.value.data.data || [])
                : [];

            const homeroomClasses = homeroomResult.status === 'fulfilled'
                ? (homeroomResult.value.data.data || [])
                : [];

            setNavCapabilities({
                hasTeachingSchedule: Array.isArray(schedules) && schedules.length > 0,
                hasHomeroomClass: Array.isArray(homeroomClasses) && homeroomClasses.length > 0,
            });
        };

        resolveCapabilities();

        return () => {
            active = false;
        };
    }, [user?.id, user?.activePortal]);

    if (!user) {
        return <div>Memuat...</div>;
    }

    const currentRole = user.roles[0]; // simplify to first role for now
    const navGroups = getNavGroups(user.activePortal, navCapabilities);

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
                {navGroups.map(({ group, items }) => (
                    <div key={group} className="mb-1">
                        {!coll && <p className="text-[10px] font-semibold text-slate-600 uppercase tracking-widest px-4 pt-4 pb-1.5">{group}</p>}
                        {coll && group !== "Utama" && <div className="h-px bg-white/5 mx-3 my-2" />}
                        {items.map(item => {
                            const Ic = item.icon; 
                            const hasSub = item.subItems && item.subItems.length > 0;
                            const isActive = item.path 
                                ? (location.pathname === item.path || (item.path !== '/' && location.pathname.startsWith(item.path)))
                                : (hasSub && item.subItems!.some(sub => location.pathname.startsWith(sub.path!)));
                            
                            const isOpen = openMenus[item.id];

                            return (
                                <div key={item.id}>
                                    <button onClick={() => { 
                                        if (hasSub) {
                                            toggleMenu(item.id);
                                        } else if (item.path) {
                                            navigate(item.path); setMobileOpen(false); 
                                        }
                                    }} title={coll ? item.label : undefined}
                                        className={cn("w-full flex items-center gap-2.5 text-sm transition-all duration-100 relative group",
                                            coll ? "justify-center py-3 px-0" : "px-4 py-2",
                                            isActive && !hasSub ? "text-blue-400 bg-blue-500/10" : "text-slate-400 hover:text-white hover:bg-white/5",
                                            hasSub && isOpen ? "text-white" : ""
                                        )}>
                                        {isActive && !hasSub && <span className="absolute left-0 top-0 bottom-0 w-0.5 bg-blue-500 rounded-r" />}
                                        <Ic className={cn("w-4 h-4 flex-shrink-0", isActive ? "text-blue-400" : "")} />
                                        {!coll && <span className="truncate text-sm flex-1 text-left">{item.label}</span>}
                                        {!coll && hasSub && (
                                            isOpen ? <ChevronDown className="w-3.5 h-3.5 flex-shrink-0" /> : <ChevronRight className="w-3.5 h-3.5 flex-shrink-0" />
                                        )}
                                    </button>
                                    
                                    {/* Sub Items */}
                                    {hasSub && isOpen && !coll && (
                                        <div className="mt-1 mb-2">
                                            {item.subItems!.map(sub => {
                                                const isSubActive = location.pathname.startsWith(sub.path!);
                                                return (
                                                    <button key={sub.id} onClick={() => { navigate(sub.path!); setMobileOpen(false); }}
                                                        className={cn("w-full flex items-center gap-3 pl-10 pr-4 py-1.5 text-[13px] transition-colors relative",
                                                            isSubActive ? "text-blue-400" : "text-slate-400 hover:text-white"
                                                        )}>
                                                        <Circle className={cn("w-1.5 h-1.5", isSubActive ? "fill-blue-400" : "")} />
                                                        <span className="truncate">{sub.label}</span>
                                                    </button>
                                                )
                                            })}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                ))}
            </nav>

            {/* User */}
            <div className="border-t border-white/5 flex-shrink-0">
                <div className={cn("p-3 flex items-center gap-2.5", coll && "justify-center")}>
                    <Av name={user.name || 'User'} sz="sm" className="flex-shrink-0" />
                    {!coll && (
                        <>
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-semibold text-slate-200 truncate leading-tight">{(user.name || 'User').split(",")[0]}</p>
                                <p className="text-[11px] text-slate-500 truncate">{ROLE_LABELS[currentRole] || "Pengguna"}</p>
                            </div>
                            <button onClick={logout} className="text-slate-500 hover:text-red-400 transition-colors flex-shrink-0 p-1">
                                <LogOut className="w-3.5 h-3.5" />
                            </button>
                        </>
                    )}
                </div>
                <button onClick={() => setColl(!coll)}
                    className="hidden lg:flex w-full items-center justify-center h-7 border-t border-white/5 text-slate-600 hover:text-slate-300 hover:bg-white/5 transition-colors text-xs gap-1">
                    {coll ? <ChevronRight className="w-3.5 h-3.5" /> : <><ChevronLeft className="w-3 h-3" /><span>Collapse</span></>}
                </button>
            </div>
        </div>
    );

    return (
        <div className="flex h-screen bg-slate-100 overflow-hidden font-sans">
            {/* Sidebar */}
            <div className="hidden lg:flex flex-shrink-0 shadow-[8px_0_24px_rgba(15,23,42,0.10)] z-20">{body}</div>
            <AnimatePresence>
                {mobileOpen && (
                    <>
                        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                            onClick={() => setMobileOpen(false)} className="fixed inset-0 bg-black/60 z-40 lg:hidden" />
                        <motion.div initial={{ x: -256 }} animate={{ x: 0 }} exit={{ x: -256 }}
                            transition={{ type: "spring", damping: 28, stiffness: 280 }}
                            className="fixed left-0 top-0 bottom-0 z-50 lg:hidden">
                            {body}
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col min-w-0 overflow-hidden relative bg-slate-50 lg:ml-6 lg:border-l lg:border-slate-200">
                {/* Topbar */}
                <header className="h-14 bg-white border-b border-slate-200 flex items-center px-4 gap-3 sticky top-0 z-30 flex-shrink-0">
                    <button onClick={() => setMobileOpen(true)} className="lg:hidden w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-600 transition-colors">
                        <Menu className="w-5 h-5" />
                    </button>
                    
                    <div className="flex-1" />
                    
                    <div className="relative hidden md:block">
                        <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input type="text" placeholder="Cari..." className="w-52 h-8 pl-8 pr-3 text-sm rounded-lg border border-slate-200 bg-slate-50 hover:border-slate-300 focus:outline-none focus:border-blue-500 focus:bg-white transition-all" />
                    </div>

                    <div className="relative">
                        <button className="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-100 transition-colors">
                            <Av name={user.name || 'User'} sz="sm" />
                            <div className="hidden sm:block text-left">
                                <p className="text-xs font-semibold text-slate-800 leading-tight">{(user.name || 'User').split(",")[0]}</p>
                                <p className="text-[11px] text-slate-400 leading-tight">{ROLE_LABELS[currentRole] || "Pengguna"}</p>
                            </div>
                            <ChevronDown className="w-3 h-3 text-slate-400 hidden sm:block" />
                        </button>
                    </div>
                </header>

                {/* Main scrollable view */}
                <main className="flex-1 overflow-y-auto overflow-x-hidden">
                    <Outlet />
                </main>
            </div>
        </div>
    );
};
