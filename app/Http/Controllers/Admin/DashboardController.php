<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\ClassAttendance;
use App\Models\AcademicCalendar;
use App\Enums\AttendanceStatus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // ─── STAT CARDS ────────────────────────────────────────────────
        $totalSiswa  = Student::count();
        $totalGuru   = Teacher::count();
        $kelasAktif  = SchoolClass::count();
        $siswaNoKelas = Student::whereNull('school_class_id')->count();

        // Kehadiran hari ini
        $today = Carbon::today()->toDateString();
        $todayTotal  = ClassAttendance::where('attendance_date', $today)->count();
        $todayHadir  = ClassAttendance::where('attendance_date', $today)->where('status', AttendanceStatus::HADIR->value)->count();
        $kehadiranHariIni = $todayTotal > 0 ? round(($todayHadir / $todayTotal) * 100, 1) : 0;

        // Kehadiran bulan ini
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth   = Carbon::now()->endOfMonth()->toDateString();
        $monthTotal   = ClassAttendance::whereBetween('attendance_date', [$startOfMonth, $endOfMonth])->count();
        $monthHadir   = ClassAttendance::whereBetween('attendance_date', [$startOfMonth, $endOfMonth])->where('status', AttendanceStatus::HADIR->value)->count();
        $kehadiranBulanIni = $monthTotal > 0 ? round(($monthHadir / $monthTotal) * 100, 1) : 0;

        // ─── TREN KEHADIRAN (6 bulan terakhir) ─────────────────────────
        $bulanIndo = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $trenKehadiran = [];
        for ($i = 5; $i >= 0; $i--) {
            $m     = Carbon::now()->subMonths($i);
            $start = $m->copy()->startOfMonth()->toDateString();
            $end   = $m->copy()->endOfMonth()->toDateString();

            $total = ClassAttendance::whereBetween('attendance_date', [$start, $end])->count();
            $hadir = ClassAttendance::whereBetween('attendance_date', [$start, $end])->where('status', AttendanceStatus::HADIR->value)->count();

            $trenKehadiran[] = [
                'month' => $bulanIndo[(int)$m->format('n')],
                'hadir' => $total > 0 ? round(($hadir / $total) * 100, 1) : 0,
                'total' => $total,
            ];
        }

        // ─── REKAP STATUS KEHADIRAN HARI INI ──────────────────────────
        $rekapHariIni = [
            'hadir' => ClassAttendance::where('attendance_date', $today)->where('status', AttendanceStatus::HADIR->value)->count(),
            'sakit' => ClassAttendance::where('attendance_date', $today)->where('status', AttendanceStatus::SAKIT->value)->count(),
            'izin' => ClassAttendance::where('attendance_date', $today)->where('status', AttendanceStatus::IZIN->value)->count(),
            'alpha' => ClassAttendance::where('attendance_date', $today)->where('status', AttendanceStatus::ALPHA->value)->count(),
        ];

        // ─── AKTIVITAS ABSENSI TERBARU ─────────────────────────────────
        $aktivitasTerbaru = ClassAttendance::with(['student.schoolClass'])
            ->orderBy('created_at', 'desc')
            ->take(7)
            ->get()
            ->map(fn($att) => [
                'id'      => $att->id,
                'student' => $att->student?->name ?? 'Unknown',
                'cls'     => $att->student?->schoolClass?->name ?? '-',
                'st'      => $att->status,
                'time'    => $att->created_at?->format('H:i') ?? '-',
                'tanggal' => $att->attendance_date?->format('d/m') ?? '-',
            ]);

        // ─── TOP 5 SISWA (Kehadiran tertinggi) ────────────────────────
        $topStudents = DB::table('students')
            ->join('class_attendances', 'students.id', '=', 'class_attendances.student_id')
            ->select(
                'students.id',
                'students.name',
                'students.school_class_id',
                DB::raw('COUNT(class_attendances.id) as total_attendance'),
                DB::raw("SUM(CASE WHEN class_attendances.status = '".AttendanceStatus::HADIR->value."' THEN 1 ELSE 0 END) as total_hadir")
            )
            ->groupBy('students.id', 'students.name', 'students.school_class_id')
            ->having('total_attendance', '>', 0)
            ->orderByRaw("(SUM(CASE WHEN class_attendances.status = '".AttendanceStatus::HADIR->value."' THEN 1 ELSE 0 END) / COUNT(class_attendances.id)) DESC")
            ->take(5)
            ->get();

        $studentIds    = $topStudents->pluck('id')->toArray();
        $studentClasses = !empty($studentIds)
            ? Student::whereIn('id', $studentIds)->with('schoolClass')->get()->keyBy('id')
            : collect();

        $performaSiswa = $topStudents->map(fn($stu) => [
            'id'              => $stu->id,
            'name'            => $stu->name,
            'cls'             => $studentClasses[$stu->id]?->schoolClass?->name ?? '-',
            'pct'             => $stu->total_attendance > 0
                                    ? round(($stu->total_hadir / $stu->total_attendance) * 100, 1)
                                    : 0,
            'total_hadir'     => $stu->total_hadir,
            'total_pertemuan' => $stu->total_attendance,
        ]);

        // ─── AGENDA HARI INI & MENDATANG ──────────────────────────────
        $agendaMendatang = AcademicCalendar::where('start_date', '>=', $today)
            ->orderBy('start_date')
            ->take(3)
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'title'      => $a->title,
                'start_date' => Carbon::parse($a->start_date)->format('d M'),
                'is_holiday' => (bool) $a->is_holiday,
            ]);

        return response()->json([
            'stats' => [
                'total_siswa'         => $totalSiswa,
                'total_guru'          => $totalGuru,
                'kelas_aktif'         => $kelasAktif,
                'siswa_no_kelas'      => $siswaNoKelas,
                'kehadiran_hari_ini'  => $kehadiranHariIni,
                'kehadiran_bulan_ini' => $kehadiranBulanIni,
                'rekap_hari_ini'      => $rekapHariIni,
            ],
            'charts' => [
                'tren_kehadiran' => $trenKehadiran,
            ],
            'recent' => [
                'aktivitas'       => $aktivitasTerbaru,
                'performa'        => $performaSiswa,
                'agenda_mendatang'=> $agendaMendatang,
            ],
            'generated_at' => Carbon::now()->format('H:i:s'),
        ]);
    }
}
