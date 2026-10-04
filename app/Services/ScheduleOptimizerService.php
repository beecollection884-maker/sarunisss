<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service untuk mengoptimalkan dan memberikan insights tentang jadwal yang sudah ada
 */
class ScheduleOptimizerService
{
    /**
     */
    public function detectTeacherConflicts(string $academicYear): array
    {
        $conflicts = [];

        $teachers = Teacher::query()
            ->with(['teachingAssignments' => fn($q) => $q->where('academic_year', $academicYear)])
            ->get();

        foreach ($teachers as $teacher) {
            $assignments = $teacher->teachingAssignments;

            for ($i = 0; $i < $assignments->count(); $i++) {
                for ($j = $i + 1; $j < $assignments->count(); $j++) {
                    $a1 = $assignments[$i];
                    $a2 = $assignments[$j];

                    // Cek jika di hari dan waktu yang sama
                    if ($a1->day_of_week === $a2->day_of_week) {
                        if ($this->hasTimeOverlap($a1->start_time, $a1->end_time, $a2->start_time, $a2->end_time)) {
                            $conflicts[] = [
                                'teacher_id' => $teacher->id,
                                'teacher_name' => $teacher->name,
                                'assignment1' => [
                                    'subject' => $a1->subject->name,
                                    'class' => $a1->schoolClass->name,
                                    'day' => $this->getDayName($a1->day_of_week),
                                    'time' => "{$a1->start_time} - {$a1->end_time}",
                                ],
                                'assignment2' => [
                                    'subject' => $a2->subject->name,
                                    'class' => $a2->schoolClass->name,
                                    'day' => $this->getDayName($a2->day_of_week),
                                    'time' => "{$a2->start_time} - {$a2->end_time}",
                                ],
                                'type' => 'time_overlap'
                            ];
                        }
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Deteksi konflik ruangan (multiple classes in same room at same time)
     */
    public function detectRoomConflicts(string $academicYear): array
    {
        $conflicts = [];

        $assignments = TeachingAssignment::query()
            ->with(['schoolClass', 'subject', 'teacher'])
            ->where('academic_year', $academicYear)
            ->whereNotNull('room')
            ->get()
            ->groupBy('room');

        foreach ($assignments as $room => $roomAssignments) {
            for ($i = 0; $i < $roomAssignments->count(); $i++) {
                for ($j = $i + 1; $j < $roomAssignments->count(); $j++) {
                    $a1 = $roomAssignments[$i];
                    $a2 = $roomAssignments[$j];

                    if ($a1->day_of_week === $a2->day_of_week) {
                        if ($this->hasTimeOverlap($a1->start_time, $a1->end_time, $a2->start_time, $a2->end_time)) {
                            $conflicts[] = [
                                'room' => $room,
                                'assignment1' => [
                                    'class' => $a1->schoolClass->name,
                                    'subject' => $a1->subject->name,
                                    'teacher' => $a1->teacher->name,
                                    'day' => $this->getDayName($a1->day_of_week),
                                    'time' => "{$a1->start_time} - {$a1->end_time}",
                                ],
                                'assignment2' => [
                                    'class' => $a2->schoolClass->name,
                                    'subject' => $a2->subject->name,
                                    'teacher' => $a2->teacher->name,
                                    'day' => $this->getDayName($a2->day_of_week),
                                    'time' => "{$a2->start_time} - {$a2->end_time}",
                                ],
                                'type' => 'room_conflict'
                            ];
                        }
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Analisis beban kerja guru
     */
    public function analyzeTeacherWorkload(string $academicYear): array
    {
        $workloads = [];

        $teachers = Teacher::query()
            ->with(['teachingAssignments' => fn($q) => $q->where('academic_year', $academicYear)])
            ->where('is_subject_teacher', true)
            ->get();

        foreach ($teachers as $teacher) {
            $assignments = $teacher->teachingAssignments;

            $totalHours = 0;
            $subjectsCount = 0;
            $classesCount = 0;
            $sessionsPerWeek = $assignments->count();

            // Hitung total jam per minggu
            foreach ($assignments as $assignment) {
                $start = $this->timeToMinutes($assignment->start_time);
                $end = $this->timeToMinutes($assignment->end_time);
                $hours = ($end - $start) / 60;
                $totalHours += $hours;
            }

            $subjectsCount = $assignments->pluck('subject_id')->unique()->count();
            $classesCount = $assignments->pluck('school_class_id')->unique()->count();

            $workloads[] = [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->name,
                'total_hours_per_week' => round($totalHours, 2),
                'sessions_per_week' => $sessionsPerWeek,
                'unique_subjects' => $subjectsCount,
                'unique_classes' => $classesCount,
                'workload_status' => $this->getWorkloadStatus($totalHours),
                'is_overloaded' => $totalHours > config('schedule.max_teacher_hours_per_week'),
            ];
        }

        return collect($workloads)->sortByDesc('total_hours_per_week')->toArray();
    }

    /**
     * Analisis distribusi jadwal per hari
     */
    public function analyzeDailyDistribution(string $academicYear, int $classId): array
    {
        $distribution = [];
        $days = config('schedule.day_names');
        $operationalDays = config('schedule.operational_days');

        foreach ($operationalDays as $day) {
            $assignments = TeachingAssignment::query()
                ->with(['subject', 'teacher'])
                ->where('academic_year', $academicYear)
                ->where('school_class_id', $classId)
                ->where('day_of_week', $day)
                ->get();

            $dayName = $days[$day] ?? 'Hari ' . $day;
            $distribution[$dayName] = [
                'day_index' => $day,
                'total_sessions' => $assignments->count(),
                'total_hours' => round($this->calculateTotalHours($assignments), 2),
                'subjects' => $assignments->pluck('subject.name')->unique()->toArray(),
                'sessions' => $assignments->map(fn($a) => [
                    'subject' => $a->subject->name,
                    'teacher' => $a->teacher->name,
                    'time' => "{$a->start_time} - {$a->end_time}",
                ])->toArray(),
            ];
        }

        return $distribution;
    }

    /**
     * Dapatkan rekomendasi perbaikan jadwal
     */
    public function getRecommendations(string $academicYear): array
    {
        $recommendations = [];

        // 1. Cek guru yang overloaded
        $workloads = $this->analyzeTeacherWorkload($academicYear);
        $overloadedTeachers = array_filter($workloads, fn($w) => $w['is_overloaded']);

        if (!empty($overloadedTeachers)) {
            $recommendations[] = [
                'type' => 'overloaded_teachers',
                'severity' => 'high',
                'title' => 'Guru dengan beban kerja tinggi',
                'description' => 'Beberapa guru memiliki lebih dari 25 jam mengajar per minggu',
                'affected_count' => count($overloadedTeachers),
                'details' => array_map(fn($w) => [
                    'teacher' => $w['teacher_name'],
                    'hours' => $w['total_hours_per_week'],
                    'sessions' => $w['sessions_per_week'],
                ], $overloadedTeachers),
                'action' => 'Pertimbangkan untuk mengurangi beban mengajar atau menambah guru lain',
            ];
        }

        // 2. Cek konflik jadwal
        $conflicts = $this->detectTeacherConflicts($academicYear);
        if (!empty($conflicts)) {
            $recommendations[] = [
                'type' => 'scheduling_conflicts',
                'severity' => 'critical',
                'title' => 'Konflik jadwal ditemukan',
                'description' => 'Ada beberapa guru dengan jadwal bertabrakan',
                'affected_count' => count($conflicts),
                'action' => 'Sesuaikan jadwal untuk menghindari tabrakan',
            ];
        }

        // 3. Cek kelas dengan jadwal tidak seimbang
        $classes = SchoolClass::where('academic_year', $academicYear)->get();
        $imbalancedClasses = [];

        foreach ($classes as $class) {
            $distribution = $this->analyzeDailyDistribution($academicYear, $class->id);
            $hourPerDay = array_map(fn($d) => $d['total_hours'], $distribution);
            $avgHours = array_sum($hourPerDay) / count($hourPerDay);
            $maxDeviation = max($hourPerDay) - min($hourPerDay);

            // Jika deviasi lebih dari 3 jam, dianggap tidak seimbang
            if ($maxDeviation > 3) {
                $imbalancedClasses[] = [
                    'class' => $class->name,
                    'avg_hours' => round($avgHours, 2),
                    'max_deviation' => round($maxDeviation, 2),
                ];
            }
        }

        if (!empty($imbalancedClasses)) {
            $recommendations[] = [
                'type' => 'imbalanced_schedule',
                'severity' => 'medium',
                'title' => 'Distribusi jadwal tidak seimbang',
                'description' => 'Beberapa kelas memiliki beban jam yang tidak merata per hari',
                'affected_count' => count($imbalancedClasses),
                'details' => $imbalancedClasses,
                'action' => 'Pindahkan beberapa jadwal untuk memperatakan beban per hari',
            ];
        }

        // 4. Cek guru tanpa jadwal
        $unscheduledTeachers = Teacher::query()
            ->where('is_subject_teacher', true)
            ->whereDoesntHave('teachingAssignments', fn($q) => $q->where('academic_year', $academicYear))
            ->get();

        if ($unscheduledTeachers->isNotEmpty()) {
            $recommendations[] = [
                'type' => 'unscheduled_teachers',
                'severity' => 'high',
                'title' => 'Guru tanpa jadwal',
                'description' => 'Beberapa guru tidak memiliki jadwal mengajar',
                'affected_count' => $unscheduledTeachers->count(),
                'details' => $unscheduledTeachers->pluck('name')->toArray(),
                'action' => 'Tambahkan jadwal mengajar untuk guru-guru ini',
            ];
        }

        return $recommendations;
    }

    /**
     * Generate laporan jadwal
     */
    public function generateScheduleReport(string $academicYear): array
    {
        return [
            'academic_year' => $academicYear,
            'generated_at' => Carbon::now(),
            'summary' => [
                'total_teachers' => Teacher::where('is_subject_teacher', true)->count(),
                'total_classes' => SchoolClass::where('academic_year', $academicYear)->count(),
                'total_assignments' => TeachingAssignment::where('academic_year', $academicYear)->count(),
                'total_subjects' => \App\Models\Subject::count(),
            ],
            'conflicts' => [
                'teacher_conflicts' => count($this->detectTeacherConflicts($academicYear)),
                'room_conflicts' => count($this->detectRoomConflicts($academicYear)),
            ],
            'workload_analysis' => $this->analyzeTeacherWorkload($academicYear),
            'recommendations' => $this->getRecommendations($academicYear),
        ];
    }

    // ===== Helper Methods =====

    private function hasTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        $s1 = $this->timeToMinutes($start1);
        $e1 = $this->timeToMinutes($end1);
        $s2 = $this->timeToMinutes($start2);
        $e2 = $this->timeToMinutes($end2);

        return $s1 < $e2 && $e1 > $s2;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return (int)$hours * 60 + (int)$minutes;
    }

    private function calculateTotalHours(Collection $assignments): float
    {
        $total = 0;

        foreach ($assignments as $assignment) {
            $start = $this->timeToMinutes($assignment->start_time);
            $end = $this->timeToMinutes($assignment->end_time);
            $total += ($end - $start) / 60;
        }

        return $total;
    }

    private function getWorkloadStatus(float $hours): string
    {
        $maxHours = config('schedule.max_teacher_hours_per_week');
        $lightThreshold = $maxHours * 0.5;      // 50%
        $normalThreshold = $maxHours * 0.7;     // 70%
        $highThreshold = $maxHours;             // 100%

        if ($hours < $lightThreshold) {
            return 'Rendah';
        } elseif ($hours < $normalThreshold) {
            return 'Normal';
        } elseif ($hours < $highThreshold) {
            return 'Tinggi';
        } else {
            return 'Sangat Tinggi';
        }
    }

    private function getDayName(int $dayOfWeek): string
    {
        $days = config('schedule.day_names');
        return $days[$dayOfWeek] ?? 'Tidak Diketahui';
    }
}
