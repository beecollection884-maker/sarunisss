import React, { useState, useEffect } from 'react';
import { Plus, Edit2, Trash2, Save, X, RefreshCw, UserCheck, Users, ArrowLeftRight, ChevronRight } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface Student {
    id: number;
    nik: string;
    name: string;
}

interface Teacher {
    id: number;
    name: string;
    nip: string;
}

interface SchoolClass {
    id: number;
    name: string;
    level: string;
    academic_year: string;
    homeroom_teacher_id: number | null;
    homeroom_teacher?: Teacher;
    students?: Student[];
}

export const Kelas = () => {
    const [classes, setClasses] = useState<SchoolClass[]>([]);
    const [teachers, setTeachers] = useState<Teacher[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    // Modal & Plotting states
    const [isClassFormOpen, setIsClassFormOpen] = useState(false);
    const [isPlottingOpen, setIsPlottingOpen] = useState(false);
    const [editingClass, setEditingClass] = useState<SchoolClass | null>(null);
    const [selectedClass, setSelectedClass] = useState<SchoolClass | null>(null);

    // Form fields - Class CRUD
    const [className, setClassName] = useState('');
    const [classLevel, setClassLevel] = useState('7');
    const [academicYear, setAcademicYear] = useState('2025/2026');
    const [description, setDescription] = useState('');
    const [formHomeroomTeacherId, setFormHomeroomTeacherId] = useState('');

    // Form fields - Plotting
    const [homeroomTeacherId, setHomeroomTeacherId] = useState('');
    const [classStudents, setClassStudents] = useState<Student[]>([]);
    const [unassignedStudents, setUnassignedStudents] = useState<Student[]>([]);
    const [checkedAssigned, setCheckedAssigned] = useState<number[]>([]);
    const [checkedUnassigned, setCheckedUnassigned] = useState<number[]>([]);

    useEffect(() => {
        fetchClasses();
        fetchTeachers();
    }, []);

    const fetchClasses = async () => {
        setLoading(true);
        try {
            const response = await api.get('/admin/kelas');
            setClasses(response.data.data || []);
        } catch (error) {
            toast.error('Gagal memuat data kelas');
        } finally {
            setLoading(false);
        }
    };

    const fetchTeachers = async () => {
        try {
            const response = await api.get('/admin/guru', { params: { per_page: 500 } });
            setTeachers(response.data.data || []);
        } catch (error) {
            console.error('Gagal memuat data guru', error);
        }
    };

    const fetchUnassignedStudents = async () => {
        try {
            const response = await api.get('/admin/siswa/tidak-ada-kelas');
            setUnassignedStudents(response.data.data || []);
        } catch (error) {
            console.error('Gagal memuat siswa tidak ada kelas', error);
        }
    };

    // Class CRUD actions
    const handleOpenClassCreate = () => {
        setEditingClass(null);
        setClassName('');
        setClassLevel('7');
        setAcademicYear('2025/2026');
        setDescription('');
        setFormHomeroomTeacherId('');
        setIsClassFormOpen(true);
    };

    const handleOpenClassEdit = (cls: SchoolClass) => {
        setEditingClass(cls);
        setClassName(cls.name);
        setClassLevel(cls.level);
        setAcademicYear(cls.academic_year);
        setDescription(cls.description || '');
        setFormHomeroomTeacherId(cls.homeroom_teacher_id?.toString() || '');
        setIsClassFormOpen(true);
    };

    const handleSaveClass = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        const payload = {
            name: className,
            level: classLevel,
            academic_year: academicYear,
            homeroom_teacher_id: formHomeroomTeacherId ? parseInt(formHomeroomTeacherId) : null,
            description: description || null,
        };

        try {
            if (editingClass) {
                await api.put(`/admin/kelas/${editingClass.id}`, payload);
                toast.success('Data kelas berhasil diperbarui!');
            } else {
                await api.post('/admin/kelas', payload);
                toast.success('Kelas baru berhasil ditambahkan!');
            }
            setIsClassFormOpen(false);
            fetchClasses();
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal menyimpan kelas');
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteClass = async (id: number) => {
        if (!confirm('Apakah Anda yakin ingin menghapus kelas ini? Semua siswa di kelas ini akan kehilangan kelas.')) return;
        try {
            await api.delete(`/admin/kelas/${id}`);
            toast.success('Kelas berhasil dihapus');
            fetchClasses();
        } catch (error) {
            toast.error('Gagal menghapus kelas');
        }
    };

    // Plotting Actions
    const handleOpenPlotting = async (cls: SchoolClass) => {
        setSelectedClass(cls);
        setHomeroomTeacherId(cls.homeroom_teacher_id?.toString() || '');
        
        // Fetch detailed class to get students list
        try {
            const detailRes = await api.get(`/admin/kelas/${cls.id}`);
            setClassStudents(detailRes.data.data.students || []);
        } catch (e) {
            setClassStudents([]);
        }

        // Fetch unassigned students
        await fetchUnassignedStudents();

        setCheckedAssigned([]);
        setCheckedUnassigned([]);
        setIsPlottingOpen(true);
    };

    const toggleAssignedCheck = (id: number) => {
        setCheckedAssigned(prev =>
            prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
        );
    };

    const toggleUnassignedCheck = (id: number) => {
        setCheckedUnassigned(prev =>
            prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
        );
    };

    const moveStudentsToClass = () => {
        const toMove = unassignedStudents.filter(s => checkedUnassigned.includes(s.id));
        setClassStudents(prev => [...prev, ...toMove]);
        setUnassignedStudents(prev => prev.filter(s => !checkedUnassigned.includes(s.id)));
        setCheckedUnassigned([]);
    };

    const removeStudentsFromClass = () => {
        const toRemove = classStudents.filter(s => checkedAssigned.includes(s.id));
        setUnassignedStudents(prev => [...prev, ...toRemove]);
        setClassStudents(prev => prev.filter(s => !checkedAssigned.includes(s.id)));
        setCheckedAssigned([]);
    };

    const handleSavePlotting = async () => {
        if (!selectedClass) return;
        setSaving(true);

        const payload = {
            homeroom_teacher_id: homeroomTeacherId ? parseInt(homeroomTeacherId) : null,
            student_ids: classStudents.map(s => s.id),
        };

        try {
            await api.put(`/admin/kelas/${selectedClass.id}/ploting`, payload);
            toast.success('Ploting siswa & wali kelas berhasil disimpan!');
            setIsPlottingOpen(false);
            fetchClasses();
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal menyimpan ploting');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="p-6 max-w-7xl mx-auto">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Manajemen Kelas</h1>
                    <p className="text-sm text-slate-500 mt-1">Daftar kelas aktif, wali kelas, dan manajemen plotting siswa.</p>
                </div>
                <button onClick={handleOpenClassCreate} className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm self-start">
                    <Plus className="w-4 h-4" />
                    Tambah Kelas
                </button>
            </div>

            {/* List of Classes */}
            {loading ? (
                <div className="p-8 text-center text-slate-500 flex items-center justify-center gap-2">
                    <RefreshCw className="w-5 h-5 animate-spin" /> Memuat data kelas...
                </div>
            ) : classes.length === 0 ? (
                <div className="p-12 text-center text-slate-400 bg-white border border-slate-200 rounded-xl">
                    Tidak ada data kelas. Silakan tambahkan kelas terlebih dahulu.
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {classes.map((cls) => (
                        <div key={cls.id} className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between">
                            <div>
                                <div className="flex items-center justify-between mb-3">
                                    <span className="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold border border-blue-100">
                                        Level {cls.level}
                                    </span>
                                    <span className="text-xs text-slate-400 font-medium">TA: {cls.academic_year}</span>
                                </div>
                                <h3 className="text-lg font-bold text-slate-800 mb-1">Kelas {cls.name}</h3>
                                <p className="text-xs text-slate-400 mb-4">{cls.description || 'Tidak ada deskripsi'}</p>

                                <div className="space-y-2 mb-5 text-sm">
                                    <div className="flex items-center gap-2 text-slate-600">
                                        <UserCheck className="w-4 h-4 text-slate-400" />
                                        <span>Wali: <strong className="text-slate-800">{cls.homeroom_teacher?.name || 'Belum diplot'}</strong></span>
                                    </div>
                                    <div className="flex items-center gap-2 text-slate-600">
                                        <Users className="w-4 h-4 text-slate-400" />
                                        <span>Siswa: <strong className="text-slate-800">{cls.students?.length || 0} orang</strong></span>
                                    </div>
                                </div>
                            </div>

                            <div className="border-t border-slate-100 pt-4 flex gap-2 justify-end">
                                <button onClick={() => handleOpenPlotting(cls)} className="flex items-center gap-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm transition-colors">
                                    <Users className="w-3.5 h-3.5" />
                                    Plotting Siswa & Wali
                                </button>
                                <button onClick={() => handleOpenClassEdit(cls)} className="p-2 hover:bg-slate-100 text-slate-600 rounded-lg">
                                    <Edit2 className="w-3.5 h-3.5" />
                                </button>
                                <button onClick={() => handleDeleteClass(cls.id)} className="p-2 hover:bg-red-50 text-red-600 rounded-lg">
                                    <Trash2 className="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Modal CRUD Kelas */}
            {isClassFormOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 className="font-semibold text-slate-800">
                                {editingClass ? 'Edit Kelas' : 'Tambah Kelas Baru'}
                            </h2>
                            <button onClick={() => setIsClassFormOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSaveClass} className="p-5 space-y-4">
                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Nama Kelas (Wajib)</label>
                                <input type="text" value={className} onChange={(e) => setClassName(e.target.value)} required placeholder="Misal: 7-A atau VIII-B" className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Tahun Ajaran</label>
                                    <input type="text" value={academicYear} onChange={(e) => setAcademicYear(e.target.value)} required className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-50 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Level Kelas</label>
                                    <select value={classLevel} onChange={(e) => setClassLevel(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                                        <option value="7">7 (Tujuh)</option>
                                        <option value="8">8 (Delapan)</option>
                                        <option value="9">9 (Sembilan)</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Wali Kelas (Opsional)</label>
                                <select value={formHomeroomTeacherId} onChange={(e) => setFormHomeroomTeacherId(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                                    <option value="">Pilih Guru Wali Kelas...</option>
                                    {teachers.map(t => (
                                        <option key={t.id} value={t.id}>{t.name} (NIP: {t.nip})</option>
                                    ))}
                                </select>
                                <p className="text-xs text-slate-500 mt-1">Satu guru hanya bisa menjadi wali untuk satu kelas dalam satu tahun ajaran.</p>
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Deskripsi/Keterangan</label>
                                <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} placeholder="Keterangan opsional tentang kelas..." className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" />
                            </div>

                            <div className="pt-2 border-t border-slate-100 flex justify-end gap-2.5">
                                <button type="button" onClick={() => setIsClassFormOpen(false)} className="px-4 py-2 text-sm font-medium text-slate-600">Batal</button>
                                <button type="submit" disabled={saving} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm">
                                    {saving ? 'Menyimpan...' : 'Simpan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal/Drawer Plotting */}
            {isPlottingOpen && selectedClass && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <div>
                                <h2 className="font-bold text-slate-800 text-lg">Plotting Kelas: {selectedClass.name}</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Atur Wali Kelas dan plotting daftar siswa aktif.</p>
                            </div>
                            <button onClick={() => setIsPlottingOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="p-6 space-y-6 overflow-y-auto flex-1">
                            {/* Wali Kelas Selector */}
                            <div className="bg-blue-50 border border-blue-100 rounded-xl p-4">
                                <label className="block text-sm font-semibold text-blue-900 mb-1.5">Wali Kelas</label>
                                <select
                                    value={homeroomTeacherId}
                                    onChange={(e) => setHomeroomTeacherId(e.target.value)}
                                    className="w-full max-w-md px-3.5 py-2 border border-blue-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                >
                                    <option value="">Pilih Guru Wali Kelas...</option>
                                    {teachers.map(t => (
                                        <option key={t.id} value={t.id}>{t.name} (NIP: {t.nip})</option>
                                    ))}
                                </select>
                            </div>

                            {/* Dual List Plotting */}
                            <div className="grid grid-cols-1 md:grid-cols-7 gap-4 items-center">
                                {/* Left List: Class Members */}
                                <div className="md:col-span-3 border border-slate-200 rounded-xl overflow-hidden flex flex-col h-[350px]">
                                    <div className="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex justify-between items-center">
                                        <span className="text-xs font-bold text-slate-700 uppercase tracking-wider">Siswa Terdaftar ({classStudents.length})</span>
                                        <button type="button" onClick={() => setCheckedAssigned(classStudents.map(s => s.id))} className="text-xs text-blue-600 font-semibold hover:underline">Pilih Semua</button>
                                    </div>
                                    <div className="overflow-y-auto p-2 flex-1 divide-y divide-slate-100">
                                        {classStudents.length === 0 ? (
                                            <div className="p-8 text-center text-xs text-slate-400">Belum ada siswa di kelas ini.</div>
                                        ) : (
                                            classStudents.map(student => (
                                                <label key={student.id} className="flex items-center gap-3 p-2.5 hover:bg-slate-50 rounded-lg cursor-pointer text-sm">
                                                    <input
                                                        type="checkbox"
                                                        checked={checkedAssigned.includes(student.id)}
                                                        onChange={() => toggleAssignedCheck(student.id)}
                                                        className="rounded text-blue-600 focus:ring-blue-500/20"
                                                    />
                                                    <div>
                                                        <span className="font-semibold text-slate-800 block">{student.name}</span>
                                                        <span className="text-xs text-slate-400">NIK: {student.nik}</span>
                                                    </div>
                                                </label>
                                            ))
                                        )}
                                    </div>
                                </div>

                                {/* Middle Buttons */}
                                <div className="md:col-span-1 flex flex-row md:flex-col justify-center gap-3">
                                    <button
                                        type="button"
                                        onClick={moveStudentsToClass}
                                        disabled={checkedUnassigned.length === 0}
                                        className="flex-1 md:flex-initial flex items-center justify-center gap-1 bg-blue-600 hover:bg-blue-700 text-white p-2.5 rounded-lg text-xs font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                                    >
                                        <ChevronRight className="w-4 h-4 hidden md:block" /> Masuk Kelas
                                    </button>
                                    <button
                                        type="button"
                                        onClick={removeStudentsFromClass}
                                        disabled={checkedAssigned.length === 0}
                                        className="flex-1 md:flex-initial flex items-center justify-center gap-1 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 p-2.5 rounded-lg text-xs font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                                    >
                                        Keluarkan <ChevronRight className="w-4 h-4 rotate-180 hidden md:block" />
                                    </button>
                                </div>

                                {/* Right List: Unassigned Students */}
                                <div className="md:col-span-3 border border-slate-200 rounded-xl overflow-hidden flex flex-col h-[350px]">
                                    <div className="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex justify-between items-center">
                                        <span className="text-xs font-bold text-slate-700 uppercase tracking-wider">Siswa Belum Punya Kelas ({unassignedStudents.length})</span>
                                        <button type="button" onClick={() => setCheckedUnassigned(unassignedStudents.map(s => s.id))} className="text-xs text-blue-600 font-semibold hover:underline">Pilih Semua</button>
                                    </div>
                                    <div className="overflow-y-auto p-2 flex-1 divide-y divide-slate-100">
                                        {unassignedStudents.length === 0 ? (
                                            <div className="p-8 text-center text-xs text-slate-400">Semua siswa aktif sudah terdaftar di kelas.</div>
                                        ) : (
                                            unassignedStudents.map(student => (
                                                <label key={student.id} className="flex items-center gap-3 p-2.5 hover:bg-slate-50 rounded-lg cursor-pointer text-sm">
                                                    <input
                                                        type="checkbox"
                                                        checked={checkedUnassigned.includes(student.id)}
                                                        onChange={() => toggleUnassignedCheck(student.id)}
                                                        className="rounded text-blue-600 focus:ring-blue-500/20"
                                                    />
                                                    <div>
                                                        <span className="font-semibold text-slate-800 block">{student.name}</span>
                                                        <span className="text-xs text-slate-400">NIK: {student.nik}</span>
                                                    </div>
                                                </label>
                                            ))
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-2.5">
                            <button type="button" onClick={() => setIsPlottingOpen(false)} className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800">
                                Batal
                            </button>
                            <button type="button" onClick={handleSavePlotting} disabled={saving} className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow-sm">
                                <Save className="w-4 h-4" />
                                {saving ? 'Menyimpan...' : 'Simpan Ploting'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
