<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleGeneratorService
{
    /**
     * Generate jadwal secara otomatis berdasarkan data yang ada
     */
    public function generateSchedule(string $academicYear, ?int $schoolClassId = null): array
    {
        return DB::transaction(function () use ($academicYear, $schoolClassId) {
            $stats = [
                'total_classes' => 0,
                'total_assignments' => 0,
                'conflicts_detected' => 0,
                'successful_slots' => 0,
                'failed_slots' => 0,
                'details' => []
            ];

            // Ambil kelas yang akan dijadwalkan
            $classes = $this->getClassesToSchedule($academicYear, $schoolClassId);

            foreach ($classes as $class) {
                $classStats = $this->scheduleClass($class, $academicYear);
                $stats['total_classes']++;
                $stats['total_assignments'] += $classStats['total_assignments'];
                $stats['conflicts_detected'] += $classStats['conflicts_detected'];
                $stats['successful_slots'] += $classStats['successful_slots'];
                $stats['failed_slots'] += $classStats['failed_slots'];
                $stats['details'][] = $classStats;
            }

            return $stats;
        });
    }

    /**
     * Generate jadwal untuk satu kelas
     */
    private function scheduleClass(SchoolClass $class, string $academicYear): array
    {
        $classStats = [
            'class_id' => $class->id,
            'class_name' => $class->name,
            'total_assignments' => 0,
            'successful_slots' => 0,
            'failed_slots' => 0,
            'conflicts_detected' => 0,
            'scheduled_subjects' => []
        ];

        // Ambil semua mapel yang harus diajarkan di kelas ini, prioritaskan yang memiliki jadwal tetap
        $subjects = $class->subjects()->get()->sortByDesc(function (Subject $subject) use ($class) {
            $hasFixedSchedule = ($subject->day_of_week !== null && 
                                 $subject->start_time !== null && 
                                 $subject->end_time !== null && 
                                 ($subject->school_class_id === null || $subject->school_class_id === $class->id));
            return $hasFixedSchedule ? 1 : 0;
        });

        if ($subjects->isEmpty()) {
            return $classStats;
        }

        // Inisialisasi time slots untuk setiap hari
        $availableSlots = $this->initializeTimeSlots();
        $teacherSchedules = []; // Track jadwal guru untuk deteksi konflik

        // Pre-load jadwal yang sudah ada untuk deteksi konflik
        $existingAssignments = TeachingAssignment::query()
            ->where('academic_year', $academicYear)
            ->get();

        foreach ($existingAssignments as $existing) {
            // Track guru schedule
            if (!isset($teacherSchedules[$existing->teacher_id])) {
                $teacherSchedules[$existing->teacher_id] = [];
            }
            $teacherSchedules[$existing->teacher_id][] = [
                'day' => $existing->day_of_week,
                'start' => $existing->start_time,
                'end' => $existing->end_time,
                'class_id' => $existing->school_class_id
            ];

            // Mark slot as used if it belongs to this class
            if ($existing->school_class_id === $class->id) {
                $availableSlots = $this->markSlotAsUsed(
                    $availableSlots,
                    $existing->day_of_week,
                    $existing->start_time,
                    $existing->end_time
                );
            }
        }

        foreach ($subjects as $subject) {
            $classStats['total_assignments'] += $subject->lesson_hours;

            // Cek berapa assignment yang sudah ada untuk mapel ini di kelas ini
            $existingCount = TeachingAssignment::query()
                ->where('subject_id', $subject->id)
                ->where('school_class_id', $class->id)
                ->where('academic_year', $academicYear)
                ->count();

            // Jika sudah fully-scheduled, skip
            if ($existingCount >= $subject->lesson_hours) {
                $classStats['successful_slots'] += $existingCount;
                $classStats['scheduled_subjects'][] = [
                    'subject' => $subject->name,
                    'status' => 'already_scheduled'
                ];
                continue;
            }

            // Track partially existing slots
            if ($existingCount > 0) {
                $classStats['successful_slots'] += $existingCount;
            }

            // Cari guru yang qualified untuk mapel ini
            $teachers = $subject->teachers()->get();

            if ($teachers->isEmpty()) {
                $classStats['failed_slots']++;
                $classStats['scheduled_subjects'][] = [
                    'subject' => $subject->name,
                    'status' => 'no_teacher_available'
                ];
                continue;
            }

            // Hitung jumlah slot yang masih perlu dijadwalkan
            // lesson_hours = jumlah jam pelajaran per minggu, setiap JP = 1 slot
            $requiredSlots = $subject->lesson_hours - $existingCount;

            // Coba assign jadwal untuk mapel ini
            $slotsAssigned = 0;
            $assignedTeacher = null;

            // Check if the subject has a fixed schedule
            $hasFixedSchedule = ($subject->day_of_week !== null && $subject->start_time !== null && $subject->end_time !== null && ($subject->school_class_id === null || $subject->school_class_id === $class->id));

            if ($hasFixedSchedule) {
                $fixedDay = (int) $subject->day_of_week;
                $fixedStart = date('H:i', strtotime($subject->start_time));
                $fixedEnd = date('H:i', strtotime($subject->end_time));

                foreach ($teachers as $teacher) {
                    $classBusy = $this->isClassBusy($availableSlots, $fixedDay, $fixedStart, $fixedEnd);
                    $teacherBusy = $this->isTeacherBusy($teacher->id, $fixedDay, $fixedStart, $fixedEnd, $teacherSchedules);
                    
                    // Check if class is busy or teacher is busy
                    if ($classBusy || $teacherBusy) {
                        $classStats['conflicts_detected']++;
                        continue;
                    }

                    // Schedule it!
                    try {
                        $this->createTeachingAssignment(
                            $teacher->id,
                            $subject->id,
                            $class->id,
                            $academicYear,
                            $fixedDay,
                            $fixedStart,
                            $fixedEnd
                        );

                        // Mark time range as used
                        $availableSlots = $this->markTimeRangeAsUsed($availableSlots, $fixedDay, $fixedStart, $fixedEnd);

                        // Track teacher schedule
                        if (!isset($teacherSchedules[$teacher->id])) {
                            $teacherSchedules[$teacher->id] = [];
                        }
                        $teacherSchedules[$teacher->id][] = [
                            'day' => $fixedDay,
                            'start' => $fixedStart,
                            'end' => $fixedEnd,
                            'class_id' => $class->id
                        ];

                        $slotsAssigned = $requiredSlots; // Fully scheduled
                        $assignedTeacher = $teacher;
                        $classStats['successful_slots'] += $requiredSlots;

                        $classStats['scheduled_subjects'][] = [
                            'subject' => $subject->name,
                            'teacher' => $teacher->name,
                            'day' => $this->getDayName($fixedDay),
                            'time' => "{$fixedStart} - {$fixedEnd}",
                            'status' => 'success'
                        ];
                        break; // exit teacher loop
                    } catch (\Exception $e) {
                        $classStats['conflicts_detected']++;
                    }
                }
            } else {
                foreach ($teachers as $teacher) {
                    if ($slotsAssigned >= $requiredSlots) break;

                    for ($slot = $slotsAssigned; $slot < $requiredSlots; $slot++) {
                        // Cari slot kosong berikutnya
                        $slot_info = $this->findAvailableSlot(
                            $availableSlots,
                            $teacher,
                            $teacherSchedules,
                            $class->id
                        );

                        if ($slot_info === null) {
                            // Guru ini tidak punya slot kosong lagi, coba guru berikutnya
                            break;
                        }

                        // Buat teaching assignment
                        try {
                            $this->createTeachingAssignment(
                                $teacher->id,
                                $subject->id,
                                $class->id,
                                $academicYear,
                                $slot_info['day'],
                                $slot_info['start_time'],
                                $slot_info['end_time']
                            );

                            // Update available slots
                            $availableSlots = $this->markSlotAsUsed(
                                $availableSlots,
                                $slot_info['day'],
                                $slot_info['start_time'],
                                $slot_info['end_time']
                            );

                            // Track guru schedule
                            if (!isset($teacherSchedules[$teacher->id])) {
                                $teacherSchedules[$teacher->id] = [];
                            }
                            $teacherSchedules[$teacher->id][] = [
                                'day' => $slot_info['day'],
                                'start' => $slot_info['start_time'],
                                'end' => $slot_info['end_time'],
                                'class_id' => $class->id
                            ];

                            $slotsAssigned++;
                            $assignedTeacher = $teacher;
                            $classStats['successful_slots']++;

                            $classStats['scheduled_subjects'][] = [
                                'subject' => $subject->name,
                                'teacher' => $teacher->name,
                                'day' => $this->getDayName($slot_info['day']),
                                'time' => "{$slot_info['start_time']} - {$slot_info['end_time']}",
                                'status' => 'success'
                            ];
                        } catch (\Exception $e) {
                            $classStats['conflicts_detected']++;
                            // Lanjut ke guru berikutnya
                            break;
                        }
                    }
                }
            }

            if ($slotsAssigned === 0) {
                $classStats['failed_slots']++;
                $classStats['scheduled_subjects'][] = [
                    'subject' => $subject->name,
                    'status' => 'failed_to_schedule'
                ];
            } elseif ($slotsAssigned < $requiredSlots) {
                $classStats['failed_slots']++;
                $classStats['scheduled_subjects'][] = [
                    'subject' => $subject->name,
                    'status' => "partial ({$slotsAssigned}/{$requiredSlots} JP)"
                ];
            }
        }

        return $classStats;
    }

    /**
     * Inisialisasi time slots untuk semua hari
     */
    private function initializeTimeSlots(): array
    {
        $slots = [];
        $operationalDays = config('schedule.operational_days');
        $lessonDuration = config('schedule.lesson_duration');
        $breakDuration = config('schedule.break_duration');
        $schoolStartHour = config('schedule.school_start_hour');
        $schoolEndHour = config('schedule.school_end_hour');

        foreach ($operationalDays as $day) {
            $slots[$day] = [];

            $currentTime = $this->timeToMinutes($schoolStartHour . ':00');
            $endTime = $this->timeToMinutes($schoolEndHour . ':00');

            while (($currentTime + $lessonDuration) <= $endTime) {
                $start = $this->minutesToTime($currentTime);
                $end = $this->minutesToTime($currentTime + $lessonDuration);

                $slots[$day][] = [
                    'start_time' => $start,
                    'end_time' => $end,
                    'is_available' => true
                ];

                // Tambah break setelah beberapa pelajaran
                $currentTime += $lessonDuration + $breakDuration;
            }
        }

        return $slots;
    }

    /**
     * Cari slot waktu yang tersedia
     */
    private function findAvailableSlot(
        array $slots,
        Teacher $teacher,
        array $teacherSchedules,
        int $classId
    ): ?array {
        $operationalDays = config('schedule.operational_days');
        foreach ($operationalDays as $day) {
            if (!isset($slots[$day])) {
                continue;
            }

            foreach ($slots[$day] as $slot) {
                if (!$slot['is_available']) {
                    continue;
                }

                // Cek apakah guru sudah punya jadwal di jam yang sama
                if ($this->isTeacherBusy($teacher->id, $day, $slot['start_time'], $slot['end_time'], $teacherSchedules)) {
                    continue;
                }

                return [
                    'day' => $day,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time']
                ];
            }
        }

        return null;
    }

    /**
     * Cek apakah guru sibuk di waktu tersebut
     */
    private function isTeacherBusy(int $teacherId, int $day, string $startTime, string $endTime, array $teacherSchedules): bool
    {
        if (!isset($teacherSchedules[$teacherId])) {
            return false;
        }

        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = $this->timeToMinutes($endTime);

        foreach ($teacherSchedules[$teacherId] as $schedule) {
            if ($schedule['day'] !== $day) {
                continue;
            }

            $scheduleStart = $this->timeToMinutes($schedule['start']);
            $scheduleEnd = $this->timeToMinutes($schedule['end']);

            // Cek overlap
            if ($startMinutes < $scheduleEnd && $endMinutes > $scheduleStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tandai slot sebagai dipakai
     */
    private function markSlotAsUsed(array $slots, int $day, string $startTime, string $endTime): array
    {
        if (!isset($slots[$day])) {
            return $slots;
        }

        foreach ($slots[$day] as &$slot) {
            if ($slot['start_time'] === $startTime && $slot['end_time'] === $endTime) {
                $slot['is_available'] = false;
                break;
            }
        }

        return $slots;
    }

    /**
     * Buat teaching assignment
     */
    private function createTeachingAssignment(
        int $teacherId,
        int $subjectId,
        int $schoolClassId,
        string $academicYear,
        int $dayOfWeek,
        string $startTime,
        string $endTime
    ): TeachingAssignment {
        return TeachingAssignment::create([
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'school_class_id' => $schoolClassId,
            'academic_year' => $academicYear,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room' => $this->allocateRoom($schoolClassId),
        ]);
    }

    /**
     * Alokasikan ruangan (bisa dikustom sesuai kebutuhan)
     */
    private function allocateRoom(int $classId): string
    {
        $class = SchoolClass::find($classId);
        return $class ? 'Ruang ' . $class->name : 'Ruang Umum';
    }

    /**
     * Ambil kelas yang akan dijadwalkan
     */
    private function getClassesToSchedule(string $academicYear, ?int $schoolClassId = null): Collection
    {
        $query = SchoolClass::query()
            ->with('subjects')
            ->where('academic_year', $academicYear);

        if ($schoolClassId) {
            $query->where('id', $schoolClassId);
        }

        return $query->get();
    }

    /**
     * Konversi jam:menit ke menit
     */
    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return (int)$hours * 60 + (int)$minutes;
    }

    /**
     * Konversi menit ke jam:menit
     */
    private function minutesToTime(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    private function isClassBusy(array $slots, int $day, string $startTime, string $endTime): bool
    {
        if (!isset($slots[$day])) {
            return true;
        }

        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = $this->timeToMinutes($endTime);

        foreach ($slots[$day] as $slot) {
            $slotStart = $this->timeToMinutes($slot['start_time']);
            $slotEnd = $this->timeToMinutes($slot['end_time']);

            if ($startMinutes < $slotEnd && $endMinutes > $slotStart) {
                if (!$slot['is_available']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function markTimeRangeAsUsed(array $slots, int $day, string $startTime, string $endTime): array
    {
        if (!isset($slots[$day])) {
            return $slots;
        }

        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = $this->timeToMinutes($endTime);

        foreach ($slots[$day] as &$slot) {
            $slotStart = $this->timeToMinutes($slot['start_time']);
            $slotEnd = $this->timeToMinutes($slot['end_time']);

            if ($startMinutes < $slotEnd && $endMinutes > $slotStart) {
                $slot['is_available'] = false;
            }
        }

        return $slots;
    }

    /**
     * Dapatkan nama hari
     */
    private function getDayName(int $dayOfWeek): string
    {
        $days = config('schedule.day_names');
        return $days[$dayOfWeek] ?? 'Tidak Diketahui';
    }

    /**
     * Clear existing schedule untuk academic year tertentu
     */
    public function clearSchedule(string $academicYear, ?int $schoolClassId = null): int
    {
        $query = TeachingAssignment::query()
            ->where('academic_year', $academicYear);

        if ($schoolClassId) {
            $query->where('school_class_id', $schoolClassId);
        }

        return $query->delete();
    }

    /**
     * Validasi schedule sebelum generation
     */
    public function validateBeforeGeneration(string $academicYear): array
    {
        $errors = [];
        $warnings = [];

        // Cek apakah ada subject yang tidak memiliki teacher
        $subjectsWithoutTeachers = \DB::table('subjects')
            ->whereNotExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('subject_teacher')
                    ->whereColumn('subject_teacher.subject_id', 'subjects.id');
            })
            ->get();

        if ($subjectsWithoutTeachers->isNotEmpty()) {
            $subjects = $subjectsWithoutTeachers->pluck('name')->join(', ');
            $warnings[] = "Mapel tanpa guru: {$subjects}";
        }

        // Cek apakah ada class yang tidak memiliki subject
        $classesWithoutSubjects = SchoolClass::query()
            ->where('academic_year', $academicYear)
            ->whereDoesntHave('subjects')
            ->pluck('name');

        if ($classesWithoutSubjects->isNotEmpty()) {
            $warnings[] = "Kelas tanpa mapel: " . $classesWithoutSubjects->join(', ');
        }

        // Cek apakah ada guru yang belum dijadwalkan dalam academic year ini
        $unscheduledTeachers = Teacher::query()
            ->whereDoesntHave('teachingAssignments', fn($q) => $q->where('academic_year', $academicYear))
            ->where('is_subject_teacher', true)
            ->pluck('name');

        if ($unscheduledTeachers->isNotEmpty()) {
            $warnings[] = "Guru tanpa jadwal: " . $unscheduledTeachers->join(', ');
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}
