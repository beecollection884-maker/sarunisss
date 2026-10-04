import React, { useState, useEffect } from 'react';
import { Plus, Search, Edit2, Trash2, Download, Upload, Save, X, RefreshCw, AlertCircle } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface Teacher {
    id: number;
    nip: string;
    nik: string | null;
    name: string;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    religion: string | null;
    employment_status: string | null;
    position: string | null;
    join_date: string | null;
    last_education: string | null;
    major: string | null;
    university: string | null;
    phone: string | null;
    address: string | null;
}

const displayDate = (value?: string | null) => {
    if (!value) return '-';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    } catch {
        return value;
    }
};

const firstValidationError = (error: any) => {
    const errors = error.response?.data?.errors;
    if (!errors) return error.response?.data?.message || 'Gagal menyimpan data';
    return (Object.values(errors).flat()[0] as string) || 'Gagal menyimpan data';
};

export const Guru = () => {
    const [teachers, setTeachers] = useState<Teacher[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    // Modal states
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isImportOpen, setIsImportOpen] = useState(false);
    const [editingTeacher, setEditingTeacher] = useState<Teacher | null>(null);
    const [saving, setSaving] = useState(false);
    const [importing, setImporting] = useState(false);

    // Form fields
    const [nip, setNip] = useState('');
    const [nik, setNik] = useState('');
    const [name, setName] = useState('');
    const [gender, setGender] = useState('L');
    const [birthPlace, setBirthPlace] = useState('');
    const [birthDate, setBirthDate] = useState('');
    const [religion, setReligion] = useState('');
    const [employmentStatus, setEmploymentStatus] = useState('PNS');
    const [position, setPosition] = useState('');
    const [joinDate, setJoinDate] = useState('');
    const [lastEducation, setLastEducation] = useState('');
    const [major, setMajor] = useState('');
    const [university, setUniversity] = useState('');
    const [phone, setPhone] = useState('');
    const [address, setAddress] = useState('');
    const [importFile, setImportFile] = useState<File | null>(null);

    useEffect(() => {
        fetchTeachers();
    }, [currentPage, searchTerm]);

    const fetchTeachers = async () => {
        try {
            const response = await api.get('/admin/guru', {
                params: {
                    page: currentPage,
                    search: searchTerm,
                }
            });
            setTeachers(response.data.data || response.data.data);
            setTotalPages(response.data.last_page || 1);
        } catch (error) {
            toast.error('Gagal memuat data guru');
        } finally {
            setLoading(false);
        }
    };

    const handleOpenCreate = () => {
        setEditingTeacher(null);
        setNip('');
        setNik('');
        setName('');
        setGender('L');
        setBirthPlace('');
        setBirthDate('');
        setReligion('');
        setEmploymentStatus('PNS');
        setPosition('');
        setJoinDate('');
        setLastEducation('');
        setMajor('');
        setUniversity('');
        setPhone('');
        setAddress('');
        setIsFormOpen(true);
    };

    const handleOpenEdit = (teacher: Teacher) => {
        setEditingTeacher(teacher);
        setNip(teacher.nip);
        setNik(teacher.nik || '');
        setName(teacher.name);
        setGender(teacher.gender || 'L');
        setBirthPlace(teacher.birth_place || '');
        setBirthDate(teacher.birth_date || '');
        setReligion(teacher.religion || '');
        setEmploymentStatus(teacher.employment_status || 'PNS');
        setPosition(teacher.position || '');
        setJoinDate(teacher.join_date ? teacher.join_date.slice(0, 10) : '');
        setLastEducation(teacher.last_education || '');
        setMajor(teacher.major || '');
        setUniversity(teacher.university || '');
        setPhone(teacher.phone || '');
        setAddress(teacher.address || '');
        setIsFormOpen(true);
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!nip.trim()) {
            toast.error('NIP wajib diisi.');
            return;
        }

        if (!name.trim()) {
            toast.error('Nama lengkap wajib diisi.');
            return;
        }

        setSaving(true);

        const payload = {
            nip: nip.trim(),
            nik: nik.trim() || null,
            name: name.trim(),
            gender,
            birth_place: birthPlace.trim() || null,
            birth_date: birthDate || null,
            religion: religion.trim() || null,
            employment_status: employmentStatus.trim() || null,
            position: position.trim() || null,
            join_date: joinDate || null,
            last_education: lastEducation.trim() || null,
            major: major.trim() || null,
            university: university.trim() || null,
            phone: phone.trim() || null,
            address: address.trim() || null,
        };
        try {
            if (editingTeacher) {
                await api.put(`/admin/guru/${editingTeacher.id}`, payload);
                toast.success('Data guru berhasil diperbarui!');
            } else {
                await api.post('/admin/guru', payload);
                toast.success('Guru baru berhasil ditambahkan!');
            }
            setIsFormOpen(false);
            fetchTeachers();
        } catch (error: any) {
            toast.error(firstValidationError(error));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Apakah Anda yakin ingin menghapus data guru ini?')) return;
        try {
            await api.delete(`/admin/guru/${id}`);
            toast.success('Guru berhasil dihapus');
            fetchTeachers();
        } catch (error) {
            toast.error('Gagal menghapus guru');
        }
    };

    const handleImport = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!importFile) return;

        setImporting(true);
        const formData = new FormData();
        formData.append('file', importFile);

        try {
            const response = await api.post('/admin/import/guru', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            const result = response.data;
            const summary = `${result.created || 0} dibuat, ${result.updated || 0} diperbarui, ${result.failed || 0} gagal.`;

            if ((result.failed || 0) > 0) {
                const firstError = result.errors?.[0];
                const detail = firstError ? ` Baris ${firstError.row}: ${firstError.messages?.join(', ')}` : '';
                toast.warning(`Import selesai: ${summary}${detail}`);
            } else {
                toast.success(`Import berhasil: ${summary}`);
            }

            setIsImportOpen(false);
            setImportFile(null);
            fetchTeachers();
        } catch (error: any) {
            const data = error.response?.data;
            const firstError = data?.errors?.[0];
            const detail = firstError ? `Baris ${firstError.row}: ${firstError.messages?.join(', ')}` : data?.message;
            toast.error(detail || 'Gagal mengimport data');
        } finally {
            setImporting(false);
        }
    };

    const downloadTemplate = () => {
        window.open('/api/v1/admin/import/template/guru', '_blank');
    };

    return (
        <div className="p-6 max-w-7xl mx-auto">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-800">Data Guru</h1>
                    <p className="text-sm text-slate-500 mt-1">Kelola seluruh data tenaga pendidik dan status mengajar mereka.</p>
                </div>
                <div className="flex flex-wrap gap-2.5">
                    <button onClick={downloadTemplate} className="flex items-center gap-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        <Download className="w-4 h-4" />
                        Download Template
                    </button>
                    <button onClick={() => setIsImportOpen(true)} className="flex items-center gap-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        <Upload className="w-4 h-4" />
                        Import CSV
                    </button>
                    <button onClick={handleOpenCreate} className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        <Plus className="w-4 h-4" />
                        Tambah Guru
                    </button>
                </div>
            </div>

            {/* Filter & Search */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm mb-6 p-4 flex gap-4">
                <div className="relative flex-1 max-w-md">
                    <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                    <input
                        type="text"
                        placeholder="Cari guru berdasarkan NIP, NIK, atau nama..."
                        value={searchTerm}
                        onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
                        className="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                    />
                </div>
            </div>

            {/* Teacher Table */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                {loading ? (
                    <div className="p-8 text-center text-slate-500 flex items-center justify-center gap-2">
                        <RefreshCw className="w-5 h-5 animate-spin" />
                        Memuat data...
                    </div>
                ) : teachers.length === 0 ? (
                    <div className="p-8 text-center text-slate-500">
                        Tidak ada data guru ditemukan.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1280px] text-left border-collapse">
                            <thead>
                                <tr className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                    <th className="py-3 px-4">NIP / NIK</th>
                                    <th className="py-3 px-4">Nama Guru</th>
                                    <th className="py-3 px-4">Lahir</th>
                                    <th className="py-3 px-4">Gender / Agama</th>
                                    <th className="py-3 px-4">Kepegawaian</th>
                                    <th className="py-3 px-4">Pendidikan</th>
                                    <th className="py-3 px-4">Kontak</th>
                                    <th className="py-3 px-4">Alamat</th>
                                    <th className="py-3 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                {teachers.map((teacher) => (
                                    <tr key={teacher.id} className="hover:bg-slate-50/50 transition-colors">
                                        <td className="py-3.5 px-4 align-top">
                                            <span className="font-semibold text-slate-900 block">{teacher.nip}</span>
                                            <span className="text-xs text-slate-400">NIK {teacher.nik || '-'}</span>
                                        </td>
                                        <td className="py-3.5 px-4 align-top font-medium text-slate-900">{teacher.name}</td>
                                        <td className="py-3.5 px-4 align-top text-xs">
                                            <span className="font-medium text-slate-700 block">{teacher.birth_place || '-'}</span>
                                            <span className="text-slate-500">{displayDate(teacher.birth_date)}</span>
                                        </td>
                                        <td className="py-3.5 px-4 align-top text-xs">
                                            <span className="font-medium text-slate-700 block">{teacher.gender === 'L' ? 'Laki-laki' : teacher.gender === 'P' ? 'Perempuan' : '-'}</span>
                                            <span className="text-slate-500">{teacher.religion || '-'}</span>
                                        </td>
                                        <td className="py-3.5 px-4 align-top text-xs">
                                            <span className="bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded-full font-medium border border-slate-200">{teacher.employment_status || '-'}</span>
                                            <span className="block mt-2 text-slate-600">{teacher.position || '-'}</span>
                                            <span className="block mt-1 text-slate-400">Mulai {displayDate(teacher.join_date)}</span>
                                        </td>
                                        <td className="py-3.5 px-4 align-top text-xs text-slate-500">
                                            <span className="font-medium text-slate-700 block">{teacher.last_education || '-'}</span>
                                            <span className="block mt-1">{teacher.major || '-'}</span>
                                            <span className="block mt-1 text-slate-400 max-w-[180px] truncate">{teacher.university || '-'}</span>
                                        </td>
                                        <td className="py-3.5 px-4 align-top text-xs text-slate-500">{teacher.phone || '-'}</td>
                                        <td className="py-3.5 px-4 align-top text-xs text-slate-500 max-w-xs truncate">{teacher.address || '-'}</td>
                                        <td className="py-3.5 px-4 align-top text-right">
                                            <div className="flex justify-end gap-1.5">
                                                <button onClick={() => handleOpenEdit(teacher)} className="p-1.5 hover:bg-slate-100 text-slate-600 hover:text-slate-900 rounded-md transition-colors">
                                                    <Edit2 className="w-4 h-4" />
                                                </button>
                                                <button onClick={() => handleDelete(teacher.id)} className="p-1.5 hover:bg-red-50 text-slate-600 hover:text-red-600 rounded-md transition-colors">
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {totalPages > 1 && (
                    <div className="px-4 py-3 border-t border-slate-200 flex items-center justify-between bg-slate-50">
                        <button
                            disabled={currentPage === 1}
                            onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                            className="px-3 py-1 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-lg text-sm disabled:opacity-50"
                        >
                            Sebelumnya
                        </button>
                        <span className="text-xs text-slate-500 font-semibold">Halaman {currentPage} dari {totalPages}</span>
                        <button
                            disabled={currentPage === totalPages}
                            onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                            className="px-3 py-1 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-lg text-sm disabled:opacity-50"
                        >
                            Selanjutnya
                        </button>
                    </div>
                )}
            </div>

            {/* Modal CRUD Form */}
            {isFormOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 className="font-semibold text-slate-800">
                                {editingTeacher ? 'Edit Data Guru' : 'Tambah Guru Baru'}
                            </h2>
                            <button onClick={() => setIsFormOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSave} className="overflow-y-auto p-5 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">NIP (Wajib)</label>
                                    <input type="text" value={nip} onChange={(e) => setNip(e.target.value)} required className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">NIK</label>
                                    <input type="text" value={nik} onChange={(e) => setNik(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Nama Lengkap (Wajib)</label>
                                <input type="text" value={name} onChange={(e) => setName(e.target.value)} required className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Gender</label>
                                    <select value={gender} onChange={(e) => setGender(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Agama</label>
                                    <input type="text" value={religion} onChange={(e) => setReligion(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Tempat Lahir</label>
                                    <input type="text" value={birthPlace} onChange={(e) => setBirthPlace(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Tanggal Lahir</label>
                                    <input type="date" value={birthDate} onChange={(e) => setBirthDate(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Status Kepegawaian</label>
                                    <input type="text" value={employmentStatus} onChange={(e) => setEmploymentStatus(e.target.value)} placeholder="Contoh: PNS, GTY, Honorer" className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Jabatan</label>
                                    <input type="text" value={position} onChange={(e) => setPosition(e.target.value)} placeholder="Contoh: Guru Matematika" className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Tanggal Mulai Tugas</label>
                                    <input type="date" value={joinDate} onChange={(e) => setJoinDate(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Pendidikan Terakhir</label>
                                    <input type="text" value={lastEducation} onChange={(e) => setLastEducation(e.target.value)} placeholder="Contoh: S1" className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Jurusan</label>
                                    <input type="text" value={major} onChange={(e) => setMajor(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Universitas</label>
                                    <input type="text" value={university} onChange={(e) => setUniversity(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-slate-700 mb-1">Nomor HP</label>
                                    <input type="text" value={phone} onChange={(e) => setPhone(e.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-semibold text-slate-700 mb-1">Alamat Rumah</label>
                                <textarea value={address} onChange={(e) => setAddress(e.target.value)} rows={3} className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                            </div>

                            <div className="pt-2 border-t border-slate-100 flex justify-end gap-2.5">
                                <button type="button" onClick={() => setIsFormOpen(false)} className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800">
                                    Batal
                                </button>
                                <button type="submit" disabled={saving} className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm disabled:opacity-75">
                                    <Save className="w-4 h-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Import */}
            {isImportOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 className="font-semibold text-slate-800">Import Data Guru</h2>
                            <button onClick={() => setIsImportOpen(false)} className="text-slate-400 hover:text-slate-600 p-1">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleImport} className="p-5 space-y-4">
                            <div className="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:border-blue-500 transition-colors">
                                <Upload className="w-10 h-10 text-slate-400 mx-auto mb-2" />
                                <span className="text-xs text-slate-500 block mb-3">Format file yang diperbolehkan: .csv atau .txt dari Excel</span>
                                <input
                                    type="file"
                                    accept=".csv,.txt,text/csv,text/plain"
                                    onChange={(e) => setImportFile(e.target.files?.[0] || null)}
                                    className="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 file:cursor-pointer hover:file:bg-blue-100"
                                    required
                                />
                            </div>

                            <div className="flex gap-2 text-xs text-slate-500 bg-amber-50 border border-amber-100 rounded-lg p-3">
                                <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0" />
                                <p>Pastikan data dalam CSV memiliki kolom-kolom yang sesuai dengan template. Download template terlebih dahulu jika belum memilikinya.</p>
                            </div>

                            <div className="flex justify-end gap-2">
                                <button type="button" onClick={() => setIsImportOpen(false)} className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800">
                                    Batal
                                </button>
                                <button type="submit" disabled={importing} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-75">
                                    {importing ? 'Mengimport...' : 'Mulai Import'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};
