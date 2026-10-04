<?php

namespace App\Console\Commands;

use App\Services\ScheduleOptimizerService;
use Illuminate\Console\Command;

class AnalyzeScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:analyze {academicYear} {--report} {--conflicts-only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analisis jadwal untuk deteksi konflik dan rekomendasi perbaikan';

    public function __construct(
        private ScheduleOptimizerService $optimizer
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $academicYear = $this->argument('academicYear');
        $report = $this->option('report');
        $conflictsOnly = $this->option('conflicts-only');

        $this->info("=== Schedule Analyzer ===");
        $this->info("Tahun Akademik: {$academicYear}");
        $this->newLine();

        // 1. Deteksi Konflik Guru
        $this->info("🔍 Deteksi Konflik...");
        $teacherConflicts = $this->optimizer->detectTeacherConflicts($academicYear);

        if (!empty($teacherConflicts)) {
            $this->error("⚠️  Konflik Jadwal Guru Ditemukan ({$this->getCount($teacherConflicts)}):");
            foreach ($teacherConflicts as $conflict) {
                $this->newLine();
                $this->warn("  • {$conflict['teacher_name']}");
                $this->line("    {$conflict['assignment1']['subject']} - {$conflict['assignment1']['class']}");
                $this->line("    {$conflict['assignment1']['day']} {$conflict['assignment1']['time']}");
                $this->line("    VS");
                $this->line("    {$conflict['assignment2']['subject']} - {$conflict['assignment2']['class']}");
                $this->line("    {$conflict['assignment2']['day']} {$conflict['assignment2']['time']}");
            }
        } else {
            $this->info("✅ Tidak ada konflik jadwal guru");
        }

        $this->newLine();

        // 2. Deteksi Konflik Ruangan
        $roomConflicts = $this->optimizer->detectRoomConflicts($academicYear);

        if (!empty($roomConflicts)) {
            $this->error("⚠️  Konflik Ruangan Ditemukan ({$this->getCount($roomConflicts)}):");
            foreach ($roomConflicts as $conflict) {
                $this->newLine();
                $this->warn("  • Ruang: {$conflict['room']}");
                $this->line("    1. {$conflict['assignment1']['class']} - {$conflict['assignment1']['subject']}");
                $this->line("       {$conflict['assignment1']['day']} {$conflict['assignment1']['time']}");
                $this->line("    2. {$conflict['assignment2']['class']} - {$conflict['assignment2']['subject']}");
                $this->line("       {$conflict['assignment2']['day']} {$conflict['assignment2']['time']}");
            }
        } else {
            $this->info("✅ Tidak ada konflik ruangan");
        }

        if ($conflictsOnly) {
            return self::SUCCESS;
        }

        $this->newLine();

        // 3. Analisis Beban Kerja Guru
        $this->info("👨‍🏫 Analisis Beban Kerja Guru:");
        $workloads = $this->optimizer->analyzeTeacherWorkload($academicYear);

        if (empty($workloads)) {
            $this->info("  Tidak ada guru dengan jadwal");
        } else {
            foreach ($workloads as $workload) {
                $statusColor = $workload['is_overloaded'] ? 'error' : 'line';
                $statusIcon = $workload['is_overloaded'] ? '⚠️' : '✓';

                $this->$statusColor("  {$statusIcon} {$workload['teacher_name']}");
                $this->line("     • Jam/minggu: {$workload['total_hours_per_week']} jam");
                $this->line("     • Sesi/minggu: {$workload['sessions_per_week']}");
                $this->line("     • Mapel: {$workload['unique_subjects']}");
                $this->line("     • Kelas: {$workload['unique_classes']}");
                $this->line("     • Status: {$workload['workload_status']}");
                $this->newLine();
            }
        }

        // 4. Rekomendasi Perbaikan
        $this->info("💡 Rekomendasi Perbaikan:");
        $recommendations = $this->optimizer->getRecommendations($academicYear);

        if (empty($recommendations)) {
            $this->info("  ✅ Jadwal sudah optimal!");
        } else {
            foreach ($recommendations as $rec) {
                $iconMap = [
                    'critical' => '🔴',
                    'high' => '🟠',
                    'medium' => '🟡',
                    'low' => '🔵'
                ];
                $icon = $iconMap[$rec['severity']] ?? '●';

                $this->newLine();
                $this->warn("{$icon} {$rec['title']}");
                $this->line("   Tipe: {$rec['type']}");
                $this->line("   Deskripsi: {$rec['description']}");
                $this->line("   Terpengaruh: {$rec['affected_count']} item");
                $this->line("   Aksi: {$rec['action']}");

                if (isset($rec['details']) && is_array($rec['details'])) {
                    $this->line("   Detail:");
                    foreach ((array)$rec['details'] as $detail) {
                        if (is_array($detail)) {
                            foreach ($detail as $key => $value) {
                                if (!is_array($value)) {
                                    $this->line("      - {$key}: {$value}");
                                }
                            }
                        } else {
                            $this->line("      - {$detail}");
                        }
                    }
                }
            }
        }

        $this->newLine();

        // Generate report jika diminta
        if ($report) {
            $this->info("📊 Generate Laporan Lengkap...");
            $reportData = $this->optimizer->generateScheduleReport($academicYear);

            // Save to file
            $filename = storage_path("app/schedule_report_{$academicYear}_" . date('Y-m-d_H-i-s') . '.json');
            file_put_contents($filename, json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info("✅ Laporan disimpan ke: {$filename}");
        }

        $this->info("✅ Analisis selesai!");
        return self::SUCCESS;
    }

    /**
     * Get count of conflicts safely
     */
    private function getCount($data): string
    {
        $count = is_array($data) ? count($data) : (is_countable($data) ? count($data) : 0);
        return "{$count} item";
    }
}
