<?php

namespace App\Console\Commands;

use App\Services\ScheduleGeneratorService;
use Illuminate\Console\Command;

class GenerateScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:generate {academicYear} {--class=} {--force} {--validate-only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate jadwal mengajar otomatis untuk kelas dan guru';

    public function __construct(
        private ScheduleGeneratorService $scheduleGenerator
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $academicYear = $this->argument('academicYear');
        $classId = $this->option('class');
        $force = $this->option('force');
        $validateOnly = $this->option('validate-only');

        $this->info("=== Schedule Generator ===");
        $this->info("Tahun Akademik: {$academicYear}");
        $this->info("Kelas: " . ($classId ? "ID {$classId}" : "Semua Kelas"));
        $this->newLine();

        // Validasi data sebelum generate
        $this->info("🔍 Validasi data...");
        $validation = $this->scheduleGenerator->validateBeforeGeneration($academicYear);

        if (!empty($validation['warnings'])) {
            $this->warn("⚠️  Peringatan:");
            foreach ($validation['warnings'] as $warning) {
                $this->warn("  • {$warning}");
            }
            $this->newLine();
        }

        if (!empty($validation['errors'])) {
            $this->error("❌ Error:");
            foreach ($validation['errors'] as $error) {
                $this->error("  • {$error}");
            }
            return self::FAILURE;
        }

        if ($validateOnly) {
            $this->info("✅ Validasi berhasil! Tidak ada error ditemukan.");
            return self::SUCCESS;
        }

        // Konfirmasi jika ada existing schedule
        $this->info("Cek existing schedule...");
        $schoolClassId = $classId ? (int)$classId : null;

        if (!$force) {
            // Cek apakah sudah ada schedule
            $existingCount = \App\Models\TeachingAssignment::query()
                ->where('academic_year', $academicYear)
                ->when($schoolClassId, fn($q) => $q->where('school_class_id', $schoolClassId))
                ->count();

            if ($existingCount > 0) {
                if (!$this->confirm("⚠️  Ditemukan {$existingCount} jadwal existing. Hapus dan buat ulang?")) {
                    $this->info("Dibatalkan.");
                    return self::SUCCESS;
                }

                $this->info("Menghapus schedule existing...");
                $deleted = $this->scheduleGenerator->clearSchedule($academicYear, $schoolClassId);
                $this->line("✓ Terhapus: {$deleted} jadwal");
                $this->newLine();
            }
        } else {
            $this->info("Mode force: menghapus schedule existing...");
            $deleted = $this->scheduleGenerator->clearSchedule($academicYear, $schoolClassId);
            $this->line("✓ Terhapus: {$deleted} jadwal");
            $this->newLine();
        }

        // Generate schedule
        $this->info("📅 Generate jadwal...");
        $startTime = microtime(true);

        try {
            $result = $this->scheduleGenerator->generateSchedule($academicYear, $schoolClassId);

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("✅ Schedule berhasil dibuat dalam {$duration}s");
            $this->newLine();

            // Tampilkan statistik
            $this->displayStats($result);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error saat generate schedule: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Tampilkan statistik hasil generation
     */
    private function displayStats(array $result): void
    {
        $this->info("📊 Statistik:");
        $this->line("  • Total Kelas: {$result['total_classes']}");
        $this->line("  • Total Assignment: {$result['total_assignments']}");
        $this->line("  • Jadwal Berhasil: {$result['successful_slots']}");
        $this->line("  • Jadwal Gagal: {$result['failed_slots']}");
        $this->line("  • Konflik Terdeteksi: {$result['conflicts_detected']}");
        $this->newLine();

        // Tampilkan detail per kelas
        if (!empty($result['details'])) {
            $this->info("📝 Detail Per Kelas:");
            foreach ($result['details'] as $detail) {
                if ($detail['total_assignments'] === 0) {
                    continue;
                }

                $successRate = $detail['successful_slots'] > 0
                    ? round(($detail['successful_slots'] / $detail['total_assignments']) * 100, 1)
                    : 0;

                $this->line("");
                $this->info("  Kelas: {$detail['class_name']}");
                $this->line("    Mapel: {$detail['total_assignments']}");
                $this->line("    Berhasil: {$detail['successful_slots']} ({$successRate}%)");

                if ($detail['failed_slots'] > 0) {
                    $this->warn("    Gagal: {$detail['failed_slots']}");
                }

                if ($detail['conflicts_detected'] > 0) {
                    $this->warn("    Konflik: {$detail['conflicts_detected']}");
                }

                // Tampilkan detail mapel yang dijadwalkan
                foreach ($detail['scheduled_subjects'] as $subject) {
                    if ($subject['status'] === 'success') {
                        $this->line("      ✓ {$subject['subject']} - {$subject['teacher']} ({$subject['day']} {$subject['time']})");
                    } else {
                        $this->warn("      ✗ {$subject['subject']} - {$subject['status']}");
                    }
                }
            }
        }

        $this->newLine();
        $this->info("✅ Selesai!");
    }
}
