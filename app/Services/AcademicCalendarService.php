<?php

namespace App\Services;

use App\Models\AcademicCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AcademicCalendarService
{
    public const TYPES = [
        'hari_efektif' => 'Hari Efektif',
        'libur_nasional' => 'Libur Nasional',
        'libur_sekolah' => 'Libur Sekolah',
        'ujian' => 'Ujian',
        'event_sekolah' => 'Event Sekolah',
    ];

    public const ATTENDANCE_DISABLED_TYPES = [
        'libur_nasional',
        'libur_sekolah',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)
            ->latest('start_date')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, AcademicCalendar>
     */
    public function list(array $filters = []): Collection
    {
        return $this->query($filters)
            ->orderBy('start_date')
            ->orderBy('title')
            ->get();
    }

    public function create(array $data): AcademicCalendar
    {
        return AcademicCalendar::query()->create($data);
    }

    public function update(AcademicCalendar $calendar, array $data): AcademicCalendar
    {
        $calendar->update($data);

        return $calendar->refresh();
    }

    public function delete(AcademicCalendar $calendar): void
    {
        $calendar->delete();
    }

    /**
     * @return Collection<int, AcademicCalendar>
     */
    public function upcoming(string $academicYear, string $semester, int $limit = 5): Collection
    {
        $today = CarbonImmutable::today()->toDateString();

        return AcademicCalendar::query()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->limit($limit)
            ->get();
    }

    public function effectiveDays(string $academicYear, string $semester): int
    {
        return AcademicCalendar::query()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->where('type', 'hari_efektif')
            ->get()
            ->sum(fn(AcademicCalendar $event): int => $event->start_date->diffInDays($event->end_date) + 1);
    }

    public function attendanceStatusForDate(string $academicYear, string $semester, string $date): array
    {
        $events = AcademicCalendar::query()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->get();

        $blockingEvent = $events->first(fn(AcademicCalendar $event): bool => $event->is_holiday || in_array($event->type, self::ATTENDANCE_DISABLED_TYPES, true));

        if ($blockingEvent !== null) {
            return [
                'allowed' => false,
                'message' => 'Tanggal ini termasuk ' . $blockingEvent->category . ' pada kalender akademik.',
                'events' => $events,
            ];
        }

        return [
            'allowed' => true,
            'message' => $events->contains(fn(AcademicCalendar $event): bool => $event->type === 'hari_efektif')
                ? 'Tanggal ini tercatat sebagai hari efektif sekolah.'
                : 'Tanggal ini tidak ditandai libur pada kalender akademik.',
            'events' => $events,
        ];
    }

    /**
     * Check if a date falls within an exam period and return its label (e.g. 'UTS', 'UAS').
     */
    public function examTypeForDate(string $academicYear, string $semester, string $date): ?string
    {
        $event = AcademicCalendar::query()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->where('type', 'ujian')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        return $event?->title;
    }

    protected function query(array $filters)
    {
        return AcademicCalendar::query()
            ->with('createdBy:id,name,email')
            ->when($filters['academic_year'] ?? null, fn($query, string $year) => $query->where('academic_year', $year))
            ->when($filters['semester'] ?? null, fn($query, string $semester) => $query->where('semester', $semester))
            ->when($filters['category'] ?? null, fn($query, string $category) => $query->where('category', $category))
            ->when($filters['type'] ?? null, fn($query, string $type) => $query->where('type', $type))
            ->when(array_key_exists('is_holiday', $filters), fn($query) => $query->where('is_holiday', (bool) $filters['is_holiday']))
            ->when(array_key_exists('is_active', $filters), fn($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($filters['date_from'] ?? null, fn($query, string $dateFrom) => $query->whereDate('end_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn($query, string $dateTo) => $query->whereDate('start_date', '<=', $dateTo));
    }
}
