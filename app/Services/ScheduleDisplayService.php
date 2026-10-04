<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/**
 * Service untuk menampilkan jadwal dalam berbagai format
 */
class ScheduleDisplayService
{
    /**
     * Dapatkan jadwal dalam format tabel per kelas
     */
    public function getClassScheduleTable(int $classId, string $academicYear): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "class_schedule_{$classId}_{$academicYear}",
            86400,
            function () use ($classId, $academicYear): array {
                $class = SchoolClass::find($classId);
                if (!$class) {
                    return [];
                }

                $schedule = [];
                $days = config('schedule.day_names');
                $operationalDays = config('schedule.operational_days');

                // Ambil semua jadwal untuk kelas ini
                $assignments = TeachingAssignment::query()
                    ->with(['subject', 'teacher', 'substituteTeacher'])
                    ->where('school_class_id', $classId)
                    ->where('academic_year', $academicYear)
                    ->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get();

                // Kelompokkan per hari
                foreach ($operationalDays as $dayIndex) {
                    $dayName = $days[$dayIndex] ?? 'Hari ' . $dayIndex;
                    $dayAssignments = $assignments->filter(fn($a) => $a->day_of_week === $dayIndex);

                    $schedule[$dayName] = $dayAssignments->map(fn($a) => [
                        'id' => $a->id,
                        'subject_id' => $a->subject_id,
                        'subject' => $a->subject->name,
                        'teacher' => $a->teacher->name,
                        'teacher_id' => $a->teacher_id,
                        'substitute_teacher_id' => $a->substitute_teacher_id,
                        'substitute_teacher' => $a->substituteTeacher?->name,
                        'time' => "{$a->start_time} - {$a->end_time}",
                        'room' => $a->room,
                        'start_time' => $a->start_time,
                        'end_time' => $a->end_time,
                        'day_of_week' => $a->day_of_week,
                        'academic_year' => $a->academic_year,
                        'school_class_id' => $a->school_class_id,
                    ])->toArray();
                }

                return [
                    'class_name' => $class->name,
                    'class_level' => $class->level,
                    'academic_year' => $academicYear,
                    'schedule' => $schedule,
                ];
            }
        );
    }

    /**
     * Dapatkan jadwal dalam format tabel per guru
     */
    public function getTeacherScheduleTable(int $teacherId, string $academicYear): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "teacher_schedule_{$teacherId}_{$academicYear}",
            86400,
            function () use ($teacherId, $academicYear): array {
                $teacher = Teacher::find($teacherId);
                if (!$teacher) {
                    return [];
                }

                $schedule = [];
                $days = config('schedule.day_names');
                $operationalDays = config('schedule.operational_days');

                // Ambil semua jadwal untuk guru ini
                $assignments = TeachingAssignment::query()
                    ->with(['subject', 'schoolClass'])
                    ->where('teacher_id', $teacherId)
                    ->where('academic_year', $academicYear)
                    ->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get();

                // Kelompokkan per hari
                foreach ($operationalDays as $dayIndex) {
                    $dayName = $days[$dayIndex] ?? 'Hari ' . $dayIndex;
                    $dayAssignments = $assignments->filter(fn($a) => $a->day_of_week === $dayIndex);

                    $schedule[$dayName] = $dayAssignments->map(fn($a) => [
                        'subject' => $a->subject->name,
                        'class' => $a->schoolClass->name,
                        'time' => "{$a->start_time} - {$a->end_time}",
                        'room' => $a->room,
                        'start_time' => $a->start_time,
                        'end_time' => $a->end_time,
                    ])->toArray();
                }

                // Hitung statistik
                $totalHours = $this->calculateTotalHours($assignments);
                $uniqueSubjects = $assignments->pluck('subject_id')->unique()->count();
                $uniqueClasses = $assignments->pluck('school_class_id')->unique()->count();

                return [
                    'teacher_name' => $teacher->name,
                    'teacher_id' => $teacher->id,
                    'academic_year' => $academicYear,
                    'schedule' => $schedule,
                    'stats' => [
                        'total_hours_per_week' => round($totalHours, 2),
                        'sessions_per_week' => $assignments->count(),
                        'unique_subjects' => $uniqueSubjects,
                        'unique_classes' => $uniqueClasses,
                    ]
                ];
            }
        );
    }

    /**
     * Dapatkan jadwal dalam format iCal/ICS (untuk kalender)
     */
    public function generateICSCalendar(int $classId, string $academicYear): string
    {
        $class = SchoolClass::find($classId);
        if (!$class) {
            return '';
        }

        $assignments = TeachingAssignment::query()
            ->with(['subject', 'teacher'])
            ->where('school_class_id', $classId)
            ->where('academic_year', $academicYear)
            ->get();

        $icsContent = "BEGIN:VCALENDAR\r\n";
        $icsContent .= "VERSION:2.0\r\n";
        $icsContent .= "PRODID:-//Sarunis//Schedule//EN\r\n";
        $icsContent .= "CALSCALE:GREGORIAN\r\n";
        $icsContent .= "METHOD:PUBLISH\r\n";
        $icsContent .= "X-WR-CALNAME:" . $class->name . " - " . $academicYear . "\r\n";
        $icsContent .= "X-WR-TIMEZONE:Asia/Jakarta\r\n";
        $icsContent .= "BEGIN:VTIMEZONE\r\n";
        $icsContent .= "TZID:Asia/Jakarta\r\n";
        $icsContent .= "BEGIN:STANDARD\r\n";
        $icsContent .= "DTSTART:19700101T000000\r\n";
        $icsContent .= "TZOFFSETFROM:+0700\r\n";
        $icsContent .= "TZOFFSETTO:+0700\r\n";
        $icsContent .= "END:STANDARD\r\n";
        $icsContent .= "END:VTIMEZONE\r\n";

        // Generate events untuk setiap jadwal
        $baseDate = new \DateTime('2026-01-05'); // Mulai dari hari Senin
        $weekStart = $baseDate->modify('Monday this week');

        foreach ($assignments as $assignment) {
            $eventDate = clone $weekStart;
            $eventDate->add(new \DateInterval('P' . $assignment->day_of_week . 'D'));

            $startDateTime = $eventDate->format('Ymd') . 'T' . str_replace(':', '', $assignment->start_time) . '00';
            $endDateTime = $eventDate->format('Ymd') . 'T' . str_replace(':', '', $assignment->end_time) . '00';

            $icsContent .= "BEGIN:VEVENT\r\n";
            $icsContent .= "DTSTART:" . $startDateTime . "\r\n";
            $icsContent .= "DTEND:" . $endDateTime . "\r\n";
            $icsContent .= "SUMMARY:" . $this->escapeString($assignment->subject->name . " - " . $assignment->teacher->name) . "\r\n";
            $icsContent .= "DESCRIPTION:" . $this->escapeString("Guru: {$assignment->teacher->name}\nRuangan: {$assignment->room}") . "\r\n";
            $icsContent .= "LOCATION:" . ($assignment->room ?? "Belum ditentukan") . "\r\n";
            $icsContent .= "UID:" . $assignment->id . "@sarunis.local\r\n";
            $icsContent .= "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";
            $icsContent .= "END:VEVENT\r\n";
        }

        $icsContent .= "END:VCALENDAR\r\n";

        return $icsContent;
    }

    /**
     * Dapatkan jadwal dalam format JSON untuk API
     */
    public function getScheduleJSON(int $classId, string $academicYear): array
    {
        $schedule = $this->getClassScheduleTable($classId, $academicYear);

        return [
            'success' => true,
            'data' => $schedule,
            'meta' => [
                'academic_year' => $academicYear,
                'generated_at' => now()->toIso8601String(),
            ]
        ];
    }

    /**
     * Export jadwal ke format HTML
     */
    public function exportToHTML(int $classId, string $academicYear): string
    {
        $scheduleData = $this->getClassScheduleTable($classId, $academicYear);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal {$scheduleData['class_name']}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        h1 { color: #2c3e50; }
        h2 { color: #34495e; margin-top: 20px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #bdc3c7;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #3498db;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #ecf0f1;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .print-date {
            text-align: right;
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="print-date">Dicetak pada: {date('d-m-Y H:i')}</div>
    <div class="header">
        <h1>JADWAL PELAJARAN</h1>
        <h2>{$scheduleData['class_name']} - {$scheduleData['academic_year']}</h2>
        <p>Tingkat: {$scheduleData['class_level']}</p>
    </div>
HTML;

        foreach ($scheduleData['schedule'] as $day => $sessions) {
            $html .= "<h2>{$day}</h2>\n";

            if (empty($sessions)) {
                $html .= "<p><em>Tidak ada jadwal</em></p>\n";
                continue;
            }

            $html .= "<table>\n";
            $html .= "<tr><th>Jam</th><th>Mapel</th><th>Guru</th><th>Ruangan</th></tr>\n";

            foreach ($sessions as $session) {
                $html .= "<tr>";
                $html .= "<td>{$session['time']}</td>";
                $html .= "<td>{$session['subject']}</td>";
                $html .= "<td>{$session['teacher']}</td>";
                $html .= "<td>" . ($session['room'] ?? '-') . "</td>";
                $html .= "</tr>\n";
            }

            $html .= "</table>\n";
        }

        $html .= "</body>\n</html>";

        return $html;
    }

    /**
     * Get jadwal dalam format CSV
     */
    public function exportToCSV(int $classId, string $academicYear): string
    {
        $scheduleData = $this->getClassScheduleTable($classId, $academicYear);

        $csv = "Jadwal Pelajaran - {$scheduleData['class_name']}\n";
        $csv .= "Tahun Akademik: {$scheduleData['academic_year']}\n";
        $csv .= "Tingkat: {$scheduleData['class_level']}\n";
        $csv .= "Dicetak: " . date('d-m-Y H:i') . "\n";
        $csv .= "\n";

        foreach ($scheduleData['schedule'] as $day => $sessions) {
            $csv .= "\n{$day}\n";
            $csv .= "Jam,Mapel,Guru,Ruangan\n";

            if (empty($sessions)) {
                $csv .= "Tidak ada jadwal\n";
                continue;
            }

            foreach ($sessions as $session) {
                $csv .= "\"{$session['time']}\",\"{$session['subject']}\",\"{$session['teacher']}\",\"" . ($session['room'] ?? '-') . "\"\n";
            }
        }

        return $csv;
    }

    // ===== Helper Methods =====

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

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return (int)$hours * 60 + (int)$minutes;
    }

    private function escapeString(string $str): string
    {
        return str_replace([',', ';', '\n'], ['\,', '\;', '\\n'], $str);
    }
}
