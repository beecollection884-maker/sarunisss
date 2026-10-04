<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\SubjectAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\TeachingAssignment;
use App\Services\TeachingAssignmentService;
use App\Services\AppSettingService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportExportService
{
    protected const IMPORT_DEFAULT_PASSWORD = 'ipyakin2026';

    public function __construct(
        protected StudentService $studentService,
        protected TeacherService $teacherService,
        protected TeachingAssignmentService $teachingAssignmentService,
        protected AppSettingService $appSettingService,
        protected UserRoleService $userRoleService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function export(string $dataset, string $format = 'csv', array $filters = []): StreamedResponse|Response
    {
        abort_unless(in_array($format, ['csv', 'xls', 'pdf'], true), 404, 'Format export tidak ditemukan.');

        [$baseFilename, $title, $headers, $rows] = match ($dataset) {
            'siswa' => ['data-siswa', 'Data Siswa', $this->studentHeaders(), $this->studentRows($filters)],
            'guru' => ['data-guru', 'Data Guru', $this->teacherHeaders(), $this->teacherRows($filters)],
            'kelas' => ['data-kelas', 'Data Kelas', $this->classHeaders(), $this->classRows($filters)],
            'mapel' => ['data-mapel', 'Data Mata Pelajaran', $this->subjectHeaders(), $this->subjectRows($filters)],
            'absensi' => ['data-absensi', 'Data Absensi Gabungan', $this->attendanceHeaders(), $this->attendanceRows($filters)],
            'catatan-siswa' => ['catatan-siswa', 'Catatan Siswa', $this->studentNoteHeaders(), $this->studentNoteRows($filters)],
            default => abort(404, 'Dataset export tidak ditemukan.'),
        };

        $filename = $this->exportFilename($baseFilename, $filters);

        if ($format === 'xls') {
            return response()
                ->view('dashboard.exports.admin-table-xls', compact('title', 'headers', 'rows'))
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '.xls"');
        }

        if ($format === 'pdf') {
            return response()->view('dashboard.exports.admin-table-print', compact('title', 'headers', 'rows'));
        }

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{created:int,updated:int,failed:int,errors:array<int, array{row:int,messages:array<int,string>}>}
     */
    public function importStudents(UploadedFile $file): array
    {
        return $this->importRows($file, function (array $row): string {
            $row = $this->normalizeStudentImportRow($row);
            $schoolClass = $this->resolveSchoolClass($row['school_class_id'] ?? null, $row['class_name'] ?? null);
            $student = Student::query()->where('nik', (string) ($row['nik'] ?? ''))->first();
            $payload = [
                'school_class_id' => $schoolClass?->id,
                'nik' => $row['nik'] ?? null,
                'nisn' => $row['nisn'] ?? null,
                'name' => $row['name'] ?? null,
                'gender' => isset($row['gender']) ? strtoupper((string) $row['gender']) : null,
                'birth_date' => $row['birth_date'] ?? null,
                'phone' => $row['phone'] ?? null,
                'address' => $row['address'] ?? null,
                'detail_siswa' => [
                    'religion' => $row['religion'] ?? null,
                    'birth_place' => $row['birth_place'] ?? null,
                    'address_street' => $row['address_street'] ?? null,
                    'address_village' => $row['address_village'] ?? null,
                    'address_district' => $row['address_district'] ?? null,
                    'address_province' => $row['address_province'] ?? null,
                    'address_city' => $row['address_city'] ?? null,
                    'father_name' => $row['father_name'] ?? null,
                    'father_education' => $row['father_education'] ?? null,
                    'father_occupation' => $row['father_occupation'] ?? null,
                    'mother_name' => $row['mother_name'] ?? null,
                    'mother_education' => $row['mother_education'] ?? null,
                    'mother_occupation' => $row['mother_occupation'] ?? null,
                    'parent_address' => $row['parent_address'] ?? null,
                    'parent_province' => $row['parent_province'] ?? null,
                    'parent_city' => $row['parent_city'] ?? null,
                    'postal_code' => $row['postal_code'] ?? null,
                    'parent_phone' => $row['parent_phone'] ?? null,
                    'previous_school' => $row['previous_school'] ?? null,
                ],
            ];

            $validator = Validator::make($payload, [
                'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
                'nik' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[0-9A-Za-z]+$/', Rule::unique('students', 'nik')->ignore($student?->id)],
                'nisn' => ['nullable', 'string', 'regex:/^[0-9]{9,20}$/', Rule::unique('students', 'nisn')->ignore($student?->id)],
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'gender' => ['nullable', Rule::in(['L', 'P'])],
                'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
                'phone' => ['nullable', 'string', 'min:10', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
                'address' => ['nullable', 'string', 'max:1000'],
                'detail_siswa' => ['nullable', 'array'],
                'detail_siswa.religion' => ['nullable', 'string', 'max:255'],
                'detail_siswa.birth_place' => ['nullable', 'string', 'max:255'],
                'detail_siswa.address_street' => ['nullable', 'string', 'max:1000'],
                'detail_siswa.address_village' => ['nullable', 'string', 'max:255'],
                'detail_siswa.address_district' => ['nullable', 'string', 'max:255'],
                'detail_siswa.address_province' => ['nullable', 'string', 'max:255'],
                'detail_siswa.address_city' => ['nullable', 'string', 'max:255'],
                'detail_siswa.father_name' => ['nullable', 'string', 'max:255'],
                'detail_siswa.father_education' => ['nullable', 'string', 'max:255'],
                'detail_siswa.father_occupation' => ['nullable', 'string', 'max:255'],
                'detail_siswa.mother_name' => ['nullable', 'string', 'max:255'],
                'detail_siswa.mother_education' => ['nullable', 'string', 'max:255'],
                'detail_siswa.mother_occupation' => ['nullable', 'string', 'max:255'],
                'detail_siswa.parent_address' => ['nullable', 'string', 'max:1000'],
                'detail_siswa.parent_province' => ['nullable', 'string', 'max:255'],
                'detail_siswa.parent_city' => ['nullable', 'string', 'max:255'],
                'detail_siswa.postal_code' => ['nullable', 'string', 'max:10'],
                'detail_siswa.parent_phone' => ['nullable', 'string', 'max:30'],
                'detail_siswa.previous_school' => ['nullable', 'string', 'max:255'],
            ]);

            $validator->validate();

            if ($student === null) {
                $student = $this->studentService->create($payload);
                $this->ensureImportedStudentAccounts($student);

                return 'created';
            }

            $student = $this->studentService->update($student, $payload);
            $this->ensureImportedStudentAccounts($student);

            return 'updated';
        });
    }

    /**
     * @param array<string, string|null> $row
     * @return array<string, string|null>
     */
    protected function normalizeStudentImportRow(array $row): array
    {
        $aliases = [
            'name' => ['nama', 'nama_siswa', 'student_name'],
            'gender' => ['jenis_kelamin', 'jk'],
            'birth_date' => ['tanggal_lahir', 'tgl_lahir'],
            'phone' => ['telepon', 'no_hp', 'nomor_hp', 'hp'],
            'address' => ['alamat'],
            'school_class_id' => ['id_kelas', 'kelas_id'],
            'class_name' => ['nama_kelas', 'kelas'],
            'religion' => ['agama'],
            'birth_place' => ['tempat_lahir'],
            'address_street' => ['jalan', 'alamat_jalan'],
            'address_village' => ['desa', 'kelurahan'],
            'address_district' => ['kecamatan'],
            'address_province' => ['provinsi'],
            'address_city' => ['kota', 'kabupaten'],
            'father_name' => ['nama_ayah', 'ayah'],
            'father_education' => ['pendidikan_ayah'],
            'father_occupation' => ['pekerjaan_ayah'],
            'mother_name' => ['nama_ibu', 'ibu'],
            'mother_education' => ['pendidikan_ibu'],
            'mother_occupation' => ['pekerjaan_ibu'],
            'parent_address' => ['alamat_orang_tua', 'alamat_wali'],
            'parent_province' => ['provinsi_orang_tua', 'provinsi_wali'],
            'parent_city' => ['kota_orang_tua', 'kota_wali'],
            'postal_code' => ['kode_pos'],
            'parent_phone' => ['telepon_orang_tua', 'no_hp_orang_tua', 'hp_orang_tua', 'telepon_wali', 'no_hp_wali'],
            'previous_school' => ['sekolah_asal'],
        ];

        foreach ($aliases as $target => $sources) {
            if (($row[$target] ?? null) !== null) {
                continue;
            }

            foreach ($sources as $source) {
                if (($row[$source] ?? null) !== null) {
                    $row[$target] = $row[$source];
                    break;
                }
            }
        }

        $gender = strtolower(trim((string) ($row['gender'] ?? '')));
        $row['gender'] = match ($gender) {
            'l', 'laki', 'laki-laki', 'pria', 'male' => 'L',
            'p', 'perempuan', 'wanita', 'female' => 'P',
            default => $row['gender'] ?? null,
        };

        return $row;
    }
    /**
     * @return array{created:int,updated:int,failed:int,errors:array<int, array{row:int,messages:array<int,string>}>}
     */
    public function importTeachers(UploadedFile $file): array
    {
        return $this->importRows($file, function (array $row): string {
            $row = $this->normalizeTeacherImportRow($row);
            $teacher = Teacher::query()->where('nip', (string) ($row['nip'] ?? ''))->first();
            $payload = [
                'nik' => $row['nik'] ?? null,
                'nip' => $row['nip'] ?? null,
                'name' => $row['name'] ?? null,
                'birth_place' => $row['birth_place'] ?? null,
                'birth_date' => $row['birth_date'] ?? null,
                'gender' => isset($row['gender']) ? strtoupper((string) $row['gender']) : null,
                'religion' => $row['religion'] ?? null,
                'employment_status' => $row['employment_status'] ?? null,
                'position' => $row['position'] ?? null,
                'join_date' => $row['join_date'] ?? null,
                'last_education' => $row['last_education'] ?? null,
                'major' => $row['major'] ?? null,
                'university' => $row['university'] ?? null,
                'phone' => $row['phone'] ?? null,
                'address' => $row['address'] ?? null,
            ];

            $validator = Validator::make($payload, [
                'nik' => ['nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9]+$/', Rule::unique('teachers', 'nik')->ignore($teacher?->id)],
                'nip' => ['required', 'string', 'min:6', 'max:30', 'regex:/^[0-9A-Za-z.\/-]+$/', Rule::unique('teachers', 'nip')->ignore($teacher?->id)],
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'birth_place' => ['nullable', 'string', 'max:255'],
                'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
                'gender' => ['nullable', Rule::in(['L', 'P'])],
                'religion' => ['nullable', 'string', 'max:100'],
                'employment_status' => ['nullable', 'string', 'max:255'],
                'position' => ['nullable', 'string', 'max:255'],
                'join_date' => ['nullable', 'date', 'before_or_equal:today'],
                'last_education' => ['nullable', 'string', 'max:255'],
                'major' => ['nullable', 'string', 'max:255'],
                'university' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'min:10', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
                'address' => ['nullable', 'string', 'max:1000'],
            ]);

            $validator->validate();

            if ($teacher === null) {
                $teacher = $this->teacherService->create($payload);
                $this->ensureImportedTeacherAccount($teacher);

                return 'created';
            }

            $teacher = $this->teacherService->update($teacher, $payload);
            $this->ensureImportedTeacherAccount($teacher);

            return 'updated';
        });
    }
    /**
     * @param array<string, string|null> $row
     * @return array<string, string|null>
     */
    protected function normalizeTeacherImportRow(array $row): array
    {
        $aliases = [
            'name' => ['nama', 'nama_guru', 'teacher_name'],
            'birth_place' => ['tempat_lahir'],
            'birth_date' => ['tanggal_lahir', 'tgl_lahir'],
            'gender' => ['jenis_kelamin', 'jk'],
            'religion' => ['agama'],
            'employment_status' => ['status_kepegawaian', 'status_pegawai'],
            'position' => ['jabatan'],
            'join_date' => ['tanggal_masuk', 'tgl_masuk', 'mulai_bergabung'],
            'last_education' => ['pendidikan_terakhir'],
            'major' => ['jurusan'],
            'university' => ['universitas', 'kampus'],
            'phone' => ['telepon', 'no_hp', 'nomor_hp', 'hp'],
            'address' => ['alamat'],
        ];

        foreach ($aliases as $target => $sources) {
            if (($row[$target] ?? null) !== null) {
                continue;
            }

            foreach ($sources as $source) {
                if (($row[$source] ?? null) !== null) {
                    $row[$target] = $row[$source];
                    break;
                }
            }
        }

        $gender = strtolower(trim((string) ($row['gender'] ?? '')));
        $row['gender'] = match ($gender) {
            'l', 'laki', 'laki-laki', 'pria', 'male' => 'L',
            'p', 'perempuan', 'wanita', 'female' => 'P',
            default => $row['gender'] ?? null,
        };

        return $row;
    }

    /**
     * @return array{created:int,updated:int,failed:int,errors:array<int, array{row:int,messages:array<int,string>}>}
     */
    public function importStudentNotes(UploadedFile $file): array
    {
        return $this->importRows($file, function (array $row): string {
            $row = $this->normalizeStudentNoteImportRow($row);

            $student = $this->resolveStudentForNote($row);
            $teacher = $this->resolveTeacherForNote($row);

            $payload = [
                'student_id' => $student?->id,
                'teacher_id' => $teacher?->id,
                'title' => $row['title'] ?? null,
                'category' => $row['category'] ?? 'umum',
                'note' => $row['note'] ?? null,
                'follow_up_at' => $row['follow_up_at'] ?? null,
                'resolved_at' => $row['resolved_at'] ?? null,
            ];

            $validator = Validator::make($payload, [
                'student_id' => ['required', 'integer', 'exists:students,id'],
                'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
                'title' => ['required', 'string', 'min:3', 'max:255'],
                'category' => ['required', 'string', 'max:100'],
                'note' => ['required', 'string', 'min:3', 'max:5000'],
                'follow_up_at' => ['nullable', 'date'],
                'resolved_at' => ['nullable', 'date'],
            ], [
                'student_id.required' => 'Siswa tidak ditemukan. Isi nik_siswa, nisn, atau nama_siswa yang sesuai data siswa.',
                'title.required' => 'Judul catatan wajib diisi.',
                'note.required' => 'Isi catatan wajib diisi.',
            ]);

            $validator->validate();

            $exists = StudentNote::query()
                ->where('student_id', $payload['student_id'])
                ->where('title', $payload['title'])
                ->where('category', $payload['category'])
                ->where('note', $payload['note'])
                ->exists();

            if ($exists) {
                throw new \Exception('Catatan siswa yang sama sudah ada. Baris ini dilewati.');
            }

            StudentNote::query()->create($payload);

            return 'created';
        });
    }

    /**
     * @param array<string, string|null> $row
     * @return array<string, string|null>
     */
    protected function normalizeStudentNoteImportRow(array $row): array
    {
        $aliases = [
            'student_nik' => ['nik_siswa', 'nik'],
            'student_nisn' => ['nisn_siswa', 'nisn'],
            'student_name' => ['nama_siswa', 'siswa'],
            'teacher_nip' => ['nip_guru', 'nip'],
            'teacher_name' => ['nama_guru', 'guru'],
            'title' => ['judul'],
            'category' => ['kategori'],
            'note' => ['catatan', 'isi_catatan', 'deskripsi'],
            'follow_up_at' => ['tanggal_tindak_lanjut', 'tindak_lanjut_pada'],
            'resolved_at' => ['tanggal_selesai', 'tanggal_resolved', 'selesai_pada'],
        ];

        foreach ($aliases as $target => $sources) {
            if (($row[$target] ?? null) !== null) {
                continue;
            }

            foreach ($sources as $source) {
                if (($row[$source] ?? null) !== null) {
                    $row[$target] = $row[$source];
                    break;
                }
            }
        }

        $row['category'] = $row['category'] ?? 'umum';

        return $row;
    }

    /**
     * @param array<string, string|null> $row
     */
    protected function resolveStudentForNote(array $row): ?Student
    {
        $nik = $row['student_nik'] ?? null;
        $nisn = $row['student_nisn'] ?? null;
        $name = $row['student_name'] ?? null;

        if ($nik === null && $nisn === null && $name === null) {
            return null;
        }

        return Student::query()
            ->where(function ($query) use ($nik, $nisn, $name): void {
                $query
                    ->when($nik, fn ($studentQuery, string $value) => $studentQuery->orWhere('nik', $value))
                    ->when($nisn, fn ($studentQuery, string $value) => $studentQuery->orWhere('nisn', $value))
                    ->when($name, fn ($studentQuery, string $value) => $studentQuery->orWhere('name', $value));
            })
            ->first();
    }

    /**
     * @param array<string, string|null> $row
     */
    protected function resolveTeacherForNote(array $row): ?Teacher
    {
        $nip = $row['teacher_nip'] ?? null;
        $name = $row['teacher_name'] ?? null;

        if ($nip === null && $name === null) {
            return null;
        }

        return Teacher::query()
            ->where(function ($query) use ($nip, $name): void {
                $query
                    ->when($nip, fn ($teacherQuery, string $value) => $teacherQuery->orWhere('nip', $value))
                    ->when($name, fn ($teacherQuery, string $value) => $teacherQuery->orWhere('name', $value));
            })
            ->first();
    }
    /**
     * @param array<string, string|null> $row
     * @return array<string, string|null>
     */
    protected function normalizeScheduleImportRow(array $row): array
    {
        $aliases = [
            'nip_guru' => ['nip', 'nip_guru_pengajar', 'nip_pengajar', 'guru_nip', 'teacher_nip'],
            'nama_mapel' => ['kode_mapel', 'mapel', 'mata_pelajaran', 'nama_mata_pelajaran', 'subject', 'subject_code'],
            'nama_kelas' => ['kelas', 'class_name', 'nama_rombel', 'rombel'],
            'hari' => ['day', 'day_of_week'],
            'jam_mulai' => ['mulai', 'start_time', 'jam_awal'],
            'jam_selesai' => ['selesai', 'end_time', 'jam_akhir'],
            'ruangan' => ['room', 'ruang'],
        ];

        foreach ($aliases as $target => $sources) {
            if (($row[$target] ?? null) !== null) {
                continue;
            }

            foreach ($sources as $source) {
                if (($row[$source] ?? null) !== null) {
                    $row[$target] = $row[$source];
                    break;
                }
            }
        }

        return $row;
    }

    public function importSchedules(UploadedFile $file): array
    {
        $academicYear = $this->appSettingService->value('academic_year', '2025/2026') ?: '2025/2026';
        $dayMap = [
            'senin' => 0, 'selasa' => 1, 'rabu' => 2, 'kamis' => 3, 'jumat' => 4, 'sabtu' => 5, 'minggu' => 6,
            'monday' => 0, 'tuesday' => 1, 'wednesday' => 2, 'thursday' => 3, 'friday' => 4, 'saturday' => 5, 'sunday' => 6
        ];

        return $this->importRows($file, function (array $row) use ($academicYear, $dayMap): string {
            $row = $this->normalizeScheduleImportRow($row);
            $teacherNip = (string) ($row['nip_guru'] ?? '');
            $subjectInput = (string) ($row['nama_mapel'] ?? '');
            $className = trim((string) ($row['nama_kelas'] ?? ''));

            // Resolve Teacher
            $teacher = Teacher::query()->where('nip', $teacherNip)->first();
            // Resolve class name now; create the class only after the row passes validation.

            // Resolve Day
            $dayInput = strtolower(trim((string) ($row['hari'] ?? '')));
            $dayOfWeek = is_numeric($dayInput) ? (int)$dayInput : ($dayMap[$dayInput] ?? null);

            $payload = [
                'academic_year' => $academicYear,
                'teacher_id' => $teacher?->id,
                'subject_input' => $subjectInput,
                'class_name' => $className,
                'day_of_week' => $dayOfWeek,
                'start_time' => $row['jam_mulai'] ?? null,
                'end_time' => $row['jam_selesai'] ?? null,
                'room' => $row['ruangan'] ?? null,
            ];

            $validator = Validator::make($payload, [
                'academic_year' => ['required', 'string'],
                'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
                'subject_input' => ['required', 'string', 'min:2', 'max:255'],
                'class_name' => ['required', 'string', 'min:2', 'max:100'],
                'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
                'room' => ['nullable', 'string', 'max:50'],
            ], [
                'teacher_id.required' => 'NIP Guru tidak valid atau tidak terdaftar.',
                'teacher_id.exists' => "NIP Guru '{$teacherNip}' tidak terdaftar dalam sistem.",
                'subject_input.required' => 'Kode/Nama Mapel wajib diisi.',
                'subject_input.min' => 'Kode/Nama Mapel minimal 2 karakter.',
                'class_name.required' => 'Nama Kelas wajib diisi.',
                'day_of_week.required' => 'Hari tidak valid. Gunakan nama hari (Senin-Minggu) atau angka (0-6).',
                'start_time.required' => 'Jam mulai wajib diisi (format HH:MM).',
                'end_time.required' => 'Jam selesai wajib diisi (format HH:MM).',
                'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            ]);

            $validator->validate();

            $subject = $this->resolveOrCreateScheduleSubject($subjectInput);
            $payload['subject_id'] = $subject->id;
            unset($payload['subject_input']);

            $schoolClass = $this->resolveOrCreateScheduleClass($className, $academicYear);
            $payload['school_class_id'] = $schoolClass?->id;
            unset($payload['class_name']);

            // Find if there is an exact schedule slot already
            $existing = TeachingAssignment::where('school_class_id', $payload['school_class_id'])
                ->where('academic_year', $academicYear)
                ->where('day_of_week', $payload['day_of_week'])
                ->where('start_time', $payload['start_time'])
                ->where('end_time', $payload['end_time'])
                ->first();

            // Check overlap for Teacher (either as main teacher or substitute teacher)
            $teacherConflict = TeachingAssignment::query()
                ->where('academic_year', $academicYear)
                ->where('day_of_week', $payload['day_of_week'])
                ->where('start_time', '<', $payload['end_time'])
                ->where('end_time', '>', $payload['start_time'])
                ->where(function ($q) use ($payload) {
                    $q->where('teacher_id', $payload['teacher_id'])
                      ->orWhere('substitute_teacher_id', $payload['teacher_id']);
                });

            // Check overlap for Class
            $classConflict = TeachingAssignment::query()
                ->where('school_class_id', $payload['school_class_id'])
                ->where('academic_year', $academicYear)
                ->where('day_of_week', $payload['day_of_week'])
                ->where('start_time', '<', $payload['end_time'])
                ->where('end_time', '>', $payload['start_time']);

            $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
            $dayName = $dayNames[$payload['day_of_week']] ?? $payload['day_of_week'];

            if ($existing) {
                throw new \Exception("Jadwal sudah ada untuk kelas {$className} pada hari {$dayName} jam {$payload['start_time']}-{$payload['end_time']}. Baris ini dilewati.");
            }

            $conflictingTeacher = $teacherConflict->with(['subject', 'schoolClass'])->first();
            if ($conflictingTeacher) {
                $conflictSubject = $conflictingTeacher->subject?->name ?? '-';
                $conflictClass = $conflictingTeacher->schoolClass?->name ?? '-';
                $conflictTime = substr($conflictingTeacher->start_time, 0, 5) . '-' . substr($conflictingTeacher->end_time, 0, 5);
                throw new \Exception(
                    "TABRAKAN GURU: Guru NIP {$teacherNip} ({$teacher?->name}) sudah mengajar mapel \"{$conflictSubject}\" di kelas {$conflictClass} pada hari {$dayName} jam {$conflictTime}. Tidak boleh ada 2 jadwal untuk guru yang sama di waktu yang bertabrakan."
                );
            }

            $conflictingClass = $classConflict->with(['subject', 'teacher'])->first();
            if ($conflictingClass) {
                $conflictSubject = $conflictingClass->subject?->name ?? '-';
                $conflictTeacher = $conflictingClass->teacher?->name ?? '-';
                $conflictTime = substr($conflictingClass->start_time, 0, 5) . '-' . substr($conflictingClass->end_time, 0, 5);
                throw new \Exception(
                    "TABRAKAN KELAS: Kelas {$className} sudah memiliki jadwal mapel \"{$conflictSubject}\" oleh {$conflictTeacher} pada hari {$dayName} jam {$conflictTime}. Tidak boleh ada 2 mapel di kelas yang sama pada waktu yang bertabrakan."
                );
            }

            $this->teachingAssignmentService->create($payload);
            return 'created';
        });
    }

    protected function ensureImportedTeacherAccount(Teacher $teacher): void
    {
        $teacher->loadMissing('user');
        $user = $teacher->user;

        if ($user === null) {
            $user = User::query()->create([
                'name' => $teacher->name,
                'email' => $this->uniqueImportedEmail('guru', $teacher->nip ?? $teacher->nik ?? $teacher->name ?? $teacher->id),
                'password' => self::IMPORT_DEFAULT_PASSWORD,
                'roles' => [UserRole::GURU_MAPEL->value],
            ]);

            $teacher->forceFill(['user_id' => $user->id])->save();
            $teacher->setRelation('user', $user);
        } else {
            $user->forceFill([
                'name' => $user->name ?: $teacher->name,
                'password' => self::IMPORT_DEFAULT_PASSWORD,
            ])->save();

            $this->userRoleService->ensureRoles($user, [UserRole::GURU_MAPEL]);
        }
    }

    protected function ensureImportedStudentAccounts(Student $student): void
    {
        $student->loadMissing(['user', 'parentUser', 'detailSiswa']);
        $user = $student->user;

        if ($user === null) {
            $user = User::query()->create([
                'name' => $student->name,
                'email' => $this->uniqueImportedEmail('siswa', $student->nisn ?? $student->nik ?? $student->name ?? $student->id),
                'password' => self::IMPORT_DEFAULT_PASSWORD,
                'roles' => [UserRole::SISWA->value],
            ]);

            $student->forceFill(['user_id' => $user->id])->save();
            $student->setRelation('user', $user);
        } else {
            $user->forceFill([
                'name' => $user->name ?: $student->name,
                'password' => self::IMPORT_DEFAULT_PASSWORD,
            ])->save();

            $this->userRoleService->ensureRoles($user, [UserRole::SISWA]);
        }

        $parentUser = $student->parentUser;
        if ($parentUser !== null) {
            $parentUser->forceFill([
                'password' => self::IMPORT_DEFAULT_PASSWORD,
            ])->save();

            $this->userRoleService->ensureRoles($parentUser, [UserRole::ORANG_TUA]);
        }
    }

    protected function uniqueImportedEmail(string $prefix, mixed $identifier): string
    {
        $local = str($prefix.'.'.(string) $identifier)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->replaceMatches('/\.+/', '.')
            ->trim('.')
            ->toString();

        if ($local === '' || $local === $prefix) {
            $local = $prefix.'.'.uniqid();
        }

        $domain = $this->generatedImportEmailDomain();
        $email = $local.'@'.$domain;
        $counter = 2;

        while (User::query()->where('email', $email)->exists()) {
            $email = $local.'.'.$counter.'@'.$domain;
            $counter++;
        }

        return $email;
    }

    protected function generatedImportEmailDomain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return filled($host) ? (string) $host : 'sarunis.local';
    }
    public function template(string $type): StreamedResponse
    {
        [$filename, $headers] = match ($type) {
            'siswa' => ['template-import-siswa.csv', [
                'nik', 'nisn', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'no_hp', 'alamat', 'nama_kelas',
                'agama', 'tempat_lahir', 'jalan', 'desa', 'kecamatan', 'provinsi', 'kota',
                'nama_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pendidikan_ibu', 'pekerjaan_ibu',
                'alamat_orang_tua', 'provinsi_orang_tua', 'kota_orang_tua', 'kode_pos', 'no_hp_orang_tua', 'sekolah_asal',
            ]],
            'guru' => ['template-import-guru.csv', [
                'nip', 'nik', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama',
                'status_kepegawaian', 'jabatan', 'tanggal_masuk', 'pendidikan_terakhir', 'jurusan', 'universitas', 'no_hp', 'alamat',
            ]],
            'jadwal' => ['template-import-jadwal.csv', [
                'nip_guru', 'nama_mapel', 'nama_kelas', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan',
            ]],
            'catatan-siswa' => ['template-import-catatan-siswa.csv', [
                'nik_siswa', 'nisn', 'nama_siswa', 'nip_guru', 'nama_guru', 'judul', 'kategori', 'catatan', 'tanggal_tindak_lanjut', 'tanggal_selesai',
            ]],
            default => abort(404, 'Template import tidak ditemukan.'),
        };

        return response()->streamDownload(function () use ($headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param callable(array<string, string|null>): string $callback
     * @return array{created:int,updated:int,failed:int,errors:array<int, array{row:int,messages:array<int,string>}>}
     */
    protected function importRows(UploadedFile $file, callable $callback): array
    {
        $this->extendImportExecutionTime();

        $rows = $this->readCsv($file);
        abort_if($rows === [], 422, 'File import tidak memiliki baris data.');

        $summary = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($rows as $rowNumber => $row) {
            try {
                $result = $callback($row);
                $summary[$result]++;
            } catch (\Throwable $throwable) {
                $summary['failed']++;
                $summary['errors'][] = [
                    'row' => $rowNumber,
                    'messages' => method_exists($throwable, 'errors')
                        ? Arr::flatten($throwable->errors())
                        : [$throwable->getMessage()],
                ];
            }
        }

        return $summary;
    }

    protected function extendImportExecutionTime(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        @ini_set('max_execution_time', '300');
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    protected function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        abort_if($handle === false, 422, 'File import tidak dapat dibaca.');

        $header = fgetcsv($handle);
        abort_if($header === false, 422, 'File import harus memiliki header CSV.');

        $firstHeader = str_replace("\xEF\xBB\xBF", '', (string) ($header[0] ?? ''));
        if (str_starts_with(strtolower(trim($firstHeader)), 'sep=')) {
            $header = fgetcsv($handle);
            abort_if($header === false, 422, 'File import harus memiliki header CSV.');
        }

        $keys = array_map(function (string $value): string {
            $cleaned = str_replace("\xEF\xBB\xBF", '', $value);
            return str($cleaned)->trim()->lower()->replace(' ', '_')->toString();
        }, $header);
        $rows = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $row = [];

            foreach ($keys as $index => $key) {
                $value = isset($data[$index]) ? trim((string) $data[$index]) : null;
                $row[$key] = $value === '' ? null : $value;
            }

            if (collect($row)->filter()->isEmpty()) {
                continue;
            }

            $rows[$rowNumber] = $row;
        }

        fclose($handle);

        return $rows;
    }

    protected function resolveSchoolClass(?string $schoolClassId, ?string $className): ?SchoolClass
    {
        if ($schoolClassId !== null) {
            return SchoolClass::query()->find((int) $schoolClassId);
        }

        if ($className !== null) {
            return SchoolClass::query()->where('name', $className)->first();
        }

        return null;
    }

    protected function resolveOrCreateScheduleSubject(string $subjectInput): Subject
    {
        $subjectInput = trim($subjectInput);

        $subject = Subject::query()
            ->where('code', $subjectInput)
            ->orWhere('name', $subjectInput)
            ->first();

        if ($subject !== null) {
            return $subject;
        }

        return Subject::query()->create([
            'code' => $this->scheduleSubjectCode($subjectInput),
            'name' => $subjectInput,
            'lesson_hours' => 2,
            'description' => 'Dibuat otomatis dari import jadwal pelajaran.',
        ]);
    }

    protected function scheduleSubjectCode(string $subjectInput): string
    {
        $base = str($subjectInput)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->substr(0, 20)
            ->toString();

        if ($base === '') {
            $base = 'MAPEL';
        }

        $code = $base;
        $counter = 2;

        while (Subject::query()->where('code', $code)->exists()) {
            $suffix = '-' . $counter;
            $code = substr($base, 0, 20 - strlen($suffix)) . $suffix;
            $counter++;
        }

        return $code;
    }
    protected function resolveOrCreateScheduleClass(string $className, string $academicYear): ?SchoolClass
    {
        $className = trim($className);

        if ($className === '') {
            return null;
        }

        return SchoolClass::query()->firstOrCreate(
            [
                'name' => $className,
                'academic_year' => $academicYear,
            ],
            [
                'level' => $this->inferClassLevel($className),
                'description' => 'Dibuat otomatis dari import jadwal pelajaran. Lengkapi siswa dan wali kelas pada menu Akademik > Kelas.',
            ],
        );
    }

    protected function inferClassLevel(string $className): string
    {
        $className = strtoupper(trim($className));

        if (preg_match('/^(XII|XI|X|IX|VIII|VII)\b/', $className, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(1[0-2]|[1-9])\b/', $className, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(1[0-2]|[1-9])(?=[A-Z])/', $className, $matches)) {
            return $matches[1];
        }

        return 'UMUM';
    }

    protected function studentHeaders(): array
    {
        return [
            'id', 'nik', 'nisn', 'name', 'gender', 'birth_date', 'phone', 'address', 'class_name',
            'religion', 'birth_place', 'address_street', 'address_village', 'address_district',
            'address_province', 'address_city', 'father_name', 'father_education', 'father_occupation',
            'mother_name', 'mother_education', 'mother_occupation', 'parent_address', 'parent_province',
            'parent_city', 'postal_code', 'parent_phone', 'previous_school'
        ];
    }

    protected function teacherHeaders(): array
    {
        return ['id', 'nip', 'nik', 'name', 'birth_place', 'birth_date', 'gender', 'religion', 'employment_status', 'position', 'join_date', 'last_education', 'major', 'university', 'phone', 'address'];
    }

    protected function classHeaders(): array
    {
        return ['id', 'name', 'level', 'academic_year', 'homeroom_teacher', 'students_count', 'description'];
    }

    protected function attendanceHeaders(): array
    {
        return ['context', 'date', 'student', 'class_name', 'subject', 'teacher', 'status', 'notes'];
    }

    protected function studentNoteHeaders(): array
    {
        return ['id', 'student', 'class_name', 'teacher', 'title', 'category', 'note', 'follow_up_at', 'resolved_at'];
    }

    protected function subjectHeaders(): array
    {
        return ['id', 'code', 'name', 'lesson_hours', 'hari', 'jam_mulai', 'jam_selesai', 'kelas', 'teachers', 'classes', 'description'];
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function studentRows(array $filters = []): array
    {
        $gender = $filters['gender'] ?? null;
        $level = $filters['level'] ?? null;
        $academicYear = $filters['academic_year'] ?? null;
        $search = $filters['search'] ?? null;

        return Student::query()
            ->with(['schoolClass', 'detailSiswa'])
            ->when(
                $this->filterInt($filters, 'school_class_id'),
                fn ($query, int $schoolClassId) => $query->where('school_class_id', $schoolClassId),
            )
            ->when(
                is_string($gender) && $gender !== '',
                fn ($query) => $query->where('gender', $gender),
            )
            ->when(is_string($level) && $level !== '', function ($query) use ($level): void {
                $query->whereHas('schoolClass', fn ($classQuery) => $classQuery->where('level', $level));
            })
            ->when(is_string($academicYear) && $academicYear !== '', function ($query) use ($academicYear): void {
                $query->whereHas('schoolClass', fn ($classQuery) => $classQuery->where('academic_year', $academicYear));
            })
            ->when(is_string($search) && $search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Student $student): array => [
                $student->id,
                $student->nik,
                $student->nisn,
                $student->name,
                $student->gender,
                $student->birth_date?->toDateString(),
                $student->phone,
                $student->address,
                $student->schoolClass?->name,
                $student->detailSiswa?->religion,
                $student->detailSiswa?->birth_place,
                $student->detailSiswa?->address_street,
                $student->detailSiswa?->address_village,
                $student->detailSiswa?->address_district,
                $student->detailSiswa?->address_province,
                $student->detailSiswa?->address_city,
                $student->detailSiswa?->father_name,
                $student->detailSiswa?->father_education,
                $student->detailSiswa?->father_occupation,
                $student->detailSiswa?->mother_name,
                $student->detailSiswa?->mother_education,
                $student->detailSiswa?->mother_occupation,
                $student->detailSiswa?->parent_address,
                $student->detailSiswa?->parent_province,
                $student->detailSiswa?->parent_city,
                $student->detailSiswa?->postal_code,
                $student->detailSiswa?->parent_phone,
                $student->detailSiswa?->previous_school,
            ])
            ->all();
    }

    protected function teacherRows(array $filters = []): array
    {
        $category = $filters['category'] ?? null;
        $gender = $filters['gender'] ?? null;
        $employmentStatus = $filters['employment_status'] ?? null;
        $search = $filters['search'] ?? null;

        $query = Teacher::query()
            ->withCount(['subjects', 'teachingAssignments', 'homeroomClasses'])
            ->when(
                is_string($gender) && $gender !== '',
                fn ($teacherQuery) => $teacherQuery->where('gender', $gender),
            )
            ->when(
                is_string($employmentStatus) && $employmentStatus !== '',
                fn ($teacherQuery) => $teacherQuery->where('employment_status', $employmentStatus),
            )
            ->when(is_string($search) && $search !== '', function ($teacherQuery) use ($search): void {
                $teacherQuery->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        if ($category !== null && $category !== '') {
            $query = $query->filter(fn (Teacher $teacher) => $teacher->roleMeta()['key'] === $category);
        }

        return $query
            ->map(fn (Teacher $teacher): array => [
                $teacher->id,
                $teacher->nip,
                $teacher->nik,
                $teacher->name,
                $teacher->birth_place,
                $teacher->birth_date?->toDateString(),
                $teacher->gender,
                $teacher->religion,
                $teacher->employment_status,
                $teacher->position,
                $teacher->join_date?->toDateString(),
                $teacher->last_education,
                $teacher->major,
                $teacher->university,
                $teacher->phone,
                $teacher->address,
            ])
            ->all();
    }

    protected function classRows(array $filters = []): array
    {
        $level = $filters['level'] ?? null;
        $academicYear = $filters['academic_year'] ?? null;

        return SchoolClass::query()
            ->with('homeroomTeacher')
            ->withCount('students')
            ->when(
                $this->filterInt($filters, 'school_class_id'),
                fn ($query, int $schoolClassId) => $query->where('id', $schoolClassId),
            )
            ->when(
                $level !== null && $level !== '',
                fn ($query) => $query->where('level', $level),
            )
            ->when(
                $academicYear !== null && $academicYear !== '',
                fn ($query) => $query->where('academic_year', $academicYear),
            )
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $schoolClass): array => [
                $schoolClass->id,
                $schoolClass->name,
                $schoolClass->level,
                $schoolClass->academic_year,
                $schoolClass->homeroomTeacher?->name,
                $schoolClass->students_count,
                $schoolClass->description,
            ])
            ->all();
    }

    protected function subjectRows(array $filters = []): array
    {
        $usage = $filters['usage'] ?? null;
        $search = $filters['search'] ?? null;

        return Subject::query()
            ->with(['teachers', 'schoolClass', 'schoolClasses'])
            ->when(
                $this->filterInt($filters, 'subject_id'),
                fn ($query, int $subjectId) => $query->where('id', $subjectId),
            )
            ->when($this->filterInt($filters, 'school_class_id'), function ($query, int $schoolClassId): void {
                $query->where(function ($q) use ($schoolClassId) {
                    $q->where('school_class_id', $schoolClassId)
                      ->orWhereHas('schoolClasses', fn ($classQuery) => $classQuery->where('school_classes.id', $schoolClassId));
                });
            })
            ->when($usage === 'dipakai', function ($query): void {
                $query->has('teachingAssignments');
            })
            ->when($usage === 'belum-dipakai', function ($query): void {
                $query->doesntHave('teachingAssignments');
            })
            ->when(is_string($search) && $search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject): array => [
                $subject->id,
                $subject->code,
                $subject->name,
                $subject->lesson_hours,
                $subject->day_of_week !== null ? (config('schedule.day_names')[$subject->day_of_week] ?? '-') : '-',
                $subject->start_time ? substr($subject->start_time, 0, 5) : '-',
                $subject->end_time ? substr($subject->end_time, 0, 5) : '-',
                $subject->schoolClass?->name ?? '-',
                $subject->teachers->pluck('name')->implode(', '),
                $subject->schoolClasses->pluck('name')->implode(', '),
                $subject->description,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function attendanceRows(array $filters = []): array
    {
        $type = $filters['type'] ?? 'gabungan';
        $status = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;
        $teacherId = $this->filterInt($filters, 'teacher_id');
        $studentId = $this->filterInt($filters, 'student_id');

        $classRows = ClassAttendance::query()
            ->with(['student', 'schoolClass', 'recordedByTeacher'])
            ->when(
                $this->filterInt($filters, 'school_class_id'),
                fn ($query, int $schoolClassId) => $query->where('school_class_id', $schoolClassId),
            )
            ->when(
                $teacherId,
                fn ($query, int $recordedByTeacherId) => $query->where('recorded_by_teacher_id', $recordedByTeacherId),
            )
            ->when(
                $studentId,
                fn ($query, int $attendanceStudentId) => $query->where('student_id', $attendanceStudentId),
            )
            ->when(is_string($search) && $search !== '', function ($query) use ($search): void {
                $query->whereHas('student', function ($studentQuery) use ($search): void {
                    $studentQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when(
                is_string($status) && $status !== '',
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $this->filterDate($filters, 'attendance_date'),
                fn ($query, string $date) => $query->whereDate('attendance_date', $date),
            )
            ->when(
                $this->filterDate($filters, 'date_from'),
                fn ($query, string $date) => $query->whereDate('attendance_date', '>=', $date),
            )
            ->when(
                $this->filterDate($filters, 'date_to'),
                fn ($query, string $date) => $query->whereDate('attendance_date', '<=', $date),
            )
            ->orderByDesc('attendance_date')
            ->get()
            ->map(fn (ClassAttendance $attendance): array => [
                'Absensi Kelas Perwalian',
                $attendance->attendance_date?->toDateString(),
                $attendance->student?->name,
                $attendance->schoolClass?->name,
                null,
                $attendance->recordedByTeacher?->name,
                $attendance->status,
                $attendance->notes,
            ]);

        $subjectRows = SubjectAttendance::query()
            ->with(['student', 'recordedByTeacher', 'teachingAssignment.subject', 'teachingAssignment.schoolClass'])
            ->whereHas('teachingAssignment', function ($query) use ($filters): void {
                $query
                    ->when(
                        $this->filterInt($filters, 'school_class_id'),
                        fn ($assignmentQuery, int $schoolClassId) => $assignmentQuery->where('school_class_id', $schoolClassId),
                    )
                    ->when(
                        $this->filterInt($filters, 'subject_id'),
                        fn ($assignmentQuery, int $subjectId) => $assignmentQuery->where('subject_id', $subjectId),
                    )
                    ->when(
                        $this->filterInt($filters, 'teacher_id'),
                        fn ($assignmentQuery, int $assignmentTeacherId) => $assignmentQuery->where('teacher_id', $assignmentTeacherId),
                    );
            })
            ->when(
                $teacherId,
                fn ($query, int $recordedByTeacherId) => $query->where('recorded_by_teacher_id', $recordedByTeacherId),
            )
            ->when(
                $studentId,
                fn ($query, int $attendanceStudentId) => $query->where('student_id', $attendanceStudentId),
            )
            ->when(is_string($search) && $search !== '', function ($query) use ($search): void {
                $query->whereHas('student', function ($studentQuery) use ($search): void {
                    $studentQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when(
                is_string($status) && $status !== '',
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $this->filterDate($filters, 'attendance_date'),
                fn ($query, string $date) => $query->whereDate('attendance_date', $date),
            )
            ->when(
                $this->filterDate($filters, 'date_from'),
                fn ($query, string $date) => $query->whereDate('attendance_date', '>=', $date),
            )
            ->when(
                $this->filterDate($filters, 'date_to'),
                fn ($query, string $date) => $query->whereDate('attendance_date', '<=', $date),
            )
            ->orderByDesc('attendance_date')
            ->get()
            ->map(fn (SubjectAttendance $attendance): array => [
                'Absensi Mapel',
                $attendance->attendance_date?->toDateString(),
                $attendance->student?->name,
                $attendance->teachingAssignment?->schoolClass?->name,
                $attendance->teachingAssignment?->subject?->name,
                $attendance->recordedByTeacher?->name,
                $attendance->status,
                $attendance->notes,
            ]);

        return match ($type) {
            'kelas' => $classRows->all(),
            'mapel' => $subjectRows->all(),
            default => $classRows->concat($subjectRows)->all(),
        };
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function studentNoteRows(array $filters = []): array
    {
        $category = $filters['category'] ?? null;

        return StudentNote::query()
            ->with(['student.schoolClass', 'teacher'])
            ->when($this->filterInt($filters, 'school_class_id'), function ($query, int $schoolClassId): void {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('school_class_id', $schoolClassId));
            })
            ->when(
                $this->filterInt($filters, 'teacher_id'),
                fn ($query, int $teacherId) => $query->where('teacher_id', $teacherId),
            )
            ->when(
                is_string($category) && $category !== '',
                fn ($query) => $query->where('category', $category),
            )
            ->when(
                $this->filterDate($filters, 'date_from'),
                fn ($query, string $date) => $query->whereDate('created_at', '>=', $date),
            )
            ->when(
                $this->filterDate($filters, 'date_to'),
                fn ($query, string $date) => $query->whereDate('created_at', '<=', $date),
            )
            ->latest()
            ->get()
            ->map(fn (StudentNote $note): array => [
                $note->id,
                $note->student?->name,
                $note->student?->schoolClass?->name,
                $note->teacher?->name,
                $note->title,
                $note->category,
                $note->note,
                $note->follow_up_at?->toDateString(),
                $note->resolved_at?->toDateString(),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function filterInt(array $filters, string $key): ?int
    {
        $value = $filters[$key] ?? null;

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function filterDate(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function exportFilename(string $baseFilename, array $filters): string
    {
        $parts = [$baseFilename];

        foreach (['type', 'subject_id', 'school_class_id', 'teacher_id', 'student_id', 'attendance_date', 'date_from', 'date_to', 'status', 'category', 'gender', 'employment_status', 'academic_year', 'level', 'usage', 'search'] as $key) {
            $value = $filters[$key] ?? null;

            if ($value !== null && $value !== '') {
                $parts[] = str_replace('_', '-', $key) . '-' . preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $value);
            }
        }

        $parts[] = now()->format('Ymd-His');

        return implode('-', $parts);
    }
}
