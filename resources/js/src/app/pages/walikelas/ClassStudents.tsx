import React, { useEffect, useMemo, useState } from 'react';
import { AlertCircle, Download, RefreshCw, Search, Users } from 'lucide-react';
import { toast } from 'sonner';
import api from '../../services/api';

interface SchoolClass {
    id: number;
    name: string;
}

interface ParentUser {
    id?: number;
    name?: string | null;
    email?: string | null;
}

interface StudentDetail {
    religion?: string | null;
    birth_place?: string | null;
    address_street?: string | null;
    address_village?: string | null;
    address_district?: string | null;
    address_province?: string | null;
    address_city?: string | null;
    father_name?: string | null;
    father_education?: string | null;
    father_occupation?: string | null;
    mother_name?: string | null;
    mother_education?: string | null;
    mother_occupation?: string | null;
    parent_address?: string | null;
    parent_province?: string | null;
    parent_city?: string | null;
    postal_code?: string | null;
    parent_phone?: string | null;
    previous_school?: string | null;
}

interface Student {
    id: number;
    nik: string;
    nisn?: string | null;
    name: string;
    gender?: 'L' | 'P' | string | null;
    birth_date?: string | null;
    phone?: string | null;
    address?: string | null;
    school_class_id?: number | null;
    school_class?: SchoolClass | null;
    detail_siswa?: StudentDetail | null;
    parent_user?: ParentUser | null;
}

type ExportFormat = 'csv' | 'xls';

const empty = (value?: string | number | null) => {
    const normalized = value === undefined || value === null ? '' : String(value).trim();
    return normalized || '-';
};

const formatGender = (gender?: string | null) => {
    if (gender === 'L') return 'Laki-laki';
    if (gender === 'P') return 'Perempuan';
    return '-';
};

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

const exportHeaders = [
    'No',
    'NIK',
    'NISN',
    'Nama',
    'Kelas',
    'Jenis Kelamin',
    'Tempat Lahir',
    'Tanggal Lahir',
    'Agama',
    'HP Siswa',
    'Alamat Siswa',
    'Kelurahan/Desa',
    'Kecamatan',
    'Kota/Kabupaten',
    'Provinsi',
    'Nama Ayah',
    'Pendidikan Ayah',
    'Pekerjaan Ayah',
    'Nama Ibu',
    'Pendidikan Ibu',
    'Pekerjaan Ibu',
    'Akun Orang Tua',
    'Email Orang Tua',
    'HP Orang Tua',
    'Alamat Orang Tua',
    'Kota Orang Tua',
    'Provinsi Orang Tua',
    'Kode Pos',
    'Sekolah Asal',
];

const studentToExportRow = (student: Student, index: number) => {
    const detail = student.detail_siswa || {};

    return [
        index + 1,
        empty(student.nik),
        empty(student.nisn),
        empty(student.name),
        empty(student.school_class?.name),
        formatGender(student.gender),
        empty(detail.birth_place),
        formatDate(student.birth_date),
        empty(detail.religion),
        empty(student.phone),
        empty(detail.address_street || student.address),
        empty(detail.address_village),
        empty(detail.address_district),
        empty(detail.address_city),
        empty(detail.address_province),
        empty(detail.father_name),
        empty(detail.father_education),
        empty(detail.father_occupation),
        empty(detail.mother_name),
        empty(detail.mother_education),
        empty(detail.mother_occupation),
        empty(student.parent_user?.name),
        empty(student.parent_user?.email),
        empty(detail.parent_phone),
        empty(detail.parent_address),
        empty(detail.parent_city),
        empty(detail.parent_province),
        empty(detail.postal_code),
        empty(detail.previous_school),
    ];
};

const csvCell = (value: string | number) => `"${String(value).replace(/"/g, '""')}"`;

const downloadBlob = (content: string, filename: string, type: string) => {
    const blob = new Blob([content], { type });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
};

export const ClassStudents = ({
    pageTitle = 'Data Kelas',
    pageDescription = 'Lihat seluruh siswa pada kelas perwalian yang diampu.',
}: {
    pageTitle?: string;
    pageDescription?: string;
}) => {
    const [classes, setClasses] = useState<SchoolClass[]>([]);
    const [students, setStudents] = useState<Student[]>([]);
    const [selectedClassId, setSelectedClassId] = useState('');
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');

    const fetchData = async () => {
        setLoading(true);
        setLoadError('');
        try {
            const [classResponse, studentResponse] = await Promise.all([
                api.get('/walikelas/kelas'),
                api.get('/walikelas/siswa'),
            ]);

            const classList: SchoolClass[] = classResponse.data.data || [];
            setClasses(classList);
            setStudents(studentResponse.data.data || []);

            if (!selectedClassId && classList.length === 1) {
                setSelectedClassId(String(classList[0].id));
            }
        } catch (error: any) {
            const message = error.response?.data?.message || 'Gagal memuat data kelas.';
            setLoadError(message);
            toast.error(message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, []);

    const filteredStudents = useMemo(() => {
        const keyword = query.trim().toLowerCase();

        return students.filter((student) => {
            const detail = student.detail_siswa || {};
            const matchesClass = selectedClassId ? String(student.school_class_id || student.school_class?.id || '') === selectedClassId : true;
            const searchableValues = [
                student.name,
                student.nik,
                student.nisn || '',
                student.phone || '',
                student.address || '',
                student.school_class?.name || '',
                detail.religion || '',
                detail.birth_place || '',
                detail.address_street || '',
                detail.address_village || '',
                detail.address_district || '',
                detail.address_city || '',
                detail.address_province || '',
                detail.father_name || '',
                detail.father_occupation || '',
                detail.mother_name || '',
                detail.mother_occupation || '',
                detail.parent_phone || '',
                detail.parent_address || '',
                detail.previous_school || '',
                student.parent_user?.name || '',
                student.parent_user?.email || '',
            ];
            const matchesQuery = keyword
                ? searchableValues.some((value) => value.toLowerCase().includes(keyword))
                : true;

            return matchesClass && matchesQuery;
        });
    }, [students, selectedClassId, query]);

    const selectedClass = classes.find((item) => String(item.id) === selectedClassId) || null;
    const maleCount = filteredStudents.filter((student) => student.gender === 'L').length;
    const femaleCount = filteredStudents.filter((student) => student.gender === 'P').length;

    const handleExport = (format: ExportFormat) => {
        if (filteredStudents.length === 0) {
            toast.error('Tidak ada data untuk diexport.');
            return;
        }

        const rows = filteredStudents.map(studentToExportRow);
        const scope = selectedClass?.name ? selectedClass.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') : 'semua-kelas';
        const filename = `data-kelas-perwalian-${scope}.${format}`;

        if (format === 'csv') {
            const csv = [exportHeaders, ...rows]
                .map((row) => row.map(csvCell).join(','))
                .join('\n');
            downloadBlob(csv, filename, 'text/csv;charset=utf-8;');
            toast.success('Data kelas berhasil diexport.');
            return;
        }

        const htmlRows = [exportHeaders, ...rows]
            .map((row, rowIndex) => `<tr>${row.map((cell) => rowIndex === 0 ? `<th>${cell}</th>` : `<td>${cell}</td>`).join('')}</tr>`)
            .join('');
        const html = `<html><head><meta charset="utf-8" /></head><body><table border="1">${htmlRows}</table></body></html>`;
        downloadBlob(html, filename, 'application/vnd.ms-excel;charset=utf-8;');
        toast.success('Data kelas berhasil diexport.');
    };

    if (loadError && !loading) {
        return (
            <div className="min-h-full bg-slate-50 p-6">
                <div className="mx-auto max-w-3xl rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-800 shadow-sm">
                    <div className="flex items-start gap-3">
                        <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <h1 className="font-semibold">Data kelas belum tersedia</h1>
                            <p className="mt-1 text-sm">{loadError}</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-full bg-slate-50 px-2 py-4 sm:p-6">
            <div className="mx-auto w-full max-w-7xl space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">{pageTitle}</h1>
                        <p className="mt-1 text-sm text-slate-500">{pageDescription}</p>
                    </div>
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <button
                            onClick={() => handleExport('csv')}
                            disabled={loading || filteredStudents.length === 0}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 disabled:opacity-60"
                        >
                            <Download className="h-4 w-4" />
                            CSV
                        </button>
                        <button
                            onClick={() => handleExport('xls')}
                            disabled={loading || filteredStudents.length === 0}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-100 disabled:opacity-60"
                        >
                            <Download className="h-4 w-4" />
                            XLS
                        </button>
                        <button
                            onClick={fetchData}
                            disabled={loading}
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60"
                        >
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            Muat Ulang
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-xs font-semibold uppercase text-slate-400">Kelas</p>
                        <p className="mt-1 text-xl font-bold text-slate-900">{selectedClass?.name || (classes.length ? 'Semua' : '-')}</p>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-xs font-semibold uppercase text-slate-400">Total Siswa</p>
                        <p className="mt-1 text-xl font-bold text-slate-900">{filteredStudents.length}</p>
                    </div>
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-700 shadow-sm">
                        <p className="text-xs font-semibold uppercase">Laki-laki</p>
                        <p className="mt-1 text-xl font-bold">{maleCount}</p>
                    </div>
                    <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-rose-700 shadow-sm">
                        <p className="text-xs font-semibold uppercase">Perempuan</p>
                        <p className="mt-1 text-xl font-bold">{femaleCount}</p>
                    </div>
                </div>

                <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-[220px_minmax(0,1fr)]">
                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Kelas</span>
                            <select
                                value={selectedClassId}
                                onChange={(event) => setSelectedClassId(event.target.value)}
                                className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">Semua kelas perwalian</option>
                                {classes.map((item) => (
                                    <option key={item.id} value={item.id}>Kelas {item.name}</option>
                                ))}
                            </select>
                        </label>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-slate-600">Cari Siswa</span>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Cari nama, NIK, NISN, orang tua, HP, alamat, atau sekolah asal..."
                                    className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>
                        </label>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 p-4">
                        <div className="flex items-center gap-2">
                            <Users className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-semibold text-slate-900">Daftar Siswa Lengkap</h2>
                        </div>
                    </div>

                    {loading ? (
                        <div className="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
                            <RefreshCw className="h-4 w-4 animate-spin" />
                            Memuat data siswa...
                        </div>
                    ) : filteredStudents.length === 0 ? (
                        <div className="p-10 text-center text-sm text-slate-500">Tidak ada siswa yang sesuai filter.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1180px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Identitas</th>
                                        <th className="px-4 py-3">Biodata</th>
                                        <th className="px-4 py-3">Kelas</th>
                                        <th className="px-4 py-3">Kontak Siswa</th>
                                        <th className="px-4 py-3">Alamat Siswa</th>
                                        <th className="px-4 py-3">Orang Tua</th>
                                        <th className="px-4 py-3">Kontak Orang Tua</th>
                                        <th className="px-4 py-3">Sekolah Asal</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredStudents.map((student) => {
                                        const detail = student.detail_siswa || {};

                                        return (
                                            <tr key={student.id} className="align-top hover:bg-slate-50/60">
                                                <td className="px-4 py-3">
                                                    <p className="font-semibold text-slate-900">{student.name}</p>
                                                    <p className="mt-1 text-xs text-slate-500">NIK: {empty(student.nik)}</p>
                                                    <p className="text-xs text-slate-500">NISN: {empty(student.nisn)}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    <p>{formatGender(student.gender)}</p>
                                                    <p className="mt-1 text-xs text-slate-500">{empty(detail.birth_place)}, {formatDate(student.birth_date)}</p>
                                                    <p className="text-xs text-slate-500">Agama: {empty(detail.religion)}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{empty(student.school_class?.name)}</td>
                                                <td className="px-4 py-3 text-slate-600">{empty(student.phone)}</td>
                                                <td className="max-w-xs px-4 py-3 text-slate-600">
                                                    <p>{empty(detail.address_street || student.address)}</p>
                                                    <p className="mt-1 text-xs text-slate-500">{[detail.address_village, detail.address_district, detail.address_city, detail.address_province].filter(Boolean).join(', ') || '-'}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    <p>Ayah: {empty(detail.father_name)}</p>
                                                    <p className="text-xs text-slate-500">{empty(detail.father_education)} / {empty(detail.father_occupation)}</p>
                                                    <p className="mt-2">Ibu: {empty(detail.mother_name)}</p>
                                                    <p className="text-xs text-slate-500">{empty(detail.mother_education)} / {empty(detail.mother_occupation)}</p>
                                                </td>
                                                <td className="max-w-xs px-4 py-3 text-slate-600">
                                                    <p>Akun: {empty(student.parent_user?.name)}</p>
                                                    <p className="text-xs text-slate-500">{empty(student.parent_user?.email)}</p>
                                                    <p className="mt-1">HP: {empty(detail.parent_phone)}</p>
                                                    <p className="text-xs text-slate-500">{empty(detail.parent_address)}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{empty(detail.previous_school)}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </div>
    );
};