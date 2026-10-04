<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\AcademicCalendar;
use App\Models\AppSetting;
use App\Models\ClassAttendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\StudentViolation;
use App\Models\Subject;
use App\Models\SubjectAttendance;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $academicYear = '2025/2026';
        $semester = 'genap';

        $admin = $this->user('Admin Sekolah', 'admin@sarunis.test', [UserRole::ADMIN->value]);
        $guruMapelUser = $this->user('Guru Mapel', 'guru.mapel@sarunis.test', [UserRole::GURU_MAPEL->value]);
        $waliKelasUser = $this->user('Wali Kelas', 'walikelas@sarunis.test', [UserRole::GURU_MAPEL->value]);
        $siswaUser = $this->user('Siswa Demo', 'siswa@sarunis.test', [UserRole::SISWA->value]);
        $orangTuaUser = $this->user('Orang Tua Demo', 'orangtua@sarunis.test', [UserRole::ORANG_TUA->value]);
        $wakasekUser = $this->user('Wakasek Kesiswaan', 'wakasek@sarunis.test', [UserRole::WAKASEK_KESISWAAN->value]);
        $guruPiketUser = $this->user('Guru Piket', 'gurupiket@sarunis.test', [UserRole::GURU_PIKET->value]);

        $teachers = [
            'math' => $this->teacher($guruMapelUser, '198801010001', 'Budi Santoso', '081200000101', 'Guru Matematika', 'S1 Pendidikan Matematika'),
            'homeroom' => $this->teacher($waliKelasUser, '198802020002', 'Siti Aminah', '081200000102', 'Wali Kelas X IPA 1', 'S1 Bimbingan Konseling'),
            'language' => $this->teacher(null, '198804040004', 'Dewi Lestari', '081200000104', 'Guru Bahasa Indonesia', 'S1 Sastra Indonesia'),
            'civics' => $this->teacher(null, '198805050005', 'Ahmad Fauzi', '081200000105', 'Guru PPKn', 'S1 Pendidikan Pancasila'),
        ];

        $subjects = [
            'math' => $this->subject('MAT', 'Matematika', 4, 'Aljabar, fungsi, dan pemecahan masalah.'),
            'biology' => $this->subject('BIO', 'Biologi', 3, 'Sel, ekosistem, dan praktikum dasar.'),
            'indonesian' => $this->subject('BIN', 'Bahasa Indonesia', 4, 'Literasi, teks argumentasi, dan presentasi.'),
            'civics' => $this->subject('PKN', 'Pendidikan Pancasila', 2, 'Kewarganegaraan dan profil pelajar Pancasila.'),
            'english' => $this->subject('BIG', 'Bahasa Inggris', 3, 'Conversation, reading, dan writing.'),
        ];

        $classes = [
            'x1' => $this->schoolClass('X IPA 1', 'X', $academicYear, $teachers['homeroom']->id, 'Kelas showcase untuk akun wali kelas.'),
            'x2' => $this->schoolClass('X IPA 2', 'X', $academicYear, $teachers['math']->id, 'Kelas showcase untuk Guru Mapel Matematika yang juga menjadi Wali Kelas.'),
            'xi1' => $this->schoolClass('XI IPS 1', 'XI', $academicYear, $teachers['language']->id, 'Kelas tambahan agar data admin lebih kaya.'),
        ];

        $students = [
            'andi' => $this->student($siswaUser, $orangTuaUser, $classes['x1'], '10001', '3000000001', 'Andi Saputra', 'L', '2010-01-10', '081300000201'),
            'bunga' => $this->student(null, null, $classes['x1'], '10002', '3000000002', 'Bunga Maharani', 'P', '2010-02-11', '081300000202'),
            'cahyo' => $this->student(null, null, $classes['x1'], '10003', '3000000003', 'Cahyo Nugroho', 'L', '2010-03-12', '081300000203'),
            'dinda' => $this->student(null, null, $classes['x2'], '10004', '3000000004', 'Dinda Permata', 'P', '2010-04-13', '081300000204'),
            'eko' => $this->student(null, null, $classes['x2'], '10005', '3000000005', 'Eko Prasetyo', 'L', '2010-05-14', '081300000205'),
            'fira' => $this->student(null, null, $classes['x2'], '10006', '3000000006', 'Fira Azzahra', 'P', '2010-06-15', '081300000206'),
            'gilang' => $this->student(null, null, $classes['xi1'], '10007', '3000000007', 'Gilang Pratama', 'L', '2009-07-16', '081300000207'),
            'hana' => $this->student(null, null, $classes['xi1'], '10008', '3000000008', 'Hana Fitriani', 'P', '2009-08-17', '081300000208'),
        ];

        $classes['x1']->subjects()->sync([$subjects['math']->id, $subjects['indonesian']->id, $subjects['civics']->id, $subjects['english']->id]);
        $classes['x2']->subjects()->sync([$subjects['math']->id, $subjects['biology']->id, $subjects['civics']->id, $subjects['english']->id]);
        $classes['xi1']->subjects()->sync([$subjects['indonesian']->id, $subjects['civics']->id, $subjects['english']->id]);

        $assignments = [
            'math_x1_today' => $this->assignment($teachers['math'], $subjects['math'], $classes['x1'], $academicYear, $today->dayOfWeekIso, '07:00', '08:30', 'R-101'),
            'math_x2_next' => $this->assignment($teachers['math'], $subjects['math'], $classes['x2'], $academicYear, $this->weekDay($today, 1), '07:00', '08:30', 'R-102'),
            'bio_x2_today' => $this->assignment($teachers['language'], $subjects['biology'], $classes['x2'], $academicYear, $today->dayOfWeekIso, '09:00', '10:30', 'Lab Bio'),
            'bin_x1_next' => $this->assignment($teachers['language'], $subjects['indonesian'], $classes['x1'], $academicYear, $this->weekDay($today, 1), '10:45', '12:15', 'R-103'),
            'pkn_x2_next' => $this->assignment($teachers['civics'], $subjects['civics'], $classes['x2'], $academicYear, $this->weekDay($today, 2), '08:00', '09:30', 'R-104'),
            'eng_xi1_next' => $this->assignment($teachers['language'], $subjects['english'], $classes['xi1'], $academicYear, $this->weekDay($today, 3), '13:00', '14:30', 'R-201'),
        ];

        $subjects['math']->teachers()->syncWithoutDetaching([$teachers['math']->id]);
        $subjects['biology']->teachers()->syncWithoutDetaching([$teachers['language']->id]);
        $subjects['indonesian']->teachers()->syncWithoutDetaching([$teachers['language']->id]);
        $subjects['civics']->teachers()->syncWithoutDetaching([$teachers['civics']->id]);
        $subjects['english']->teachers()->syncWithoutDetaching([$teachers['language']->id]);

        $this->subjectAttendance($assignments['math_x1_today'], $teachers['math'], $today->copy()->subDay(), [
            [$students['andi'], AttendanceStatus::HADIR->value, null],
            [$students['bunga'], AttendanceStatus::IZIN->value, 'Izin keluarga'],
            [$students['cahyo'], AttendanceStatus::HADIR->value, null],
        ]);

        $this->subjectAttendance($assignments['math_x1_today'], $teachers['math'], $today, [
            [$students['andi'], AttendanceStatus::HADIR->value, null],
            [$students['bunga'], AttendanceStatus::HADIR->value, null],
            [$students['cahyo'], AttendanceStatus::SAKIT->value, 'Demam ringan'],
        ]);

        $this->subjectAttendance($assignments['math_x2_next'], $teachers['math'], $today->copy()->subDay(), [
            [$students['dinda'], AttendanceStatus::HADIR->value, null],
            [$students['eko'], AttendanceStatus::HADIR->value, null],
            [$students['fira'], AttendanceStatus::IZIN->value, 'Lomba tingkat kota'],
        ]);

        $this->subjectAttendance($assignments['bio_x2_today'], $teachers['language'], $today, [
            [$students['dinda'], AttendanceStatus::HADIR->value, null],
            [$students['eko'], AttendanceStatus::ALPHA->value, 'Belum ada keterangan'],
            [$students['fira'], AttendanceStatus::HADIR->value, null],
        ]);

        $this->classAttendance($classes['x1'], $teachers['homeroom'], $today, [
            [$students['andi'], AttendanceStatus::HADIR->value, null],
            [$students['bunga'], AttendanceStatus::HADIR->value, null],
            [$students['cahyo'], AttendanceStatus::SAKIT->value, 'Izin UKS'],
        ]);

        $this->classAttendance($classes['x2'], $teachers['math'], $today, [
            [$students['dinda'], AttendanceStatus::HADIR->value, null],
            [$students['eko'], AttendanceStatus::ALPHA->value, 'Perlu konfirmasi orang tua'],
            [$students['fira'], AttendanceStatus::IZIN->value, 'Acara keluarga'],
        ]);

        $this->classAttendance($classes['xi1'], $teachers['language'], $today->copy()->subDay(), [
            [$students['gilang'], AttendanceStatus::HADIR->value, null],
            [$students['hana'], AttendanceStatus::HADIR->value, null],
        ]);

        StudentNote::query()->create([
            'student_id' => $students['eko']->id,
            'teacher_id' => $teachers['language']->id,
            'user_id' => $admin->id,
            'title' => 'Konfirmasi ketidakhadiran',
            'category' => 'absensi',
            'note' => 'Hubungi orang tua untuk memastikan alasan tidak hadir.',
            'follow_up_at' => $today->copy()->addDay()->toDateString(),
        ]);

        StudentNote::query()->create([
            'student_id' => $students['cahyo']->id,
            'teacher_id' => $teachers['homeroom']->id,
            'user_id' => $waliKelasUser->id,
            'title' => 'Pantau kondisi kesehatan',
            'category' => 'kesehatan',
            'note' => 'Siswa sempat sakit ringan, cek kembali saat masuk kelas.',
            'follow_up_at' => $today->copy()->addDays(2)->toDateString(),
        ]);

        // ── Data Pelanggaran Siswa ──
        $this->violations($wakasekUser, $guruPiketUser, $students, $today);

        $this->settings($academicYear, $semester, $today);
        $this->calendar($admin, $academicYear, $semester, $today);

        $roleService = app(UserRoleService::class);
        foreach ($teachers as $teacher) {
            $teacher->load('user');
            $roleService->syncTeacherRoles($teacher);
        }
        foreach ($students as $student) {
            $student->load('user');
            $roleService->syncStudentRole($student);
        }
        $admin->forceFill(['roles' => [UserRole::ADMIN->value]])->save();
        $orangTuaUser->forceFill(['roles' => [UserRole::ORANG_TUA->value]])->save();
        $wakasekUser->forceFill(['roles' => [UserRole::WAKASEK_KESISWAAN->value]])->save();
        $guruPiketUser->forceFill(['roles' => [UserRole::GURU_PIKET->value]])->save();
    }

    protected function user(string $name, string $email, array $roles): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'email_verified_at' => now(),
            'roles' => $roles,
        ]);
    }

    protected function teacher(?User $user, string $nip, string $name, string $phone, string $position, string $education): Teacher
    {
        return Teacher::query()->create([
            'user_id' => $user?->id,
            'nip' => $nip,
            'nik' => '33'.$nip,
            'name' => $name,
            'birth_place' => 'Bandung',
            'birth_date' => '1988-01-10',
            'gender' => str_contains($name, 'Siti') || str_contains($name, 'Dewi') ? 'P' : 'L',
            'religion' => 'Islam',
            'employment_status' => 'Guru Tetap',
            'position' => $position,
            'join_date' => '2020-07-01',
            'last_education' => $education,
            'major' => $education,
            'university' => 'Universitas Pendidikan Indonesia',
            'phone' => $phone,
            'address' => 'Jl. Pendidikan No. '.substr($nip, -2),
        ]);
    }

    protected function subject(string $code, string $name, int $lessonHours, string $description): Subject
    {
        return Subject::query()->create([
            'code' => $code,
            'name' => $name,
            'lesson_hours' => $lessonHours,
            'description' => $description,
        ]);
    }

    protected function schoolClass(string $name, string $level, string $academicYear, int $homeroomTeacherId, string $description): SchoolClass
    {
        return SchoolClass::query()->create([
            'name' => $name,
            'level' => $level,
            'academic_year' => $academicYear,
            'homeroom_teacher_id' => $homeroomTeacherId,
            'description' => $description,
        ]);
    }

    protected function student(?User $user, ?User $parentUser, SchoolClass $class, string $nik, string $nisn, string $name, string $gender, string $birthDate, string $phone): Student
    {
        $student = Student::query()->create([
            'user_id' => $user?->id,
            'parent_user_id' => $parentUser?->id,
            'school_class_id' => $class->id,
            'nik' => $nik,
            'nisn' => $nisn,
            'name' => $name,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'phone' => $phone,
            'address' => 'Jl. Siswa No. '.substr($nik, -2),
        ]);

        $student->detailSiswa()->create([
            'religion' => 'Islam',
            'birth_place' => 'Bandung',
            'address_street' => 'Jl. Siswa No. '.substr($nik, -2),
            'address_village' => 'Cisaranten',
            'address_district' => 'Arcamanik',
            'address_province' => 'Jawa Barat',
            'address_city' => 'Bandung',
            'father_name' => 'Bapak '.$name,
            'father_education' => 'SMA',
            'father_occupation' => 'Wiraswasta',
            'mother_name' => 'Ibu '.$name,
            'mother_education' => 'SMA',
            'mother_occupation' => 'Ibu Rumah Tangga',
            'parent_address' => 'Jl. Siswa No. '.substr($nik, -2),
            'parent_province' => 'Jawa Barat',
            'parent_city' => 'Bandung',
            'postal_code' => '40291',
            'parent_phone' => $phone,
            'previous_school' => 'SMP Negeri Contoh',
        ]);

        return $student;
    }

    protected function assignment(Teacher $teacher, Subject $subject, SchoolClass $class, string $academicYear, int $day, string $start, string $end, string $room): TeachingAssignment
    {
        return TeachingAssignment::query()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'academic_year' => $academicYear,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'room' => $room,
        ]);
    }

    protected function subjectAttendance(TeachingAssignment $assignment, Teacher $teacher, Carbon $date, array $rows): void
    {
        foreach ($rows as [$student, $status, $notes]) {
            SubjectAttendance::query()->create([
                'teaching_assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'recorded_by_teacher_id' => $teacher->id,
                'attendance_date' => $date->toDateString(),
                'status' => $status,
                'notes' => $notes,
            ]);
        }
    }

    protected function classAttendance(SchoolClass $class, Teacher $teacher, Carbon $date, array $rows): void
    {
        foreach ($rows as [$student, $status, $notes]) {
            ClassAttendance::query()->create([
                'school_class_id' => $class->id,
                'student_id' => $student->id,
                'recorded_by_teacher_id' => $teacher->id,
                'attendance_date' => $date->toDateString(),
                'status' => $status,
                'notes' => $notes,
            ]);
        }
    }

    protected function settings(string $academicYear, string $semester, Carbon $today): void
    {
        $settings = [
            ['school_name', 'Nama Sekolah', 'SMA Sarunis Showcase', 'text', 'Nama sekolah yang tampil pada dashboard.'],
            ['academic_year', 'Tahun Ajaran Aktif', $academicYear, 'text', 'Tahun ajaran aktif untuk showcase.'],
            ['active_semester', 'Semester Aktif', $semester, 'select', 'Semester aktif untuk absensi dan kalender.'],
            ['school_start_date', 'Tanggal Mulai KBM', $today->copy()->subMonths(6)->toDateString(), 'text', 'Tanggal mulai KBM.'],
            ['school_end_date', 'Tanggal Akhir KBM', $today->copy()->addMonths(6)->toDateString(), 'text', 'Tanggal akhir KBM.'],
            ['school_address', 'Alamat Sekolah', 'Jl. Pendidikan No. 45, Bandung', 'textarea', 'Alamat sekolah demo.'],
            ['school_phone', 'Telepon Sekolah', '022-1234567', 'text', 'Nomor telepon sekolah demo.'],
        ];

        foreach ($settings as [$key, $label, $value, $type, $description]) {
            AppSetting::query()->create(compact('key', 'label', 'value', 'type', 'description'));
        }
    }

    protected function calendar(User $admin, string $academicYear, string $semester, Carbon $today): void
    {
        $events = [
            ['Hari Efektif Semester Genap', 'Akademik', 'hari_efektif', $today->copy()->subMonth(), $today->copy()->addMonth(), false],
            ['Penilaian Tengah Semester', 'Ujian', 'ujian', $today->copy()->addDays(10), $today->copy()->addDays(14), false],
            ['Libur Sekolah Akhir Pekan Panjang', 'Libur', 'libur_sekolah', $today->copy()->addDays(21), $today->copy()->addDays(22), true],
            ['Pentas Karya Siswa', 'Kegiatan', 'event_sekolah', $today->copy()->addDays(30), $today->copy()->addDays(30), false],
        ];

        foreach ($events as [$title, $category, $type, $start, $end, $isHoliday]) {
            AcademicCalendar::query()->create([
                'academic_year' => $academicYear,
                'semester' => $semester,
                'title' => $title,
                'category' => $category,
                'type' => $type,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'description' => 'Data kalender untuk kebutuhan showcase.',
                'is_holiday' => $isHoliday,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }
    }

    protected function weekDay(Carbon $today, int $offset): int
    {
        return (int) $today->copy()->addDays($offset)->dayOfWeekIso;
    }

    protected function violations(User $wakasekUser, User $guruPiketUser, array $students, Carbon $today): void
    {
        $violations = [
            [$students['eko'], $guruPiketUser, $today->copy()->subDays(5), 'Keterlambatan', 'Terlambat masuk sekolah 15 menit tanpa keterangan.', 5, 'Peringatan lisan.'],
            [$students['cahyo'], $guruPiketUser, $today->copy()->subDays(3), 'Ketertiban Berpakaian', 'Tidak memakai dasi dan sepatu hitam.', 10, 'Peringatan tertulis, wajib melengkapi atribut besok.'],
            [$students['gilang'], $wakasekUser, $today->copy()->subDays(2), 'Sikap / Perilaku', 'Membuat keributan di kantin saat jam istirahat.', 15, 'Panggilan orang tua.'],
            [$students['andi'], $guruPiketUser, $today->copy()->subDay(), 'Keterlambatan', 'Terlambat 10 menit, alasan hujan deras.', 3, null],
            [$students['fira'], $wakasekUser, $today->copy()->subDay(), 'Ketertiban Berpakaian', 'Memakai jaket luar saat jam pelajaran.', 5, 'Peringatan lisan dan jaket disimpan di TU.'],
            [$students['dinda'], $guruPiketUser, $today, 'Keterlambatan', 'Terlambat masuk kelas setelah jam istirahat.', 5, 'Peringatan lisan.'],
            [$students['hana'], $wakasekUser, $today, 'Sikap / Perilaku', 'Menggunakan HP saat jam pelajaran berlangsung.', 20, 'HP disita selama 3 hari, surat peringatan.'],
            [$students['eko'], $wakasekUser, $today, 'Keterlambatan', 'Terlambat masuk gerbang sekolah pagi (kali ke-3).', 10, 'Peringatan tertulis ke orang tua.'],
        ];

        foreach ($violations as [$student, $reporter, $date, $type, $description, $points, $action]) {
            StudentViolation::query()->create([
                'student_id' => $student->id,
                'reported_by_id' => $reporter->id,
                'violation_date' => $date->toDateString(),
                'violation_type' => $type,
                'description' => $description,
                'points' => $points,
                'action_taken' => $action,
            ]);
        }
    }
}
