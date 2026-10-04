<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Concerns\ResolvesSchoolProfiles;
use App\Models\AcademicCalendar;
use App\Models\ClassAttendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\SubjectAttendance;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AppSettingService;
use App\Services\AcademicCalendarService;
use App\Services\AuthService;
use App\Services\ClassAttendanceService;
use App\Services\SchoolClassService;
use App\Services\SubjectAttendanceService;
use App\Services\TeachingAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalDashboardController extends Controller
{
    use ResolvesSchoolProfiles;

    public function __construct(
        protected TeachingAssignmentService $teachingAssignmentService,
        protected SchoolClassService $schoolClassService,
        protected ClassAttendanceService $classAttendanceService,
        protected SubjectAttendanceService $subjectAttendanceService,
        protected AppSettingService $appSettingService,
        protected AcademicCalendarService $academicCalendarService,
    ) {}

    public function admin(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'admin sekolah',
            'akses' => [
                'crud data siswa',
                'crud data guru',
                'crud data kelas',
                'crud mapel',
                'ploting kelas siswa dan walikelas',
                'crud jadwal ajar',
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json(array_merge($payload, $this->buildAdminDashboard($request, $payload)));
        }

        return view('dashboard.portal', $this->buildAdminDashboard($request, $payload));
    }

    public function wakasekKesiswaan(Request $request): View
    {
        $cachedData = \Illuminate\Support\Facades\Cache::remember('wakasek_dashboard_data', 3600, function(): array {
            $today = CarbonImmutable::today();
            $startOfMonth = $today->startOfMonth();

            $totalViolations = \App\Models\StudentViolation::count();
            $thisMonth = \App\Models\StudentViolation::where('violation_date', '>=', $startOfMonth)->count();
            $totalPoints = \App\Models\StudentViolation::sum('points');

            $byType = \App\Models\StudentViolation::query()
                ->selectRaw('violation_type, count(*) as total, sum(points) as total_points')
                ->groupBy('violation_type')
                ->orderByDesc('total')
                ->get();

            $byClass = \App\Models\StudentViolation::query()
                ->join('students', 'student_violations.student_id', '=', 'students.id')
                ->join('school_classes', 'students.school_class_id', '=', 'school_classes.id')
                ->selectRaw('school_classes.name as class_name, count(*) as total, sum(student_violations.points) as total_points')
                ->groupBy('school_classes.name')
                ->orderByDesc('total')
                ->get();

            $recentViolations = \App\Models\StudentViolation::with(['student.schoolClass', 'reporter'])
                ->latest('violation_date')
                ->limit(10)
                ->get();

            $topOffenders = \App\Models\StudentViolation::query()
                ->join('students', 'student_violations.student_id', '=', 'students.id')
                ->selectRaw('students.id, students.name, count(*) as violation_count, sum(student_violations.points) as total_points')
                ->groupBy('students.id', 'students.name')
                ->orderByDesc('total_points')
                ->limit(5)
                ->get();

            return [
                'totalViolations' => $totalViolations,
                'thisMonth' => $thisMonth,
                'totalPoints' => $totalPoints,
                'byType' => $byType,
                'byClass' => $byClass,
                'recentViolations' => $recentViolations,
                'topOffenders' => $topOffenders,
            ];
        });

        $menuSections = [
            ['title' => 'Menu Utama', 'items' => [
                ['label' => 'Dashboard', 'icon' => 'home', 'href' => url('/wakasek-kesiswaan/dashboard'), 'active' => true],
                ['label' => 'Data Pelanggaran', 'icon' => 'note', 'href' => url('/wakasek-kesiswaan/pelanggaran'), 'active' => false],
            ]],
        ];

        return view('dashboard.wakasek-kesiswaan', [
            'pageTitle' => 'Wakasek Kesiswaan',
            'user' => $request->user(),
            'menuSections' => $menuSections,
            'totalViolations' => $cachedData['totalViolations'],
            'thisMonth' => $cachedData['thisMonth'],
            'totalPoints' => $cachedData['totalPoints'],
            'byType' => $cachedData['byType'],
            'byClass' => $cachedData['byClass'],
            'recentViolations' => $cachedData['recentViolations'],
            'topOffenders' => $cachedData['topOffenders'],
            'announcements' => $this->getAnnouncementsForUser($request->user()),
        ]);
    }

    public function guruPiket(Request $request): View
    {
        $cachedData = \Illuminate\Support\Facades\Cache::remember('guru_piket_dashboard_data', 3600, function(): array {
            $today = CarbonImmutable::today();
            $weekAgo = $today->subDays(7);

            $todayCount = \App\Models\StudentViolation::where('violation_date', $today)->count();
            $todayPoints = \App\Models\StudentViolation::where('violation_date', $today)->sum('points');
            $weekCount = \App\Models\StudentViolation::where('violation_date', '>=', $weekAgo)->count();
            $totalAll = \App\Models\StudentViolation::count();

            $todayViolations = \App\Models\StudentViolation::with(['student.schoolClass', 'reporter'])
                ->where('violation_date', $today)
                ->latest('created_at')
                ->get();

            $recentViolations = \App\Models\StudentViolation::with(['student.schoolClass', 'reporter'])
                ->where('violation_date', '>=', $weekAgo)
                ->latest('violation_date')
                ->limit(15)
                ->get();

            $students = Student::with('schoolClass')->orderBy('name')->get();
            $classes = SchoolClass::orderBy('name')->get();

            return [
                'todayCount' => $todayCount,
                'todayPoints' => $todayPoints,
                'weekCount' => $weekCount,
                'totalAll' => $totalAll,
                'todayViolations' => $todayViolations,
                'recentViolations' => $recentViolations,
                'students' => $students,
                'classes' => $classes,
            ];
        });

        $menuSections = [
            ['title' => 'Menu Utama', 'items' => [
                ['label' => 'Dashboard', 'icon' => 'home', 'href' => url('/guru-piket/dashboard'), 'active' => true],
                ['label' => 'Data Pelanggaran', 'icon' => 'note', 'href' => url('/guru-piket/pelanggaran'), 'active' => false],
            ]],
        ];

        return view('dashboard.guru-piket', [
            'pageTitle' => 'Guru Piket',
            'user' => $request->user(),
            'menuSections' => $menuSections,
            'todayCount' => $cachedData['todayCount'],
            'todayPoints' => $cachedData['todayPoints'],
            'weekCount' => $cachedData['weekCount'],
            'totalAll' => $cachedData['totalAll'],
            'todayViolations' => $cachedData['todayViolations'],
            'recentViolations' => $cachedData['recentViolations'],
            'students' => $cachedData['students'],
            'classes' => $cachedData['classes'],
            'announcements' => $this->getAnnouncementsForUser($request->user()),
        ]);
    }

    public function adminAttendanceRecap(Request $request): JsonResponse|View|Response
    {
        $payload = $this->adminPayload();
        $data = $this->buildAdminDashboard($request, $payload);

        return $this->renderViewOrJson($request, 'dashboard.admin-attendance-recap', array_merge($data, [
            'pageTitle' => 'Rekap Kehadiran',
            'menuSections' => $this->adminPageMenu('rekap-kehadiran'),
        ]));
    }

    public function adminAttendanceReport(Request $request): JsonResponse|View|Response
    {
        $payload = $this->adminPayload();
        $data = $this->buildAdminDashboard($request, $payload);

        return $this->renderViewOrJson($request, 'dashboard.admin-attendance-report', array_merge($data, [
            'pageTitle' => 'Laporan Statistik',
            'menuSections' => $this->adminPageMenu('laporan-tren'),
        ]));
    }

    public function adminStudents(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'admin sekolah',
            'akses' => [
                'crud data siswa',
                'crud data guru',
                'crud data kelas',
                'crud mapel',
                'ploting kelas siswa dan walikelas',
                'crud jadwal ajar',
            ],
        ];

        $cachedData = \Illuminate\Support\Facades\Cache::remember('admin_students_payload', 3600, function(): array {
            $students = Student::query()
                ->with(['schoolClass.homeroomTeacher', 'detailSiswa'])
                ->orderBy('school_class_id')
                ->orderBy('name')
                ->get();

            $directoryGroups = $students
                ->groupBy(fn(Student $student): string => (string) ($student->school_class_id ?? 0))
                ->map(function (Collection $group): array {
                    /** @var Student|null $firstStudent */
                    $firstStudent = $group->first();
                    $schoolClass = $firstStudent?->schoolClass;
                    $homeroomTeacher = $schoolClass?->homeroomTeacher;
                    $classId = $schoolClass?->id;

                    return [
                        'key' => 'class-' . ($classId ?? 'unassigned'),
                        'class_id' => $classId,
                        'label' => $schoolClass !== null ? 'Kelas ' . $schoolClass->name : 'Belum Masuk Kelas',
                        'subtitle' => sprintf(
                            'Wali kelas: %s | %s siswa',
                            $homeroomTeacher?->name ?? 'Belum diatur',
                            $group->count(),
                        ),
                        'students' => $group->values()->map(function (Student $student) use ($homeroomTeacher): array {
                            $genderLabel = match ($student->gender) {
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                                default => '-',
                            };

                            return [
                                'id' => $student->id,
                                'name' => $student->name,
                                'nik' => $student->nik,
                                'nisn' => $student->nisn ?? '-',
                                'photo_url' => $student->photo_url,
                                'has_account' => $student->user_id !== null,
                                'default_password' => $student->user_id !== null && $student->birth_date !== null
                                    ? $student->birth_date->format('dmY')
                                    : '',
                                'birth_date_label' => $student->birth_date?->format('d-m-Y') ?? '-',
                                'birth_date_value' => $student->birth_date?->format('Y-m-d') ?? '',
                                'gender' => $student->gender ?? '',
                                'gender_label' => $genderLabel,
                                'class_id' => $student->school_class_id,
                                'class_name' => $student->schoolClass?->name ?? 'Belum diatur',
                                'homeroom_teacher' => $homeroomTeacher?->name ?? 'Belum diatur',
                                'homeroom_phone' => $homeroomTeacher?->phone ?? '-',
                                'status' => 'Aktif',
                                'phone' => $student->phone ?? '',
                                'address' => $student->address ?? '',
                                'detail_siswa' => [
                                    'religion' => $student->detailSiswa?->religion ?? '',
                                    'birth_place' => $student->detailSiswa?->birth_place ?? '',
                                    'address_street' => $student->detailSiswa?->address_street ?? '',
                                    'address_village' => $student->detailSiswa?->address_village ?? '',
                                    'address_district' => $student->detailSiswa?->address_district ?? '',
                                    'address_province' => $student->detailSiswa?->address_province ?? '',
                                    'address_city' => $student->detailSiswa?->address_city ?? '',
                                    'father_name' => $student->detailSiswa?->father_name ?? '',
                                    'father_education' => $student->detailSiswa?->father_education ?? '',
                                    'father_occupation' => $student->detailSiswa?->father_occupation ?? '',
                                    'mother_name' => $student->detailSiswa?->mother_name ?? '',
                                    'mother_education' => $student->detailSiswa?->mother_education ?? '',
                                    'mother_occupation' => $student->detailSiswa?->mother_occupation ?? '',
                                    'parent_address' => $student->detailSiswa?->parent_address ?? '',
                                    'parent_province' => $student->detailSiswa?->parent_province ?? '',
                                    'parent_city' => $student->detailSiswa?->parent_city ?? '',
                                    'postal_code' => $student->detailSiswa?->postal_code ?? '',
                                    'parent_phone' => $student->detailSiswa?->parent_phone ?? '',
                                    'previous_school' => $student->detailSiswa?->previous_school ?? '',
                                ],
                                'search_text' => mb_strtolower(implode(' ', array_filter([
                                    $student->name,
                                    $student->nik,
                                    $student->nisn,
                                    $student->schoolClass?->name,
                                    $homeroomTeacher?->name,
                                ]))),
                            ];
                        })->all(),
                    ];
                })
                ->values()
                ->all();

            $classOptions = SchoolClass::query()
                ->orderBy('name')
                ->get()
                ->map(fn(SchoolClass $schoolClass): array => [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                ])
                ->values()
                ->all();

            $studentPayload = collect($directoryGroups)
                ->flatMap(fn(array $group): array => $group['students'])
                ->mapWithKeys(fn(array $student): array => [
                    $student['id'] => [
                        'id' => $student['id'],
                        'name' => $student['name'],
                        'nik' => $student['nik'],
                        'nisn' => $student['nisn'] !== '-' ? $student['nisn'] : '',
                        'photo_url' => $student['photo_url'],
                        'has_account' => $student['has_account'],
                        'default_password' => $student['default_password'],
                        'gender' => $student['gender'],
                        'gender_label' => $student['gender_label'],
                        'birth_date' => $student['birth_date_value'],
                        'birth_date_label' => $student['birth_date_label'],
                        'phone' => $student['phone'],
                        'address' => $student['address'],
                        'class_name' => $student['class_name'],
                        'homeroom_teacher' => $student['homeroom_teacher'],
                        'homeroom_phone' => $student['homeroom_phone'],
                        'status' => $student['status'],
                        'school_class_id' => $student['class_id'],
                        'detail_siswa' => [
                            'religion' => $student['detail_siswa']['religion'] ?? '',
                            'birth_place' => $student['detail_siswa']['birth_place'] ?? '',
                            'address_street' => $student['detail_siswa']['address_street'] ?? '',
                            'address_village' => $student['detail_siswa']['address_village'] ?? '',
                            'address_district' => $student['detail_siswa']['address_district'] ?? '',
                            'address_province' => $student['detail_siswa']['address_province'] ?? '',
                            'address_city' => $student['detail_siswa']['address_city'] ?? '',
                            'father_name' => $student['detail_siswa']['father_name'] ?? '',
                            'father_education' => $student['detail_siswa']['father_education'] ?? '',
                            'father_occupation' => $student['detail_siswa']['father_occupation'] ?? '',
                            'mother_name' => $student['detail_siswa']['mother_name'] ?? '',
                            'mother_education' => $student['detail_siswa']['mother_education'] ?? '',
                            'mother_occupation' => $student['detail_siswa']['mother_occupation'] ?? '',
                            'parent_address' => $student['detail_siswa']['parent_address'] ?? '',
                            'parent_province' => $student['detail_siswa']['parent_province'] ?? '',
                            'parent_city' => $student['detail_siswa']['parent_city'] ?? '',
                            'postal_code' => $student['detail_siswa']['postal_code'] ?? '',
                            'parent_phone' => $student['detail_siswa']['parent_phone'] ?? '',
                            'previous_school' => $student['detail_siswa']['previous_school'] ?? '',
                        ],
                    ],
                ])
                ->all();

            return [
                'total_students' => $students->count(),
                'groups' => $directoryGroups,
                'classes' => $classOptions,
                'studentPayload' => $studentPayload,
            ];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'title' => 'Data Siswa',
                'total_students' => $cachedData['total_students'],
                'groups' => $cachedData['groups'],
                'classes' => $cachedData['classes'],
                'akses' => $payload['akses'],
            ]);
        }

        return view('dashboard.admin-students', [
            'pageTitle' => 'Data Siswa',
            'menuSections' => $this->adminPageMenu('data-siswa'),
            'directoryTitle' => 'Siswa ' . $this->schoolName(),
            'directorySubtitle' => 'Kelola data siswa aktif per kelas, cari cepat, filter kelas, dan lakukan pembaruan dasar dari satu halaman.',
            'directoryGroups' => $cachedData['groups'],
            'classOptions' => $cachedData['classes'],
            'studentPayload' => $cachedData['studentPayload'],
            'totalStudents' => $cachedData['total_students'],
        ]);
    }

    public function adminTeachers(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'admin sekolah',
            'akses' => [
                'crud data siswa',
                'crud data guru',
                'crud data kelas',
                'crud mapel',
                'ploting kelas siswa dan walikelas',
                'crud jadwal ajar',
            ],
        ];

        $cachedData = \Illuminate\Support\Facades\Cache::remember('admin_teachers_payload', 3600, function(): array {
            $teachers = Teacher::query()
                ->with([
                    'homeroomClasses:id,name,homeroom_teacher_id',
                    'subjects:id,name',
                    'teachingAssignments.subject:id,name',
                    'teachingAssignments.schoolClass:id,name',
                ])
                ->withCount(['subjects', 'homeroomClasses', 'teachingAssignments'])
                ->orderBy('name')
                ->get();

            $categorizeTeacher = function (Teacher $teacher): array {
                return $teacher->roleMeta();
            };

            $directoryGroups = $teachers
                ->groupBy(function (Teacher $teacher) use ($categorizeTeacher): string {
                    return $categorizeTeacher($teacher)['key'];
                })
                ->map(function (Collection $group) use ($categorizeTeacher): array {
                    /** @var Teacher|null $firstTeacher */
                    $firstTeacher = $group->first();
                    $category = $firstTeacher ? $categorizeTeacher($firstTeacher) : ['key' => 'guru', 'label' => 'Guru', 'status' => 'Guru'];

                    return [
                        'key' => $category['key'],
                        'category' => $category['key'],
                        'label' => $category['label'],
                        'subtitle' => sprintf(
                            '%s guru | %s kelas perwalian',
                            $group->count(),
                            $group->sum('homeroom_classes_count'),
                        ),
                        'teachers' => $group->values()->map(function (Teacher $teacher) use ($categorizeTeacher): array {
                            $category = $categorizeTeacher($teacher);
                            $homeroomClasses = $teacher->homeroomClasses
                                ->pluck('name')
                                ->filter()
                                ->values()
                                ->all();
                            $subjectNames = $teacher->subjects
                                ->pluck('name')
                                ->merge(
                                    $teacher->teachingAssignments
                                        ->map(fn($assignment) => $assignment->subject?->name)
                                        ->filter()
                                )
                                ->filter()
                                ->unique()
                                ->values()
                                ->all();
                            $teachingClassNames = $teacher->teachingAssignments
                                ->map(fn($assignment) => $assignment->schoolClass?->name)
                                ->filter()
                                ->unique()
                                ->values()
                                ->all();
                            $genderLabel = match ($teacher->gender) {
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                                default => '-',
                            };

                            return [
                                'id' => $teacher->id,
                                'name' => $teacher->name,
                                'photo_url' => $teacher->photo_url,
                                'nik' => $teacher->nik ?? '-',
                                'nip' => $teacher->nip,
                                'birth_place' => $teacher->birth_place ?? '-',
                                'birth_date_label' => $teacher->birth_date?->format('d-m-Y') ?? '-',
                                'birth_date_value' => $teacher->birth_date?->format('Y-m-d') ?? '',
                                'gender' => $teacher->gender ?? '',
                                'gender_label' => $genderLabel,
                                'religion' => $teacher->religion ?? '-',
                                'employment_status' => $teacher->employment_status ?? '-',
                                'position' => $teacher->position ?? '-',
                                'join_date_label' => $teacher->join_date?->format('d-m-Y') ?? '-',
                                'join_date_value' => $teacher->join_date?->format('Y-m-d') ?? '',
                                'last_education' => $teacher->last_education ?? '-',
                                'major' => $teacher->major ?? '-',
                                'university' => $teacher->university ?? '-',
                                'category' => $category['key'],
                                'category_label' => $category['label'],
                                'status' => $category['status'],
                                'homeroom_classes' => count($homeroomClasses) > 0 ? implode(', ', $homeroomClasses) : '-',
                                'homeroom_count' => $teacher->homeroom_classes_count,
                                'teaching_count' => $teacher->teaching_assignments_count,
                                'subject_count' => count($subjectNames),
                                'subject_names' => $subjectNames,
                                'subject_label' => $subjectNames !== [] ? implode(', ', $subjectNames) : '-',
                                'teaching_class_names' => $teachingClassNames,
                                'teaching_class_label' => $teachingClassNames !== [] ? implode(', ', $teachingClassNames) : '-',
                                'phone' => $teacher->phone ?? '-',
                                'address' => $teacher->address ?? '-',
                                'search_text' => mb_strtolower(implode(' ', array_filter([
                                    $teacher->name,
                                    $teacher->nik,
                                    $teacher->nip,
                                    $teacher->birth_place,
                                    $teacher->religion,
                                    $teacher->employment_status,
                                    $teacher->position,
                                    $teacher->last_education,
                                    $teacher->major,
                                    $teacher->university,
                                    $teacher->phone,
                                    $teacher->address,
                                    $category['label'],
                                    implode(' ', $subjectNames),
                                    implode(' ', $teachingClassNames),
                                    implode(' ', $homeroomClasses),
                                ]))),
                            ];
                        })->all(),
                    ];
                })
                ->values()
                ->all();

            $categoryOptions = [
                ['key' => 'guru', 'label' => 'Guru'],
                ['key' => 'guru-mapel', 'label' => 'Guru Mapel'],
                ['key' => 'walikelas', 'label' => 'Walikelas'],
                ['key' => 'guru-mapel-walikelas', 'label' => 'Guru Mapel + Walikelas'],
            ];

            $teacherPayload = collect($directoryGroups)
                ->flatMap(fn(array $group): array => $group['teachers'])
                ->mapWithKeys(fn(array $teacher): array => [
                    $teacher['id'] => [
                        'id' => $teacher['id'],
                        'name' => $teacher['name'],
                        'photo_url' => $teacher['photo_url'],
                        'nik' => $teacher['nik'] !== '-' ? $teacher['nik'] : '',
                        'nip' => $teacher['nip'],
                        'birth_place' => $teacher['birth_place'] !== '-' ? $teacher['birth_place'] : '',
                        'birth_date' => $teacher['birth_date_value'],
                        'gender' => $teacher['gender'],
                        'gender_label' => $teacher['gender_label'],
                        'religion' => $teacher['religion'] !== '-' ? $teacher['religion'] : '',
                        'employment_status' => $teacher['employment_status'] !== '-' ? $teacher['employment_status'] : '',
                        'position' => $teacher['position'] !== '-' ? $teacher['position'] : '',
                        'join_date' => $teacher['join_date_value'],
                        'last_education' => $teacher['last_education'] !== '-' ? $teacher['last_education'] : '',
                        'major' => $teacher['major'] !== '-' ? $teacher['major'] : '',
                        'university' => $teacher['university'] !== '-' ? $teacher['university'] : '',
                        'phone' => $teacher['phone'] !== '-' ? $teacher['phone'] : '',
                        'address' => $teacher['address'] !== '-' ? $teacher['address'] : '',
                        'category' => $teacher['category'],
                        'category_label' => $teacher['category_label'],
                        'status' => $teacher['status'],
                        'birth_date_label' => $teacher['birth_date_label'],
                        'join_date_label' => $teacher['join_date_label'],
                        'teaching_count' => $teacher['teaching_count'],
                        'subject_count' => $teacher['subject_count'],
                        'subject_label' => $teacher['subject_label'],
                        'teaching_class_label' => $teacher['teaching_class_label'],
                        'homeroom_classes' => $teacher['homeroom_classes'],
                    ],
                ])
                ->all();

            return [
                'total_teachers' => $teachers->count(),
                'groups' => $directoryGroups,
                'categories' => $categoryOptions,
                'teacherPayload' => $teacherPayload,
            ];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'title' => 'Data Guru',
                'total_teachers' => $cachedData['total_teachers'],
                'groups' => $cachedData['groups'],
                'categories' => $cachedData['categories'],
                'akses' => $payload['akses'],
            ]);
        }

        return view('dashboard.admin-teachers', [
            'pageTitle' => 'Data Guru',
            'menuSections' => $this->adminPageMenu('data-guru'),
            'directoryTitle' => 'Guru ' . $this->schoolName(),
            'directorySubtitle' => 'Kelola data guru aktif, kelompokkan menurut kategori peran, dan lakukan pembaruan dasar dari satu halaman.',
            'directoryGroups' => $cachedData['groups'],
            'categoryOptions' => $cachedData['categories'],
            'teacherPayload' => $cachedData['teacherPayload'],
            'totalTeachers' => $cachedData['total_teachers'],
        ]);
    }

    public function adminClasses(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'admin sekolah',
            'akses' => [
                'crud data siswa',
                'crud data guru',
                'crud data kelas',
                'crud mapel',
                'ploting walikelas, siswa, dan mapel ke kelas',
                'crud jadwal ajar',
            ],
        ];

        $cachedData = \Illuminate\Support\Facades\Cache::remember('admin_classes_payload', 3600, function(): array {
            $classes = SchoolClass::query()
                ->with([
                    'homeroomTeacher',
                    'students' => fn($query) => $query->select(['id', 'name', 'nik', 'school_class_id'])->orderBy('name'),
                    'subjects' => fn($query) => $query->select(['subjects.id', 'name', 'code'])->orderBy('name'),
                ])
                ->withCount(['students', 'subjects', 'teachingAssignments'])
                ->orderBy('level')
                ->orderBy('name')
                ->get();

            $directoryGroups = $classes
                ->groupBy(fn(SchoolClass $schoolClass): string => $schoolClass->level)
                ->map(function (Collection $group, string $level): array {
                    return [
                        'key' => 'level-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $level) ?: 'kelas'),
                        'level' => $level,
                        'label' => 'Kelas ' . $level,
                        'subtitle' => sprintf(
                            '%s kelas | %s siswa',
                            $group->count(),
                            $group->sum('students_count'),
                        ),
                        'classes' => $group->values()->map(function (SchoolClass $schoolClass): array {
                            $studentNames = $schoolClass->students
                                ->pluck('name')
                                ->filter()
                                ->values()
                                ->all();
                            $subjectNames = $schoolClass->subjects
                                ->map(fn(Subject $subject): string => trim($subject->code . ' ' . $subject->name))
                                ->filter()
                                ->values()
                                ->all();

                            return [
                                'id' => $schoolClass->id,
                                'name' => $schoolClass->name,
                                'level' => $schoolClass->level,
                                'academic_year' => $schoolClass->academic_year,
                                'homeroom_teacher_id' => $schoolClass->homeroom_teacher_id,
                                'homeroom_teacher' => $schoolClass->homeroomTeacher?->name ?? 'Belum diatur',
                                'student_ids' => $schoolClass->students->pluck('id')->values()->all(),
                                'subject_ids' => $schoolClass->subjects->pluck('id')->values()->all(),
                                'students_count' => $schoolClass->students_count,
                                'subjects_count' => $schoolClass->subjects_count,
                                'teaching_count' => $schoolClass->teaching_assignments_count,
                                'description' => $schoolClass->description ?: '-',
                                'status' => $schoolClass->homeroom_teacher_id ? 'Aktif' : 'Perlu wali',
                                'search_text' => mb_strtolower(implode(' ', array_filter([
                                    $schoolClass->name,
                                    $schoolClass->level,
                                    $schoolClass->academic_year,
                                    $schoolClass->description,
                                    $schoolClass->homeroomTeacher?->name,
                                    implode(' ', $studentNames),
                                    implode(' ', $subjectNames),
                                ]))),
                            ];
                        })->all(),
                    ];
                })
                ->values()
                ->all();

            $levelOptions = $classes
                ->pluck('level')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->map(fn(string $level): array => [
                    'value' => $level,
                    'label' => 'Kelas ' . $level,
                ])
                ->all();

            $teacherOptions = Teacher::query()
                ->with('homeroomClasses:id,name,homeroom_teacher_id')
                ->orderBy('name')
                ->get()
                ->map(function (Teacher $teacher): array {
                    $homeroomClass = $teacher->homeroomClasses->first();

                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->name,
                        'homeroom_class_id' => $homeroomClass?->id,
                        'homeroom_class_name' => $homeroomClass?->name,
                    ];
                })
                ->values()
                ->all();

            $studentOptions = Student::query()
                ->with('schoolClass')
                ->orderBy('name')
                ->get()
                ->map(fn(Student $student): array => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nik' => $student->nik,
                    'current_class' => $student->schoolClass?->name ?? 'Belum ada kelas',
                ])
                ->values()
                ->all();

            $subjectOptions = Subject::query()
                ->orderBy('name')
                ->get()
                ->map(fn(Subject $subject): array => [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'name' => $subject->name,
                ])
                ->values()
                ->all();

            $classPayload = collect($directoryGroups)
                ->flatMap(fn(array $group): array => $group['classes'])
                ->mapWithKeys(fn(array $class): array => [
                    $class['id'] => [
                        'id' => $class['id'],
                        'name' => $class['name'],
                        'level' => $class['level'],
                        'academic_year' => $class['academic_year'],
                        'homeroom_teacher_id' => $class['homeroom_teacher_id'],
                        'description' => $class['description'] !== '-' ? $class['description'] : '',
                        'student_ids' => $class['student_ids'],
                        'subject_ids' => $class['subject_ids'],
                    ],
                ])
                ->all();

            return [
                'total_classes' => $classes->count(),
                'groups' => $directoryGroups,
                'levels' => $levelOptions,
                'teachers' => $teacherOptions,
                'students' => $studentOptions,
                'subjects' => $subjectOptions,
                'classPayload' => $classPayload,
            ];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'title' => 'Data Kelas',
                'total_classes' => $cachedData['total_classes'],
                'groups' => $cachedData['groups'],
                'levels' => $cachedData['levels'],
                'teachers' => $cachedData['teachers'],
                'students' => $cachedData['students'],
                'subjects' => $cachedData['subjects'],
                'akses' => $payload['akses'],
            ]);
        }

        return view('dashboard.admin-classes', [
            'pageTitle' => 'Data Kelas',
            'menuSections' => $this->adminPageMenu('data-kelas'),
            'directoryTitle' => 'Kelas ' . $this->schoolName(),
            'directorySubtitle' => 'Kelola struktur kelas, wali kelas, dan ringkasan siswa aktif per tingkat dari satu halaman.',
            'directoryGroups' => $cachedData['groups'],
            'levelOptions' => $cachedData['levels'],
            'teacherOptions' => $cachedData['teachers'],
            'studentOptions' => $cachedData['students'],
            'subjectOptions' => $cachedData['subjects'],
            'classPayload' => $cachedData['classPayload'],
            'totalClasses' => $cachedData['total_classes'],
        ]);
    }

    public function adminSubjects(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'admin sekolah',
            'akses' => [
                'crud data siswa',
                'crud data guru',
                'crud data kelas',
                'crud mapel',
                'ploting kelas siswa dan walikelas',
                'crud jadwal ajar',
            ],
        ];

        $cachedData = \Illuminate\Support\Facades\Cache::remember('admin_subjects_payload', 3600, function(): array {
            $subjects = Subject::query()
                ->with([
                    'teachers:id,name',
                    'schoolClass:id,name',
                    'schoolClasses' => fn($query) => $query
                        ->select(['school_classes.id', 'name', 'level'])
                        ->withCount('students'),
                    'teachingAssignments:id,subject_id,teacher_id,school_class_id',
                ])
                ->withCount('teachingAssignments')
                ->orderBy('name')
                ->get();

            $directoryGroups = $subjects
                ->groupBy(fn(Subject $subject): string => $subject->teaching_assignments_count > 0 ? 'dipakai' : 'belum-dipakai')
                ->map(function (Collection $group, string $key): array {
                    $label = $key === 'dipakai' ? 'Sudah Dipakai di Jadwal' : 'Belum Dipakai di Jadwal';

                    return [
                        'key' => $key,
                        'usage' => $key,
                        'label' => $label,
                        'subtitle' => sprintf(
                            '%s mapel | %s total jadwal',
                            $group->count(),
                            $group->sum('teaching_assignments_count'),
                        ),
                        'subjects' => $group->values()->map(function (Subject $subject) use ($key): array {
                            $teacherNames = $subject->teachers
                                ->pluck('name')
                                ->filter()
                                ->values()
                                ->all();
                            $classNames = $subject->schoolClasses
                                ->map(fn(SchoolClass $schoolClass): string => $schoolClass->name)
                                ->filter()
                                ->values()
                                ->all();
                            $studentsTaught = (int) $subject->schoolClasses->sum('students_count');

                            $teacherCount = $subject->teachers
                                ->pluck('id')
                                ->filter()
                                ->unique()
                                ->count();

                            $classCount = $subject->teachingAssignments
                                ->pluck('school_class_id')
                                ->filter()
                                ->unique()
                                ->count();

                            $dayName = $subject->day_of_week !== null ? (config('schedule.day_names')[$subject->day_of_week] ?? '-') : '-';
                            $timeLabel = $subject->start_time && $subject->end_time ? substr($subject->start_time, 0, 5) . ' - ' . substr($subject->end_time, 0, 5) : '-';

                            return [
                                'id' => $subject->id,
                                'code' => $subject->code,
                                'name' => $subject->name,
                                'lesson_hours' => $subject->lesson_hours,
                                'lesson_hours_label' => $subject->lesson_hours ? $subject->lesson_hours . ' JP' : '-',
                                'description' => $subject->description ?: '-',
                                'usage' => $key,
                                'usage_label' => $key === 'dipakai' ? 'Dipakai' : 'Belum dipakai',
                                'school_class_id' => $subject->school_class_id,
                                'class_name' => $subject->schoolClass?->name ?? '-',
                                'day_of_week' => $subject->day_of_week,
                                'day_name' => $dayName,
                                'start_time' => $subject->start_time,
                                'end_time' => $subject->end_time,
                                'time_label' => $timeLabel,
                                'teacher_ids' => $subject->teachers->pluck('id')->values()->all(),
                                'teacher_names' => $teacherNames,
                                'teacher_label' => $teacherNames !== [] ? implode(', ', $teacherNames) : 'Belum diatur',
                                'class_ids' => $subject->schoolClasses->pluck('id')->values()->all(),
                                'class_names' => $classNames,
                                'class_label' => $classNames !== [] ? implode(', ', $classNames) : 'Belum diatur',
                                'students_taught' => $studentsTaught,
                                'students_taught_label' => $studentsTaught . ' siswa',
                                'teacher_count' => $teacherCount,
                                'class_count' => $classCount,
                                'schedule_count' => $subject->teaching_assignments_count,
                                'search_text' => mb_strtolower(implode(' ', array_filter([
                                    $subject->code,
                                    $subject->name,
                                    $subject->lesson_hours,
                                    $subject->description,
                                    $key,
                                    $dayName,
                                    $subject->schoolClass?->name,
                                    implode(' ', $teacherNames),
                                    implode(' ', $classNames),
                                ]))),
                            ];
                        })->all(),
                    ];
                })
                ->values()
                ->all();

            $usageOptions = [
                ['value' => 'dipakai', 'label' => 'Sudah dipakai'],
                ['value' => 'belum-dipakai', 'label' => 'Belum dipakai'],
            ];

            $teacherOptions = Teacher::query()
                ->withCount(['subjects', 'teachingAssignments', 'homeroomClasses'])
                ->orderBy('name')
                ->get()
                ->map(fn(Teacher $teacher): array => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'role' => $teacher->roleMeta()['label'],
                ])
                ->values()
                ->all();

            $classOptions = SchoolClass::query()
                ->orderBy('name')
                ->get()
                ->map(fn(SchoolClass $schoolClass): array => [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                    'level' => $schoolClass->level,
                ])
                ->values()
                ->all();

            $subjectPayload = collect($directoryGroups)
                ->flatMap(fn(array $group): array => $group['subjects'])
                ->mapWithKeys(fn(array $subject): array => [
                    $subject['id'] => [
                        'id' => $subject['id'],
                        'code' => $subject['code'],
                        'name' => $subject['name'],
                        'lesson_hours' => $subject['lesson_hours'],
                        'description' => $subject['description'] !== '-' ? $subject['description'] : '',
                        'teacher_ids' => $subject['teacher_ids'],
                        'class_ids' => $subject['class_ids'],
                        'school_class_id' => $subject['school_class_id'],
                        'day_of_week' => $subject['day_of_week'],
                        'start_time' => $subject['start_time'] ? substr($subject['start_time'], 0, 5) : '',
                        'end_time' => $subject['end_time'] ? substr($subject['end_time'], 0, 5) : '',
                    ],
                ])
                ->all();

            return [
                'total_subjects' => $subjects->count(),
                'groups' => $directoryGroups,
                'usage_options' => $usageOptions,
                'teachers' => $teacherOptions,
                'classes' => $classOptions,
                'subjectPayload' => $subjectPayload,
            ];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'title' => 'Mata Pelajaran',
                'total_subjects' => $cachedData['total_subjects'],
                'groups' => $cachedData['groups'],
                'usage_options' => $cachedData['usage_options'],
                'teachers' => $cachedData['teachers'],
                'classes' => $cachedData['classes'],
                'akses' => $payload['akses'],
            ]);
        }

        return view('dashboard.admin-subjects', [
            'pageTitle' => 'Mata Pelajaran',
            'menuSections' => $this->adminPageMenu('mata-pelajaran'),
            'directoryTitle' => 'Mata Pelajaran ' . $this->schoolName(),
            'directorySubtitle' => 'Kelola mapel aktif, pantau keterpakaiannya pada jadwal, dan perbarui detail utama dari satu halaman.',
            'directoryGroups' => $cachedData['groups'],
            'usageOptions' => $cachedData['usage_options'],
            'teacherOptions' => $cachedData['teachers'],
            'classOptions' => $cachedData['classes'],
            'subjectPayload' => $cachedData['subjectPayload'],
            'totalSubjects' => $cachedData['total_subjects'],
        ]);
    }

    public function teacherStudentsPage(Request $request): View
    {
        $teacher = $this->teacherFromRequest($request);
        $students = $request->user()->hasRole('admin')
            ? Student::query()->with('schoolClass')->orderBy('name')->get()
            : $this->schoolClassService->studentsForHomeroomTeacher($teacher);

        return view('dashboard.portal-students', $this->portalStudentDirectoryData(
            $request,
            'walikelas',
            'Data Siswa Perwalian',
            'Lihat siswa per kelas perwalian, cek kontak dasar, dan lanjutkan ke absensi kelas dari satu halaman.',
            $students,
            'Siswa perwalian',
        ));
    }

    public function homeroomStudentsPage(Request $request): View
    {
        $teacher = $this->teacherFromRequest($request);
        $students = $request->user()->hasRole('admin')
            ? Student::query()->with('schoolClass')->orderBy('name')->get()
            : $this->schoolClassService->studentsForHomeroomTeacher($teacher);

        return view('dashboard.portal-students', $this->portalStudentDirectoryData(
            $request,
            'walikelas',
            'Data Siswa Perwalian',
            'Lihat siswa per kelas perwalian, cek kontak dasar, dan lanjutkan ke absensi kelas dari satu halaman.',
            $students,
            'Siswa perwalian',
        ));
    }

    public function homeroomAttendancePage(Request $request): View
    {
        $data = $this->buildHomeroomDashboard($request, $this->homeroomPayload());

        return view('dashboard.homeroom-attendance', array_merge($data, [
            'pageTitle' => 'Absensi Kelas',
            'menuSections' => $this->menuForPortalPage('walikelas', 'Absensi Kelas'),
        ]));
    }

    public function homeroomAttendanceRecapPage(Request $request): Response
    {
        $data = $this->buildHomeroomDashboard($request, $this->homeroomPayload());

        return response()->view('dashboard.homeroom-attendance-recap', array_merge($data, [
            'pageTitle' => 'Rekap Absensi Kelas',
            'menuSections' => $this->menuForPortalPage('walikelas', 'Rekap Absensi'),
        ]))->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }



    public function teacher(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'guru mapel',
            'akses' => [
                'melakukan absensi mapel',
                'melihat jadwal ajar',
                'melihat siswa kelas yang diajar',
                'rekap absen mapel',
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json(array_merge($payload, $this->buildTeacherDashboard($request, $payload)));
        }

        return view('dashboard.portal', $this->buildTeacherDashboard($request, $payload));
    }

    public function teacherSchedulePage(Request $request): View
    {
        $data = $this->buildTeacherDashboard($request, $this->teacherPayload());

        $user = $this->dashboardUser($request);
        $isAdmin = $user->hasRole('admin');
        $allSchedules = $isAdmin
            ? $this->teachingAssignmentService->schedules()
            : $this->teachingAssignmentService->scheduleForTeacher($this->teacherFromRequest($request));

        $dayNames = config('schedule.day_names', []);
        $allScheduleRows = $allSchedules
            ->sortBy([['day_of_week', 'asc'], ['start_time', 'asc']])
            ->groupBy('day_of_week')
            ->map(function (Collection $daySchedules, int $day) use ($dayNames): array {
                return [
                    'day' => $dayNames[$day] ?? 'Hari ' . ($day + 1),
                    'day_of_week' => $day,
                    'items' => $daySchedules->values()->map(fn(TeachingAssignment $assignment, int $index): array => [
                        'lesson_period' => $index + 1,
                        'time' => $this->timeRange($assignment->start_time, $assignment->end_time),
                        'subject' => $assignment->subject?->name ?? '-',
                        'class_name' => $assignment->schoolClass?->name ?? '-',
                        'room' => $assignment->room ?? '-',
                        'status' => $this->scheduleStatus($assignment),
                    ])->all(),
                ];
            })
            ->sortBy('day_of_week')
            ->values()
            ->all();

        return view('dashboard.teacher-schedule', array_merge($data, [
            'pageTitle' => 'Jadwal Mengajar',
            'menuSections' => $this->menuForPortalPage('guru-mapel', 'Jadwal Mengajar'),
            'allScheduleRows' => $allScheduleRows,
        ]));
    }

    public function teacherAttendancePage(Request $request): View
    {
        $data = $this->buildTeacherDashboard($request, $this->teacherPayload());

        return view('dashboard.teacher-attendance', array_merge($data, [
            'pageTitle' => 'Tambah Absen',
            'menuSections' => $this->menuForPortalPage('guru-mapel', 'Tambah Absen'),
        ]));
    }

    public function teacherAttendanceSchedules(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $user = $this->dashboardUser($request);
        $schedules = $user->hasRole('admin')
            ? $this->teachingAssignmentService->schedules()
            : $this->teachingAssignmentService->scheduleForTeacher($this->teacherFromRequest($request));

        $scheduleRows = $this->scheduleRowsForDate($schedules, $payload['date']);

        return response()->json([
            'data' => [
                'date' => $payload['date'],
                'schedules' => $scheduleRows,
                'total_schedules' => count($scheduleRows),
                'total_students' => collect($scheduleRows)->sum('students_count'),
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function teacherAttendanceListPage(Request $request): Response
    {
        $data = $this->buildTeacherDashboard($request, $this->teacherPayload());

        return response()->view('dashboard.teacher-attendance-list', array_merge($data, [
            'pageTitle' => 'Daftar Absen',
            'menuSections' => $this->menuForPortalPage('guru-mapel', 'Daftar Absen'),
        ]))->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function teacherClassAttendancePage(Request $request): View
    {
        $teacher = $this->teacherFromRequest($request);
        $allowedClassIds = $this->classAttendanceService->allowedClassIdsForTeacher($teacher);

        $classes = SchoolClass::query()
            ->whereIn('id', $allowedClassIds)
            ->withCount('students')
            ->orderBy('name')
            ->get();

        $students = Student::query()
            ->whereIn('school_class_id', $allowedClassIds)
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        $user = $this->dashboardUser($request);
        $attendanceFilters = $this->classAttendanceFiltersFromRequest($request);
        $attendances = $this->classAttendanceService->recapForTeacher($teacher, $attendanceFilters);

        $latestAttendanceDate = $this->latestAttendanceDate($attendances);
        $latestRecords = $this->recordsForDate($attendances, $latestAttendanceDate);
        $studentIds = $students->pluck('id');
        $openNotes = StudentNote::query()
            ->whereIn('student_id', $studentIds)
            ->whereNull('resolved_at')
            ->count();
        $followUpNotes = StudentNote::query()
            ->whereIn('student_id', $studentIds)
            ->whereNull('resolved_at')
            ->whereDate('follow_up_at', '<=', now()->addWeek()->toDateString())
            ->count();
        $cards = [
            ['label' => 'Total Siswa', 'value' => $students->count(), 'meta' => 'Siswa kelas terkait'],
            ['label' => 'Hadir', 'value' => $latestRecords->where('status', AttendanceStatus::HADIR->value)->count(), 'meta' => 'Hadir hari ini'],
            ['label' => 'Tidak Hadir', 'value' => $latestRecords->where('status', '!=', AttendanceStatus::HADIR->value)->count(), 'meta' => 'Perlu tindak lanjut'],
            ['label' => 'Catatan Terbuka', 'value' => $openNotes, 'meta' => $followUpNotes . ' tindak lanjut dekat'],
        ];

        $data = $this->baseDashboardData($user, 'guru-mapel', $this->teacherPayload(), [
            'hero' => [
                'title' => 'Hai Guru!',
                'subtitle' => 'Isi absensi harian kelas jam pertama Anda di sini.',
                'badge' => $this->formattedTimestamp(),
                'asset' => 'school',
            ],
            'summary' => $cards,
            'classes' => $classes->map(fn(SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'students_count' => $schoolClass->students_count,
                'level' => $schoolClass->level,
            ])->all(),
            'homeroomStudents' => $students->map(fn(Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nik' => $student->nik,
                'school_class_id' => $student->school_class_id,
                'class_name' => $student->schoolClass?->name ?? 'Belum ada kelas',
            ])->values()->all(),
            'attendanceStatuses' => AttendanceStatus::values(),
            'classAttendanceSummary' => $this->classAttendanceSummary($attendances),
            'classAttendanceRecapRows' => $this->classAttendanceRecapRows($attendances),
            'classAttendanceDetailRows' => $this->classAttendanceDetailRows($attendances),
            'attendanceFilters' => $attendanceFilters,
            'homeroomExportClasses' => $this->homeroomExportClasses($classes),
            'noteBox' => $latestRecords
                ->where('status', '!=', AttendanceStatus::HADIR->value)
                ->take(5)
                ->map(fn(ClassAttendance $attendance): string => sprintf(
                    '%s tercatat %s pada %s.',
                    $attendance->student?->name ?? 'Siswa',
                    ucfirst($attendance->status),
                    $attendance->attendance_date?->format('d M Y') ?? '-',
                ))
                ->values()
                ->all(),
            'studentNoteRows' => StudentNote::query()
                ->with('student')
                ->whereIn('student_id', $studentIds)
                ->whereNull('resolved_at')
                ->orderByRaw('follow_up_at is null')
                ->orderBy('follow_up_at')
                ->take(5)
                ->get()
                ->map(fn(StudentNote $note): string => sprintf(
                    '%s: %s%s',
                    $note->student?->name ?? 'Siswa',
                    $note->title,
                    $note->follow_up_at ? ' (' . $note->follow_up_at->format('d M Y') . ')' : '',
                ))
                ->values()
                ->all(),
            'checklist' => [
                'Review absensi kelas sebelum pulang.',
                'Catat siswa yang perlu dihubungi orang tua.',
                'Pastikan data kelas perwalian tetap mutakhir.',
            ],
        ]);

        return view('dashboard.homeroom-attendance', array_merge($data, [
            'pageTitle' => 'Absensi Kelas',
            'menuSections' => $this->menuForPortalPage('guru-mapel', 'Absensi Kelas'),
            'portalKey' => 'guru-mapel',
        ]));
    }

    public function teacherAttendanceRecapPage(Request $request): Response
    {
        $data = $this->buildTeacherDashboard($request, $this->teacherPayload());

        return response()->view('dashboard.teacher-attendance-recap', array_merge($data, [
            'pageTitle' => 'Rekap Absen',
            'menuSections' => $this->menuForPortalPage('guru-mapel', 'Rekap Absen'),
        ]))->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function teacherAttendanceExport(Request $request, string $format): StreamedResponse|Response
    {
        $data = $this->buildTeacherDashboard($request, $this->teacherPayload());
        $mode = $request->query('mode', 'recap');
        $filename = $this->exportFilename('rekap-absensi-mapel', $request);
        $rawRows = $data['attendanceDetailRows'] ?? [];

        if ($mode === 'recap' || $mode === 'ringkasan') {
            $headers = ['Siswa', 'Mapel', 'Kelas', 'Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Alpha (A)', 'Total Pertemuan', 'Persentase Kehadiran (%)'];
            $rows = collect($rawRows)
                ->groupBy(fn (array $r): string => ($r['student'] ?? '') . '|' . ($r['class_name'] ?? '') . '|' . ($r['subject'] ?? ''))
                ->map(function ($group): array {
                    $first = $group->first();
                    $hadir = $group->filter(fn (array $r): bool => strtolower($r['status'] ?? '') === 'hadir')->count();
                    $sakit = $group->filter(fn (array $r): bool => strtolower($r['status'] ?? '') === 'sakit')->count();
                    $izin = $group->filter(fn (array $r): bool => strtolower($r['status'] ?? '') === 'izin')->count();
                    $alpha = $group->filter(fn (array $r): bool => strtolower($r['status'] ?? '') === 'alpha')->count();
                    $total = $group->count();
                    $rate = $total > 0 ? (int) round(($hadir / $total) * 100) : 0;

                    return [
                        $first['student'] ?? '-',
                        $first['subject'] ?? '-',
                        $first['class_name'] ?? '-',
                        $hadir,
                        $sakit,
                        $izin,
                        $alpha,
                        $total,
                        $rate . '%',
                    ];
                })
                ->values()
                ->all();
        } else {
            $headers = ['Konteks', 'Tanggal', 'Mapel', 'Kelas', 'Siswa', 'Status', 'Catatan'];
            $rows = collect($rawRows)->map(fn (array $row): array => [
                'Absensi Mapel',
                $row['date'],
                $row['subject'],
                $row['class_name'],
                $row['student'],
                $row['status'],
                $row['notes'],
            ])->all();
        }

        if ($format === 'csv') {
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

        if ($format === 'xls') {
            return response()
                ->view('dashboard.exports.admin-table-xls', [
                    'title' => 'Rekap Absensi Mapel',
                    'headers' => $headers,
                    'rows' => $rows,
                ])
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '.xls"');
        }

        if ($format === 'pdf') {
            return response()->view('dashboard.exports.admin-table-print', [
                'title' => 'Cetak Rekap Absensi Mapel',
                'headers' => $headers,
                'rows' => $rows,
            ]);
        }

        abort(404, 'Format export tidak ditemukan.');
    }

    public function teacherAttendancePrint(Request $request): View
    {
        $data = $this->buildTeacherDashboard($request, $this->teacherPayload());

        return view('dashboard.exports.teacher-attendance-print', array_merge($data, [
            'pageTitle' => 'Cetak Rekap Absensi Mapel',
        ]));
    }

    public function homeroom(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'walikelas',
            'akses' => [
                'melakukan absensi harian kelas',
                'melihat data siswa di kelas perwalian',
                'rekap absen kelas',
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json(array_merge($payload, $this->buildHomeroomDashboard($request, $payload)));
        }

        return view('dashboard.portal', $this->buildHomeroomDashboard($request, $payload));
    }

    public function homeroomAttendanceExport(Request $request, string $format): StreamedResponse|Response
    {
        $data = $this->buildHomeroomDashboard($request, $this->homeroomPayload());
        $mode = $request->query('mode', 'recap');
        $filename = $this->exportFilename('rekap-absensi-kelas', $request);
        $rawRows = $data['classAttendanceDetailRows'] ?? [];

        if ($mode === 'recap' || $mode === 'ringkasan') {
            $headers = ['Siswa', 'Kelas', 'Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Alpha (A)', 'Total Pertemuan', 'Persentase Kehadiran (%)'];
            $rows = collect($rawRows)
                ->groupBy(fn (array $r): string => ($r['student'] ?? '') . '|' . ($r['class_name'] ?? ''))
                ->map(function ($group): array {
                    $first = $group->first();
                    $hadir = $group->filter(fn (array $r): bool => strtolower($r['status'] ?? '') === 'hadir')->count();
                    $sakit = $group->filter(fn (array $r): bool => strtolower($r['status'] ?? '') === 'sakit')->count();
                    $izin = $group->filter(fn (array $r): bool => strtolower($r['status'] ?? '') === 'izin')->count();
                    $alpha = $group->filter(fn (array $r): bool => strtolower($r['status'] ?? '') === 'alpha')->count();
                    $total = $group->count();
                    $rate = $total > 0 ? (int) round(($hadir / $total) * 100) : 0;

                    return [
                        $first['student'] ?? '-',
                        $first['class_name'] ?? '-',
                        $hadir,
                        $sakit,
                        $izin,
                        $alpha,
                        $total,
                        $rate . '%',
                    ];
                })
                ->values()
                ->all();
        } else {
            $headers = ['Konteks', 'Tanggal', 'Kelas', 'Siswa', 'Status', 'Catatan'];
            $rows = collect($rawRows)->map(fn (array $row): array => [
                'Absensi Kelas Perwalian',
                $row['date'],
                $row['class_name'],
                $row['student'],
                $row['status'],
                $row['notes'],
            ])->all();
        }

        if ($format === 'csv') {
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

        if ($format === 'xls') {
            return response()
                ->view('dashboard.exports.admin-table-xls', [
                    'title' => 'Rekap Absensi Kelas',
                    'headers' => $headers,
                    'rows' => $rows,
                ])
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '.xls"');
        }

        if ($format === 'pdf') {
            return response()->view('dashboard.exports.admin-table-print', [
                'title' => 'Cetak Rekap Absensi Kelas',
                'headers' => $headers,
                'rows' => $rows,
            ]);
        }

        abort(404, 'Format export tidak ditemukan.');
    }

    public function homeroomAttendancePrint(Request $request): View
    {
        $data = $this->buildHomeroomDashboard($request, $this->homeroomPayload());

        return view('dashboard.exports.homeroom-attendance-print', array_merge($data, [
            'pageTitle' => 'Cetak Rekap Absensi Kelas',
        ]));
    }

    public function parent(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'orang tua',
            'akses' => [
                'melihat dashboard perkembangan anak',
                'melihat jadwal sekolah anak',
                'melihat kehadiran harian anak',
                'melihat kehadiran mapel anak',
                'melihat catatan wali kelas',
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json(array_merge($payload, $this->buildParentDashboard($request, $payload)));
        }

        return view('dashboard.portal', $this->buildParentDashboard($request, $payload));
    }

    public function student(Request $request): JsonResponse|View
    {
        $payload = [
            'area' => 'siswa',
            'akses' => [
                'melihat daftar hadir kelas',
                'melihat jadwal sekolah',
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json(array_merge($payload, $this->buildStudentDashboard($request, $payload)));
        }

        return view('dashboard.portal', $this->buildStudentDashboard($request, $payload));
    }

    /**
     * @param Collection<int, Student> $students
     * @param string|\Closure(Student): string $contextResolver
     * @return array<string,mixed>
     */
    protected function portalStudentDirectoryData(
        Request $request,
        string $portalKey,
        string $title,
        string $subtitle,
        Collection $students,
        string|\Closure $contextResolver,
    ): array {
        $user = $this->dashboardUser($request);
        $rows = $students
            ->map(function (Student $student) use ($contextResolver): array {
                $genderLabel = match ($student->gender) {
                    'L' => 'Laki-laki',
                    'P' => 'Perempuan',
                    default => '-',
                };
                $context = is_string($contextResolver)
                    ? $contextResolver
                    : $contextResolver($student);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nik' => $student->nik,
                    'nisn' => $student->nisn ?? '-',
                    'class_name' => $student->schoolClass?->name ?? 'Belum diatur',
                    'gender' => $genderLabel,
                    'birth_date' => $student->birth_date?->format('d-m-Y') ?? '-',
                    'phone' => $student->phone ?? '-',
                    'address' => $student->address ?? '-',
                    'context' => $context,
                    'initials' => collect(preg_split('/\s+/', trim($student->name)) ?: [])
                        ->filter()
                        ->map(static fn(string $part): string => strtoupper(substr($part, 0, 1)))
                        ->take(2)
                        ->implode('') ?: 'SW',
                    'search_text' => mb_strtolower(implode(' ', array_filter([
                        $student->name,
                        $student->nik,
                        $student->nisn,
                        $student->schoolClass?->name,
                        $genderLabel,
                        $student->phone,
                        $context,
                    ]))),
                ];
            })
            ->values();
        $classCount = $rows->pluck('class_name')->filter(fn(string $className): bool => $className !== 'Belum diatur')->unique()->count();
        $studentGroups = $rows
            ->groupBy('class_name')
            ->map(function (Collection $group, string $className): array {
                return [
                    'class_name' => $className,
                    'total' => $group->count(),
                    'male' => $group->where('gender', 'Laki-laki')->count(),
                    'female' => $group->where('gender', 'Perempuan')->count(),
                    'contexts' => $group->pluck('context')->unique()->values()->all(),
                    'students' => $group->sortBy('name')->values()->all(),
                ];
            })
            ->sortBy('class_name')
            ->values();

        return [
            'portalKey' => $portalKey,
            'pageTitle' => $title,
            'menuSections' => $this->menuForPortalPage($portalKey, 'Data Siswa'),
            'profile' => $this->profileFor($user, $portalKey),
            'schoolName' => $this->schoolName(),
            'activeAcademicYear' => $this->activeAcademicYear(),
            'activeSemester' => $this->activeSemester(),
            'directoryTitle' => $title,
            'directorySubtitle' => $subtitle,
            'studentRows' => $rows->all(),
            'studentGroups' => $studentGroups->all(),
            'totalStudents' => $rows->count(),
            'studentSummary' => [
                ['label' => 'Total siswa', 'value' => $rows->count()],
                ['label' => 'Kelas terkait', 'value' => $classCount],
                ['label' => 'Laki-laki', 'value' => $rows->where('gender', 'Laki-laki')->count()],
                ['label' => 'Perempuan', 'value' => $rows->where('gender', 'Perempuan')->count()],
            ],
            'classOptions' => $rows
                ->pluck('class_name')
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array{area:string,akses:array<int,string>} $payload
     * @return array<string,mixed>
     */
    protected function buildAdminDashboard(Request $request, array $payload): array
    {
        $user = $this->dashboardUser($request);

        $heavyData = \Illuminate\Support\Facades\Cache::remember('admin_dashboard_heavy_data', 3600, function(): array {
            $classes = SchoolClass::query()
                ->with(['homeroomTeacher'])
                ->withCount('students')
                ->orderBy('name')
                ->get();

            $classAttendances = $this->classAttendanceService->recap();
            $subjectAttendances = $this->subjectAttendanceService->recap();
            $latestDate = ClassAttendance::query()->max('attendance_date');
            $latestAttendances = $latestDate !== null
                ? ClassAttendance::query()
                ->whereDate('attendance_date', $latestDate)
                ->get()
                ->groupBy('school_class_id')
                : collect();

            $tableRows = $classes->take(8)->map(function (SchoolClass $schoolClass) use ($latestAttendances): array {
                $classAttendance = $latestAttendances->get($schoolClass->id, collect());
                $presentCount = $classAttendance->where('status', AttendanceStatus::HADIR->value)->count();
                $absentCount = $classAttendance->count() - $presentCount;

                return [
                    'class_name' => $schoolClass->name,
                    'students_count' => $schoolClass->students_count,
                    'present_count' => $presentCount,
                    'absent_count' => max($absentCount, 0),
                    'homeroom_teacher' => $schoolClass->homeroomTeacher?->name ?? 'Belum diatur',
                ];
            })->all();

            $openNotes = StudentNote::query()->whereNull('resolved_at')->count();
            $followUpNotes = StudentNote::query()
                ->whereNull('resolved_at')
                ->whereDate('follow_up_at', '<=', now()->addWeek()->toDateString())
                ->count();

            $attendanceReports = $this->buildAdminAttendanceReports($classAttendances, $subjectAttendances, $classes);

            $adminExportClasses = $classes
                ->map(fn(SchoolClass $schoolClass): array => ['id' => $schoolClass->id, 'name' => $schoolClass->name])
                ->values()
                ->all();

            $adminExportSubjects = Subject::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn(Subject $subject): array => ['id' => $subject->id, 'name' => $subject->name])
                ->values()
                ->all();

            return [
                'latestAttendanceDate' => $latestDate,
                'tableRows' => $tableRows,
                'openNotes' => $openNotes,
                'followUpNotes' => $followUpNotes,
                'attendanceReports' => $attendanceReports,
                'adminExportClasses' => $adminExportClasses,
                'adminExportSubjects' => $adminExportSubjects,
                'classesCount' => $classes->count(),
            ];
        });

        return $this->baseDashboardData($user, 'admin', $payload, [
            'hero' => [
                'title' => 'Hai Admin!',
                'subtitle' => $this->schoolName() . ' | Tahun ajaran ' . $this->activeAcademicYear() . '. Cek rekap operasional hari ini dari satu dashboard.',
                'badge' => $this->formattedTimestamp(),
                'asset' => 'admin',
            ],
            'stats' => [
                ['label' => 'Data Siswa', 'value' => Student::query()->count(), 'meta' => 'Siswa aktif'],
                ['label' => 'Data Guru', 'value' => Teacher::query()->count(), 'meta' => 'Guru terdaftar'],
                ['label' => 'Data Kelas', 'value' => $heavyData['classesCount'], 'meta' => 'Kelas aktif'],
                ['label' => 'Mata Pelajaran', 'value' => Subject::query()->count(), 'meta' => 'Mapel tersedia'],
                ['label' => 'Catatan Terbuka', 'value' => $heavyData['openNotes'], 'meta' => $heavyData['followUpNotes'] . ' perlu tindak lanjut'],
            ],
            'tableRows' => $heavyData['tableRows'],
            'latestAttendanceDate' => $heavyData['latestAttendanceDate'],
            'attendanceReports' => $heavyData['attendanceReports'],
            'adminExportClasses' => $heavyData['adminExportClasses'],
            'adminExportSubjects' => $heavyData['adminExportSubjects'],
            'checklist' => [
                'Periksa kelas yang belum memiliki walikelas.',
                'Validasi kelengkapan data guru dan siswa.',
                'Cek rekap absensi terakhir sebelum tutup hari.',
            ],
        ]);
    }

    /**
     * @return array{area:string,akses:array<int,string>}
     */
    protected function adminPayload(): array
    {
        return [
            'area' => 'admin sekolah',
            'akses' => [
                'crud data siswa',
                'crud data guru',
                'crud data kelas',
                'crud mapel',
                'ploting kelas siswa dan walikelas',
                'crud jadwal ajar',
            ],
        ];
    }

    /**
     * @param Collection<int, ClassAttendance> $classAttendances
     * @param Collection<int, SubjectAttendance> $subjectAttendances
     * @param Collection<int, SchoolClass> $classes
     * @return array<string,mixed>
     */
    protected function buildAdminAttendanceReports(Collection $classAttendances, Collection $subjectAttendances, Collection $classes): array
    {
        $allAttendances = $classAttendances->concat($subjectAttendances)->values();

        return [
            'weekly_trends' => $this->attendanceTrends($allAttendances, 'week', 6),
            'monthly_trends' => $this->attendanceTrends($allAttendances, 'month', 6),
            'top_absent_students' => $this->topAbsentStudents($allAttendances, 8),
            'effective_days' => $this->academicCalendarService->effectiveDays($this->activeAcademicYear(), $this->activeSemester()),
            'low_attendance_classes' => $this->lowAttendanceClasses(
                $classAttendances,
                $classes,
                6,
                $this->academicCalendarService->effectiveDays($this->activeAcademicYear(), $this->activeSemester()),
            ),
            'subject_teacher_recaps' => $this->subjectTeacherRecaps($subjectAttendances, 8),
        ];
    }

    /**
     * @param Collection<int, ClassAttendance|SubjectAttendance> $records
     * @return array<int, array{key:string,label:string,total:int,present:int,absent:int,present_rate:int}>
     */
    protected function attendanceTrends(Collection $records, string $period, int $limit): array
    {
        return $records
            ->filter(static fn($record): bool => $record->attendance_date !== null)
            ->groupBy(function ($record) use ($period): string {
                $date = CarbonImmutable::parse($record->attendance_date);

                return $period === 'month'
                    ? $date->format('Y-m')
                    : $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
            })
            ->sortKeys()
            ->take(-$limit)
            ->map(function (Collection $periodRecords, string $key) use ($period): array {
                $total = $periodRecords->count();
                $present = $periodRecords->where('status', AttendanceStatus::HADIR->value)->count();
                $date = CarbonImmutable::parse($key);

                return [
                    'key' => $key,
                    'label' => $period === 'month'
                        ? $this->monthLabel($date)
                        : $this->weekLabel($date),
                    'total' => $total,
                    'present' => $present,
                    'absent' => max($total - $present, 0),
                    'present_rate' => $total > 0 ? (int) round(($present / $total) * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, ClassAttendance|SubjectAttendance> $records
     * @return array<int, array{student:string,class_name:string,alpha:int,sakit:int,izin:int,total:int}>
     */
    protected function topAbsentStudents(Collection $records, int $limit): array
    {
        return $records
            ->whereIn('status', [
                AttendanceStatus::ALPHA->value,
                AttendanceStatus::SAKIT->value,
                AttendanceStatus::IZIN->value,
            ])
            ->groupBy('student_id')
            ->map(function (Collection $studentRecords): array {
                $firstRecord = $studentRecords->first();
                $className = $firstRecord instanceof ClassAttendance
                    ? $firstRecord->schoolClass?->name
                    : $firstRecord?->teachingAssignment?->schoolClass?->name;

                return [
                    'student' => $firstRecord?->student?->name ?? 'Siswa',
                    'class_name' => $className ?? 'Belum diatur',
                    'alpha' => $studentRecords->where('status', AttendanceStatus::ALPHA->value)->count(),
                    'sakit' => $studentRecords->where('status', AttendanceStatus::SAKIT->value)->count(),
                    'izin' => $studentRecords->where('status', AttendanceStatus::IZIN->value)->count(),
                    'total' => $studentRecords->count(),
                ];
            })
            ->sortByDesc('total')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, ClassAttendance> $records
     * @param Collection<int, SchoolClass> $classes
     * @return array<int, array{class_name:string,homeroom_teacher:string,total:int,present:int,absent:int,present_rate:int}>
     */
    protected function lowAttendanceClasses(Collection $records, Collection $classes, int $limit, int $effectiveDays = 0): array
    {
        $recordsByClass = $records->groupBy('school_class_id');

        return $classes
            ->map(function (SchoolClass $schoolClass) use ($recordsByClass, $effectiveDays): array {
                $classRecords = $recordsByClass->get($schoolClass->id, collect());
                $total = $classRecords->count();
                $present = $classRecords->where('status', AttendanceStatus::HADIR->value)->count();
                $expected = $effectiveDays > 0
                    ? ((int) $schoolClass->students_count * $effectiveDays)
                    : $total;

                return [
                    'class_name' => $schoolClass->name,
                    'homeroom_teacher' => $schoolClass->homeroomTeacher?->name ?? 'Belum diatur',
                    'total' => $total,
                    'present' => $present,
                    'absent' => max($total - $present, 0),
                    'present_rate' => $expected > 0 ? (int) round(($present / $expected) * 100) : 0,
                ];
            })
            ->filter(static fn(array $row): bool => $row['total'] > 0)
            ->sortBy('present_rate')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, SubjectAttendance> $records
     * @return array<int, array{subject:string,teacher:string,class_name:string,total:int,present:int,alpha:int,sakit:int,izin:int,present_rate:int}>
     */
    protected function subjectTeacherRecaps(Collection $records, int $limit): array
    {
        return $records
            ->groupBy('teaching_assignment_id')
            ->map(function (Collection $assignmentRecords): array {
                $firstRecord = $assignmentRecords->first();
                $assignment = $firstRecord?->teachingAssignment;
                $total = $assignmentRecords->count();
                $present = $assignmentRecords->where('status', AttendanceStatus::HADIR->value)->count();

                return [
                    'subject' => $assignment?->subject?->name ?? 'Mapel',
                    'teacher' => $assignment?->teacher?->name ?? $firstRecord?->recordedByTeacher?->name ?? 'Guru',
                    'class_name' => $assignment?->schoolClass?->name ?? 'Kelas',
                    'total' => $total,
                    'present' => $present,
                    'alpha' => $assignmentRecords->where('status', AttendanceStatus::ALPHA->value)->count(),
                    'sakit' => $assignmentRecords->where('status', AttendanceStatus::SAKIT->value)->count(),
                    'izin' => $assignmentRecords->where('status', AttendanceStatus::IZIN->value)->count(),
                    'present_rate' => $total > 0 ? (int) round(($present / $total) * 100) : 0,
                ];
            })
            ->sortBy('present_rate')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param array{area:string,akses:array<int,string>} $payload
     * @return array<string,mixed>
     */
    protected function buildTeacherDashboard(Request $request, array $payload): array
    {
        $user = $this->dashboardUser($request);
        $isAdmin = $user->hasRole('admin');
        $attendanceFilters = $this->subjectAttendanceFiltersFromRequest($request);
        $schedules = $isAdmin
            ? $this->teachingAssignmentService->schedules()
            : $this->teachingAssignmentService->scheduleForTeacher($this->teacherFromRequest($request));
        $students = $isAdmin
            ? $this->teachingAssignmentService->students()
            : $this->teachingAssignmentService->studentsForTeacher($this->teacherFromRequest($request));
        $attendances = $isAdmin
            ? $this->subjectAttendanceService->recap($attendanceFilters)
            : $this->subjectAttendanceService->recapForTeacher($this->teacherFromRequest($request), $attendanceFilters);

        $scheduleRows = $this->scheduleRowsForDate($schedules, now()->toDateString());
        $latestAttendanceDate = $this->latestAttendanceDate($attendances);
        $todayAssignments = $this->schedulesForDate($schedules, now()->toDateString());
        $attendanceCards = $todayAssignments->take(3)->map(function (TeachingAssignment $assignment) use ($attendances, $latestAttendanceDate): array {
            $summary = $this->subjectAttendanceSummary($attendances, $assignment->id, $latestAttendanceDate);

            return [
                'title' => $assignment->subject?->name ?? 'Mapel',
                'subtitle' => $assignment->schoolClass?->name ?? 'Kelas',
                'counts' => $summary,
            ];
        })->values()->all();

        $allMeetingRowsForFilter = $this->teacherAttendanceMeetingRows($attendances);
        $meetingFilter = $request->query('meeting');
        if (is_string($meetingFilter) && $meetingFilter !== '') {
            $matchingDates = collect($allMeetingRowsForFilter)
                ->filter(fn($row) => $row['meeting_label'] === $meetingFilter)
                ->pluck('date_sort')
                ->unique()
                ->all();

            $attendances = $attendances->filter(function (SubjectAttendance $attendance) use ($matchingDates) {
                return in_array($attendance->attendance_date?->toDateString(), $matchingDates, true);
            });
        }

        return $this->baseDashboardData($user, 'guru-mapel', $payload, [
            'hero' => [
                'title' => 'Hai Guru!',
                'subtitle' => 'Pantau jadwal mengajar dan rekap absensi mapel dari dashboard hari ini.',
                'badge' => $this->formattedTimestamp(),
                'asset' => 'school',
            ],
            'todayLabel' => $this->todayDateLabel(),
            'summary' => [
                ['label' => 'Kelas Diajar', 'value' => $schedules->pluck('school_class_id')->filter()->unique()->count()],
                ['label' => 'Jadwal Aktif', 'value' => $schedules->count()],
                ['label' => 'Siswa Terkait', 'value' => $students->count()],
            ],
            'scheduleRows' => $scheduleRows,
            'teacherStudents' => $students->map(fn(Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nik' => $student->nik,
                'school_class_id' => $student->school_class_id,
                'class_name' => $student->schoolClass?->name ?? 'Belum ada kelas',
            ])->values()->all(),
            'attendanceStatuses' => AttendanceStatus::values(),
            'attendanceCards' => $attendanceCards,
            'attendanceSummary' => $this->teacherAttendanceSummary($attendances),
            'attendanceRecapRows' => $this->teacherAttendanceRecapRows($attendances),
            'attendanceMeetingRows' => $this->teacherAttendanceMeetingRows($attendances),
            'attendanceDetailRows' => $this->teacherAttendanceDetailRows($attendances),
            'attendanceFilters' => $attendanceFilters,
            'teacherExportSubjects' => $this->teacherExportSubjects($schedules),
            'teacherExportClasses' => $this->teacherExportClasses($schedules),
            'checklist' => [
                'Pastikan absensi mapel diisi sebelum jam terakhir selesai.',
                'Cek siswa yang belum hadir pada kelas hari ini.',
                'Siapkan materi untuk sesi berikutnya.',
            ],
        ]);
    }

    /**
     * @return array{area:string,akses:array<int,string>}
     */
    protected function teacherPayload(): array
    {
        return [
            'area' => 'guru mapel',
            'akses' => [
                'melakukan absensi mapel',
                'melihat jadwal ajar',
                'rekap absen mapel',
            ],
        ];
    }

    /**
     * @return array{area:string,akses:array<int,string>}
     */
    protected function homeroomPayload(): array
    {
        return [
            'area' => 'walikelas',
            'akses' => [
                'melakukan absensi harian kelas',
                'melihat data siswa di kelas perwalian',
                'rekap absen kelas',
            ],
        ];
    }

    /**
     * @param Collection<int, SubjectAttendance> $attendances
     * @return array{total:int,hadir:int,izin:int,sakit:int,alpha:int,present_rate:int,latest_date:string}
     */
    protected function teacherAttendanceSummary(Collection $attendances): array
    {
        $total = $attendances->count();
        $hadir = $attendances->where('status', AttendanceStatus::HADIR->value)->count();

        return [
            'total' => $total,
            'hadir' => $hadir,
            'izin' => $attendances->where('status', AttendanceStatus::IZIN->value)->count(),
            'sakit' => $attendances->where('status', AttendanceStatus::SAKIT->value)->count(),
            'alpha' => $attendances->where('status', AttendanceStatus::ALPHA->value)->count(),
            'present_rate' => $total > 0 ? (int) round(($hadir / $total) * 100) : 0,
            'latest_date' => $this->latestAttendanceDate($attendances) ?? '-',
        ];
    }

    /**
     * @param Collection<int, SubjectAttendance> $attendances
     * @return array<int, array<string, int|string>>
     */
    protected function teacherAttendanceRecapRows(Collection $attendances): array
    {
        return $attendances
            ->groupBy('teaching_assignment_id')
            ->map(function (Collection $records): array {
                $first = $records->first();
                $assignment = $first?->teachingAssignment;
                $total = $records->count();
                $hadir = $records->where('status', AttendanceStatus::HADIR->value)->count();

                return [
                    'subject' => $assignment?->subject?->name ?? 'Mapel',
                    'class_name' => $assignment?->schoolClass?->name ?? 'Kelas',
                    'total' => $total,
                    'hadir' => $hadir,
                    'izin' => $records->where('status', AttendanceStatus::IZIN->value)->count(),
                    'sakit' => $records->where('status', AttendanceStatus::SAKIT->value)->count(),
                    'alpha' => $records->where('status', AttendanceStatus::ALPHA->value)->count(),
                    'present_rate' => $total > 0 ? (int) round(($hadir / $total) * 100) : 0,
                    'dates_count' => $records->pluck('attendance_date')->map(fn($date): string => $date?->toDateString() ?? '')->filter()->unique()->count(),
                    'latest_date' => $this->latestAttendanceDate($records) ?? '-',
                ];
            })
            ->sortBy([['subject', 'asc'], ['class_name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, SubjectAttendance> $attendances
     * @return array<int, array<string, string>>
     */
    protected function teacherAttendanceDetailRows(Collection $attendances): array
    {
        return $attendances
            ->map(function (SubjectAttendance $attendance): array {
                $assignment = $attendance->teachingAssignment;
                $dateStr = $attendance->attendance_date?->toDateString() ?? '';

                return [
                    'date' => $attendance->attendance_date?->format('d-m-Y') ?? '-',
                    'subject' => $assignment?->subject?->name ?? 'Mapel',
                    'class_name' => $assignment?->schoolClass?->name ?? 'Kelas',
                    'student' => $attendance->student?->name ?? 'Siswa',
                    'status' => ucfirst($attendance->status),
                    'notes' => $attendance->notes ?: '-',
                    'meeting_label' => $assignment !== null && $dateStr
                        ? $this->getMeetingLabelForSchedule($assignment->id, $dateStr)
                        : '-',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, SubjectAttendance> $attendances
     * @return array<int, array<string, mixed>>
     */
    protected function teacherAttendanceMeetingRows(Collection $attendances): array
    {
        // Group attendance records by teaching_assignment_id + attendance_date.
        $grouped = $attendances
            ->groupBy(fn(SubjectAttendance $attendance): string => implode('|', [
                $attendance->teaching_assignment_id,
                $attendance->attendance_date?->toDateString() ?? '',
            ]));

        // Build raw meeting rows.
        return $grouped->map(function (Collection $records, string $key): array {
            $first = $records->first();
            $assignment = $first?->teachingAssignment;
            $date = $first?->attendance_date;
            $dateString = $date?->toDateString() ?? '';
            $total = $records->count();
            $hadir = $records->where('status', AttendanceStatus::HADIR->value)->count();

            $meetingLabel = $first !== null && $dateString
                ? $this->getMeetingLabelForSchedule($first->teaching_assignment_id, $dateString)
                : '-';

            return [
                'key' => 'attendance-meeting-' . md5($key),
                'date' => $date?->format('d-m-Y') ?? '-',
                'date_sort' => $dateString,
                'subject' => $assignment?->subject?->name ?? 'Mapel',
                'class_name' => $assignment?->schoolClass?->name ?? 'Kelas',
                'time' => $assignment !== null ? $this->timeRange($assignment->start_time, $assignment->end_time) : '-',
                'student_count' => $total,
                'hadir' => $hadir,
                'izin' => $records->where('status', AttendanceStatus::IZIN->value)->count(),
                'sakit' => $records->where('status', AttendanceStatus::SAKIT->value)->count(),
                'alpha' => $records->where('status', AttendanceStatus::ALPHA->value)->count(),
                'present_rate' => $total > 0 ? (int) round(($hadir / $total) * 100) : 0,
                'meeting_label' => $meetingLabel,
                'details' => $records
                    ->sortBy(fn(SubjectAttendance $attendance): string => $attendance->student?->name ?? '')
                    ->map(fn(SubjectAttendance $attendance): array => [
                        'student' => $attendance->student?->name ?? 'Siswa',
                        'identifier' => $attendance->student?->nisn ?: ($attendance->student?->nik ?: '-'),
                        'status' => ucfirst($attendance->status),
                        'notes' => $attendance->notes ?: '-',
                    ])
                    ->values()
                    ->all(),
            ];
        })
        ->sortBy([
            ['date_sort', 'desc'],
            ['subject', 'asc'],
            ['class_name', 'asc'],
            ['time', 'asc'],
        ])
        ->values()
        ->all();
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function subjectAttendanceFiltersFromRequest(Request $request): array
    {
        $filters = $this->attendanceFiltersFromRequest($request, ['subject_id', 'school_class_id', 'teaching_assignment_id']);
        
        $meeting = $request->query('meeting');
        if (is_string($meeting) && $meeting !== '') {
            $filters['meeting'] = $meeting;
        }

        return $filters;
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function classAttendanceFiltersFromRequest(Request $request): array
    {
        return $this->attendanceFiltersFromRequest($request, ['school_class_id']);
    }

    /**
     * @param array<int, string> $integerKeys
     * @return array<string, int|string|null>
     */
    protected function attendanceFiltersFromRequest(Request $request, array $integerKeys): array
    {
        $filters = [];

        foreach ($integerKeys as $key) {
            $value = $request->query($key);

            if ($value !== null && $value !== '') {
                $filters[$key] = (int) $value;
            }
        }

        foreach (['attendance_date', 'date_from', 'date_to'] as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * @param Collection<int, TeachingAssignment> $schedules
     * @return array<int, array{id:int,name:string}>
     */
    protected function teacherExportSubjects(Collection $schedules): array
    {
        return $schedules
            ->filter(fn(TeachingAssignment $assignment): bool => $assignment->subject !== null)
            ->map(fn(TeachingAssignment $assignment): array => [
                'id' => $assignment->subject->id,
                'name' => $assignment->subject->name,
            ])
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, TeachingAssignment> $schedules
     * @return array<int, array{id:int,name:string}>
     */
    protected function teacherExportClasses(Collection $schedules): array
    {
        return $schedules
            ->filter(fn(TeachingAssignment $assignment): bool => $assignment->schoolClass !== null)
            ->map(fn(TeachingAssignment $assignment): array => [
                'id' => $assignment->schoolClass->id,
                'name' => $assignment->schoolClass->name,
            ])
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, SchoolClass> $classes
     * @return array<int, array{id:int,name:string}>
     */
    protected function homeroomExportClasses(Collection $classes): array
    {
        return $classes
            ->map(fn(SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, ClassAttendance> $attendances
     * @return array{total:int,hadir:int,izin:int,sakit:int,alpha:int,present_rate:int,latest_date:string}
     */
    protected function classAttendanceSummary(Collection $attendances): array
    {
        $total = $attendances->count();
        $hadir = $attendances->where('status', AttendanceStatus::HADIR->value)->count();

        return [
            'total' => $total,
            'hadir' => $hadir,
            'izin' => $attendances->where('status', AttendanceStatus::IZIN->value)->count(),
            'sakit' => $attendances->where('status', AttendanceStatus::SAKIT->value)->count(),
            'alpha' => $attendances->where('status', AttendanceStatus::ALPHA->value)->count(),
            'present_rate' => $total > 0 ? (int) round(($hadir / $total) * 100) : 0,
            'latest_date' => $this->latestAttendanceDate($attendances) ?? '-',
        ];
    }

    /**
     * @param Collection<int, ClassAttendance> $attendances
     * @return array<int, array<string, int|string>>
     */
    protected function classAttendanceRecapRows(Collection $attendances): array
    {
        return $attendances
            ->groupBy('school_class_id')
            ->map(function (Collection $records): array {
                $first = $records->first();
                $schoolClass = $first?->schoolClass;
                $total = $records->count();
                $hadir = $records->where('status', AttendanceStatus::HADIR->value)->count();

                return [
                    'class_name' => $schoolClass?->name ?? 'Kelas',
                    'total' => $total,
                    'hadir' => $hadir,
                    'izin' => $records->where('status', AttendanceStatus::IZIN->value)->count(),
                    'sakit' => $records->where('status', AttendanceStatus::SAKIT->value)->count(),
                    'alpha' => $records->where('status', AttendanceStatus::ALPHA->value)->count(),
                    'present_rate' => $total > 0 ? (int) round(($hadir / $total) * 100) : 0,
                    'dates_count' => $records->pluck('attendance_date')->map(fn($date): string => $date?->toDateString() ?? '')->filter()->unique()->count(),
                    'latest_date' => $this->latestAttendanceDate($records) ?? '-',
                ];
            })
            ->sortBy('class_name')
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, ClassAttendance> $attendances
     * @return array<int, array<string, string>>
     */
    protected function classAttendanceDetailRows(Collection $attendances): array
    {
        return $attendances
            ->map(fn(ClassAttendance $attendance): array => [
                'date' => $attendance->attendance_date?->format('d-m-Y') ?? '-',
                'class_name' => $attendance->schoolClass?->name ?? 'Kelas',
                'student' => $attendance->student?->name ?? 'Siswa',
                'status' => ucfirst($attendance->status),
                'notes' => $attendance->notes ?: '-',
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string,mixed> $teacherData
     * @param array<string,mixed> $homeroomData
     * @return array<string,mixed>
     */
    protected function combinedAttendanceExportData(array $teacherData, array $homeroomData, string $scope): array
    {
        $mapelRows = collect($teacherData['attendanceDetailRows'])
            ->map(fn(array $row): array => array_merge(['context' => 'Absensi Mapel'], $row))
            ->all();
        $kelasRows = collect($homeroomData['classAttendanceDetailRows'])
            ->map(fn(array $row): array => [
                'context' => 'Absensi Kelas Perwalian',
                'date' => $row['date'],
                'subject' => '-',
                'class_name' => $row['class_name'],
                'student' => $row['student'],
                'status' => $row['status'],
                'notes' => $row['notes'],
            ])
            ->all();

        return array_merge($teacherData, [
            'exportScope' => $scope,
            'classAttendanceSummary' => $homeroomData['classAttendanceSummary'],
            'classAttendanceRecapRows' => $homeroomData['classAttendanceRecapRows'],
            'classAttendanceDetailRows' => $homeroomData['classAttendanceDetailRows'],
            'combinedAttendanceDetailRows' => match ($scope) {
                'mapel' => $mapelRows,
                'kelas' => $kelasRows,
                default => array_merge($mapelRows, $kelasRows),
            },
        ]);
    }

    protected function exportFilename(string $prefix, Request $request): string
    {
        $parts = [$prefix];

        foreach (['subject_id' => 'mapel', 'school_class_id' => 'kelas', 'attendance_date' => 'tanggal', 'date_from' => 'dari', 'date_to' => 'sampai'] as $key => $label) {
            $value = $request->query($key);

            if ($value !== null && $value !== '') {
                $parts[] = $label . '-' . preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $value);
            }
        }

        $parts[] = now()->format('Ymd-His');

        return implode('-', $parts);
    }

    /**
     * @param array{area:string,akses:array<int,string>} $payload
     * @return array<string,mixed>
     */
    protected function buildHomeroomDashboard(Request $request, array $payload): array
    {
        $user = $this->dashboardUser($request);
        $isAdmin = $user->hasRole('admin');
        $attendanceFilters = $this->classAttendanceFiltersFromRequest($request);
        $classes = $isAdmin
            ? $this->schoolClassService->classes()
            : $this->schoolClassService->classesForHomeroomTeacher($this->teacherFromRequest($request));
        $students = $isAdmin
            ? $this->schoolClassService->students()
            : $this->schoolClassService->studentsForHomeroomTeacher($this->teacherFromRequest($request));
        $attendances = $isAdmin
            ? $this->classAttendanceService->recap($attendanceFilters)
            : $this->classAttendanceService->recapForTeacher($this->teacherFromRequest($request), $attendanceFilters);

        $latestAttendanceDate = $this->latestAttendanceDate($attendances);
        $latestRecords = $this->recordsForDate($attendances, $latestAttendanceDate);
        $studentIds = $students->pluck('id');

        $cards = [
            ['label' => 'Total Siswa', 'value' => $students->count(), 'meta' => 'Siswa perwalian'],
            ['label' => 'Hadir', 'value' => $latestRecords->where('status', AttendanceStatus::HADIR->value)->count(), 'meta' => 'Hadir hari ini'],
            ['label' => 'Tidak Hadir', 'value' => $latestRecords->where('status', '!=', AttendanceStatus::HADIR->value)->count(), 'meta' => 'Perlu tindak lanjut'],
        ];

        $teacher = null;
        if (!$isAdmin) {
            try {
                $teacher = $this->teacherFromRequest($request);
            } catch (\Exception $e) {}
        }

        $teacherSubjectAttendanceHadir = [];
        if ($teacher) {
            $teacherSubjectAttendanceHadir = TeachingAssignment::query()
                ->with(['subject', 'schoolClass'])
                ->where('teacher_id', $teacher->id)
                ->get()
                ->map(function ($assignment) {
                    $hadirCount = SubjectAttendance::query()
                        ->where('teaching_assignment_id', $assignment->id)
                        ->where('status', 'hadir')
                        ->count();

                    return [
                        'id' => $assignment->id,
                        'subject_name' => $assignment->subject?->name ?? 'Mapel',
                        'class_name' => $assignment->schoolClass?->name ?? 'Kelas',
                        'total_hadir' => $hadirCount,
                    ];
                })
                ->values()
                ->all();
        }

        return $this->baseDashboardData($user, 'walikelas', $payload, [
            'hero' => [
                'title' => 'Hai Wali Kelas!',
                'subtitle' => $this->schoolName() . ' | Pantau kelas perwalian, absensi, dan catatan siswa dari satu tempat.',
                'badge' => $this->formattedTimestamp(),
                'asset' => 'school',
            ],
            'summary' => $cards,
            'classes' => $classes->map(fn(SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'students_count' => $schoolClass->students->count(),
                'level' => $schoolClass->level,
            ])->all(),
            'homeroomStudents' => $students->map(fn(Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nik' => $student->nik,
                'school_class_id' => $student->school_class_id,
                'class_name' => $student->schoolClass?->name ?? 'Belum ada kelas',
            ])->values()->all(),
            'attendanceStatuses' => AttendanceStatus::values(),
            'classAttendanceSummary' => $this->classAttendanceSummary($attendances),
            'classAttendanceRecapRows' => $this->classAttendanceRecapRows($attendances),
            'classAttendanceDetailRows' => $this->classAttendanceDetailRows($attendances),
            'attendanceFilters' => $attendanceFilters,
            'homeroomExportClasses' => $this->homeroomExportClasses($classes),
            'noteBox' => $latestRecords
                ->where('status', '!=', AttendanceStatus::HADIR->value)
                ->take(5)
                ->map(fn(ClassAttendance $attendance): string => sprintf(
                    '%s tercatat %s pada %s.',
                    $attendance->student?->name ?? 'Siswa',
                    ucfirst($attendance->status),
                    $attendance->attendance_date?->format('d M Y') ?? '-',
                ))
                ->values()
                ->all(),
            'studentNoteRows' => StudentNote::query()
                ->with('student')
                ->whereIn('student_id', $studentIds)
                ->whereNull('resolved_at')
                ->orderByRaw('follow_up_at is null')
                ->orderBy('follow_up_at')
                ->take(5)
                ->get()
                ->map(fn(StudentNote $note): string => sprintf(
                    '%s: %s%s',
                    $note->student?->name ?? 'Siswa',
                    $note->title,
                    $note->follow_up_at ? ' (' . $note->follow_up_at->format('d M Y') . ')' : '',
                ))
                ->values()
                ->all(),
            'checklist' => [
                'Review absensi kelas sebelum pulang.',
                'Catat siswa yang perlu dihubungi orang tua.',
                'Pastikan data kelas perwalian tetap mutakhir.',
            ],
            'teacherSubjectAttendanceHadir' => $teacherSubjectAttendanceHadir,
        ]);
    }

    /**
     * @param array{area:string,akses:array<int,string>} $payload
     * @return array<string,mixed>
     */
    protected function buildParentDashboard(Request $request, array $payload): array
    {
        $user = $this->dashboardUser($request);
        $childrenData = [];
        $children = $user->parentStudents()->with(['schoolClass.homeroomTeacher', 'classAttendances', 'notes', 'violations'])->get();

        foreach ($children as $child) {
            $attendances = $child->classAttendances;
            $class = $child->schoolClass;
            $homeroomTeacherName = $class?->homeroomTeacher?->name ?? 'Belum ditentukan';

            // Get schedules today
            $dayOfWeek = $this->currentScheduleDay();
            $schedules = TeachingAssignment::query()
                ->where('school_class_id', $class?->id)
                ->where('day_of_week', $dayOfWeek)
                ->orderBy('start_time')
                ->get()
                ->map(fn(TeachingAssignment $assignment): array => [
                    'time' => $this->timeRange($assignment->start_time, $assignment->end_time),
                    'subject' => $assignment->subject?->name ?? '-',
                    'teacher' => $assignment->teacher?->name ?? '-',
                    'room' => $assignment->room ?? '-',
                ])->all();

            // Fetch subject attendance for this child
            $subjectAttendances = SubjectAttendance::query()
                ->with(['teachingAssignment.subject'])
                ->where('student_id', $child->id)
                ->get();

            $subjectStats = $subjectAttendances->groupBy(fn($item) => $item->teachingAssignment?->subject_id ?? 0)->map(function ($items) {
                $first = $items->first();
                return [
                    'subject_name' => $first->teachingAssignment?->subject?->name ?? 'Mapel',
                    'hadir' => $items->where('status', 'hadir')->count(),
                    'sakit' => $items->where('status', 'sakit')->count(),
                    'izin' => $items->where('status', 'izin')->count(),
                    'alpha' => $items->where('status', 'alpha')->count(),
                ];
            })->values()->all();

            $childrenData[] = [
                'id' => $child->id,
                'name' => $child->name,
                'class_name' => $class?->name ?? 'Belum ada kelas',
                'homeroom_teacher' => $homeroomTeacherName,
                'attendance' => [
                    'hadir' => $attendances->where('status', AttendanceStatus::HADIR->value)->count(),
                    'sakit' => $attendances->where('status', AttendanceStatus::SAKIT->value)->count(),
                    'izin' => $attendances->where('status', AttendanceStatus::IZIN->value)->count(),
                    'alpha' => $attendances->where('status', AttendanceStatus::ALPHA->value)->count(),
                ],
                'schedules' => $schedules,
                'subjectStats' => $subjectStats,
                'notes' => $child->notes->map(fn(StudentNote $note): array => [
                    'title' => $note->title,
                    'note' => $note->note,
                ])->all(),
                'violations' => $child->violations->map(fn($v): array => [
                    'type' => $v->violation_type,
                    'points' => $v->points,
                    'description' => $v->description,
                ])->all(),
            ];
        }

        return $this->baseDashboardData($user, 'orang-tua', $payload, [
            'hero' => [
                'title' => 'Hai Orang Tua!',
                'subtitle' => 'Pantau perkembangan belajar dan kehadiran buah hati Anda dengan mudah.',
                'badge' => $this->formattedTimestamp(),
                'asset' => 'school',
            ],
            'children' => $childrenData,
            'checklist' => [
                'Periksa kehadiran harian anak Anda secara berkala.',
                'Tinjau catatan perkembangan dari Wali Kelas.',
                'Hubungi pihak sekolah jika terdapat ketidaksesuaian data.',
            ],
        ]);
    }

    /**
     * @param array{area:string,akses:array<int,string>} $payload
     * @return array<string,mixed>
     */
    protected function buildStudentDashboard(Request $request, array $payload): array
    {
        $user = $this->dashboardUser($request);
        $isAdmin = $user->hasRole('admin');
        $student = $isAdmin ? null : $user->studentProfile?->loadMissing('schoolClass');
        $schedule = ($isAdmin || $student !== null)
            ? ($isAdmin ? $this->teachingAssignmentService->schedules() : $this->teachingAssignmentService->scheduleForStudent($student))
            : collect();
        $attendances = ($isAdmin || $student !== null)
            ? ($isAdmin ? $this->classAttendanceService->recap() : $this->classAttendanceService->recapForStudent($student))
            : collect();

        $scheduleRows = $this->todaySchedules($schedule);
        $attendanceRows = $attendances->take(3)->map(function (ClassAttendance $attendance): array {
            return [
                'date' => $attendance->attendance_date?->format('d M Y') ?? '-',
                'subject' => $attendance->schoolClass?->name ?? 'Kelas',
                'status' => ucfirst($attendance->status),
                'notes' => $attendance->notes ?: 'Tidak ada catatan',
            ];
        })->values()->all();

        $summary = [
            ['label' => 'Hadir', 'value' => $attendances->where('status', AttendanceStatus::HADIR->value)->count(), 'meta' => 'Riwayat hadir'],
            ['label' => 'Izin', 'value' => $attendances->where('status', AttendanceStatus::IZIN->value)->count(), 'meta' => 'Riwayat izin'],
            ['label' => 'Sakit/Alpha', 'value' => $attendances->whereIn('status', [AttendanceStatus::SAKIT->value, AttendanceStatus::ALPHA->value])->count(), 'meta' => 'Perlu diperhatikan'],
        ];

        if (!$isAdmin) {
            array_unshift($summary, [
                'label' => 'Kelas',
                'value' => $student?->schoolClass?->name ?? 'Belum di-plot',
                'meta' => $student?->schoolClass?->name ? 'Data kelas siswa' : 'Hubungi admin sekolah',
            ]);
        }

        $subjectStats = [];
        if (!$isAdmin && $student) {
            $subjectAttendances = SubjectAttendance::query()
                ->with(['teachingAssignment.subject'])
                ->where('student_id', $student->id)
                ->get();

            $subjectStats = $subjectAttendances->groupBy(fn($item) => $item->teachingAssignment?->subject_id ?? 0)->map(function ($items) {
                $first = $items->first();
                return [
                    'subject_name' => $first->teachingAssignment?->subject?->name ?? 'Mapel',
                    'hadir' => $items->where('status', 'hadir')->count(),
                    'sakit' => $items->where('status', 'sakit')->count(),
                    'izin' => $items->where('status', 'izin')->count(),
                    'alpha' => $items->where('status', 'alpha')->count(),
                ];
            })->values()->all();
        }

        $isUnassigned = !$isAdmin && ($student === null || $student->school_class_id === null);

        return $this->baseDashboardData($user, 'siswa', $payload, [
            'hero' => [
                'title' => 'Hai Siswa!',
                'subtitle' => 'Cek jadwal pelajaran dan riwayat kehadiranmu dengan cepat dari dashboard ini.',
                'badge' => $this->formattedTimestamp(),
                'asset' => 'school',
            ],
            'isUnassigned' => $isUnassigned,
            'unassignedReason' => $student === null
                ? 'Akun Anda belum terhubung ke profil siswa. Silakan hubungi admin sekolah.'
                : 'Data kelas belum terploting oleh admin. Silakan hubungi admin sekolah.',
            'summary' => $summary,
            'todayLabel' => $this->todayDateLabel(),
            'studentClassName' => $student?->schoolClass?->name ?? null,
            'scheduleRows' => $scheduleRows->values()->map(fn(TeachingAssignment $assignment, int $index): array => [
                'lesson_period' => $index + 1,
                'time' => $this->timeRange($assignment->start_time, $assignment->end_time),
                'subject' => $assignment->subject?->name ?? '-',
                'teacher' => $assignment->teacher?->name ?? '-',
                'status' => $this->scheduleStatus($assignment)['label'] ?? 'Terjadwal',
            ])->all(),
            'attendanceRows' => $attendanceRows,
            'subjectStats' => $subjectStats,
            'checklist' => [
                'Periksa jadwal pelajaran sebelum masuk kelas.',
                'Pastikan status kehadiranmu sudah tercatat benar.',
                'Catat tugas atau pengumuman penting hari ini.',
            ],
        ]);
    }

    protected function dashboardUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user()->load(['teacherProfile', 'studentProfile']);

        return $user;
    }

    /**
     * @param array{area:string,akses:array<int,string>} $payload
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function getAnnouncementsForUser(User $user): \Illuminate\Support\Collection
    {
        try {
            $announcements = \App\Models\Announcement::query()
                ->with('creator')
                ->latest()
                ->get();

            return $announcements->filter(function (\App\Models\Announcement $announcement) use ($user) {
                $target = $announcement->target_roles;
                if (empty($target)) {
                    return true;
                }
                foreach ($user->roles ?? [] as $role) {
                    if (in_array($role, $target, true)) {
                        return true;
                    }
                }
                return false;
            })->values();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Could not load announcements: ' . $e->getMessage());
            return collect();
        }
    }

    protected function baseDashboardData(User $user, string $portalKey, array $payload, array $data): array
    {
        return array_merge($data, [
            'portalKey' => $portalKey,
            'pageTitle' => AuthService::portalMap()[$portalKey]['label'],
            'menuSections' => $this->menuFor($portalKey),
            'profile' => $this->profileFor($user, $portalKey),
            'calendar' => $this->calendarData(),
            'apiPayload' => $payload,
            'schoolName' => $this->schoolName(),
            'activeAcademicYear' => $this->activeAcademicYear(),
            'activeSemester' => $this->activeSemester(),
            'academicCalendarEvents' => $this->dashboardAcademicCalendarEvents(),
            'announcements' => $this->getAnnouncementsForUser($user),
        ]);
    }

    /**
     * @return array<int, array{title:string,items:array<int, array{label:string,icon:string,href:string,active:bool}>}>
     */
    public function menuFor(string $portalKey): array
    {
        $user = auth()->user();
        $isTeacher = $user && $user->teacherProfile;
        $isHomeroom = false;
        if ($isTeacher) {
            $isHomeroom = \App\Models\SchoolClass::where('homeroom_teacher_id', $user->teacherProfile->id)->exists();
        }

        $menu = match ($portalKey) {
            'admin' => [
                [
                    'title' => 'Utama',
                    'items' => [
                        ['label' => 'Beranda', 'icon' => 'home', 'href' => '#beranda', 'active' => true],
                        ['label' => 'Data Siswa', 'icon' => 'students', 'href' => url('/admin/data-siswa'), 'active' => false],
                        ['label' => 'Data Guru', 'icon' => 'teacher', 'href' => url('/admin/data-guru'), 'active' => false],
                        ['label' => 'Data Kelas', 'icon' => 'class', 'href' => url('/admin/data-kelas'), 'active' => false],
                        ['label' => 'Mata Pelajaran', 'icon' => 'subject', 'href' => url('/admin/mata-pelajaran'), 'active' => false],
                        ['label' => 'Kalender Akademik', 'icon' => 'calendar', 'href' => url('/admin/kalender-akademik'), 'active' => false],
                        ['label' => 'Rekap Kehadiran', 'icon' => 'recap', 'href' => url('/admin/rekap-kehadiran'), 'active' => false],
                        ['label' => 'Laporan Statistik', 'icon' => 'chart', 'href' => url('/admin/laporan-statistik'), 'active' => false],
                    ],
                ],
                [
                    'title' => 'Admin',
                    'items' => [
                        ['label' => 'Catatan Siswa', 'icon' => 'note', 'href' => url('/admin/catatan-siswa'), 'active' => false],
                        ['label' => 'Manajemen Pengguna', 'icon' => 'users', 'href' => url('/admin/manajemen-pengguna'), 'active' => false],
                        ['label' => 'Pengaturan', 'icon' => 'settings', 'href' => url('/admin/pengaturan'), 'active' => false],
                    ],
                ],
            ],
            'guru-mapel' => [
                [
                    'title' => 'Guru Mapel',
                    'items' => [
                        ['label' => 'Beranda', 'icon' => 'home', 'href' => '#beranda', 'active' => true],
                        ['label' => 'Jadwal Mengajar', 'icon' => 'schedule', 'href' => url('/guru-mapel/jadwal-mengajar'), 'active' => false],
                    ],
                ],
                [
                    'title' => 'Absensi Siswa',
                    'items' => [
                        ['label' => 'Tambah Absen', 'icon' => 'attendance', 'href' => url('/guru-mapel/absensi-siswa/tambah'), 'active' => false],
                        ['label' => 'Daftar Absen', 'icon' => 'recap', 'href' => url('/guru-mapel/absensi-siswa/daftar'), 'active' => false],
                        ['label' => 'Rekap Absen', 'icon' => 'chart', 'href' => url('/guru-mapel/rekap-absensi'), 'active' => false],
                    ],
                ],
            ],
            'walikelas' => [
                [
                    'title' => 'Wali Kelas',
                    'items' => [
                        ['label' => 'Beranda', 'icon' => 'home', 'href' => '#beranda', 'active' => true],
                        ['label' => 'Data Siswa', 'icon' => 'students', 'href' => url('/walikelas/data-siswa'), 'active' => false],
                        ['label' => 'Absensi Kelas', 'icon' => 'attendance', 'href' => url('/walikelas/absensi-kelas'), 'active' => false],
                        ['label' => 'Rekap Absensi', 'icon' => 'recap', 'href' => url('/walikelas/rekap-absensi'), 'active' => false],
                        ['label' => 'Catatan Siswa', 'icon' => 'note', 'href' => url('/walikelas/catatan-siswa'), 'active' => false],
                    ],
                ],
            ],
            'orang-tua' => [
                [
                    'title' => 'Orang Tua',
                    'items' => [
                        ['label' => 'Beranda', 'icon' => 'home', 'href' => '#beranda', 'active' => true],
                    ],
                ],
                [
                    'title' => 'Informasi',
                    'items' => [
                        ['label' => 'Kalender Akademik', 'icon' => 'calendar', 'href' => url('/orang-tua/kalender-akademik'), 'active' => false],
                    ],
                ],
            ],
            default => [
                [
                    'title' => 'Siswa',
                    'items' => [
                        ['label' => 'Beranda', 'icon' => 'home', 'href' => '#beranda', 'active' => true],
                        ['label' => 'Jadwal Mata Pelajaran', 'icon' => 'schedule', 'href' => url('/siswa/jadwal-mata-pelajaran'), 'active' => false],
                        ['label' => 'Daftar Hadir', 'icon' => 'attendance', 'href' => url('/siswa/daftar-hadir'), 'active' => false],
                    ],
                ],
                [
                    'title' => 'Informasi',
                    'items' => [
                        ['label' => 'Kalender Akademik', 'icon' => 'calendar', 'href' => url('/siswa/kalender-akademik'), 'active' => false],
                    ],
                ],
            ],
        };

        if ($isTeacher && $isHomeroom) {
            if ($portalKey === 'guru-mapel') {
                $menu[] = [
                    'title' => 'Wali Kelas',
                    'items' => [
                        ['label' => 'Data Siswa', 'icon' => 'students', 'href' => url('/walikelas/data-siswa'), 'active' => false],
                        ['label' => 'Absensi Kelas', 'icon' => 'attendance', 'href' => url('/walikelas/absensi-kelas'), 'active' => false],
                        ['label' => 'Rekap Absensi', 'icon' => 'recap', 'href' => url('/walikelas/rekap-absensi'), 'active' => false],
                        ['label' => 'Catatan Siswa', 'icon' => 'note', 'href' => url('/walikelas/catatan-siswa'), 'active' => false],
                    ],
                ];
            } elseif ($portalKey === 'walikelas') {
                $menu[] = [
                    'title' => 'Guru Mapel',
                    'items' => [
                        ['label' => 'Jadwal Mengajar', 'icon' => 'schedule', 'href' => url('/guru-mapel/jadwal-mengajar'), 'active' => false],
                        ['label' => 'Tambah Absen', 'icon' => 'attendance', 'href' => url('/guru-mapel/absensi-siswa/tambah'), 'active' => false],
                        ['label' => 'Daftar Absen', 'icon' => 'recap', 'href' => url('/guru-mapel/absensi-siswa/daftar'), 'active' => false],
                        ['label' => 'Rekap Absen', 'icon' => 'chart', 'href' => url('/guru-mapel/rekap-absensi'), 'active' => false],
                    ],
                ];
            }
        }

        if ($portalKey === 'guru-mapel' || $portalKey === 'walikelas') {
            $menu[] = [
                'title' => 'Informasi',
                'items' => [
                    ['label' => 'Kalender Akademik', 'icon' => 'calendar', 'href' => url('/' . $portalKey . '/kalender-akademik'), 'active' => false],
                ],
            ];
        }

        $menu[] = [
            'title' => 'Pengaturan',
            'items' => [
                ['label' => 'Profil Saya', 'icon' => 'user', 'href' => url('/profil'), 'active' => false],
            ],
        ];

        return $menu;
    }

    /**
     * @return array<int, array{title:string,items:array<int, array{label:string,icon:string,href:string,active:bool}>}>
     */
    public function menuForPortalPage(string $portalKey, string $activeLabel): array
    {
        $dashboardUrl = url(AuthService::portalMap()[$portalKey]['dashboard']);

        return collect($this->menuFor($portalKey))
            ->map(function (array $section) use ($dashboardUrl, $activeLabel): array {
                $section['items'] = collect($section['items'])
                    ->map(function (array $item) use ($dashboardUrl, $activeLabel): array {
                        if (str_starts_with($item['href'], '#')) {
                            $item['href'] = $dashboardUrl . $item['href'];
                        }

                        $item['active'] = $item['label'] === $activeLabel;

                        return $item;
                    })
                    ->all();

                return $section;
            })
            ->all();
    }

    /**
     * @return array<int, array{title:string,items:array<int, array{label:string,icon:string,href:string,active:bool}>}>
     */
    protected function adminPageMenu(string $activeItem): array
    {
        return [
            [
                'title' => 'Menu',
                'items' => [
                    ['label' => 'Beranda', 'icon' => 'home', 'href' => url('/admin/dashboard'), 'active' => $activeItem === 'beranda'],
                    ['label' => 'Data Siswa', 'icon' => 'students', 'href' => url('/admin/data-siswa'), 'active' => $activeItem === 'data-siswa'],
                    ['label' => 'Data Guru', 'icon' => 'teacher', 'href' => url('/admin/data-guru'), 'active' => $activeItem === 'data-guru'],
                    ['label' => 'Data Kelas', 'icon' => 'class', 'href' => url('/admin/data-kelas'), 'active' => $activeItem === 'data-kelas'],
                    ['label' => 'Mata Pelajaran', 'icon' => 'subject', 'href' => url('/admin/mata-pelajaran'), 'active' => $activeItem === 'mata-pelajaran'],
                    ['label' => 'Kalender Akademik', 'icon' => 'calendar', 'href' => url('/admin/kalender-akademik'), 'active' => $activeItem === 'kalender-akademik'],
                    ['label' => 'Jadwal Pelajaran', 'icon' => 'schedule', 'href' => url('/admin/schedule/generate'), 'active' => $activeItem === 'jadwal-pelajaran'],
                    ['label' => 'Rekap Kehadiran', 'icon' => 'recap', 'href' => url('/admin/rekap-kehadiran'), 'active' => $activeItem === 'rekap-kehadiran'],
                    ['label' => 'Laporan Statistik', 'icon' => 'chart', 'href' => url('/admin/laporan-statistik'), 'active' => $activeItem === 'laporan-tren'],
                ],
            ],
            [
                'title' => 'Lainnya',
                'items' => [
                    ['label' => 'Manajemen Pengguna', 'icon' => 'users', 'href' => url('/admin/manajemen-pengguna'), 'active' => $activeItem === 'manajemen-pengguna'],
                    ['label' => 'Pengaturan', 'icon' => 'settings', 'href' => url('/admin/pengaturan'), 'active' => $activeItem === 'pengaturan'],
                    ['label' => 'Catatan Siswa', 'icon' => 'note', 'href' => url('/admin/catatan-siswa'), 'active' => $activeItem === 'catatan-siswa'],
                    ['label' => 'Pengumuman', 'icon' => 'announcement', 'href' => url('/admin/pengumuman'), 'active' => $activeItem === 'pengumuman'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function profileFor(User $user, string $portalKey): array
    {
        $profile = $user->teacherProfile ?? $user->studentProfile;
        $nameParts = preg_split('/\s+/', trim($user->name)) ?: [];
        $initials = collect($nameParts)
            ->filter()
            ->map(static fn(string $part): string => strtoupper(substr($part, 0, 1)))
            ->take(2)
            ->implode('');

        return [
            'name' => $user->name,
            'role' => AuthService::portalMap()[$portalKey]['label'],
            'email' => $user->email,
            'phone' => $profile?->phone ?? 'Belum diisi',
            'address' => $profile?->address ?? 'Alamat belum dilengkapi',
            'photo_url' => $profile?->photo_url ?? null,
            'initials' => $initials !== '' ? $initials : 'US',
        ];
    }

    /**
     * @return array{month_label:string,days:array<int,string>,weeks:array<int, array<int, array{day:int,month:int,is_current_month:bool,is_today:bool}>>}
     */
    protected function calendarData(): array
    {
        $today = CarbonImmutable::now();
        $startOfMonth = $today->startOfMonth();
        $cursor = $startOfMonth->startOfWeek(CarbonImmutable::MONDAY);
        $endCursor = $today->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $weeks = [];

        while ($cursor <= $endCursor) {
            $week = [];

            for ($day = 0; $day < 7; $day++) {
                $week[] = [
                    'day' => $cursor->day,
                    'month' => $cursor->month,
                    'is_current_month' => $cursor->month === $today->month,
                    'is_today' => $cursor->isSameDay($today),
                ];

                $cursor = $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'month_label' => ($monthNames[$today->month] ?? $today->format('F')) . ' ' . $today->year,
            'days' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
            'weeks' => $weeks,
        ];
    }

    protected function formattedTimestamp(): string
    {
        $now = CarbonImmutable::now();
        $monthNames = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        return sprintf(
            '%s %s %s, %s',
            $now->day,
            $monthNames[$now->month] ?? $now->format('M'),
            $now->year,
            $now->format('H.i'),
        );
    }

    protected function weekLabel(CarbonImmutable $startOfWeek): string
    {
        $endOfWeek = $startOfWeek->addDays(6);

        if ($startOfWeek->month === $endOfWeek->month) {
            return sprintf(
                '%s-%s %s',
                $startOfWeek->day,
                $endOfWeek->day,
                $this->shortMonthName($endOfWeek->month),
            );
        }

        return sprintf(
            '%s %s-%s %s',
            $startOfWeek->day,
            $this->shortMonthName($startOfWeek->month),
            $endOfWeek->day,
            $this->shortMonthName($endOfWeek->month),
        );
    }

    protected function monthLabel(CarbonImmutable $date): string
    {
        return $this->shortMonthName($date->month) . ' ' . $date->year;
    }

    protected function todayDateLabel(): string
    {
        $today = CarbonImmutable::now();
        $dayNames = config('schedule.day_names', []);

        return sprintf(
            '%s, %s %s %s',
            $dayNames[$this->scheduleDayFromIso($today->dayOfWeekIso)] ?? $today->format('l'),
            $today->day,
            $this->shortMonthName($today->month),
            $today->year,
        );
    }

    protected function shortMonthName(int $month): string
    {
        $monthNames = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        return $monthNames[$month] ?? '';
    }

    protected function schoolName(): string
    {
        return $this->appSettingService->value('school_name', config('app.name', 'Sarunis')) ?: config('app.name', 'Sarunis');
    }

    protected function activeAcademicYear(): string
    {
        return $this->appSettingService->value('academic_year', '2025/2026') ?: '2025/2026';
    }

    protected function activeSemester(): string
    {
        return $this->appSettingService->value('active_semester', 'ganjil') ?: 'ganjil';
    }

    /**
     * @return array<int, array{title:string,category:string,period:string,is_holiday:bool}>
     */
    protected function dashboardAcademicCalendarEvents(): array
    {
        return $this->academicCalendarService
            ->upcoming($this->activeAcademicYear(), $this->activeSemester())
            ->map(fn($event): array => [
                'title' => $event->title,
                'category' => $event->category,
                'period' => $event->start_date?->format('d M') . ' - ' . $event->end_date?->format('d M Y'),
                'is_holiday' => $event->is_holiday,
            ])
            ->all();
    }

    /**
     * @param Collection<int, TeachingAssignment> $schedules
     * @return Collection<int, TeachingAssignment>
     */
    protected function todaySchedules(Collection $schedules): Collection
    {
        return $this->schedulesForDate($schedules, now()->toDateString());
    }

    /**
     * @param Collection<int, TeachingAssignment> $schedules
     * @return Collection<int, TeachingAssignment>
     */
    protected function schedulesForDate(Collection $schedules, string $date): Collection
    {
        $scheduleDay = $this->scheduleDayFromIso(CarbonImmutable::parse($date)->dayOfWeekIso);

        return $schedules
            ->filter(static fn(TeachingAssignment $assignment): bool => (int) $assignment->day_of_week === $scheduleDay)
            ->sortBy([['start_time', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * @param Collection<int, TeachingAssignment> $schedules
     * @return array<int, array<string, mixed>>
     */
    protected function scheduleRowsForDate(Collection $schedules, string $date): array
    {
        $dayNames = config('schedule.day_names', []);
        $scheduleDay = $this->scheduleDayFromIso(CarbonImmutable::parse($date)->dayOfWeekIso);

        return $this->schedulesForDate($schedules, $date)
            ->map(fn(TeachingAssignment $assignment, int $index): array => [
                'id' => $assignment->id,
                'school_class_id' => $assignment->school_class_id,
                'subject_id' => $assignment->subject_id,
                'teacher_id' => $assignment->teacher_id,
                'lesson_period' => $index + 1,
                'day_of_week' => $assignment->day_of_week,
                'day_name' => $dayNames[$scheduleDay] ?? 'Hari ' . ($scheduleDay + 1),
                'start_time' => substr((string) $assignment->start_time, 0, 5),
                'end_time' => substr((string) $assignment->end_time, 0, 5),
                'time' => $this->timeRange($assignment->start_time, $assignment->end_time),
                'subject' => $assignment->subject?->name ?? '-',
                'class_name' => $assignment->schoolClass?->name ?? '-',
                'room' => $assignment->room ?? '-',
                'students_count' => (int) ($assignment->schoolClass?->students_count ?? $assignment->schoolClass?->students()->count() ?? 0),
                'status' => $this->scheduleStatusForDate($assignment, $date),
                'meeting_label' => $this->getMeetingLabelForSchedule($assignment->id, $date),
                'is_filled' => \App\Models\SubjectAttendance::where('teaching_assignment_id', $assignment->id)
                    ->where('attendance_date', $date)
                    ->exists(),
            ])
            ->values()
            ->all();
    }

    protected function getMeetingLabelForSchedule(int $teachingAssignmentId, string $date): string
    {
        $assignment = TeachingAssignment::query()->find($teachingAssignmentId);

        if (!$assignment) {
            return '-';
        }

        $academicYear = $this->activeAcademicYear();
        $semester = $this->activeSemester();
        $startDateVal = $this->appSettingService->value('school_start_date', '2025-07-14') ?: '2025-07-14';
        $endDateVal = $this->appSettingService->value('school_end_date', '2026-06-30') ?: '2026-06-30';
        $saturdayEnabled = filter_var(
            $this->appSettingService->value('school_saturday_enabled', '1') ?? '1',
            FILTER_VALIDATE_BOOLEAN
        );

        $start = CarbonImmutable::parse($startDateVal)->startOfDay();
        $end = CarbonImmutable::parse($endDateVal)->startOfDay();
        $targetDate = CarbonImmutable::parse($date)->startOfDay();

        if ($end->lt($start) || $targetDate->lt($start) || $targetDate->gt($end)) {
            return '-';
        }

        $examEvents = AcademicCalendar::query()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->where('type', 'ujian')
            ->orderBy('start_date')
            ->get();

        $examLabelForDate = function (string $dateString) use ($examEvents): ?string {
            foreach ($examEvents as $event) {
                if ($event->start_date->lte($dateString) && $event->end_date->gte($dateString)) {
                    return $event->title;
                }
            }
            return null;
        };

        $targetDateString = $targetDate->toDateString();
        $targetExamLabel = $examLabelForDate($targetDateString);

        if ($targetExamLabel) {
            return $targetExamLabel;
        }

        $meetingCounter = 0;
        $current = $start;

        while ($current->lte($targetDate)) {
            $isSunday = $current->dayOfWeekIso === 7;
            $isSaturday = $current->dayOfWeekIso === 6;
            $scheduleDay = $this->scheduleDayFromIso($current->dayOfWeekIso);
            $dateString = $current->toDateString();

            if (
                !$isSunday
                && (!$isSaturday || $saturdayEnabled)
                && (int) $assignment->day_of_week === $scheduleDay
                && !$examLabelForDate($dateString)
            ) {
                $meetingCounter++;

                if ($dateString === $targetDateString) {
                    return 'Pertemuan ' . $meetingCounter;
                }
            }

            $current = $current->addDay();
        }

        return '-';
    }

    /**
     * @param Collection<int, SubjectAttendance|ClassAttendance> $records
     */
    protected function latestAttendanceDate(Collection $records): ?string
    {
        /** @var string|null $latest */
        $latest = $records->max(static fn($record): ?string => $record->attendance_date?->toDateString());

        return $latest;
    }

    /**
     * @param Collection<int, SubjectAttendance|ClassAttendance> $records
     * @return Collection<int, SubjectAttendance|ClassAttendance>
     */
    protected function recordsForDate(Collection $records, ?string $date): Collection
    {
        if ($date === null) {
            return collect();
        }

        return $records
            ->filter(static fn($record): bool => $record->attendance_date?->toDateString() === $date)
            ->values();
    }

    /**
     * @param Collection<int, SubjectAttendance> $attendances
     * @return array<int, array{label:string,value:int,color:string}>
     */
    protected function subjectAttendanceSummary(Collection $attendances, int $assignmentId, ?string $date): array
    {
        $records = $this->recordsForDate($attendances, $date)
            ->where('teaching_assignment_id', $assignmentId);

        return [
            ['label' => 'Hadir', 'value' => $records->where('status', AttendanceStatus::HADIR->value)->count(), 'color' => 'success'],
            ['label' => 'Izin', 'value' => $records->where('status', AttendanceStatus::IZIN->value)->count(), 'color' => 'warning'],
            ['label' => 'Sakit', 'value' => $records->where('status', AttendanceStatus::SAKIT->value)->count(), 'color' => 'info'],
            ['label' => 'Alpha', 'value' => $records->where('status', AttendanceStatus::ALPHA->value)->count(), 'color' => 'danger'],
        ];
    }

    protected function timeRange(?string $startTime, ?string $endTime): string
    {
        return sprintf('%s - %s', substr((string) $startTime, 0, 5), substr((string) $endTime, 0, 5));
    }

    /**
     * @return array{label:string,tone:string}
     */
    protected function scheduleStatus(TeachingAssignment $assignment): array
    {
        return $this->scheduleStatusForDate($assignment, now()->toDateString());
    }

    /**
     * @return array{label:string,tone:string}
     */
    protected function scheduleStatusForDate(TeachingAssignment $assignment, string $date): array
    {
        $now = CarbonImmutable::now();
        $targetDate = CarbonImmutable::parse($date);
        $todayNumber = $this->scheduleDayFromIso($targetDate->dayOfWeekIso);
        $start = substr((string) $assignment->start_time, 0, 5);
        $end = substr((string) $assignment->end_time, 0, 5);
        $currentTime = $now->format('H:i');

        if ((int) $assignment->day_of_week !== $todayNumber) {
            return ['label' => 'Terjadwal', 'tone' => 'neutral'];
        }

        if (! $targetDate->isSameDay($now)) {
            return ['label' => 'Terjadwal', 'tone' => 'neutral'];
        }

        if ($currentTime < $start) {
            return ['label' => 'Belum', 'tone' => 'warning'];
        }

        if ($currentTime > $end) {
            return ['label' => 'Selesai', 'tone' => 'success'];
        }

        return ['label' => 'Saat Ini', 'tone' => 'primary'];
    }

    protected function currentScheduleDay(): int
    {
        return $this->scheduleDayFromIso(CarbonImmutable::now()->dayOfWeekIso);
    }

    protected function scheduleDayFromIso(int $dayOfWeekIso): int
    {
        return $dayOfWeekIso - 1;
    }

    protected function renderViewOrJson(Request $request, string $viewName, array $data): JsonResponse|View|Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        if (view()->exists($viewName)) {
            return view($viewName, $data);
        }

        return view('app', $data);
    }
}


